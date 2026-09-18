<?php
declare(strict_types=1);

if (defined('AETHER_SIMPLE_ENDPOINT_TEST_BOOTSTRAP')) {
    final class SimpleCharacterEndpointStatement extends PDOStatement
    {
        private array $parameters = [];
        private int $affectedRows = 0;

        public function __construct(
            private SimpleCharacterEndpointPdo $testPdo,
            private string $query
        ) {
        }

        public function execute(?array $params = null): bool
        {
            $this->parameters = $params ?? [];
            $this->affectedRows = 0;

            if (str_contains($this->query, 'INSERT INTO tblCharacter')) {
                $this->testPdo->mutations++;
                $this->testPdo->lastWriteParameters = $this->parameters;
                $this->affectedRows = 1;
            }
            if (str_contains($this->query, 'DELETE FROM tblCharacterLanguage')) {
                $languageId = (int) ($this->parameters['id'] ?? 0);
                $characterId = (int) ($this->parameters['idCharacter'] ?? 0);
                if (($languageId === 71 && $characterId === 1)
                    || ($languageId === 72 && $characterId === 2)) {
                    $this->testPdo->mutations++;
                    $this->affectedRows = 1;
                }
            }

            return true;
        }

        public function fetch(
            int $mode = PDO::FETCH_DEFAULT,
            int $cursorOrientation = PDO::FETCH_ORI_NEXT,
            int $cursorOffset = 0
        ): mixed {
            if (str_contains($this->query, 'FROM tblUser')) {
                return $this->testPdo->users[(int) ($this->parameters['id'] ?? 0)] ?? false;
            }
            if (str_contains($this->query, 'FROM tblCharacter') && str_contains($this->query, 'WHERE id =')) {
                return $this->testPdo->characters[(int) ($this->parameters['id'] ?? 0)] ?? false;
            }

            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            if (str_contains($this->query, 'FROM tblCharacter')) {
                $characters = array_values($this->testPdo->characters);
                if (str_contains($this->query, 'WHERE idUser = :uid')) {
                    $userId = (int) ($this->parameters['uid'] ?? 0);
                    $characters = array_values(array_filter(
                        $characters,
                        static fn(array $character): bool => (int) $character['idUser'] === $userId
                    ));
                }
                return $characters;
            }
            if (str_contains($this->query, 'FROM tblSkillSpecialisation')) {
                return [
                    ['id' => '301', 'name' => 'Telepathie'],
                    ['id' => '302', 'name' => 'Telekinese'],
                ];
            }
            if (str_contains($this->query, 'FROM tblSkill') && str_contains($this->query, 'NOT IN')) {
                $skills = [
                    ['id' => 5, 'name' => 'academicus', 'visibility' => 'public'],
                    ['id' => 6, 'name' => 'verborgen kunst', 'visibility' => 'secret'],
                ];
                if (str_contains($this->query, "visibility = 'public'")) {
                    $skills = array_values(array_filter(
                        $skills,
                        static fn(array $skill): bool => $skill['visibility'] === 'public'
                    ));
                }
                return array_map(
                    static fn(array $skill): array => ['id' => $skill['id'], 'name' => $skill['name']],
                    $skills
                );
            }

            return [];
        }

        public function fetchColumn(int $column = 0): mixed
        {
            if (str_contains($this->query, 'SELECT visibility FROM tblSkill')) {
                $skillId = (int) ($this->parameters['id'] ?? 0);
                return $skillId === 6 ? 'secret' : 'public';
            }

            return false;
        }

        public function rowCount(): int
        {
            return $this->affectedRows;
        }
    }

    final class SimpleCharacterEndpointPdo extends PDO
    {
        public int $mutations = 0;
        public array $lastWriteParameters = [];

        public function __construct(public array $users, public array $characters)
        {
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new SimpleCharacterEndpointStatement($this, $query);
        }

        public function lastInsertId(?string $name = null): string|false
        {
            return '77';
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

    function getCharacterPortraitUrl(int $characterId): ?string
    {
        return null;
    }

    function characterLanguageSchemaReady(PDO $pdo): bool
    {
        return true;
    }

    function canCurrentUserManageCharacterLanguages(array $character, string $role, int $currentUserId): bool
    {
        return $role === 'administrator'
            || $role === 'director'
            || ($role === 'participant'
                && (string) ($character['type'] ?? '') === 'player'
                && (int) ($character['idUser'] ?? 0) === $currentUserId);
    }

    $scenario = (string) ($argv[2] ?? 'success');
    $users = [
        10 => ['id' => 10, 'username' => 'participant', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'director', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
        30 => ['id' => 30, 'username' => 'administrator', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
    ];
    $characters = [
        1 => ['id' => 1, 'idUser' => 10, 'firstName' => 'Eigen', 'lastName' => 'Speler', 'type' => 'player', 'state' => 'active', 'class' => 'upper class'],
        2 => ['id' => 2, 'idUser' => 99, 'firstName' => 'Andere', 'lastName' => 'Speler', 'type' => 'player', 'state' => 'active', 'class' => 'middle class'],
    ];
    $pdo = new SimpleCharacterEndpointPdo($users, $characters);

    $sessionUserId = match ($scenario) {
        'unauthenticated' => 999,
        'director' => 20,
        'administrator' => 30,
        default => 10,
    };
    session_start();
    $_SESSION = [
        'user' => [
            'id' => $sessionUserId,
            'role' => $scenario === 'forged_role' ? 'administrator' : 'participant',
        ],
        'aetherCsrfToken' => 'expected-token',
    ];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf' ? 'forged-token' : 'expected-token';

    register_shutdown_function(static function () use ($pdo): void {
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_SIMPLE_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'mutations' => $pdo->mutations,
            'lastWriteParameters' => $pdo->lastWriteParameters,
        ]) . "\n");
    });

    return;
}

$projectRoot = dirname(__DIR__);
$routeNames = [
    'getCharacterList', 'getNewSkills', 'getDisciplineList',
    'newCharacter', 'deleteCharacterLanguage',
];
$fixturePaths = [];
$bootstrap = "define('AETHER_SIMPLE_ENDPOINT_TEST_BOOTSTRAP', true);\nrequire " . var_export(__FILE__, true) . ';';

foreach ($routeNames as $routeName) {
    $source = file_get_contents($projectRoot . "/api/characters/{$routeName}.php");
    if ($source === false) {
        throw new RuntimeException("Kon {$routeName}.php niet lezen.");
    }
    $source = str_replace("require __DIR__ . '/../../db.php';", $bootstrap, $source, $dbReplacementCount);
    if ($dbReplacementCount !== 1) {
        throw new RuntimeException("Kon de PDO-dependency van {$routeName} niet vervangen.");
    }
    $source = str_replace("require_once __DIR__ . '/characterMediaUtils.php';", '// testdouble voor portret-URL', $source);
    $source = str_replace("require_once __DIR__ . '/characterLanguageUtils.php';", '// testdouble voor taalbeleid', $source);

    $fixturePath = tempnam($projectRoot . '/api/characters', ".simple-{$routeName}-");
    if ($fixturePath === false) {
        throw new RuntimeException("Kon fixture voor {$routeName} niet maken.");
    }
    file_put_contents($fixturePath, $source);
    $fixturePaths[$routeName] = $fixturePath;
}

$failures = [];

function assertSimpleEndpoint(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function runSimpleEndpoint(string $fixturePath, string $routeName, string $scenario, array|string $request): array
{
    $process = proc_open(
        [PHP_BINARY, $fixturePath, $routeName, $scenario],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Kon {$routeName}/{$scenario} niet starten.");
    }
    fwrite($pipes[0], is_string($request) ? $request : json_encode($request));
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!preg_match('/__AETHER_SIMPLE_STATE__:(\{.*\})/', $stderr, $matches)) {
        throw new RuntimeException("{$routeName}/{$scenario} rapporteerde geen status. STDERR: {$stderr}");
    }
    $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

    return [
        'body' => $body,
        'status' => (int) $state['status'],
        'mutations' => (int) $state['mutations'],
        'lastWriteParameters' => $state['lastWriteParameters'],
        'exitCode' => $exitCode,
        'stderr' => $stderr,
    ];
}

function expectSimpleEndpoint(
    array $fixtures,
    string $routeName,
    string $scenario,
    array|string $request,
    int $status,
    mixed $body,
    int $mutations = 0
): array {
    $result = runSimpleEndpoint($fixtures[$routeName], $routeName, $scenario, $request);
    assertSimpleEndpoint($result['status'] === $status, "{$routeName}/{$scenario}: HTTP {$result['status']} in plaats van {$status}.");
    assertSimpleEndpoint($result['body'] === json_encode($body), "{$routeName}/{$scenario}: onverwachte body {$result['body']}.");
    assertSimpleEndpoint($result['mutations'] === $mutations, "{$routeName}/{$scenario}: onverwacht aantal wijzigingen {$result['mutations']}.");
    assertSimpleEndpoint($result['exitCode'] === 0, "{$routeName}/{$scenario}: exit {$result['exitCode']}; {$result['stderr']}");
    return $result;
}

$validCharacter = [
    'idUser' => 99, 'type' => 'extra', 'state' => 'active',
    'firstName' => 'Nieuw', 'lastName' => 'Personage', 'class' => 'middle class',
    'birthDate' => '1900-01-01', 'birthPlace' => '', 'nationality' => '',
    'stateRegisterNumber' => '', 'street' => '', 'houseNumber' => '',
    'municipality' => '', 'postalCode' => '', 'title' => '', 'maritalStatus' => '',
    'physicalHealth' => 0, 'mentalHealth' => 0,
    'physicalHealthFree' => 4, 'mentalHealthFree' => 5,
];

try {
    expectSimpleEndpoint($fixturePaths, 'getCharacterList', 'success', '{}', 200, [[
        'id' => 1, 'idUser' => 10, 'firstName' => 'Eigen', 'lastName' => 'Speler',
        'type' => 'player', 'state' => 'active', 'class' => 'upper class', 'portraitUrl' => null,
    ]]);
    expectSimpleEndpoint($fixturePaths, 'getCharacterList', 'director', '{}', 200, [
        ['id' => 1, 'idUser' => 10, 'firstName' => 'Eigen', 'lastName' => 'Speler', 'type' => 'player', 'state' => 'active', 'class' => 'upper class', 'portraitUrl' => null],
        ['id' => 2, 'idUser' => 99, 'firstName' => 'Andere', 'lastName' => 'Speler', 'type' => 'player', 'state' => 'active', 'class' => 'middle class', 'portraitUrl' => null],
    ]);
    expectSimpleEndpoint($fixturePaths, 'getCharacterList', 'forged_role', '{}', 200, [[
        'id' => 1, 'idUser' => 10, 'firstName' => 'Eigen', 'lastName' => 'Speler',
        'type' => 'player', 'state' => 'active', 'class' => 'upper class', 'portraitUrl' => null,
    ]]);
    expectSimpleEndpoint($fixturePaths, 'getCharacterList', 'unauthenticated', '{}', 401, ['error' => 'Not authenticated']);
    expectSimpleEndpoint($fixturePaths, 'getCharacterList', 'unknown_field', ['role' => 'administrator'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: role.'],
    ]);

    expectSimpleEndpoint($fixturePaths, 'getNewSkills', 'success', ['id' => 1], 200, [[
        'id' => 5, 'name' => 'academicus',
    ]]);
    expectSimpleEndpoint($fixturePaths, 'getNewSkills', 'director', ['id' => 2], 200, [
        ['id' => 5, 'name' => 'academicus'], ['id' => 6, 'name' => 'verborgen kunst'],
    ]);
    expectSimpleEndpoint($fixturePaths, 'getNewSkills', 'forged_role', ['id' => 2], 403, ['error' => 'Je hebt geen rechten voor dit personage.']);
    expectSimpleEndpoint($fixturePaths, 'getNewSkills', 'unauthenticated', ['id' => 1], 401, ['error' => 'Not authenticated']);
    expectSimpleEndpoint($fixturePaths, 'getNewSkills', 'unknown_field', ['id' => 1, 'idUser' => 99], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: idUser.'],
    ]);
    expectSimpleEndpoint($fixturePaths, 'getNewSkills', 'invalid_id', ['id' => 0], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld id is kleiner dan toegestaan.'],
    ]);

    expectSimpleEndpoint($fixturePaths, 'getDisciplineList', 'success', ['idSkill' => 5, 'idCharacter' => 1], 200, [
        'options' => [['id' => 301, 'name' => 'Telepathie'], ['id' => 302, 'name' => 'Telekinese']],
    ]);
    expectSimpleEndpoint($fixturePaths, 'getDisciplineList', 'director', ['idSkill' => 6, 'idCharacter' => 2], 200, [
        'options' => [['id' => 301, 'name' => 'Telepathie'], ['id' => 302, 'name' => 'Telekinese']],
    ]);
    expectSimpleEndpoint($fixturePaths, 'getDisciplineList', 'forged_role', ['idSkill' => 5, 'idCharacter' => 2], 403, ['error' => 'Je hebt geen rechten voor dit personage.']);
    expectSimpleEndpoint($fixturePaths, 'getDisciplineList', 'secret_skill', ['idSkill' => 6, 'idCharacter' => 1], 403, ['error' => 'Je hebt geen rechten om deze vaardigheid te beheren.']);
    expectSimpleEndpoint($fixturePaths, 'getDisciplineList', 'unauthenticated', ['idSkill' => 5, 'idCharacter' => 1], 401, ['error' => 'Not authenticated']);
    expectSimpleEndpoint($fixturePaths, 'getDisciplineList', 'unknown_field', ['idSkill' => 5, 'idCharacter' => 1, 'role' => 'director'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: role.'],
    ]);

    $created = expectSimpleEndpoint($fixturePaths, 'newCharacter', 'success', $validCharacter, 200, 77, 1);
    assertSimpleEndpoint(($created['lastWriteParameters']['idUser'] ?? null) === 10, 'newCharacter: participant-eigenaarschap is niet server-side afgedwongen.');
    assertSimpleEndpoint(($created['lastWriteParameters']['type'] ?? null) === 'player', 'newCharacter: participant-type is niet server-side afgedwongen.');
    assertSimpleEndpoint(($created['lastWriteParameters']['state'] ?? null) === 'draft', 'newCharacter: beginstatus is niet server-side afgedwongen.');
    assertSimpleEndpoint(($created['lastWriteParameters']['createdBy'] ?? null) === 10, 'newCharacter: createdBy komt niet uit de vertrouwde gebruiker.');
    $directorCreated = expectSimpleEndpoint($fixturePaths, 'newCharacter', 'director', $validCharacter, 200, 77, 1);
    assertSimpleEndpoint(($directorCreated['lastWriteParameters']['idUser'] ?? null) === 99, 'newCharacter: director kan de bedoelde eigenaar niet behouden.');
    assertSimpleEndpoint(($directorCreated['lastWriteParameters']['type'] ?? null) === 'extra', 'newCharacter: director kan het bedoelde type niet behouden.');
    $forgedCreated = expectSimpleEndpoint($fixturePaths, 'newCharacter', 'forged_role', $validCharacter, 200, 77, 1);
    assertSimpleEndpoint(($forgedCreated['lastWriteParameters']['idUser'] ?? null) === 10, 'newCharacter: vervalste sessierol gaf extra eigenaarsrechten.');
    expectSimpleEndpoint($fixturePaths, 'newCharacter', 'invalid_csrf', $validCharacter, 403, ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']);
    expectSimpleEndpoint($fixturePaths, 'newCharacter', 'unauthenticated', $validCharacter, 401, ['error' => 'Not authenticated']);
    expectSimpleEndpoint($fixturePaths, 'newCharacter', 'unknown_field', $validCharacter + ['role' => 'administrator'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: role.'],
    ]);

    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'success', ['idCharacter' => 1, 'idCharacterLanguage' => 71], 200, ['success' => true], 1);
    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'director', ['idCharacter' => 2, 'idCharacterLanguage' => 72], 200, ['success' => true], 1);
    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'forged_role', ['idCharacter' => 2, 'idCharacterLanguage' => 72], 403, ['error' => 'Geen rechten om talen te beheren.']);
    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'invalid_csrf', ['idCharacter' => 1, 'idCharacterLanguage' => 71], 403, ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']);
    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'unauthenticated', ['idCharacter' => 1, 'idCharacterLanguage' => 71], 401, ['error' => 'Not authenticated']);
    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'unknown_field', ['idCharacter' => 1, 'idCharacterLanguage' => 71, 'idUser' => 10], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: idUser.'],
    ]);
    expectSimpleEndpoint($fixturePaths, 'deleteCharacterLanguage', 'invalid_id', ['idCharacter' => 1, 'idCharacterLanguage' => 0], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld idCharacterLanguage is kleiner dan toegestaan.'],
    ]);
} finally {
    foreach ($fixturePaths as $fixturePath) {
        @unlink($fixturePath);
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Simple character endpoint tests passed.\n";
