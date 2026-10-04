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
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('getDisciplineList', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idSkill = $input['idSkill'];
$idCharacter = $input['idCharacter'];

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $idCharacter, 'edit');
    aetherRequireSkillAccess($pdo, $currentUser, $idSkill);

    $stmt = $pdo->prepare(
        "SELECT ss.id, ss.name
           FROM tblSkillSpecialisation ss
           LEFT JOIN tblCharacterSpecialisation cs
             ON cs.idSkillSpecialisation = ss.id
            AND cs.idCharacter = :idCharacter
          WHERE ss.idSkill = :idSkill
            AND ss.kind = 'discipline'
            AND cs.idSkillSpecialisation IS NULL
       ORDER BY ss.name"
    );
    $stmt->execute([
        ':idSkill' => $idSkill,
        ':idCharacter' => $idCharacter,
    ]);

    $options = array_map(
        static fn(array $row): array => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
        ],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
    aetherJsonResponse(['options' => $options]);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon disciplines niet laden.');
}
