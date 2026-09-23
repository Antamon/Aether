<?php
declare(strict_types=1);

if (defined('AETHER_EVENT_ADMIN_ACTION_TEST_BOOTSTRAP')) {
    if (!function_exists('mb_strtolower')) {
        function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
    }
    final class EventAdminActionStatement extends PDOStatement
    {
        private array $params = [];
        private int $affected = 0;
        public function __construct(private EventAdminActionPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }
        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $this->affected = 0;
            $sql = $this->sql();
            if (str_starts_with($sql, 'INSERT INTO tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                if (!isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key] = ['payloadHash' => $this->params['payloadHash'], 'status' => 'processing', 'responseStatus' => null, 'responseJson' => null];
                    $this->pdo->write('idempotency-claim', $this->params);
                }
            } elseif (str_starts_with($sql, 'UPDATE tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                $this->pdo->idempotency[$key]['status'] = 'completed';
                $this->pdo->idempotency[$key]['responseStatus'] = 200;
                $this->pdo->idempotency[$key]['responseJson'] = $this->params['responseJson'];
                $this->affected = 1;
                $this->pdo->write('idempotency-complete', $this->params);
            } elseif (str_starts_with($sql, 'UPDATE tblCharacterSkillActionUse')) {
                if ($this->pdo->scenario === 'database_error') throw new PDOException('SQLSTATE[HY000]: secret_action_table');
                $id = (int) $this->params['idActionUse'];
                if (isset($this->pdo->actions[$id])) {
                    foreach (['idSkill','actionCode','actionSubtype','rollBase','rollModifier','rollFinal','resultTitle','resultText','createdAt'] as $field) {
                        $this->pdo->actions[$id][$field] = $this->params[$field];
                    }
                    $this->affected = 1;
                    $this->pdo->write('action-update', $this->params);
                }
            } elseif (str_starts_with($sql, 'DELETE FROM tblCharacterSkillActionUse')) {
                if ($this->pdo->scenario === 'database_error') throw new PDOException('SQLSTATE[HY000]: secret_action_table');
                $id = (int) $this->params['idActionUse'];
                if (isset($this->pdo->actions[$id])) {
                    unset($this->pdo->actions[$id]);
                    $this->affected = 1;
                    $this->pdo->write('action-delete', $this->params);
                }
            }
            return true;
        }
        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser') && str_contains($sql, 'username')) return $this->pdo->users[(int) ($this->params['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                return $this->pdo->idempotency[$key] ?? false;
            }
            return false;
        }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            if (!str_contains($this->sql(), 'FROM tblCharacterSkillActionUse AS u')) return [];
            $id = (int) ($this->params['idActionUse'] ?? 0);
            return isset($this->pdo->actions[$id]) ? [$this->pdo->actions[$id]] : [];
        }
        public function rowCount(): int { return $this->affected; }
    }

    final class EventAdminActionPdo extends PDO
    {
        public array $users = [
            10 => ['id' => 10, 'username' => 'part', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
            20 => ['id' => 20, 'username' => 'dir', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
            30 => ['id' => 30, 'username' => 'admin', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
        ];
        public array $actions;
        public array $idempotency = [];
        public int $writes = 0;
        public array $writeLog = [];
        private ?array $snapshot = null;
        private int $staged = 0;
        public function __construct(public string $scenario, array $state) { $this->actions = $state['actions']; $this->idempotency = $state['idempotency']; }
        public function prepare(string $query, array $options = []): PDOStatement|false { return new EventAdminActionStatement($this, $query); }
        public function beginTransaction(): bool { $this->snapshot = [$this->actions, $this->idempotency]; $this->staged = 0; return true; }
        public function commit(): bool { $this->writes += $this->staged; $this->staged = 0; $this->snapshot = null; return true; }
        public function rollBack(): bool { if ($this->snapshot !== null) [$this->actions, $this->idempotency] = $this->snapshot; $this->snapshot = null; $this->staged = 0; return true; }
        public function inTransaction(): bool { return $this->snapshot !== null; }
        public function write(string $kind, array $params): void { $this->writeLog[] = ['kind' => $kind, 'parameters' => $params]; $this->inTransaction() ? $this->staged++ : $this->writes++; }
        public function export(): array { return ['actions' => $this->actions, 'idempotency' => $this->idempotency]; }
    }

    function getPDO(): PDO { global $pdo; return $pdo; }
    function dbOne(PDO $pdo, string $sql, array $params = []): ?array { $s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null; }
    function dbAll(PDO $pdo, string $sql, array $params = []): array { $s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC); }

    $scenario = (string) ($argv[1] ?? 'administrator');
    $stateFile = (string) ($argv[2] ?? '');
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new EventAdminActionPdo($scenario, $state);
    $userId = match ($scenario) { 'participant' => 10, 'director' => 20, 'unauthenticated' => 999, default => 30 };
    session_start();
    $_SESSION = ['user' => ['id' => $userId, 'role' => 'participant'], 'aetherCsrfToken' => 'action-csrf'];
    if ($scenario !== 'missing_csrf') $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf' ? 'wrong' : 'action-csrf';
    if ($scenario !== 'missing_key') $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'event-admin-action-request-0001';
    register_shutdown_function(static function () use ($pdo, $stateFile): void {
        file_put_contents($stateFile, json_encode($pdo->export(), JSON_THROW_ON_ERROR));
        $status = http_response_code();
        fwrite(STDERR, '__EVENT_ADMIN_ACTION_STATE__:' . json_encode(['status' => $status === false ? 200 : $status, 'writes' => $pdo->writes, 'writeLog' => $pdo->writeLog], JSON_THROW_ON_ERROR) . "\n");
    });
    return;
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-event-admin-action-' . bin2hex(random_bytes(5));
$failures = [];
function adminActionAssert(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }
function adminActionCopy(string $source, string $target): void { if(!is_dir($target))mkdir($target,0777,true);foreach(new DirectoryIterator($source)as$item){if($item->isDot())continue;$destination=$target.'/'.$item->getFilename();$item->isDir()?adminActionCopy($item->getPathname(),$destination):copy($item->getPathname(),$destination);} }
function adminActionRemove(string $path): void { if(!is_dir($path))return;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}rmdir($path); }
function adminActionState(array $state): string { $path=tempnam(sys_get_temp_dir(),'event-action-state-');if($path===false)throw new RuntimeException('Geen state.');file_put_contents($path,json_encode($state,JSON_THROW_ON_ERROR));return $path; }
function runAdminAction(string $route,string $scenario,array|string $payload,string $state):array{$p=proc_open([PHP_BINARY,$route,$scenario,$state],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Geen proces.');fwrite($pipes[0],is_string($payload)?$payload:json_encode($payload,JSON_THROW_ON_ERROR));fclose($pipes[0]);$body=(string)stream_get_contents($pipes[1]);fclose($pipes[1]);$stderr=(string)stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($p);if(!preg_match('/__EVENT_ADMIN_ACTION_STATE__:(\{.*\})/',$stderr,$m))throw new RuntimeException($stderr);return json_decode($m[1],true,512,JSON_THROW_ON_ERROR)+['body'=>$body,'stderr'=>$stderr,'decoded'=>json_decode($body,true),'state'=>json_decode((string)file_get_contents($state),true,512,JSON_THROW_ON_ERROR)];}

adminActionCopy($root . '/api', $fixture . '/api');
copy($root . '/sessionUserBootstrap.php', $fixture . '/sessionUserBootstrap.php');
file_put_contents($fixture . '/db.php', "<?php\ndefine('AETHER_EVENT_ADMIN_ACTION_TEST_BOOTSTRAP',true);\nrequire " . var_export(__FILE__, true) . ";\n");
$routes = ['update' => $fixture . '/api/admin/updateActionUse.php', 'delete' => $fixture . '/api/admin/deleteActionUse.php'];
$row = [
    'idActionUse'=>50,'idEvent'=>7,'eventTitle'=>'Event','idCharacter'=>1,'firstName'=>'Ada','lastName'=>'Lovelace','characterType'=>'player',
    'idSkill'=>32,'skillName'=>'Wereldwijs','actionCode'=>'knowledge','actionSubtype'=>'gossip','rollBase'=>1,'rollModifier'=>0,'rollFinal'=>1,
    'resultCode'=>'success','resultTitle'=>'Oud','resultText'=>'Tekst','stateBefore'=>null,'stateAfter'=>null,'metadata'=>null,
    'createdAt'=>'2026-09-20 20:00:00','createdBy'=>30,
];
$initial = ['actions' => [50 => $row], 'idempotency' => []];
$payload = ['idActionUse'=>50,'idSkill'=>32,'createdAt'=>'2026-09-21 21:00:00','actionCode'=>'knowledge','actionSubtype'=>'gossip','rollBase'=>'2','rollModifier'=>1,'rollFinal'=>'3','resultTitle'=>'Nieuw','resultText'=>'Gewone tekst'];

try {
    $state = adminActionState($initial);
    $updated = runAdminAction($routes['update'], 'director', $payload, $state);
    adminActionAssert($updated['status'] === 200 && ($updated['decoded']['ok'] ?? false) === true && ($updated['decoded']['item']['idActionUse'] ?? 0) === 50, 'Admin-actionupdate wijzigde het succescontract: '.json_encode($updated));
    $updateWrites = array_values(array_filter($updated['writeLog'], static fn(array $item): bool => $item['kind'] === 'action-update'));
    adminActionAssert(($updateWrites[0]['parameters']['idActionUse'] ?? 0) === 50 && ($updateWrites[0]['parameters']['rollFinal'] ?? null) === 3, 'Actionupdate gebruikte niet de verwachte prepared parameters: '.json_encode($updated));
    $replay = runAdminAction($routes['update'], 'director', $payload, $state);
    adminActionAssert($replay['decoded'] === $updated['decoded'] && $replay['writes'] === 0, 'Actionupdate-replay schreef opnieuw of wijzigde de response.');

    foreach ([['participant',403],['invalid_csrf',403],['missing_key',400]] as [$scenario,$status]) {
        $deniedState=adminActionState($initial);$result=runAdminAction($routes['update'],$scenario,$payload,$deniedState);
        adminActionAssert($result['status']===$status&&$result['writes']===0&&$result['state']===$initial,"{$scenario} wijzigde een action-use-record: ".json_encode($result));
    }
    $invalidState=adminActionState($initial);$invalid=runAdminAction($routes['update'],'administrator',$payload+['unexpected'=>1],$invalidState);
    adminActionAssert($invalid['status']===422&&$invalid['writes']===0,'Onverwacht actionveld werd niet met 422 geweigerd.');

    $deleteState=adminActionState($initial);$deleted=runAdminAction($routes['delete'],'administrator',['idActionUse'=>50],$deleteState);
    adminActionAssert($deleted['status']===200&&$deleted['decoded']===['ok'=>true,'idActionUse'=>50]&&!isset($deleted['state']['actions'][50]),'Actiondeletecontract of mutatie wijzigde: '.json_encode($deleted));
    $deleteReplay=runAdminAction($routes['delete'],'administrator',['idActionUse'=>50],$deleteState);
    adminActionAssert($deleteReplay['decoded']===$deleted['decoded']&&$deleteReplay['writes']===0,'Actiondelete-replay voerde een tweede delete uit.');

    $errorState=adminActionState($initial);$error=runAdminAction($routes['update'],'database_error',$payload,$errorState);
    adminActionAssert($error['status']===500&&$error['state']===$initial&&!str_contains($error['body'],'SQLSTATE')&&!str_contains($error['body'],'secret_'),'Actiondatabasefout was niet generiek of rolde niet terug.');
    $adminActionsJs=(string)file_get_contents($root.'/js/adminActions.js');
    adminActionAssert(substr_count($adminActionsJs,'apiFetchIdempotentJson(')>=2,'Admin-actionfrontend gebruikt de gedeelde idempotentiehelper niet.');
} finally { adminActionRemove($fixture); }

if($failures!==[]){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "Event admin action endpoint tests passed.\n";
