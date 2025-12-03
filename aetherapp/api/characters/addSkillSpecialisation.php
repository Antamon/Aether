<?php
declare(strict_types=1);
require_once '../../db.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * Huidig gebruikte EP voor een personage:
 * - levels in tblLinkCharacterSkill
 * - + 2 EP per niet-discipline specialisatie
 */
function getUsedXP(PDO $pdo, int $idChar): int
{
    // 1) Skills
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE level
                WHEN 1 THEN 1
                WHEN 2 THEN 3
                WHEN 3 THEN 6
            END
        ),0) AS usedXP
        FROM tblLinkCharacterSkill
        WHERE idCharacter = ?
    ");
    $stmt->execute([$idChar]);
    $usedSkillXP = (int) $stmt->fetchColumn();

    // 2) Specialisaties (geen disciplines)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tblCharacterSpecialisation cs
        JOIN tblSkillSpecialisation ss ON ss.id = cs.idSkillSpecialisation
        WHERE cs.idCharacter = ?
          AND (ss.kind IS NULL OR ss.kind <> 'discipline')
    ");
    $stmt->execute([$idChar]);
    $nonDiscCount = (int) $stmt->fetchColumn();

    return $usedSkillXP + ($nonDiscCount * 2);
}

try {
    $pdo = getPDO();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $idSkill = (int) ($input['idSkill'] ?? 0);
    $idChar = (int) ($input['idCharacter'] ?? 0);
    $idSpec = (int) ($input['idSkillSpecialisation'] ?? 0);
    $name = trim((string) ($input['name'] ?? ''));
    $kindFromClient = $input['kind'] ?? null; // 'discipline' of 'specialisation' of null

    if ($idSkill <= 0 || $idChar <= 0) {
        throw new RuntimeException("Ongeldige parameters.");
    }

    // --- Character ophalen: type + idUser ---
    $stmt = $pdo->prepare("
        SELECT type, idUser
        FROM tblCharacter
        WHERE id = ?
    ");
    $stmt->execute([$idChar]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        throw new RuntimeException("Personage niet gevonden.");
    }

    $isPlayer = ($character['type'] === 'player');
    $idUser = (int) $character['idUser'];

    // --- Max EP enkel voor spelerspersonages ---
    $maxXP = null;
    if ($isPlayer) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(e.ep),0) AS total
            FROM tblLinkEventUser leu
            JOIN tblEvent e ON leu.idEvent = e.id
            WHERE leu.idUser = ?
        ");
        $stmt->execute([$idUser]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalFromEvents = (int) ($row['total'] ?? 0);

        $maxXP = $totalFromEvents + 15; // zelfde logica als elders
    }

    // 1) Bestaande specialisatie uit dropdown
    if ($idSpec > 0) {

        // Eerst type/kind van deze specialisatie ophalen
        $stmt = $pdo->prepare("
            SELECT kind
            FROM tblSkillSpecialisation
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$idSpec]);
        $specRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$specRow) {
            throw new RuntimeException("Onbekende specialisatie.");
        }

        $specKind = $specRow['kind'] ?: 'specialisation';

        // Bestaat de link al?
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tblCharacterSpecialisation
            WHERE idCharacter = ? 
              AND idSkill = ? 
              AND idSkillSpecialisation = ?
        ");
        $stmt->execute([$idChar, $idSkill, $idSpec]);
        $alreadyLinked = (int) $stmt->fetchColumn() > 0;

        // Enkel XP-check als:
        // - spelerspersonage
        // - geen discipline
        // - link bestaat nog niet
        if ($isPlayer && $maxXP !== null && $specKind !== 'discipline' && !$alreadyLinked) {
            $usedXP = getUsedXP($pdo, $idChar);
            $cost = 2; // elke niet-discipline specialisatie = 2 EP

            if ($usedXP + $cost > $maxXP) {
                echo json_encode(['error' => 'Onvoldoende ervaringspunten voor deze specialisatie.']);
                exit;
            }
        }

        if (!$alreadyLinked) {
            $stmt = $pdo->prepare("
                INSERT INTO tblCharacterSpecialisation (idCharacter, idSkill, idSkillSpecialisation)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idChar, $idSkill, $idSpec]);
        }

    } else {
        // 2) Nieuwe naam → eerst kijken of die al bestaat voor deze skill
        if ($name === '') {
            throw new RuntimeException("Geen naam opgegeven voor nieuwe specialisatie.");
        }

        $stmt = $pdo->prepare("
            SELECT id, kind
            FROM tblSkillSpecialisation
            WHERE idSkill = ? 
              AND LOWER(name) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$idSkill, $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Specialisatie bestaat al → gebruik die id + kind
            $idSpec = (int) $row['id'];
            $specKind = $row['kind'] ?: 'specialisation';
        } else {
            // Bestaat nog niet → nieuwe rij aanmaken
            $specKind = ($kindFromClient === 'discipline') ? 'discipline' : 'specialisation';

            $stmt = $pdo->prepare("
                INSERT INTO tblSkillSpecialisation (idSkill, name, kind)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idSkill, $name, $specKind]);
            $idSpec = (int) $pdo->lastInsertId();
        }

        // Link met het personage ENKEL als die nog niet bestaat
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tblCharacterSpecialisation
            WHERE idCharacter = ? 
              AND idSkill = ? 
              AND idSkillSpecialisation = ?
        ");
        $stmt->execute([$idChar, $idSkill, $idSpec]);
        $alreadyLinked = (int) $stmt->fetchColumn() > 0;

        // XP-check (zelfde voorwaarden)
        if ($isPlayer && $maxXP !== null && $specKind !== 'discipline' && !$alreadyLinked) {
            $usedXP = getUsedXP($pdo, $idChar);
            $cost = 2;

            if ($usedXP + $cost > $maxXP) {
                echo json_encode(['error' => 'Onvoldoende ervaringspunten voor deze specialisatie.']);
                exit;
            }
        }

        if (!$alreadyLinked) {
            $stmt = $pdo->prepare("
                INSERT INTO tblCharacterSpecialisation (idCharacter, idSkill, idSkillSpecialisation)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idChar, $idSkill, $idSpec]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
