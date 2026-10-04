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
    aetherRequireAdminEditor($pdo);
    aetherRequireCsrfToken();
    $data = aetherValidateAdminRequest('newSkill', aetherReadJsonObject());
    aetherJsonResponse(aetherAdminCreateSkill($pdo, $data['name']));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AdminSkillProblem $e) {
    aetherJsonError($e->status, $e->getMessage());
} catch (Throwable $e) {
    error_log('newSkill: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de vaardigheid niet aanmaken.');
}
