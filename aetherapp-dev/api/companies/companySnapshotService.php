<?php
declare(strict_types=1);

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/../characters/companyShareUtils.php';
require_once __DIR__ . '/../characters/characterFinanceService.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/../shares/shareAccess.php';
require_once __DIR__ . '/../shares/shareService.php';
require_once __DIR__ . '/../shares/shareDividendRules.php';
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

function applyCompanyValueDeltaAndRemap(PDO $pdo, int $idCompany, mixed $delta,
                                       int $actorUserId, int $snapshotId,
                                       string $reason): string
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
    if (aetherDecimalCompare($nextCompanyValue, '9999999999.99') > 0) {
        throw new AetherCompanyFinanceException(409, 'De nieuwe bedrijfswaarde is te groot.');
    }
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

    if (aetherShareLegacyWritesClosed($pdo)) {
        aetherShareInsertValueHistory($pdo, $idCompany, null, $actorUserId,
            $reason, $currentCompanyValue, $nextCompanyValue,
            json_encode(['idSnapshot' => $snapshotId], JSON_THROW_ON_ERROR));
        aetherShareRevalue($pdo, aetherShareHolderIds($pdo, $idCompany));
    } elseif (
        $previousCompanyTypeKey !== null
        && $nextCompanyTypeKey !== null
        && $previousCompanyTypeKey !== $nextCompanyTypeKey
    ) {
        remapCompanyShareTraitsForCompany($pdo, $idCompany, $nextCompanyTypeKey);
    }

    return $actualDelta;
}

