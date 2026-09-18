<?php
declare(strict_types=1);

if (defined('AETHER_CHARACTER_TIE_TEST_BOOTSTRAP')) {
    final class CharacterTieTestStatement extends PDOStatement
    {
        private array $parameters = [];
        private array $rows = [];
        private int $cursor = 0;
        private int $affectedRows = 0;

        public function __construct(private CharacterTieTestPdo $testPdo, private string $query)
        {
        }

        public function execute(?array $params = null): bool
        {
            $this->parameters = $params ?? [];
            $this->rows = [];
            $this->cursor = 0;
            $this->affectedRows = 0;
            $sql = preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);

            if ($this->testPdo->scenario === 'server_error'
                && (str_contains($sql, 'FROM tblCharacterTie t')
                    || str_contains($sql, 'SELECT id, firstName, lastName, title, class FROM tblCharacter'))) {
                throw new PDOException('SQLSTATE[42S02]: secret_tie_table detail');
            }

            if (str_starts_with($sql, 'INSERT INTO tblCharacterTie')) {
                $this->testPdo->recordWrite($sql, $this->parameters);
                if ($this->testPdo->scenario === 'server_error') {
                    throw new PDOException('SQLSTATE[42S02]: secret_tie_table detail');
                }
                $id = $this->testPdo->nextTieId++;
                $this->testPdo->lastInsertIdValue = $id;
                $this->testPdo->ties[$id] = [
                    'id' => $id,
                    'idCharacter' => (int) $this->parameters[':idCharacter'],
                    'idCharacterTarget' => (int) $this->parameters[':idOtherCharacter'],
                    'relationType' => (string) $this->parameters[':relationType'],
                    'description' => (string) $this->parameters[':description'],
                ];
                $this->affectedRows = 1;
                return true;
            }
            if (str_starts_with($sql, 'UPDATE tblCharacterTie')) {
                $this->testPdo->recordWrite($sql, $this->parameters);
                $id = (int) $this->parameters[':idTie'];
                if (isset($this->testPdo->ties[$id])
                    && (int) $this->testPdo->ties[$id]['idCharacter'] === (int) $this->parameters[':idCharacter']) {
                    $this->testPdo->ties[$id]['idCharacterTarget'] = (int) $this->parameters[':idOtherCharacter'];
                    $this->testPdo->ties[$id]['relationType'] = (string) $this->parameters[':relationType'];
                    $this->testPdo->ties[$id]['description'] = (string) $this->parameters[':description'];
                    $this->affectedRows = 1;
                }
                return true;
            }
            if (str_starts_with($sql, 'DELETE FROM tblCharacterTie')) {
                if ($this->testPdo->scenario === 'server_error') {
                    $this->testPdo->recordFailedWrite($sql, $this->parameters);
                    throw new PDOException('SQLSTATE[42S02]: secret_tie_table detail');
                }
                $this->testPdo->recordWrite($sql, $this->parameters);
                $id = (int) $this->parameters[':idTie'];
                if (isset($this->testPdo->ties[$id])
                    && (int) $this->testPdo->ties[$id]['idCharacter'] === (int) $this->parameters[':idCharacter']) {
                    unset($this->testPdo->ties[$id]);
                    $this->affectedRows = 1;
                }
                return true;
            }
            if (str_starts_with($sql, 'UPDATE tblCharacter SET street')) {
                $this->testPdo->recordWrite($sql, $this->parameters);
                if ($this->testPdo->scenario === 'address_error') {
                    throw new PDOException('SQLSTATE[42S02]: secret_address_table detail');
                }
                $id = (int) $this->parameters[':idCharacter'];
                foreach (['street', 'houseNumber', 'postalCode', 'municipality'] as $field) {
                    $this->testPdo->characters[$id][$field] = (string) $this->parameters[':' . $field];
                }
                $this->affectedRows = 1;
            }

            return true;
        }

        public function fetch(
            int $mode = PDO::FETCH_DEFAULT,
            int $cursorOrientation = PDO::FETCH_ORI_NEXT,
            int $cursorOffset = 0
        ): mixed {
            $sql = preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);
            if (str_contains($sql, 'FROM tblUser')) {
                return $this->testPdo->users[(int) ($this->parameters['id'] ?? 0)] ?? false;
            }
            if (str_contains($sql, 'FROM tblCharacterTie') && str_contains($sql, 'WHERE id = :idTie')) {
                $tie = $this->testPdo->ties[(int) $this->parameters[':idTie']] ?? null;
                return $tie !== null
                    && (int) $tie['idCharacter'] === (int) $this->parameters[':idCharacter']
                    ? ['id' => $tie['id']]
                    : false;
            }
            if (str_contains($sql, 'FROM tblCharacter') && !str_contains($sql, 'JOIN')) {
                $id = (int) ($this->parameters['id'] ?? $this->parameters[':id'] ?? $this->parameters[':idCharacter'] ?? 0);
                $character = $this->testPdo->characters[$id] ?? null;
                if ($character === null) {
                    return false;
                }
                if (str_starts_with($sql, 'SELECT street,')) {
                    return array_intersect_key($character, array_flip(['street', 'houseNumber', 'postalCode', 'municipality']));
                }
                return $character;
            }
            if (isset($this->rows[$this->cursor])) {
                return $this->rows[$this->cursor++];
            }
            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            $sql = preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);
            if (str_contains($sql, 'FROM tblCharacterTie t')) {
                $ownerId = (int) $this->parameters[':idCharacter'];
                $rows = [];
                foreach ($this->testPdo->ties as $tie) {
                    if ((int) $tie['idCharacter'] !== $ownerId) {
                        continue;
                    }
                    $target = $this->testPdo->characters[(int) $tie['idCharacterTarget']];
                    $reverse = function (string $type) use ($tie): bool {
                        foreach ($this->testPdo->ties as $candidate) {
                            if ((int) $candidate['idCharacter'] === (int) $tie['idCharacterTarget']
                                && (int) $candidate['idCharacterTarget'] === (int) $tie['idCharacter']
                                && $candidate['relationType'] === $type) {
                                return true;
                            }
                        }
                        return false;
                    };
                    $rows[] = $tie + [
                        'firstName' => $target['firstName'], 'lastName' => $target['lastName'],
                        'title' => $target['title'], 'class' => $target['class'],
                        'hasReverseSuperior' => $reverse('superior') ? 1 : 0,
                        'hasReverseLandlord' => $reverse('landlord') ? 1 : 0,
                        'hasReverseHouseholdStaff' => $reverse('household_staff') ? 1 : 0,
                        'hasReverseSpouse' => $reverse('spouse') ? 1 : 0,
                    ];
                }
                usort($rows, static fn(array $a, array $b): int => [$a['firstName'], $a['lastName']] <=> [$b['firstName'], $b['lastName']]);
                return $rows;
            }
            if (str_starts_with($sql, 'SELECT id, firstName, lastName, title, class FROM tblCharacter')) {
                $rows = array_values(array_filter(
                    $this->testPdo->characters,
                    static fn(array $character): bool => !str_contains($sql, "WHERE type = 'player'")
                        || ($character['type'] === 'player' && $character['state'] === 'active')
                ));
                usort($rows, static fn(array $a, array $b): int => [$a['firstName'], $a['lastName']] <=> [$b['firstName'], $b['lastName']]);
                return array_map(static fn(array $row): array => array_intersect_key(
                    $row,
                    array_flip(['id', 'firstName', 'lastName', 'title', 'class'])
                ), $rows);
            }
            return [];
        }

        public function fetchColumn(int $column = 0): mixed
        {
            $sql = preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);
            if (str_contains($sql, 'FROM tblCharacterTie') && str_contains($sql, 'relationType = :relationType')) {
                foreach ($this->testPdo->ties as $tie) {
                    if ((int) $tie['idCharacter'] === (int) $this->parameters[':idCharacter']
                        && (int) $tie['idCharacterTarget'] === (int) $this->parameters[':idCharacterTarget']
                        && $tie['relationType'] === $this->parameters[':relationType']) {
                        return 1;
                    }
                }
            }
            return 0;
        }

        public function rowCount(): int
        {
            return $this->affectedRows;
        }
    }

    final class CharacterTieTestPdo extends PDO
    {
        public int $writeAttempts = 0;
        public int $committedWrites = 0;
        public int $nextTieId;
        public int $lastInsertIdValue = 0;
        public array $writes = [];
        public array $users;
        public array $characters;
        public array $ties;
        private bool $transactionActive = false;
        private int $stagedWrites = 0;
        private ?array $transactionSnapshot = null;

        public function __construct(public string $scenario, array $state)
        {
            $this->users = $state['users'];
            $this->characters = $state['characters'];
            $this->ties = $state['ties'];
            $this->nextTieId = (int) $state['nextTieId'];
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new CharacterTieTestStatement($this, $query);
        }

        public function lastInsertId(?string $name = null): string|false
        {
            return (string) $this->lastInsertIdValue;
        }

        public function beginTransaction(): bool
        {
            $this->transactionActive = true;
            $this->stagedWrites = 0;
            $this->transactionSnapshot = [
                'characters' => $this->characters,
                'ties' => $this->ties,
                'nextTieId' => $this->nextTieId,
            ];
            return true;
        }

        public function recordWrite(string $sql, array $parameters): void
        {
            $this->writeAttempts++;
            $this->writes[] = ['sql' => $sql, 'parameters' => $parameters];
            if ($this->transactionActive) {
                $this->stagedWrites++;
            } else {
                $this->committedWrites++;
            }
        }

        public function recordFailedWrite(string $sql, array $parameters): void
        {
            $this->writeAttempts++;
            $this->writes[] = ['sql' => $sql, 'parameters' => $parameters];
        }

        public function commit(): bool
        {
            $this->committedWrites += $this->stagedWrites;
            $this->stagedWrites = 0;
            $this->transactionActive = false;
            $this->transactionSnapshot = null;
            return true;
        }

        public function rollBack(): bool
        {
            if ($this->transactionSnapshot !== null) {
                $this->characters = $this->transactionSnapshot['characters'];
                $this->ties = $this->transactionSnapshot['ties'];
                $this->nextTieId = $this->transactionSnapshot['nextTieId'];
            }
            $this->stagedWrites = 0;
            $this->transactionActive = false;
            $this->transactionSnapshot = null;
            return true;
        }

        public function inTransaction(): bool
        {
            return $this->transactionActive;
        }

        public function exportState(): array
        {
            return [
                'users' => $this->users,
                'characters' => $this->characters,
                'ties' => $this->ties,
                'nextTieId' => $this->nextTieId,
            ];
        }
    }

    function dbAll(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function dbOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    function getPDO(): PDO
    {
        global $pdo;
        return $pdo;
    }

    $route = (string) ($argv[1] ?? 'get');
    $scenario = (string) ($argv[2] ?? 'participant');
    $stateFile = (string) ($argv[3] ?? '');
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new CharacterTieTestPdo($scenario, $state);

    $userId = match ($scenario) {
        'unauthenticated' => 999,
        'director' => 20,
        'administrator' => 30,
        default => 10,
    };
    session_start();
    $_SESSION = [
        'user' => ['id' => $userId, 'role' => 'administrator'],
        'aetherCsrfToken' => 'expected-token',
    ];
    if (!in_array($scenario, ['missing_csrf'], true)) {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf' ? 'forged-token' : 'expected-token';
    }
    if ($route === 'options' && $scenario === 'unexpected') {
        $_GET['unexpected'] = '1';
    }

    register_shutdown_function(static function () use ($pdo, $stateFile): void {
        file_put_contents($stateFile, json_encode($pdo->exportState(), JSON_THROW_ON_ERROR));
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_TIE_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'writeAttempts' => $pdo->writeAttempts,
            'committedWrites' => $pdo->committedWrites,
            'writes' => $pdo->writes,
        ]) . "\n");
    });
    return;
}

