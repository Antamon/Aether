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
    $data = aetherValidateAdminRequest('getSkill', aetherReadJsonObject());
    $skill = fetchAdminSkillDetail($pdo, $data['idSkill']);
    if ($skill === null) aetherJsonError(404, 'Vaardigheid niet gevonden.');
    aetherJsonResponse($skill);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getSkill: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de vaardigheid niet laden.');
}
