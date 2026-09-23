<?php
declare(strict_types=1);

if (defined('AETHER_COMPANY_ENDPOINT_BOOTSTRAP')) {
    final class CompanyTestStatement extends PDOStatement
    {
        private array $params = [];
        private array $rows = [];
        private int $affected = 0;
        public function __construct(private CompanyTestPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }
        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $this->rows = [];
            $this->affected = 0;
            $sql = $this->sql();
            $p = $this->params;
            if (str_contains($sql, 'FROM tblCompany WHERE') && str_contains($sql, 'FOR UPDATE')) $this->pdo->locks[] = 'company';
            if (str_contains($sql, 'FROM tblCompanySnapshot AS cs') && str_contains($sql, 'FOR UPDATE')) $this->pdo->locks[] = 'snapshot';
            if (str_starts_with($sql, 'INSERT INTO tblApiIdempotency')) {
                $key = $p['idUser'].'|'.$p['operation'].'|'.$p['requestKey'];
                if (!isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key] = ['payloadHash'=>$p['payloadHash'], 'status'=>'processing', 'responseStatus'=>null, 'responseJson'=>null];
                    $this->pdo->write($sql, $p);
                }
            } elseif (str_starts_with($sql, 'UPDATE tblApiIdempotency')) {
                $key = $p['idUser'].'|'.$p['operation'].'|'.$p['requestKey'];
                $this->pdo->idempotency[$key]['status'] = 'completed';
                $this->pdo->idempotency[$key]['responseStatus'] = 200;
                $this->pdo->idempotency[$key]['responseJson'] = $p['responseJson'];
                $this->pdo->write($sql, $p);
                $this->affected = 1;
            } elseif (str_starts_with($sql, 'INSERT INTO tblCompany ')) {
                $id = $this->pdo->nextCompanyId++;
                $this->pdo->lastId = $id;
                $this->pdo->companies[$id] = ['id'=>$id] + $p;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'UPDATE tblCompany SET')) {
                $id = (int) ($p['id'] ?? $p['idCompany']);
                foreach ($p as $name => $value) if ($name !== 'id' && $name !== 'idCompany') $this->pdo->companies[$id][$name] = $value;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'UPDATE tblCharacter SET bankaccount')) {
                $id = (int) $p['idCharacter'];
                $current = (string) $this->pdo->characters[$id]['bankaccount'];
                $this->pdo->characters[$id]['bankaccount'] = aetherDecimalAdd($current, (string) $p['amount']);
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'INSERT INTO tblCompanySnapshotPayout')) {
                if ($this->pdo->scenario === 'payout_error') throw new PDOException('SQLSTATE[HY000]: secret_payout_table');
                $this->pdo->payouts[] = $p;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'INSERT INTO tblCompanySnapshot (')) {
                $id = $this->pdo->nextSnapshotId++;
                $this->pdo->lastId = $id;
                $this->pdo->snapshots[$id] = ['id'=>$id] + $p;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'UPDATE tblCompanySnapshot')) {
                $id = (int) $p['idCompanySnapshot'];
                foreach ($p as $name=>$value) if ($name !== 'idCompanySnapshot' && $name !== 'idCompany') $this->pdo->snapshots[$id][$name] = $value;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation')) {
                $this->pdo->personnelSpecs = [];
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'DELETE cps FROM tblCompanyPersonnelSkill')) {
                $this->pdo->personnelSkills = [];
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'DELETE FROM tblCompanyPersonnel')) {
                $this->pdo->personnel = array_filter($this->pdo->personnel, static fn(array $row): bool => $row['idCompany'] !== (int) $p['idCompany']);
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'INSERT INTO tblCompanyPersonnel (')) {
                $id = $this->pdo->nextPersonnelId++;
                $this->pdo->lastId = $id;
                $this->pdo->personnel[$id] = ['id'=>$id] + $p;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'INSERT INTO tblCompanyPersonnelSkill (')) {
                if ($this->pdo->scenario === 'mid_error') throw new PDOException('SQLSTATE[HY000]: secret_personnel_table');
                $id = $this->pdo->nextPersonnelSkillId++;
                $this->pdo->lastId = $id;
                $this->pdo->personnelSkills[$id] = ['id'=>$id] + $p;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'INSERT INTO tblCompanyPersonnelSkillSpecialisation')) {
                $this->pdo->personnelSpecs[] = $p;
                $this->pdo->write($sql, $p);
            } elseif (str_starts_with($sql, 'INSERT INTO tblSkillSpecialisation')) {
                $id = $this->pdo->nextSpecId++;
                $this->pdo->lastId = $id;
                $this->pdo->specialisations[$id] = ['id'=>$id] + $p;
                $this->pdo->write($sql, $p);
            }
            return true;
        }
        public function rowCount(): int { return $this->affected; }
        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql(); $p = $this->params;
            if (str_contains($sql, 'FROM tblUser')) return $this->pdo->users[(int) ($p['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM tblApiIdempotency')) return $this->pdo->idempotency[$p['idUser'].'|'.$p['operation'].'|'.$p['requestKey']] ?? false;
            if (str_contains($sql, 'FROM tblCompany WHERE id')) return $this->pdo->companies[(int) ($p['idCompany'] ?? $p['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM tblEvent WHERE id')) return $this->pdo->events[(int) $p['idEvent']] ?? false;
            if (str_contains($sql, 'FROM tblCompanySnapshot AS cs')) {
                $snapshot = $this->pdo->snapshots[(int) $p['idCompanySnapshot']] ?? false;
                if ($snapshot === false || (int) $snapshot['idCompany'] !== (int) $p['idCompany']) return false;
                $event = $this->pdo->events[(int) $snapshot['idEvent']];
                return $snapshot + ['title'=>$event['title'], 'dateStart'=>$event['dateStart']];
            }
            if (str_contains($sql, 'FROM tblCompanySnapshot WHERE idCompany')) {
                foreach ($this->pdo->snapshots as $row) if ((int) $row['idCompany']===(int)$p['idCompany'] && (int)$row['idEvent']===(int)$p['idEvent']) return $row;
            }
            if (str_contains($sql, 'FROM tblCharacter WHERE id')) {
                $row = $this->pdo->characters[(int) $p['idCharacter']] ?? false;
                return $row && $row['state'] !== 'draft' ? $row : false;
            }
            if (str_contains($sql, 'FROM tblSkill WHERE id')) return $this->pdo->skills[(int) $p['idSkill']] ?? false;
            if (str_contains($sql, 'FROM tblSkillSpecialisation WHERE id =')) {
                $row = $this->pdo->specialisations[(int) $p['idSkillSpecialisation']] ?? false;
                return $row && $row['idSkill'] === (int) $p['idSkill'] ? $row : false;
            }
            if (str_contains($sql, 'FROM tblSkillSpecialisation WHERE idSkill')) {
                foreach ($this->pdo->specialisations as $row) if ($row['idSkill'] === (int) $p['idSkill'] && strcasecmp($row['name'], $p['name']) === 0) return $row;
            }
            return false;
        }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            if (str_contains($this->sql(), 'FROM tblCompany ORDER BY')) {
                $rows = array_map(static fn(array $row): array => ['id'=>$row['id'], 'companyName'=>$row['companyName']], array_values($this->pdo->companies));
                usort($rows, static fn(array $a, array $b): int => strcmp($a['companyName'], $b['companyName']) ?: $a['id'] <=> $b['id']);
                return $rows;
            }
            return $this->rows;
        }
    }

    final class CompanyTestPdo extends PDO
    {
        public array $users, $companies, $characters, $skills, $specialisations, $personnel, $personnelSkills, $personnelSpecs, $idempotency, $events, $snapshots, $payouts;
        public int $nextCompanyId, $nextPersonnelId, $nextPersonnelSkillId, $nextSpecId, $nextSnapshotId, $lastId = 0, $writes = 0, $rollbacks = 0;
        public array $writeLog = [];
        public array $locks = [];
        private ?array $before = null;
        public function __construct(public string $scenario, array $state) { foreach ($state as $key=>$value) if (property_exists($this, $key)) $this->{$key} = $value; }
        public function prepare(string $query, array $options = []): PDOStatement|false { return new CompanyTestStatement($this, $query); }
        public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
        public function inTransaction(): bool { return $this->before !== null; }
        public function beginTransaction(): bool { $this->before = $this->exportState(); return true; }
        public function commit(): bool { $this->before = null; return true; }
        public function rollBack(): bool { foreach ($this->before ?? [] as $key=>$value) $this->{$key}=$value; $this->before=null; $this->rollbacks++; return true; }
        public function write(string $sql, array $params): void { $this->writes++; $this->writeLog[] = ['sql'=>$sql, 'params'=>$params]; }
        public function exportState(): array { return array_intersect_key(get_object_vars($this), array_fill_keys(['users','companies','characters','skills','specialisations','personnel','personnelSkills','personnelSpecs','idempotency','events','snapshots','payouts','nextCompanyId','nextPersonnelId','nextPersonnelSkillId','nextSpecId','nextSnapshotId'], true)); }
    }
    function getPDO(): PDO { global $pdo; return $pdo; }
    $scenario = (string) ($argv[1] ?? 'director');
    $stateFile = (string) ($argv[2] ?? '');
    $pdo = new CompanyTestPdo($scenario, json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR));
    $userId = match ($scenario) { 'anonymous'=>999, 'participant'=>10, 'administrator'=>30, default=>20 };
    session_start();
    $_SESSION = ['user'=>['id'=>$userId, 'role'=>'administrator'], 'aetherCsrfToken'=>'company-csrf'];
    if ($scenario !== 'missing_csrf') $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'bad_csrf' ? 'wrong' : 'company-csrf';
    if (isset($argv[3])) $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $argv[3];
    if ($scenario === 'logo_upload' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'uploadCompanyLogo.php') {
        $uploadedLogo = tempnam(sys_get_temp_dir(), 'aether-company-logo-');
        file_put_contents($uploadedLogo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLzWQAAAABJRU5ErkJggg==', true));
        $_POST = ['id' => '7'];
        $_FILES = ['logo' => ['name'=>'company.png', 'tmp_name'=>$uploadedLogo, 'error'=>UPLOAD_ERR_OK, 'size'=>filesize($uploadedLogo)]];
        $GLOBALS['aetherCompanyLogoUploadVerifier'] = static fn(string $path): bool => is_file($path);
        $GLOBALS['aetherCompanyLogoMoveUploadedFile'] = static fn(string $source, string $target): bool => rename($source, $target);
    }
    register_shutdown_function(static function () use ($pdo, $stateFile): void {
        file_put_contents($stateFile, json_encode($pdo->exportState(), JSON_THROW_ON_ERROR));
        fwrite(STDERR, '__AETHER_COMPANY_TEST__:' . json_encode(['status'=>http_response_code() ?: 200, 'writes'=>$pdo->writes, 'rollbacks'=>$pdo->rollbacks, 'writeLog'=>$pdo->writeLog, 'locks'=>$pdo->locks], JSON_THROW_ON_ERROR) . "\n");
    });
    return;
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-company-' . bin2hex(random_bytes(5));
$failures = [];
$stateFiles = [];
function companyCheck(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }
function companyCopyTree(string $from, string $to): void
{
    if (!is_dir($to)) mkdir($to, 0777, true);
    foreach (new DirectoryIterator($from) as $item) {
        if ($item->isDot()) continue;
        $target = $to . '/' . $item->getFilename();
        $item->isDir() ? companyCopyTree($item->getPathname(), $target) : copy($item->getPathname(), $target);
    }
}
function companyRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($path);
}
function companyState(array $value): string
{
    $path = tempnam(sys_get_temp_dir(), 'aether-company-state-');
    file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR));
    return $path;
}
function companyRoute(string $route, string $scenario, array|string $body, string $state, ?string $key = null): array
{
    $command = [PHP_BINARY, $route, $scenario, $state];
    if ($key !== null) $command[] = $key;
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Companytestproces kon niet starten.');
    fwrite($pipes[0], is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($process);
    if (!preg_match('/__AETHER_COMPANY_TEST__:(\{.*\})/', $stderr, $match)) throw new RuntimeException($stderr);
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR) + ['body'=>$output, 'json'=>json_decode($output, true), 'state'=>json_decode((string) file_get_contents($state), true, 512, JSON_THROW_ON_ERROR)];
}

