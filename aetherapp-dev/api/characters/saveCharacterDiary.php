<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

$idCharacter = isset($input['idCharacter']) ? (int)$input['idCharacter'] : 0;
$idDiary = isset($input['idDiary']) ? (int)$input['idDiary'] : 0;
$idEvent = isset($input['idEvent']) ? (int)$input['idEvent'] : 0;
$goals = $input['goals'] ?? '';
$achievements = $input['achievements'] ?? '';
$gossip1 = $input['gossip1'] ?? '';
$gossip2 = $input['gossip2'] ?? '';
$gossip3 = $input['gossip3'] ?? '';

if ($idCharacter <= 0 || ($idDiary <= 0 && $idEvent <= 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

// Character ophalen en rechten bepalen
try {
    $stmtChar = $pdo->prepare("SELECT idUser, type FROM tblCharacter WHERE id = :id");
    $stmtChar->execute([':id' => $idCharacter]);
    $character = $stmtChar->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Character niet gevonden.']);
        exit;
    }

    $userId = (int) $_SESSION['user']['id'];
    $stmtUser = $pdo->prepare("SELECT role FROM tblUser WHERE id = :id");
    $stmtUser->execute([':id' => $userId]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $role = $userRow['role'] ?? ($_SESSION['user']['role'] ?? 'participant');

    $isAdmin = ($role === 'administrator' || $role === 'director');
    $isOwnerPlayer = (
        $role === 'participant' &&
        $character['type'] === 'player' &&
        (int)$character['idUser'] === $userId
    );
    $isOwnerExtra = (
        $role === 'participant' &&
        $character['type'] === 'extra' &&
        (int)$character['idUser'] === $userId
    );

    $canEditAll = $isAdmin || $isOwnerPlayer;
    $canEditAchievementsOnly = (!$canEditAll && $isOwnerExtra);

    if (!$canEditAll && !$canEditAchievementsOnly) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om deze diary te wijzigen.']);
        exit;
    }

    // Bij insert: check of event nog geen entry heeft
    if ($idDiary <= 0) {
        $stmtCheck = $pdo->prepare("SELECT id FROM tblCharacterDiary WHERE idCharacter = :idCharacter AND idEvent = :idEvent");
        $stmtCheck->execute([':idCharacter' => $idCharacter, ':idEvent' => $idEvent]);
        if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dit event heeft al een diary entry.']);
            exit;
        }
    }

    // Beperk velden indien enkel achievements bewerkbaar
    if ($canEditAchievementsOnly && !$canEditAll) {
        // Laat goals/gossip ongemoeid door bestaande waarden op te halen
        if ($idDiary > 0) {
            $stmtExisting = $pdo->prepare("SELECT goals, gossip1, gossip2, gossip3 FROM tblCharacterDiary WHERE id = :id AND idCharacter = :idCharacter");
            $stmtExisting->execute([':id' => $idDiary, ':idCharacter' => $idCharacter]);
            $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $goals = $existing['goals'];
                $gossip1 = $existing['gossip1'];
                $gossip2 = $existing['gossip2'];
                $gossip3 = $existing['gossip3'];
            }
        } else {
            $goals = '';
            $gossip1 = '';
            $gossip2 = '';
            $gossip3 = '';
        }
    }

    if ($idDiary > 0) {
        $sql = "
            UPDATE tblCharacterDiary
               SET goals = :goals,
                   achievements = :achievements,
                   gossip1 = :gossip1,
                   gossip2 = :gossip2,
                   gossip3 = :gossip3,
                   updatedAt = NOW(),
                   updatedBy = :updatedBy
             WHERE id = :idDiary AND idCharacter = :idCharacter
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':goals' => $goals,
            ':achievements' => $achievements,
            ':gossip1' => $gossip1,
            ':gossip2' => $gossip2,
            ':gossip3' => $gossip3,
            ':updatedBy' => $userId,
            ':idDiary' => $idDiary,
            ':idCharacter' => $idCharacter
        ]);
    } else {
        $sql = "
            INSERT INTO tblCharacterDiary
                (idCharacter, idEvent, goals, achievements, gossip1, gossip2, gossip3, createdAt, createdBy, updatedAt, updatedBy)
            VALUES
                (:idCharacter, :idEvent, :goals, :achievements, :gossip1, :gossip2, :gossip3, NOW(), :createdBy, NOW(), :updatedBy)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':idCharacter' => $idCharacter,
            ':idEvent' => $idEvent,
            ':goals' => $goals,
            ':achievements' => $achievements,
            ':gossip1' => $gossip1,
            ':gossip2' => $gossip2,
            ':gossip3' => $gossip3,
            ':createdBy' => $userId,
            ':updatedBy' => $userId
        ]);
        $idDiary = (int)$pdo->lastInsertId();
    }

    // Refresh data
    $stmtList = $pdo->prepare("
        SELECT d.*, e.title AS eventTitle, e.dateStart, e.dateEnd
          FROM tblCharacterDiary d
          JOIN tblEvent e ON e.id = d.idEvent
         WHERE d.idCharacter = :idCharacter
         ORDER BY e.dateStart DESC, e.id DESC
    ");
    $stmtList->execute([':idCharacter' => $idCharacter]);
    $entries = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtAvail = $pdo->prepare("
        SELECT e.id, e.title, e.dateStart
          FROM tblEvent e
         WHERE e.id NOT IN (
             SELECT idEvent FROM tblCharacterDiary WHERE idCharacter = :idCharacter
         )
         ORDER BY e.dateStart DESC, e.id DESC
    ");
    $stmtAvail->execute([':idCharacter' => $idCharacter]);
    $available = $stmtAvail->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'entries' => array_map(function ($row) {
            $row['id'] = (int)$row['id'];
            $row['idCharacter'] = (int)$row['idCharacter'];
            $row['idEvent'] = (int)$row['idEvent'];
            return $row;
        }, $entries),
        'availableEvents' => array_map(function ($row) {
            $row['id'] = (int)$row['id'];
            return $row;
        }, $available)
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon diary niet opslaan.', 'detail' => $e->getMessage()]);
}
