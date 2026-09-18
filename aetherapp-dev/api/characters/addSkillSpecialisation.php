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
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('addSkillSpecialisation', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}
try {
    aetherRequireCharacterAccess($pdo, $currentUser, $input['idCharacter'], 'edit');
    aetherRequireSkillAccess($pdo, $currentUser, $input['idSkill']);
    aetherJsonResponse(aetherAddCharacterSkillSpecialisation(
        $pdo,
        $currentUser,
        $input['idCharacter'],
        $input['idSkill'],
        (int) ($input['idSkillSpecialisation'] ?? 0),
        (string) ($input['name'] ?? ''),
        isset($input['kind']) ? (string) $input['kind'] : null
    ));
} catch (AetherCharacterSkillException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('addSkillSpecialisation.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon specialisatie niet opslaan.');
}
