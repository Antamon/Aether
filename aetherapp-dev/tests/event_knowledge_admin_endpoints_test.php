<?php
declare(strict_types=1);

if (defined('AETHER_EVENT_KNOWLEDGE_TEST_BOOTSTRAP')) {
    final class EventKnowledgeTestStatement extends PDOStatement
    {
        private array $params = [];
        private int $affected = 0;
        public function __construct(private EventKnowledgeTestPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }

        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $this->affected = 0;
            $sql = $this->sql();
            if (str_starts_with($sql, 'INSERT INTO tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                if (!isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key] = [
                        'payloadHash' => $this->params['payloadHash'], 'status' => 'processing',
                        'responseStatus' => null, 'responseJson' => null,
                    ];
                    $this->pdo->write('idempotency-claim');
                }
            } elseif (str_starts_with($sql, 'UPDATE tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                if (isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key]['status'] = 'completed';
                    $this->pdo->idempotency[$key]['responseStatus'] = 200;
                    $this->pdo->idempotency[$key]['responseJson'] = $this->params['responseJson'];
                    $this->affected = 1;
                    $this->pdo->write('idempotency-complete');
                }
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterDiaryVisibility')) {
                if ($this->pdo->scenario === 'visibility_error') throw new PDOException('SQLSTATE[HY000]: secret_visibility_table');
                $key = $this->params['idEvent'] . ':' . $this->params['idCharacter'];
                $this->pdo->visibility[$key] = [
                    'idEvent' => (int) $this->params['idEvent'],
                    'idCharacter' => (int) $this->params['idCharacter'],
                    'isVisible' => (int) $this->params['isVisible'],
                    'updatedBy' => $this->params['updatedBy'],
                ];
                $this->pdo->write('visibility');
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterEventGossipAttempt')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'];
                if (!isset($this->pdo->attempts[$key])) {
                    $this->pdo->attempts[$key] = 0;
                    $this->pdo->write('attempt-lock-row');
                }
            } elseif (str_starts_with($sql, 'DELETE FROM tblCharacterEventGossipUnlock')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'] . ':' . $this->params['idSourceCharacter'];
                unset($this->pdo->unlocks[$key]);
                $this->pdo->write('unlock-delete');
            } elseif (str_starts_with($sql, 'UPDATE tblCharacterEventGossipAttempt')) {
                if ($this->pdo->scenario === 'attempt_error') throw new PDOException('SQLSTATE[HY000]: secret_attempt_table');
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'];
                $this->pdo->attempts[$key] = (int) $this->params['attemptCount'];
                $this->pdo->write('attempt-update');
            } elseif (str_starts_with($sql, 'DELETE FROM tblCharacterEventGossipAttempt')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'];
                unset($this->pdo->attempts[$key]);
                $this->pdo->write('attempt-delete');
            }
            return true;
        }

        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser') && str_contains($sql, 'username')) {
                return $this->pdo->users[(int) ($this->params['id'] ?? 0)] ?? false;
            }
            if (str_contains($sql, 'FROM tblApiIdempotency')) {
                $key = $this->params['idUser'] . '|' . $this->params['operation'] . '|' . $this->params['requestKey'];
                return $this->pdo->idempotency[$key] ?? false;
            }
            if (str_contains($sql, 'FROM tblEvent') && str_contains($sql, 'WHERE id = :id')) {
                return isset($this->pdo->events[(int) $this->params['id']])
                    ? ['id' => (int) $this->params['id'], 'type' => 'weekend', 'title' => 'Event']
                    : false;
            }
            if (str_contains($sql, 'FROM tblCharacterEventGossipAttempt')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'];
                return isset($this->pdo->attempts[$key]) ? ['attemptCount' => $this->pdo->attempts[$key]] : false;
            }
            if (str_contains($sql, 'FROM tblCharacterEventGossipUnlock')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'] . ':' . $this->params['idSourceCharacter'];
                return $this->pdo->unlocks[$key] ?? false;
            }
            return false;
        }

        public function fetchColumn(int $column = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'SELECT d.id') && str_contains($sql, 'FROM tblCharacterDiary')) {
                $key = $this->params['idEvent'] . ':' . $this->params['idCharacter'];
                return isset($this->pdo->diaries[$key]) ? $this->pdo->diaries[$key] : false;
            }
            $row = $this->fetch();
            return $row === false ? false : (array_values($row)[$column] ?? false);
        }
        public function rowCount(): int { return $this->affected; }
    }

    final class EventKnowledgeTestPdo extends PDO
    {
        public array $users = [
            10 => ['id' => 10, 'username' => 'part', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
            20 => ['id' => 20, 'username' => 'dir', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
            30 => ['id' => 30, 'username' => 'admin', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
        ];
        public array $events = [7 => true];
        public array $diaries = ['7:3' => 70];
        public array $visibility = [];
        public array $attempts = ['1:7' => 2];
        public array $unlocks = ['1:7:3' => ['unlockGossip1' => 1, 'unlockGossip2' => 1, 'unlockGossip3' => 0]];
        public array $idempotency = [];
        public int $writes = 0;
        private ?array $snapshot = null;
        private int $stagedWrites = 0;
        public function __construct(public string $scenario) {}
        public function prepare(string $query, array $options = []): PDOStatement|false { return new EventKnowledgeTestStatement($this, $query); }
        public function beginTransaction(): bool
        {
            $this->snapshot = [$this->visibility, $this->attempts, $this->unlocks, $this->idempotency];
            $this->stagedWrites = 0;
            return true;
        }
        public function commit(): bool { $this->writes += $this->stagedWrites; $this->stagedWrites = 0; $this->snapshot = null; return true; }
        public function rollBack(): bool
        {
            if ($this->snapshot !== null) [$this->visibility, $this->attempts, $this->unlocks, $this->idempotency] = $this->snapshot;
            $this->stagedWrites = 0; $this->snapshot = null; return true;
        }
        public function inTransaction(): bool { return $this->snapshot !== null; }
        public function write(string $kind): void { $this->inTransaction() ? $this->stagedWrites++ : $this->writes++; }
        public function export(): array
        {
            return ['visibility' => $this->visibility, 'attempts' => $this->attempts, 'unlocks' => $this->unlocks, 'idempotency' => $this->idempotency];
        }
    }

    function getPDO(): PDO { global $pdo; return $pdo; }
    function dbOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $statement = $pdo->prepare($sql); $statement->execute($params); $row = $statement->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }
    function dbAll(PDO $pdo, string $sql, array $params = []): array
    {
        $statement = $pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $scenario = (string) ($argv[1] ?? 'administrator');
    $stateFile = (string) ($argv[2] ?? '');
    $pdo = new EventKnowledgeTestPdo($scenario);
    if ($stateFile !== '' && is_file($stateFile)) {
        $saved = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
        foreach ($saved as $key => $value) if (property_exists($pdo, $key)) $pdo->{$key} = $value;
    }
    $userId = match ($scenario) { 'participant' => 10, 'director' => 20, 'unauthenticated' => 999, default => 30 };
    session_start();
    $_SESSION = ['user' => ['id' => $userId, 'role' => 'administrator'], 'aetherCsrfToken' => 'event-csrf'];
    if ($scenario !== 'missing_csrf') $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf' ? 'wrong' : 'event-csrf';
    if ($scenario !== 'missing_key') $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'event-knowledge-request-0001';
    register_shutdown_function(static function () use ($pdo, $stateFile): void {
        if ($stateFile !== '') file_put_contents($stateFile, json_encode($pdo->export(), JSON_THROW_ON_ERROR));
        $status = http_response_code();
        fwrite(STDERR, '__EVENT_KNOWLEDGE_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status, 'writes' => $pdo->writes,
        ], JSON_THROW_ON_ERROR) . "\n");
    });
    return;
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-event-knowledge-' . bin2hex(random_bytes(5));
$failures = [];
function knowledgeAssert(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }
function knowledgeCopy(string $source, string $target): void
{
    if (!is_dir($target)) mkdir($target, 0777, true);
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) continue;
        $destination = $target . '/' . $item->getFilename();
        $item->isDir() ? knowledgeCopy($item->getPathname(), $destination) : copy($item->getPathname(), $destination);
    }
}
function knowledgeRemove(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}
function knowledgeState(?array $state = null): string
{
    $path = tempnam(sys_get_temp_dir(), 'event-knowledge-state-');
    if ($path === false) throw new RuntimeException('Geen teststate beschikbaar.');
    if ($state !== null) file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
    return $path;
}
function knowledgeRun(string $route, string $scenario, array|string $payload, string $state): array
{
    $process = proc_open([PHP_BINARY, $route, $scenario, $state], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Kon eventkennistest niet starten.');
    fwrite($pipes[0], is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $body = (string) stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($process);
    if (!preg_match('/__EVENT_KNOWLEDGE_STATE__:(\{.*\})/', $stderr, $match)) throw new RuntimeException("Geen state: {$stderr}");
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR) + [
        'body' => $body, 'decoded' => json_decode($body, true),
        'state' => json_decode((string) file_get_contents($state), true, 512, JSON_THROW_ON_ERROR),
    ];
}

knowledgeCopy($root . '/api', $fixture . '/api');
copy($root . '/sessionUserBootstrap.php', $fixture . '/sessionUserBootstrap.php');
file_put_contents($fixture . '/db.php', "<?php\ndefine('AETHER_EVENT_KNOWLEDGE_TEST_BOOTSTRAP', true);\nrequire " . var_export(__FILE__, true) . ";\n");
$saveRoute = $fixture . '/api/admin/saveKnowledgeVisibility.php';
$deleteRoute = $fixture . '/api/admin/deleteKnowledgeUnlock.php';
$initial = [
    'visibility' => [], 'attempts' => ['1:7' => 2],
    'unlocks' => ['1:7:3' => ['unlockGossip1' => 1, 'unlockGossip2' => 1, 'unlockGossip3' => 0]],
    'idempotency' => [],
];

try {
    $visibilityState = knowledgeState($initial);
    $saved = knowledgeRun($saveRoute, 'director', ['idEvent' => 7, 'idCharacter' => 3, 'isVisible' => false], $visibilityState);
    knowledgeAssert($saved['status'] === 200 && $saved['decoded'] === ['ok' => true, 'idEvent' => 7, 'idCharacter' => 3, 'isVisible' => false], 'Visibilityresponse wijzigde: ' . json_encode($saved));
    knowledgeAssert(($saved['state']['visibility']['7:3']['isVisible'] ?? null) === 0, 'Diaryvisibility werd niet opgeslagen.');
    $replayed = knowledgeRun($saveRoute, 'director', ['idEvent' => 7, 'idCharacter' => 3, 'isVisible' => false], $visibilityState);
    knowledgeAssert($replayed['decoded'] === $saved['decoded'] && $replayed['writes'] === 0, 'Visibilityreplay voerde opnieuw een mutatie uit.');

    foreach ([['participant', 403], ['missing_csrf', 403], ['missing_key', 400]] as [$scenario, $status]) {
        $state = knowledgeState($initial);
        $result = knowledgeRun($saveRoute, $scenario, ['idEvent' => 7, 'idCharacter' => 3, 'isVisible' => true], $state);
        knowledgeAssert($result['status'] === $status && $result['writes'] === 0 && $result['state'] === $initial, "{$scenario} wijzigde diaryvisibility: " . json_encode($result));
    }
    $invalidState = knowledgeState($initial);
    $invalid = knowledgeRun($saveRoute, 'administrator', ['idEvent' => 7, 'idCharacter' => 3, 'isVisible' => true, 'unexpected' => 1], $invalidState);
    knowledgeAssert($invalid['status'] === 422 && $invalid['writes'] === 0, 'Onverwacht visibilityveld werd niet met 422 geweigerd: ' . json_encode($invalid));

    $deleteState = knowledgeState($initial);
    $deleted = knowledgeRun($deleteRoute, 'administrator', ['idEvent' => 7, 'idSourceCharacter' => 3, 'idViewerCharacter' => 1], $deleteState);
    knowledgeAssert($deleted['status'] === 200 && $deleted['decoded'] === [
        'ok' => true, 'idEvent' => 7, 'idSourceCharacter' => 3, 'idViewerCharacter' => 1, 'attemptCount' => 1,
    ], 'Unlock-deleteresponse wijzigde: ' . json_encode($deleted));
    knowledgeAssert(!isset($deleted['state']['unlocks']['1:7:3']) && ($deleted['state']['attempts']['1:7'] ?? null) === 1, 'Unlock en attemptcounter committen niet samen.');
    $deleteReplay = knowledgeRun($deleteRoute, 'administrator', ['idEvent' => 7, 'idSourceCharacter' => 3, 'idViewerCharacter' => 1], $deleteState);
    knowledgeAssert($deleteReplay['decoded'] === $deleted['decoded'] && ($deleteReplay['state']['attempts']['1:7'] ?? null) === 1 && $deleteReplay['writes'] === 0, 'Unlockreplay verminderde de counter opnieuw.');

    $rollbackState = knowledgeState($initial);
    $rollback = knowledgeRun($deleteRoute, 'attempt_error', ['idEvent' => 7, 'idSourceCharacter' => 3, 'idViewerCharacter' => 1], $rollbackState);
    knowledgeAssert($rollback['status'] === 500 && $rollback['state'] === $initial, 'Fout na unlockdelete rolde unlock en counter niet volledig terug: ' . json_encode($rollback));
    knowledgeAssert(!str_contains($rollback['body'], 'SQLSTATE') && !str_contains($rollback['body'], 'secret_'), 'Eventkennis-500 lekte technische details.');

    $adminJs = (string) file_get_contents($root . '/js/adminFunctions.js');
    knowledgeAssert(substr_count($adminJs, 'apiFetchIdempotentJson(') >= 2, 'Admin-knowledgefrontend gebruikt de gedeelde idempotentiehelper niet.');
} finally {
    knowledgeRemove($fixture);
}

if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Event knowledge admin endpoint tests passed.\n";
