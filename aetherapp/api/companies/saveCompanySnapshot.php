<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/../characters/companyShareUtils.php';

function buildCompanySnapshotResponse(PDO $pdo, int $idCompany): array
{
    $company = getCompanyDetailData($pdo, $idCompany);
    if ($company === null) {
        return [
            'success' => true,
            'company' => null,
            'availableSharePercentage' => 0,
            'snapshotEventOptions' => [],
            'snapshots' => [],
        ];
    }

    return [
        'success' => true,
        'company' => $company,
        'availableSharePercentage' => (int) ($company['availableSharePercentage'] ?? 0),
        'snapshotEventOptions' => (array) ($company['snapshotEventOptions'] ?? []),
        'snapshots' => (array) ($company['snapshots'] ?? []),
    ];
}

function getCompanySnapshotDividendPerPercentage(float $profitAmount): float
{
    if ($profitAmount <= 0) {
        return 0.0;
    }

    return round($profitAmount / 110, 2);
}

function applyCompanyValueDeltaAndRemap(PDO $pdo, int $idCompany, float $delta): float
{
    $company = dbOne(
        $pdo,
        'SELECT id, companyValue
           FROM tblCompany
          WHERE id = :idCompany',
        ['idCompany' => $idCompany]
    );

    if ($company === null) {
        throw new RuntimeException('Bedrijf niet gevonden.');
    }

    $currentCompanyValue = round((float) ($company['companyValue'] ?? 0), 2);
    $nextCompanyValue = normalizeCompanyValue($currentCompanyValue + $delta);
    $actualDelta = round($nextCompanyValue - $currentCompanyValue, 2);
    $previousCompanyTypeKey = getCompanyTypeByValue($currentCompanyValue)['key'] ?? null;
    $nextCompanyTypeKey = getCompanyTypeByValue($nextCompanyValue)['key'] ?? null;

    $stmt = $pdo->prepare(
        'UPDATE tblCompany
            SET companyValue = :companyValue,
                updatedAt = CURRENT_TIMESTAMP
          WHERE id = :idCompany'
    );
    $stmt->execute([
        'companyValue' => $nextCompanyValue,
        'idCompany' => $idCompany,
    ]);

    if (
        $previousCompanyTypeKey !== null
        && $nextCompanyTypeKey !== null
        && $previousCompanyTypeKey !== $nextCompanyTypeKey
    ) {
        remapCompanyShareTraitsForCompany($pdo, $idCompany, $nextCompanyTypeKey);
    }

    return $actualDelta;
}

function createCompanySnapshotDividendPayouts(PDO $pdo, array $snapshot, int $idCompany, float $dividendPerPercentage): float
{
    if ($dividendPerPercentage <= 0) {
        return 0.0;
    }

    $payoutEntries = getCompanyShareholderPayoutEntries($pdo, $idCompany);
    if (count($payoutEntries) === 0) {
        return 0.0;
    }

    $snapshotId = (int) ($snapshot['id'] ?? 0);
    $eventTitle = trim((string) ($snapshot['title'] ?? ''));
    $companyName = trim((string) ($snapshot['companyName'] ?? ''));
    $transactionDate = trim((string) ($snapshot['dateStart'] ?? ''));
    $description = $eventTitle !== ''
        ? 'Aether - ' . $eventTitle
        : 'Aether';

    $updateCharacterStmt = $pdo->prepare(
        'UPDATE tblCharacter
            SET bankaccount = ROUND(COALESCE(bankaccount, 0) + :amount, 2)
          WHERE id = :idCharacter'
    );
    $insertPayoutStmt = $pdo->prepare(
        'INSERT INTO tblCompanySnapshotPayout
            (idCompanySnapshot, idCharacter, amount, transactionDate, description)
         VALUES
            (:idCompanySnapshot, :idCharacter, :amount, :transactionDate, :description)'
    );

    $paidOutTotal = 0.0;
    foreach ($payoutEntries as $entry) {
        $idCharacter = (int) ($entry['idCharacter'] ?? 0);
        $percentage = max(0, (int) ($entry['percentage'] ?? 0));
        if ($idCharacter <= 0 || $percentage <= 0) {
            continue;
        }

        $amount = round($dividendPerPercentage * $percentage, 2);
        if ($amount <= 0) {
            continue;
        }

        $updateCharacterStmt->execute([
            'amount' => $amount,
            'idCharacter' => $idCharacter,
        ]);
        $insertPayoutStmt->execute([
            'idCompanySnapshot' => $snapshotId,
            'idCharacter' => $idCharacter,
            'amount' => $amount,
            'transactionDate' => $transactionDate !== '' ? $transactionDate : date('Y-m-d'),
            'description' => $description,
        ]);
        $paidOutTotal += $amount;
    }

    return round($paidOutTotal, 2);
}

