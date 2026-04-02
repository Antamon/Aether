<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/companyShareUtils.php';

function isGroupedTrait(array $trait): bool
{
    return !empty($trait['grouped']);
}

function calculateTraitPointCost(array $trait, int $rankValue): int
{
    $cost = (int) ($trait['cost'] ?? 0);
    if (isCompanyShareTrait($trait)) {
        return $cost * getCompanySharePointPurchaseUnits($rankValue);
    }

    $rankType = (string) ($trait['rankType'] ?? 'singular');
    if ($rankType === 'singular') {
        return $cost;
    }

    return $cost * abs($rankValue);
}

function buildTypeFilterClause(array $types, array &$params, string $prefix = 'type'): string
{
    if (count($types) === 0) {
        return '';
    }

    $placeholders = [];
    foreach (array_values($types) as $index => $type) {
        $key = $prefix . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $type;
    }

    return ' AND `type` IN (' . implode(', ', $placeholders) . ')';
}

function getTraitsForCharacterClass(PDO $pdo, string $characterClass, array $types = ['status', 'quality']): array
{
    try {
        $params = ['characterClass' => $characterClass];
        $typeClause = buildTypeFilterClause($types, $params, 'traitType');
        $rows = dbAll(
            $pdo,
            "SELECT
                id,
                name,
                `class`,
                `type`,
                isUnique,
                rankType,
                traitGroup,
                grouped,
                cost,
                income,
                evolution,
                staffRequirements,
                description
             FROM tblTrait
             WHERE (`class` = :characterClass OR `class` = 'all')
             {$typeClause}
             ORDER BY traitGroup, name",
            $params
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        $shareMetadata = getCompanyShareTraitMetadataByName((string) ($row['name'] ?? ''));

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'class' => $row['class'],
            'type' => $row['type'],
            'isUnique' => (bool) $row['isUnique'],
            'rankType' => $row['rankType'],
            'traitGroup' => $row['traitGroup'],
            'grouped' => (bool) ($row['grouped'] ?? false),
            'cost' => isset($row['cost']) ? (int) $row['cost'] : 0,
            'income' => isset($row['income']) ? (int) $row['income'] : null,
            'evolution' => isset($row['evolution']) ? (float) $row['evolution'] : null,
            'staffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
            'rawStaffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
            'description' => $row['description'] ?? '',
            'isCompanyShare' => $shareMetadata !== null,
            'shareClass' => $shareMetadata['shareClass'] ?? null,
            'companyTypeKey' => $shareMetadata['companyTypeKey'] ?? null,
            'companyTypeLabel' => $shareMetadata['companyTypeLabel'] ?? null,
            'companyTypeDescription' => $shareMetadata['companyTypeDescription'] ?? null,
        ];
    }, $rows);
}

