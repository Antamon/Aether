<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

if (($argv[1] ?? '') === '--json-fixture') {
    if (($argv[2] ?? '') === 'with-form-data') {
        $_POST = ['name' => 'Form fallback is niet toegestaan'];
    }
    register_shutdown_function(static function (): void {
        $status = http_response_code();
        fwrite(STDERR, "__AETHER_HTTP_STATUS__:" . ($status === false ? '200' : (string) $status) . "\n");
    });

    require_once $projectRoot . '/api/shared/request.php';
    aetherJsonResponse(aetherReadJsonObject());
}

require_once $projectRoot . '/api/shared/request.php';
require_once $projectRoot . '/api/shared/validation.php';

$failures = [];

function assertRequestValidation(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * @return array{body: string, stderr: string, status: int, exitCode: int}
 */
function runJsonRequestScenario(string $body, bool $withFormData = false): array
{
    $command = [PHP_BINARY, __FILE__, '--json-fixture'];
    if ($withFormData) {
        $command[] = 'with-form-data';
    }
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Kon JSON-requestscenario niet starten.');
    }

    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $responseBody = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (!preg_match('/__AETHER_HTTP_STATUS__:(\d+)/', $stderr, $matches)) {
        throw new RuntimeException("JSON-requestscenario rapporteerde geen HTTP-status. STDERR: {$stderr}");
    }

    return [
        'body' => $responseBody,
        'stderr' => $stderr,
        'status' => (int) $matches[1],
        'exitCode' => $exitCode,
    ];
}

/** @param array<string, mixed> $expectedBody */
function assertJsonRequestScenario(
    string $input,
    int $expectedStatus,
    array $expectedBody,
    bool $withFormData = false
): void
{
    $result = runJsonRequestScenario($input, $withFormData);
    assertRequestValidation(
        $result['status'] === $expectedStatus,
        "JSON-request: verwacht HTTP {$expectedStatus}, kreeg {$result['status']}."
    );
    assertRequestValidation(
        $result['exitCode'] === 0,
        "JSON-request: verwacht exitcode 0, kreeg {$result['exitCode']}. STDERR: {$result['stderr']}"
    );
    assertRequestValidation(
        $result['body'] === json_encode($expectedBody),
        "JSON-request: responsebody wijkt af. Ontvangen: {$result['body']}"
    );
}

/** @param callable(): void $callback */
function expectAetherValidationException(callable $callback): AetherValidationException
{
    try {
        $callback();
    } catch (AetherValidationException $e) {
        return $e;
    }

    throw new RuntimeException('Verwachte AetherValidationException bleef uit.');
}

assertJsonRequestScenario('{"name":"Ada","active":true}', 200, ['name' => 'Ada', 'active' => true]);
assertJsonRequestScenario('{}', 200, []);
assertJsonRequestScenario('', 400, ['error' => 'Requestbody bevat geen geldige JSON.']);
assertJsonRequestScenario(
    '',
    400,
    ['error' => 'Requestbody bevat geen geldige JSON.'],
    true
);
assertJsonRequestScenario('{"name":', 400, ['error' => 'Requestbody bevat geen geldige JSON.']);
assertJsonRequestScenario('[{"name":"Ada"}]', 400, ['error' => 'Requestbody moet een JSON-object zijn.']);

$_POST = ['name' => 'Ada', 'count' => '2'];
assertRequestValidation(
    aetherReadFormFields() === $_POST,
    'De formulierlezer moet de bestaande formuliervelden ongewijzigd teruggeven.'
);
$_POST = [];

$scalarSchema = [
    'name' => ['type' => 'string', 'required' => true, 'trim' => true, 'minLength' => 2, 'maxLength' => 10],
    'count' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 10],
    'amount' => ['type' => 'number', 'required' => true, 'minExclusive' => 0, 'max' => 10, 'scale' => 2],
    'active' => ['type' => 'bool', 'required' => true],
    'date' => ['type' => 'date', 'required' => true],
    'status' => ['type' => 'enum', 'required' => true, 'values' => ['draft', 'active']],
    'nullableId' => ['type' => 'nullable_int', 'required' => false, 'min' => 1],
    'nullableStatus' => ['type' => 'nullable_enum', 'required' => false, 'values' => ['one', 'two']],
    'label' => ['type' => 'string', 'required' => false, 'default' => 'fallback'],
];

