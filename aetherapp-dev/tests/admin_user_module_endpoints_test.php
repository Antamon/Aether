<?php
declare(strict_types=1);

if (defined('AETHER_ADMIN_USER_TEST_BOOTSTRAP')) {
    final class AdminTestStatement extends PDOStatement
    {
        private array $rows = [];
        private int $affected = 0;
        public function __construct(private AdminTestPdo $pdo, private string $query) {}
        public function execute(?array $params = null): bool
        {
            $p = $params ?? [];
            $q = preg_replace('/\s+/', ' ', trim($this->query)) ?? '';
            $db = $this->pdo;
            $this->rows = [];
            $this->affected = 0;
            if ($db->scenario === 'read_error' && str_contains($q, 'FROM tblSkillType') && str_contains($q, 'ORDER BY')) throw new PDOException('secret_skill_type_table');
            if (str_contains($q, 'FROM tblApiIdempotency')) {
                $key=$p['idUser'].'|'.$p['operation'].'|'.$p['requestKey'];
                if (isset($db->idempotency[$key])) $this->rows=[$db->idempotency[$key]];
            } elseif (str_contains($q, 'FROM information_schema.COLUMNS')) $this->rows = [['COLUMN_TYPE' => "enum('public','secret')"]];
            elseif (str_contains($q, 'FROM tblUser') && str_contains($q, 'WHERE id')) {
                $row = $db->users[(int) ($p['id'] ?? 0)] ?? null;
                if ($row) $this->rows = [$row];
            } elseif (str_contains($q, 'FROM tblUser')) $this->rows = array_values($db->users);
            elseif (str_contains($q, 'FROM tblSkillType') && str_contains($q, 'WHERE LOWER(name)')) {
                foreach ($db->types as $row) if (strcasecmp($row['name'], $p['name']) === 0 && $row['id'] !== ($p['idSkillType'] ?? 0)) $this->rows = [$row];
            } elseif (str_contains($q, 'FROM tblSkillType') && str_contains($q, 'WHERE code')) {
                foreach ($db->types as $row) if ($row['code'] === $p['code']) $this->rows = [$row];
            } elseif (str_contains($q, 'FROM tblSkillType') && str_contains($q, 'WHERE id')) {
                $row = $db->types[(int) ($p['idSkillType'] ?? 0)] ?? null;
                if ($row) $this->rows = [$row];
            } elseif (str_contains($q, 'FROM tblSkillType')) $this->rows = array_values($db->types);
            elseif (preg_match('/FROM tblSkill\\b/', $q) && str_contains($q, 'WHERE LOWER(name)')) {
                foreach ($db->skills as $row) if (strcasecmp($row['name'], $p['name']) === 0 && $row['id'] !== ($p['idSkill'] ?? 0)) $this->rows = [$row];
            } elseif (preg_match('/FROM tblSkill\\b/', $q) && str_contains($q, 'WHERE id')) {
                $row = $db->skills[(int) ($p['idSkill'] ?? 0)] ?? null;
                if ($row) $this->rows = [$row];
            } elseif (str_contains($q, 'SELECT DISTINCT visibility FROM tblSkill')) {
                $this->rows = [['visibility' => 'public'], ['visibility' => 'secret']];
            } elseif (preg_match('/FROM tblSkill\\b/', $q)) $this->rows = array_values($db->skills);
            elseif (str_contains($q, 'FROM tblLinkSkillType AS lst')) {
                foreach ($db->links as $link) if ($link['idSkill'] === $p['idSkill'] && isset($db->types[$link['idSkillType']])) $this->rows[] = $db->types[$link['idSkillType']];
            } elseif (str_contains($q, 'FROM tblSkillSpecialisation')) {
                foreach ($db->specs as $row) if ($row['idSkill'] === $p['idSkill']) $this->rows[] = $row;
            } elseif (str_contains($q, 'FROM tblCharacterSpecialisation') || str_contains($q, 'FROM tblCompanyPersonnelSkillSpecialisation')) {
                $kind = str_contains($q, 'CompanyPersonnel') ? 'companyLinks' : 'characterLinks';
                if (in_array((int) $p['id'], $db->{$kind}, true)) $this->rows = [['id' => 1]];
            }
            if (str_starts_with($q, 'INSERT INTO tblApiIdempotency')) {
                $key=$p['idUser'].'|'.$p['operation'].'|'.$p['requestKey'];
                if (!isset($db->idempotency[$key])) {
                    $db->idempotency[$key]=['payloadHash'=>$p['payloadHash'],'status'=>'processing','responseStatus'=>null,'responseJson'=>null];
                    $db->record($q,$p);
                }
            } elseif (str_starts_with($q, 'UPDATE tblApiIdempotency')) {
                $key=$p['idUser'].'|'.$p['operation'].'|'.$p['requestKey'];
                $db->idempotency[$key]['status']='completed';
                $db->idempotency[$key]['responseStatus']=200;
                $db->idempotency[$key]['responseJson']=$p['responseJson'];
                $this->affected=1;
                $db->record($q,$p);
            } elseif (str_starts_with($q, 'INSERT INTO tblSkill (')) {
                $id = ++$db->nextSkillId; $db->lastId = $id;
                $db->skills[$id] = ['id' => $id] + $p; $db->record($q, $p);
            } elseif (str_starts_with($q, 'UPDATE tblSkill SET')) {
                $id = $p['idSkill']; foreach ($p as $key => $value) if ($key !== 'idSkill') $db->skills[$id][$key] = $value;
                $db->record($q, $p);
            } elseif (str_starts_with($q, 'DELETE FROM tblLinkSkillType')) {
                $db->links = array_values(array_filter($db->links, static fn(array $row): bool => $row[str_contains($q, 'idSkillType') ? 'idSkillType' : 'idSkill'] !== ($p[str_contains($q, 'idSkillType') ? 'idSkillType' : 'idSkill'])));
                $db->record($q, $p);
            } elseif (str_starts_with($q, 'INSERT INTO tblLinkSkillType')) {
                $db->links[] = $p; $db->record($q, $p);
            } elseif (str_starts_with($q, 'DELETE FROM tblSkillSpecialisation')) {
                unset($db->specs[$p['idSkillSpecialisation']]); $db->record($q, $p);
            } elseif (str_starts_with($q, 'UPDATE tblSkillSpecialisation')) {
                $db->specs[$p['idSkillSpecialisation']]['name'] = $p['name']; $db->record($q, $p);
            } elseif (str_starts_with($q, 'INSERT INTO tblSkillSpecialisation')) {
                $id = ++$db->nextSpecId; $db->specs[$id] = ['id' => $id] + $p; $db->record($q, $p);
            } elseif (str_starts_with($q, 'INSERT INTO tblSkillType')) {
                $id = ++$db->nextTypeId; $db->types[$id] = ['id' => $id] + $p; $db->record($q, $p);
            } elseif (str_starts_with($q, 'UPDATE tblSkillType')) {
                $db->types[$p['idSkillType']]['name'] = $p['name']; $db->record($q, $p);
            } elseif (str_starts_with($q, 'DELETE FROM tblSkillType')) {
                unset($db->types[$p['idSkillType']]); $db->record($q, $p);
            }
            return true;
        }
        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed { return array_shift($this->rows) ?? false; }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
        public function fetchColumn(int $column = 0): mixed { $row = $this->fetch(); return $row === false ? false : (array_values($row)[$column] ?? false); }
        public function rowCount(): int { return $this->affected; }
    }
    final class AdminTestPdo extends PDO
    {
        public array $users = [10 => ['id'=>10,'username'=>'part','firstName'=>'P','lastName'=>'A','role'=>'participant'],20 => ['id'=>20,'username'=>'director','firstName'=>'D','lastName'=>'I','role'=>'director'],30 => ['id'=>30,'username'=>'admin','firstName'=>'A','lastName'=>'D','role'=>'administrator']];
        public array $skills = [1 => ['id'=>1,'name'=>'Lore','description'=>'old','beginner'=>'','professional'=>'','master'=>'','visibility'=>'public']];
        public array $types = [2 => ['id'=>2,'code'=>'discipline','name'=>'Discipline','description'=>'']];
        public array $specs = [3 => ['id'=>3,'idSkill'=>1,'name'=>'Old','kind'=>'discipline']];
        public array $links = [['idSkill'=>1,'idSkillType'=>2]];
        public array $idempotency = [];
        public array $characterLinks = [3]; public array $companyLinks = [];
        public int $nextSkillId = 1; public int $nextTypeId = 2; public int $nextSpecId = 3;
        public int $lastId = 0; public int $writes = 0; public array $writeLog = [];
        private ?array $snapshot = null;
        public function __construct(public string $scenario) {}
        public function prepare(string $query, array $options = []): PDOStatement|false { return new AdminTestStatement($this, $query); }
        public function beginTransaction(): bool { $this->snapshot = $this->export(); return true; }
        public function inTransaction(): bool { return $this->snapshot !== null; }
        public function commit(): bool { $this->snapshot = null; return true; }
        public function rollBack(): bool { foreach ($this->snapshot ?? [] as $key => $value) $this->{$key} = $value; $this->snapshot = null; return true; }
        public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
        public function record(string $sql, array $params): void { ++$this->writes; $this->writeLog[] = ['sql'=>$sql,'params'=>$params]; if ($this->scenario === 'write_error' && $this->writes === 2) throw new PDOException('secret_sql_table'); }
        public function export(): array { return ['users'=>$this->users,'skills'=>$this->skills,'types'=>$this->types,'specs'=>$this->specs,'links'=>$this->links,'idempotency'=>$this->idempotency,'characterLinks'=>$this->characterLinks,'companyLinks'=>$this->companyLinks,'nextSkillId'=>$this->nextSkillId,'nextTypeId'=>$this->nextTypeId,'nextSpecId'=>$this->nextSpecId,'lastId'=>$this->lastId]; }
    }
    function getPDO(): PDO { global $pdo; return $pdo; }
    function dbOne(PDO $pdo, string $sql, array $params = []): ?array { $s=$pdo->prepare($sql); $s->execute($params); $row=$s->fetch(PDO::FETCH_ASSOC); return $row ?: null; }
    function dbAll(PDO $pdo, string $sql, array $params = []): array { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }
    $scenario = (string) ($argv[1] ?? 'administrator');
    $stateFile = (string) ($argv[2] ?? '');
    $pdo = new AdminTestPdo($scenario);
    if ($stateFile && is_file($stateFile)) foreach (json_decode((string) file_get_contents($stateFile), true) as $key => $value) if (property_exists($pdo, $key)) $pdo->{$key} = $value;
    $id = match ($scenario) { 'participant'=>10,'director'=>20,'unauthenticated'=>999,default=>30 };
    session_start(); $_SESSION = ['user'=>['id'=>$id,'role'=>'administrator'],'aetherCsrfToken'=>'csrf-test'];
    if ($scenario !== 'missing_csrf') $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'bad_csrf' ? 'bad' : 'csrf-test';
    if ($scenario !== 'missing_key') $_SERVER['HTTP_IDEMPOTENCY_KEY'] = (string)($argv[3] ?? 'admin-skill-request-0001');
    register_shutdown_function(static function () use ($pdo,$stateFile): void {
        if ($stateFile) file_put_contents($stateFile, json_encode($pdo->export(), JSON_THROW_ON_ERROR));
        fwrite(STDERR, '__ADMIN_STATE__:' . json_encode(['status'=>http_response_code() ?: 200,'writes'=>$pdo->writes,'log'=>$pdo->writeLog], JSON_THROW_ON_ERROR) . "\n");
    });
    return;
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-admin-user-' . bin2hex(random_bytes(5));
$failures = [];
function adminCheck(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }
function adminCopy(string $source, string $target): void { if (!is_dir($target)) mkdir($target,0777,true); foreach (new DirectoryIterator($source) as $item) { if ($item->isDot()) continue; $dest=$target.'/'.$item->getFilename(); $item->isDir() ? adminCopy($item->getPathname(),$dest) : copy($item->getPathname(),$dest); } }
function adminRemove(string $path): void { foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($path); }
function adminState(?array $value = null): string { $path=tempnam(sys_get_temp_dir(),'admin-state-'); if ($value) file_put_contents($path,json_encode($value)); return $path; }
function adminRun(string $route,string $scenario,array|string $input,string $state,string $key='admin-skill-request-0001'): array {
    $proc=proc_open([PHP_BINARY,$route,$scenario,$state,$key],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],is_string($input) ? $input : json_encode($input,JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $body=(string)stream_get_contents($pipes[1]); fclose($pipes[1]); $stderr=(string)stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($proc);
    if (!preg_match('/__ADMIN_STATE__:(\{.*\})/',$stderr,$m)) throw new RuntimeException($stderr);
    return json_decode($m[1],true,512,JSON_THROW_ON_ERROR)+['body'=>$body,'json'=>json_decode($body,true),'state'=>json_decode((string)file_get_contents($state),true,512,JSON_THROW_ON_ERROR)];
}
adminCopy($root.'/api',$fixture.'/api'); copy($root.'/sessionUserBootstrap.php',$fixture.'/sessionUserBootstrap.php');
file_put_contents($fixture.'/db.php',"<?php\ndefine('AETHER_ADMIN_USER_TEST_BOOTSTRAP',true);\nrequire ".var_export(__FILE__,true).";\n");
$routes=[]; foreach (['getSkillList','getSkill','newSkill','saveSkill','saveSkillType'] as $route) $routes[$route]=$fixture.'/api/admin/'.$route.'.php';
$routes['getUserList']=$fixture.'/api/users/getUserList.php';
$stateFiles=[];
try {
    $initial=[
        'users'=>[10=>['id'=>10,'username'=>'part','firstName'=>'P','lastName'=>'A','role'=>'participant'],20=>['id'=>20,'username'=>'director','firstName'=>'D','lastName'=>'I','role'=>'director'],30=>['id'=>30,'username'=>'admin','firstName'=>'A','lastName'=>'D','role'=>'administrator']],
        'skills'=>[1=>['id'=>1,'name'=>'Lore','description'=>'old','beginner'=>'','professional'=>'','master'=>'','visibility'=>'public']],
        'types'=>[2=>['id'=>2,'code'=>'discipline','name'=>'Discipline','description'=>'']],
        'specs'=>[3=>['id'=>3,'idSkill'=>1,'name'=>'Old','kind'=>'discipline']],
        'links'=>[['idSkill'=>1,'idSkillType'=>2]], 'idempotency'=>[], 'characterLinks'=>[3], 'companyLinks'=>[],
        'nextSkillId'=>1,'nextTypeId'=>2,'nextSpecId'=>3,'lastId'=>0,
    ];
    $stateFiles[]=$state=adminState($initial);
    $r=adminRun($routes['getSkillList'],'director',[],$state);
    adminCheck($r['status']===200 && isset($r['json']['skills'],$r['json']['skillTypes']),'Director catalog');
    $r=adminRun($routes['getSkill'],'administrator',['idSkill'=>1],$state);
    adminCheck($r['status']===200 && ($r['json']['idSkill']??null)===1 && isset($r['json']['holders'],$r['json']['specialisations']),'Skilldetail response');
    $r=adminRun($routes['getUserList'],'director',[],$state);
    adminCheck($r['status']===200 && ($r['json'][0]['displayName']??null)==='P A','Gebruikerslijst response');
    $r=adminRun($routes['newSkill'],'director',['name'=>'New'],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===200 && ($r['json']['skill']['name']??null)==='New' && count($r['state']['skills'])===2,'Director mag skill aanmaken');
    $r=adminRun($routes['newSkill'],'administrator',['name'=>'New'], $stateFiles[]=adminState($initial));
    adminCheck($r['status']===200 && ($r['log'][0]['params']['name']??null)==='New','Nieuwe skill gebruikt prepared naamparameter');
    $payload=['idSkill'=>1,'name'=>'Lore','description'=>'<script>x</script>','beginner'=>'','professional'=>'','master'=>'','isSecret'=>true,'categoryIds'=>[2],'specialisations'=>[['idSkillSpecialisation'=>3,'name'=>'Known']]];
    $r=adminRun($routes['saveSkill'],'director',$payload,$stateFiles[]=adminState($initial));
    adminCheck($r['status']===200 && ($r['json']['skill']['isSecret']??null)===true && ($r['state']['skills'][1]['description']??null)==='<script>x</script>' && ($r['state']['specs'][3]['name']??null)==='Known','Skillupdate en plain-text opslag: '.json_encode($r));
    $withNew=$payload; $withNew['specialisations'][]=['idSkillSpecialisation'=>0,'name'=>'New specialisation'];
    $retryState=$stateFiles[]=adminState($initial);
    $first=adminRun($routes['saveSkill'],'administrator',$withNew,$retryState,'admin-skill-new-spec-0001');
    $second=adminRun($routes['saveSkill'],'administrator',$withNew,$retryState,'admin-skill-new-spec-0001');
    adminCheck($first['status']===200 && $second['status']===200 && $second['writes']===0 && $second['json']===$first['json'] && count($second['state']['specs'])===2,'Verloren response/retry maakt specialisatie eenmaal');
    $conflict=$withNew; $conflict['description']='changed';
    $r=adminRun($routes['saveSkill'],'administrator',$conflict,$retryState,'admin-skill-new-spec-0001');
    adminCheck($r['status']===409 && $r['writes']===0,'Zelfde request-ID met andere payload geeft 409');
    $revokedReplay=$second['state']; $revokedReplay['users'][30]['role']='participant';
    $r=adminRun($routes['saveSkill'],'administrator',$withNew,$stateFiles[]=adminState($revokedReplay),'admin-skill-new-spec-0001');
    adminCheck($r['status']===403 && $r['writes']===0,'Replay vereist actuele rol');
    $r=adminRun($routes['saveSkillType'],'administrator',['action'=>'create','idSkillType'=>0,'name'=>'Craft'],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===200 && count($r['json']['skillTypes']??[])===2,'Administrator maakt categorie');
    $r=adminRun($routes['saveSkillType'],'administrator',['action'=>'update','idSkillType'=>2,'name'=>'Updated'],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===200 && ($r['state']['types'][2]['name']??null)==='Updated','Categorie-update');
    $r=adminRun($routes['saveSkillType'],'administrator',['action'=>'delete','idSkillType'=>2,'name'=>''],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===200 && !isset($r['state']['types'][2]) && $r['state']['links']===[],'Categorie en links verwijderen samen');
    foreach ([['participant',403],['unauthenticated',401],['bad_csrf',403],['missing_csrf',403],['missing_key',400]] as [$scenario,$status]) {
        $r=adminRun($routes['saveSkill'],$scenario,$payload,$stateFiles[]=adminState($initial));
        adminCheck($r['status']===$status && $r['writes']===0 && $r['state']===$initial,"Geweigerde skillupdate {$scenario}");
    }
    $r=adminRun($routes['saveSkillType'],'director',['action'=>'delete','idSkillType'=>2,'name'=>''],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===403 && $r['writes']===0,'Director mag categorie niet verwijderen');
    $r=adminRun($routes['getUserList'],'participant',[],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===403 && $r['writes']===0,'Participant mag gebruikerslijst niet zien');
    $r=adminRun($routes['getUserList'],'unauthenticated',[],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===401 && $r['writes']===0 && $r['state']===$initial,'Niet aangemeld maakt geen user aan');
    $r=adminRun($routes['getSkill'],'administrator',['idSkill'=>999],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===404 && $r['writes']===0,'Onbekende skill');
    $r=adminRun($routes['saveSkill'],'administrator',array_replace($payload,['idSkill'=>999]),$stateFiles[]=adminState($initial));
    adminCheck($r['status']===404 && $r['writes']===0,'Onbekende skillupdate');
    $r=adminRun($routes['getSkill'],'administrator','not-json',$stateFiles[]=adminState($initial));
    adminCheck($r['status']===400 && $r['writes']===0,'Ongeldige JSON');
    foreach ([$payload+['role'=>'administrator'],array_replace($payload,['name'=>str_repeat('x',31)]),array_replace($payload,['categoryIds'=>['2']]),array_replace($payload,['specialisations'=>[['idSkillSpecialisation'=>3,'name'=>'Known','idUser'=>30]]])] as $invalid) {
        $r=adminRun($routes['saveSkill'],'administrator',$invalid,$stateFiles[]=adminState($initial));
        adminCheck($r['status']===422 && $r['writes']===0 && isset($r['json']['validationErrors']),'Validatie weigerde foutieve payload');
    }
    $removed=$payload; $removed['specialisations']=[];
    $r=adminRun($routes['saveSkill'],'administrator',$removed,$stateFiles[]=adminState($initial));
    adminCheck($r['status']===409 && $r['writes']===0 && $r['state']===$initial,'Gekoppelde specialisatie mag niet stil verdwijnen: '.json_encode($r));
    $unlinked=$initial; $unlinked['characterLinks']=[];
    $r=adminRun($routes['saveSkill'],'administrator',$removed,$stateFiles[]=adminState($unlinked));
    adminCheck($r['status']===200 && !isset($r['state']['specs'][3]),'Niet gekoppelde specialisatie mag worden verwijderd');
    $r=adminRun($routes['saveSkill'],'write_error',$payload,$stateFiles[]=adminState($initial));
    adminCheck($r['status']===500 && $r['state']===$initial && !str_contains($r['body'],'secret_sql_table'),'Transactionele rollback en generieke 500: '.json_encode($r));
    $r=adminRun($routes['getSkillList'],'read_error',[],$stateFiles[]=adminState($initial));
    adminCheck($r['status']===500 && !str_contains($r['body'],'secret_skill_type_table'),'Catalogfout mag geen lege succeslijst of SQL-detail tonen');
    $revoked=$initial; $revoked['users'][30]['role']='participant';
    $r=adminRun($routes['newSkill'],'administrator',['name'=>'Denied'],$stateFiles[]=adminState($revoked));
    adminCheck($r['status']===403 && $r['writes']===0,'Actuele databaserol wint van vervalste sessierol');
    $sql=(string)file_get_contents($root.'/sql/migrations/inspect_admin_user_integrity_readonly.sql');
    $sql=preg_replace('/--[^\n]*/','',$sql) ?? $sql;
    adminCheck(!preg_match('/\b(?:INSERT|UPDATE|DELETE|ALTER|DROP|CREATE|REPLACE|TRUNCATE)\b/i',$sql),'Integriteitsquery mag niets wijzigen');
    $js=(string)file_get_contents($root.'/js/adminFunctions.js');
    adminCheck(str_contains($js,'apiFetchIdempotentJson("api/admin/saveSkill.php"'),'Skillfrontend moet retries met dezelfde request-ID sturen');
    adminCheck(str_contains($js,'option.textContent = skill.name') && str_contains($js,'text.textContent = category.name'),'Admin gewone tekst moet via textContent lopen');
} finally { foreach ($stateFiles as $path) if (is_file($path)) unlink($path); adminRemove($fixture); }
if ($failures) { fwrite(STDERR,implode("\n",$failures)."\n"); exit(1); }
echo "Admin/user endpoint tests passed.\n";
