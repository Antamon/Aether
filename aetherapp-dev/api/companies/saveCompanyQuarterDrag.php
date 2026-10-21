<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/companyQuarterService.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo, true);
    if (!aetherCompanyQuarterHistoryAvailable($pdo))
        aetherJsonError(503, 'Voer eerst migratie 0016 voor bedrijfskwartalen uit.');
    $input = aetherReadJsonObject();
    $id = filter_var($input['idCompany'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) aetherJsonError(422, 'Kies een bedrijf.');
    aetherJsonResponse(aetherCompanySaveQuarterEdits($pdo, $id, $input));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('saveCompanyQuarterDrag failed: ' . $error->getMessage());
    $status = (int) $error->getCode();
    if (in_array($status, [404, 409, 422], true)) aetherJsonError($status, $error->getMessage());
    aetherJsonError(500, 'Kon de grafiekbewerking niet bewaren.');
}
