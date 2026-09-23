<?php
declare(strict_types=1);

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/../characters/companyShareUtils.php';
require_once __DIR__ . '/../characters/characterFinanceService.php';
require_once __DIR__ . '/../shared/idempotency.php';
final class AetherCompanyFinanceException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

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

function getCompanySnapshotDividendPerPercentage(mixed $profitAmount): string
{
    $amount = aetherFinanceDecimal($profitAmount);
    if (aetherDecimalCompare($amount, '0.00') <= 0) {
        return '0.00';
    }
    return aetherDecimalMultiplyRatio($amount, 1, 110);
}

function applyCompanyValueDeltaAndRemap(PDO $pdo, int $idCompany, mixed $delta): string
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

    $currentCompanyValue = aetherFinanceDecimal($company['companyValue'] ?? 0);
    $deltaValue = aetherFinanceDecimal($delta);
    $nextCompanyValue = aetherDecimalAdd($currentCompanyValue, $deltaValue);
    if (aetherDecimalCompare($nextCompanyValue, '0.00') < 0) $nextCompanyValue = '0.00';
    $actualDelta = aetherDecimalSubtract($nextCompanyValue, $currentCompanyValue);
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

function createCompanySnapshotDividendPayouts(PDO $pdo, array $snapshot, int $idCompany, string $dividendPerPercentage): string
{
    if (aetherDecimalCompare($dividendPerPercentage, '0.00') <= 0) {
        return '0.00';
    }

    $payoutEntries = getCompanyShareholderPayoutEntries($pdo, $idCompany);
    if (count($payoutEntries) === 0) {
        return '0.00';
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

    $characterIds = array_values(array_unique(array_map(
        static fn(array $entry): int => (int) ($entry['idCharacter'] ?? 0),
        $payoutEntries
    )));
    aetherFinanceLockCharacters($pdo, $characterIds);
    $paidOutMinor = 0;
    foreach ($payoutEntries as $entry) {
        $idCharacter = (int) ($entry['idCharacter'] ?? 0);
        $percentage = max(0, (int) ($entry['percentage'] ?? 0));
        if ($idCharacter <= 0 || $percentage <= 0) {
            continue;
        }

        $amount = aetherDecimalMultiplyRatio($dividendPerPercentage, $percentage, 1);
        if (aetherDecimalCompare($amount, '0.00') <= 0) {
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
        $paidOutMinor += aetherDecimalToMinorUnits($amount);
    }

    return aetherMinorUnitsToDecimal($paidOutMinor);
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

    aetherFinanceLockCharacters($pdo, array_map(
        static fn(array $payout): int => (int) ($payout['idCharacter'] ?? 0),
        $payouts
    ));

    $updateCharacterStmt = $pdo->prepare(
        'UPDATE tblCharacter
            SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
          WHERE id = :idCharacter'
    );
    foreach ($payouts as $payout) {
        $idCharacter = (int) ($payout['idCharacter'] ?? 0);
        $amount = aetherFinanceDecimal($payout['amount'] ?? 0);
        if ($idCharacter <= 0 || aetherDecimalCompare($amount, '0.00') <= 0) {
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

    $companyValueDelta = aetherFinanceDecimal($snapshot['companyValueDelta'] ?? 0);
    if (aetherDecimalCompare($companyValueDelta, '0.00') !== 0) {
        applyCompanyValueDeltaAndRemap($pdo, $idCompany, aetherMinorUnitsToDecimal(-aetherDecimalToMinorUnits($companyValueDelta)));
    }
}

/** Owns the existing snapshot transaction and finance rules. */
function aetherSaveCompanySnapshot(PDO $pdo, array $currentUser, array $input, string $requestKey): array
{
    $action = (string) $input['action'];
    $applyAction = (string) ($input['applyAction'] ?? '');
    $idCompany = (int) $input['idCompany'];
    $idCompanySnapshot = (int) ($input['idCompanySnapshot'] ?? 0);
    $idEvent = (int) ($input['idEvent'] ?? 0);
    $stability = (int) ($input['stability'] ?? 0);
    $profitability = (int) ($input['profitability'] ?? 0);
    $operation = 'company.snapshot.' . $action . ($action === 'apply' ? '.' . $applyAction : '');
    $pdo->beginTransaction();
    $claim = aetherClaimIdempotency($pdo, $currentUser, $operation, $requestKey, $input);
    $company = dbOne(
        $pdo,
        'SELECT id, companyName, companyValue
           FROM tblCompany
          WHERE id = :idCompany
          FOR UPDATE',
        ['idCompany' => $idCompany]
    );

    if ($company === null) {
        throw new AetherCompanyFinanceException(404, 'Bedrijf niet gevonden.');
    }
    if ($claim['replayed']) {
        $pdo->commit();
        return $claim['response'];
    }

    // Dividend- and reversal-character locks precede the concrete snapshot lock.
    // Company/share writers serialize on the company row above.
    if ($action === 'apply' && $applyAction === 'dividend') {
        $shareholders = getCompanyShareholderPayoutEntries($pdo, $idCompany);
        aetherFinanceLockCharacters($pdo, array_column($shareholders, 'idCharacter'));
    } elseif ($action === 'delete') {
        $payoutCharacters = dbAll(
            $pdo,
            'SELECT p.idCharacter
               FROM tblCompanySnapshotPayout AS p
               JOIN tblCompanySnapshot AS s ON s.id = p.idCompanySnapshot
              WHERE s.id = :idCompanySnapshot AND s.idCompany = :idCompany',
            ['idCompanySnapshot' => $idCompanySnapshot, 'idCompany' => $idCompany]
        );
        aetherFinanceLockCharacters($pdo, array_column($payoutCharacters, 'idCharacter'));
    }

    if ($action === 'create') {
        if ($idEvent <= 0) {
            throw new AetherCompanyFinanceException(400, 'Kies eerst een event.');
        }

        $event = dbOne(
            $pdo,
            'SELECT id
               FROM tblEvent
              WHERE id = :idEvent',
            ['idEvent' => $idEvent]
        );

        if ($event === null) {
            throw new AetherCompanyFinanceException(404, 'Event niet gevonden.');
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
            throw new AetherCompanyFinanceException(400, 'Voor dit event bestaat al een snapshot.');
        }

        $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
        $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
        $financials = calculateCompanySnapshotFinancials(
            $company['companyValue'] ?? 0,
            $stability,
            $profitability,
            $personnelImpactSummary['totalPercentage'] ?? 0,
            $personnelSalaryIncreaseExpenseAmount,
            $personnelImpactSummary['lowerBoundPercentage'] ?? 0,
            $personnelImpactSummary['upperBoundPercentage'] ?? 0
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
            throw new AetherCompanyFinanceException(400, 'Geen geldige snapshot geselecteerd.');
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
                AND cs.idCompany = :idCompany
              FOR UPDATE',
            [
                'idCompanySnapshot' => $idCompanySnapshot,
                'idCompany' => $idCompany,
            ]
        );

        if ($snapshot === null) {
            throw new AetherCompanyFinanceException(404, 'Snapshot niet gevonden.');
        }

        $appliedActionCurrent = trim((string) ($snapshot['appliedAction'] ?? 'none'));

        if ($action === 'update' || $action === 'recalculate') {
            if ($appliedActionCurrent !== '' && $appliedActionCurrent !== 'none') {
                throw new AetherCompanyFinanceException(400, 'Een toegepaste snapshot kan niet meer aangepast of herberekend worden.');
            }

            if ($action === 'update') {
                $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
                $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
                $financials = calculateCompanySnapshotFinancials(
                    $snapshot['companyValue'] ?? 0,
                    $stability,
                    $profitability,
                    $personnelImpactSummary['totalPercentage'] ?? 0,
                    $personnelSalaryIncreaseExpenseAmount,
                    $personnelImpactSummary['lowerBoundPercentage'] ?? 0,
                    $personnelImpactSummary['upperBoundPercentage'] ?? 0
                );
            } else {
                $resolvedStability = normalizeCompanySliderValue($snapshot['stability'] ?? 0);
                $resolvedProfitability = normalizeCompanySliderValue($snapshot['profitability'] ?? 0);
                $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $resolvedStability);
                $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
                $financials = calculateCompanySnapshotFinancials(
                    $snapshot['companyValue'] ?? 0,
                    $resolvedStability,
                    $resolvedProfitability,
                    $personnelImpactSummary['totalPercentage'] ?? 0,
                    $personnelSalaryIncreaseExpenseAmount,
                    $personnelImpactSummary['lowerBoundPercentage'] ?? 0,
                    $personnelImpactSummary['upperBoundPercentage'] ?? 0
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
                throw new AetherCompanyFinanceException(400, 'Deze snapshot is al toegepast.');
            }

            if (!in_array($applyAction, ['loss_adjustment', 'reinvest', 'dividend'], true)) {
                throw new AetherCompanyFinanceException(400, 'Ongeldige snapshot-verwerking.');
            }

            $profitAmount = aetherFinanceDecimal($snapshot['profitAmount'] ?? 0);

            if ($applyAction === 'loss_adjustment') {
                if (aetherDecimalCompare($profitAmount, '0.00') >= 0) {
                    throw new AetherCompanyFinanceException(400, 'Deze snapshot bevat geen verlies om te verwerken.');
                }

                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $profitAmount);
            } elseif ($applyAction === 'reinvest') {
                if (aetherDecimalCompare($profitAmount, '0.00') <= 0) {
                    throw new AetherCompanyFinanceException(400, 'Deze snapshot bevat geen winst om te herinvesteren.');
                }

                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $profitAmount);
            } else {
                if (aetherDecimalCompare($profitAmount, '0.00') <= 0) {
                    throw new AetherCompanyFinanceException(400, 'Deze snapshot bevat geen winst om dividend uit te betalen.');
                }

                $snapshot['companyName'] = (string) ($company['companyName'] ?? '');
                $dividendPerPercentage = getCompanySnapshotDividendPerPercentage($profitAmount);
                $paidOutTotal = createCompanySnapshotDividendPayouts($pdo, $snapshot, $idCompany, $dividendPerPercentage);
                $retainedAmount = aetherDecimalSubtract($profitAmount, $paidOutTotal);
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

        } else {
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
        }
    }

    $response = buildCompanySnapshotResponse($pdo, $idCompany);
    aetherCompleteIdempotency($pdo, $currentUser, $operation, $requestKey, $response);
    $pdo->commit();
    return $response;
}
