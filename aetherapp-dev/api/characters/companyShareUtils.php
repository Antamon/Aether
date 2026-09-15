<?php
declare(strict_types=1);

require_once __DIR__ . '/../companies/companyUtils.php';

function normalizeCompanyShareTraitName(string $traitName): string
{
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $traitName)));
}

function getCompanyShareTraitDefinitions(): array
{
    return [
        normalizeCompanyShareTraitName('B-aandelen in een micro-onderneming') => ['shareClass' => 'B', 'companyTypeKey' => 'micro'],
        normalizeCompanyShareTraitName('A-aandelen in een micro-onderneming') => ['shareClass' => 'A', 'companyTypeKey' => 'micro'],
        normalizeCompanyShareTraitName('B-aandelen in een familiebedrijf') => ['shareClass' => 'B', 'companyTypeKey' => 'family'],
        normalizeCompanyShareTraitName('A-aandelen in een familiebedrijf') => ['shareClass' => 'A', 'companyTypeKey' => 'family'],
        normalizeCompanyShareTraitName('B-aandelen in een nationale onderneming') => ['shareClass' => 'B', 'companyTypeKey' => 'national'],
        normalizeCompanyShareTraitName('A-aandelen in een nationale onderneming') => ['shareClass' => 'A', 'companyTypeKey' => 'national'],
        normalizeCompanyShareTraitName('B-aandelen in een kleine internationale groep') => ['shareClass' => 'B', 'companyTypeKey' => 'small_international'],
        normalizeCompanyShareTraitName('A-aandelen in een kleine internationale groep') => ['shareClass' => 'A', 'companyTypeKey' => 'small_international'],
        normalizeCompanyShareTraitName('B-aandelen in een grote internationale groep') => ['shareClass' => 'B', 'companyTypeKey' => 'large_international'],
        normalizeCompanyShareTraitName('A-aandelen in een grote internationale groep') => ['shareClass' => 'A', 'companyTypeKey' => 'large_international'],
        normalizeCompanyShareTraitName('B-aandelen in een kleine onderneming') => [
            'shareClass' => 'B',
            'companyTypeKey' => 'small_enterprise',
            'companyTypeLabel' => 'Kleine onderneming',
            'companyTypeDescription' => 'Dit aandeeltype kan gekoppeld worden aan micro-ondernemingen en familiebedrijven.',
            'allowedCompanyTypeKeys' => ['micro', 'family'],
        ],
        normalizeCompanyShareTraitName('A-aandelen in een kleine onderneming') => [
            'shareClass' => 'A',
            'companyTypeKey' => 'small_enterprise',
            'companyTypeLabel' => 'Kleine onderneming',
            'companyTypeDescription' => 'Dit aandeeltype kan gekoppeld worden aan micro-ondernemingen en familiebedrijven.',
            'allowedCompanyTypeKeys' => ['micro', 'family'],
        ],
        normalizeCompanyShareTraitName('B-aandelen in een grote onderneming') => [
            'shareClass' => 'B',
            'companyTypeKey' => 'large_enterprise',
            'companyTypeLabel' => 'Grote onderneming',
            'companyTypeDescription' => 'Dit aandeeltype kan gekoppeld worden aan nationale ondernemingen en internationale groepen.',
            'allowedCompanyTypeKeys' => ['national', 'small_international', 'large_international'],
        ],
        normalizeCompanyShareTraitName('A-aandelen in een grote onderneming') => [
            'shareClass' => 'A',
            'companyTypeKey' => 'large_enterprise',
            'companyTypeLabel' => 'Grote onderneming',
            'companyTypeDescription' => 'Dit aandeeltype kan gekoppeld worden aan nationale ondernemingen en internationale groepen.',
            'allowedCompanyTypeKeys' => ['national', 'small_international', 'large_international'],
        ],
    ];
}

function getCompanyShareTraitMetadataByName(string $traitName): ?array
{
    $definitions = getCompanyShareTraitDefinitions();
    $normalizedName = normalizeCompanyShareTraitName($traitName);

    if (!isset($definitions[$normalizedName])) {
        return null;
    }

    $definition = $definitions[$normalizedName];
    $companyTypeDefinitions = getCompanyTypeDefinitions();
    $companyType = $companyTypeDefinitions[$definition['companyTypeKey']] ?? null;
    $allowedCompanyTypeKeys = $definition['allowedCompanyTypeKeys'] ?? [$definition['companyTypeKey']];

    return [
        'shareClass' => $definition['shareClass'],
        'companyTypeKey' => $definition['companyTypeKey'],
        'companyTypeLabel' => $definition['companyTypeLabel'] ?? ($companyType['label'] ?? ''),
        'companyTypeDescription' => $definition['companyTypeDescription'] ?? ($companyType['description'] ?? ''),
        'allowedCompanyTypeKeys' => array_values(array_filter($allowedCompanyTypeKeys, static fn($key): bool => is_string($key) && $key !== '')),
    ];
}

