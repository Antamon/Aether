<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/economyUtils.php';

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
    'landlord',
    'household_staff',
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
    $stmtChar = $pdo->prepare("SELECT idUser, type, `class` FROM tblCharacter WHERE id = :id");
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

    if ($idCharacter === $idOtherCharacter) {
        http_response_code(400);
        echo json_encode(['error' => 'Een tie met hetzelfde personage is niet toegelaten.']);
        exit;
    }

    $stmtTarget = $pdo->prepare("SELECT `class` FROM tblCharacter WHERE id = :id");
    $stmtTarget->execute([':id' => $idOtherCharacter]);
    $targetCharacter = $stmtTarget->fetch(PDO::FETCH_ASSOC);

    if (!$targetCharacter) {
        http_response_code(404);
        echo json_encode(['error' => 'Doelpersonage niet gevonden.']);
        exit;
    }

    $ownerCharacterData = [
        'id' => $idCharacter,
        'class' => (string) ($character['class'] ?? ''),
    ];
    $targetCharacterData = [
        'id' => $idOtherCharacter,
        'class' => (string) ($targetCharacter['class'] ?? ''),
    ];

    if ($relationType === 'household_staff' && !canCharacterBeLandlord($pdo, $ownerCharacterData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Dit personage kan geen household staff kiezen.']);
        exit;
    }

    if ($relationType === 'landlord' && !canCharacterBeLandlord($pdo, $targetCharacterData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Het gekozen personage kan niet als landlord aangeduid worden.']);
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
        'landlord' => 'Landlord',
        'household_staff' => 'Household staff',
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
            c.class,
            EXISTS(
                SELECT 1
                FROM tblCharacterTie AS reverseTie
                WHERE reverseTie.idCharacter = t.idCharacterTarget
                  AND reverseTie.idCharacterTarget = t.idCharacter
                  AND reverseTie.relationType = 'superior'
            ) AS hasReverseSuperior,
            EXISTS(
                SELECT 1
                FROM tblCharacterTie AS reverseTie
                WHERE reverseTie.idCharacter = t.idCharacterTarget
                  AND reverseTie.idCharacterTarget = t.idCharacter
                  AND reverseTie.relationType = 'landlord'
            ) AS hasReverseLandlord,
            EXISTS(
                SELECT 1
                FROM tblCharacterTie AS reverseTie
                WHERE reverseTie.idCharacter = t.idCharacterTarget
                  AND reverseTie.idCharacterTarget = t.idCharacter
                  AND reverseTie.relationType = 'household_staff'
            ) AS hasReverseHouseholdStaff,
            EXISTS(
                SELECT 1
                FROM tblCharacterTie AS reverseTie
                WHERE reverseTie.idCharacter = t.idCharacterTarget
                  AND reverseTie.idCharacterTarget = t.idCharacter
                  AND reverseTie.relationType = 'spouse'
            ) AS hasReverseSpouse
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
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'otherClass' => (string) ($row['class'] ?? ''),
            'otherRecurringIncomeTotal' => getCharacterRecurringIncomeTotal($pdo, [
                'id' => (int) $row['idCharacterTarget'],
                'class' => (string) ($row['class'] ?? ''),
            ]),
            'otherMiddleClassLivingStandardIncome' => getCharacterMiddleClassLivingStandardIncome($pdo, [
                'id' => (int) $row['idCharacterTarget'],
                'class' => (string) ($row['class'] ?? ''),
            ]),
            'otherUpperClassLivingStandardTier' => getCharacterUpperClassLivingStandardTier($pdo, [
                'id' => (int) $row['idCharacterTarget'],
                'class' => (string) ($row['class'] ?? ''),
            ]),
            'portraitUrl' => getCharacterPortraitUrl((int) $row['idCharacterTarget']),
            'hasReverseSuperior' => (bool) $row['hasReverseSuperior'],
            'hasReverseLandlord' => (bool) $row['hasReverseLandlord'],
            'hasReverseHouseholdStaff' => (bool) $row['hasReverseHouseholdStaff'],
            'hasReverseSpouse' => (bool) $row['hasReverseSpouse'],
        ];
    }

    echo json_encode(['success' => true, 'ties' => $ties]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon tie niet opslaan.', 'detail' => $e->getMessage()]);
}
