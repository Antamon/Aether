<?php
declare(strict_types=1);

require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/traitUtils.php';
require_once __DIR__ . '/companyShareUtils.php';

function getCurrentUserId(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
}

function canViewCharacterEconomy(array $character, string $role, int $userId): bool
{
    if (isPrivilegedUserRole($role)) {
        return true;
    }

    return $role === 'participant'
        && (int) ($character['idUser'] ?? 0) === $userId;
}

function canEditCharacterBankAccount(array $character, string $role): bool
{
    return isPrivilegedUserRole($role)
        && (string) ($character['state'] ?? '') !== 'draft';
}

function canTransferFromCharacter(array $character, string $role): bool
{
    return canEditCharacterBankAccount($character, $role);
}

function canManageCharacterEconomySnapshots(array $character, string $role, int $userId): bool
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return false;
    }

    return isPrivilegedUserRole($role);
}

function canManageCharacterSecurities(array $character, string $role, int $userId): bool
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return false;
    }

    return canViewCharacterEconomy($character, $role, $userId);
}

function canApproveCharacterSecuritiesSnapshots(array $character, string $role, int $userId): bool
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return false;
    }

    return isPrivilegedUserRole($role);
}

function getDisplayedCharacterBankAmount(PDO $pdo, array $character): float
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return getDraftBankAccountAmountForCharacter($pdo, $character);
    }

    return round((float) ($character['bankaccount'] ?? 0), 2);
}

function calculateEconomyTraitIncomeAtRank(array $trait): ?float
{
    if (!isset($trait['income']) || $trait['income'] === null || $trait['income'] === '') {
        return null;
    }

    $baseIncome = (float) $trait['income'];
    if (!is_finite($baseIncome)) {
        return null;
    }

    $rankType = (string) ($trait['rankType'] ?? 'singular');
    $rank = (int) ($trait['rank'] ?? 1);
    $evolution = isset($trait['evolution']) ? (float) $trait['evolution'] : null;
    $hasEvolution = $evolution !== null && is_finite($evolution) && $evolution != 0.0;

    if ($rankType === 'singular') {
        return $baseIncome;
    }

    if (!$hasEvolution) {
        return $baseIncome * max(1, abs($rank ?: 1));
    }

    if ($rank >= 1) {
        return $baseIncome * pow(1 + $evolution, max(0, $rank - 1));
    }

    return $baseIncome * pow(1 - $evolution, abs($rank));
}

function getEconomyUpperClassNobilityIncomeMultiplier(array $traitLinks, array $character): float
{
    if (getCharacterEconomyClass($character) !== 'upper class') {
        return 1.0;
    }

    $linkedTitle = null;
    foreach ($traitLinks as $trait) {
        if (
            (string) ($trait['trackKey'] ?? '') === 'upper_nobility_title'
            || (string) ($trait['traitGroup'] ?? '') === 'Adellijke titel'
        ) {
            $linkedTitle = $trait;
            break;
        }
    }

    if ($linkedTitle === null) {
        return 1.0;
    }

    if (
        traitHasFlag($linkedTitle, 'family_head_title')
        || mb_strtolower(trim((string) ($linkedTitle['name'] ?? ''))) === 'familiehoofd'
    ) {
        return 2.0;
    }

    return 1.5;
}

function getCharacterEconomyClass(array $character): string
{
    $characterClass = trim((string) ($character['class'] ?? ''));
    if ($characterClass !== '') {
        return $characterClass;
    }

    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return '';
    }

    try {
        $pdo = getPDO();
        $row = dbOne(
            $pdo,
            'SELECT `class`
               FROM tblCharacter
              WHERE id = :idCharacter',
            ['idCharacter' => $idCharacter]
        );
    } catch (Throwable $e) {
        return '';
    }

    return trim((string) ($row['class'] ?? ''));
}

function shouldApplyUpperClassNobilityIncomeMultiplier(array $trait): bool
{
    return traitHasFlag($trait, 'nobility_income_scaled')
        || (string) ($trait['trackKey'] ?? '') === 'upper_nobility_lineage'
        || (string) ($trait['traitGroup'] ?? '') === 'Adeldom';
}

function isMiddleClassProfessionIncomeTrait(array $trait): bool
{
    return (string) ($trait['type'] ?? '') === 'profession';
}

function isUpperClassDividendIncomeTrait(array $trait): bool
{
    return shouldApplyUpperClassNobilityIncomeMultiplier($trait)
        || (string) ($trait['trackKey'] ?? '') === 'upper_nobility_title'
        || (string) ($trait['traitGroup'] ?? '') === 'Adellijke titel';
}

function normalizeCharacterSalaryIncreasePercentage(mixed $value): float
{
    $normalized = round((float) $value, 2);
    if (!is_finite($normalized)) {
        return 0.0;
    }

    return max(0.0, $normalized);
}

function getCharacterSalaryIncreaseIncomeBaseFromTraitLinks(array $traitLinks, array $character): float
{
    $characterClass = getCharacterEconomyClass($character);
    if ($characterClass !== 'middle class' && $characterClass !== 'upper class') {
        return 0.0;
    }

    $seenTraitIds = [];
    $nobilityIncomeMultiplier = getEconomyUpperClassNobilityIncomeMultiplier($traitLinks, $character);
    $salaryIncreaseBase = 0.0;

    foreach ($traitLinks as $trait) {
        $idTrait = (int) ($trait['idTrait'] ?? 0);
        if ($idTrait > 0 && isset($seenTraitIds[$idTrait])) {
            continue;
        }

        if ($idTrait > 0) {
            $seenTraitIds[$idTrait] = true;
        }

        $income = calculateEconomyTraitIncomeAtRank($trait);
        if ($income === null) {
            continue;
        }

        if (shouldApplyUpperClassNobilityIncomeMultiplier($trait)) {
            $income *= $nobilityIncomeMultiplier;
        }

        if ($characterClass === 'middle class' && isMiddleClassProfessionIncomeTrait($trait)) {
            $salaryIncreaseBase += $income;
            continue;
        }

        if ($characterClass === 'upper class' && isUpperClassDividendIncomeTrait($trait)) {
            $salaryIncreaseBase += $income;
        }
    }

    return round($salaryIncreaseBase, 2);
}

