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

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('getNewSkills', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$characterId = $input['id'];

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $characterId, 'edit');

    $sql = '
        SELECT id, name
          FROM tblSkill
         WHERE id NOT IN (
                   SELECT idSkill
                     FROM tblLinkCharacterSkill
                    WHERE idCharacter = :idCharacter
               )
    ';
    if (!aetherIsPrivilegedRole($currentUser['role'])) {
        $sql .= " AND visibility = 'public'";
    }
    $sql .= ' ORDER BY name';

    aetherJsonResponse(dbAll($pdo, $sql, ['idCharacter' => $characterId]));
} catch (Throwable $e) {
    aetherJsonError(500, 'Server error while loading new skills.');
}
