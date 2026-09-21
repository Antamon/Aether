<?php
declare(strict_types=1);

if (defined('AETHER_SIMPLE_BATCH2_TEST_BOOTSTRAP')) {
    final class SimpleBatch2Statement extends PDOStatement
    {
        private array $parameters = [];

        public function __construct(
            private SimpleBatch2Pdo $testPdo,
            private string $query
        ) {
        }

        public function execute(?array $params = null): bool
        {
            $this->parameters = $params ?? [];
            if (str_contains($this->query, 'INSERT INTO tblLinkCharacterSkill')) {
                if ($this->testPdo->scenario === 'skill_unique_conflict'
                    && !str_contains($this->query, 'ON DUPLICATE KEY UPDATE')) {
                    $exception = new PDOException('SQLSTATE[23000]: duplicate skill link', 23000);
                    $exception->errorInfo = ['23000', 1062, 'duplicate skill link'];
                    throw $exception;
                }
                $this->testPdo->mutations++;
                $this->testPdo->lastWriteParameters = $this->parameters;
            }
            if (str_contains($this->query, 'DELETE FROM tblCharacterSpecialisation')) {
                $this->testPdo->mutations++;
                $this->testPdo->lastWriteParameters = $this->parameters;
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
            if (str_contains($this->query, 'FROM tblCharacter')) {
                return $this->testPdo->characters[(int) ($this->parameters['id'] ?? 0)] ?? false;
            }
            if (str_contains($this->query, 'SELECT * FROM tblSkill')) {
                return $this->testPdo->skills[(int) ($this->parameters['idSkill'] ?? 0)] ?? false;
            }
            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            if (str_contains($this->query, 'FROM tblSkillSpecialisation')
                && !str_contains($this->query, 'JOIN tblSkillSpecialisation')) {
                return [
                    ['id' => 301, 'name' => 'Analyse'],
                    ['id' => 302, 'name' => 'Onderzoek'],
                ];
            }
            if (str_contains($this->query, 'FROM tblCharacterSpecialisation')
                && !str_contains($this->query, 'JOIN tblSkillSpecialisation')) {
                return [['idSkillSpecialisation' => 301]];
            }
            if (str_contains($this->query, 'JOIN tblSkillSpecialisation')) {
                return [[
                    'idCharSpec' => 902,
                    'idSkillSpecialisation' => 302,
                    'name' => 'Onderzoek',
                    'kind' => 'specialisation',
                ]];
            }
            if (str_contains($this->query, 'FROM tblLanguage')) {
                return [
                    ['id' => '41', 'name' => 'Frans'],
                    ['id' => '42', 'name' => 'Nederlands'],
                ];
            }
            return [];
        }

        public function fetchColumn(int $column = 0): mixed
        {
            if (str_contains($this->query, 'SELECT visibility FROM tblSkill')) {
                $skillId = (int) ($this->parameters['id'] ?? 0);
                return (string) ($this->testPdo->skills[$skillId]['visibility'] ?? '');
            }
            return false;
        }
    }

    final class SimpleBatch2Pdo extends PDO
    {
        public int $mutations = 0;
        public array $lastWriteParameters = [];

        public function __construct(
            public array $users,
            public array $characters,
            public array $skills,
            public string $scenario
        ) {
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new SimpleBatch2Statement($this, $query);
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

    function canCharacterUseWrittenLanguages(PDO $pdo, array $character): bool
    {
        return (string) ($character['class'] ?? '') !== 'lower class';
    }

    $scenario = (string) ($argv[2] ?? 'success');
    $users = [
        10 => ['id' => 10, 'username' => 'participant', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'director', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
    ];
    $characters = [
        1 => ['id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'upper class'],
        2 => ['id' => 2, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'middle class'],
        3 => ['id' => 3, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'lower class'],
    ];
    $skills = [
        5 => ['id' => 5, 'name' => 'academicus', 'visibility' => 'public'],
        6 => ['id' => 6, 'name' => 'verborgen kunst', 'visibility' => 'secret'],
    ];
    $pdo = new SimpleBatch2Pdo($users, $characters, $skills, $scenario);

    $sessionUserId = match ($scenario) {
        'unauthenticated' => 999,
        'director' => 20,
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
        fwrite(STDERR, '__AETHER_BATCH2_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'mutations' => $pdo->mutations,
            'lastWriteParameters' => $pdo->lastWriteParameters,
        ]) . "\n");
    });
    return;
}

$projectRoot = dirname(__DIR__);
$routeNames = [
    'AddNewSkill', 'getSkillSpecialisations',
    'deleteSkillSpecialisation', 'getCharacterLanguageOptions',
];
$fixturePaths = [];
$bootstrap = "define('AETHER_SIMPLE_BATCH2_TEST_BOOTSTRAP', true);\nrequire " . var_export(__FILE__, true) . ';';

foreach ($routeNames as $routeName) {
    $source = file_get_contents($projectRoot . "/api/characters/{$routeName}.php");
    if ($source === false) {
        throw new RuntimeException("Kon {$routeName}.php niet lezen.");
    }
    $source = str_replace("require __DIR__ . '/../../db.php';", $bootstrap, $source, $dbReplacementCount);
    if ($dbReplacementCount !== 1) {
        throw new RuntimeException("Kon de PDO-dependency van {$routeName} niet vervangen.");
    }
    $source = str_replace("require_once __DIR__ . '/characterLanguageUtils.php';", '// taalhelpers via testdouble', $source);

    $fixturePath = tempnam($projectRoot . '/api/characters', ".simple-batch2-{$routeName}-");
    if ($fixturePath === false) {
        throw new RuntimeException("Kon fixture voor {$routeName} niet maken.");
    }
    file_put_contents($fixturePath, $source);
    $fixturePaths[$routeName] = $fixturePath;
}

$failures = [];

function assertSimpleBatch2(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function runSimpleBatch2(string $fixturePath, string $routeName, string $scenario, array|string $request): array
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

    if (!preg_match('/__AETHER_BATCH2_STATE__:(\{.*\})/', $stderr, $matches)) {
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

function expectSimpleBatch2(
    array $fixtures,
    string $routeName,
    string $scenario,
    array|string $request,
    int $expectedStatus,
    mixed $expectedBody,
    int $expectedMutations = 0
): array {
    $result = runSimpleBatch2($fixtures[$routeName], $routeName, $scenario, $request);
    assertSimpleBatch2($result['status'] === $expectedStatus, "{$routeName}/{$scenario}: HTTP {$result['status']} in plaats van {$expectedStatus}.");
    assertSimpleBatch2($result['body'] === json_encode($expectedBody), "{$routeName}/{$scenario}: onverwachte body {$result['body']}.");
    assertSimpleBatch2($result['mutations'] === $expectedMutations, "{$routeName}/{$scenario}: {$result['mutations']} wijzigingen in plaats van {$expectedMutations}.");
    assertSimpleBatch2($result['exitCode'] === 0, "{$routeName}/{$scenario}: exit {$result['exitCode']}; {$result['stderr']}");
    return $result;
}

try {
    $skillAdded = expectSimpleBatch2(
        $fixturePaths,
        'AddNewSkill',
        'success',
        ['idCharacter' => 1, 'idSkill' => 5],
        200,
        ['id' => 5, 'name' => 'academicus', 'visibility' => 'public'],
        1
    );
    assertSimpleBatch2($skillAdded['lastWriteParameters'] === [
        'idCharacter' => 1, 'idSkill' => 5, 'level' => 0,
    ], 'AddNewSkill gebruikt niet de gevalideerde prepared parameters en schema-default.');
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'director', ['idCharacter' => 2, 'idSkill' => 6, 'level' => 2], 200, ['id' => 6, 'name' => 'verborgen kunst', 'visibility' => 'secret'], 1);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'skill_unique_conflict', ['idCharacter' => 1, 'idSkill' => 5], 200, ['id' => 5, 'name' => 'academicus', 'visibility' => 'public'], 1);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'forged_role', ['idCharacter' => 2, 'idSkill' => 5], 403, ['error' => 'Je hebt geen rechten voor dit personage.']);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'secret_skill', ['idCharacter' => 1, 'idSkill' => 6], 403, ['error' => 'Je hebt geen rechten om deze vaardigheid te beheren.']);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'invalid_csrf', ['idCharacter' => 1, 'idSkill' => 5], 403, ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'unauthenticated', ['idCharacter' => 1, 'idSkill' => 5], 401, ['error' => 'Not authenticated']);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'invalid_level', ['idCharacter' => 1, 'idSkill' => 5, 'level' => 4], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld level is groter dan toegestaan.'],
    ]);
    expectSimpleBatch2($fixturePaths, 'AddNewSkill', 'unknown_field', ['idCharacter' => 1, 'idSkill' => 5, 'role' => 'director'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: role.'],
    ]);

    expectSimpleBatch2($fixturePaths, 'getSkillSpecialisations', 'success', ['idCharacter' => 1, 'idSkill' => 5], 200, [
        'options' => [['id' => 302, 'name' => 'Onderzoek']],
    ]);
    expectSimpleBatch2($fixturePaths, 'getSkillSpecialisations', 'director', ['idCharacter' => 2, 'idSkill' => 6], 200, [
        'options' => [['id' => 302, 'name' => 'Onderzoek']],
    ]);
    expectSimpleBatch2($fixturePaths, 'getSkillSpecialisations', 'forged_role', ['idCharacter' => 2, 'idSkill' => 5], 403, ['error' => 'Je hebt geen rechten voor dit personage.']);
    expectSimpleBatch2($fixturePaths, 'getSkillSpecialisations', 'secret_skill', ['idCharacter' => 1, 'idSkill' => 6], 403, ['error' => 'Je hebt geen rechten om deze vaardigheid te beheren.']);
    expectSimpleBatch2($fixturePaths, 'getSkillSpecialisations', 'unauthenticated', ['idCharacter' => 1, 'idSkill' => 5], 401, ['error' => 'Not authenticated']);
    expectSimpleBatch2($fixturePaths, 'getSkillSpecialisations', 'unknown_field', ['idCharacter' => 1, 'idSkill' => 5, 'idUser' => 10], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: idUser.'],
    ]);

    expectSimpleBatch2($fixturePaths, 'deleteSkillSpecialisation', 'success', [
        'idCharacter' => 1, 'idSkill' => 5, 'idSkillSpecialisation' => 301,
    ], 200, [
        'success' => true,
        'specialisations' => [[
            'idCharSpec' => 902, 'idSkillSpecialisation' => 302,
            'name' => 'Onderzoek', 'kind' => 'specialisation',
        ]],
    ], 1);
    expectSimpleBatch2($fixturePaths, 'deleteSkillSpecialisation', 'director', [
        'idCharacter' => 2, 'idSkill' => 6, 'idSkillSpecialisation' => 301,
    ], 200, [
        'success' => true,
        'specialisations' => [[
            'idCharSpec' => 902, 'idSkillSpecialisation' => 302,
            'name' => 'Onderzoek', 'kind' => 'specialisation',
        ]],
    ], 1);
    expectSimpleBatch2($fixturePaths, 'deleteSkillSpecialisation', 'forged_role', [
        'idCharacter' => 2, 'idSkill' => 5, 'idSkillSpecialisation' => 301,
    ], 403, ['error' => 'Je hebt geen rechten voor dit personage.']);
    expectSimpleBatch2($fixturePaths, 'deleteSkillSpecialisation', 'invalid_csrf', [
        'idCharacter' => 1, 'idSkill' => 5, 'idSkillSpecialisation' => 301,
    ], 403, ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']);
    expectSimpleBatch2($fixturePaths, 'deleteSkillSpecialisation', 'unknown_field', [
        'idCharacter' => 1, 'idSkill' => 5, 'idSkillSpecialisation' => 301, 'owner' => 10,
    ], 422, ['error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: owner.']]);

    expectSimpleBatch2($fixturePaths, 'getCharacterLanguageOptions', 'success', ['idCharacter' => 1], 200, [
        'options' => [['id' => 41, 'name' => 'Frans'], ['id' => 42, 'name' => 'Nederlands']],
    ]);
    expectSimpleBatch2($fixturePaths, 'getCharacterLanguageOptions', 'lower_class', ['idCharacter' => 3], 200, ['options' => []]);
    expectSimpleBatch2($fixturePaths, 'getCharacterLanguageOptions', 'director', ['idCharacter' => 2], 200, [
        'options' => [['id' => 41, 'name' => 'Frans'], ['id' => 42, 'name' => 'Nederlands']],
    ]);
    expectSimpleBatch2($fixturePaths, 'getCharacterLanguageOptions', 'forged_role', ['idCharacter' => 2], 403, ['error' => 'Geen rechten om talen te beheren.']);
    expectSimpleBatch2($fixturePaths, 'getCharacterLanguageOptions', 'unauthenticated', ['idCharacter' => 1], 401, ['error' => 'Not authenticated']);
    expectSimpleBatch2($fixturePaths, 'getCharacterLanguageOptions', 'unknown_field', ['idCharacter' => 1, 'role' => 'director'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: role.'],
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

echo "Simple character endpoint batch 2 tests passed.\n";
