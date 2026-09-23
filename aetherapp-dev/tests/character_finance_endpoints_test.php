<?php
declare(strict_types=1);

if (defined('AETHER_FINANCE_TEST_BOOTSTRAP')) {
    final class FinanceTestStatement extends PDOStatement
    {
        private array $params = [];
        private array $rows = [];
        private int $rowCountValue = 0;
        public function __construct(private FinanceTestPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }
        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $this->rows = [];
            $this->rowCountValue = 0;
            $sql = $this->sql();
            if (str_starts_with($sql, 'INSERT INTO tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                if (!isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key] = [
                        'payloadHash' => $this->params['payloadHash'], 'status' => 'processing',
                        'responseStatus' => null, 'responseJson' => null,
                    ];
                    $this->pdo->write('idempotency_insert');
                }
            } elseif (str_starts_with($sql, 'UPDATE tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                $this->pdo->idempotency[$key]['status'] = 'completed';
                $this->pdo->idempotency[$key]['responseStatus'] = 200;
                $this->pdo->idempotency[$key]['responseJson'] = $this->params['responseJson'];
                $this->pdo->write('idempotency_complete');
                $this->rowCountValue = 1;
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterBankTransaction')) {
                $id = $this->pdo->nextTransactionId++;
                $this->pdo->lastId = $id;
                $this->pdo->transactions[$id] = $this->params + ['id' => $id];
                $this->pdo->write('bank_history');
                $this->rowCountValue = 1;
            } elseif (str_starts_with($sql, 'UPDATE tblCharacter SET bankaccount = bankaccount +')) {
                $id = (int) $this->params['idCharacter'];
                if ($this->pdo->scenario === 'credit_error' && $id === 2) {
                    throw new PDOException('SQLSTATE[HY000]: secret balance table');
                }
                $minor = static fn(string $value): int => (int) round(((float) $value) * 100);
                $current = $minor((string) $this->pdo->characters[$id]['bankaccount']);
                $delta = $minor((string) $this->params['amount']);
                $this->pdo->characters[$id]['bankaccount'] = number_format(($current + $delta) / 100, 2, '.', '');
                $this->pdo->write('balance:' . $id);
                $this->rowCountValue = 1;
            }
            return true;
        }
        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser')) return $this->pdo->users[(int) ($this->params['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                return $this->pdo->idempotency[$key] ?? false;
            }
            return $this->rows[0] ?? false;
        }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblCharacter') && str_contains($sql, 'WHERE id IN')) {
                $rows = [];
                foreach ($this->params as $id) if (isset($this->pdo->characters[(int) $id])) $rows[] = $this->pdo->characters[(int) $id];
                usort($rows, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
                return $rows;
            }
            return $this->rows;
        }
        public function fetchColumn(int $column = 0): mixed { $row=$this->fetch(); return $row===false?false:(array_values($row)[$column]??false); }
        public function rowCount(): int { return $this->rowCountValue; }
    }

    final class FinanceTestPdo extends PDO
    {
        public array $users = [
            10 => ['id'=>10,'username'=>'participant','firstName'=>'Part','lastName'=>'Icipant','role'=>'participant'],
            20 => ['id'=>20,'username'=>'director','firstName'=>'Di','lastName'=>'Rector','role'=>'director'],
            30 => ['id'=>30,'username'=>'admin','firstName'=>'Ad','lastName'=>'Min','role'=>'administrator'],
        ];
        public array $characters = [
            1 => ['id'=>1,'idUser'=>10,'type'=>'player','state'=>'active','class'=>'middle class','bankaccount'=>'100.00','securitiesaccount'=>'0.00','securitiesManagerType'=>'self','securitiesManagerCharacterId'=>null,'securitiesRiskProfile'=>3],
            2 => ['id'=>2,'idUser'=>99,'type'=>'player','state'=>'active','class'=>'middle class','bankaccount'=>'20.00','securitiesaccount'=>'0.00','securitiesManagerType'=>'self','securitiesManagerCharacterId'=>null,'securitiesRiskProfile'=>3],
        ];
        public array $transactions = [], $idempotency = [];
        public int $nextTransactionId=50, $lastId=0, $committedWrites=0, $rollbacks=0;
        private ?array $snapshot=null;
        private int $stagedWrites=0;
        public function __construct(public string $scenario) {
            if (in_array($scenario, ['replay','replay_revoked','conflict'], true)) {
                $payload = ['idSourceCharacter'=>1,'idTargetCharacter'=>2,'amount'=>'10.00','description'=>'','transactionDate'=>'1926-09-21'];
                ksort($payload, SORT_STRING);
                $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
                if ($scenario === 'conflict') $hash = str_repeat('f', 64);
                $this->idempotency['20|character.bank_transfer.create|finance-request-0001'] = [
                    'payloadHash'=>$hash,'status'=>'completed','responseStatus'=>200,
                    'responseJson'=>'{"success":true,"idTransaction":49}',
                ];
                if ($scenario === 'replay_revoked') {
                    $this->users[20]['role'] = 'participant';
                }
            }
        }
        public function prepare(string $query,array $options=[]):PDOStatement|false{return new FinanceTestStatement($this,$query);}
        public function beginTransaction():bool{$this->snapshot=[$this->characters,$this->transactions,$this->idempotency];$this->stagedWrites=0;return true;}
        public function commit():bool{$this->committedWrites+=$this->stagedWrites;$this->stagedWrites=0;$this->snapshot=null;return true;}
        public function rollBack():bool{if($this->snapshot){[$this->characters,$this->transactions,$this->idempotency]=$this->snapshot;}$this->snapshot=null;$this->stagedWrites=0;$this->rollbacks++;return true;}
        public function inTransaction():bool{return $this->snapshot!==null;}
        public function lastInsertId(?string $name=null):string|false{return (string)$this->lastId;}
        public function write(string $kind):void{if($this->inTransaction())$this->stagedWrites++;else$this->committedWrites++;}
    }

    function getPDO(): PDO { global $pdo; return $pdo; }
    function dbAll(PDO $pdo,string $sql,array $params=[]):array{$s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}
    function dbOne(PDO $pdo,string $sql,array $params=[]):?array{$s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}

    $scenario=(string)($argv[1]??'success');
    $pdo=new FinanceTestPdo($scenario);
    $userId=match($scenario){'unauthenticated'=>999,'participant'=>10,'administrator'=>30,default=>20};
    session_start();
    $_SESSION=['user'=>['id'=>$userId],'aetherCsrfToken'=>'expected-token'];
    if($scenario!=='missing_csrf')$_SERVER['HTTP_X_CSRF_TOKEN']=$scenario==='invalid_csrf'?'bad':'expected-token';
    if($scenario!=='missing_key')$_SERVER['HTTP_IDEMPOTENCY_KEY']='finance-request-0001';
    register_shutdown_function(static function()use($pdo):void{
        $status=http_response_code();
        fwrite(STDERR,'__FINANCE_STATE__:'.json_encode([
            'status'=>$status===false?200:$status,'writes'=>$pdo->committedWrites,'rollbacks'=>$pdo->rollbacks,
            'characters'=>$pdo->characters,'transactions'=>$pdo->transactions,
        ])."\n");
    });
    return;
}

