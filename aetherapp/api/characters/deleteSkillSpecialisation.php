<?php
declare(strict_types=1);
require_once "../../db.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $input = json_decode(file_get_contents("php://input"), true) ?? [];

    $idSkill = (int) ($input['idSkill'] ?? 0);
    $idCharacter = (int) ($input['idCharacter'] ?? 0);
    $idSpec = (int) ($input['idSkillSpecialisation'] ?? 0);

    if ($idSkill <= 0 || $idCharacter <= 0 || $idSpec <= 0) {
        throw new RuntimeException("Ongeldige parameters.");
    }

    $sqlDel = "
        DELETE FROM tblCharacterSpecialisation
        WHERE idCharacter = ? AND idSkill = ? AND idSkillSpecialisation = ?
    ";
    $stmt = $pdo->prepare($sqlDel);
    $stmt->execute([$idCharacter, $idSkill, $idSpec]);

    // Nieuwe lijst voor deze skill teruggeven
    $sqlSpecs = "
        SELECT cs.id      AS idCharSpec,
               ss.id      AS idSkillSpecialisation,
               ss.name,
               ss.kind
        FROM tblCharacterSpecialisation cs
        JOIN tblSkillSpecialisation ss ON ss.id = cs.idSkillSpecialisation
        WHERE cs.idCharacter = ? AND cs.idSkill = ?
        ORDER BY ss.name
    ";
    $stmt = $pdo->prepare($sqlSpecs);
    $stmt->execute([$idCharacter, $idSkill]);
    $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'specialisations' => $specs
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
