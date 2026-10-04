<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

final class AetherIdempotencyException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

function aetherRequireIdempotencyKey(): string
{
    $key = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,127}$/D', $key) !== 1) {
        throw new AetherIdempotencyException(
            400,
            'Deze actie mist een geldige request-ID. Vernieuw de pagina en probeer opnieuw.'
        );
    }
    return $key;
}

function aetherCanonicalizePayloadValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('aetherCanonicalizePayloadValue', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = aetherCanonicalizePayloadValue($item);
    }
    return $value;
}

/** @param array<string, mixed> $payload */
function aetherIdempotencyPayloadHash(array $payload): string
{
    $json = json_encode(
        aetherCanonicalizePayloadValue($payload),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
    return hash('sha256', $json);
}

/**
 * Execute and persist a mutation and its response in one transaction.
 * The callback must not start, commit, or roll back a PDO transaction.
 *
 * @param array<string, mixed> $payload
 * @param callable():mixed $mutation
 * @param null|callable():void $authorizeReplay Rechecks current route/object access before returning a stored response.
 */
function aetherRunIdempotentMutation(
    PDO $pdo,
    array $currentUser,
    string $operation,
    string $requestKey,
    array $payload,
    callable $mutation,
    ?callable $authorizeReplay = null
): mixed {
    if ($pdo->inTransaction()) {
        throw new LogicException('De idempotentielaag moet de buitenste transactie beheren.');
    }

    if ((int) ($currentUser['id'] ?? 0) <= 0 || $operation === '') {
        throw new LogicException('Ongeldige vertrouwde idempotentiecontext.');
    }

    $pdo->beginTransaction();
    try {
        $claim = aetherClaimIdempotency($pdo, $currentUser, $operation, $requestKey, $payload);
        if ($claim['replayed']) {
            if ($authorizeReplay !== null) {
                $authorizeReplay();
            }
            $pdo->commit();
            return $claim['response'];
        }

        $response = $mutation();
        aetherCompleteIdempotency($pdo, $currentUser, $operation, $requestKey, $response);
        $pdo->commit();
        return $response;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * @param array<string, mixed> $payload
 * @return array{replayed: bool, response: mixed}
 */
function aetherClaimIdempotency(
    PDO $pdo,
    array $currentUser,
    string $operation,
    string $requestKey,
    array $payload
): array {
    if (!$pdo->inTransaction()) throw new LogicException('Idempotentieclaim vereist een actieve transactie.');
    $userId = (int) ($currentUser['id'] ?? 0);
    $payloadHash = aetherIdempotencyPayloadHash($payload);
    $insert = $pdo->prepare(
        "INSERT INTO tblApiIdempotency
            (idUser, operation, requestKey, payloadHash, status, responseStatus, responseJson, createdAt, updatedAt, expiresAt)
         VALUES
            (:idUser, :operation, :requestKey, :payloadHash, 'processing', NULL, NULL, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 7 YEAR))
         ON DUPLICATE KEY UPDATE id = id"
    );
    $insert->execute([
        'idUser' => $userId, 'operation' => $operation,
        'requestKey' => $requestKey, 'payloadHash' => $payloadHash,
    ]);
    $select = $pdo->prepare(
        'SELECT payloadHash, status, responseStatus, responseJson
           FROM tblApiIdempotency
          WHERE idUser = :idUser AND operation = :operation AND requestKey = :requestKey
          FOR UPDATE'
    );
    $select->execute(['idUser' => $userId, 'operation' => $operation, 'requestKey' => $requestKey]);
    $record = $select->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new RuntimeException('Idempotentieregistratie ontbreekt na insert.');
    if (!hash_equals((string) $record['payloadHash'], $payloadHash)) {
        throw new AetherIdempotencyException(409, 'Deze request-ID werd al voor andere gegevens gebruikt.');
    }
    if ((string) $record['status'] === 'completed') {
        return [
            'replayed' => true,
            'response' => json_decode((string) $record['responseJson'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }
    return ['replayed' => false, 'response' => null];
}

function aetherCompleteIdempotency(
    PDO $pdo,
    array $currentUser,
    string $operation,
    string $requestKey,
    mixed $response
): void {
    if (!$pdo->inTransaction()) throw new LogicException('Idempotentieafronding vereist een actieve transactie.');
    $stmt = $pdo->prepare(
        "UPDATE tblApiIdempotency
            SET status = 'completed', responseStatus = 200, responseJson = :responseJson, updatedAt = NOW()
          WHERE idUser = :idUser AND operation = :operation AND requestKey = :requestKey"
    );
    $stmt->execute([
        'responseJson' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'idUser' => (int) $currentUser['id'], 'operation' => $operation, 'requestKey' => $requestKey,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Idempotentieresponse kon niet worden opgeslagen.');
    }
}
