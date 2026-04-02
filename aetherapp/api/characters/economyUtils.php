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
    if ((string) ($character['class'] ?? '') !== 'upper class') {
        return 1.0;
    }

    $linkedTitle = null;
    foreach ($traitLinks as $trait) {
        if ((string) ($trait['traitGroup'] ?? '') === 'Adellijke titel') {
            $linkedTitle = $trait;
            break;
        }
    }

    if ($linkedTitle === null) {
        return 1.0;
    }

    $linkedTitleName = mb_strtolower(trim((string) ($linkedTitle['name'] ?? '')));
    if ($linkedTitleName === 'familiehoofd') {
        return 2.0;
    }

    return 1.5;
}

function characterHasTraitByName(PDO $pdo, int $idCharacter, string $traitName): bool
{
    $searchName = mb_strtolower(trim($traitName));
    if ($idCharacter <= 0 || $searchName === '') {
        return false;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
    foreach ($traitLinks as $trait) {
        if (mb_strtolower(trim((string) ($trait['name'] ?? ''))) === $searchName) {
            return true;
        }
    }

    return false;
}

function getDraftBankAccountAmountForCharacter(PDO $pdo, array $character): float
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return 0.0;
    }

    $traitLinks = getCharacterTraitLinks($pdo, $idCharacter);
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

        if ((string) ($trait['traitGroup'] ?? '') === 'Adeldom') {
            $income *= $nobilityIncomeMultiplier;
        }

        $totalIncome += $income;
    }

    $multiplier = characterHasTraitByName($pdo, $idCharacter, 'Spaarder') ? 15.0 : 10.0;

    return round($totalIncome * $multiplier, 2);
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
            'direction' => $isOutgoing ? 'outgoing' : 'incoming',
            'counterpartyName' => $nameCache[$counterpartyId],
            'amount' => round((float) $row['amount'], 2),
            'transactionDate' => (string) $row['transactionDate'],
            'description' => (string) ($row['description'] ?? ''),
        ];
    }

    return $transactions;
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
            'SELECT id, companyName, companyValue
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
            'companyValue' => round((float) ($row['companyValue'] ?? 0), 2),
        ];

        return enrichCompanyWithType($company);
    }, $rows);
}

function getCompanyShareAllocationByCompany(PDO $pdo): array
{
    try {
        $rows = dbAll(
            $pdo,
            'SELECT
                lctc.idCompany,
                lct.id AS idLinkCharacterTrait,
                lct.idTrait,
                lct.rankValue,
                lctc.extraPercentage,
                t.name
             FROM tblLinkCharacterTraitCompany AS lctc
             JOIN tblLinkCharacterTrait AS lct
               ON lct.id = lctc.idLinkCharacterTrait
             JOIN tblTrait AS t
               ON t.id = lct.idTrait
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

        if (!isCompanyShareTrait(['name' => (string) ($row['name'] ?? '')])) {
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
        if (($shareTrait['companyTypeKey'] ?? null) !== ($company['companyTypeKey'] ?? null)) {
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
    $currentBalance = getDisplayedCharacterBankAmount($pdo, $character);

    return array_map(static function (array $shareTrait) use ($companies, $allocatedByCompany, $canManageAssignments, $canIncreaseRank, $currentBalance): array {
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

        if ($currentCompanyId > 0 && isset($shareTrait['companyValue']) && $shareTrait['companyValue'] !== null) {
            $nextPercentageCost = round(((float) $shareTrait['companyValue']) / 100, 2);
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
                : null,
            'remainingSharePercentage' => $remainingSharePercentage,
            'availableCompanies' => buildCompanyShareAvailabilityForTrait($shareTrait, $companies, $allocatedByCompany),
            'canManageAssignments' => $canManageAssignments,
            'canIncreaseRank' => $canIncreaseRank,
            'nextPercentageCost' => $nextPercentageCost,
            'canAffordNextRank' => $canAffordNextRank,
        ];
    }, $shareTraits);
}