function getCharacterCompanySalaryIncreasePercentage(PDO $pdo, int $idCharacter, ?int $idCompany = null): float
{
    if ($idCharacter <= 0) {
        return 0.0;
    }

    $params = ['idCharacter' => $idCharacter];
    $companyClause = '';
    if ($idCompany !== null && $idCompany > 0) {
        $companyClause = ' AND idCompany = :idCompany';
        $params['idCompany'] = $idCompany;
    }

    try {
        $row = dbOne(
            $pdo,
            "SELECT COALESCE(SUM(salaryIncreasePercentage), 0) AS totalPercentage
               FROM tblCompanyPersonnel
              WHERE idCharacter = :idCharacter{$companyClause}",
            $params
        );
    } catch (Throwable $e) {
        return 0.0;
    }

    return normalizeCharacterSalaryIncreasePercentage($row['totalPercentage'] ?? 0);
}

function calculateCharacterSalaryIncreaseAmount(float $salaryIncreaseBaseIncome, float $salaryIncreasePercentage): float
{
    if ($salaryIncreaseBaseIncome <= 0 || $salaryIncreasePercentage <= 0) {
        return 0.0;
    }

    return round($salaryIncreaseBaseIncome * ($salaryIncreasePercentage / 100), 2);
}

function getCharacterGrossRecurringIncomeBreakdown(PDO $pdo, array $character): array
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return [
            'baseRecurringIncome' => 0.0,
            'salaryIncreaseBaseIncome' => 0.0,
            'salaryIncreasePercentage' => 0.0,
            'salaryIncreaseAmount' => 0.0,
            'grossRecurringIncome' => 0.0,
        ];
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    $baseRecurringIncome = getCharacterRecurringIncomeTotalFromTraitLinks($traitLinks, $character);
    $salaryIncreaseBaseIncome = getCharacterSalaryIncreaseIncomeBaseFromTraitLinks($traitLinks, $character);
    $salaryIncreasePercentage = getCharacterCompanySalaryIncreasePercentage($pdo, $idCharacter);
    $salaryIncreaseAmount = calculateCharacterSalaryIncreaseAmount(
        $salaryIncreaseBaseIncome,
        $salaryIncreasePercentage
    );

    return [
        'baseRecurringIncome' => $baseRecurringIncome,
        'salaryIncreaseBaseIncome' => $salaryIncreaseBaseIncome,
        'salaryIncreasePercentage' => $salaryIncreasePercentage,
        'salaryIncreaseAmount' => $salaryIncreaseAmount,
        'grossRecurringIncome' => round($baseRecurringIncome + $salaryIncreaseAmount, 2),
    ];
}

function getCharacterCompanySalaryIncreaseAmount(PDO $pdo, array $character, ?int $idCompany = null): float
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return 0.0;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    $salaryIncreaseBaseIncome = getCharacterSalaryIncreaseIncomeBaseFromTraitLinks($traitLinks, $character);
    $salaryIncreasePercentage = getCharacterCompanySalaryIncreasePercentage($pdo, $idCharacter, $idCompany);

    return calculateCharacterSalaryIncreaseAmount($salaryIncreaseBaseIncome, $salaryIncreasePercentage);
}

function getCharacterUpperClassLivingStandardTier(PDO $pdo, array $character): int
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0 || getCharacterEconomyClass($character) !== 'upper class') {
        return 0;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    foreach ($traitLinks as $trait) {
        if (
            (string) ($trait['trackKey'] ?? '') === 'upper_nobility_lineage'
            || (string) ($trait['traitGroup'] ?? '') === 'Adeldom'
        ) {
            return (int) ($trait['idTrait'] ?? 0);
        }
    }

    return 0;
}

function canCharacterBeLandlord(PDO $pdo, array $character): bool
{
    $characterClass = getCharacterEconomyClass($character);
    if ($characterClass === 'upper class') {
        return true;
    }

    if ($characterClass !== 'middle class') {
        return false;
    }

    return getCharacterRecurringIncomeTotal($pdo, $character) >= 350.0;
}

function getCharacterMiddleClassProfessionIncomeFromTraitLinks(array $traitLinks): float
{
    $seenTraitIds = [];
    $professionIncome = 0.0;

    foreach ($traitLinks as $trait) {
        $idTrait = (int) ($trait['idTrait'] ?? 0);
        if ($idTrait > 0 && isset($seenTraitIds[$idTrait])) {
            continue;
        }

        if ($idTrait > 0) {
            $seenTraitIds[$idTrait] = true;
        }

        if (!isMiddleClassProfessionIncomeTrait($trait)) {
            continue;
        }

        $income = calculateEconomyTraitIncomeAtRank($trait);
        if ($income === null) {
            continue;
        }

        $professionIncome += $income;
    }

    return round($professionIncome, 2);
}

function getCharacterVirtualCompanyShareLivingStandardIncome(PDO $pdo, int $idCharacter): float
{
    if ($idCharacter <= 0) {
        return 0.0;
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT
                lct.rankValue,
                COALESCE(lctc.extraPercentage, 0) AS extraPercentage,
                c.companyValue,
                c.profitability
             FROM tblLinkCharacterTraitCompany AS lctc
             JOIN tblLinkCharacterTrait AS lct
               ON lct.id = lctc.idLinkCharacterTrait
             JOIN tblCompany AS c
               ON c.id = lctc.idCompany
             WHERE lct.idCharacter = :idCharacter",
            ['idCharacter' => $idCharacter]
        );
    } catch (Throwable $e) {
        return 0.0;
    }

    $virtualMonthlyIncome = 0.0;
    foreach ($rows as $row) {
        $ownedPercentage = max(0, (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0));
        if ($ownedPercentage <= 0) {
            continue;
        }

        $companyValue = round((float) ($row['companyValue'] ?? 0), 2);
        $profitability = max(-7, min(7, (int) ($row['profitability'] ?? 0)));
        $profitabilityPercentage = max(-11.0, min(17.0, 3.0 + (2.0 * $profitability)));
        if ($profitabilityPercentage <= 0 || $companyValue <= 0) {
            continue;
        }

        $annualProfitPerPercentage = ($companyValue * ($profitabilityPercentage / 100)) / 100;
        if ($annualProfitPerPercentage <= 0) {
            continue;
        }

        $virtualMonthlyIncome += ($annualProfitPerPercentage * $ownedPercentage) / 12;
    }

    return round($virtualMonthlyIncome, 2);
}

function getCharacterMiddleClassLivingStandardIncome(PDO $pdo, array $character): float
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0 || getCharacterEconomyClass($character) !== 'middle class') {
        return 0.0;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    $professionIncome = getCharacterMiddleClassProfessionIncomeFromTraitLinks($traitLinks);
    $salaryIncreaseAmount = calculateCharacterSalaryIncreaseAmount(
        $professionIncome,
        getCharacterCompanySalaryIncreasePercentage($pdo, $idCharacter)
    );
    $householdStaffExpenseAmount = getCharacterConfirmedHouseholdStaffExpenseAmount($pdo, $character);
    $virtualShareIncome = getCharacterVirtualCompanyShareLivingStandardIncome($pdo, $idCharacter);

    return round($professionIncome + $salaryIncreaseAmount - $householdStaffExpenseAmount + $virtualShareIncome, 2);
}

