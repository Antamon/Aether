<?php
declare(strict_types=1);

if (defined('AETHER_UPDATE_CHARACTER_TEST_BOOTSTRAP')) {
    final class UpdateCharacterTestStatement extends PDOStatement
    {
        private array $parameters = [];
        private int $affectedRows = 0;

        public function __construct(
            private UpdateCharacterTestPdo $testPdo,
            private string $query
        ) {
        }

        public function execute(?array $params = null): bool
        {
            $this->parameters = $params ?? [];
            $this->affectedRows = 0;

            if (str_starts_with(trim($this->query), 'UPDATE tblCharacter SET')) {
                $this->testPdo->updateSql = preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);
                $this->testPdo->updateParameters = $this->parameters;
                $this->testPdo->writeAttempts++;
                if ($this->testPdo->scenario === 'server_error') {
                    throw new PDOException('SQLSTATE[42S02]: secret_table technical detail');
                }
                $this->testPdo->stageWrite();
                $this->affectedRows = 1;
            }
            if (str_contains($this->query, 'DELETE lct')) {
                $this->testPdo->writeAttempts++;
                $this->testPdo->stageWrite();
                $this->affectedRows = 1;
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
                $id = (int) ($this->parameters['id'] ?? $this->parameters['idCharacter'] ?? 0);
                return $this->testPdo->characters[$id] ?? false;
            }
            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            return [];
        }

        public function fetchColumn(int $column = 0): mixed
        {
            return false;
        }

        public function rowCount(): int
        {
            return $this->affectedRows;
        }
    }

    final class UpdateCharacterTestPdo extends PDO
    {
        public int $committedWrites = 0;
        public int $writeAttempts = 0;
        public string $updateSql = '';
        public array $updateParameters = [];
        private int $stagedWrites = 0;
        private bool $transactionActive = false;

        public function __construct(
            public string $scenario,
            public array $users,
            public array $characters
        ) {
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new UpdateCharacterTestStatement($this, $query);
        }

        public function beginTransaction(): bool
        {
            $this->transactionActive = true;
            $this->stagedWrites = 0;
            return true;
        }

        public function stageWrite(): void
        {
            if ($this->transactionActive) {
                $this->stagedWrites++;
                return;
            }
            $this->committedWrites++;
        }

        public function commit(): bool
        {
            $this->committedWrites += $this->stagedWrites;
            $this->stagedWrites = 0;
            $this->transactionActive = false;
            return true;
        }

        public function rollBack(): bool
        {
            $this->stagedWrites = 0;
            $this->transactionActive = false;
            return true;
        }

        public function inTransaction(): bool
        {
            return $this->transactionActive;
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

    $scenario = (string) ($argv[1] ?? 'success');
    $users = [
        10 => ['id' => 10, 'username' => 'participant', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'director', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
        30 => ['id' => 30, 'username' => 'administrator', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
    ];
    $characters = [
        1 => [
            'id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'upper class',
            'experienceToTrait' => 0, 'physicalHealth' => 0, 'mentalHealth' => 0,
            'physicalHealthFree' => 0, 'mentalHealthFree' => 0, 'securitiesaccount' => 0,
        ],
        2 => [
            'id' => 2, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'middle class',
            'experienceToTrait' => 0, 'physicalHealth' => 0, 'mentalHealth' => 0,
            'physicalHealthFree' => 0, 'mentalHealthFree' => 0, 'securitiesaccount' => 0,
        ],
        3 => [
            'id' => 3, 'idUser' => 10, 'type' => 'player', 'state' => 'draft', 'class' => 'lower class',
            'experienceToTrait' => 0, 'physicalHealth' => 0, 'mentalHealth' => 0,
            'physicalHealthFree' => 0, 'mentalHealthFree' => 0, 'securitiesaccount' => 0,
        ],
    ];
    $pdo = new UpdateCharacterTestPdo($scenario, $users, $characters);

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
    if ($scenario !== 'missing_csrf') {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf'
            ? 'forged-token'
            : 'expected-token';
    }

    register_shutdown_function(static function () use ($pdo): void {
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_UPDATE_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'committedWrites' => $pdo->committedWrites,
            'writeAttempts' => $pdo->writeAttempts,
            'updateSql' => $pdo->updateSql,
            'updateParameters' => $pdo->updateParameters,
        ]) . "\n");
    });
    return;
}

$projectRoot = dirname(__DIR__);
$fixtureRoot = sys_get_temp_dir() . '/aether-update-character-' . bin2hex(random_bytes(6));

function copyUpdateCharacterFixtureTree(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
        throw new RuntimeException("Kon fixturemap {$target} niet maken.");
    }
    $iterator = new DirectoryIterator($source);
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }
        $targetPath = $target . '/' . $item->getFilename();
        if ($item->isDir()) {
            copyUpdateCharacterFixtureTree($item->getPathname(), $targetPath);
        } elseif (!copy($item->getPathname(), $targetPath)) {
            throw new RuntimeException("Kon fixturebestand {$item->getPathname()} niet kopiÃ«ren.");
        }
    }
}

function removeUpdateCharacterFixtureTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

