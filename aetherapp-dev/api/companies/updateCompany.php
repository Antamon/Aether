<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/../characters/companyShareUtils.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/companyFinanceSchemas.php';

final class AetherCompanyUpdateException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

try {
    $pdo = getPDO();
    $currentUser = requirePrivilegedCompanyAccess($pdo, true);
    $postData = aetherReadJsonObject();
    $input = aetherValidateInput($postData, aetherCompanyUpdateSchema());
    $id = (int) $input['id'];
    unset($input['id']);
    if ($input === []) {
        throw new AetherCompanyUpdateException(400, 'Geen velden om bij te werken.');
    }
    $requestKey = aetherRequireIdempotencyKey();
    $pdo->beginTransaction();
    $claim = aetherClaimIdempotency($pdo, $currentUser, 'company.update', $requestKey, $input + ['id' => $id]);
    $currentCompany = dbOne(
        $pdo,
        'SELECT id, companyValue
           FROM tblCompany
          WHERE id = :id
          FOR UPDATE',
        ['id' => $id]
    );

    if ($currentCompany === null) {
        throw new AetherCompanyUpdateException(404, 'Bedrijf niet gevonden.');
    }
    if ($claim['replayed']) {
        $pdo->commit();
        aetherJsonResponse($claim['response']);
    }

    $allowedFields = ['companyName', 'description', 'foundationDate', 'companyValue', 'stability', 'profitability'];
    $updateData = [];

    foreach ($allowedFields as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }

        $value = $input[$field];

        switch ($field) {
            case 'companyName':
                break;

            case 'description':
                $value = trim((string) $value);
                break;

            case 'foundationDate':
                if ($value === '') {
                    $value = null;
                }
                break;

            case 'companyValue':
                break;

            case 'stability':
            case 'profitability':
                $value = normalizeCompanySliderValue($value);
                break;
        }

        $updateData[$field] = $value;
    }

    $setParts = [];
    foreach (array_keys($updateData) as $column) {
        $setParts[] = "$column = :$column";
    }
    $setParts[] = 'updatedAt = CURRENT_TIMESTAMP';

    $sql = 'UPDATE tblCompany
               SET ' . implode(', ', $setParts) . '
             WHERE id = :id';

    $updateData['id'] = $id;

    $previousCompanyTypeKey = getCompanyTypeByValue($currentCompany['companyValue'] ?? 0)['key'] ?? null;
    $nextCompanyValue = array_key_exists('companyValue', $updateData)
        ? $updateData['companyValue']
        : ($currentCompany['companyValue'] ?? 0);
    $nextCompanyTypeKey = getCompanyTypeByValue($nextCompanyValue)['key'] ?? null;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateData);

    $updatedShareTraitCount = 0;
    if (
        array_key_exists('companyValue', $updateData)
        && $previousCompanyTypeKey !== null
        && $nextCompanyTypeKey !== null
        && $previousCompanyTypeKey !== $nextCompanyTypeKey
    ) {
        $updatedShareTraitCount = remapCompanyShareTraitsForCompany($pdo, $id, $nextCompanyTypeKey);
    }

    $response = [
        'status' => 'ok',
        'updatedShareTraitCount' => $updatedShareTraitCount,
    ];
    aetherCompleteIdempotency($pdo, $currentUser, 'company.update', $requestKey, $response);
    $pdo->commit();
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCompanyUpdateException|AetherIdempotencyException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('updateCompany failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijf niet bewaren.');
}