function getCharacterConfirmedHouseholdStaffExpenseAmount(PDO $pdo, array $character): float
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return 0.0;
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT DISTINCT t.idCharacterTarget
               FROM tblCharacterTie AS t
               JOIN tblCharacterTie AS reverseTie
                 ON reverseTie.idCharacter = t.idCharacterTarget
                AND reverseTie.idCharacterTarget = t.idCharacter
                AND reverseTie.relationType = 'landlord'
              WHERE t.idCharacter = :idCharacter
                AND t.relationType = 'household_staff'",
            ['idCharacter' => $idCharacter]
        );
    } catch (Throwable $e) {
        return 0.0;
    }

    if (count($rows) === 0) {
        return 0.0;
    }

    $grossRecurringIncome = (float) (getCharacterGrossRecurringIncomeBreakdown($pdo, $character)['grossRecurringIncome'] ?? 0.0);
    $maxAllowedExpense = max(0.0, $grossRecurringIncome - 350.0);
    if ($maxAllowedExpense <= 0) {
        return 0.0;
    }

    $staffExpenseTotal = 0.0;
    foreach ($rows as $row) {
        $idStaffCharacter = (int) ($row['idCharacterTarget'] ?? 0);
        if ($idStaffCharacter <= 0) {
            continue;
        }

        $staffExpenseTotal += (float) (getCharacterGrossRecurringIncomeBreakdown(
            $pdo,
            ['id' => $idStaffCharacter]
        )['grossRecurringIncome'] ?? 0.0);
    }

    return round(min($staffExpenseTotal, $maxAllowedExpense), 2);
}

function getCharacterRecurringIncomeBreakdown(PDO $pdo, array $character): array
{
    $grossBreakdown = getCharacterGrossRecurringIncomeBreakdown($pdo, $character);
    $grossRecurringIncome = (float) ($grossBreakdown['grossRecurringIncome'] ?? 0.0);
    $householdStaffExpenseAmount = getCharacterConfirmedHouseholdStaffExpenseAmount($pdo, $character);

    return [
        'baseRecurringIncome' => (float) ($grossBreakdown['baseRecurringIncome'] ?? 0.0),
        'salaryIncreaseBaseIncome' => (float) ($grossBreakdown['salaryIncreaseBaseIncome'] ?? 0.0),
        'salaryIncreasePercentage' => (float) ($grossBreakdown['salaryIncreasePercentage'] ?? 0.0),
        'salaryIncreaseAmount' => (float) ($grossBreakdown['salaryIncreaseAmount'] ?? 0.0),
        'grossRecurringIncome' => $grossRecurringIncome,
        'householdStaffExpenseAmount' => $householdStaffExpenseAmount,
        'totalRecurringIncome' => round($grossRecurringIncome - $householdStaffExpenseAmount, 2),
    ];
}

function characterHasTraitFlag(PDO $pdo, int $idCharacter, string $flagKey, ?string $legacyTraitName = null): bool
{
    $normalizedLegacyTraitName = $legacyTraitName !== null
        ? mb_strtolower(trim($legacyTraitName))
        : '';
    if ($idCharacter <= 0 || ($flagKey === '' && $normalizedLegacyTraitName === '')) {
        return false;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    foreach ($traitLinks as $trait) {
        if ($flagKey !== '' && traitHasFlag($trait, $flagKey)) {
            return true;
        }

        if ($normalizedLegacyTraitName !== '' && mb_strtolower(trim((string) ($trait['name'] ?? ''))) === $normalizedLegacyTraitName) {
            return true;
        }
    }

    return false;
}

function characterHasTraitId(PDO $pdo, int $idCharacter, int $idTrait): bool
{
    if ($idCharacter <= 0 || $idTrait <= 0) {
        return false;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    foreach ($traitLinks as $trait) {
        if ((int) ($trait['idTrait'] ?? 0) === $idTrait) {
            return true;
        }
    }

    return false;
}

function getCharacterSkillLevelById(PDO $pdo, int $idCharacter, int $idSkill): int
{
    if ($idCharacter <= 0 || $idSkill <= 0) {
        return 0;
    }

    try {
        $row = dbOne(
            $pdo,
            'SELECT level
               FROM tblLinkCharacterSkill
              WHERE idCharacter = :idCharacter
                AND idSkill = :idSkill',
            [
                'idCharacter' => $idCharacter,
                'idSkill' => $idSkill,
            ]
        );
    } catch (Throwable $e) {
        return 0;
    }

    return max(0, min(3, (int) ($row['level'] ?? 0)));
}

function getCharacterEconomySnapshotAmount(PDO $pdo, array $character): float
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return 0.0;
    }

    $characterClass = getCharacterEconomyClass($character);
    $totalIncome = getCharacterRecurringIncomeTotal($pdo, $character);
    $fiscalityLevel = getCharacterSkillLevelById($pdo, $idCharacter, 6);
    $fiscalityMultiplier = 1 + ($fiscalityLevel * 0.1);

    if ($characterClass === 'upper class') {
        $baseMultiplier = characterHasTraitId($pdo, $idCharacter, 12) ? 4.0 : 3.0;
        return round($totalIncome * $baseMultiplier * $fiscalityMultiplier, 2);
    }

    if ($characterClass === 'middle class') {
        $baseMultiplier = characterHasTraitId($pdo, $idCharacter, 56) ? 4.0 : 3.0;
        return round($totalIncome * $baseMultiplier * 0.6 * $fiscalityMultiplier, 2);
    }

    if ($characterClass === 'lower class') {
        return round($totalIncome * 3.0 * 0.6 * $fiscalityMultiplier, 2);
    }

    return round($totalIncome, 2);
}

function getDraftBankAccountAmountForCharacter(PDO $pdo, array $character): float
{
    $totalIncome = getCharacterRecurringIncomeTotal($pdo, $character);
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return 0.0;
    }

    $multiplier = characterHasTraitFlag($pdo, $idCharacter, 'savings_bank_multiplier', 'Spaarder') ? 15.0 : 10.0;

    return round($totalIncome * $multiplier, 2);
}

