<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterSkillRepository.php';
require_once __DIR__ . '/characterSkillService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('updateSkill', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}
try {
    aetherRequireCharacterAccess($pdo, $currentUser, $input['idCharacter'], 'edit');
    aetherRequireSkillAccess($pdo, $currentUser, $input['idSkill']);
    aetherJsonResponse(aetherUpdateCharacterSkill(
        $pdo,
        $input['idCharacter'],
        $input['idSkill'],
        $input['action']
    ));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('updateSkill.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Fout bij updaten van skill.');
}
