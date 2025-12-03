<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaseverbinding mislukt.']);
    exit;
}

// JSON body uitlezen
$inputJson = file_get_contents('php://input');
$input = json_decode($inputJson, true) ?? [];

$action = $input['action'] ?? null;
$idSkill = isset($input['idSkill']) ? (int) $input['idSkill'] : 0;
$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;

if (!$action || !$idSkill || !$idCharacter) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    // --- 1. Bestaande skill-link ophalen (tblLinkCharacterSkill) ---
    $stmt = $pdo->prepare(
        'SELECT level 
         FROM tblLinkCharacterSkill 
         WHERE idCharacter = ? AND idSkill = ?'
    );
    $stmt->execute([$idCharacter, $idSkill]);
    $skillRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$skillRow) {
        echo json_encode(['error' => 'Skill niet gevonden voor dit personage.']);
        exit;
    }

    $level = (int) $skillRow['level'];

    // --- 2. Is dit een discipline-skill? ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tblLinkSkillType lst
        JOIN tblSkillType st ON st.id = lst.idSkillType
        WHERE lst.idSkill = ? AND st.code = 'discipline'
    ");
    $stmt->execute([$idSkill]);
    $isDisciplineSkill = ((int) $stmt->fetchColumn() > 0);

    // --- 3. Character ophalen: type + idUser (voor EP-berekening) ---
    $stmt = $pdo->prepare(
        'SELECT type, idUser 
         FROM tblCharacter 
         WHERE id = ?'
    );
    $stmt->execute([$idCharacter]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $isPlayer = ($character['type'] === 'player');
    $idUser = (int) $character['idUser'];

    // --- 4. Max beschikbare EP alleen voor spelerspersonages ---
    $maxXP = null;
    if ($isPlayer) {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(e.ep),0) AS total
             FROM tblLinkEventUser leu
             JOIN tblEvent e ON leu.idEvent = e.id
             WHERE leu.idUser = ?'
        );
        $stmt->execute([$idUser]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalFromEvents = (int) ($row['total'] ?? 0);

        // Basis 15 EP zoals in getCharacter.php
        $maxXP = $totalFromEvents + 15;
    }

    // --- 5. Huidig gebruikte EP berekenen ---
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(
             CASE level
                 WHEN 1 THEN 1
                 WHEN 2 THEN 3
                 WHEN 3 THEN 6
             END
         ),0) AS usedXP
         FROM tblLinkCharacterSkill 
         WHERE idCharacter = ?'
    );
    $stmt->execute([$idCharacter]);
    $usedXP = (int) $stmt->fetchColumn();

    // --- 6. Actie verwerken ---
    if ($action === 'up') {
        if ($level >= 3) {
            echo json_encode(['error' => 'Maximum vaardigheidsniveau bereikt.']);
            exit;
        }

        // Kosten van deze upgrade
        $cost = ($level === 0 ? 1 : ($level === 1 ? 2 : 3));

        if ($isPlayer && $maxXP !== null && ($usedXP + $cost) > $maxXP) {
            echo json_encode(['error' => 'Onvoldoende ervaringspunten.']);
            exit;
        }

        $level++;

    } elseif ($action === 'down') {
        if ($level <= 0) {
            echo json_encode(['error' => 'Niveau is al 0.']);
            exit;
        }
        $level--;

        // Als de skill nu terug op 0 komt, discipline-link(s) loskoppelen
        if ($level === 0) {
            $stmt = $pdo->prepare("
                DELETE cs
                FROM tblCharacterSpecialisation cs
                JOIN tblSkillSpecialisation ss 
                ON ss.id = cs.idSkillSpecialisation
                WHERE cs.idCharacter = ?
                AND cs.idSkill = ?
                AND ss.kind = 'discipline'
            ");
            $stmt->execute([$idCharacter, $idSkill]);
        }

    } elseif ($action === 'delete') {
        // wordt verderop afgehandeld in de DELETE-queries

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Onbekende actie.']);
        exit;
    }

    // --- 7. Wijziging wegschrijven ---
    if ($action === 'delete') {
        // Eerst alle specialisaties van dit character + skill verwijderen
        $stmt = $pdo->prepare("
            DELETE FROM tblCharacterSpecialisation
            WHERE idCharacter = ? AND idSkill = ?
        ");
        $stmt->execute([$idCharacter, $idSkill]);

        // Daarna de skill-link zelf verwijderen
        $stmt = $pdo->prepare("
            DELETE FROM tblLinkCharacterSkill
            WHERE idCharacter = ? AND idSkill = ?
        ");
        $stmt->execute([$idCharacter, $idSkill]);

    } else {
        $stmt = $pdo->prepare(
            'UPDATE tblLinkCharacterSkill 
             SET level = ? 
             WHERE idCharacter = ? AND idSkill = ?'
        );
        $stmt->execute([$level, $idCharacter, $idSkill]);
    }

    // --- 8. Nieuwe lijst van skills voor feedback (optioneel) ---
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.description, 
                s.beginner, s.professional, s.master, 
                cs.level
         FROM tblSkill s 
         JOIN tblLinkCharacterSkill cs ON cs.idSkill = s.id
         WHERE cs.idCharacter = ?
         ORDER BY s.name'
    );
    $stmt->execute([$idCharacter]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Used XP opnieuw berekenen
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(
             CASE level
                 WHEN 1 THEN 1
                 WHEN 2 THEN 3
                 WHEN 3 THEN 6
             END
         ),0) AS usedXP
         FROM tblLinkCharacterSkill 
         WHERE idCharacter = ?'
    );
    $stmt->execute([$idCharacter]);
    $usedXP = (int) $stmt->fetchColumn();

    echo json_encode([
        'skills' => $skills,
        'usedExperience' => $usedXP,
        'maxExperience' => $maxXP
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Fout bij updaten van skill.',
        'details' => $e->getMessage()
    ]);
}