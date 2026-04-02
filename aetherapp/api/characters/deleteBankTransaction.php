<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idTransaction = isset($input['idTransaction']) ? (int) $input['idTransaction'] : 0;

if ($idTransaction <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige verrichting.']);
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

    if (!isPrivilegedUserRole($currentUserRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om verrichtingen te verwijderen.']);
        exit;
    }

    $stmtTransaction = $pdo->prepare(
        'SELECT id, idSourceCharacter, idTargetCharacter, amount
         FROM tblCharacterBankTransaction
         WHERE id = :id'
    );
    $stmtTransaction->execute(['id' => $idTransaction]);
    $transaction = $stmtTransaction->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Verrichting niet gevonden.']);
        exit;
    }

    $amount = round((float) ($transaction['amount'] ?? 0), 2);
    $idSourceCharacter = (int) ($transaction['idSourceCharacter'] ?? 0);
    $idTargetCharacter = (int) ($transaction['idTargetCharacter'] ?? 0);

    if ($amount <= 0 || $idSourceCharacter <= 0 || $idTargetCharacter <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Deze verrichting kan niet veilig verwijderd worden.']);
        exit;
    }

    $pdo->beginTransaction();

    $stmtRestoreSource = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :amount, 2)
         WHERE id = :id'
    );
    $stmtRestoreSource->execute([
        'amount' => $amount,
        'id' => $idSourceCharacter,
    ]);

    $stmtRevertTarget = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
         WHERE id = :id'
    );
    $stmtRevertTarget->execute([
        'amount' => $amount,
        'id' => $idTargetCharacter,
    ]);

    $stmtDelete = $pdo->prepare(
        'DELETE FROM tblCharacterBankTransaction
         WHERE id = :id'
    );
    $stmtDelete->execute(['id' => $idTransaction]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['error' => 'Kon verrichting niet verwijderen.']);
}
