<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$idSourceCharacter = isset($input['idSourceCharacter']) ? (int) $input['idSourceCharacter'] : 0;
$idTargetCharacter = isset($input['idTargetCharacter']) ? (int) $input['idTargetCharacter'] : 0;
$amount = isset($input['amount']) ? round((float) $input['amount'], 2) : 0.0;
$description = trim((string) ($input['description'] ?? ''));
$transactionDate = trim((string) ($input['transactionDate'] ?? ''));

if ($idSourceCharacter <= 0 || $idTargetCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Bron- en doelpersonage zijn verplicht.']);
    exit;
}

if ($idSourceCharacter === $idTargetCharacter) {
    http_response_code(400);
    echo json_encode(['error' => 'Een overschrijving naar hetzelfde personage is niet toegestaan.']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Het bedrag moet groter zijn dan 0.']);
    exit;
}

if ($transactionDate === '') {
    $transactionDate = getDefaultBankTransferDate();
}

$dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $transactionDate);
$dateErrors = DateTimeImmutable::getLastErrors();
$warningCount = is_array($dateErrors) ? (int) ($dateErrors['warning_count'] ?? 0) : 0;
$errorCount = is_array($dateErrors) ? (int) ($dateErrors['error_count'] ?? 0) : 0;
$hasDateErrors = $warningCount > 0 || $errorCount > 0;
if (!$dateTime || $hasDateErrors || $dateTime->format('Y-m-d') !== $transactionDate) {
    http_response_code(400);
    echo json_encode(['error' => 'De datum van de overschrijving is ongeldig.']);
    exit;
}

if (mb_strlen($description) > 255) {
    $description = mb_substr($description, 0, 255);
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

    $stmtSource = $pdo->prepare(
        'SELECT id, state, bankaccount, idUser, firstName, lastName
         FROM tblCharacter
         WHERE id = :id'
    );
    $stmtSource->execute(['id' => $idSourceCharacter]);
    $sourceCharacter = $stmtSource->fetch(PDO::FETCH_ASSOC);

    $stmtTarget = $pdo->prepare(
        'SELECT id, state, bankaccount, idUser, firstName, lastName
         FROM tblCharacter
         WHERE id = :id'
    );
    $stmtTarget->execute(['id' => $idTargetCharacter]);
    $targetCharacter = $stmtTarget->fetch(PDO::FETCH_ASSOC);

    if (!$sourceCharacter || !$targetCharacter) {
        http_response_code(404);
        echo json_encode(['error' => 'Een van de gekozen personages bestaat niet.']);
        exit;
    }

    if (!canTransferFromCharacter($sourceCharacter, $currentUserRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om een overschrijving voor dit personage uit te voeren.']);
        exit;
    }

    if ((string) ($targetCharacter['state'] ?? '') === 'draft') {
        http_response_code(400);
        echo json_encode(['error' => 'Je kan niet overschrijven naar een personage in draft.']);
        exit;
    }

    if ((string) ($targetCharacter['state'] ?? '') !== 'active') {
        http_response_code(400);
        echo json_encode(['error' => 'Je kan alleen overschrijven naar actieve personages.']);
        exit;
    }

    $sourceBalance = round((float) ($sourceCharacter['bankaccount'] ?? 0), 2);
    if ($amount > $sourceBalance) {
        http_response_code(400);
        echo json_encode(['error' => 'Je kan niet meer geld overschrijven dan het beschikbare saldo op de rekening.']);
        exit;
    }

    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare(
        "INSERT INTO tblCharacterBankTransaction
            (idSourceCharacter, idTargetCharacter, amount, transactionDate, description, createdAt, createdBy)
         VALUES
            (:idSourceCharacter, :idTargetCharacter, :amount, :transactionDate, :description, NOW(), :createdBy)"
    );
    $stmtInsert->execute([
        'idSourceCharacter' => $idSourceCharacter,
        'idTargetCharacter' => $idTargetCharacter,
        'amount' => $amount,
        'transactionDate' => $transactionDate,
        'description' => $description !== '' ? $description : null,
        'createdBy' => $currentUserId,
    ]);

    $stmtUpdateSource = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
         WHERE id = :id'
    );
    $stmtUpdateSource->execute([
        'amount' => $amount,
        'id' => $idSourceCharacter,
    ]);

    $stmtUpdateTarget = $pdo->prepare(
        'UPDATE tblCharacter
         SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :amount, 2)
         WHERE id = :id'
    );
    $stmtUpdateTarget->execute([
        'amount' => $amount,
        'id' => $idTargetCharacter,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'idTransaction' => (int) $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon overschrijving niet bewaren.'
    ]);
}