copyUpdateCharacterFixtureTree($projectRoot . '/api', $fixtureRoot . '/api');
copy($projectRoot . '/sessionUserBootstrap.php', $fixtureRoot . '/sessionUserBootstrap.php');
$bootstrap = "<?php\ndefine('AETHER_UPDATE_CHARACTER_TEST_BOOTSTRAP', true);\nrequire "
    . var_export(__FILE__, true) . ";\n";
file_put_contents($fixtureRoot . '/db.php', $bootstrap);
$routePath = $fixtureRoot . '/api/characters/updateCharacter.php';

$failures = [];

function assertUpdateCharacter(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function runUpdateCharacterScenario(string $routePath, string $scenario, array|string $request): array
{
    $process = proc_open(
        [PHP_BINARY, $routePath, $scenario],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Kon updateCharacter-scenario {$scenario} niet starten.");
    }
    fwrite($pipes[0], is_string($request) ? $request : json_encode($request));
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!preg_match('/__AETHER_UPDATE_STATE__:(\{.*\})/', $stderr, $matches)) {
        throw new RuntimeException("Scenario {$scenario} rapporteerde geen status. STDERR: {$stderr}");
    }
    $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    return [
        'body' => $body,
        'status' => (int) $state['status'],
        'committedWrites' => (int) $state['committedWrites'],
        'writeAttempts' => (int) $state['writeAttempts'],
        'updateSql' => (string) $state['updateSql'],
        'updateParameters' => $state['updateParameters'],
        'exitCode' => $exitCode,
        'stderr' => $stderr,
    ];
}

function expectUpdateCharacter(
    string $routePath,
    string $scenario,
    array|string $request,
    int $expectedStatus,
    mixed $expectedBody,
    int $expectedWrites = 0
): array {
    $result = runUpdateCharacterScenario($routePath, $scenario, $request);
    assertUpdateCharacter($result['status'] === $expectedStatus, "{$scenario}: HTTP {$result['status']} in plaats van {$expectedStatus}.");
    assertUpdateCharacter($result['body'] === json_encode($expectedBody), "{$scenario}: onverwachte body {$result['body']}.");
    assertUpdateCharacter($result['committedWrites'] === $expectedWrites, "{$scenario}: {$result['committedWrites']} writes in plaats van {$expectedWrites}.");
    assertUpdateCharacter($result['exitCode'] === 0, "{$scenario}: exit {$result['exitCode']}; {$result['stderr']}");
    return $result;
}

