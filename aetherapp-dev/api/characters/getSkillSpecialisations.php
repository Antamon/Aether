<?php
declare(strict_types=1);
require_once "../../db.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $input = json_decode(file_get_contents("php://input"), true) ?? [];

    $idSkill = (int) ($input['idSkill'] ?? 0);
    $idCharacter = (int) ($input['idCharacter'] ?? 0);

    if ($idSkill <= 0 || $idCharacter <= 0) {
        throw new RuntimeException("Ongeldige parameters.");
    }

    // Alle specialisaties voor deze skill (kind = 'specialisation')
    $sqlAll = "
        SELECT id, name
        FROM tblSkillSpecialisation
        WHERE idSkill = ? AND kind = 'specialisation'
        ORDER BY name
    ";
    $stmtAll = $pdo->prepare($sqlAll);
    $stmtAll->execute([$idSkill]);
    $allSpecs = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    // Reeds genomen door dit personage
    $sqlTaken = "
        SELECT idSkillSpecialisation
        FROM tblCharacterSpecialisation
        WHERE idCharacter = ? AND idSkill = ?
    ";
    $stmtTaken = $pdo->prepare($sqlTaken);
    $stmtTaken->execute([$idCharacter, $idSkill]);
    $takenRows = $stmtTaken->fetchAll(PDO::FETCH_ASSOC);
    $takenIds = array_column($takenRows, 'idSkillSpecialisation');

    // Filter: enkel nog niet-genomen specialisaties
    $options = array_values(array_filter($allSpecs, function ($row) use ($takenIds) {
        return !in_array($row['id'], $takenIds, true);
    }));

    echo json_encode(['options' => $options]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