$validated = aetherValidateInput([
    'name' => '  Ada  ',
    'count' => '5',
    'amount' => '2.345',
    'active' => true,
    'date' => '2026-09-16',
    'status' => 'draft',
    'nullableId' => null,
    'nullableStatus' => null,
], $scalarSchema);
assertRequestValidation($validated['name'] === 'Ada', 'String trim wordt niet toegepast.');
assertRequestValidation($validated['count'] === 5, 'Integer-string wordt niet genormaliseerd.');
assertRequestValidation($validated['amount'] === 2.35, 'Getal wordt niet gevalideerd of afgerond.');
assertRequestValidation($validated['active'] === true, 'Boolean wordt niet behouden.');
assertRequestValidation($validated['date'] === '2026-09-16', 'Geldige datum wordt niet behouden.');
assertRequestValidation($validated['status'] === 'draft', 'Geldige enum wordt niet behouden.');
assertRequestValidation($validated['nullableId'] === null, 'Nullable integer accepteert null niet.');
assertRequestValidation($validated['nullableStatus'] === null, 'Nullable enum accepteert null niet.');
assertRequestValidation($validated['label'] === 'fallback', 'Defaultwaarde wordt niet toegepast.');

$invalidSchema = [
    'requiredText' => ['type' => 'string', 'required' => true],
    'shortText' => ['type' => 'string', 'required' => true, 'minLength' => 2],
    'longText' => ['type' => 'string', 'required' => true, 'maxLength' => 5],
    'minimum' => ['type' => 'int', 'required' => true, 'min' => 1],
    'maximum' => ['type' => 'int', 'required' => true, 'max' => 10],
    'exclusiveMinimum' => ['type' => 'number', 'required' => true, 'minExclusive' => 0],
    'status' => ['type' => 'enum', 'required' => true, 'values' => ['draft', 'active']],
    'date' => ['type' => 'date', 'required' => true],
];
$multipleErrors = expectAetherValidationException(
    fn() => aetherValidateInput([
        'shortText' => 'x',
        'longText' => '123456',
        'minimum' => 0,
        'maximum' => 11,
        'exclusiveMinimum' => 0,
        'status' => 'unknown',
        'date' => '2026-02-30',
        'unexpected' => 'value',
    ], $invalidSchema)
);
$errorCodes = array_column($multipleErrors->getErrors(), 'code');
foreach ([
    'unknown_field', 'required', 'too_short', 'too_long', 'below_minimum',
    'above_maximum', 'below_exclusive_minimum', 'invalid_enum', 'invalid_date',
] as $expectedCode) {
    assertRequestValidation(
        in_array($expectedCode, $errorCodes, true),
        "Gecombineerde validatie mist foutcode {$expectedCode}."
    );
}
assertRequestValidation(
    count($multipleErrors->getErrors()) >= 9,
    'Meerdere validatiefouten worden niet in één verzoek verzameld.'
);
foreach ($multipleErrors->getErrors() as $error) {
    assertRequestValidation(
        array_keys($error) === ['field', 'code', 'message'],
        'Een generieke validatiefout heeft niet de vaste structuur field/code/message.'
    );
}

$typeErrors = expectAetherValidationException(
    fn() => aetherValidateInput([
        'text' => ['not text'],
        'integer' => true,
        'number' => 'not-a-number',
        'boolean' => 1,
    ], [
        'text' => ['type' => 'string', 'required' => true],
        'integer' => ['type' => 'int', 'required' => true],
        'number' => ['type' => 'number', 'required' => true],
        'boolean' => ['type' => 'bool', 'required' => true],
    ])
);
assertRequestValidation(
    count(array_filter($typeErrors->getErrors(), static fn(array $error): bool => $error['code'] === 'invalid_type')) === 4,
    'Ongeldige scalairtypen worden niet allemaal geweigerd.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Generic request and validation tests passed.\n";
