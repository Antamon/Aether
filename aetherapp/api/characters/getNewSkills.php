<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

// JSON-body inlezen
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$characterId = isset($postData['id']) ? (int) $postData['id'] : 0;
$role = $postData['role'] ?? 'participant';   // nieuw

if ($characterId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldig character ID.']);
    exit;
}

// Bepaal of deze request vanuit een admin/director komt
$isAdmin = in_array($role, ['administrator', 'director'], true);

try {
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