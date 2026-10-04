<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/decimal.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/characterFinanceRepository.php';

final class AetherFinanceException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message)
    {
        parent::__construct($message);
    }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

function aetherFinanceDecimal(mixed $value): string
{
    if (is_float($value)) {
        return aetherNormalizeDecimal(number_format($value, 2, '.', ''), 2);
    }
    return aetherNormalizeDecimal($value, 2);
}

/** Recheck current access before replaying a stored bank-transfer response. */
function aetherAuthorizeBankTransferReplay(PDO $pdo, array $user, array $input): void
{
    $sourceId = (int) $input['idSourceCharacter'];
    $targetId = (int) $input['idTargetCharacter'];
    $characters = aetherFinanceLockCharacters($pdo, [$sourceId, $targetId]);
    if (!isset($characters[$sourceId], $characters[$targetId])) {
        throw new AetherFinanceException(404, 'Een van de gekozen personages bestaat niet.');
    }
    if (!canTransferFromCharacter($characters[$sourceId], (string) $user['role'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om een overschrijving voor dit personage uit te voeren.');
    }
}

/** Recheck the role required to replay a privileged delete response. */
function aetherAuthorizePrivilegedFinanceReplay(array $user, string $message): void
{
    if (!isPrivilegedUserRole((string) $user['role'])) {
        throw new AetherFinanceException(403, $message);
    }
}

/** Recheck current character access before replaying a snapshot-create response. */
function aetherAuthorizeEconomySnapshotReplay(PDO $pdo, array $user, int $characterId): void
{
    $character = aetherFinanceLockCharacter($pdo, $characterId);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    if (!canManageCharacterEconomySnapshots($character, (string) $user['role'], (int) $user['id'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om een economiesnapshot voor dit personage te maken.');
    }
}

/** Recheck current character access before replaying a securities response. */
function aetherAuthorizeSecuritiesReplay(PDO $pdo, array $user, array $input): void
{
    $character = aetherFinanceLockCharacter($pdo, (int) $input['idCharacter']);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    $action = (string) $input['action'];
    $allowed = in_array($action, ['reroll_snapshot', 'approve_snapshot'], true)
        ? canApproveCharacterSecuritiesSnapshots($character, (string) $user['role'], (int) $user['id'])
        : canManageCharacterSecurities($character, (string) $user['role'], (int) $user['id']);
    if (!$allowed) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om deze effectenportefeuille te beheren.');
    }
}

/** @return array<string, mixed> */
function aetherSaveBankTransfer(PDO $pdo, array $user, array $input): array
{
    $sourceId = (int) $input['idSourceCharacter'];
    $targetId = (int) $input['idTargetCharacter'];
    if ($sourceId === $targetId) {
        throw new AetherFinanceException(400, 'Een overschrijving naar hetzelfde personage is niet toegestaan.');
    }
    $characters = aetherFinanceLockCharacters($pdo, [$sourceId, $targetId]);
    if (!isset($characters[$sourceId], $characters[$targetId])) {
        throw new AetherFinanceException(404, 'Een van de gekozen personages bestaat niet.');
    }
    $source = $characters[$sourceId];
    $target = $characters[$targetId];
    if (!canTransferFromCharacter($source, (string) $user['role'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om een overschrijving voor dit personage uit te voeren.');
    }
    if ((string) $target['state'] === 'draft') {
        throw new AetherFinanceException(400, 'Je kan niet overschrijven naar een personage in draft.');
    }
    if ((string) $target['state'] !== 'active') {
        throw new AetherFinanceException(400, 'Je kan alleen overschrijven naar actieve personages.');
    }
    $amount = (string) $input['amount'];
    if (aetherDecimalCompare($amount, (string) $source['bankaccount']) > 0) {
        throw new AetherFinanceException(400, 'Je kan niet meer geld overschrijven dan het beschikbare saldo op de rekening.');
    }
    $transactionId = aetherFinanceInsertBankTransfer($pdo, [
        'idSourceCharacter' => $sourceId, 'idTargetCharacter' => $targetId,
        'amount' => $amount,
        'transactionDate' => (string) ($input['transactionDate'] !== '' ? $input['transactionDate'] : getDefaultBankTransferDate()),
        'description' => $input['description'] !== '' ? $input['description'] : null,
        'createdBy' => (int) $user['id'],
    ]);
    aetherFinanceAdjustBankBalance($pdo, $sourceId, '-' . $amount);
    aetherFinanceAdjustBankBalance($pdo, $targetId, $amount);
    return ['success' => true, 'idTransaction' => $transactionId];
}

/** @return array<string, mixed> */
function aetherDeleteBankTransfer(PDO $pdo, array $user, int $transactionId): array
{
    if (!isPrivilegedUserRole((string) $user['role'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om verrichtingen te verwijderen.');
    }
    $initial = aetherFinanceFetchBankTransaction($pdo, $transactionId);
    if ($initial === null) {
        throw new AetherFinanceException(404, 'Verrichting niet gevonden.');
    }
    $sourceId = (int) $initial['idSourceCharacter'];
    $targetId = (int) $initial['idTargetCharacter'];
    if (count(aetherFinanceLockCharacters($pdo, [$sourceId, $targetId])) !== 2) {
        throw new AetherFinanceException(409, 'De betrokken personages bestaan niet meer volledig.');
    }
    $transaction = aetherFinanceLockBankTransaction($pdo, $transactionId);
    if ($transaction === null) {
        throw new AetherFinanceException(404, 'Verrichting niet gevonden.');
    }
    $amount = aetherFinanceDecimal($transaction['amount']);
    if ($sourceId <= 0 || $targetId <= 0 || aetherDecimalCompare($amount, '0.00') <= 0) {
        throw new AetherFinanceException(400, 'Deze verrichting kan niet veilig verwijderd worden.');
    }
    aetherFinanceAdjustBankBalance($pdo, $sourceId, $amount);
    aetherFinanceAdjustBankBalance($pdo, $targetId, '-' . $amount);
    aetherFinanceDeleteBankTransaction($pdo, $transactionId);
    return ['success' => true];
}

/** @return array<string, mixed> */
function aetherCreateEconomySnapshot(PDO $pdo, array $user, array $input): array
{
    $characterId = (int) $input['idCharacter'];
    $character = aetherFinanceLockCharacter($pdo, $characterId);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    if (!canManageCharacterEconomySnapshots($character, (string) $user['role'], (int) $user['id'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om een economiesnapshot voor dit personage te maken.');
    }
    $event = aetherFinanceFetchEvent($pdo, (int) $input['idEvent']);
    if ($event === null) throw new AetherFinanceException(404, 'Event niet gevonden.');
    if (aetherFinanceSnapshotExists($pdo, $characterId, (int) $input['idEvent'])) {
        throw new AetherFinanceException(400, 'Voor dit event bestaat al een economiesnapshot van dit personage.');
    }
    $amount = aetherFinanceDecimal(getCharacterEconomySnapshotAmount($pdo, $character));
    $securities = calculateCharacterSecuritiesSnapshotData($pdo, $character);
    $date = trim((string) ($event['dateStart'] ?? '')) ?: getDefaultBankTransferDate();
    aetherFinanceInsertEconomySnapshot($pdo, [
        'idCharacter' => $characterId, 'idEvent' => (int) $input['idEvent'], 'amount' => $amount,
        'transactionDate' => $date,
        'securitiesBalanceSnapshot' => aetherFinanceDecimal($securities['balanceSnapshot'] ?? 0),
        'securitiesManagerType' => $securities['managerType'] ?? 'none',
        'securitiesManagerCharacterId' => $securities['managerCharacterId'] ?? null,
        'securitiesRiskProfile' => $securities['riskProfile'] ?? 3,
        'securitiesManagerSkillLevel' => $securities['managerSkillLevel'] ?? 0,
        'securitiesBasePercentage' => $securities['basePercentage'] ?? 0,
        'securitiesVariationLimitPercentage' => $securities['variationLimitPercentage'] ?? 0,
        'securitiesVariationPercentage' => $securities['variationPercentage'] ?? 0,
        'securitiesReturnPercentage' => $securities['returnPercentage'] ?? 0,
        'securitiesReturnAmount' => aetherFinanceDecimal($securities['returnAmount'] ?? 0),
        'securitiesStatus' => $securities['status'] ?? 'none', 'createdBy' => (int) $user['id'],
    ]);
    aetherFinanceAdjustBankBalance($pdo, $characterId, $amount);
    return ['success' => true, 'amount' => (float) $amount];
}

/** @return array<string, mixed> */
function aetherDeleteEconomySnapshot(PDO $pdo, array $user, int $snapshotId): array
{
    $characterId = aetherFinanceFetchSnapshotCharacterId($pdo, $snapshotId);
    if ($characterId === null) throw new AetherFinanceException(404, 'Snapshot niet gevonden.');
    if (aetherFinanceLockCharacter($pdo, $characterId) === null) {
        throw new AetherFinanceException(409, 'Het betrokken personage bestaat niet meer.');
    }
    $snapshot = aetherFinanceLockEconomySnapshot($pdo, $snapshotId, $characterId);
    if ($snapshot === null) throw new AetherFinanceException(404, 'Snapshot niet gevonden.');
    if (!canManageCharacterEconomySnapshots($snapshot, (string) $user['role'], (int) $user['id'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om deze economiesnapshot te verwijderen.');
    }
    $amount = aetherFinanceDecimal($snapshot['amount']);
    $returnAmount = aetherFinanceDecimal($snapshot['securitiesReturnAmount'] ?? 0);
    $withdrawal = aetherFinanceDecimal($snapshot['securitiesSnapshotWithdrawalAmount'] ?? 0);
    $withdrawalBank = aetherFinanceDecimal($snapshot['securitiesSnapshotWithdrawalBankAmount'] ?? 0);
    if (aetherDecimalCompare($withdrawal, '0.00') > 0 && aetherDecimalCompare($withdrawalBank, '0.00') <= 0) {
        $withdrawalBank = $withdrawal;
    }
    aetherFinanceDeleteEconomySnapshot($pdo, $snapshotId);
    aetherFinanceAdjustBankBalance($pdo, $characterId, aetherMinorUnitsToDecimal(-aetherDecimalToMinorUnits($amount)));
    if ((string) ($snapshot['securitiesStatus'] ?? 'none') === 'approved' && aetherDecimalCompare($returnAmount, '0.00') !== 0) {
        aetherFinanceAdjustBankBalance($pdo, $characterId, aetherMinorUnitsToDecimal(-aetherDecimalToMinorUnits($returnAmount)));
    }
    if (aetherDecimalCompare($withdrawal, '0.00') > 0) {
        aetherFinanceAdjustBalances($pdo, $characterId, '-' . $withdrawalBank, $withdrawal);
        $transactionId = (int) ($snapshot['securitiesSnapshotWithdrawalTransactionId'] ?? 0);
        if ($transactionId > 0) aetherFinanceDeleteSecuritiesTransaction($pdo, $transactionId);
    }
    return ['success' => true, 'revertedAmount' => (float) $amount];
}

function aetherSecuritiesWithdrawalBankAmount(string $amount, bool $eventEnded): string
{
    if (aetherDecimalCompare($amount, '0.00') <= 0) return '0.00';
    return $eventEnded || aetherDecimalCompare($amount, '30000.00') > 0
        ? aetherDecimalMultiplyRatio($amount, 75, 100)
        : $amount;
}

/** @return array<string, mixed> */
function aetherSaveSecuritiesPortfolio(PDO $pdo, array $user, array $input): array
{
    $action = (string) $input['action'];
    $characterId = (int) $input['idCharacter'];
    $character = aetherFinanceLockCharacter($pdo, $characterId);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');

    $role = (string) $user['role'];
    $userId = (int) $user['id'];
    if (in_array($action, ['reroll_snapshot', 'approve_snapshot'], true)) {
        if (!canApproveCharacterSecuritiesSnapshots($character, $role, $userId)) {
            throw new AetherFinanceException(403, 'Je hebt geen rechten om effectenportefeuillesnapshots te beheren.');
        }
    } elseif (!canManageCharacterSecurities($character, $role, $userId)) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om deze effectenportefeuille te beheren.');
    }

    if ($action === 'save_settings') {
        $managerType = normalizeCharacterSecuritiesManagerType($input['managerType']);
        $risk = normalizeCharacterSecuritiesRiskProfile($input['riskProfile']);
        $managerId = $managerType === 'third' ? (int) ($input['managerCharacterId'] ?? 0) : null;
        if ($managerType === 'third' && ($managerId <= 0 || !aetherFinanceActiveCharacterExists($pdo, $managerId))) {
            throw new AetherFinanceException(400, 'De gekozen beheerder is niet actief.');
        }
        aetherFinanceUpdateSecuritiesSettings($pdo, $characterId, $managerType, $managerId, $risk);
        return ['success' => true];
    }

    if ($action === 'deposit') {
        $amount = (string) $input['amount'];
        if (aetherDecimalCompare($amount, (string) $character['bankaccount']) > 0) {
            throw new AetherFinanceException(400, 'Onvoldoende saldo op de bankrekening.');
        }
        aetherFinanceAdjustBalances($pdo, $characterId, '-' . $amount, $amount);
        aetherFinanceInsertSecuritiesTransaction($pdo, [
            'idCharacter' => $characterId, 'direction' => 'deposit',
            'securitiesAmount' => $amount, 'bankAmount' => '-' . $amount,
            'transactionDate' => getDefaultBankTransferDate(),
            'description' => 'Storting naar effectenportefeuille', 'createdBy' => $userId,
        ]);
        return ['success' => true];
    }

    if ($action === 'manual_withdrawal') {
        $amount = (string) $input['amount'];
        if (aetherDecimalCompare($amount, (string) $character['securitiesaccount']) > 0) {
            throw new AetherFinanceException(400, 'Onvoldoende saldo in de effectenportefeuille.');
        }
        $bankAmount = aetherDecimalMultiplyRatio($amount, 75, 100);
        aetherFinanceAdjustBalances($pdo, $characterId, $bankAmount, '-' . $amount);
        aetherFinanceInsertSecuritiesTransaction($pdo, [
            'idCharacter' => $characterId, 'direction' => 'manual_withdrawal',
            'securitiesAmount' => '-' . $amount, 'bankAmount' => $bankAmount,
            'transactionDate' => getDefaultBankTransferDate(),
            'description' => 'Opname uit effectenportefeuille buiten snapshot (25% verlies)',
            'createdBy' => $userId,
        ]);
        return ['success' => true];
    }

    $snapshotId = (int) $input['idSnapshot'];
    $snapshot = aetherFinanceLockEconomySnapshot($pdo, $snapshotId, $characterId);
    if ($snapshot === null) throw new AetherFinanceException(404, 'Snapshot niet gevonden.');
    $status = trim((string) ($snapshot['securitiesStatus'] ?? 'none'));

    if ($action === 'reroll_snapshot') {
        if ($status !== 'pending') {
            throw new AetherFinanceException(400, 'Alleen wachtende snapshots kunnen opnieuw gerold worden.');
        }
        $variationLimit = aetherFinanceDecimal($snapshot['securitiesVariationLimitPercentage'] ?? 0);
        $variation = aetherFinanceDecimal(generateCharacterSecuritiesVariationPercentage((float) $variationLimit));
        $base = aetherFinanceDecimal($snapshot['securitiesBasePercentage'] ?? 0);
        $returnPercentage = aetherDecimalAdd($base, $variation);
        $balance = aetherFinanceDecimal($snapshot['securitiesBalanceSnapshot'] ?? 0);
        $returnAmount = aetherDecimalMultiplyRatio($balance, aetherDecimalToMinorUnits($returnPercentage), 10000);
        aetherFinanceUpdateSnapshot($pdo, $snapshotId, [
            'securitiesVariationPercentage' => $variation,
            'securitiesReturnPercentage' => $returnPercentage,
            'securitiesReturnAmount' => $returnAmount,
        ]);
        return ['success' => true];
    }

    if ($action === 'approve_snapshot') {
        if ($status !== 'pending') {
            throw new AetherFinanceException(400, 'Deze snapshot wacht niet meer op goedkeuring.');
        }
        $returnAmount = aetherFinanceDecimal($snapshot['securitiesReturnAmount'] ?? 0);
        aetherFinanceUpdateSnapshot($pdo, $snapshotId, [
            'securitiesStatus' => 'approved',
            'securitiesApprovedAt' => date('Y-m-d H:i:s'),
            'securitiesApprovedBy' => $userId,
        ]);
        if (aetherDecimalCompare($returnAmount, '0.00') !== 0) {
            aetherFinanceAdjustBankBalance($pdo, $characterId, $returnAmount);
        }
        return ['success' => true];
    }

    if ($status !== 'approved') {
        throw new AetherFinanceException(400, 'Alleen goedgekeurde snapshots laten een opname toe.');
    }
    $dateEnd = trim((string) ($snapshot['dateEnd'] ?? ''));
    $eventEnded = false;
    if ($dateEnd !== '') {
        try { $eventEnded = (new DateTimeImmutable($dateEnd)) < new DateTimeImmutable('today'); }
        catch (Throwable) { $eventEnded = false; }
    }
    $amount = (string) $input['amount'];
    $previousAmount = aetherFinanceDecimal($snapshot['securitiesSnapshotWithdrawalAmount'] ?? 0);
    $previousBank = aetherFinanceDecimal($snapshot['securitiesSnapshotWithdrawalBankAmount'] ?? 0);
    if (aetherDecimalCompare($previousAmount, '0.00') > 0 && aetherDecimalCompare($previousBank, '0.00') <= 0) {
        $previousBank = $previousAmount;
    }
    $available = aetherDecimalAdd(aetherFinanceDecimal($character['securitiesaccount']), $previousAmount);
    if (aetherDecimalCompare($amount, $available) > 0) {
        throw new AetherFinanceException(400, 'Onvoldoende saldo in de effectenportefeuille.');
    }
    $bankAmount = aetherSecuritiesWithdrawalBankAmount($amount, $eventEnded);
    $securitiesDelta = aetherDecimalSubtract($amount, $previousAmount);
    $bankDelta = aetherDecimalSubtract($bankAmount, $previousBank);
    $transactionId = (int) ($snapshot['securitiesSnapshotWithdrawalTransactionId'] ?? 0);
    $transactionDate = trim((string) ($snapshot['transactionDate'] ?? '')) ?: getDefaultBankTransferDate();
    $description = buildCharacterSecuritiesSnapshotWithdrawalDescription($snapshotId, (string) ($snapshot['eventTitle'] ?? ''));

    aetherFinanceUpdateSnapshot($pdo, $snapshotId, ['securitiesSnapshotWithdrawalAmount' => $amount]);
    if (aetherDecimalCompare($securitiesDelta, '0.00') !== 0 || aetherDecimalCompare($bankDelta, '0.00') !== 0) {
        aetherFinanceAdjustBalances(
            $pdo,
            $characterId,
            $bankDelta,
            aetherMinorUnitsToDecimal(-aetherDecimalToMinorUnits($securitiesDelta))
        );
    }
    if (aetherDecimalCompare($amount, '0.00') <= 0) {
        if ($transactionId > 0) aetherFinanceDeleteSecuritiesTransaction($pdo, $transactionId);
    } elseif ($transactionId > 0) {
        aetherFinanceUpdateSecuritiesTransaction($pdo, $transactionId, [
            'securitiesAmount' => '-' . $amount, 'bankAmount' => $bankAmount,
            'transactionDate' => $transactionDate, 'description' => $description,
        ]);
    } else {
        aetherFinanceInsertSecuritiesTransaction($pdo, [
            'idCharacter' => $characterId, 'direction' => 'manual_withdrawal',
            'securitiesAmount' => '-' . $amount, 'bankAmount' => $bankAmount,
            'transactionDate' => $transactionDate, 'description' => $description,
            'createdBy' => $userId,
        ]);
    }
    return [
        'success' => true,
        'bankAmount' => (float) $bankAmount,
        'hasLoss' => $eventEnded || aetherDecimalCompare($amount, '30000.00') > 0,
    ];
}