function getCharacterRecurringIncomeTotalFromTraitLinks(array $traitLinks, array $character): float
{
    $seenTraitIds = [];
    $nobilityIncomeMultiplier = getEconomyUpperClassNobilityIncomeMultiplier($traitLinks, $character);
    $totalIncome = 0.0;

    foreach ($traitLinks as $trait) {
        $idTrait = (int) ($trait['idTrait'] ?? 0);
        if ($idTrait > 0 && isset($seenTraitIds[$idTrait])) {
            continue;
        }

        if ($idTrait > 0) {
            $seenTraitIds[$idTrait] = true;
        }

        $income = calculateEconomyTraitIncomeAtRank($trait);
        if ($income === null) {
            continue;
        }

        if (shouldApplyUpperClassNobilityIncomeMultiplier($trait)) {
            $income *= $nobilityIncomeMultiplier;
        }

        $totalIncome += $income;
    }

    return round($totalIncome, 2);
}

function getCharacterRecurringIncomeTotal(PDO $pdo, array $character): float
{
    $breakdown = getCharacterRecurringIncomeBreakdown($pdo, $character);
    return round((float) ($breakdown['totalRecurringIncome'] ?? 0), 2);
}

function getDefaultBankTransferDate(): string
{
    return (new DateTimeImmutable('today'))->modify('-100 years')->format('Y-m-d');
}

function formatCharacterDisplayName(array $row): string
{
    $name = trim(((string) ($row['firstName'] ?? '')) . ' ' . ((string) ($row['lastName'] ?? '')));
    if ($name !== '') {
        return $name;
    }

    return 'Personage #' . (int) ($row['id'] ?? 0);
}

function getCharacterDisplayNameById(PDO $pdo, int $idCharacter): string
{
    if ($idCharacter <= 0) {
        return 'Onbekend personage';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, firstName, lastName
             FROM tblCharacter
             WHERE id = :id'
        );
        $stmt->execute(['id' => $idCharacter]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 'Onbekend personage';
        }

        return formatCharacterDisplayName($row);
    } catch (Throwable $e) {
        return 'Onbekend personage';
    }
}

function getCharacterBankTransactions(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                bt.id,
                bt.idSourceCharacter,
                bt.idTargetCharacter,
                bt.amount,
                bt.transactionDate,
                bt.description
             FROM tblCharacterBankTransaction AS bt
             WHERE bt.idSourceCharacter = :idCharacterSource
                OR bt.idTargetCharacter = :idCharacterTarget
             ORDER BY bt.transactionDate DESC, bt.id DESC"
        );
        $stmt->execute([
            'idCharacterSource' => $idCharacter,
            'idCharacterTarget' => $idCharacter,
        ]);
    } catch (Throwable $e) {
        return [];
    }

    $transactions = [];
    $nameCache = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isOutgoing = (int) $row['idSourceCharacter'] === $idCharacter;
        $counterpartyId = $isOutgoing
            ? (int) $row['idTargetCharacter']
            : (int) $row['idSourceCharacter'];

        if (!isset($nameCache[$counterpartyId])) {
            $nameCache[$counterpartyId] = getCharacterDisplayNameById($pdo, $counterpartyId);
        }

        $transactions[] = [
            'id' => (int) $row['id'],
            'type' => 'bank_transfer',
            'direction' => $isOutgoing ? 'outgoing' : 'incoming',
            'counterpartyName' => $nameCache[$counterpartyId],
            'amount' => round((float) $row['amount'], 2),
            'transactionDate' => (string) $row['transactionDate'],
            'description' => (string) ($row['description'] ?? ''),
            'canDelete' => true,
        ];
    }

    try {
        $stmtSnapshots = $pdo->prepare(
            "SELECT
                ces.id,
                ces.amount,
                ces.transactionDate,
                e.title AS eventTitle
             FROM tblCharacterEconomySnapshot AS ces
             JOIN tblEvent AS e
               ON e.id = ces.idEvent
             WHERE ces.idCharacter = :idCharacter
             ORDER BY ces.transactionDate DESC, ces.id DESC"
        );
        $stmtSnapshots->execute(['idCharacter' => $idCharacter]);

        foreach ($stmtSnapshots->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $transactions[] = [
                'id' => (int) ($row['id'] ?? 0),
                'type' => 'character_income_snapshot',
                'direction' => $amount < 0 ? 'outgoing' : 'incoming',
                'counterpartyName' => 'Inkomsten snapshot',
                'amount' => $amount,
                'transactionDate' => (string) ($row['transactionDate'] ?? ''),
                'description' => 'Aether - ' . trim((string) ($row['eventTitle'] ?? '')),
                'canDelete' => false,
            ];
        }
    } catch (Throwable $e) {
        // Character economy snapshots are optional until the migration has run.
    }

    try {
        $stmtPayouts = $pdo->prepare(
            "SELECT
                csp.id,
                csp.amount,
                csp.transactionDate,
                csp.description,
                co.companyName,
                e.title AS eventTitle
             FROM tblCompanySnapshotPayout AS csp
             JOIN tblCompanySnapshot AS cs
               ON cs.id = csp.idCompanySnapshot
             JOIN tblCompany AS co
               ON co.id = cs.idCompany
             JOIN tblEvent AS e
               ON e.id = cs.idEvent
             WHERE csp.idCharacter = :idCharacter
             ORDER BY csp.transactionDate DESC, csp.id DESC"
        );
        $stmtPayouts->execute(['idCharacter' => $idCharacter]);

        foreach ($stmtPayouts->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $transactions[] = [
                'id' => (int) ($row['id'] ?? 0),
                'type' => 'company_snapshot_dividend',
                'direction' => 'incoming',
                'counterpartyName' => 'Divident ' . trim((string) ($row['companyName'] ?? 'Bedrijf')),
                'amount' => round((float) ($row['amount'] ?? 0), 2),
                'transactionDate' => (string) ($row['transactionDate'] ?? ''),
                'description' => trim((string) ($row['description'] ?? '')) !== ''
                    ? (string) $row['description']
                    : ('Aether - ' . trim((string) ($row['eventTitle'] ?? ''))),
                'canDelete' => false,
            ];
        }
    } catch (Throwable $e) {
        // Snapshot payouts are optional until the migration has run.
    }

    try {
        $stmtSecuritiesSnapshots = $pdo->prepare(
            "SELECT
                ces.id,
                ces.transactionDate,
                ces.securitiesReturnAmount,
                ces.securitiesSnapshotWithdrawalAmount,
                e.title AS eventTitle
             FROM tblCharacterEconomySnapshot AS ces
             JOIN tblEvent AS e
               ON e.id = ces.idEvent
             WHERE ces.idCharacter = :idCharacter
               AND ces.securitiesStatus = 'approved'
               AND (
                    COALESCE(ces.securitiesReturnAmount, 0) <> 0
                    OR COALESCE(ces.securitiesSnapshotWithdrawalAmount, 0) > 0
               )
             ORDER BY ces.transactionDate DESC, ces.id DESC"
        );
        $stmtSecuritiesSnapshots->execute(['idCharacter' => $idCharacter]);

        foreach ($stmtSecuritiesSnapshots->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $returnAmount = round((float) ($row['securitiesReturnAmount'] ?? 0), 2);
            if ($returnAmount !== 0.0) {
                $transactions[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => 'character_securities_snapshot_return',
                    'direction' => $returnAmount < 0 ? 'outgoing' : 'incoming',
                    'counterpartyName' => 'Effectenportefeuille',
                    'amount' => abs($returnAmount),
                    'transactionDate' => (string) ($row['transactionDate'] ?? ''),
                    'description' => 'Rendement effectenportefeuille - Aether - ' . trim((string) ($row['eventTitle'] ?? '')),
                    'canDelete' => false,
                ];
            }

            $withdrawalAmount = round((float) ($row['securitiesSnapshotWithdrawalAmount'] ?? 0), 2);
            if ($withdrawalAmount > 0) {
                $transactions[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => 'character_securities_snapshot_withdrawal',
                    'direction' => 'incoming',
                    'counterpartyName' => 'Effectenportefeuille',
                    'amount' => $withdrawalAmount,
                    'transactionDate' => (string) ($row['transactionDate'] ?? ''),
                    'description' => 'Opname effectenportefeuille - Aether - ' . trim((string) ($row['eventTitle'] ?? '')),
                    'canDelete' => false,
                ];
            }
        }
    } catch (Throwable $e) {
        // Securities snapshot fields are optional until the migration has run.
    }

    foreach (getCharacterSecuritiesTransactions($pdo, $idCharacter) as $securitiesTransaction) {
        $transactions[] = $securitiesTransaction;
    }

    usort($transactions, static function (array $left, array $right): int {
        $leftDate = (string) ($left['transactionDate'] ?? '');
        $rightDate = (string) ($right['transactionDate'] ?? '');
        if ($leftDate !== $rightDate) {
            return strcmp($rightDate, $leftDate);
        }

        return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
    });

    return $transactions;
}

