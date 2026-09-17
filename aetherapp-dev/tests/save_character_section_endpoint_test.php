<?php
declare(strict_types=1);

if (defined('AETHER_SECTION_ENDPOINT_TEST_BOOTSTRAP')) {
    final class SaveCharacterSectionTestStatement extends PDOStatement
    {
        private array $parameters = [];

        public function __construct(
            private SaveCharacterSectionTestPdo $testPdo,
            private string $query
        ) {
        }

        public function execute(?array $params = null): bool
        {
            $this->parameters = $params ?? [];
            if (str_contains($this->query, 'INSERT INTO tblCharacterSection')) {
                $this->testPdo->mutations++;
                $this->testPdo->lastWriteParameters = $this->parameters;
                $key = (int) ($this->parameters[':idCharacter'] ?? 0)
                    . ':' . (string) ($this->parameters[':section'] ?? '');
                $this->testPdo->sections[$key] = (string) ($this->parameters[':content'] ?? '');
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

            return false;
        }
    }

    final class SaveCharacterSectionTestPdo extends PDO
    {
        public int $mutations = 0;
        /** @var array<string, string> */
        public array $sections = [];
        /** @var array<string, mixed> */
        public array $lastWriteParameters = [];

        /**
         * @param array<int, array<string, mixed>> $users
         * @param array<int, array<string, mixed>> $characters
         */
        public function __construct(public array $users, public array $characters)
        {
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new SaveCharacterSectionTestStatement($this, $query);
        }
    }

    $scenario = (string) ($argv[1] ?? '');
    $users = [
        10 => [
            'id' => 10,
            'username' => 'participant',
            'firstName' => 'Part',
            'lastName' => 'Icipant',
            'role' => 'participant',
        ],
    ];
    $characters = [
        1 => ['id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'upper class'],
        2 => ['id' => 2, 'idUser' => 20, 'type' => 'player', 'state' => 'active', 'class' => 'upper class'],
    ];
    $pdo = new SaveCharacterSectionTestPdo($users, $characters);

    session_start();
    $_SESSION = [
        'user' => [
            'id' => $scenario === 'unauthenticated' ? 999 : 10,
            // Bewust vervalst: aetherLoadAuthenticatedUser moet de databaserol gebruiken.
            'role' => 'administrator',
        ],
        'aetherCsrfToken' => 'expected-token',
    ];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf'
        ? 'forged-token'
        : 'expected-token';
    if ($scenario === 'form_fallback') {
        $_POST = ['idCharacter' => '1', 'section' => 'knowledge', 'content' => 'Formulierdata'];
    }

    register_shutdown_function(static function () use ($pdo): void {
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_SECTION_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'mutations' => $pdo->mutations,
            'sections' => $pdo->sections,
            'lastWriteParameters' => $pdo->lastWriteParameters,
        ]) . "\n");
    });

    return;
}

$projectRoot = dirname(__DIR__);
$routePath = $projectRoot . '/api/characters/saveCharacterSection.php';
$routeSource = file_get_contents($routePath);
if ($routeSource === false) {
    throw new RuntimeException('Kon saveCharacterSection.php niet lezen.');
}

$bootstrapStatement = "define('AETHER_SECTION_ENDPOINT_TEST_BOOTSTRAP', true);\nrequire "
    . var_export(__FILE__, true) . ';';
$fixtureSource = str_replace(
    "require __DIR__ . '/../../db.php';",
    $bootstrapStatement,
    $routeSource,
    $replacementCount
);
if ($replacementCount !== 1) {
    throw new RuntimeException('Kon de PDO-dependency voor de geïsoleerde endpointtest niet vervangen.');
}

$fixturePath = tempnam($projectRoot . '/api/characters', '.save-section-endpoint-');
if ($fixturePath === false) {
    throw new RuntimeException('Kon geen tijdelijke endpointfixture maken.');
}
file_put_contents($fixturePath, $fixtureSource);

$failures = [];

function assertSaveCharacterSection(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * @return array{body: string, stderr: string, status: int, mutations: int, sections: array<string, string>, lastWriteParameters: array<string, mixed>, exitCode: int}
 */
function runSaveCharacterSectionScenario(string $fixturePath, string $scenario, array|string $request): array
{
    $process = proc_open(
        [PHP_BINARY, $fixturePath, $scenario],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Kon endpointscenario {$scenario} niet starten.");
    }

    fwrite($pipes[0], is_string($request) ? $request : json_encode($request));
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!preg_match('/__AETHER_SECTION_STATE__:(\{.*\})/', $stderr, $matches)) {
        throw new RuntimeException("Endpointscenario {$scenario} rapporteerde geen status. STDERR: {$stderr}");
    }
    $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

    return [
        'body' => $body,
        'stderr' => $stderr,
        'status' => (int) $state['status'],
        'mutations' => (int) $state['mutations'],
        'sections' => $state['sections'],
        'lastWriteParameters' => $state['lastWriteParameters'],
        'exitCode' => $exitCode,
    ];
}

/** @param array<string, mixed> $expectedBody */
function assertSaveCharacterSectionScenario(
    string $fixturePath,
    string $scenario,
    array|string $request,
    int $expectedStatus,
    array $expectedBody,
    int $expectedMutations
): array {
    $result = runSaveCharacterSectionScenario($fixturePath, $scenario, $request);
    assertSaveCharacterSection(
        $result['status'] === $expectedStatus,
        "{$scenario}: verwacht HTTP {$expectedStatus}, kreeg {$result['status']}."
    );
    assertSaveCharacterSection(
        $result['body'] === json_encode($expectedBody, JSON_UNESCAPED_UNICODE),
        "{$scenario}: onverwachte responsebody {$result['body']}."
    );
    assertSaveCharacterSection(
        $result['mutations'] === $expectedMutations,
        "{$scenario}: verwacht {$expectedMutations} mutaties, kreeg {$result['mutations']}."
    );
    assertSaveCharacterSection(
        $result['exitCode'] === 0,
        "{$scenario}: endpoint eindigde met status {$result['exitCode']}. STDERR: {$result['stderr']}"
    );

    return $result;
}

