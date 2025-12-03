<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $idCharacter = isset($input['id']) ? (int) $input['id'] : 0;

    if ($idCharacter <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldig character-id.']);
        exit;
    }

    $pdo = getPDO();

    // --- 1. Basisgegevens van het personage ---
    $stmt = $pdo->prepare('SELECT * FROM tblCharacter WHERE id = ?');
    $stmt->execute([$idCharacter]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    // --- 2. Skills (zonder types, die doen we apart) ---
    $sqlSkills = "
        SELECT
            lcs.idSkill      AS id,
            s.name,
            s.description,
            s.beginner,
            s.professional,
            s.master,
            lcs.level
        FROM tblLinkCharacterSkill AS lcs
        JOIN tblSkill AS s
              ON s.id = lcs.idSkill
        WHERE lcs.idCharacter = ?
        ORDER BY s.name
    ";
    $stmt = $pdo->prepare($sqlSkills);
    $stmt->execute([$idCharacter]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Specialisaties per skill voor dit personage ---
    $sqlSpecs = "
        SELECT
        cs.idSkill,
        ss.id,
        ss.name,
        ss.kind
        FROM tblCharacterSpecialisation AS cs
        JOIN tblSkillSpecialisation AS ss
            ON ss.id = cs.idSkillSpecialisation
        WHERE cs.idCharacter = ?
        ORDER BY ss.name
    ";
    $stmt = $pdo->prepare($sqlSpecs);
    $stmt->execute([$idCharacter]);
    $specRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $specBySkill = [];
    foreach ($specRows as $row) {
        $skillId = (int) $row['idSkill'];
        if (!isset($specBySkill[$skillId])) {
            $specBySkill[$skillId] = [];
        }
        $specBySkill[$skillId][] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'kind' => $row['kind'] ?? 'specialisation',
        ];
    }

    // --- 4. Type-attributen per skill (met beschrijving) ---
    $sqlTypes = "
        SELECT
            lst.idSkill,
            st.name,
            st.description
        FROM tblLinkSkillType AS lst
        JOIN tblSkillType AS st
              ON st.id = lst.idSkillType
        WHERE lst.idSkill IN (
            SELECT idSkill
            FROM tblLinkCharacterSkill
            WHERE idCharacter = ?
        )
        ORDER BY st.name
    ";
    $stmt = $pdo->prepare($sqlTypes);
    $stmt->execute([$idCharacter]);
    $typeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $typesBySkill = [];
    foreach ($typeRows as $row) {
        $skillId = (int) $row['idSkill'];
        if (!isset($typesBySkill[$skillId])) {
            $typesBySkill[$skillId] = [];
        }
        $typesBySkill[$skillId][] = [
            'name' => $row['name'],
            'description' => $row['description'],
        ];
    }

    // --- 5. Skills verrijken met types + specialisations ---
    foreach ($skills as &$skill) {
        $sid = (int) $skill['id'];

        // array van {name, description}
        $skill['types'] = $typesBySkill[$sid] ?? [];

        // bestaande specialisaties
        $skill['specialisations'] = $specBySkill[$sid] ?? [];
    }
    unset($skill);

    $character['skills'] = $skills;

    // --- 6. Naam van de deelnemer (indien gekoppeld) ---
    if (!empty($character['idUser'])) {
        $stmt = $pdo->prepare('SELECT firstName, lastName FROM tblUser WHERE id = ?');
        $stmt->execute([(int) $character['idUser']]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow) {
            $character['nameParticipant'] = trim($userRow['firstName'] . ' ' . $userRow['lastName']);
        } else {
            $character['nameParticipant'] = null;
        }
    } else {
        $character['nameParticipant'] = null;
    }

    // --- 7. Ervaringspunten voor spelerspersonages ---
    if ($character['type'] === 'player' && !empty($character['idUser'])) {
        $sqlXP = "
            SELECT SUM(e.ep) AS total
            FROM tblLinkEventUser AS leu
            JOIN tblEvent AS e
                  ON leu.idEvent = e.id
            WHERE leu.idUser = ?
        ";
        $stmt = $pdo->prepare($sqlXP);
        $stmt->execute([(int) $character['idUser']]);
        $rowXP = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalXP = (int) ($rowXP['total'] ?? 0);
        // +15 basis XP zoals vroeger
        $character['experience'] = $totalXP + 15;
    }

    echo json_encode($character);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet ophalen.',
        'details' => $e->getMessage()
    ]);
}