function getCharacterTraitLinks(PDO $pdo, int $idCharacter, array $types = []): array
{
    $rows = [];

    try {
        $params = ['idCharacter' => $idCharacter];
        $typeClause = buildTypeFilterClause($types, $params, 'linkType');
        $rows = dbAll(
            $pdo,
            "SELECT
                lct.id,
                lct.idTrait,
                lct.rankValue,
                t.name,
                t.`class`,
                t.`type`,
                t.isUnique,
                t.rankType,
                t.traitGroup,
                t.grouped,
                t.cost,
                t.income,
                t.evolution,
                t.staffRequirements,
                t.description,
                lct.rankValue AS baseRankValue,
                COALESCE(lctc.extraPercentage, 0) AS extraPercentage,
                lctc.idCompany,
                c.companyName,
                c.companyValue
             FROM tblLinkCharacterTrait AS lct
             JOIN tblTrait AS t
                   ON t.id = lct.idTrait
             LEFT JOIN tblLinkCharacterTraitCompany AS lctc
                    ON lctc.idLinkCharacterTrait = lct.id
             LEFT JOIN tblCompany AS c
                    ON c.id = lctc.idCompany
             WHERE lct.idCharacter = :idCharacter
             {$typeClause}
             ORDER BY t.traitGroup, t.name",
            $params
        );
    } catch (Throwable $e) {
        try {
            $params = ['idCharacter' => $idCharacter];
            $typeClause = buildTypeFilterClause($types, $params, 'linkTypeFallback');
            $rows = dbAll(
                $pdo,
                "SELECT
                    lct.id,
                    lct.idTrait,
                    lct.rankValue,
                    t.name,
                    t.`class`,
                    t.`type`,
                    t.isUnique,
                    t.rankType,
                    t.traitGroup,
                    t.grouped,
                    t.cost,
                    t.income,
                    t.evolution,
                    t.staffRequirements,
                    t.description,
                    lct.rankValue AS baseRankValue,
                    0 AS extraPercentage,
                    NULL AS idCompany,
                    NULL AS companyName,
                    NULL AS companyValue
                 FROM tblLinkCharacterTrait AS lct
                 JOIN tblTrait AS t
                       ON t.id = lct.idTrait
                 WHERE lct.idCharacter = :idCharacter
                 {$typeClause}
                 ORDER BY t.traitGroup, t.name",
                $params
            );
        } catch (Throwable $fallbackException) {
            return [];
        }
    }

    return array_map(static function (array $row): array {
        $shareMetadata = getCompanyShareTraitMetadataByName((string) ($row['name'] ?? ''));
        $baseRank = (int) ($row['baseRankValue'] ?? $row['rankValue'] ?? 0);
        $extraRank = $shareMetadata !== null ? (int) ($row['extraPercentage'] ?? 0) : 0;
        $effectiveRank = $shareMetadata !== null ? $baseRank + $extraRank : (int) $row['rankValue'];

        $link = [
            'id' => (int) $row['id'],
            'idTrait' => (int) $row['idTrait'],
            'rank' => $effectiveRank,
            'baseRank' => $baseRank,
            'shareExtraRank' => $extraRank,
            'name' => $row['name'],
            'class' => $row['class'],
            'type' => $row['type'],
            'isUnique' => (bool) $row['isUnique'],
            'rankType' => $row['rankType'],
            'traitGroup' => $row['traitGroup'],
            'grouped' => (bool) ($row['grouped'] ?? false),
            'cost' => isset($row['cost']) ? (int) $row['cost'] : 0,
            'income' => isset($row['income']) ? (int) $row['income'] : null,
            'evolution' => isset($row['evolution']) ? (float) $row['evolution'] : null,
            'staffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
            'rawStaffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
            'description' => $row['description'] ?? '',
            'isCompanyShare' => $shareMetadata !== null,
            'shareClass' => $shareMetadata['shareClass'] ?? null,
            'companyTypeKey' => $shareMetadata['companyTypeKey'] ?? null,
            'companyTypeLabel' => $shareMetadata['companyTypeLabel'] ?? null,
            'companyTypeDescription' => $shareMetadata['companyTypeDescription'] ?? null,
            'companyId' => isset($row['idCompany']) ? (int) $row['idCompany'] : null,
            'companyName' => $row['companyName'] ?? null,
            'companyValue' => isset($row['companyValue']) ? round((float) $row['companyValue'], 2) : null,
        ];

        $link['staffRequirements'] = getEffectiveTraitStaffRequirements($link, $effectiveRank);

        return $link;
    }, $rows);
}

function buildCharacterTraitGroups(PDO $pdo, int $idCharacter, string $characterClass, array $types = ['status', 'quality']): array
{
    $options = getTraitsForCharacterClass($pdo, $characterClass, $types);
    $links = getCharacterTraitLinks($pdo, $idCharacter, $types);

    $groupMap = [];

    foreach ($options as $option) {
        $groupName = $option['traitGroup'];
        if (!isset($groupMap[$groupName])) {
            $groupMap[$groupName] = [
                'name' => $groupName,
                'grouped' => false,
                'options' => [],
                'linkedTraits' => [],
            ];
        }

        $groupMap[$groupName]['grouped'] = $groupMap[$groupName]['grouped'] || (bool) $option['grouped'];
        $groupMap[$groupName]['options'][] = $option;
    }

    foreach ($links as $link) {
        $groupName = $link['traitGroup'];
        if (!isset($groupMap[$groupName])) {
            $groupMap[$groupName] = [
                'name' => $groupName,
                'grouped' => false,
                'options' => [],
                'linkedTraits' => [],
            ];
        }

        $groupMap[$groupName]['grouped'] = $groupMap[$groupName]['grouped'] || (bool) $link['grouped'];
        $groupMap[$groupName]['linkedTraits'][] = $link;
    }

    return array_values($groupMap);
}
