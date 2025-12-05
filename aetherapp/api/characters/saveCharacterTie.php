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
$idTie = isset($input['idTie']) ? (int)$input['idTie'] : 0;
$idOtherCharacter = isset($input['idOtherCharacter']) ? (int)$input['idOtherCharacter'] : 0;
$relationType = $input['relationType'] ?? '';
$description = $input['description'] ?? '';

$allowedTypes = [
    'superior',
    'dependent',
    'spouse',
    'ally',
    'adversary',
    'person_of_interest'
];

if ($idCharacter <= 0 || $idOtherCharacter <= 0 || !in_array($relationType, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    // Character info ophalen voor rechten
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

    if (!$isAdmin && !$isOwnerPlayer) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om deze tie te wijzigen.']);
        exit;
    }

    if ($idTie > 0) {
        $sql = "
            UPDATE tblCharacterTie
               SET idCharacterTarget = :idOtherCharacter,
                   relationType = :relationType,
                   description = :description,
                   updatedAt = NOW(),
                   updatedBy = :updatedBy
             WHERE id = :idTie AND idCharacter = :idCharacter
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':idOtherCharacter' => $idOtherCharacter,
            ':relationType' => $relationType,
            ':description' => $description,
            ':updatedBy' => $userId,
            ':idTie' => $idTie,
            ':idCharacter' => $idCharacter
        ]);
    } else {
        $sql = "
            INSERT INTO tblCharacterTie
                (idCharacter, idCharacterTarget, relationType, description, updatedAt, updatedBy, createdAt, createdBy)
            VALUES
                (:idCharacter, :idOtherCharacter, :relationType, :description, NOW(), :updatedBy, NOW(), :createdBy)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':idCharacter' => $idCharacter,
            ':idOtherCharacter' => $idOtherCharacter,
            ':relationType' => $relationType,
            ':description' => $description,
            ':updatedBy' => $userId,
            ':createdBy' => $userId
        ]);
        $idTie = (int)$pdo->lastInsertId();
    }

    // Refresh list
    $tieLabels = [
        'superior' => 'Superior',
        'dependent' => 'Dependent',
        'spouse' => 'Spouse',
        'ally' => 'Ally',
        'adversary' => 'Adversary',
        'person_of_interest' => 'Person of interest',
    ];

        $stmtList = $pdo->prepare("
        SELECT
            t.id,
            t.idCharacterTarget,
            t.relationType,
            t.description,
            c.firstName,
            c.lastName,
            c.title,
            c.class
        FROM tblCharacterTie t
        JOIN tblCharacter c ON c.id = t.idCharacterTarget
        WHERE t.idCharacter = :idCharacter
        ORDER BY c.firstName, c.lastName
    ");
    $stmtList->execute([':idCharacter' => $idCharacter]);

    $ties = [];
    while ($row = $stmtList->fetch(PDO::FETCH_ASSOC)) {
        $displayName = '';
        if ($row['class'] === 'upper class' && !empty($row['title'])) {
            $displayName = trim($row['title'] . ' ' . $row['firstName'] . ' ' . $row['lastName']);
        } else {
            $displayName = trim($row['firstName'] . ' ' . $row['lastName']);
        }

        $ties[] = [
            'id' => (int)$row['id'],
            'idOtherCharacter' => (int)$row['idCharacterTarget'],
            'relationType' => $row['relationType'],
            'relationTypeLabel' => $tieLabels[$row['relationType']] ?? $row['relationType'],
            'description' => $row['description'],
            'otherName' => $displayName,
        ];
    }

    echo json_encode(['success' => true, 'ties' => $ties]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon tie niet opslaan.', 'detail' => $e->getMessage()]);
}
