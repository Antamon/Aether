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

$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;
$idTie = isset($input['idTie']) ? (int) $input['idTie'] : 0;

if ($idCharacter <= 0 || $idTie <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

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
        (int) $character['idUser'] === $userId
    );

    if (!$isAdmin && !$isOwnerPlayer) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om deze tie te verwijderen.']);
        exit;
    }

    $stmtDelete = $pdo->prepare(
        "DELETE FROM tblCharacterTie
          WHERE id = :idTie
            AND idCharacter = :idCharacter"
    );
    $stmtDelete->execute([
        ':idTie' => $idTie,
        ':idCharacter' => $idCharacter,
    ]);

    if ($stmtDelete->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'Tie niet gevonden.']);
        exit;
    }

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
            'id' => (int) $row['id'],
            'idOtherCharacter' => (int) $row['idCharacterTarget'],
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
    echo json_encode(['error' => 'Kon tie niet verwijderen.', 'detail' => $e->getMessage()]);
}