companyCopyTree($root.'/api', $fixture.'/api');
file_put_contents($fixture.'/sessionUserBootstrap.php', '<?php function aetherHydrateSessionUserFromWordPress(): bool { return false; }');
file_put_contents($fixture.'/db.php', "<?php\ndefine('AETHER_COMPANY_ENDPOINT_BOOTSTRAP', true);\nrequire " . var_export(__FILE__, true) . ";\n");
file_put_contents($fixture.'/api/companies/companyUtils.php', <<<'PHP'
<?php
require_once __DIR__.'/../../db.php';
require_once __DIR__.'/companyAccess.php';
function getDefaultCompanyFoundationDate(): string { return '1926-01-01'; }
function dbOne(PDO $pdo,string $sql,array $params=[]): ?array { $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row===false?null:$row; }
function dbAll(PDO $pdo,string $sql,array $params=[]): array { $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC); }
function getCompanyDetailData(PDO $pdo,int $id): ?array { if (!isset($pdo->companies[$id])) return null; return $pdo->companies[$id]+['availableSharePercentage'=>99,'snapshotEventOptions'=>[],'snapshots'=>getCompanySnapshots($pdo,$id)]; }
function getCompanyPersonnelEntries(PDO $pdo,int $id,bool $strict=false): array { if($strict&&$pdo->scenario==='read_error')throw new PDOException('SQLSTATE[HY000]: secret_read_table');return array_values(array_filter($pdo->personnel,static fn(array $r):bool=>(int)$r['idCompany']===$id)); }
function getCompanySnapshots(PDO $pdo,int $id,bool $strict=false): array { return array_values(array_filter($pdo->snapshots,static fn(array $r):bool=>(int)$r['idCompany']===$id)); }
function refreshCompanySnapshotsForCurrentPersonnel(PDO $pdo,int $id): void {}
function getCompanyTypeByValue(mixed $value): array { return ['key'=>'micro']; }
function getCompanyPersonnelImpactSummary(PDO $pdo,int $id,int $stability): array { return ['totalPercentage'=>0,'lowerBoundPercentage'=>0,'upperBoundPercentage'=>0]; }
function getCompanyPersonnelSalaryIncreaseExpenseAmount(PDO $pdo,int $id): float { return 0; }
function calculateCompanySnapshotFinancials(mixed $value,int $stability,int $profitability,mixed $impact,mixed $salary,mixed $lower,mixed $upper): array { return ['companyValue'=>(string)$value,'personnelImpactPercentage'=>'0.00','stabilityLowerBoundPercentage'=>'0.00','stabilityUpperBoundPercentage'=>'0.00','profitAmount'=>'110.00','baseProfitAmount'=>'110.00','stabilityAdjustmentAmount'=>'0.00']; }
function getCompanyShareholderPayoutEntries(PDO $pdo,int $id): array { return [['idCharacter'=>1,'percentage'=>1,'displayName'=>'Shareholder']]; }
function getCompanyLogoDirectory(): string { return dirname(__DIR__,2).'/img/bedrijfslogo'; }
function aetherCompanyLogoMimeExtensions(): array { return ['image/png'=>'png','image/jpeg'=>'jpg','image/gif'=>'gif','image/webp'=>'webp']; }
function aetherCompanyLogoExtensionIsAllowed(string $extension): bool { return in_array($extension,['png','jpg','gif','webp'],true); }
function getCompanyLogoAbsolutePath(int $id,string $extension='png'): string { return getCompanyLogoDirectory().'/'.$id.'.'.$extension; }
function getCompanyLogoPublicPath(int $id,string $extension='png'): string { return 'img/bedrijfslogo/'.$id.'.'.$extension; }
function aetherCompanyLogoManagedPaths(int $id): array { $paths=[];foreach(['png','jpg','gif','webp']as$extension){$path=getCompanyLogoAbsolutePath($id,$extension);if(is_file($path))$paths[]=$path;}return $paths; }
function getCompanyLogoUrl(int $id): ?string { foreach(aetherCompanyLogoManagedPaths($id)as$path){$modified=@filemtime($path);return 'img/bedrijfslogo/'.basename($path).($modified===false?'':'?v='.$modified);}return null; }
PHP);
file_put_contents($fixture.'/api/characters/companyShareUtils.php', '<?php function remapCompanyShareTraitsForCompany(PDO $pdo,int $id,string $key): int { return 0; }');
file_put_contents($fixture.'/api/characters/characterFinanceService.php', "<?php\nrequire_once __DIR__.'/../shared/decimal.php';\nfunction aetherFinanceDecimal(mixed \$v): string { return aetherNormalizeDecimal(\$v,2); }\nfunction aetherFinanceLockCharacters(PDO \$p,array \$ids): array { \$p->locks[]='characters'; return []; }\n");
$routes = array_map(static fn(string $name): string => $fixture.'/api/companies/'.$name.'.php', ['list'=>'getCompanyList','detail'=>'getCompany','create'=>'newCompany','personnel'=>'saveCompanyPersonnel','update'=>'updateCompany','snapshot'=>'saveCompanySnapshot','deleteLogo'=>'deleteCompanyLogo','uploadLogo'=>'uploadCompanyLogo']);
$initial = [
    'users'=>[10=>['id'=>10,'username'=>'participant','firstName'=>'Part','lastName'=>'One','role'=>'participant'],20=>['id'=>20,'username'=>'director','firstName'=>'Dir','lastName'=>'One','role'=>'director'],30=>['id'=>30,'username'=>'administrator','firstName'=>'Admin','lastName'=>'One','role'=>'administrator']],
    'companies'=>[7=>['id'=>7,'companyName'=>'<img src=x onerror=1>','description'=>'<script>x</script>','companyValue'=>'100.00']],
    'characters'=>[1=>['id'=>1,'idUser'=>10,'state'=>'active','bankaccount'=>'10.00'],2=>['id'=>2,'idUser'=>99,'state'=>'draft','bankaccount'=>'0.00']],
    'skills'=>[4=>['id'=>4]], 'specialisations'=>[8=>['id'=>8,'idSkill'=>4,'name'=>'Metallurgy','kind'=>'specialisation'],9=>['id'=>9,'idSkill'=>99,'name'=>'Wrong','kind'=>'specialisation']],
    'personnel'=>[], 'personnelSkills'=>[], 'personnelSpecs'=>[], 'idempotency'=>[],
    'events'=>[18=>['id'=>18,'title'=>'Testevent','dateStart'=>'1926-01-01']], 'snapshots'=>[], 'payouts'=>[],
    'nextCompanyId'=>8, 'nextPersonnelId'=>1, 'nextPersonnelSkillId'=>1, 'nextSpecId'=>10, 'nextSnapshotId'=>1,
];
try {
    $state = $stateFiles[] = companyState($initial);
    $list = companyRoute($routes['list'], 'director', [], $state);
    companyCheck($list['status']===200 && $list['json'] === [['id'=>7,'companyName'=>'<img src=x onerror=1>']], 'Lijstresponse wijkt af');
    foreach (['participant'=>403,'anonymous'=>401] as $scenario=>$status) {
        $r=companyRoute($routes['list'],$scenario,[],$stateFiles[]=companyState($initial));
        companyCheck($r['status']===$status && $r['writes']===0, "Lijsttoegang {$scenario}");
    }
    $linkedState=$initial;
    $linkedState['personnel'][5]=['id'=>5,'idCompany'=>7,'idCharacter'=>1,'importance'=>'High','salaryIncreasePercentage'=>'0.00'];
    $linked=companyRoute($routes['detail'],'participant',['id'=>7],$stateFiles[]=companyState($linkedState));
    companyCheck($linked['status']===403 && $linked['writes']===0,'Personeelslidmaatschap verleent geen companybeheer');
    $detail=companyRoute($routes['detail'],'administrator',['id'=>7],$stateFiles[]=companyState($initial));
    companyCheck($detail['status']===200 && $detail['json']['description']==='<script>x</script>', 'Companydetail wijzigde gewone tekst');
    foreach ([[['id'=>999],404],[['id'=>7,'role'=>'director'],422],['{bad',400]] as [$request,$status]) {
        $r=companyRoute($routes['detail'],'director',$request,$stateFiles[]=companyState($initial));
        companyCheck($r['status']===$status && $r['writes']===0,"Detailfout {$status}");
    }
    $created=companyRoute($routes['create'],'director',['companyName'=>'  Nieuw  '],$state,'company-create-test-key-0001');
    companyCheck($created['status']===200 && $created['json']['companyName']==='Nieuw' && $created['json']['id']===8 && $created['json']['companyValue']===0,'Companycreatecontract');
    $replay=companyRoute($routes['create'],'director',['companyName'=>'  Nieuw  '],$state,'company-create-test-key-0001');
    companyCheck($replay['json']===$created['json'] && count($replay['state']['companies'])===2 && $replay['writes']===0,'Create-idempotentie replay');
    $conflict=companyRoute($routes['create'],'director',['companyName'=>'Anders'],$state,'company-create-test-key-0001');
    companyCheck($conflict['status']===409 && $conflict['writes']===0,'Create-keyconflict');
    foreach (['participant'=>403,'bad_csrf'=>403,'missing_csrf'=>403] as $scenario=>$status) {
        $r=companyRoute($routes['create'],$scenario,['companyName'=>'X'],$stateFiles[]=companyState($initial),'company-create-test-key-0002');
        companyCheck($r['status']===$status && $r['writes']===0,"Createweigering {$scenario}");
    }
    $r=companyRoute($routes['create'],'director',['companyName'=>'Nieuw'],$stateFiles[]=companyState($initial));
    companyCheck($r['status']===400 && $r['writes']===0 && str_contains($r['body'],'Vernieuw'),'Oude browsertab wordt vóór companycreate geweigerd');
    $personnel = ['idCompany'=>7,'personnel'=>[['idCharacter'=>1,'importance'=>'High','salaryIncreasePercentage'=>5,'skills'=>[['idSkill'=>4,'level'=>2,'specialisations'=>[['idSkillSpecialisation'=>8,'name'=>'Metallurgy','kind'=>'specialisation']]]]]]];
    $personnelState=$stateFiles[]=companyState($initial);
    $saved=companyRoute($routes['personnel'],'administrator',$personnel,$personnelState,'company-personnel-key-0001');
    companyCheck($saved['status']===200 && array_keys($saved['json'] ?? [])===['success','personnelEntries','snapshots'] && count($saved['state']['personnel'])===1 && count($saved['state']['personnelSkills'])===1 && count($saved['state']['personnelSpecs'])===1,'Personeel opslaan/response');
    $personnelWrites=array_values(array_filter($saved['writeLog'],static fn(array $row):bool=>str_starts_with($row['sql'],'INSERT INTO tblCompanyPersonnel (')));
    companyCheck(($personnelWrites[0]['params']??null)===['idCompany'=>7,'idCharacter'=>1,'importance'=>'High','salaryIncreasePercentage'=>'5.00'],'Personeelinsert gebruikt exacte prepared parameters');
    $again=companyRoute($routes['personnel'],'administrator',$personnel,$personnelState,'company-personnel-key-0001');
    companyCheck($again['json']===$saved['json'] && $again['writes']===0 && count($again['state']['personnel'])===1,'Personeel replay zonder writes');
    $changed=$personnel; $changed['personnel'][0]['salaryIncreasePercentage']=6;
    $r=companyRoute($routes['personnel'],'administrator',$changed,$personnelState,'company-personnel-key-0001');
    companyCheck($r['status']===409 && $r['writes']===0,'Personeel keyconflict');
    $invalids = [
        [$personnel+['role'=>'administrator'],422],
        [array_replace_recursive($personnel,['personnel'=>[['skills'=>null]]]),422],
        [array_replace_recursive($personnel,['personnel'=>[['skills'=>[['sqlColumn'=>'bankaccount']]]]]),422],
        [array_replace_recursive($personnel,['personnel'=>[['idCharacter'=>2]]]),422],
        [array_replace_recursive($personnel,['personnel'=>[['skills'=>[['specialisations'=>[['idSkillSpecialisation'=>9]]]]]]]),422],
        [array_replace_recursive($personnel,['personnel'=>[['salaryIncreasePercentage'=>'1.001']]]),422],
        [['idCompany'=>999,'personnel'=>[]],404],
    ];
    foreach ($invalids as $i=>[$payload,$status]) {
        $r=companyRoute($routes['personnel'],'director',$payload,$stateFiles[]=companyState($initial),'company-personnel-invalid-'.$i);
        companyCheck($r['status']===$status && $r['state']['personnel']===[] && $r['state']['personnelSkills']===[],"Personeelweigering {$i}");
    }
    $forgedPresentation=$personnel;
    $forgedPresentation['personnel'][0]['idCompanyPersonnel']=999;
    $forgedPresentation['personnel'][0]['displayName']='administrator';
    $forged=companyRoute($routes['personnel'],'director',$forgedPresentation,$stateFiles[]=companyState($initial),'company-personnel-forged-display');
    companyCheck($forged['status']===200 && ($forged['state']['personnel'][1]['idCharacter']??null)===1 && !isset($forged['state']['personnel'][1]['displayName']),'Meegestuurde presentatie-ID/rol verleent geen bevoegdheid');
    $r=companyRoute($routes['personnel'],'mid_error',$personnel,$stateFiles[]=companyState($initial),'company-personnel-mid-error');
    companyCheck($r['status']===500 && $r['rollbacks']===1 && $r['state']['personnel']===[] && !str_contains($r['body'],'secret_personnel_table') && !str_contains($r['body'],'SQLSTATE'),'Halverwege fout rolt terug zonder details');
    $r=companyRoute($routes['personnel'],'read_error',$personnel,$stateFiles[]=companyState($initial),'company-personnel-read-error');
    companyCheck($r['status']===500 && $r['rollbacks']===1 && $r['state']['personnel']===[] && !str_contains($r['body'],'secret_read_table'),'Responseleesfout rolt personnel en idempotentie terug');
    $r=companyRoute($routes['personnel'],'participant',$personnel,$stateFiles[]=companyState($initial),'company-personnel-denied');
    companyCheck($r['status']===403 && $r['writes']===0,'Participant met character mag geen companypersoneel beheren');
    $r=companyRoute($routes['personnel'],'bad_csrf',$personnel,$stateFiles[]=companyState($initial),'company-personnel-bad-csrf');
    companyCheck($r['status']===403 && $r['writes']===0,'Ongeldige CSRF mag personeel niet wijzigen');
    $revokedState=$stateFiles[]=companyState($saved['state']);
    $revokedValue=json_decode((string) file_get_contents($revokedState),true,512,JSON_THROW_ON_ERROR);
    $revokedValue['users'][30]['role']='participant';
    file_put_contents($revokedState,json_encode($revokedValue,JSON_THROW_ON_ERROR));
    $r=companyRoute($routes['personnel'],'administrator',$personnel,$revokedState,'company-personnel-key-0001');
    companyCheck($r['status']===403 && $r['writes']===0,'Ingetrokken rechten verhinderen replay');
    $updated=companyRoute($routes['update'],'director',['id'=>7,'companyName'=>'Gewijzigd','companyValue'=>'101.00'],$stateFiles[]=companyState($initial),'company-update-key-0001');
    companyCheck($updated['status']===200 && $updated['json']===['status'=>'ok','updatedShareTraitCount'=>0] && $updated['state']['companies'][7]['companyValue']==='101.00','Companyupdatecontract/bedrag');
    $r=companyRoute($routes['update'],'director',['id'=>7,'companyValue'=>'1.001'],$stateFiles[]=companyState($initial),'company-update-invalid-key');
    companyCheck($r['status']===422 && $r['writes']===0,'Companybedragprecisie');
    $r=companyRoute($routes['update'],'director',['id'=>7,'description'=>str_repeat('é',33000)],$stateFiles[]=companyState($initial),'company-update-text-too-long');
    companyCheck($r['status']===422 && $r['state']['companies'][7]['description']==='<script>x</script>','Te lange UTF-8-bedrijfsbeschrijving wordt geweigerd');
    $r=companyRoute($routes['update'],'bad_csrf',['id'=>7,'companyName'=>'Niet toegestaan'],$stateFiles[]=companyState($initial),'company-update-bad-csrf');
    companyCheck($r['status']===403 && $r['writes']===0,'Companyupdate zonder geldige CSRF');
    $snapshotState=$stateFiles[]=companyState($initial);
    $snapshotInput=['action'=>'create','idCompany'=>7,'idEvent'=>18,'stability'=>0,'profitability'=>0];
    $snapshot=companyRoute($routes['snapshot'],'director',$snapshotInput,$snapshotState,'company-snapshot-create-key');
    companyCheck($snapshot['status']===200 && ($snapshot['json']['success']??false)===true && count($snapshot['state']['snapshots'])===1,'Snapshotcreatie via financeflow');
    $snapshotReplay=companyRoute($routes['snapshot'],'director',$snapshotInput,$snapshotState,'company-snapshot-create-key');
    companyCheck($snapshotReplay['json']===$snapshot['json'] && $snapshotReplay['writes']===0,'Snapshot-idempotentie');
    $apply=['action'=>'apply','idCompany'=>7,'idCompanySnapshot'=>1,'applyAction'=>'dividend'];
    $payout=companyRoute($routes['snapshot'],'administrator',$apply,$snapshotState,'company-snapshot-payout-key');
    companyCheck($payout['status']===200 && $payout['state']['companies'][7]['companyValue']==='209.00'
        && $payout['state']['characters'][1]['bankaccount']==='11.00' && count($payout['state']['payouts'])===1
        && $payout['state']['snapshots'][1]['appliedAction']==='dividend','Dividend, bank en snapshot committen samen');
    companyCheck(($payout['locks']??[])===['company','characters','snapshot','characters'],'Payoutlocks volgen company, character, snapshot');
    $payoutWrites=array_values(array_filter($payout['writeLog'],static fn(array $row):bool=>str_starts_with($row['sql'],'INSERT INTO tblCompanySnapshotPayout')));
    companyCheck(($payoutWrites[0]['params']['idCharacter']??null)===1 && ($payoutWrites[0]['params']['amount']??null)==='1.00','Dividend gebruikt exacte prepared payoutparameters');
    $payoutError=companyRoute($routes['snapshot'],'payout_error',$apply,$stateFiles[]=companyState($snapshot['state']),'company-snapshot-payout-error');
    companyCheck($payoutError['status']===500 && $payoutError['rollbacks']===1
        && $payoutError['state']['companies'][7]['companyValue']==='100.00'
        && $payoutError['state']['characters'][1]['bankaccount']==='10.00'
        && $payoutError['state']['payouts']===[] && !str_contains($payoutError['body'],'secret_payout_table'),'Dividendfout rolt bank en company terug zonder details');
    $snapshotDenied=companyRoute($routes['snapshot'],'participant',$snapshotInput,$stateFiles[]=companyState($initial),'company-snapshot-denied');
    companyCheck($snapshotDenied['status']===403 && $snapshotDenied['writes']===0,'Participant kan snapshot niet maken');
    $snapshotCsrf=companyRoute($routes['snapshot'],'bad_csrf',$snapshotInput,$stateFiles[]=companyState($initial),'company-snapshot-bad-csrf');
    companyCheck($snapshotCsrf['status']===403 && $snapshotCsrf['writes']===0,'Snapshot zonder geldige CSRF');
    $logoDir=$fixture.'/img/bedrijfslogo';
    mkdir($logoDir,0777,true);
    file_put_contents($logoDir.'/7.png','existing-logo');
    file_put_contents($logoDir.'/8.png','foreign-logo');
    $r=companyRoute($routes['deleteLogo'],'participant',['id'=>7],$stateFiles[]=companyState($initial));
    companyCheck($r['status']===403 && is_file($logoDir.'/7.png'),'Participant kan logo niet verwijderen');
    $r=companyRoute($routes['deleteLogo'],'director',['id'=>7,'path'=>'8.png'],$stateFiles[]=companyState($initial));
    companyCheck($r['status']===422 && is_file($logoDir.'/7.png') && is_file($logoDir.'/8.png'),'Browserpad mag logo niet bepalen');
    $r=companyRoute($routes['deleteLogo'],'director',['id'=>7],$stateFiles[]=companyState($initial));
    companyCheck($r['status']===200 && $r['json']===['status'=>'ok'] && !is_file($logoDir.'/7.png') && is_file($logoDir.'/8.png'),'Logo verwijderen raakt alleen geselecteerd bestand');
    $r=companyRoute($routes['uploadLogo'],'director',[],$stateFiles[]=companyState($initial));
    companyCheck($r['status']===422 && is_file($logoDir.'/8.png'),'Ontbrekende upload wijzigt niets');
    $r=companyRoute($routes['uploadLogo'],'logo_upload',[],$stateFiles[]=companyState($initial));
    companyCheck($r['status']===200 && ($r['json']['status']??null)==='ok'
        && preg_match('#^img/bedrijfslogo/7\\.png\\?v=\\d+$#',(string)($r['json']['logoUrl']??''))===1
        && is_file($logoDir.'/7.png') && is_file($logoDir.'/8.png'), 'Logo-upload werkt zonder GD en raakt geen vreemd logo');
    $preflight=(string) file_get_contents($root.'/sql/migrations/inspect_company_integrity_readonly.sql');
    companyCheck(!preg_match('/\b(?:INSERT|UPDATE|DELETE|ALTER|DROP|CREATE|REPLACE|TRUNCATE)\b/i',preg_replace('/--[^\n]*/','',$preflight)),'Company-integriteitspreflight mag niets wijzigen');
    $js=(string) file_get_contents($root.'/js/companyFunctions.js');
    companyCheck(str_contains($js,'apiFetchIdempotentJson("api/companies/newCompany.php') && str_contains($js,'apiFetchIdempotentJson("api/companies/saveCompanyPersonnel.php'),'Frontend mist veilige retry voor companywrites');
    companyCheck(!preg_match('/\b(?:name|title|description)\.innerHTML\s*=/', $js),'Companytekst gaat via innerHTML');
    companyCheck(!str_contains($js,'result.innerHTML ='), 'Companysnapshot toont tekst via innerHTML');
} finally {
    foreach ($stateFiles as $path) if (is_file($path)) unlink($path);
    companyRemoveTree($fixture);
}
if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL); exit(1); }
echo "Company module endpoint tests passed.\n";