function getCharacterEconomySnapshots(PDO $pdo, int $idCharacter, bool $includePendingSecurities = true): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                ces.id,
                ces.idEvent,
                ces.amount,
                ces.transactionDate,
                e.title AS eventTitle,
                e.dateEnd,
                COALESCE(ces.securitiesBalanceSnapshot, 0) AS securitiesBalanceSnapshot,
                COALESCE(ces.securitiesManagerType, 'none') AS securitiesManagerType,
                ces.securitiesManagerCharacterId,
                COALESCE(ces.securitiesRiskProfile, 3) AS securitiesRiskProfile,
                COALESCE(ces.securitiesManagerSkillLevel, 0) AS securitiesManagerSkillLevel,
                COALESCE(ces.securitiesBasePercentage, 0) AS securitiesBasePercentage,
                COALESCE(ces.securitiesVariationLimitPercentage, 0) AS securitiesVariationLimitPercentage,
                COALESCE(ces.securitiesVariationPercentage, 0) AS securitiesVariationPercentage,
                COALESCE(ces.securitiesReturnPercentage, 0) AS securitiesReturnPercentage,
                COALESCE(ces.securitiesReturnAmount, 0) AS securitiesReturnAmount,
                COALESCE(ces.securitiesStatus, 'none') AS securitiesStatus,
                COALESCE(ces.securitiesSnapshotWithdrawalAmount, 0) AS securitiesSnapshotWithdrawalAmount,
                manager.firstName AS securitiesManagerFirstName,
                manager.lastName AS securitiesManagerLastName
             FROM tblCharacterEconomySnapshot AS ces
             JOIN tblEvent AS e
               ON e.id = ces.idEvent
             LEFT JOIN tblCharacter AS manager
               ON manager.id = ces.securitiesManagerCharacterId
             WHERE ces.idCharacter = :idCharacter
             ORDER BY ces.transactionDate DESC, ces.id DESC"
        );
        $stmt->execute(['idCharacter' => $idCharacter]);
    } catch (Throwable $e) {
        return [];
    }

    $today = new DateTimeImmutable('today');
    $snapshots = array_map(static function (array $row) use ($today): array {
        $dateEnd = (string) ($row['dateEnd'] ?? '');
        $eventEnded = false;
        if ($dateEnd !== '') {
            try {
                $eventEnded = (new DateTimeImmutable($dateEnd)) < $today;
            } catch (Throwable $e) {
                $eventEnded = false;
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'idEvent' => (int) ($row['idEvent'] ?? 0),
            'eventTitle' => (string) ($row['eventTitle'] ?? ''),
            'transactionDate' => (string) ($row['transactionDate'] ?? ''),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
            'eventDateEnd' => $dateEnd,
            'securitiesBalanceSnapshot' => round((float) ($row['securitiesBalanceSnapshot'] ?? 0), 2),
            'securitiesManagerType' => (string) ($row['securitiesManagerType'] ?? 'none'),
            'securitiesManagerCharacterId' => ($row['securitiesManagerCharacterId'] ?? null) !== null
                ? (int) $row['securitiesManagerCharacterId']
                : null,
            'securitiesManagerDisplayName' => trim(((string) ($row['securitiesManagerFirstName'] ?? '')) . ' ' . ((string) ($row['securitiesManagerLastName'] ?? ''))),
            'securitiesRiskProfile' => (int) ($row['securitiesRiskProfile'] ?? 3),
            'securitiesManagerSkillLevel' => (int) ($row['securitiesManagerSkillLevel'] ?? 0),
            'securitiesBasePercentage' => round((float) ($row['securitiesBasePercentage'] ?? 0), 2),
            'securitiesVariationLimitPercentage' => round((float) ($row['securitiesVariationLimitPercentage'] ?? 0), 2),
            'securitiesVariationPercentage' => round((float) ($row['securitiesVariationPercentage'] ?? 0), 2),
            'securitiesReturnPercentage' => round((float) ($row['securitiesReturnPercentage'] ?? 0), 2),
            'securitiesReturnAmount' => round((float) ($row['securitiesReturnAmount'] ?? 0), 2),
            'securitiesStatus' => (string) ($row['securitiesStatus'] ?? 'none'),
            'securitiesSnapshotWithdrawalAmount' => round((float) ($row['securitiesSnapshotWithdrawalAmount'] ?? 0), 2),
            'securitiesEventEnded' => $eventEnded,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    if ($includePendingSecurities) {
        return $snapshots;
    }

    return array_values(array_filter($snapshots, static function (array $snapshot): bool {
        $status = trim((string) ($snapshot['securitiesStatus'] ?? 'none'));
        return $status === '' || $status === 'none' || $status === 'approved';
    }));
}

function getCharacterEconomySnapshotEventOptions(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                e.id,
                e.title,
                e.dateStart
             FROM tblEvent AS e
             LEFT JOIN tblCharacterEconomySnapshot AS ces
               ON ces.idEvent = e.id
              AND ces.idCharacter = :idCharacter
             WHERE ces.id IS NULL
             ORDER BY e.dateStart DESC, e.title ASC, e.id DESC"
        );
        $stmt->execute(['idCharacter' => $idCharacter]);
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'dateStart' => (string) ($row['dateStart'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getBankTransferTargets(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, firstName, lastName
         FROM tblCharacter
         WHERE id <> :idCharacter
           AND state = 'active'
         ORDER BY firstName, lastName"
    );
    $stmt->execute(['idCharacter' => $idCharacter]);

    $targets = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $targets[] = [
            'id' => (int) $row['id'],
            'displayName' => formatCharacterDisplayName($row),
        ];
    }

    return $targets;
}

function getCompaniesForShares(PDO $pdo): array
{
    try {
        $rows = dbAll(
            $pdo,
            'SELECT id, companyName, description, companyValue
               FROM tblCompany
           ORDER BY companyName ASC, id ASC'
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        $company = [
            'id' => (int) ($row['id'] ?? 0),
            'companyName' => (string) ($row['companyName'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'companyValue' => round((float) ($row['companyValue'] ?? 0), 2),
        ];

        $company = enrichCompanyWithType($company);
        $company['logoUrl'] = getCompanyLogoUrl((int) $company['id']);

        return $company;
    }, $rows);
}

function getCompanyShareAllocationByCompany(PDO $pdo): array
{
    try {
        $rows = dbAll(
            $pdo,
            'SELECT
                lctc.idCompany,
                lct.idTrait,
                lct.rankValue,
                lctc.extraPercentage,
                lct.id AS idLinkCharacterTrait
             FROM tblLinkCharacterTraitCompany AS lctc
             JOIN tblLinkCharacterTrait AS lct
               ON lct.id = lctc.idLinkCharacterTrait
             WHERE lctc.idCompany IS NOT NULL'
        );
    } catch (Throwable $e) {
        return [];
    }

    $allocation = [];
    foreach ($rows as $row) {
        $idCompany = (int) ($row['idCompany'] ?? 0);
        if ($idCompany <= 0) {
            continue;
        }

        $trait = getTraitDefinition($pdo, (int) ($row['idTrait'] ?? 0));
        if (!$trait || !isCompanyShareTrait($trait)) {
            continue;
        }

        $allocatedRank = (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0);
        if (!isset($allocation[$idCompany])) {
            $allocation[$idCompany] = 0;
        }

        $allocation[$idCompany] += max(0, $allocatedRank);
    }

    return $allocation;
}

function buildCompanyShareAvailabilityForTrait(array $shareTrait, array $companies, array $allocatedByCompany): array
{
    $availableCompanies = [];
    $requiredPercentage = max(0, (int) ($shareTrait['rank'] ?? 0));
    $currentCompanyId = (int) ($shareTrait['companyId'] ?? 0);

    foreach ($companies as $company) {
        if (!companyMatchesShareTrait($shareTrait, $company)) {
            continue;
        }

        $allocatedPercentage = (int) ($allocatedByCompany[(int) $company['id']] ?? 0);
        $remainingPercentage = max(0, 100 - $allocatedPercentage);
        $remainingIncludingCurrent = $remainingPercentage;

        if ((int) $company['id'] === $currentCompanyId) {
            $remainingIncludingCurrent = max(0, $remainingPercentage + $requiredPercentage);
        }

        if ($remainingIncludingCurrent < $requiredPercentage) {
            continue;
        }

        $availableCompanies[] = [
            'id' => (int) $company['id'],
            'companyName' => (string) ($company['companyName'] ?? ''),
            'companyValue' => round((float) ($company['companyValue'] ?? 0), 2),
            'companyTypeKey' => $company['companyTypeKey'] ?? null,
            'companyTypeLabel' => $company['companyTypeLabel'] ?? null,
            'remainingSharePercentage' => $remainingIncludingCurrent,
        ];
    }

    return $availableCompanies;
}

function getCharacterCompanySharePurchaseOptions(PDO $pdo, array $character, string $role, int $currentUserId): array
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return [];
    }

    if (!canIncreaseCompanyShareRank($character, $role, $currentUserId)) {
        return [];
    }

    $characterState = (string) ($character['state'] ?? '');
    if ($characterState === 'draft') {
        return [];
    }

    $characterClass = trim((string) ($character['class'] ?? ''));
    if ($characterClass === '') {
        return [];
    }

    $companies = getCompaniesForShares($pdo);
    if (count($companies) === 0) {
        return [];
    }

    $allocatedByCompany = getCompanyShareAllocationByCompany($pdo);
    $currentBalance = getDisplayedCharacterBankAmount($pdo, $character);
    if ($currentBalance <= 0) {
        return [];
    }

    $linkedTraits = getCharacterTraitLinks($pdo, $idCharacter, ['status']);
    $ownedTraitIds = [];
    foreach ($linkedTraits as $linkedTrait) {
        if (isCompanyShareTrait($linkedTrait)) {
            $ownedTraitIds[(int) ($linkedTrait['idTrait'] ?? 0)] = true;
        }
    }

    $options = [];
    foreach ($companies as $company) {
        $idCompany = (int) ($company['id'] ?? 0);
        if ($idCompany <= 0) {
            continue;
        }

        $unitPrice = round(((float) ($company['companyValue'] ?? 0)) / 100, 2);
        if ($unitPrice <= 0 || $unitPrice > $currentBalance) {
            continue;
        }

        $remainingSharePercentage = max(0, 100 - (int) ($allocatedByCompany[$idCompany] ?? 0));
        if ($remainingSharePercentage < 1) {
            continue;
        }

        foreach (['A', 'B'] as $shareClass) {
            $idTrait = findCompanyShareTraitIdForCompanyType(
                $pdo,
                $characterClass,
                $shareClass,
                (string) ($company['companyTypeKey'] ?? '')
            );

            if ($idTrait === null || $idTrait <= 0 || isset($ownedTraitIds[$idTrait])) {
                continue;
            }

            $options[] = [
                'idCompany' => $idCompany,
                'idTrait' => $idTrait,
                'companyName' => (string) ($company['companyName'] ?? ''),
                'companyValue' => round((float) ($company['companyValue'] ?? 0), 2),
                'companyTypeKey' => $company['companyTypeKey'] ?? null,
                'companyTypeLabel' => $company['companyTypeLabel'] ?? null,
                'remainingSharePercentage' => $remainingSharePercentage,
                'shareClass' => $shareClass,
                'unitPrice' => $unitPrice,
            ];
        }
    }

    usort($options, static function (array $left, array $right): int {
        $companyCompare = strcasecmp((string) ($left['companyName'] ?? ''), (string) ($right['companyName'] ?? ''));
        if ($companyCompare !== 0) {
            return $companyCompare;
        }

        return strcmp((string) ($left['shareClass'] ?? ''), (string) ($right['shareClass'] ?? ''));
    });

    return $options;
}

function getCharacterCompanyShares(PDO $pdo, array $character, string $role, int $currentUserId): array
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return [];
    }

    $allTraitLinks = getCharacterTraitLinks($pdo, $idCharacter, ['status']);
    $shareTraits = array_values(array_filter($allTraitLinks, static fn(array $trait): bool => isCompanyShareTrait($trait)));

    if (count($shareTraits) === 0) {
        return [];
    }

    $companies = getCompaniesForShares($pdo);
    $allocatedByCompany = getCompanyShareAllocationByCompany($pdo);
    $canManageAssignments = canManageCompanyShareAssignments($character, $role, $currentUserId);
    $canIncreaseRank = canIncreaseCompanyShareRank($character, $role, $currentUserId);
    $canDecreaseRank = canDecreaseCompanyShareRank($character, $role, $currentUserId);
    $currentBalance = getDisplayedCharacterBankAmount($pdo, $character);
    $companiesById = [];
    foreach ($companies as $company) {
        $companyId = (int) ($company['id'] ?? 0);
        if ($companyId > 0) {
            $companiesById[$companyId] = $company;
        }
    }

    return array_map(static function (array $shareTrait) use ($companies, $companiesById, $allocatedByCompany, $canManageAssignments, $canIncreaseRank, $canDecreaseRank, $currentBalance): array {
        $currentCompanyId = (int) ($shareTrait['companyId'] ?? 0);
        $allocatedPercentage = $currentCompanyId > 0
            ? (int) ($allocatedByCompany[$currentCompanyId] ?? 0)
            : 0;
        $currentPercentage = max(0, (int) ($shareTrait['rank'] ?? 0));
        $remainingSharePercentage = $currentCompanyId > 0
            ? max(0, 100 - $allocatedPercentage + $currentPercentage)
            : 0;
        $nextPercentageCost = null;
        $canAffordNextRank = false;
        $currentCompany = $currentCompanyId > 0
            ? ($companiesById[$currentCompanyId] ?? null)
            : null;

        if ($currentCompanyId > 0 && isset($shareTrait['companyValue']) && $shareTrait['companyValue'] !== null) {
            $nextPercentageCost = round(((float) $shareTrait['companyValue']) / 100, 2);
            $canAffordNextRank = $nextPercentageCost <= $currentBalance;
        } elseif ($currentCompany !== null) {
            $nextPercentageCost = round(((float) ($currentCompany['companyValue'] ?? 0)) / 100, 2);
            $canAffordNextRank = $nextPercentageCost <= $currentBalance;
        }

        return [
            'idLinkCharacterTrait' => (int) ($shareTrait['id'] ?? 0),
            'idTrait' => (int) ($shareTrait['idTrait'] ?? 0),
            'name' => (string) ($shareTrait['name'] ?? ''),
            'percentage' => $currentPercentage,
            'basePercentage' => (int) ($shareTrait['baseRank'] ?? $currentPercentage),
            'extraPercentage' => (int) ($shareTrait['shareExtraRank'] ?? 0),
            'shareClass' => $shareTrait['shareClass'] ?? null,
            'companyTypeKey' => $shareTrait['companyTypeKey'] ?? null,
            'companyTypeLabel' => $shareTrait['companyTypeLabel'] ?? null,
            'companyTypeDescription' => $shareTrait['companyTypeDescription'] ?? null,
            'companyId' => $currentCompanyId > 0 ? $currentCompanyId : null,
            'companyName' => $shareTrait['companyName'] ?? null,
            'companyValue' => isset($shareTrait['companyValue']) && $shareTrait['companyValue'] !== null
                ? round((float) $shareTrait['companyValue'], 2)
                : ($currentCompany !== null ? round((float) ($currentCompany['companyValue'] ?? 0), 2) : null),
            'companyDescription' => $currentCompany['description'] ?? null,
            'companyLogoUrl' => $currentCompany['logoUrl'] ?? null,
            'companyTypeLabelResolved' => $currentCompany['companyTypeLabel'] ?? ($shareTrait['companyTypeLabel'] ?? null),
            'companyTypeDescriptionResolved' => $currentCompany['companyTypeDescription'] ?? ($shareTrait['companyTypeDescription'] ?? null),
            'remainingSharePercentage' => $remainingSharePercentage,
            'availableCompanies' => buildCompanyShareAvailabilityForTrait($shareTrait, $companies, $allocatedByCompany),
            'canManageAssignments' => $canManageAssignments,
            'canIncreaseRank' => $canIncreaseRank,
            'canDecreaseRank' => $canDecreaseRank,
            'nextPercentageCost' => $nextPercentageCost,
            'sellPercentageValue' => $nextPercentageCost,
            'canAffordNextRank' => $canAffordNextRank,
        ];
    }, $shareTraits);
}

function normalizeCharacterSecuritiesManagerType(mixed $value): string
{
    $normalized = trim((string) $value);
    if (in_array($normalized, ['self', 'bank', 'third'], true)) {
        return $normalized;
    }

    return 'self';
}

function normalizeCharacterSecuritiesRiskProfile(mixed $value): int
{
    return max(1, min(5, (int) $value));
}

function getCharacterSecuritiesRiskProfileOptions(): array
{
    return [
        1 => ['label' => 'Volatiel', 'variationPercentage' => 9.0],
        2 => ['label' => 'Dynamisch', 'variationPercentage' => 7.0],
        3 => ['label' => 'Evenwichtig', 'variationPercentage' => 5.0],
        4 => ['label' => 'Voorzichtig', 'variationPercentage' => 3.0],
        5 => ['label' => 'Veilig', 'variationPercentage' => 1.0],
    ];
}

function getCharacterSecuritiesBaseReturnRateBySkillLevel(int $skillLevel): float
{
    return match (max(0, min(4, $skillLevel))) {
        0 => 2.0,
        1 => 3.0,
        2 => 5.0,
        3 => 8.0,
        default => 13.0,
    };
}

function getCharacterSecuritiesSnapshotVariationPercentageByRiskProfile(int $riskProfile): float
{
    $options = getCharacterSecuritiesRiskProfileOptions();
    return (float) ($options[normalizeCharacterSecuritiesRiskProfile($riskProfile)]['variationPercentage'] ?? 5.0);
}

function characterHasSkillSpecialisation(PDO $pdo, int $idCharacter, int $idSkill, int $idSkillSpecialisation, ?string $fallbackNameLike = null): bool
{
    if ($idCharacter <= 0 || $idSkill <= 0) {
        return false;
    }

    try {
        $params = [
            'idCharacter' => $idCharacter,
            'idSkill' => $idSkill,
            'idSkillSpecialisation' => $idSkillSpecialisation,
        ];
        $fallbackClause = '';
        if ($fallbackNameLike !== null && trim($fallbackNameLike) !== '') {
            $fallbackClause = ' OR (ss.idSkill = :idSkill AND LOWER(ss.name) LIKE :fallbackNameLike)';
            $params['fallbackNameLike'] = '%' . mb_strtolower(trim($fallbackNameLike)) . '%';
        }

        $row = dbOne(
            $pdo,
            "SELECT cs.id
               FROM tblCharacterSpecialisation AS cs
               JOIN tblSkillSpecialisation AS ss
                 ON ss.id = cs.idSkillSpecialisation
              WHERE cs.idCharacter = :idCharacter
                AND cs.idSkill = :idSkill
                AND (ss.id = :idSkillSpecialisation{$fallbackClause})
              LIMIT 1",
            $params
        );

        return $row !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function getCharacterFiscalityInvestmentSkillLevel(PDO $pdo, int $idCharacter): int
{
    if ($idCharacter <= 0) {
        return 0;
    }

    $level = getCharacterSkillLevelById($pdo, $idCharacter, 6);
    if (characterHasSkillSpecialisation($pdo, $idCharacter, 6, 55, 'invest')) {
        $level += 1;
    }

    return max(0, min(4, $level));
}

function getCharacterSecuritiesManagerOptions(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT id, firstName, lastName
               FROM tblCharacter
              WHERE state = 'active'
                AND id <> :idCharacter
              ORDER BY firstName ASC, lastName ASC, id ASC",
            ['idCharacter' => $idCharacter]
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'displayName' => formatCharacterDisplayName($row),
        ];
    }, $rows);
}

