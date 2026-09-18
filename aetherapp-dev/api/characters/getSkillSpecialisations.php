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
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('getSkillSpecialisations', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idSkill = $input['idSkill'];
$idCharacter = $input['idCharacter'];

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $idCharacter, 'edit');
    aetherRequireSkillAccess($pdo, $currentUser, $idSkill);

    $stmt = $pdo->prepare(
        "SELECT id, name
           FROM tblSkillSpecialisation
          WHERE idSkill = ?
            AND kind = 'specialisation'
       ORDER BY name"
    );
    $stmt->execute([$idSkill]);
    $allSpecs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT idSkillSpecialisation
           FROM tblCharacterSpecialisation
          WHERE idCharacter = ?
            AND idSkill = ?'
    );
    $stmt->execute([$idCharacter, $idSkill]);
    $takenIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'idSkillSpecialisation');

    $options = array_values(array_filter(
        $allSpecs,
        static fn(array $row): bool => !in_array($row['id'], $takenIds, true)
    ));
    aetherJsonResponse(['options' => $options]);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon specialisaties niet laden.');
}
