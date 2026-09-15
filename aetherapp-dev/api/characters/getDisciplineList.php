<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB-verbinding mislukt.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idSkill = isset($input['idSkill']) ? (int) $input['idSkill'] : 0;
$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;

if ($idSkill <= 0 || $idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    // Alle disciplines voor deze skill die de speler NOG NIET heeft
    $sql = "
        SELECT ss.id, ss.name
        FROM tblSkillSpecialisation ss
        LEFT JOIN tblCharacterSpecialisation cs
            ON cs.idSkillSpecialisation = ss.id
           AND cs.idCharacter = :idCharacter
        WHERE ss.idSkill = :idSkill
          AND ss.kind = 'discipline'
          AND cs.idSkillSpecialisation IS NULL
        ORDER BY ss.name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':idSkill' => $idSkill,
        ':idCharacter' => $idCharacter
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'options' => array_map(function ($r) {
            return [
                'id' => (int) $r['id'],
                'name' => $r['name']
            ];
        }, $rows)
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}