function reverseCompanySnapshotDividendPayouts(PDO $pdo, int $idCompanySnapshot): void
{
    $payouts = dbAll(
        $pdo,
        'SELECT id, idCharacter, amount
           FROM tblCompanySnapshotPayout
          WHERE idCompanySnapshot = :idCompanySnapshot',
        ['idCompanySnapshot' => $idCompanySnapshot]
    );

    if (count($payouts) === 0) {
        return;
    }

    $updateCharacterStmt = $pdo->prepare(
        'UPDATE tblCharacter
            SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
          WHERE id = :idCharacter'
    );
    foreach ($payouts as $payout) {
        $idCharacter = (int) ($payout['idCharacter'] ?? 0);
        $amount = round((float) ($payout['amount'] ?? 0), 2);
        if ($idCharacter <= 0 || $amount <= 0) {
            continue;
        }

        $updateCharacterStmt->execute([
            'amount' => $amount,
            'idCharacter' => $idCharacter,
        ]);
    }

    $deleteStmt = $pdo->prepare(
        'DELETE FROM tblCompanySnapshotPayout
          WHERE idCompanySnapshot = :idCompanySnapshot'
    );
    $deleteStmt->execute(['idCompanySnapshot' => $idCompanySnapshot]);
}

function reverseCompanySnapshotEffects(PDO $pdo, array $snapshot, int $idCompany): void
{
    $appliedAction = trim((string) ($snapshot['appliedAction'] ?? 'none'));
    if ($appliedAction === '' || $appliedAction === 'none') {
        return;
    }

    $snapshotId = (int) ($snapshot['id'] ?? 0);
    if ($appliedAction === 'dividend') {
        reverseCompanySnapshotDividendPayouts($pdo, $snapshotId);
    }

    $companyValueDelta = round((float) ($snapshot['companyValueDelta'] ?? 0), 2);
    if ($companyValueDelta !== 0.0) {
        applyCompanyValueDeltaAndRemap($pdo, $idCompany, -$companyValueDelta);
    }
}

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$action = trim((string) ($postData['action'] ?? ''));
$applyAction = trim((string) ($postData['applyAction'] ?? ''));
$idCompany = (int) ($postData['idCompany'] ?? 0);
$idCompanySnapshot = (int) ($postData['idCompanySnapshot'] ?? 0);
$idEvent = (int) ($postData['idEvent'] ?? 0);
$stability = normalizeCompanySliderValue($postData['stability'] ?? 0);
$profitability = normalizeCompanySliderValue($postData['profitability'] ?? 0);

if ($idCompany <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig bedrijf ID.']);
    exit;
}