try {
    $validRequest = ['idCharacter' => 1, 'section' => 'knowledge', 'content' => '<p>Nieuwe <strong>tekst</strong></p>'];
    $success = assertSaveCharacterSectionScenario(
        $fixturePath,
        'success',
        $validRequest,
        200,
        ['success' => true, 'content' => '<p>Nieuwe <strong>tekst</strong></p>'],
        1
    );
    assertSaveCharacterSection(
        ($success['sections']['1:knowledge'] ?? null) === '<p>Nieuwe <strong>tekst</strong></p>',
        'De toegestane update is niet met de verwachte prepared parameters uitgevoerd.'
    );
    assertSaveCharacterSection(
        $success['lastWriteParameters'] === [
            ':idCharacter' => 1,
            ':section' => 'knowledge',
            ':content' => '<p>Nieuwe <strong>tekst</strong></p>',
            ':updatedBy' => 10,
        ],
        'De upsert gebruikt niet langer exact de bestaande prepared parameters.'
    );

    assertSaveCharacterSectionScenario(
        $fixturePath,
        'unauthenticated',
        $validRequest,
        401,
        ['error' => 'Not authenticated'],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'invalid_csrf',
        $validRequest,
        403,
        ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.'],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'unknown_field',
        $validRequest + ['role' => 'administrator'],
        422,
        ['error' => 'Ongeldige invoer.', 'validationErrors' => ['Onverwacht veld: role.']],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'invalid_section',
        ['idCharacter' => 1, 'section' => 'admin_notes', 'content' => 'Onveilig'],
        422,
        ['error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld section bevat geen toegestane waarde.']],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'missing_character',
        ['section' => 'knowledge', 'content' => 'Geen ID'],
        422,
        ['error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld idCharacter is verplicht.']],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'invalid_character',
        ['idCharacter' => 0, 'section' => 'knowledge', 'content' => 'Verkeerd ID'],
        422,
        ['error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld idCharacter is kleiner dan toegestaan.']],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'other_character',
        ['idCharacter' => 2, 'section' => 'knowledge', 'content' => 'Niet toegestaan'],
        403,
        ['error' => 'Je hebt geen rechten voor dit personage.'],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'missing_object',
        ['idCharacter' => 999, 'section' => 'knowledge', 'content' => 'Onbekend'],
        404,
        ['error' => 'Personage niet gevonden.'],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'invalid_json',
        '{',
        400,
        ['error' => 'Requestbody bevat geen geldige JSON.'],
        0
    );
    assertSaveCharacterSectionScenario(
        $fixturePath,
        'form_fallback',
        '',
        400,
        ['error' => 'Requestbody bevat geen geldige JSON.'],
        0
    );

    require_once $projectRoot . '/api/characters/characterSchemas.php';
    $sectionSchema = aetherCharacterRequestSchema('saveCharacterSection');
    assertSaveCharacterSection(
        ($sectionSchema['section']['values'] ?? null) === [
            'personal_background', 'knowledge', 'nature', 'demeanour',
        ],
        'De vastgelegde lijst met toegestane secties wijkt af van het characterschema.'
    );

    foreach ([
        "require_once __DIR__ . '/../shared/request.php'",
        "require_once __DIR__ . '/../shared/validation.php'",
        "require_once __DIR__ . '/../shared/response.php'",
        "require_once __DIR__ . '/../auth/accessControl.php'",
        "require_once __DIR__ . '/characterAccess.php'",
        "require_once __DIR__ . '/characterSchemas.php'",
        'aetherReadJsonObject()',
        "aetherCharacterRequestSchema('saveCharacterSection'",
        "aetherRequireCharacterAccess(\$pdo, \$currentUser, \$idCharacter, 'edit')",
    ] as $requiredRouteFragment) {
        assertSaveCharacterSection(
            str_contains($routeSource, $requiredRouteFragment),
            "De dunne route mist: {$requiredRouteFragment}"
        );
    }
    foreach (['session_start()', 'aetherReadCharacterJsonRequest', '$allowedSections', 'http_response_code(', 'echo json_encode('] as $removedFragment) {
        assertSaveCharacterSection(
            !str_contains($routeSource, $removedFragment),
            "De route bevat nog verwijderde duplicatie: {$removedFragment}"
        );
    }

    $backgroundSource = file_get_contents($projectRoot . '/js/backgroundCharacter.js');
    $apiSource = file_get_contents($projectRoot . '/js/mainFunctions.js');
    assertSaveCharacterSection(
        is_string($backgroundSource)
            && str_contains($backgroundSource, 'body: { idCharacter, section, content }'),
        'Het frontendverzoek verstuurt niet de drie vastgelegde velden.'
    );
    assertSaveCharacterSection(
        is_string($apiSource)
            && str_contains($apiSource, 'fetchOptions.headers["Content-Type"] = "application/json"')
            && str_contains($apiSource, 'fetchOptions.body = JSON.stringify(body)'),
        'Het frontendverzoek gebruikt niet aantoonbaar JSON.'
    );
} finally {
    unlink($fixturePath);
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "saveCharacterSection endpoint tests passed.\n";
