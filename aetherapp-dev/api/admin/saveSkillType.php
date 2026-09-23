<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/adminAccess.php';
require_once __DIR__ . '/adminSchemas.php';
require_once __DIR__ . '/adminSkillService.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';
try {
    $pdo = getPDO();
    aetherRequireAdminEditor($pdo, true);
    aetherRequireCsrfToken();
    $data = aetherValidateAdminRequest('saveSkillType', aetherReadJsonObject());
    aetherJsonResponse(aetherAdminSaveSkillType($pdo, $data));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AdminSkillProblem $e) {
    aetherJsonError($e->status, $e->getMessage());
} catch (Throwable $e) {
    error_log('saveSkillType: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de categorie niet bewaren.');
}
