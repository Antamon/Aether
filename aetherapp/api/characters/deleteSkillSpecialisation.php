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
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('deleteSkillSpecialisation', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idSkill = $input['idSkill'];
$idCharacter = $input['idCharacter'];
$idSpec = $input['idSkillSpecialisation'];

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $idCharacter, 'edit');
    aetherRequireSkillAccess($pdo, $currentUser, $idSkill);

    $stmt = $pdo->prepare(
        'DELETE FROM tblCharacterSpecialisation
          WHERE idCharacter = ?
            AND idSkill = ?
            AND idSkillSpecialisation = ?'
    );
    $stmt->execute([$idCharacter, $idSkill, $idSpec]);

    $stmt = $pdo->prepare(
        'SELECT cs.id AS idCharSpec,
                ss.id AS idSkillSpecialisation,
                ss.name,
                ss.kind
           FROM tblCharacterSpecialisation cs
           JOIN tblSkillSpecialisation ss ON ss.id = cs.idSkillSpecialisation
          WHERE cs.idCharacter = ?
            AND cs.idSkill = ?
       ORDER BY ss.name'
    );
    $stmt->execute([$idCharacter, $idSkill]);

    aetherJsonResponse([
        'success' => true,
        'specialisations' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon specialisatie niet verwijderen.');
}
