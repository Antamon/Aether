<?php
declare(strict_types=1);

if (defined('AETHER_EVENT_ENDPOINT_TEST_BOOTSTRAP')) {
    final class EventEndpointTestStatement extends PDOStatement
    {
        private array $params = [];
        private int $affected = 0;

        public function __construct(private EventEndpointTestPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }

        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $this->affected = 0;
            $sql = $this->sql();
            if ($this->pdo->scenario === 'server_error' && str_contains($sql, 'FROM tblEvent AS e')) {
                throw new PDOException('SQLSTATE[42S02]: secret_event_table');
            }
            if (str_starts_with($sql, 'INSERT INTO tblEvent ')) {
                $this->pdo->write($sql, $this->params);
                $id = $this->pdo->nextEventId++;
                $this->pdo->lastInsertIdValue = $id;
                $this->pdo->events[$id] = ['id' => $id] + $this->params;
                $this->affected = 1;
            } elseif (str_starts_with($sql, 'UPDATE tblEvent SET')) {
                $this->pdo->write($sql, $this->params);
                $id = (int) $this->params['id'];
                if (isset($this->pdo->events[$id])) {
                    foreach ($this->params as $key => $value) if ($key !== 'id') $this->pdo->events[$id][$key] = $value;
                    $this->affected = 1;
                }
            } elseif (str_starts_with($sql, 'INSERT INTO tblLinkEventUser')) {
                $this->pdo->write($sql, $this->params);
                $key = $this->params['idEvent'] . ':' . $this->params['idUser'];
                if (isset($this->pdo->participations[$key])) {
                    $this->pdo->lastInsertIdValue = (int) $this->pdo->participations[$key]['id'];
                } else {
                    $id = $this->pdo->nextParticipationId++;
                    $this->pdo->lastInsertIdValue = $id;
                    $this->pdo->participations[$key] = [
                        'id' => $id, 'idEvent' => (int) $this->params['idEvent'], 'idUser' => (int) $this->params['idUser'],
                    ];
                    $this->affected = 1;
                }
            } elseif (str_starts_with($sql, 'DELETE FROM tblLinkEventUser')) {
                $this->pdo->write($sql, $this->params);
                $key = $this->params['idEvent'] . ':' . $this->params['idUser'];
                if (isset($this->pdo->participations[$key])) {
                    unset($this->pdo->participations[$key]);
                    $this->affected = 1;
                }
            }
            return true;
        }

        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser') && str_contains($sql, 'username')) {
                return $this->pdo->users[(int) ($this->params['id'] ?? 0)] ?? false;
            }
            if (str_contains($sql, 'FROM tblEvent') && str_contains($sql, 'WHERE id = :id')) {
                return $this->pdo->events[(int) ($this->params['id'] ?? 0)] ?? false;
            }
            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            $sql = $this->sql();
            if (!str_contains($sql, 'FROM tblEvent AS e')) return [];
            $userId = (int) ($this->params['idUser'] ?? 0);
            $events = array_values($this->pdo->events);
            usort($events, static fn(array $a, array $b): int => strcmp((string) $a['dateStart'], (string) $b['dateStart']));
            return array_map(function (array $event) use ($userId): array {
                $event['participation'] = isset($this->pdo->participations[$event['id'] . ':' . $userId]) ? 1 : 0;
                return $event;
            }, $events);
        }

        public function fetchColumn(int $column = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser')) {
                $id = (int) ($this->params['id'] ?? 0);
                return isset($this->pdo->users[$id]) ? $id : false;
            }
            if (str_contains($sql, 'FROM tblLinkEventUser')) {
                $key = $this->params['idEvent'] . ':' . $this->params['idUser'];
                return $this->pdo->participations[$key]['id'] ?? false;
            }
            return false;
        }

        public function rowCount(): int { return $this->affected; }
    }

    final class EventEndpointTestPdo extends PDO
    {
        public array $users;
        public array $events;
        public array $participations;
        public int $nextEventId;
        public int $nextParticipationId;
        public int $lastInsertIdValue = 0;
        public int $writes = 0;
        public array $writeLog = [];

        public function __construct(public string $scenario, array $state)
        {
            foreach ($state as $key => $value) if (property_exists($this, $key)) $this->{$key} = $value;
        }
        public function prepare(string $query, array $options = []): PDOStatement|false { return new EventEndpointTestStatement($this, $query); }
        public function lastInsertId(?string $name = null): string|false { return (string) $this->lastInsertIdValue; }
        public function write(string $sql, array $params): void
        {
            $this->writes++;
            $this->writeLog[] = ['sql' => $sql, 'parameters' => $params];
        }
        public function exportState(): array
        {
            return [
                'users' => $this->users, 'events' => $this->events, 'participations' => $this->participations,
                'nextEventId' => $this->nextEventId, 'nextParticipationId' => $this->nextParticipationId,
            ];
        }
    }

    function getPDO(): PDO { global $pdo; return $pdo; }

    $scenario = (string) ($argv[1] ?? 'participant');
    $stateFile = (string) ($argv[2] ?? '');
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new EventEndpointTestPdo($scenario, $state);
    $userId = match ($scenario) {
        'unauthenticated' => 999, 'director' => 20, 'administrator', 'server_error' => 30, default => 10,
    };
    session_start();
    $_SESSION = ['user' => ['id' => $userId, 'role' => 'administrator'], 'aetherCsrfToken' => 'event-csrf'];
    if ($scenario !== 'missing_csrf') {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf' ? 'wrong-csrf' : 'event-csrf';
    }
    register_shutdown_function(static function () use ($pdo, $stateFile): void {
        file_put_contents($stateFile, json_encode($pdo->exportState(), JSON_THROW_ON_ERROR));
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_EVENT_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'writes' => $pdo->writes,
            'writeLog' => $pdo->writeLog,
        ], JSON_THROW_ON_ERROR) . "\n");
    });
    return;
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-event-module-' . bin2hex(random_bytes(6));
$stateFiles = [];
$failures = [];

