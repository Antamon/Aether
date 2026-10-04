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
    aetherValidateAdminRequest('getSkillList', $_GET);
    aetherJsonResponse(aetherAdminSkillCatalog($pdo));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getSkillList: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de vaardighedenlijst niet laden.');
}