function getCompanyShareTraitMetadata(array $trait): ?array
{
    if (!empty($trait['isCompanyShare']) || !empty($trait['shareClass'])) {
        $companyTypeDefinitions = getCompanyTypeDefinitions();
        $allowedCompanyTypeKeys = array_values(array_filter(
            is_array($trait['allowedCompanyTypeKeys'] ?? null)
                ? $trait['allowedCompanyTypeKeys']
                : [],
            static fn($key): bool => is_string($key) && $key !== ''
        ));
        $firstAllowedCompanyTypeKey = $allowedCompanyTypeKeys[0] ?? ($trait['companyTypeKey'] ?? '');
        $firstAllowedCompanyType = $companyTypeDefinitions[$firstAllowedCompanyTypeKey] ?? null;

        return [
            'shareClass' => isset($trait['shareClass']) ? (string) $trait['shareClass'] : null,
            'companyTypeKey' => isset($trait['companyTypeKey']) ? (string) $trait['companyTypeKey'] : $firstAllowedCompanyTypeKey,
            'companyTypeLabel' => isset($trait['companyTypeLabel']) && $trait['companyTypeLabel'] !== null && $trait['companyTypeLabel'] !== ''
                ? (string) $trait['companyTypeLabel']
                : ($firstAllowedCompanyType['label'] ?? ''),
            'companyTypeDescription' => isset($trait['companyTypeDescription']) && $trait['companyTypeDescription'] !== null && $trait['companyTypeDescription'] !== ''
                ? (string) $trait['companyTypeDescription']
                : ($firstAllowedCompanyType['description'] ?? ''),
            'allowedCompanyTypeKeys' => $allowedCompanyTypeKeys,
        ];
    }

    return getCompanyShareTraitMetadataByName((string) ($trait['name'] ?? ''));
}

function isCompanyShareTrait(array $trait): bool
{
    return getCompanyShareTraitMetadata($trait) !== null;
}

function getCompanyShareStepValue(array $trait, string $field, int $default): int
{
    $value = isset($trait[$field]) ? (int) $trait[$field] : $default;
    return $value > 0 ? $value : $default;
}

function getCompanyShareDraftStep(array $trait): int
{
    return getCompanyShareStepValue($trait, 'shareDraftStep', 10);
}

function getCompanyShareCostStep(array $trait): int
{
    return getCompanyShareStepValue($trait, 'shareCostStep', 10);
}

function getCompanyShareStaffStep(array $trait): int
{
    return getCompanyShareStepValue($trait, 'shareStaffStep', 10);
}

function normalizeCompanyShareBaseRankValue(int $baseRank, int $minimumStep = 10): int
{
    return max(0, $baseRank);
}

function getCompanyShareBaseRank(array $trait): int
{
    $draftStep = getCompanyShareDraftStep($trait);

    if (array_key_exists('baseRank', $trait)) {
        return normalizeCompanyShareBaseRankValue((int) $trait['baseRank'], $draftStep);
    }

    return normalizeCompanyShareBaseRankValue((int) ($trait['rank'] ?? 0), $draftStep);
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

function getCompanySharePointPurchaseUnits(int $baseRank, int $step = 10): int
{
    $normalizedStep = $step > 0 ? $step : 10;
    $normalizedRank = normalizeCompanyShareBaseRankValue($baseRank, $normalizedStep);
    if ($normalizedRank <= 0) {
        return 0;
    }

    return (int) ceil($normalizedRank / $normalizedStep);
}

function getCompanySharePointPurchaseUnitsForTrait(array $trait, int $baseRank): int
{
    return getCompanySharePointPurchaseUnits($baseRank, getCompanyShareCostStep($trait));
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

    $normalizedRank = getCompanyShareTotalRank([
        'name' => (string) ($trait['name'] ?? ''),
        'baseRank' => $effectiveRank,
        'isCompanyShare' => true,
        'shareDraftStep' => $trait['shareDraftStep'] ?? null,
        'shareExtraRank' => 0,
    ]);

    if ($normalizedRank <= 0) {
        return 0;
    }

    $rankStep = getCompanyShareStaffStep($trait);
    $rankBucket = (int) floor(max(0, $normalizedRank - 1) / $rankStep) + 1;
    return $baseStaffRequirements * max(1, $rankBucket);
}

function companyMatchesShareTrait(array $trait, array $company): bool
{
    $metadata = getCompanyShareTraitMetadata($trait);
    if ($metadata === null) {
        return false;
    }

    $allowedCompanyTypeKeys = $metadata['allowedCompanyTypeKeys'] ?? [];
    $companyTypeKey = (string) ($company['companyTypeKey'] ?? '');

    return $companyTypeKey !== '' && in_array($companyTypeKey, $allowedCompanyTypeKeys, true);
}

function canManageCompanyShareAssignments(array $character, string $role, int $currentUserId): bool
{
    if (isPrivilegedUserRole($role)) {
        return true;
    }

    return $role === 'participant'
        && (string) ($character['type'] ?? '') === 'player'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}

function canPrivilegedUserTradeCompanySharesForCharacter(array $character): bool
{
    $characterType = (string) ($character['type'] ?? '');
    return $characterType === 'player' || $characterType === 'extra';
}

function canIncreaseCompanyShareRank(array $character, string $role, int $currentUserId): bool
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return false;
    }

    if (isPrivilegedUserRole($role)) {
        return canPrivilegedUserTradeCompanySharesForCharacter($character);
    }

    return $role === 'participant'
        && (string) ($character['type'] ?? '') === 'player'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}

