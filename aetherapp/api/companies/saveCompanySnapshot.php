<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companySnapshotService.php';
require_once __DIR__ . '/companyFinanceSchemas.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';

try {
    $pdo = getPDO();
    $currentUser = requirePrivilegedCompanyAccess($pdo, true);
    $postData = aetherReadJsonObject();
    $input = aetherValidateInput($postData, aetherCompanySnapshotSchema($postData));
    $requestKey = aetherRequireIdempotencyKey();
    aetherJsonResponse(aetherSaveCompanySnapshot($pdo, $currentUser, $input, $requestKey));
} catch (AetherValidationException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCompanyFinanceException|AetherIdempotencyException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('saveCompanySnapshot failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de bedrijfssnapshot niet bewaren.');
}