try {
    $success = expectUpdateCharacter($routePath, 'success', ['id' => 1, 'firstName' => '  Nieuwe naam  '], 200, 1, 1);
    assertUpdateCharacter(
        $success['updateSql'] === 'UPDATE tblCharacter SET `firstName` = :firstName WHERE id = :id',
        'De update gebruikt niet de verwachte vaste SQL-kolommapping.'
    );
    assertUpdateCharacter(
        $success['updateParameters'] === ['firstName' => 'Nieuwe naam', 'id' => 1],
        'De update gebruikt niet de exact gevalideerde prepared parameters.'
    );

    expectUpdateCharacter($routePath, 'director', ['id' => 2, 'idUser' => 10], 200, 1, 1);
    expectUpdateCharacter($routePath, 'administrator', ['id' => 2, 'type' => 'extra'], 200, 1, 1);
    expectUpdateCharacter($routePath, 'forged_role', ['id' => 2, 'firstName' => 'Verboden'], 403, ['error' => 'Je hebt geen rechten om dit personage te wijzigen.']);
    expectUpdateCharacter($routePath, 'other_character', ['id' => 2, 'firstName' => 'Verboden'], 403, ['error' => 'Je hebt geen rechten om dit personage te wijzigen.']);
    expectUpdateCharacter($routePath, 'unauthenticated', ['id' => 1, 'firstName' => 'Verboden'], 401, ['error' => 'Not authenticated']);
    expectUpdateCharacter($routePath, 'invalid_csrf', ['id' => 1, 'firstName' => 'Verboden'], 403, ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']);
    expectUpdateCharacter($routePath, 'missing_csrf', ['id' => 1, 'firstName' => 'Verboden'], 403, ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']);

    expectUpdateCharacter($routePath, 'invalid_json', '{', 400, ['error' => 'Requestbody bevat geen geldige JSON.']);
    expectUpdateCharacter($routePath, 'missing_id', ['firstName' => 'Naam'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld id is verplicht.'],
    ]);
    expectUpdateCharacter($routePath, 'invalid_id', ['id' => 0, 'firstName' => 'Naam'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld id is kleiner dan toegestaan.'],
    ]);
    expectUpdateCharacter($routePath, 'unknown_character', ['id' => 999, 'firstName' => 'Naam'], 404, ['error' => 'Personage niet gevonden.']);
    expectUpdateCharacter($routePath, 'no_fields', ['id' => 1], 400, ['error' => 'Geen velden om bij te werken.']);

    expectUpdateCharacter($routePath, 'unknown_field', ['id' => 1, 'unknownField' => 'waarde'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: unknownField.'],
    ]);
    expectUpdateCharacter($routePath, 'column_injection', ['id' => 1, "firstName` = 'gehackt' WHERE 1=1 --" => 'waarde'], 422, [
        'error' => 'Ongeldige invoer.',
        'validationErrors' => ["Onverwacht veld: firstName` = 'gehackt' WHERE 1=1 --."],
    ]);
    expectUpdateCharacter($routePath, 'invalid_type', ['id' => 1, 'firstName' => ['Naam']], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld firstName moet tekst zijn.'],
    ]);
    expectUpdateCharacter($routePath, 'too_long', ['id' => 1, 'firstName' => str_repeat('a', 41)], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld firstName is te lang (maximaal 40 tekens).'],
    ]);
    expectUpdateCharacter($routePath, 'invalid_enum', ['id' => 1, 'type' => 'god'], 422, [
        'error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld type bevat geen toegestane waarde.'],
    ]);

    foreach (['idUser' => 99, 'type' => 'extra', 'state' => 'inactive'] as $field => $value) {
        expectUpdateCharacter($routePath, "authority_{$field}", ['id' => 1, $field => $value], 403, [
            'error' => 'Je hebt geen rechten om eigenaar, type of status van dit personage te wijzigen.',
        ]);
    }
    foreach (['createdBy' => 20, 'createdAt' => '2026-09-18 12:00:00'] as $field => $value) {
        expectUpdateCharacter($routePath, "audit_{$field}", ['id' => 1, $field => $value], 403, [
            'error' => 'Auditvelden kunnen niet via deze API worden gewijzigd.',
        ]);
    }
    expectUpdateCharacter($routePath, 'participant_class_active', ['id' => 1, 'class' => 'middle class'], 403, [
        'error' => 'Je hebt geen rechten om de klasse van dit personage aan te passen.',
    ]);
    expectUpdateCharacter($routePath, 'participant_bank', ['id' => 1, 'bankaccount' => 100], 403, [
        'error' => 'Je hebt geen rechten om de bankrekening van dit personage aan te passen.',
    ]);

    $draftClass = expectUpdateCharacter($routePath, 'draft_class', ['id' => 3, 'class' => 'middle class'], 200, 1, 2);
    assertUpdateCharacter(
        $draftClass['updateParameters'] === ['class' => 'middle class', 'id' => 3],
        'De toegestane draft-klassewijziging gebruikt niet de verwachte parameters.'
    );

    $serverError = expectUpdateCharacter($routePath, 'server_error', ['id' => 1, 'lastName' => 'Fout'], 500, [
        'error' => 'Kon character niet bijwerken.',
    ]);
    assertUpdateCharacter(
        !str_contains($serverError['body'], 'SQLSTATE')
            && !str_contains($serverError['body'], 'secret_table'),
        'De HTTP 500-response lekt technische databasedetails.'
    );

    $routeSource = file_get_contents($projectRoot . '/api/characters/updateCharacter.php');
    assertUpdateCharacter(is_string($routeSource) && substr_count($routeSource, "\n") < 85, 'updateCharacter.php is niet dun genoeg.');
    foreach ([
        "require_once __DIR__ . '/../shared/request.php'",
        "require_once __DIR__ . '/../shared/response.php'",
        "require_once __DIR__ . '/../shared/validation.php'",
        "require_once __DIR__ . '/characterAccess.php'",
        "require_once __DIR__ . '/characterSchemas.php'",
        "require_once __DIR__ . '/characterRepository.php'",
        "require_once __DIR__ . '/characterService.php'",
        'aetherReadJsonObject()',
        "aetherCharacterRequestSchema('updateCharacter'",
        'aetherValidateInput(',
        'aetherCanEditCharacter(',
    ] as $fragment) {
        assertUpdateCharacter(str_contains((string) $routeSource, $fragment), "De dunne route mist {$fragment}.");
    }
    foreach (['aetherReadCharacterJsonRequest', 'session_start()', 'echo json_encode(', '$columnMap = ['] as $fragment) {
        assertUpdateCharacter(!str_contains((string) $routeSource, $fragment), "De route bevat nog oude afhandeling: {$fragment}.");
    }

    $apiSource = file_get_contents($projectRoot . '/js/apiCharacter.js');
    $fetchSource = file_get_contents($projectRoot . '/js/mainFunctions.js');
    assertUpdateCharacter(
        is_string($apiSource)
            && str_contains($apiSource, 'api/characters/updateCharacter.php')
            && str_contains($apiSource, 'method: "POST"')
            && str_contains($apiSource, 'body: payload'),
        'Het actieve frontendcontract voor updateCharacter is niet meer aantoonbaar POST met JSON-payload.'
    );
    assertUpdateCharacter(
        is_string($fetchSource)
            && str_contains($fetchSource, 'fetchOptions.headers["Content-Type"] = "application/json"')
            && str_contains($fetchSource, 'fetchOptions.body = JSON.stringify(body)'),
        'De centrale frontendhelper verstuurt de updatepayload niet als JSON.'
    );
} finally {
    removeUpdateCharacterFixtureTree($fixtureRoot);
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "updateCharacter endpoint tests passed.\n";
