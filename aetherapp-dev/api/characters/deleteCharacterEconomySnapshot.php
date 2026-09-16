<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterRequestValidation.php';

$input = aetherReadCharacterJsonRequest('deleteCharacterEconomySnapshot');

$idSnapshot = isset($input['idSnapshot']) ? (int) $input['idSnapshot'] : 0;

if ($idSnapshot <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Snapshot is verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCsrfToken();
    $currentUserRole = $currentUser['role'];
    $currentUserId = (int) $currentUser['id'];

    $stmt = $pdo->prepare(
        'SELECT
            ces.id,
            ces.idCharacter,
            e.title AS eventTitle,
            ces.amount,
            ces.securitiesReturnAmount,
            ces.securitiesStatus,
            ces.securitiesSnapshotWithdrawalAmount,
            withdrawalTx.id AS securitiesSnapshotWithdrawalTransactionId,
            withdrawalTx.bankAmount AS securitiesSnapshotWithdrawalBankAmount,
            c.state,
            c.idUser
         FROM tblCharacterEconomySnapshot AS ces
         JOIN tblCharacter AS c
           ON c.id = ces.idCharacter
         JOIN tblEvent AS e
           ON e.id = ces.idEvent
         LEFT JOIN tblCharacterSecuritiesTransaction AS withdrawalTx
           ON withdrawalTx.idCharacter = ces.idCharacter
          AND withdrawalTx.description LIKE CONCAT(\'[snapshot-withdrawal:\', ces.id, \']%\')
         WHERE ces.id = :id'
    );
    $stmt->execute(['id' => $idSnapshot]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$snapshot) {
        http_response_code(404);
        echo json_encode(['error' => 'Snapshot niet gevonden.']);
        exit;
    }

    if (!canManageCharacterEconomySnapshots($snapshot, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om deze economiesnapshot te verwijderen.']);
        exit;
    }

    $idCharacter = (int) ($snapshot['idCharacter'] ?? 0);
    $amount = round((float) ($snapshot['amount'] ?? 0), 2);
    $securitiesReturnAmount = round((float) ($snapshot['securitiesReturnAmount'] ?? 0), 2);
    $securitiesStatus = trim((string) ($snapshot['securitiesStatus'] ?? 'none'));
    $securitiesWithdrawalAmount = round((float) ($snapshot['securitiesSnapshotWithdrawalAmount'] ?? 0), 2);
    $securitiesWithdrawalTransactionId = (int) ($snapshot['securitiesSnapshotWithdrawalTransactionId'] ?? 0);
    $securitiesWithdrawalBankAmount = round((float) ($snapshot['securitiesSnapshotWithdrawalBankAmount'] ?? 0), 2);
    if ($securitiesWithdrawalAmount > 0 && $securitiesWithdrawalBankAmount <= 0) {
        $securitiesWithdrawalBankAmount = $securitiesWithdrawalAmount;
    }

    $pdo->beginTransaction();

    $stmtDelete = $pdo->prepare(
        'DELETE FROM tblCharacterEconomySnapshot
         WHERE id = :id'
    );
    $stmtDelete->execute(['id' => $idSnapshot]);

    $stmtUpdateCharacter = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
         WHERE id = :idCharacter'
    );
    $stmtUpdateCharacter->execute([
        'amount' => $amount,
        'idCharacter' => $idCharacter,
    ]);

    if ($securitiesStatus === 'approved' && $securitiesReturnAmount !== 0.0) {
        $stmtReverseReturn = $pdo->prepare(
            'UPDATE tblCharacter
             SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
             WHERE id = :idCharacter'
        );
        $stmtReverseReturn->execute([
            'amount' => $securitiesReturnAmount,
            'idCharacter' => $idCharacter,
        ]);
    }

    if ($securitiesWithdrawalAmount > 0) {
        $stmtReverseWithdrawal = $pdo->prepare(
            'UPDATE tblCharacter
             SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :bankSubtractAmount, 2),
                 securitiesaccount = ROUND(COALESCE(securitiesaccount, 0) + :securitiesAddAmount, 2)
             WHERE id = :idCharacter'
        );
        $stmtReverseWithdrawal->execute([
            'bankSubtractAmount' => $securitiesWithdrawalBankAmount,
            'securitiesAddAmount' => $securitiesWithdrawalAmount,
            'idCharacter' => $idCharacter,
        ]);

        if ($securitiesWithdrawalTransactionId > 0) {
            $pdo->prepare(
                'DELETE FROM tblCharacterSecuritiesTransaction
                 WHERE id = :id'
            )->execute([
                'id' => $securitiesWithdrawalTransactionId,
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'revertedAmount' => $amount,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon economiesnapshot niet verwijderen.',
        'details' => $e->getMessage(),
    ]);
}
