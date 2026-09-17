<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterRequestValidation.php';

$postData = aetherReadCharacterJsonRequest('getNewSkills');

$characterId = isset($postData['id']) ? (int) $postData['id'] : 0;
if ($characterId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldig character ID.']);
    exit;
}

try {
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCharacterAccess($pdo, $currentUser, $characterId, 'edit');
    $isAdmin = aetherIsPrivilegedRole($currentUser['role']);

    // Basis-SELECT
    $sql = '
        SELECT id, name
          FROM tblSkill
         WHERE id NOT IN (
                   SELECT idSkill
                     FROM tblLinkCharacterSkill
                    WHERE idCharacter = :idCharacter
               )
    ';

    // Gewone deelnemers: alleen public skills
    if (!$isAdmin) {
        $sql .= " AND visibility = 'public'";
    }

    $sql .= ' ORDER BY name';

    $skills = dbAll(
        $pdo,
        $sql,
        ['idCharacter' => $characterId]
    );

    echo json_encode($skills);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading new skills.']);
}
