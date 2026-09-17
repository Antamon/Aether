<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterRequestValidation.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

$input = aetherReadCharacterJsonRequest('saveCharacterTie');

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

function fetchCharacterAddress(PDO $pdo, int $idCharacter): ?array
{
    if ($idCharacter <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT street, houseNumber, postalCode, municipality
        FROM tblCharacter
        WHERE id = :idCharacter
    ");
    $stmt->execute([':idCharacter' => $idCharacter]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        'street' => (string) ($row['street'] ?? ''),
        'houseNumber' => (string) ($row['houseNumber'] ?? ''),
        'postalCode' => (string) ($row['postalCode'] ?? ''),
        'municipality' => (string) ($row['municipality'] ?? ''),
    ];
}

function copyCharacterAddress(PDO $pdo, int $sourceCharacterId, int $targetCharacterId): ?array
{
    $address = fetchCharacterAddress($pdo, $sourceCharacterId);
    if ($address === null || $targetCharacterId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        UPDATE tblCharacter
        SET street = :street,
            houseNumber = :houseNumber,
            postalCode = :postalCode,
            municipality = :municipality
        WHERE id = :idCharacter
    ");
    $stmt->execute([
        ':street' => $address['street'],
        ':houseNumber' => $address['houseNumber'],
        ':postalCode' => $address['postalCode'],
        ':municipality' => $address['municipality'],
        ':idCharacter' => $targetCharacterId,
    ]);

    return [
        'idCharacter' => $targetCharacterId,
        'street' => $address['street'],
        'houseNumber' => $address['houseNumber'],
        'postalCode' => $address['postalCode'],
        'municipality' => $address['municipality'],
    ];
}

function tieExists(PDO $pdo, int $idCharacter, int $idCharacterTarget, string $relationType): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM tblCharacterTie
        WHERE idCharacter = :idCharacter
          AND idCharacterTarget = :idCharacterTarget
          AND relationType = :relationType
        LIMIT 1
    ");
    $stmt->execute([
        ':idCharacter' => $idCharacter,
        ':idCharacterTarget' => $idCharacterTarget,
        ':relationType' => $relationType,
    ]);

    return (bool) $stmt->fetchColumn();
}

function syncConfirmedHouseholdStaffAddress(PDO $pdo, int $idCharacter, int $idOtherCharacter, string $relationType): ?array
{
    if ($relationType === 'household_staff') {
        $isConfirmed = tieExists($pdo, $idOtherCharacter, $idCharacter, 'landlord');
        if ($isConfirmed) {
            return copyCharacterAddress($pdo, $idOtherCharacter, $idCharacter);
        }
    }

    if ($relationType === 'landlord') {
        $isConfirmed = tieExists($pdo, $idOtherCharacter, $idCharacter, 'household_staff');
        if ($isConfirmed) {
            return copyCharacterAddress($pdo, $idCharacter, $idOtherCharacter);
        }
    }

    return null;
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

    $userId = (int) $currentUser['id'];
    if (!aetherCanEditCharacter($currentUser, $character)) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om deze tie te wijzigen.']);
        exit;
    }

    if ($idCharacter === $idOtherCharacter) {
        http_response_code(400);
        echo json_encode(['error' => 'Een tie met hetzelfde personage is niet toegelaten.']);
        exit;
    }

    $stmtTarget = $pdo->prepare("SELECT `class`, type, state FROM tblCharacter WHERE id = :id");
    $stmtTarget->execute([':id' => $idOtherCharacter]);
    $targetCharacter = $stmtTarget->fetch(PDO::FETCH_ASSOC);

    if (!$targetCharacter) {
        http_response_code(404);
        echo json_encode(['error' => 'Doelpersonage niet gevonden.']);
        exit;
    }

    if (!aetherIsPrivilegedRole($currentUser['role'])
        && ((string) ($targetCharacter['type'] ?? '') !== 'player'
            || (string) ($targetCharacter['state'] ?? '') !== 'active')) {
        aetherJsonError(403, 'Je hebt geen rechten om dit doelpersonage te koppelen.');
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

    $pdo->beginTransaction();

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

    $syncedAddress = syncConfirmedHouseholdStaffAddress($pdo, $idCharacter, $idOtherCharacter, $relationType);
    $pdo->commit();

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

    echo json_encode([
        'success' => true,
        'ties' => $ties,
        'syncedAddress' => $syncedAddress,
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['error' => 'Kon tie niet opslaan.', 'detail' => $e->getMessage()]);
}