function getCharacterSecuritiesPortfolio(PDO $pdo, array $character): array
{
    $idCharacter = (int) ($character['id'] ?? 0);
    $managerType = normalizeCharacterSecuritiesManagerType($character['securitiesManagerType'] ?? 'self');
    $riskProfile = normalizeCharacterSecuritiesRiskProfile($character['securitiesRiskProfile'] ?? 3);
    $managerCharacterId = $managerType === 'third'
        ? max(0, (int) ($character['securitiesManagerCharacterId'] ?? 0))
        : 0;
    $balance = round((float) ($character['securitiesaccount'] ?? 0), 2);
    if (!is_finite($balance) || $balance < 0) {
        $balance = 0.0;
    }

    $managerSkillLevel = 0;
    if ($managerType === 'bank') {
        $managerSkillLevel = 2;
    } elseif ($managerType === 'third' && $managerCharacterId > 0) {
        $managerSkillLevel = getCharacterFiscalityInvestmentSkillLevel($pdo, $managerCharacterId);
    } elseif ($idCharacter > 0) {
        $managerSkillLevel = getCharacterFiscalityInvestmentSkillLevel($pdo, $idCharacter);
    }

    return [
        'balance' => $balance,
        'managerType' => $managerType,
        'managerCharacterId' => $managerCharacterId > 0 ? $managerCharacterId : null,
        'riskProfile' => $riskProfile,
        'managerSkillLevel' => $managerSkillLevel,
    ];
}