if (!in_array($action, ['create', 'update', 'recalculate', 'apply', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige snapshot-actie.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $company = dbOne(
        $pdo,
        'SELECT id, companyName, companyValue
           FROM tblCompany
          WHERE id = :idCompany',
        ['idCompany' => $idCompany]
    );

    if ($company === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
        exit;
    }

    if ($action === 'create') {
        if ($idEvent <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Kies eerst een event.']);
            exit;
        }

        $event = dbOne(
            $pdo,
            'SELECT id
               FROM tblEvent
              WHERE id = :idEvent',
            ['idEvent' => $idEvent]
        );

        if ($event === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Event niet gevonden.']);
            exit;
        }

        $existing = dbOne(
            $pdo,
            'SELECT id
               FROM tblCompanySnapshot
              WHERE idCompany = :idCompany
                AND idEvent = :idEvent',
            [
                'idCompany' => $idCompany,
                'idEvent' => $idEvent,
            ]
        );

        if ($existing !== null) {
            http_response_code(400);
            echo json_encode(['error' => 'Voor dit event bestaat al een snapshot.']);
            exit;
        }

        $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
        $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
        $financials = calculateCompanySnapshotFinancials(
            (float) ($company['companyValue'] ?? 0),
            $stability,
            $profitability,
            (float) ($personnelImpactSummary['totalPercentage'] ?? 0),
            $personnelSalaryIncreaseExpenseAmount,
            (float) ($personnelImpactSummary['lowerBoundPercentage'] ?? 0),
            (float) ($personnelImpactSummary['upperBoundPercentage'] ?? 0)
        );

        $stmt = $pdo->prepare(
            'INSERT INTO tblCompanySnapshot (
                idCompany,
                idEvent,
                companyValue,
                stability,
                profitability,
                appliedAction,
                companyValueDelta,
                personnelImpactPercentage,
                stabilityLowerBoundPercentage,
                stabilityUpperBoundPercentage,
                profitAmount,
                baseProfitAmount,
                stabilityAdjustmentAmount
             ) VALUES (
                :idCompany,
                :idEvent,
                :companyValue,
                :stability,
                :profitability,
                :appliedAction,
                :companyValueDelta,
                :personnelImpactPercentage,
                :stabilityLowerBoundPercentage,
                :stabilityUpperBoundPercentage,
                :profitAmount,
                :baseProfitAmount,
                :stabilityAdjustmentAmount
             )'
        );
        $stmt->execute([
            'idCompany' => $idCompany,
            'idEvent' => $idEvent,
            'companyValue' => $financials['companyValue'],
            'stability' => $stability,
            'profitability' => $profitability,
            'appliedAction' => 'none',
            'companyValueDelta' => 0,
            'personnelImpactPercentage' => $financials['personnelImpactPercentage'],
            'stabilityLowerBoundPercentage' => $financials['stabilityLowerBoundPercentage'],
            'stabilityUpperBoundPercentage' => $financials['stabilityUpperBoundPercentage'],
            'profitAmount' => $financials['profitAmount'],
            'baseProfitAmount' => $financials['baseProfitAmount'],
            'stabilityAdjustmentAmount' => $financials['stabilityAdjustmentAmount'],
        ]);
    } elseif ($action === 'update' || $action === 'recalculate' || $action === 'apply' || $action === 'delete') {
        if ($idCompanySnapshot <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Geen geldige snapshot geselecteerd.']);
            exit;
        }

        $snapshot = dbOne(
            $pdo,
            'SELECT
                cs.id,
                cs.idCompany,
                cs.idEvent,
                cs.companyValue,
                cs.stability,
                cs.profitability,
                cs.appliedAction,
                cs.companyValueDelta,
                cs.personnelImpactPercentage,
                cs.stabilityLowerBoundPercentage,
                cs.stabilityUpperBoundPercentage,
                cs.profitAmount,
                e.title,
                e.dateStart
               FROM tblCompanySnapshot AS cs
               JOIN tblEvent AS e
                 ON e.id = cs.idEvent
              WHERE cs.id = :idCompanySnapshot
                AND cs.idCompany = :idCompany',
            [
                'idCompanySnapshot' => $idCompanySnapshot,
                'idCompany' => $idCompany,
            ]
        );

        if ($snapshot === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Snapshot niet gevonden.']);
            exit;
        }

        $appliedActionCurrent = trim((string) ($snapshot['appliedAction'] ?? 'none'));

        if ($action === 'update' || $action === 'recalculate') {
            if ($appliedActionCurrent !== '' && $appliedActionCurrent !== 'none') {
                http_response_code(400);
                echo json_encode(['error' => 'Een toegepaste snapshot kan niet meer aangepast of herberekend worden.']);
                exit;
            }

            if ($action === 'update') {
                $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
                $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
                $financials = calculateCompanySnapshotFinancials(
                    (float) ($snapshot['companyValue'] ?? 0),
                    $stability,
                    $profitability,
                    (float) ($personnelImpactSummary['totalPercentage'] ?? 0),
                    $personnelSalaryIncreaseExpenseAmount,
                    (float) ($personnelImpactSummary['lowerBoundPercentage'] ?? 0),
                    (float) ($personnelImpactSummary['upperBoundPercentage'] ?? 0)
                );
            } else {
                $resolvedStability = normalizeCompanySliderValue($snapshot['stability'] ?? 0);
                $resolvedProfitability = normalizeCompanySliderValue($snapshot['profitability'] ?? 0);
                $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $resolvedStability);
                $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
                $financials = calculateCompanySnapshotFinancials(
                    (float) ($snapshot['companyValue'] ?? 0),
                    $resolvedStability,
                    $resolvedProfitability,
                    (float) ($personnelImpactSummary['totalPercentage'] ?? 0),
                    $personnelSalaryIncreaseExpenseAmount,
                    (float) ($personnelImpactSummary['lowerBoundPercentage'] ?? 0),
                    (float) ($personnelImpactSummary['upperBoundPercentage'] ?? 0)
                );
                $stability = $resolvedStability;
                $profitability = $resolvedProfitability;
            }

            $stmt = $pdo->prepare(
                'UPDATE tblCompanySnapshot
                    SET stability = :stability,
                        profitability = :profitability,
                        personnelImpactPercentage = :personnelImpactPercentage,
                        stabilityLowerBoundPercentage = :stabilityLowerBoundPercentage,
                        stabilityUpperBoundPercentage = :stabilityUpperBoundPercentage,
                        profitAmount = :profitAmount,
                        baseProfitAmount = :baseProfitAmount,
                        stabilityAdjustmentAmount = :stabilityAdjustmentAmount,
                        updatedAt = CURRENT_TIMESTAMP
                  WHERE id = :idCompanySnapshot
                    AND idCompany = :idCompany'
            );
            $stmt->execute([
                'stability' => $stability,
                'profitability' => $profitability,
                'personnelImpactPercentage' => $financials['personnelImpactPercentage'],
                'stabilityLowerBoundPercentage' => $financials['stabilityLowerBoundPercentage'],
                'stabilityUpperBoundPercentage' => $financials['stabilityUpperBoundPercentage'],
                'profitAmount' => $financials['profitAmount'],
                'baseProfitAmount' => $financials['baseProfitAmount'],
                'stabilityAdjustmentAmount' => $financials['stabilityAdjustmentAmount'],
                'idCompanySnapshot' => $idCompanySnapshot,
                'idCompany' => $idCompany,
            ]);
        } elseif ($action === 'apply') {
            if ($appliedActionCurrent !== '' && $appliedActionCurrent !== 'none') {
                http_response_code(400);
                echo json_encode(['error' => 'Deze snapshot is al toegepast.']);
                exit;
            }

            if (!in_array($applyAction, ['loss_adjustment', 'reinvest', 'dividend'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldige snapshot-verwerking.']);
                exit;
            }

            $profitAmount = round((float) ($snapshot['profitAmount'] ?? 0), 2);
            $pdo->beginTransaction();

            if ($applyAction === 'loss_adjustment') {
                if ($profitAmount >= 0) {
                    throw new RuntimeException('Deze snapshot bevat geen verlies om te verwerken.');
                }

                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $profitAmount);
            } elseif ($applyAction === 'reinvest') {
                if ($profitAmount <= 0) {
                    throw new RuntimeException('Deze snapshot bevat geen winst om te herinvesteren.');
                }

                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $profitAmount);
            } else {
                if ($profitAmount <= 0) {
                    throw new RuntimeException('Deze snapshot bevat geen winst om dividend uit te betalen.');
                }

                $snapshot['companyName'] = (string) ($company['companyName'] ?? '');
                $dividendPerPercentage = getCompanySnapshotDividendPerPercentage($profitAmount);
                $paidOutTotal = createCompanySnapshotDividendPayouts($pdo, $snapshot, $idCompany, $dividendPerPercentage);
                $retainedAmount = round($profitAmount - $paidOutTotal, 2);
                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $retainedAmount);
            }

            $stmt = $pdo->prepare(
                'UPDATE tblCompanySnapshot
                    SET appliedAction = :appliedAction,
                        companyValueDelta = :companyValueDelta,
                        updatedAt = CURRENT_TIMESTAMP
                  WHERE id = :idCompanySnapshot
                    AND idCompany = :idCompany'
            );
            $stmt->execute([
                'appliedAction' => $applyAction,
                'companyValueDelta' => $companyValueDelta,
                'idCompanySnapshot' => $idCompanySnapshot,
                'idCompany' => $idCompany,
            ]);

            $pdo->commit();
        } else {
            $pdo->beginTransaction();
            reverseCompanySnapshotEffects($pdo, $snapshot, $idCompany);

            $deletePayoutStmt = $pdo->prepare(
                'DELETE FROM tblCompanySnapshotPayout
                  WHERE idCompanySnapshot = :idCompanySnapshot'
            );
            $deletePayoutStmt->execute(['idCompanySnapshot' => $idCompanySnapshot]);

            $stmt = $pdo->prepare(
                'DELETE FROM tblCompanySnapshot
                  WHERE id = :idCompanySnapshot
                    AND idCompany = :idCompany'
            );
            $stmt->execute([
                'idCompanySnapshot' => $idCompanySnapshot,
                'idCompany' => $idCompany,
            ]);
            $pdo->commit();
        }
    }

    echo json_encode(buildCompanySnapshotResponse($pdo, $idCompany));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de bedrijfssnapshot niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