$root=dirname(__DIR__);
$fixture=sys_get_temp_dir().'/aether-finance-'.bin2hex(random_bytes(5));
function financeCopy(string $from,string $to):void{if(!is_dir($to))mkdir($to,0777,true);foreach(new DirectoryIterator($from)as$i){if($i->isDot())continue;$d=$to.'/'.$i->getFilename();$i->isDir()?financeCopy($i->getPathname(),$d):copy($i->getPathname(),$d);}}
function financeRemove(string $path):void{if(!is_dir($path))return;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$i){$i->isDir()?rmdir($i->getPathname()):unlink($i->getPathname());}rmdir($path);}
financeCopy($root.'/api',$fixture.'/api');
copy($root.'/sessionUserBootstrap.php',$fixture.'/sessionUserBootstrap.php');
file_put_contents($fixture.'/db.php',"<?php\ndefine('AETHER_FINANCE_TEST_BOOTSTRAP',true);require ".var_export(__FILE__,true).";\n");
$route=$fixture.'/api/characters/saveBankTransfer.php';
$php=PHP_BINARY;
$failures=[];
function financeAssert(bool $ok,string $message):void{global$failures;if(!$ok)$failures[]=$message;}
function financeRun(string $php,string $route,string $scenario,array|string $body):array{
    $cmd=escapeshellarg($php).' '.escapeshellarg($route).' '.escapeshellarg($scenario);
    $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],is_string($body)?$body:json_encode($body));fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($p);
    preg_match('/__FINANCE_STATE__:(\{.*\})/',$err,$m);$state=json_decode($m[1]??'{}',true);
    return ['body'=>$out,'exit'=>$exit]+$state;
}
$payload=['idSourceCharacter'=>1,'idTargetCharacter'=>2,'amount'=>10,'description'=>'','transactionDate'=>'1926-09-21'];
try{
    $ok=financeRun($php,$route,'success',$payload);
    financeAssert($ok['status']===200&&json_decode($ok['body'],true)===['success'=>true,'idTransaction'=>50],'Geldige betaling heeft geen compatibele response.');
    financeAssert($ok['characters'][1]['bankaccount']==='90.00'&&$ok['characters'][2]['bankaccount']==='30.00'&&count($ok['transactions'])===1,'Geldige betaling wijzigt saldi/historie niet atomair.');
    $replay=financeRun($php,$route,'replay',$payload);
    financeAssert($replay['status']===200&&json_decode($replay['body'],true)===['success'=>true,'idTransaction'=>49]&&$replay['writes']===0,'Replay voert de betaling opnieuw uit: '.json_encode($replay));
    $revokedReplay=financeRun($php,$route,'replay_revoked',$payload);
    financeAssert($revokedReplay['status']===403&&$revokedReplay['writes']===0,
        'Replay omzeilt de actuele rol- en objecttoegang: '.json_encode($revokedReplay));
    $conflict=financeRun($php,$route,'conflict',$payload);
    financeAssert($conflict['status']===409&&$conflict['writes']===0,'Dezelfde sleutel met andere payload wordt niet zonder writes geweigerd.');
    $rollback=financeRun($php,$route,'credit_error',$payload);
    financeAssert($rollback['status']===500&&$rollback['characters'][1]['bankaccount']==='100.00'&&$rollback['characters'][2]['bankaccount']==='20.00'&&count($rollback['transactions'])===0&&$rollback['rollbacks']===1,'Fout tussen af- en bijschrijving rolt niet volledig terug.');
    financeAssert(!str_contains($rollback['body'],'SQLSTATE')&&!str_contains($rollback['body'],'secret balance table'),'Generieke HTTP 500 lekt technische details.');
    $insufficient=financeRun($php,$route,'success',array_merge($payload,['amount'=>'100.01']));
    financeAssert($insufficient['status']===400&&$insufficient['writes']===0,'Onvoldoende saldo wordt niet vóór domeinwrites geweigerd.');
    $precision=financeRun($php,$route,'success',array_merge($payload,['amount'=>'1.001']));
    financeAssert($precision['status']===422&&$precision['writes']===0,'Ongeldige precisie wordt niet met 422 geweigerd.');
    $withoutDate=$payload;
    unset($withoutDate['transactionDate']);
    $defaultDate=financeRun($php,$route,'success',$withoutDate);
    $savedTransaction=array_values($defaultDate['transactions'])[0]??[];
    $expectedDefaultDate=(new DateTimeImmutable('today'))->modify('-100 years')->format('Y-m-d');
    financeAssert($defaultDate['status']===200&&($savedTransaction['transactionDate']??null)===$expectedDefaultDate,
        'Het bestaande standaarddatumcontract voor overschrijvingen is niet behouden.');
    foreach(['participant'=>403,'invalid_csrf'=>403,'missing_csrf'=>403,'unauthenticated'=>401,'missing_key'=>400]as$scenario=>$status){
        $result=financeRun($php,$route,$scenario,$payload);
        financeAssert($result['status']===$status&&$result['writes']===0,"{$scenario} wordt niet zonder writes geweigerd.");
    }
    require_once $root.'/api/shared/decimal.php';
    require_once $root.'/api/shared/validation.php';
    require_once $root.'/api/companies/companyFinanceSchemas.php';
    financeAssert(aetherDecimalMultiplyRatio('10.01',75,100)==='7.51','Exacte 75%-afronding is fout.');
    $companyInput=aetherValidateInput([
        'id'=>'4','companyName'=>'  Testbedrijf  ','companyValue'=>'1234.50','stability'=>'2','profitability'=>-1,
    ],aetherCompanyUpdateSchema());
    financeAssert($companyInput['companyName']==='Testbedrijf'&&$companyInput['companyValue']==='1234.50','Company-validatie normaliseert het contract niet correct.');
    try{
        aetherValidateInput(['action'=>'delete','idCompany'=>4,'idCompanySnapshot'=>8,'unexpected'=>true],aetherCompanySnapshotSchema(['action'=>'delete']));
        financeAssert(false,'Onverwacht company-snapshotveld wordt aanvaard.');
    }catch(AetherValidationException $e){
        financeAssert(in_array('unknown_field',array_column($e->getErrors(),'code'),true),'Onverwacht company-snapshotveld heeft geen uniforme validatiefout.');
    }
    $shareService=(string)file_get_contents($root.'/api/characters/characterShareService.php');
    $financeRepo=(string)file_get_contents($root.'/api/characters/characterFinanceRepository.php');
    $mainJs=(string)file_get_contents($root.'/js/mainFunctions.js');
    $economyJs=(string)file_get_contents($root.'/js/economyCharacter.js');
    $companyJs=(string)file_get_contents($root.'/js/companyFunctions.js');
    $navJs=(string)file_get_contents($root.'/js/navCharacter.js');
    financeAssert(str_contains($shareService,'aetherShareLockCompanies')&&str_contains($shareService,'aetherFinanceLockCharacter'),'Aandelentransacties missen company- en characterlocks.');
    financeAssert(str_contains($financeRepo,'ORDER BY id')&&str_contains($financeRepo,'FOR UPDATE'),'Financiële records worden niet in vaste volgorde vergrendeld.');
    financeAssert(str_contains($mainJs,'async function apiFetchFinancialJson')
        && str_contains($mainJs,'sessionStorage.setItem')
        && str_contains($mainJs,'"Idempotency-Key"')
        && str_contains($mainJs,'error.status < 500'),
        'De frontend bewaart of hergebruikt financiële request-ID’s niet correct.');
    foreach(['saveBankTransfer.php','deleteBankTransaction.php','buyCompanyShare.php','saveCompanyShare.php',
        'saveCharacterEconomySnapshot.php','deleteCharacterEconomySnapshot.php','saveCharacterSecuritiesPortfolio.php']as$route){
        financeAssert(str_contains($economyJs,'apiFetchFinancialJson("api/characters/'.$route),'Frontendroute '.$route.' gebruikt geen financiële fetchhelper.');
    }
    financeAssert(substr_count($companyJs,'apiFetchFinancialJson("api/companies/saveCompanySnapshot.php')===5,
        'Niet alle company-snapshotmutaties gebruiken idempotentie.');
    financeAssert(str_contains($companyJs,'apiFetchFinancialJson("api/companies/updateCompany.php'),
        'Een rechtstreekse bedrijfswaardewijziging gebruikt geen idempotentie.');
    financeAssert(preg_match('/apiFetchFinancialJson\("api\/characters\/updateCharacter\.php"[\s\S]{0,220}state: newState/',$navJs)===1,
        'Een statusovergang die het startsaldo kan instellen gebruikt geen idempotentie.');
}finally{financeRemove($fixture);}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}
echo "Character finance endpoint tests passed.\n";