function canDecreaseCompanyShareRank(array $character, string $role, int $currentUserId): bool
{
    if ((string) ($character['state'] ?? '') === 'draft') {
        return false;
    }

    if (isPrivilegedUserRole($role)) {
        return canPrivilegedUserTradeCompanySharesForCharacter($character);
    }

    return $role === 'participant'
        && (string) ($character['type'] ?? '') === 'player'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}

function findCompanyShareTraitIdForCompanyType(PDO $pdo, string $traitClass, string $shareClass, string $companyTypeKey): ?int
{
    $normalizedTraitClass = trim($traitClass);
    $normalizedShareClass = trim($shareClass);
    $normalizedCompanyTypeKey = trim($companyTypeKey);

    if ($normalizedTraitClass === '' || $normalizedShareClass === '' || $normalizedCompanyTypeKey === '') {
        return null;
    }

    try {
        $row = dbOne(
            $pdo,
            "SELECT t.id
             FROM tblTrait AS t
             JOIN tblTraitShareDefinition AS tsd
               ON tsd.idTrait = t.id
             JOIN tblTraitShareAllowedCompanyType AS tsact
               ON tsact.idTrait = t.id
              AND tsact.companyTypeKey = :companyTypeKey
             WHERE t.`class` = :traitClass
               AND t.`type` = 'status'
               AND tsd.shareClass = :shareClass
             ORDER BY COALESCE(t.cost, 0) ASC, t.id ASC
             LIMIT 1",
            [
                'companyTypeKey' => $normalizedCompanyTypeKey,
                'traitClass' => $normalizedTraitClass,
                'shareClass' => $normalizedShareClass,
            ]
        );

        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }
    } catch (Throwable $e) {
        // Fall back to name-based metadata below when the share metadata tables are unavailable.
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT id, name
             FROM tblTrait
             WHERE `class` = :traitClass
               AND `type` = 'status'
             ORDER BY COALESCE(cost, 0) ASC, id ASC",
            ['traitClass' => $normalizedTraitClass]
        );
    } catch (Throwable $e) {
        return null;
    }

    foreach ($rows as $row) {
        $metadata = getCompanyShareTraitMetadataByName((string) ($row['name'] ?? ''));
        if ($metadata === null) {
            continue;
        }

        if (
            (string) ($metadata['shareClass'] ?? '') === $normalizedShareClass
            && (string) ($metadata['companyTypeKey'] ?? '') === $normalizedCompanyTypeKey
        ) {
            return (int) ($row['id'] ?? 0);
        }
    }

    return null;
}

function remapCompanyShareTraitsForCompany(PDO $pdo, int $idCompany, string $targetCompanyTypeKey): int
{
    if ($idCompany <= 0 || trim($targetCompanyTypeKey) === '') {
        return 0;
    }

    $rows = dbAll(
        $pdo,
        'SELECT
            lct.id AS idLinkCharacterTrait,
            lct.idTrait,
            t.name,
            t.`class` AS traitClass
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct
           ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTrait AS t
           ON t.id = lct.idTrait
         WHERE lctc.idCompany = :idCompany',
        ['idCompany' => $idCompany]
    );

    $updatedCount = 0;
    foreach ($rows as $row) {
        $metadata = getCompanyShareTraitMetadataByName((string) ($row['name'] ?? ''));
        if ($metadata === null) {
            continue;
        }

        $targetTraitId = findCompanyShareTraitIdForCompanyType(
            $pdo,
            (string) ($row['traitClass'] ?? ''),
            (string) ($metadata['shareClass'] ?? ''),
            $targetCompanyTypeKey
        );

        if ($targetTraitId === null || $targetTraitId <= 0) {
            throw new RuntimeException('Geen passende aandelen-trait gevonden voor het nieuwe bedrijfstype.');
        }

        if ($targetTraitId === (int) ($row['idTrait'] ?? 0)) {
            continue;
        }

        $stmt = $pdo->prepare(
            'UPDATE tblLinkCharacterTrait
             SET idTrait = :idTrait
             WHERE id = :idLinkCharacterTrait'
        );
        $stmt->execute([
            'idTrait' => $targetTraitId,
            'idLinkCharacterTrait' => (int) ($row['idLinkCharacterTrait'] ?? 0),
        ]);

        $updatedCount++;
    }

    return $updatedCount;
}
