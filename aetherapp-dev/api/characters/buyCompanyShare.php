<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/companyShareUtils.php';
require_once __DIR__ . '/../companies/companyUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;
$idCompany = isset($input['idCompany']) ? (int) $input['idCompany'] : 0;
$shareClass = trim((string) ($input['shareClass'] ?? ''));

if ($idCharacter <= 0 || $idCompany <= 0 || ($shareClass !== 'A' && $shareClass !== 'B')) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUserRole = getCurrentUserRole($pdo);
    $currentUserId = getCurrentUserId();

    if ($currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $character = dbOne(
        $pdo,
        'SELECT id, `class`, type, state, idUser, bankaccount
         FROM tblCharacter
         WHERE id = :id',
        ['id' => $idCharacter]
    );

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    if (!canIncreaseCompanyShareRank($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om een nieuw aandeel te kopen voor dit personage.']);
        exit;
    }

    if ((string) ($character['state'] ?? '') === 'draft') {
        http_response_code(400);
        echo json_encode(['error' => 'Nieuwe aandelen koop je pas zodra het personage niet meer in draft staat.']);
        exit;
    }

    $company = dbOne(
        $pdo,
        'SELECT id, companyName, companyValue
         FROM tblCompany
         WHERE id = :id',
        ['id' => $idCompany]
    );

    if (!$company) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
        exit;
    }

    $company = enrichCompanyWithType($company);
    $unitPrice = round(normalizeCompanyValue($company['companyValue'] ?? 0) / 100, 2);
    if ($unitPrice <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'De bedrijfswaarde is ongeldig voor een aandelenaankoop.']);
        exit;
    }

    $currentBalance = round((float) ($character['bankaccount'] ?? 0), 2);
    if ($unitPrice > $currentBalance) {
        http_response_code(400);
        echo json_encode(['error' => 'Onvoldoende saldo op de bankrekening.']);
        exit;
    }

    $allocatedByCompany = getCompanyShareAllocationByCompany($pdo);
    $remainingSharePercentage = max(0, 100 - (int) ($allocatedByCompany[$idCompany] ?? 0));
    if ($remainingSharePercentage < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Dit bedrijf heeft geen vrij aandeel meer voor +1%.']);
        exit;
    }

    $traitClass = trim((string) ($character['class'] ?? ''));
    $companyTypeKey = trim((string) ($company['companyTypeKey'] ?? ''));
    $idTrait = findCompanyShareTraitIdForCompanyType($pdo, $traitClass, $shareClass, $companyTypeKey);
    if ($idTrait === null || $idTrait <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Geen passend aandelen-trait gevonden voor dit personage en bedrijf.']);
        exit;
    }

    $existingLink = dbOne(
        $pdo,
        'SELECT id
         FROM tblLinkCharacterTrait
         WHERE idCharacter = :idCharacter
           AND idTrait = :idTrait',
        [
            'idCharacter' => $idCharacter,
            'idTrait' => $idTrait,
        ]
    );

    if ($existingLink) {
        http_response_code(400);
        echo json_encode(['error' => 'Dit aandelen-trait is al gekoppeld aan het personage. Gebruik de bestaande aandeelkaart om verder te verhogen.']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkCharacterTrait (idCharacter, idTrait, rankValue)
         VALUES (:idCharacter, :idTrait, :rankValue)'
    );
    $stmt->execute([
        'idCharacter' => $idCharacter,
        'idTrait' => $idTrait,
        'rankValue' => 1,
    ]);

    $idLinkCharacterTrait = (int) $pdo->lastInsertId();
    if ($idLinkCharacterTrait <= 0) {
        throw new RuntimeException('Kon het aandelen-trait niet koppelen aan het personage.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkCharacterTraitCompany (idLinkCharacterTrait, idCompany, extraPercentage)
         VALUES (:idLinkCharacterTrait, :idCompany, 0)'
    );
    $stmt->execute([
        'idLinkCharacterTrait' => $idLinkCharacterTrait,
        'idCompany' => $idCompany,
    ]);

    $stmt = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amountSubtract, 2)
         WHERE id = :idCharacter
           AND COALESCE(bankaccount, 0) >= :amountCheck'
    );
    $stmt->execute([
        'amountSubtract' => $unitPrice,
        'amountCheck' => $unitPrice,
        'idCharacter' => $idCharacter,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Onvoldoende saldo op rekening.');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'idLinkCharacterTrait' => $idLinkCharacterTrait,
        'unitPrice' => $unitPrice,
    ]);
} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Kon het aandeel niet kopen.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon het aandeel niet kopen.',
        'details' => $e->getMessage(),
    ]);
}
