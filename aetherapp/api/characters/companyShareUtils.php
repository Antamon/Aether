<?php
declare(strict_types=1);

require_once __DIR__ . '/../companies/companyUtils.php';

function getCompanyShareTraitDefinitions(): array
{
    return [
        'B-aandelen in een micro-onderneming' => ['shareClass' => 'B', 'companyTypeKey' => 'micro'],
        'A-aandelen in een micro-onderneming' => ['shareClass' => 'A', 'companyTypeKey' => 'micro'],
        'B-aandelen in een familiebedrijf' => ['shareClass' => 'B', 'companyTypeKey' => 'family'],
        'A-aandelen in een familiebedrijf' => ['shareClass' => 'A', 'companyTypeKey' => 'family'],
        'B-aandelen in een nationale onderneming' => ['shareClass' => 'B', 'companyTypeKey' => 'national'],
        'A-aandelen in een nationale onderneming' => ['shareClass' => 'A', 'companyTypeKey' => 'national'],
        'B-aandelen in een kleine internationale groep' => ['shareClass' => 'B', 'companyTypeKey' => 'small_international'],
        'A-aandelen in een kleine internationale groep' => ['shareClass' => 'A', 'companyTypeKey' => 'small_international'],
        'B-aandelen in een grote internationale groep' => ['shareClass' => 'B', 'companyTypeKey' => 'large_international'],
        'A-aandelen in een grote internationale groep' => ['shareClass' => 'A', 'companyTypeKey' => 'large_international'],
    ];
}

function getCompanyShareTraitMetadataByName(string $traitName): ?array
{
    $definitions = getCompanyShareTraitDefinitions();
    $normalizedName = trim($traitName);

    if (!isset($definitions[$normalizedName])) {
        return null;
    }

    $definition = $definitions[$normalizedName];
    $companyTypeDefinitions = getCompanyTypeDefinitions();
    $companyType = $companyTypeDefinitions[$definition['companyTypeKey']] ?? null;

    return [
        'shareClass' => $definition['shareClass'],
        'companyTypeKey' => $definition['companyTypeKey'],
        'companyTypeLabel' => $companyType['label'] ?? '',
        'companyTypeDescription' => $companyType['description'] ?? '',
    ];
}

function getCompanyShareTraitMetadata(array $trait): ?array
{
    return getCompanyShareTraitMetadataByName((string) ($trait['name'] ?? ''));
}

function isCompanyShareTrait(array $trait): bool
{
    return getCompanyShareTraitMetadata($trait) !== null;
}

function getCompanyShareBaseRank(array $trait): int
{
    if (array_key_exists('baseRank', $trait)) {
        return max(0, (int) $trait['baseRank']);
    }

    return max(0, (int) ($trait['rank'] ?? 0));
}

function getCompanyShareExtraRank(array $trait): int
{
    return max(0, (int) ($trait['shareExtraRank'] ?? 0));
}

function getCompanyShareTotalRank(array $trait): int
{
    if (!isCompanyShareTrait($trait)) {
        return max(0, (int) ($trait['rank'] ?? 0));
    }

    return getCompanyShareBaseRank($trait) + getCompanyShareExtraRank($trait);
}

function getCompanySharePointPurchaseUnits(int $baseRank): int
{
    $normalizedRank = max(0, $baseRank);
    if ($normalizedRank <= 0) {
        return 0;
    }

    return (int) ceil($normalizedRank / 10);
}

function getEffectiveTraitStaffRequirements(array $trait, int $effectiveRank): int
{
    $baseStaffRequirements = (int) ($trait['rawStaffRequirements'] ?? $trait['staffRequirements'] ?? 0);
    if ($baseStaffRequirements <= 0) {
        return 0;
    }

    if (!isCompanyShareTrait($trait)) {
        return $baseStaffRequirements;
    }

    if ($effectiveRank <= 0) {
        return 0;
    }

    $rankBucket = (int) floor(max(0, $effectiveRank - 1) / 10) + 1;
    return $baseStaffRequirements * max(1, $rankBucket);
}

function canManageCompanyShareAssignments(array $character, string $role, int $currentUserId): bool
{
    if (isPrivilegedUserRole($role)) {
        return true;
    }

    return $role === 'participant'
        && (string) ($character['type'] ?? '') === 'player'
        && (string) ($character['state'] ?? '') === 'draft'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}

function canIncreaseCompanyShareRank(array $character, string $role, int $currentUserId): bool
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return false;
    }

    if (isPrivilegedUserRole($role)) {
        return true;
    }

    return $role === 'participant'
        && (string) ($character['type'] ?? '') === 'player'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}
