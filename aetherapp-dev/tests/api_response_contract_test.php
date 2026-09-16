<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

final class ApiResponseContractStatement extends PDOStatement
{
    public function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return false;
    }
}

final class ApiResponseContractPdo extends PDO
{
    public function __construct(private readonly bool $failOnPrepare = false)
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failOnPrepare) {
            throw new RuntimeException('SQLSTATE internal database detail from tblUser');
        }

        return new ApiResponseContractStatement();
    }
}

if (($argv[1] ?? '') === '--fixture') {
    $scenario = (string) ($argv[2] ?? '');

    register_shutdown_function(static function (): void {
        $status = http_response_code();
        fwrite(STDERR, "__AETHER_HTTP_STATUS__:" . ($status === false ? '200' : (string) $status) . "\n");
    });

    switch ($scenario) {
        case 'success':
            require_once $projectRoot . '/api/shared/response.php';
            require_once $projectRoot . '/api/auth/accessControl.php';
            require_once $projectRoot . '/api/shared/response.php';
            aetherJsonResponse(['success' => true, 'id' => 17]);

        case 'bad_request':
            require_once $projectRoot . '/api/shared/response.php';
            aetherJsonError(400, 'Ongeldige parameters.');

        case 'unauthenticated':
            require_once $projectRoot . '/api/auth/accessControl.php';
            aetherStartSession();
            $_SESSION = ['user' => ['id' => 999]];
            aetherRequireAuthenticatedUser(new ApiResponseContractPdo());

        case 'forbidden_csrf':
            require_once $projectRoot . '/api/auth/accessControl.php';
            aetherStartSession();
            $_SESSION = ['aetherCsrfToken' => 'expected-token'];
            $_SERVER['HTTP_X_CSRF_TOKEN'] = 'forged-token';
            aetherRequireCsrfToken();

        case 'validation_error':
            require_once $projectRoot . '/api/characters/characterRequestValidation.php';
            aetherCharacterValidationFailure(['Veld firstName is verplicht.']);

        case 'server_error':
            require_once $projectRoot . '/api/auth/accessControl.php';
            aetherStartSession();
            $_SESSION = ['user' => ['id' => 42]];
            aetherRequireAuthenticatedUser(new ApiResponseContractPdo(true));

        default:
            fwrite(STDERR, "Onbekend contracttestscenario: {$scenario}\n");
            exit(64);
    }
}

$failures = [];

function assertApiResponseContract(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * @return array{body: string, stderr: string, status: int, exitCode: int}
 */
function runApiResponseScenario(string $scenario): array
{
    $command = [PHP_BINARY, __FILE__, '--fixture', $scenario];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException("Kon scenario {$scenario} niet starten.");
    }

    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!preg_match('/__AETHER_HTTP_STATUS__:(\d+)/', $stderr, $matches)) {
        throw new RuntimeException("Scenario {$scenario} rapporteerde geen HTTP-status. STDERR: {$stderr}");
    }

    return [
        'body' => $body,
        'stderr' => $stderr,
        'status' => (int) $matches[1],
        'exitCode' => $exitCode,
    ];
}

/** @param array<string, mixed> $expectedBody */
function assertApiResponseScenario(
    string $scenario,
    int $expectedStatus,
    array $expectedBody,
    int $expectedJsonFlags = 0
): array {
    $result = runApiResponseScenario($scenario);
    assertApiResponseContract(
        $result['status'] === $expectedStatus,
        "{$scenario}: verwacht HTTP {$expectedStatus}, kreeg {$result['status']}."
    );
    assertApiResponseContract(
        $result['exitCode'] === 0,
        "{$scenario}: verwacht exitcode 0, kreeg {$result['exitCode']}. STDERR: {$result['stderr']}"
    );
    assertApiResponseContract(
        $result['body'] === json_encode($expectedBody, $expectedJsonFlags),
        "{$scenario}: responsebody wijkt af. Ontvangen: {$result['body']}"
    );

    return $result;
}

require_once $projectRoot . '/api/shared/response.php';
require_once $projectRoot . '/api/auth/accessControl.php';
require_once $projectRoot . '/api/shared/response.php';

$errorFunction = new ReflectionFunction('aetherJsonError');
$errorParameters = $errorFunction->getParameters();
assertApiResponseContract(count($errorParameters) === 2, 'aetherJsonError moet exact twee parameters behouden.');
assertApiResponseContract(
    ($errorParameters[0]->getName() ?? '') === 'status'
        && (string) $errorParameters[0]->getType() === 'int'
        && ($errorParameters[1]->getName() ?? '') === 'message'
        && (string) $errorParameters[1]->getType() === 'string',
    'aetherJsonError moet de bestaande parameterorde (int $status, string $message) behouden.'
);

assertApiResponseScenario('success', 200, ['success' => true, 'id' => 17]);
assertApiResponseScenario('bad_request', 400, ['error' => 'Ongeldige parameters.']);
assertApiResponseScenario('unauthenticated', 401, ['error' => 'Not authenticated']);
assertApiResponseScenario(
    'forbidden_csrf',
    403,
    ['error' => 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.']
);
assertApiResponseScenario(
    'validation_error',
    422,
    ['error' => 'Ongeldige invoer.', 'validationErrors' => ['Veld firstName is verplicht.']],
    JSON_UNESCAPED_UNICODE
);
$serverError = assertApiResponseScenario(
    'server_error',
    500,
    ['error' => 'Server error while checking access.']
);
foreach (['SQLSTATE', 'PDOException', 'tblUser', 'no such table'] as $internalDetail) {
    assertApiResponseContract(
        !str_contains($serverError['body'], $internalDetail),
        "500-response lekt intern detail: {$internalDetail}"
    );
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "API response contract tests passed.\n";