$projectRoot = dirname(__DIR__);
$fixtureRoot = sys_get_temp_dir() . '/aether-character-ties-' . bin2hex(random_bytes(6));

function copyCharacterTieFixtureTree(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
        throw new RuntimeException("Kon fixturemap {$target} niet maken.");
    }
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) {
            continue;
        }
        $targetPath = $target . '/' . $item->getFilename();
        if ($item->isDir()) {
            copyCharacterTieFixtureTree($item->getPathname(), $targetPath);
        } elseif (!copy($item->getPathname(), $targetPath)) {
            throw new RuntimeException("Kon fixturebestand {$item->getPathname()} niet kopieren.");
        }
    }
}

function removeCharacterTieFixtureTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

copyCharacterTieFixtureTree($projectRoot . '/api', $fixtureRoot . '/api');
copy($projectRoot . '/sessionUserBootstrap.php', $fixtureRoot . '/sessionUserBootstrap.php');
file_put_contents(
    $fixtureRoot . '/db.php',
    "<?php\ndefine('AETHER_CHARACTER_TIE_TEST_BOOTSTRAP', true);\nrequire "
        . var_export(__FILE__, true) . ";\n"
);

$routes = [
    'get' => $fixtureRoot . '/api/characters/getCharacterTies.php',
    'options' => $fixtureRoot . '/api/characters/getCharacterTieOptions.php',
    'save' => $fixtureRoot . '/api/characters/saveCharacterTie.php',
    'delete' => $fixtureRoot . '/api/characters/deleteCharacterTie.php',
];
$initialState = [
    'users' => [
        10 => ['id' => 10, 'username' => 'participant', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'director', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
        30 => ['id' => 30, 'username' => 'administrator', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
    ],
    'characters' => [
        1 => ['id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'upper class', 'firstName' => 'Own', 'lastName' => 'Character', 'title' => 'Count', 'street' => 'Old street', 'houseNumber' => '1', 'postalCode' => '1000', 'municipality' => 'Oldtown'],
        2 => ['id' => 2, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'middle class', 'firstName' => 'Active', 'lastName' => 'Player', 'title' => '', 'street' => 'Second street', 'houseNumber' => '2', 'postalCode' => '2000', 'municipality' => 'Secondtown'],
        3 => ['id' => 3, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'upper class', 'firstName' => 'Land', 'lastName' => 'Lord', 'title' => 'Duke', 'street' => 'Landlord lane', 'houseNumber' => '3', 'postalCode' => '3000', 'municipality' => 'Richville'],
        4 => ['id' => 4, 'idUser' => 99, 'type' => 'extra', 'state' => 'inactive', 'class' => 'lower class', 'firstName' => 'Hidden', 'lastName' => 'Extra', 'title' => '', 'street' => '', 'houseNumber' => '', 'postalCode' => '', 'municipality' => ''],
    ],
    'ties' => [
        100 => ['id' => 100, 'idCharacter' => 1, 'idCharacterTarget' => 2, 'relationType' => 'ally', 'description' => '<b>plain description</b>'],
        101 => ['id' => 101, 'idCharacter' => 3, 'idCharacterTarget' => 1, 'relationType' => 'landlord', 'description' => 'Confirmed landlord'],
    ],
    'nextTieId' => 102,
];
$failures = [];

function assertCharacterTie(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function createCharacterTieStateFile(array $state): string
{
    $path = tempnam(sys_get_temp_dir(), 'aether-tie-state-');
    if ($path === false) {
        throw new RuntimeException('Kon geen tie-statebestand maken.');
    }
    file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
    return $path;
}

/** @return array{body:string,decoded:mixed,status:int,writeAttempts:int,committedWrites:int,writes:array,stderr:string,exitCode:int,state:array} */
function runCharacterTieScenario(
    string $path,
    string $route,
    string $scenario,
    array|string|null $request,
    string $stateFile
): array {
    $process = proc_open(
        [PHP_BINARY, $path, $route, $scenario, $stateFile],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Kon {$route}/{$scenario} niet starten.");
    }
    if ($request !== null) {
        fwrite($pipes[0], is_string($request) ? $request : json_encode($request));
    }
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if (!preg_match('/__AETHER_TIE_STATE__:(\{.*\})/', $stderr, $matches)) {
        throw new RuntimeException("Geen teststatus voor {$route}/{$scenario}: {$stderr}");
    }
    $meta = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    return [
        'body' => $body,
        'decoded' => json_decode($body, true),
        'status' => (int) $meta['status'],
        'writeAttempts' => (int) $meta['writeAttempts'],
        'committedWrites' => (int) $meta['committedWrites'],
        'writes' => $meta['writes'],
        'stderr' => $stderr,
        'exitCode' => $exitCode,
        'state' => json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR),
    ];
}

function assertCharacterTieError(array $result, int $status, int $writes, string $label): void
{
    assertCharacterTie($result['status'] === $status, "{$label}: HTTP {$result['status']} in plaats van {$status}");
    assertCharacterTie(is_array($result['decoded']) && isset($result['decoded']['error']), "{$label}: error ontbreekt");
    assertCharacterTie($result['writeAttempts'] === $writes, "{$label}: {$result['writeAttempts']} writepogingen in plaats van {$writes}");
    assertCharacterTie($result['exitCode'] === 0, "{$label}: processtatus {$result['exitCode']}; {$result['stderr']}");
}

$stateFiles = [];
try {
    $readState = $stateFiles[] = createCharacterTieStateFile($initialState);
    $ownTies = runCharacterTieScenario($routes['get'], 'get', 'participant', ['idCharacter' => 1], $readState);
    assertCharacterTie($ownTies['status'] === 200, 'Participant kon eigen ties niet lezen');
    assertCharacterTie(count($ownTies['decoded'] ?? []) === 1, 'Eigen tielijst bevat onverwacht aantal regels');
    assertCharacterTie(array_keys($ownTies['decoded'][0] ?? []) === [
        'id', 'idOtherCharacter', 'relationType', 'relationTypeLabel', 'description', 'otherName',
        'firstName', 'lastName', 'otherClass', 'otherRecurringIncomeTotal',
        'otherMiddleClassLivingStandardIncome', 'otherUpperClassLivingStandardTier', 'portraitUrl',
        'hasReverseSuperior', 'hasReverseLandlord', 'hasReverseHouseholdStaff', 'hasReverseSpouse',
    ], 'Tielijstresponse wijzigde van vorm of veldvolgorde');
    assertCharacterTie(($ownTies['decoded'][0]['description'] ?? '') === '<b>plain description</b>', 'Tieomschrijving werd onverwacht als HTML verwerkt');
    assertCharacterTieError(
        runCharacterTieScenario($routes['get'], 'get', 'participant', ['idCharacter' => 2], $readState),
        403, 0, 'Participant leest ties van een ander'
    );
    assertCharacterTieError(
        runCharacterTieScenario($routes['get'], 'get', 'participant', ['idCharacter' => 999], $readState),
        404, 0, 'Onbekend character bij tielijst'
    );
    foreach (['director', 'administrator'] as $role) {
        $result = runCharacterTieScenario($routes['get'], 'get', $role, ['idCharacter' => 2], $readState);
        assertCharacterTie($result['status'] === 200 && $result['decoded'] === [], "{$role} verloor tielijstrechten");
    }

    $participantOptions = runCharacterTieScenario($routes['options'], 'options', 'participant', null, $readState);
    assertCharacterTie($participantOptions['status'] === 200, 'Participant kon tie-opties niet laden');
    assertCharacterTie(array_column($participantOptions['decoded'] ?? [], 'id') === [2, 3, 1], 'Participantopties bevatten niet exact actieve player-characters in naamvolgorde');
    assertCharacterTie(!in_array(4, array_column($participantOptions['decoded'] ?? [], 'id'), true), 'Inactieve extra lekte in participantopties');
    $directorOptions = runCharacterTieScenario($routes['options'], 'options', 'director', null, $readState);
    assertCharacterTie(count($directorOptions['decoded'] ?? []) === 4, 'Director kreeg niet alle tie-opties');
    assertCharacterTie(array_keys($directorOptions['decoded'][0] ?? []) === ['id', 'displayName', 'class', 'recurringIncomeTotal', 'canBeLandlord'], 'Tie-optieresponse wijzigde');

    $writeState = $stateFiles[] = createCharacterTieStateFile($initialState);
    $create = runCharacterTieScenario($routes['save'], 'save', 'participant', [
        'idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 2,
        'relationType' => 'ally', 'description' => '  Nieuwe band  ',
    ], $writeState);
    assertCharacterTie($create['status'] === 200, 'Nieuwe tie met frontend-null kon niet worden opgeslagen: ' . $create['body']);
    assertCharacterTie(array_keys($create['decoded'] ?? []) === ['success', 'ties', 'syncedAddress'], 'Save-response wijzigde');
    assertCharacterTie($create['decoded']['success'] === true && $create['decoded']['syncedAddress'] === null, 'Save-responsewaarden wijzigden');
    assertCharacterTie($create['committedWrites'] === 1, 'Nieuwe tie committe niet exact een write');
    assertCharacterTie(($create['writes'][0]['parameters'] ?? []) === [
        ':idCharacter' => 1, ':idOtherCharacter' => 2, ':relationType' => 'ally',
        ':description' => 'Nieuwe band', ':updatedBy' => 10, ':createdBy' => 10,
    ], 'Insert prepared parameters zijn niet exact');
    $reread = runCharacterTieScenario($routes['get'], 'get', 'participant', ['idCharacter' => 1], $writeState);
    assertCharacterTie(in_array('Nieuwe band', array_column($reread['decoded'] ?? [], 'description'), true), 'Nieuwe tie bleef niet bestaan bij opnieuw lezen');

    $update = runCharacterTieScenario($routes['save'], 'save', 'participant', [
        'idCharacter' => 1, 'idTie' => 102, 'idOtherCharacter' => 2,
        'relationType' => 'adversary', 'description' => 'Gewijzigd',
    ], $writeState);
    assertCharacterTie($update['status'] === 200 && $update['committedWrites'] === 1, 'Tie-update mislukte');
    assertCharacterTie(($update['writes'][0]['parameters'] ?? []) === [
        ':idOtherCharacter' => 2, ':relationType' => 'adversary', ':description' => 'Gewijzigd',
        ':updatedBy' => 10, ':idTie' => 102, ':idCharacter' => 1,
    ], 'Update prepared parameters zijn niet exact');

    $duplicate = runCharacterTieScenario($routes['save'], 'save', 'participant', [
        'idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 2,
        'relationType' => 'adversary', 'description' => 'Duplicaat blijft toegestaan',
    ], $writeState);
    $sameRelation = array_filter($duplicate['decoded']['ties'] ?? [], static fn(array $tie): bool => $tie['idOtherCharacter'] === 2 && $tie['relationType'] === 'adversary');
    assertCharacterTie(count($sameRelation) === 2, 'Bestaand duplicaatgedrag wijzigde');

    $delete = runCharacterTieScenario($routes['delete'], 'delete', 'participant', ['idCharacter' => 1, 'idTie' => 102], $writeState);
    assertCharacterTie($delete['status'] === 200 && array_keys($delete['decoded'] ?? []) === ['success', 'ties'], 'Delete-response wijzigde');
    assertCharacterTie(($delete['writes'][0]['parameters'] ?? []) === [':idTie' => 102, ':idCharacter' => 1], 'Delete prepared parameters zijn niet exact');
    $afterDelete = runCharacterTieScenario($routes['get'], 'get', 'participant', ['idCharacter' => 1], $writeState);
    assertCharacterTie(!in_array(102, array_column($afterDelete['decoded'] ?? [], 'id'), true), 'Verwijderde tie bleef na opnieuw lezen bestaan');

    $syncState = $stateFiles[] = createCharacterTieStateFile($initialState);
    $sync = runCharacterTieScenario($routes['save'], 'save', 'participant', [
        'idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 3,
        'relationType' => 'household_staff', 'description' => 'Confirmed staff',
    ], $syncState);
    assertCharacterTie($sync['status'] === 200 && $sync['committedWrites'] === 2, 'Gekoppelde tie/adreswrites committen niet samen');
    assertCharacterTie(($sync['decoded']['syncedAddress']['idCharacter'] ?? 0) === 1, 'Gesynchroniseerd adres ontbreekt in response');
    assertCharacterTie(($sync['state']['characters'][1]['street'] ?? '') === 'Landlord lane', 'Adreswrite werd niet gecommit');

    $rollbackState = $stateFiles[] = createCharacterTieStateFile($initialState);
    $rollback = runCharacterTieScenario($routes['save'], 'save', 'address_error', [
        'idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 3,
        'relationType' => 'household_staff', 'description' => 'Must roll back',
    ], $rollbackState);
    assertCharacterTieError($rollback, 500, 2, 'Adresfout');
    assertCharacterTie($rollback['committedWrites'] === 0, 'Rollback rapporteerde gecommitte writes');
    assertCharacterTie(count($rollback['state']['ties']) === count($initialState['ties']), 'Tie-insert bleef na rollback bestaan');
    assertCharacterTie(($rollback['state']['characters'][1]['street'] ?? '') === 'Old street', 'Adres wijzigde ondanks rollback');
    assertCharacterTie(!str_contains($rollback['body'], 'SQLSTATE') && !str_contains($rollback['body'], 'secret_'), 'Technisch databasedetail lekte in 500-response');

    $rejectionCases = [
        ['save', 'participant', ['idCharacter' => 2, 'idTie' => null, 'idOtherCharacter' => 1, 'relationType' => 'ally', 'description' => 'x'], 403, 'participant wijzigt ander character'],
        ['save', 'participant', ['idCharacter' => 999, 'idTie' => null, 'idOtherCharacter' => 2, 'relationType' => 'ally', 'description' => 'x'], 404, 'onbekend broncharacter'],
        ['save', 'participant', ['idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 999, 'relationType' => 'ally', 'description' => 'x'], 404, 'onbekend doelcharacter'],
        ['save', 'participant', ['idCharacter' => 1, 'idTie' => 999, 'idOtherCharacter' => 2, 'relationType' => 'ally', 'description' => 'x'], 404, 'onbekende update-tie'],
        ['save', 'participant', ['idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 1, 'relationType' => 'ally', 'description' => 'x'], 400, 'zelfkoppeling'],
        ['save', 'participant', ['idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 4, 'relationType' => 'ally', 'description' => 'x'], 403, 'participant koppelt inactieve extra'],
        ['delete', 'participant', ['idCharacter' => 1, 'idTie' => 999], 404, 'onbekende delete-tie'],
        ['delete', 'participant', ['idCharacter' => 999, 'idTie' => 100], 404, 'onbekend delete-character'],
        ['delete', 'participant', ['idCharacter' => 2, 'idTie' => 100], 403, 'participant verwijdert bij ander character'],
    ];
    foreach ($rejectionCases as [$route, $scenario, $request, $status, $label]) {
        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, $scenario, $request, $state), $status, 0, $label);
    }

    foreach (['director', 'administrator'] as $role) {
        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        $result = runCharacterTieScenario($routes['save'], 'save', $role, [
            'idCharacter' => 2, 'idTie' => null, 'idOtherCharacter' => 4,
            'relationType' => 'ally', 'description' => 'Privileged',
        ], $state);
        assertCharacterTie($result['status'] === 200 && $result['committedWrites'] === 1, "{$role} verloor bestaande schrijfrechten");
    }

    foreach (['save', 'delete'] as $route) {
        $validRequest = $route === 'save'
            ? ['idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 2, 'relationType' => 'ally', 'description' => 'x']
            : ['idCharacter' => 1, 'idTie' => 100];
        foreach (['missing_csrf', 'invalid_csrf'] as $scenario) {
            $state = $stateFiles[] = createCharacterTieStateFile($initialState);
            assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, $scenario, $validRequest, $state), 403, 0, "{$route}: {$scenario}");
        }
    }

    $invalidEnumState = $stateFiles[] = createCharacterTieStateFile($initialState);
    assertCharacterTieError(runCharacterTieScenario($routes['save'], 'save', 'participant', [
        'idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 2,
        'relationType' => 'sql_column', 'description' => 'x',
    ], $invalidEnumState), 422, 0, 'Save: ongeldige relationType');

    foreach (['get' => ['idCharacter' => 1], 'save' => ['idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 2, 'relationType' => 'ally'], 'delete' => ['idCharacter' => 1, 'idTie' => 100]] as $route => $valid) {
        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, 'unauthenticated', $valid, $state), 401, 0, "{$route}: niet aangemeld");

        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, 'participant', '{}', $state), 422, 0, "{$route}: ontbrekende velden");

        $invalid = $valid;
        $invalid[array_key_first($invalid)] = 'fout';
        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, 'participant', $invalid, $state), 422, 0, "{$route}: ongeldig veld");

        $unexpected = $valid + ['unexpected' => true];
        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, 'participant', $unexpected, $state), 422, 0, "{$route}: onverwacht veld");

        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        assertCharacterTieError(runCharacterTieScenario($routes[$route], $route, 'participant', '{ongeldige-json', $state), 400, 0, "{$route}: ongeldige JSON");
    }

    $optionsUnexpectedState = $stateFiles[] = createCharacterTieStateFile($initialState);
    assertCharacterTieError(runCharacterTieScenario($routes['options'], 'options', 'unexpected', null, $optionsUnexpectedState), 422, 0, 'Options: onverwacht queryveld');

    foreach (['get', 'options', 'save', 'delete'] as $route) {
        $state = $stateFiles[] = createCharacterTieStateFile($initialState);
        $request = match ($route) {
            'get' => ['idCharacter' => 1],
            'options' => null,
            'save' => ['idCharacter' => 1, 'idTie' => null, 'idOtherCharacter' => 2, 'relationType' => 'ally', 'description' => 'x'],
            'delete' => ['idCharacter' => 1, 'idTie' => 100],
        };
        $error = runCharacterTieScenario($routes[$route], $route, 'server_error', $request, $state);
        assertCharacterTieError($error, 500, in_array($route, ['save', 'delete'], true) ? 1 : 0, "{$route}: serverfout");
        $expectedError = match ($route) {
            'get' => ['error' => 'Kon ties niet ophalen.'],
            'options' => ['error' => 'Kon character lijst voor ties niet ophalen.'],
            'save' => ['error' => 'Kon tie niet opslaan.'],
            'delete' => ['error' => 'Kon tie niet verwijderen.'],
        };
        assertCharacterTie($error['decoded'] === $expectedError, "{$route}: generieke 500-response wijzigde");
        assertCharacterTie(!str_contains($error['body'], 'SQLSTATE') && !str_contains($error['body'], 'secret_'), "{$route}: databasedetails lekten");
        assertCharacterTie($error['committedWrites'] === 0, "{$route}: serverfout committe een write");
    }
} finally {
    foreach ($stateFiles as $stateFile) {
        if (is_file($stateFile)) {
            unlink($stateFile);
        }
    }
    removeCharacterTieFixtureTree($fixtureRoot);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Character tie endpoint tests passed." . PHP_EOL;
