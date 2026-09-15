<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;
$idEvent = isset($input['idEvent']) ? (int) $input['idEvent'] : 0;

if ($idCharacter <= 0 || $idEvent <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Personage en event zijn verplicht.']);
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

    $stmtCharacter = $pdo->prepare(
        'SELECT *
         FROM tblCharacter
         WHERE id = :id'
    );
    $stmtCharacter->execute(['id' => $idCharacter]);
    $character = $stmtCharacter->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    if (!canManageCharacterEconomySnapshots($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om een economiesnapshot voor dit personage te maken.']);
        exit;
    }

    $stmtEvent = $pdo->prepare(
        'SELECT id, title, dateStart
         FROM tblEvent
         WHERE id = :id'
    );
    $stmtEvent->execute(['id' => $idEvent]);
    $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event niet gevonden.']);
        exit;
    }

    $stmtExisting = $pdo->prepare(
        'SELECT id
         FROM tblCharacterEconomySnapshot
         WHERE idCharacter = :idCharacter
           AND idEvent = :idEvent'
    );
    $stmtExisting->execute([
        'idCharacter' => $idCharacter,
        'idEvent' => $idEvent,
    ]);

    if ($stmtExisting->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(400);
        echo json_encode(['error' => 'Voor dit event bestaat al een economiesnapshot van dit personage.']);
        exit;
    }

    $snapshotAmount = getCharacterEconomySnapshotAmount($pdo, $character);
    $securitiesSnapshot = calculateCharacterSecuritiesSnapshotData($pdo, $character);
    $transactionDate = trim((string) ($event['dateStart'] ?? ''));
    if ($transactionDate === '') {
        $transactionDate = getDefaultBankTransferDate();
    }

    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare(
        "INSERT INTO tblCharacterEconomySnapshot
            (
                idCharacter,
                idEvent,
                amount,
                transactionDate,
                securitiesBalanceSnapshot,
                securitiesManagerType,
                securitiesManagerCharacterId,
                securitiesRiskProfile,
                securitiesManagerSkillLevel,
                securitiesBasePercentage,
                securitiesVariationLimitPercentage,
                securitiesVariationPercentage,
                securitiesReturnPercentage,
                securitiesReturnAmount,
                securitiesStatus,
                createdAt,
                createdBy
            )
         VALUES
            (
                :idCharacter,
                :idEvent,
                :amount,
                :transactionDate,
                :securitiesBalanceSnapshot,
                :securitiesManagerType,
                :securitiesManagerCharacterId,
                :securitiesRiskProfile,
                :securitiesManagerSkillLevel,
                :securitiesBasePercentage,
                :securitiesVariationLimitPercentage,
                :securitiesVariationPercentage,
                :securitiesReturnPercentage,
                :securitiesReturnAmount,
                :securitiesStatus,
                NOW(),
                :createdBy
            )"
    );
    $stmtInsert->execute([
        'idCharacter' => $idCharacter,
        'idEvent' => $idEvent,
        'amount' => $snapshotAmount,
        'transactionDate' => $transactionDate,
        'securitiesBalanceSnapshot' => $securitiesSnapshot['balanceSnapshot'] ?? 0,
        'securitiesManagerType' => $securitiesSnapshot['managerType'] ?? 'none',
        'securitiesManagerCharacterId' => $securitiesSnapshot['managerCharacterId'] ?? null,
        'securitiesRiskProfile' => $securitiesSnapshot['riskProfile'] ?? 3,
        'securitiesManagerSkillLevel' => $securitiesSnapshot['managerSkillLevel'] ?? 0,
        'securitiesBasePercentage' => $securitiesSnapshot['basePercentage'] ?? 0,
        'securitiesVariationLimitPercentage' => $securitiesSnapshot['variationLimitPercentage'] ?? 0,
        'securitiesVariationPercentage' => $securitiesSnapshot['variationPercentage'] ?? 0,
        'securitiesReturnPercentage' => $securitiesSnapshot['returnPercentage'] ?? 0,
        'securitiesReturnAmount' => $securitiesSnapshot['returnAmount'] ?? 0,
        'securitiesStatus' => $securitiesSnapshot['status'] ?? 'none',
        'createdBy' => $currentUserId,
    ]);

    $stmtUpdateCharacter = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :amount, 2)
         WHERE id = :idCharacter'
    );
    $stmtUpdateCharacter->execute([
        'amount' => $snapshotAmount,
        'idCharacter' => $idCharacter,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'amount' => $snapshotAmount,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon economiesnapshot niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
