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
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('AddNewSkill', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idCharacter = $input['idCharacter'];
$idSkill = $input['idSkill'];
$level = $input['level'];

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $idCharacter, 'edit');
    aetherRequireSkillAccess($pdo, $currentUser, $idSkill);

    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
         VALUES (:idCharacter, :idSkill, :level)'
    );
    $stmt->execute([
        'idCharacter' => $idCharacter,
        'idSkill' => $idSkill,
        'level' => $level,
    ]);

    $skill = dbOne($pdo, 'SELECT * FROM tblSkill WHERE id = :idSkill', ['idSkill' => $idSkill]);
    aetherJsonResponse($skill);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon vaardigheid niet toevoegen.');
}