function eventAssert(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }
function eventCopyTree(string $source, string $target): void
{
    if (!is_dir($target)) mkdir($target, 0777, true);
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) continue;
        $destination = $target . '/' . $item->getFilename();
        $item->isDir() ? eventCopyTree($item->getPathname(), $destination) : copy($item->getPathname(), $destination);
    }
}
function eventRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($path);
}
function eventState(array $state): string
{
    $path = tempnam(sys_get_temp_dir(), 'aether-event-state-');
    if ($path === false) throw new RuntimeException('Geen eventteststate beschikbaar.');
    file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
    return $path;
}
function runEventRoute(string $path, string $scenario, array|string $request, string $stateFile): array
{
    $process = proc_open([PHP_BINARY, $path, $scenario, $stateFile], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Kon eventroute niet starten.');
    fwrite($pipes[0], is_string($request) ? $request : json_encode($request, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $body = (string) stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]); fclose($pipes[2]);
    proc_close($process);
    if (!preg_match('/__AETHER_EVENT_STATE__:(\{.*\})/', $stderr, $match)) throw new RuntimeException("Geen eventstatus: {$stderr}");
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR) + [
        'body' => $body, 'decoded' => json_decode($body, true),
        'state' => json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR),
    ];
}
function eventError(array $result, int $status, int $writes, string $label): void
{
    eventAssert($result['status'] === $status, "{$label}: HTTP {$result['status']} in plaats van {$status}");
    eventAssert(isset($result['decoded']['error']), "{$label}: foutresponse ontbreekt");
    eventAssert($result['writes'] === $writes, "{$label}: onverwachte writepoging");
}

