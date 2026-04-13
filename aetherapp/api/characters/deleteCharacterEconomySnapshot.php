<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$idSnapshot = isset($input['idSnapshot']) ? (int) $input['idSnapshot'] : 0;

if ($idSnapshot <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Snapshot is verplicht.']);
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

    $stmt = $pdo->prepare(
        'SELECT
            ces.id,
            ces.idCharacter,
            ces.amount,
            ces.securitiesReturnAmount,
            ces.securitiesStatus,
            ces.securitiesSnapshotWithdrawalAmount,
            c.state,
            c.idUser
         FROM tblCharacterEconomySnapshot AS ces
         JOIN tblCharacter AS c
           ON c.id = ces.idCharacter
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
            'bankSubtractAmount' => $securitiesWithdrawalAmount,
            'securitiesAddAmount' => $securitiesWithdrawalAmount,
            'idCharacter' => $idCharacter,
        ]);
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