function generateCharacterSecuritiesVariationPercentage(float $maxVariationPercentage): float
{
    $limit = max(0, (int) round($maxVariationPercentage * 100));
    if ($limit <= 0) {
        return 0.0;
    }

    return random_int(-$limit, $limit) / 100;
}

function calculateCharacterSecuritiesSnapshotData(PDO $pdo, array $character, ?float $forcedVariationPercentage = null): array
{
    $portfolio = getCharacterSecuritiesPortfolio($pdo, $character);
    $balance = round((float) ($portfolio['balance'] ?? 0), 2);
    $riskProfile = normalizeCharacterSecuritiesRiskProfile($portfolio['riskProfile'] ?? 3);
    $managerType = normalizeCharacterSecuritiesManagerType($portfolio['managerType'] ?? 'self');
    $managerCharacterId = max(0, (int) ($portfolio['managerCharacterId'] ?? 0));
    $managerSkillLevel = max(0, min(4, (int) ($portfolio['managerSkillLevel'] ?? 0)));
    $basePercentage = getCharacterSecuritiesBaseReturnRateBySkillLevel($managerSkillLevel);
    $variationLimitPercentage = getCharacterSecuritiesSnapshotVariationPercentageByRiskProfile($riskProfile);
    $variationPercentage = $forcedVariationPercentage !== null
        ? round(max(-$variationLimitPercentage, min($variationLimitPercentage, $forcedVariationPercentage)), 2)
        : generateCharacterSecuritiesVariationPercentage($variationLimitPercentage);
    $totalPercentage = round($basePercentage + $variationPercentage, 2);
    $returnAmount = round($balance * ($totalPercentage / 100), 2);
    $status = $balance > 0 ? 'pending' : 'none';

    return [
        'balanceSnapshot' => $balance,
        'managerType' => $managerType,
        'managerCharacterId' => $managerCharacterId > 0 ? $managerCharacterId : null,
        'riskProfile' => $riskProfile,
        'managerSkillLevel' => $managerSkillLevel,
        'basePercentage' => $basePercentage,
        'variationLimitPercentage' => $variationLimitPercentage,
        'variationPercentage' => $variationPercentage,
        'returnPercentage' => $totalPercentage,
        'returnAmount' => $returnAmount,
        'status' => $status,
    ];
}

function getCharacterSecuritiesTransactions(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT id, direction, securitiesAmount, bankAmount, transactionDate, description
               FROM tblCharacterSecuritiesTransaction
              WHERE idCharacter = :idCharacter
              ORDER BY transactionDate DESC, id DESC",
            ['idCharacter' => $idCharacter]
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        $direction = trim((string) ($row['direction'] ?? ''));
        $bankAmount = round((float) ($row['bankAmount'] ?? 0), 2);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'type' => 'securities_' . ($direction !== '' ? $direction : 'transaction'),
            'direction' => $bankAmount < 0 ? 'outgoing' : 'incoming',
            'counterpartyName' => 'Effectenportefeuille',
            'amount' => abs($bankAmount),
            'transactionDate' => (string) ($row['transactionDate'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'canDelete' => false,
        ];
    }, $rows);
}