eventCopyTree($root . '/api', $fixture . '/api');
copy($root . '/sessionUserBootstrap.php', $fixture . '/sessionUserBootstrap.php');
file_put_contents($fixture . '/db.php', "<?php\ndefine('AETHER_EVENT_ENDPOINT_TEST_BOOTSTRAP', true);\nrequire " . var_export(__FILE__, true) . ";\n");
$routes = [
    'list' => $fixture . '/api/events/getEventList.php',
    'create' => $fixture . '/api/events/newEvent.php',
    'update' => $fixture . '/api/events/updateEvent.php',
    'participation' => $fixture . '/api/events/updateParticipation.php',
];
$initial = [
    'users' => [
        10 => ['id' => 10, 'username' => 'part', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'dir', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
        30 => ['id' => 30, 'username' => 'admin', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
    ],
    'events' => [
        7 => ['id' => 7, 'type' => 'weekend', 'title' => '<img src=x onerror=alert(1)>', 'description' => '<script>alert(1)</script>', 'dateStart' => '2026-09-01', 'dateEnd' => '2026-09-02', 'venue' => '<b>Hal</b>', 'ep' => 1],
    ],
    'participations' => ['7:10' => ['id' => 50, 'idEvent' => 7, 'idUser' => 10]],
    'nextEventId' => 8, 'nextParticipationId' => 51,
];

try {
    $state = $stateFiles[] = eventState($initial);
    $ownList = runEventRoute($routes['list'], 'participant', ['idUser' => 0], $state);
    eventAssert($ownList['status'] === 200 && array_keys($ownList['decoded'][0] ?? []) === [
        'id', 'type', 'title', 'description', 'dateStart', 'dateEnd', 'venue', 'ep', 'participation',
    ], 'Eventlijstresponse wijzigde');
    eventAssert(($ownList['decoded'][0]['participation'] ?? false) === true, 'Eigen deelname ontbreekt');
    eventAssert(($ownList['decoded'][0]['title'] ?? '') === '<img src=x onerror=alert(1)>', 'Gewone eventtekst werd server-side als HTML veranderd');

    $otherListState = $stateFiles[] = eventState($initial);
    eventError(runEventRoute($routes['list'], 'participant', ['idUser' => 20], $otherListState), 403, 0, 'participant leest andere deelname');
    $directorList = runEventRoute($routes['list'], 'director', ['idUser' => 10], $stateFiles[] = eventState($initial));
    eventAssert($directorList['status'] === 200 && ($directorList['decoded'][0]['participation'] ?? false) === true, 'Director verloor deelname-inzicht');
    eventError(runEventRoute($routes['list'], 'unauthenticated', ['idUser' => 0], $stateFiles[] = eventState($initial)), 401, 0, 'anonieme eventlijst');

    $createPayload = ['type' => 'mini', 'title' => ' Nieuw ', 'description' => 'Beschrijving', 'dateStart' => '2026-10-01', 'dateEnd' => '2026-10-01', 'venue' => 'Gent', 'ep' => '2'];
    $createState = $stateFiles[] = eventState($initial);
    $created = runEventRoute($routes['create'], 'director', $createPayload, $createState);
    eventAssert($created['decoded'] === 8 && ($created['state']['events'][8]['title'] ?? '') === 'Nieuw', 'Eventcreatecontract of trim wijzigde');
    eventAssert(array_keys($created['writeLog'][0]['parameters'] ?? []) === ['type', 'title', 'description', 'dateStart', 'dateEnd', 'venue', 'ep'], 'Eventinsert gebruikt niet de vaste parameters');
    eventError(runEventRoute($routes['create'], 'participant', $createPayload, $stateFiles[] = eventState($initial)), 403, 0, 'participant maakt event');
    eventError(runEventRoute($routes['create'], 'invalid_csrf', $createPayload, $stateFiles[] = eventState($initial)), 403, 0, 'eventcreate ongeldige CSRF');
    eventError(runEventRoute($routes['create'], 'director', $createPayload + ['column`=1' => 'x'], $stateFiles[] = eventState($initial)), 422, 0, 'eventcreate gemanipuleerd veld');
    eventError(runEventRoute($routes['create'], 'director', array_replace($createPayload, ['dateEnd' => '2026-09-30']), $stateFiles[] = eventState($initial)), 422, 0, 'event datumvolgorde');

    $updateState = $stateFiles[] = eventState($initial);
    $updated = runEventRoute($routes['update'], 'administrator', ['id' => 7, 'title' => 'Veilige titel'], $updateState);
    eventAssert($updated['decoded'] === 1 && ($updated['state']['events'][7]['title'] ?? '') === 'Veilige titel', 'Eventupdatecontract wijzigde');
    eventAssert(str_contains((string) ($updated['writeLog'][0]['sql'] ?? ''), '`title` = :title'), 'Eventupdate gebruikt geen vaste kolommapping');
    eventError(runEventRoute($routes['update'], 'administrator', ['id' => 7, 'title = 1 --' => 'x'], $stateFiles[] = eventState($initial)), 422, 0, 'eventupdate kolommanipulatie');
    eventError(runEventRoute($routes['update'], 'administrator', ['id' => 999, 'title' => 'X'], $stateFiles[] = eventState($initial)), 404, 0, 'onbekend event');

    $participationState = $stateFiles[] = eventState($initial);
    $added = runEventRoute($routes['participation'], 'director', ['idEvent' => 7, 'idUser' => 20, 'participation' => true], $participationState);
    $repeated = runEventRoute($routes['participation'], 'director', ['idEvent' => 7, 'idUser' => 20, 'participation' => true], $participationState);
    eventAssert($added['decoded'] === 51 && $repeated['decoded'] === 51
        && count(array_filter($repeated['state']['participations'], static fn(array $row): bool => $row['idEvent'] === 7 && $row['idUser'] === 20)) === 1,
        'Dubbele deelname werd niet idempotent voorkomen');
    $removed = runEventRoute($routes['participation'], 'administrator', ['idEvent' => 7, 'idUser' => 20, 'participation' => false], $participationState);
    eventAssert($removed['decoded'] === 1 && !isset($removed['state']['participations']['7:20']), 'Deelname verwijderen wijzigde');
    eventError(runEventRoute($routes['participation'], 'participant', ['idEvent' => 7, 'idUser' => 10, 'participation' => false], $stateFiles[] = eventState($initial)), 403, 0, 'participant wijzigt deelname');

    foreach ([
        [['idUser' => 0, 'unexpected' => 1], 'lijst onbekend veld'],
        ['{bad-json', 'lijst ongeldige JSON'],
    ] as [$request, $label]) {
        $result = runEventRoute($routes['list'], 'participant', $request, $stateFiles[] = eventState($initial));
        eventError($result, is_string($request) ? 400 : 422, 0, $label);
    }
    $server = runEventRoute($routes['list'], 'server_error', ['idUser' => 0], $stateFiles[] = eventState($initial));
    eventAssert($server['status'] === 500 && $server['decoded'] === ['error' => 'Kon eventlijst niet laden.']
        && !str_contains($server['body'], 'SQLSTATE') && !str_contains($server['body'], 'secret_event_table'),
        'Eventserverfout lekt details of wijzigde contract');

    $eventJs = (string) file_get_contents($root . '/js/eventFunctions.js');
    foreach (['divTitle', 'divDescription', 'divVenue', 'divExperience'] as $variable) {
        eventAssert(str_contains($eventJs, $variable . '.textContent'), "{$variable} gebruikt geen veilige tekstweergave");
    }
    eventAssert(!is_file($root . '/api/events/deleteEvent.php'), 'Er verscheen onverwacht een eventverwijderroute');
} finally {
    foreach ($stateFiles as $file) if (is_file($file)) unlink($file);
    eventRemoveTree($fixture);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Event module endpoint tests passed." . PHP_EOL;