function createCompanySnapshotDividendPayouts(PDO $pdo, array $snapshot, int $idCompany, string $profitAmount): string
{
    $pool = aetherShareDividendPool($profitAmount);
    if (aetherDecimalCompare($pool, '0.00') <= 0) {
        return '0.00';
    }
    $holdings = [];
    if (aetherShareLegacyWritesClosed($pdo)) {
        $stmt = $pdo->prepare('SELECT idCharacter, shareCount FROM tblCompanyShareHolding
                               WHERE idCompany = :idCompany ORDER BY idCharacter');
        $stmt->execute(['idCompany' => $idCompany]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $holdings[(int) $row['idCharacter']] = (int) $row['shareCount'];
        }
        $stmt = $pdo->prepare('SELECT shareCount FROM tblCompanyAnonymousShares WHERE idCompany = :idCompany');
        $stmt->execute(['idCompany' => $idCompany]);
        $anonymous = (int) ($stmt->fetchColumn() ?: 0);
    } else {
        foreach (getCompanyShareholderPayoutEntries($pdo, $idCompany) as $row) {
            $holdings[(int) $row['idCharacter']] = (int) $row['percentage'];
        }
        $anonymous = max(0, 100 - array_sum($holdings));
        aetherFinanceLockCharacters($pdo, array_keys($holdings));
    }
    $allocation = aetherShareDividendAllocation($pool, $holdings, $anonymous);

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

    $paidOutMinor = 0;
    foreach ($allocation['payouts'] as $idCharacter => $amount) {
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

function reverseCompanySnapshotEffects(PDO $pdo, array $snapshot, int $idCompany, int $actorUserId): void
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
        applyCompanyValueDeltaAndRemap($pdo, $idCompany,
            aetherMinorUnitsToDecimal(-aetherDecimalToMinorUnits($companyValueDelta)),
            $actorUserId, $snapshotId, 'snapshot_reversal');
    }
}

/** Correct recorded parameters without replaying financial effects already posted. */
function aetherCorrectCompanySnapshot(PDO $pdo, array $company, array $snapshot,
                                      array $input, int $actorUserId): void
{
    $idCompany = (int) $company['id'];
    $idSnapshot = (int) $snapshot['id'];
    $keys = ['companyValue', 'issuedShareCount',
             'anonymousShareCount', 'shareMarketPrice', 'stability', 'profitability'];
    $provided = array_intersect_key($input, array_flip($keys));
    if ($provided === []) throw new AetherCompanyFinanceException(400, 'Geen snapshotgegevens om bij te werken.');
    $next = [];
    foreach ($keys as $key) $next[$key] = array_key_exists($key, $provided) ? $provided[$key] : $snapshot[$key];
    if ($next['issuedShareCount'] !== null && $next['anonymousShareCount'] !== null
        && (int) $next['anonymousShareCount'] > (int) $next['issuedShareCount']) {
        throw new AetherCompanyFinanceException(409, 'Meer anonieme dan uitgegeven aandelen.');
    }

    // Preserve trades and other mutations made after this snapshot by applying only its correction delta.
    $latest = aetherLatestCompanySnapshotId($pdo, $idCompany) === $idSnapshot;
    if ($latest) {
        $stock = aetherShareStock($pdo, $idCompany, (int) $company['issuedShareCount']);
        $newIssued = $stock['issued'];
        if (array_key_exists('issuedShareCount', $provided)) {
            $newIssued = $snapshot['issuedShareCount'] === null
                ? (int) $next['issuedShareCount']
                : $stock['issued'] + (int) $next['issuedShareCount'] - (int) $snapshot['issuedShareCount'];
        }
        $newAnonymous = $stock['anonymous'];
        if (array_key_exists('anonymousShareCount', $provided)) {
            $newAnonymous = $snapshot['anonymousShareCount'] === null
                ? (int) $next['anonymousShareCount']
                : $stock['anonymous'] + (int) $next['anonymousShareCount'] - (int) $snapshot['anonymousShareCount'];
        }
        if ($newAnonymous < 0 || $newIssued < 1 || $newIssued < $stock['held'] + $newAnonymous) {
            throw new AetherCompanyFinanceException(409, 'De correctie laat te weinig vrije aandelen over.');
        }
        if ($newIssued !== $stock['issued']) {
            $stmt = $pdo->prepare('UPDATE tblCompany SET issuedShareCount = :issued WHERE id = :idCompany');
            $stmt->execute(['issued' => $newIssued, 'idCompany' => $idCompany]);
        }
        if ($newAnonymous !== $stock['anonymous']) {
            $stmt = $pdo->prepare('INSERT INTO tblCompanyAnonymousShares (idCompany, shareCount)
                VALUES (:idCompany, :shareCount)
                ON DUPLICATE KEY UPDATE shareCount = VALUES(shareCount)');
            $stmt->execute(['idCompany' => $idCompany, 'shareCount' => $newAnonymous]);
        }
        $valueDelta = aetherDecimalSubtract((string) $next['companyValue'], (string) $snapshot['companyValue']);
        if (aetherDecimalCompare($valueDelta, '0.00') !== 0) {
            if (aetherDecimalCompare(aetherDecimalAdd((string) $company['companyValue'], $valueDelta), '0.00') < 0) {
                throw new AetherCompanyFinanceException(409,
                    'De correctie zou de actuele bedrijfswaarde negatief maken.');
            }
            applyCompanyValueDeltaAndRemap($pdo, $idCompany, $valueDelta,
                $actorUserId, $idSnapshot, 'snapshot_value_correction');
        } elseif ($newIssued !== $stock['issued']) {
            aetherShareRevalue($pdo, aetherShareHolderIds($pdo, $idCompany));
        }
        if (array_key_exists('stability', $provided) || array_key_exists('profitability', $provided)) {
            $stmt = $pdo->prepare('UPDATE tblCompany SET stability = :stability,
                profitability = :profitability WHERE id = :idCompany');
            $stmt->execute(['stability' => $next['stability'], 'profitability' => $next['profitability'],
                            'idCompany' => $idCompany]);
        }
    }

    $params = ['idSnapshot' => $idSnapshot, 'idCompany' => $idCompany];
    foreach ($keys as $key) $params[$key] = $next[$key];
    $set = implode(', ', array_map(static fn(string $key): string => "$key = :$key", $keys));
    // Posted dividends and profit/loss remain historical transactions; only unposted outcomes can be rerolled.
    $applied = !in_array(trim((string) $snapshot['appliedAction']), ['', 'none'], true);
    if (!$applied && array_intersect(array_keys($provided), ['companyValue', 'stability', 'profitability']) !== []) {
        $stability = (int) $next['stability'];
        $personnelImpact = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
        $salaryExpense = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
        $financials = calculateCompanySnapshotFinancials($next['companyValue'], $stability,
            (int) $next['profitability'], $personnelImpact['totalPercentage'] ?? 0, $salaryExpense,
            $personnelImpact['lowerBoundPercentage'] ?? 0, $personnelImpact['upperBoundPercentage'] ?? 0);
        foreach (['personnelImpactPercentage', 'stabilityLowerBoundPercentage',
                  'stabilityUpperBoundPercentage', 'profitAmount', 'baseProfitAmount',
                  'stabilityAdjustmentAmount'] as $field) {
            $set .= ", $field = :$field";
            $params[$field] = $financials[$field];
        }
    }
    $stmt = $pdo->prepare('UPDATE tblCompanySnapshot SET ' . $set . ', updatedAt = CURRENT_TIMESTAMP
        WHERE id = :idSnapshot AND idCompany = :idCompany');
    $stmt->execute($params);
    if (!$latest && array_key_exists('companyValue', $provided)
        && aetherDecimalCompare((string) $next['companyValue'], (string) $snapshot['companyValue']) !== 0) {
        $liveValue = aetherFinanceDecimal($company['companyValue']);
        aetherShareInsertValueHistory($pdo, $idCompany, null, $actorUserId,
            'historical_snapshot_correction', $liveValue, $liveValue,
            json_encode(['idSnapshot' => $idSnapshot,
                'previousRecordedValue' => $snapshot['companyValue'],
                'newRecordedValue' => $next['companyValue']], JSON_THROW_ON_ERROR));
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
    if (!aetherCompanySnapshotHistoryAvailable($pdo)) {
        throw new AetherCompanyFinanceException(503, 'Voer eerst de snapshotgeschiedenismigratie uit.');
    }
    $operation = 'company.snapshot.' . $action . ($action === 'apply' ? '.' . $applyAction : '');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    $claim = aetherClaimIdempotency($pdo, $currentUser, $operation, $requestKey, $input);
    $company = dbOne(
        $pdo,
        'SELECT id, companyName, companyValue, issuedShareCount,
                stability, profitability
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
    if (in_array($action, ['update', 'apply', 'delete'], true) && aetherShareLegacyWritesClosed($pdo)) {
        $affected = aetherShareHolderIds($pdo, $idCompany);
        if ($action === 'delete') {
            $payoutCharacters = dbAll(
                $pdo,
                'SELECT p.idCharacter
                   FROM tblCompanySnapshotPayout AS p
                   JOIN tblCompanySnapshot AS s ON s.id = p.idCompanySnapshot
                  WHERE s.id = :idCompanySnapshot AND s.idCompany = :idCompany',
                ['idCompanySnapshot' => $idCompanySnapshot, 'idCompany' => $idCompany]
            );
            $affected = array_merge($affected, array_column($payoutCharacters, 'idCharacter'));
        }
        aetherFinanceLockCharacters($pdo, $affected);
    }

    if ($action === 'create') {
        if ($idEvent <= 0) {
            throw new AetherCompanyFinanceException(400, 'Kies eerst een event.');
        }

        $event = dbOne(
            $pdo,
            'SELECT id, dateStart
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

        // Historical events may be filled in later. Derive their opening figures from
        // the preceding snapshot, never from the company's current live balance.
        $previousSnapshot = dbOne($pdo,
            'SELECT cs.companyValue, cs.shareMarketPrice,
                    cs.issuedShareCount, cs.anonymousShareCount
               FROM tblCompanySnapshot AS cs
               JOIN tblEvent AS e ON e.id = cs.idEvent
              WHERE cs.idCompany = :idCompany
                AND (e.dateStart < :dateStart
                     OR (e.dateStart = :sameDate AND e.id < :idEvent))
              ORDER BY e.dateStart DESC, e.id DESC, cs.id DESC LIMIT 1',
            ['idCompany' => $idCompany, 'dateStart' => $event['dateStart'],
             'sameDate' => $event['dateStart'], 'idEvent' => $idEvent]);
        $foundation = dbOne($pdo,
            'SELECT initialCompanyValue, shareMarketPrice
               FROM tblCompanyFoundationSnapshot WHERE idCompany = :idCompany',
            ['idCompany' => $idCompany]);
        $newerSnapshot = dbOne($pdo,
            'SELECT cs.id FROM tblCompanySnapshot AS cs
               JOIN tblEvent AS e ON e.id = cs.idEvent
              WHERE cs.idCompany = :idCompany
                AND (e.dateStart > :dateStart
                     OR (e.dateStart = :sameDate AND e.id > :idEvent))
              LIMIT 1',
            ['idCompany' => $idCompany, 'dateStart' => $event['dateStart'],
             'sameDate' => $event['dateStart'], 'idEvent' => $idEvent]);

        $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
        $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
        $financials = calculateCompanySnapshotFinancials(
            $newerSnapshot === null ? ($company['companyValue'] ?? 0)
                : ($previousSnapshot['companyValue'] ?? $foundation['initialCompanyValue'] ?? 0),
            $stability,
            $profitability,
            $personnelImpactSummary['totalPercentage'] ?? 0,
            $personnelSalaryIncreaseExpenseAmount,
            $personnelImpactSummary['lowerBoundPercentage'] ?? 0,
            $personnelImpactSummary['upperBoundPercentage'] ?? 0
        );

        $stock = $newerSnapshot === null
            ? aetherShareStock($pdo, $idCompany, (int) $company['issuedShareCount']) : null;
        $marketPrice = $previousSnapshot === null
            ? ($foundation['shareMarketPrice'] ?? null)
            : $previousSnapshot['shareMarketPrice'];

        $stmt = $pdo->prepare(
            'INSERT INTO tblCompanySnapshot (
                idCompany,
                idEvent,
                companyValue,
                issuedShareCount,
                anonymousShareCount,
                shareMarketPrice,
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
                :issuedShareCount,
                :anonymousShareCount,
                :shareMarketPrice,
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
            'issuedShareCount' => $stock === null ? ($previousSnapshot['issuedShareCount'] ?? null)
                : $company['issuedShareCount'],
            'anonymousShareCount' => $stock === null ? ($previousSnapshot['anonymousShareCount'] ?? null)
                : $stock['anonymous'],
            'shareMarketPrice' => $marketPrice,
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
        if ($newerSnapshot === null) {
            $stmt = $pdo->prepare('UPDATE tblCompany SET stability = :stability,
                profitability = :profitability WHERE id = :idCompany');
            $stmt->execute(['stability' => $stability, 'profitability' => $profitability,
                            'idCompany' => $idCompany]);
        }
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
                cs.issuedShareCount,
                cs.anonymousShareCount,
                cs.shareMarketPrice,
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

        if ($action === 'update') {
            aetherCorrectCompanySnapshot($pdo, $company, $snapshot, $input, (int) $currentUser['id']);
        } elseif ($action === 'recalculate') {
            if ($appliedActionCurrent !== '' && $appliedActionCurrent !== 'none') {
                throw new AetherCompanyFinanceException(400, 'Een toegepaste snapshot kan niet opnieuw berekend worden.');
            }
            $stability = normalizeCompanySliderValue($snapshot['stability'] ?? 0);
            $profitability = normalizeCompanySliderValue($snapshot['profitability'] ?? 0);
            $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
            $salaryExpense = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
            $financials = calculateCompanySnapshotFinancials(
                $snapshot['companyValue'] ?? 0, $stability, $profitability,
                $personnelImpactSummary['totalPercentage'] ?? 0, $salaryExpense,
                $personnelImpactSummary['lowerBoundPercentage'] ?? 0,
                $personnelImpactSummary['upperBoundPercentage'] ?? 0
            );
            $stmt = $pdo->prepare(
                'UPDATE tblCompanySnapshot SET
                    personnelImpactPercentage = :personnelImpactPercentage,
                    stabilityLowerBoundPercentage = :stabilityLowerBoundPercentage,
                    stabilityUpperBoundPercentage = :stabilityUpperBoundPercentage,
                    profitAmount = :profitAmount,
                    baseProfitAmount = :baseProfitAmount,
                    stabilityAdjustmentAmount = :stabilityAdjustmentAmount,
                    updatedAt = CURRENT_TIMESTAMP
                 WHERE id = :idCompanySnapshot AND idCompany = :idCompany'
            );
            $stmt->execute([
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

                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $profitAmount,
                    (int) $currentUser['id'], $idCompanySnapshot, 'snapshot_loss');
            } elseif ($applyAction === 'reinvest') {
                if (aetherDecimalCompare($profitAmount, '0.00') <= 0) {
                    throw new AetherCompanyFinanceException(400, 'Deze snapshot bevat geen winst om te herinvesteren.');
                }

                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $profitAmount,
                    (int) $currentUser['id'], $idCompanySnapshot, 'snapshot_reinvestment');
            } else {
                if (aetherDecimalCompare($profitAmount, '0.00') <= 0) {
                    throw new AetherCompanyFinanceException(400, 'Deze snapshot bevat geen winst om dividend uit te betalen.');
                }

                $snapshot['companyName'] = (string) ($company['companyName'] ?? '');
                $paidOutTotal = createCompanySnapshotDividendPayouts($pdo, $snapshot, $idCompany, $profitAmount);
                $retainedAmount = aetherDecimalSubtract($profitAmount, $paidOutTotal);
                $companyValueDelta = applyCompanyValueDeltaAndRemap($pdo, $idCompany, $retainedAmount,
                    (int) $currentUser['id'], $idCompanySnapshot, 'snapshot_dividend_remainder');
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
            if (aetherShareLegacyWritesClosed($pdo)) {
                $stmt = $pdo->prepare('SELECT 1 FROM tblShareSearchAssessment
                                       WHERE idSnapshot = :idSnapshot LIMIT 1');
                $stmt->execute(['idSnapshot' => $idCompanySnapshot]);
                if ($stmt->fetchColumn() !== false) {
                    throw new AetherCompanyFinanceException(409,
                        'Deze snapshot heeft historische aandelenzoekresultaten en kan niet verwijderd worden.');
                }
                $stmt = $pdo->prepare('SELECT 1 FROM tblShareOffer
                                       WHERE idSnapshot = :idSnapshot LIMIT 1');
                $stmt->execute(['idSnapshot' => $idCompanySnapshot]);
                if ($stmt->fetchColumn() !== false) {
                    throw new AetherCompanyFinanceException(409,
                        'Deze snapshot heeft aandelenaanbiedingen en kan niet verwijderd worden.');
                }
            }
            reverseCompanySnapshotEffects($pdo, $snapshot, $idCompany, (int) $currentUser['id']);

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
