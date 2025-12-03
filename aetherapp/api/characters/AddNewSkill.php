<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$idCharacter = isset($postData['idCharacter']) ? (int) $postData['idCharacter'] : 0;
$idSkill = isset($postData['idSkill']) ? (int) $postData['idSkill'] : 0;
$level = isset($postData['level']) ? (int) $postData['level'] : 0;

if ($idCharacter <= 0 || $idSkill <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige character- of skill-ID.']);
    exit;
}

try {
    // 1. Link toevoegen in tblLinkCharacterSkill
    $sql = 'INSERT INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
            VALUES (:idCharacter, :idSkill, :level)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'idCharacter' => $idCharacter,
        'idSkill' => $idSkill,
        'level' => $level,
    ]);

    // 2. Skill ophalen
    $sqlSkill = 'SELECT * FROM tblSkill WHERE id = :idSkill';
    $skill = dbOne($pdo, $sqlSkill, ['idSkill' => $idSkill]);

    echo json_encode($skill);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon vaardigheid niet toevoegen.']);
}
