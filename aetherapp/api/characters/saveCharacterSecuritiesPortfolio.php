<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string) ($input['action'] ?? ''));
$idCharacter = (int) ($input['idCharacter'] ?? 0);

if ($idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Personage is verplicht.']);
    exit;
}

if (!in_array($action, ['save_settings', 'deposit', 'manual_withdrawal', 'reroll_snapshot', 'approve_snapshot', 'withdraw_snapshot'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige actie.']);
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
        'SELECT *
           FROM tblCharacter
          WHERE id = :idCharacter',
        ['idCharacter' => $idCharacter]
    );

    if ($character === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    if (in_array($action, ['reroll_snapshot', 'approve_snapshot'], true)) {
        if (!canApproveCharacterSecuritiesSnapshots($character, $currentUserRole, $currentUserId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Je hebt geen rechten om effectenportefeuillesnapshots te beheren.']);
            exit;
        }
    } elseif (!canManageCharacterSecurities($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om deze effectenportefeuille te beheren.']);
        exit;
    }

    if ($action === 'save_settings') {
        $managerType = normalizeCharacterSecuritiesManagerType($input['managerType'] ?? 'self');
        $riskProfile = normalizeCharacterSecuritiesRiskProfile($input['riskProfile'] ?? 3);
        $managerCharacterId = $managerType === 'third'
            ? (int) ($input['managerCharacterId'] ?? 0)
            : 0;

        if ($managerType === 'third') {
            if ($managerCharacterId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Kies een actieve beheerder.']);
                exit;
            }

            $manager = dbOne(
                $pdo,
                "SELECT id
                   FROM tblCharacter
                  WHERE id = :idCharacter
                    AND state = 'active'",
                ['idCharacter' => $managerCharacterId]
            );

            if ($manager === null) {
                http_response_code(400);
                echo json_encode(['error' => 'De gekozen beheerder is niet actief.']);
                exit;
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE tblCharacter
                SET securitiesManagerType = :managerType,
                    securitiesManagerCharacterId = :managerCharacterId,
                    securitiesRiskProfile = :riskProfile
              WHERE id = :idCharacter'
        );
        $stmt->execute([
            'managerType' => $managerType,
            'managerCharacterId' => $managerType === 'third' ? $managerCharacterId : null,
            'riskProfile' => $riskProfile,
            'idCharacter' => $idCharacter,
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'deposit') {
        $amount = round((float) ($input['amount'] ?? 0), 2);
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Geef een geldig bedrag op om te storten.']);
            exit;
        }

        $bankBalance = round((float) ($character['bankaccount'] ?? 0), 2);
        if ($amount > $bankBalance) {
            http_response_code(400);
            echo json_encode(['error' => 'Onvoldoende saldo op de bankrekening.']);
            exit;
        }

        $transactionDate = getDefaultBankTransferDate();

        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE tblCharacter
                SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :bankSubtractAmount, 2),
                    securitiesaccount = ROUND(COALESCE(securitiesaccount, 0) + :securitiesAddAmount, 2)
              WHERE id = :idCharacter'
        )->execute([
            'bankSubtractAmount' => $amount,
            'securitiesAddAmount' => $amount,
            'idCharacter' => $idCharacter,
        ]);

        $pdo->prepare(
            'INSERT INTO tblCharacterSecuritiesTransaction
                (idCharacter, direction, securitiesAmount, bankAmount, transactionDate, description, createdBy)
             VALUES
                (:idCharacter, :direction, :securitiesAmount, :bankAmount, :transactionDate, :description, :createdBy)'
        )->execute([
            'idCharacter' => $idCharacter,
            'direction' => 'deposit',
            'securitiesAmount' => $amount,
            'bankAmount' => -$amount,
            'transactionDate' => $transactionDate,
            'description' => 'Storting naar effectenportefeuille',
            'createdBy' => $currentUserId,
        ]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'manual_withdrawal') {
        $amount = round((float) ($input['amount'] ?? 0), 2);
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Geef een geldig bedrag op om op te nemen.']);
            exit;
        }

        $portfolioBalance = round((float) ($character['securitiesaccount'] ?? 0), 2);
        if ($amount > $portfolioBalance) {
            http_response_code(400);
            echo json_encode(['error' => 'Onvoldoende saldo in de effectenportefeuille.']);
            exit;
        }

        $bankAmount = round($amount * 0.75, 2);
        $transactionDate = getDefaultBankTransferDate();

        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE tblCharacter
                SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :bankAddAmount, 2),
                    securitiesaccount = ROUND(COALESCE(securitiesaccount, 0) - :securitiesSubtractAmount, 2)
              WHERE id = :idCharacter'
        )->execute([
            'bankAddAmount' => $bankAmount,
            'securitiesSubtractAmount' => $amount,
            'idCharacter' => $idCharacter,
        ]);

        $pdo->prepare(
            'INSERT INTO tblCharacterSecuritiesTransaction
                (idCharacter, direction, securitiesAmount, bankAmount, transactionDate, description, createdBy)
             VALUES
                (:idCharacter, :direction, :securitiesAmount, :bankAmount, :transactionDate, :description, :createdBy)'
        )->execute([
            'idCharacter' => $idCharacter,
            'direction' => 'manual_withdrawal',
            'securitiesAmount' => -$amount,
            'bankAmount' => $bankAmount,
            'transactionDate' => $transactionDate,
            'description' => 'Opname uit effectenportefeuille buiten snapshot (25% verlies)',
            'createdBy' => $currentUserId,
        ]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    $idSnapshot = (int) ($input['idSnapshot'] ?? 0);
    if ($idSnapshot <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Snapshot is verplicht.']);
        exit;
    }

    $snapshot = dbOne(
        $pdo,
        "SELECT ces.*, e.dateEnd
           FROM tblCharacterEconomySnapshot AS ces
           JOIN tblEvent AS e
             ON e.id = ces.idEvent
          WHERE ces.id = :idSnapshot
            AND ces.idCharacter = :idCharacter",
        [
            'idSnapshot' => $idSnapshot,
            'idCharacter' => $idCharacter,
        ]
    );

    if ($snapshot === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Snapshot niet gevonden.']);
        exit;
    }

    if ($action === 'reroll_snapshot') {
        if (trim((string) ($snapshot['securitiesStatus'] ?? 'none')) !== 'pending') {
            http_response_code(400);
            echo json_encode(['error' => 'Alleen wachtende snapshots kunnen opnieuw gerold worden.']);
            exit;
        }

        $variationLimitPercentage = round((float) ($snapshot['securitiesVariationLimitPercentage'] ?? 0), 2);
        $variationPercentage = generateCharacterSecuritiesVariationPercentage($variationLimitPercentage);
        $basePercentage = round((float) ($snapshot['securitiesBasePercentage'] ?? 0), 2);
        $returnPercentage = round($basePercentage + $variationPercentage, 2);
        $balanceSnapshot = round((float) ($snapshot['securitiesBalanceSnapshot'] ?? 0), 2);
        $returnAmount = round($balanceSnapshot * ($returnPercentage / 100), 2);

        $pdo->prepare(
            'UPDATE tblCharacterEconomySnapshot
                SET securitiesVariationPercentage = :variationPercentage,
                    securitiesReturnPercentage = :returnPercentage,
                    securitiesReturnAmount = :returnAmount
              WHERE id = :idSnapshot'
        )->execute([
            'variationPercentage' => $variationPercentage,
            'returnPercentage' => $returnPercentage,
            'returnAmount' => $returnAmount,
            'idSnapshot' => $idSnapshot,
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'approve_snapshot') {
        if (trim((string) ($snapshot['securitiesStatus'] ?? 'none')) !== 'pending') {
            http_response_code(400);
            echo json_encode(['error' => 'Deze snapshot wacht niet meer op goedkeuring.']);
            exit;
        }

        $returnAmount = round((float) ($snapshot['securitiesReturnAmount'] ?? 0), 2);

        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE tblCharacterEconomySnapshot
                SET securitiesStatus = :status,
                    securitiesApprovedAt = NOW(),
                    securitiesApprovedBy = :approvedBy
              WHERE id = :idSnapshot'
        )->execute([
            'status' => 'approved',
            'approvedBy' => $currentUserId,
            'idSnapshot' => $idSnapshot,
        ]);

        if ($returnAmount !== 0.0) {
            $pdo->prepare(
                'UPDATE tblCharacter
                    SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :amount, 2)
                  WHERE id = :idCharacter'
            )->execute([
                'amount' => $returnAmount,
                'idCharacter' => $idCharacter,
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    if (trim((string) ($snapshot['securitiesStatus'] ?? 'none')) !== 'approved') {
        http_response_code(400);
        echo json_encode(['error' => 'Alleen goedgekeurde snapshots laten een opname toe.']);
        exit;
    }

    if (round((float) ($snapshot['securitiesSnapshotWithdrawalAmount'] ?? 0), 2) > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Voor deze snapshot werd al een opname uitgevoerd.']);
        exit;
    }

    $dateEnd = trim((string) ($snapshot['dateEnd'] ?? ''));
    if ($dateEnd !== '') {
        try {
            if ((new DateTimeImmutable($dateEnd)) < new DateTimeImmutable('today')) {
                http_response_code(400);
                echo json_encode(['error' => 'De opnameperiode voor deze snapshot is afgelopen.']);
                exit;
            }
        } catch (Throwable $e) {
            // Ignore malformed event dates and keep the action available.
        }
    }

    $amount = round((float) ($input['amount'] ?? 0), 2);
    if ($amount <= 0 || $amount > 30000) {
        http_response_code(400);
        echo json_encode(['error' => 'Je kan per snapshot maximaal 30000 Fr opnemen.']);
        exit;
    }

    $portfolioBalance = round((float) ($character['securitiesaccount'] ?? 0), 2);
    if ($amount > $portfolioBalance) {
        http_response_code(400);
        echo json_encode(['error' => 'Onvoldoende saldo in de effectenportefeuille.']);
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE tblCharacterEconomySnapshot
            SET securitiesSnapshotWithdrawalAmount = :amount
          WHERE id = :idSnapshot'
    )->execute([
        'amount' => $amount,
        'idSnapshot' => $idSnapshot,
    ]);

    $pdo->prepare(
        'UPDATE tblCharacter
            SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :bankAddAmount, 2),
                securitiesaccount = ROUND(COALESCE(securitiesaccount, 0) - :securitiesSubtractAmount, 2)
          WHERE id = :idCharacter'
    )->execute([
        'bankAddAmount' => $amount,
        'securitiesSubtractAmount' => $amount,
        'idCharacter' => $idCharacter,
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de effectenportefeuille niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
