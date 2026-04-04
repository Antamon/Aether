<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/companyShareUtils.php';
require_once __DIR__ . '/traitMetadataUtils.php';

function isGroupedTrait(array $trait): bool
{
    return !empty($trait['grouped']);
}

function calculateTraitPointCost(array $trait, int $rankValue): int
{
    $cost = (int) ($trait['cost'] ?? 0);
    if (isCompanyShareTrait($trait)) {
        return $cost * getCompanySharePointPurchaseUnitsForTrait($trait, $rankValue);
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

function normalizeTraitDefinitionRow(array $row): array
{
    $shareMetadata = getCompanyShareTraitMetadata($row);

    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'class' => (string) ($row['class'] ?? ''),
        'type' => (string) ($row['type'] ?? ''),
        'isUnique' => (bool) ($row['isUnique'] ?? false),
        'rankType' => (string) ($row['rankType'] ?? 'singular'),
        'traitGroup' => (string) ($row['traitGroup'] ?? ''),
        'grouped' => (bool) ($row['grouped'] ?? false),
        'cost' => isset($row['cost']) ? (int) $row['cost'] : 0,
        'income' => isset($row['income']) && $row['income'] !== null ? (float) $row['income'] : null,
        'evolution' => isset($row['evolution']) && $row['evolution'] !== null ? (float) $row['evolution'] : null,
        'staffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
        'rawStaffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
        'description' => (string) ($row['description'] ?? ''),
        'trackId' => isset($row['trackId']) && $row['trackId'] !== null ? (int) $row['trackId'] : null,
        'trackKey' => isset($row['trackKey']) ? (string) $row['trackKey'] : null,
        'trackLabel' => isset($row['trackLabel']) ? (string) $row['trackLabel'] : null,
        'sheetZone' => (string) ($row['sheetZone'] ?? defaultTraitSheetZone($row)),
        'leftModuleLabel' => isset($row['leftModuleLabel']) && $row['leftModuleLabel'] !== null && $row['leftModuleLabel'] !== ''
            ? (string) $row['leftModuleLabel']
            : defaultTraitLeftModuleLabel($row, (string) ($row['sheetZone'] ?? defaultTraitSheetZone($row))),
        'trackSortOrder' => isset($row['trackSortOrder']) ? (int) $row['trackSortOrder'] : 0,
        'trackStepOrder' => isset($row['trackStepOrder']) ? (int) $row['trackStepOrder'] : 0,
        'compactReadOnly' => (bool) ($row['compactReadOnly'] ?? defaultTraitCompactReadOnly($row, (string) ($row['sheetZone'] ?? defaultTraitSheetZone($row)))),
        'groupKey' => isset($row['groupKey']) && $row['groupKey'] !== ''
            ? (string) $row['groupKey']
            : getTraitSelectionGroupKey($row),
        'groupLabel' => isset($row['groupLabel']) && $row['groupLabel'] !== ''
            ? (string) $row['groupLabel']
            : (string) ($row['traitGroup'] ?? ''),
        'traitFlagKeys' => array_values(array_filter(
            is_array($row['traitFlagKeys'] ?? null) ? $row['traitFlagKeys'] : [],
            static fn($flag): bool => is_string($flag) && $flag !== ''
        )),
        'traitFlags' => is_array($row['traitFlags'] ?? null) ? $row['traitFlags'] : [],
        'isCompanyShare' => $shareMetadata !== null,
        'shareClass' => $shareMetadata['shareClass'] ?? null,
        'companyTypeKey' => $shareMetadata['companyTypeKey'] ?? null,
        'companyTypeLabel' => $shareMetadata['companyTypeLabel'] ?? null,
        'companyTypeDescription' => $shareMetadata['companyTypeDescription'] ?? null,
        'allowedCompanyTypeKeys' => $shareMetadata['allowedCompanyTypeKeys'] ?? [],
        'shareDisplayUnit' => isset($row['shareDisplayUnit']) && $row['shareDisplayUnit'] !== null && $row['shareDisplayUnit'] !== ''
            ? (string) $row['shareDisplayUnit']
            : ($shareMetadata !== null ? 'percentage' : null),
        'shareDraftStep' => isset($row['shareDraftStep']) && $row['shareDraftStep'] !== null
            ? (int) $row['shareDraftStep']
            : ($shareMetadata !== null ? 10 : null),
        'shareCostStep' => isset($row['shareCostStep']) && $row['shareCostStep'] !== null
            ? (int) $row['shareCostStep']
            : ($shareMetadata !== null ? 10 : null),
        'shareStaffStep' => isset($row['shareStaffStep']) && $row['shareStaffStep'] !== null
            ? (int) $row['shareStaffStep']
            : ($shareMetadata !== null ? 10 : null),
    ];
}

function sortTraitGroupItems(array &$items): void
{
    usort($items, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['trackStepOrder'] ?? 0);
        $rightOrder = (int) ($right['trackStepOrder'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        $leftCost = (int) ($left['cost'] ?? 0);
        $rightCost = (int) ($right['cost'] ?? 0);
        if ($leftCost !== $rightCost) {
            return $leftCost <=> $rightCost;
        }

        return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });
}

function getTraitDefinition(PDO $pdo, int $idTrait): ?array
{
    if ($idTrait <= 0) {
        return null;
    }

    try {
        $row = dbOne(
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
             WHERE id = :id",
            ['id' => $idTrait]
        );
    } catch (Throwable $e) {
        return null;
    }

    if (!$row) {
        return null;
    }

    $rows = enrichTraitDefinitionRows($pdo, [$row]);
    if (count($rows) === 0) {
        return null;
    }

    return normalizeTraitDefinitionRow($rows[0]);
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

    $rows = enrichTraitDefinitionRows($pdo, $rows);

    return array_map(static fn(array $row): array => normalizeTraitDefinitionRow($row), $rows);
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

    $rows = enrichTraitDefinitionRows($pdo, $rows);

    return array_map(static function (array $row): array {
        $link = normalizeTraitDefinitionRow($row);
        $link['id'] = (int) ($row['id'] ?? 0);
        $link['idTrait'] = (int) ($row['idTrait'] ?? 0);

        if (isCompanyShareTrait($link)) {
            $baseRank = getCompanyShareBaseRank([
                'name' => (string) ($row['name'] ?? ''),
                'baseRank' => (int) ($row['baseRankValue'] ?? $row['rankValue'] ?? 0),
                'isCompanyShare' => true,
                'shareDraftStep' => $link['shareDraftStep'] ?? null,
            ]);
            $extraRank = (int) ($row['extraPercentage'] ?? 0);
            $effectiveRank = $baseRank + $extraRank;
            $link['baseRank'] = $baseRank;
            $link['shareExtraRank'] = $extraRank;
            $link['rank'] = $effectiveRank;
        } else {
            $link['baseRank'] = (int) ($row['baseRankValue'] ?? $row['rankValue'] ?? 0);
            $link['shareExtraRank'] = 0;
            $link['rank'] = (int) ($row['rankValue'] ?? 0);
        }

        $link['companyId'] = isset($row['idCompany']) && $row['idCompany'] !== null ? (int) $row['idCompany'] : null;
        $link['companyName'] = isset($row['companyName']) && $row['companyName'] !== null ? (string) $row['companyName'] : null;
        $link['companyValue'] = isset($row['companyValue']) && $row['companyValue'] !== null ? round((float) $row['companyValue'], 2) : null;
        $link['staffRequirements'] = getEffectiveTraitStaffRequirements($link, (int) ($link['rank'] ?? 0));

        return $link;
    }, $rows);
}

function buildCharacterTraitGroups(PDO $pdo, int $idCharacter, string $characterClass, array $types = ['status', 'quality']): array
{
    $options = getTraitsForCharacterClass($pdo, $characterClass, $types);
    $links = getCharacterTraitLinks($pdo, $idCharacter, $types);

    $groupMap = [];

    foreach ($options as $option) {
        $groupKey = (string) ($option['groupKey'] ?? getTraitSelectionGroupKey($option));
        if (!isset($groupMap[$groupKey])) {
            $groupMap[$groupKey] = [
                'groupKey' => $groupKey,
                'name' => (string) ($option['groupLabel'] ?? $option['traitGroup']),
                'traitGroup' => (string) ($option['traitGroup'] ?? ''),
                'trackId' => isset($option['trackId']) ? (int) $option['trackId'] : null,
                'trackKey' => $option['trackKey'] ?? null,
                'grouped' => false,
                'sheetZone' => (string) ($option['sheetZone'] ?? 'main'),
                'leftModuleLabel' => $option['leftModuleLabel'] ?? null,
                'compactReadOnly' => (bool) ($option['compactReadOnly'] ?? false),
                'sortOrder' => (int) ($option['trackSortOrder'] ?? 0),
                'options' => [],
                'linkedTraits' => [],
            ];
        }

        $groupMap[$groupKey]['grouped'] = $groupMap[$groupKey]['grouped'] || (bool) $option['grouped'];
        $groupMap[$groupKey]['compactReadOnly'] = $groupMap[$groupKey]['compactReadOnly'] || (bool) ($option['compactReadOnly'] ?? false);
        $groupMap[$groupKey]['options'][] = $option;
    }

    foreach ($links as $link) {
        $groupKey = (string) ($link['groupKey'] ?? getTraitSelectionGroupKey($link));
        if (!isset($groupMap[$groupKey])) {
            $groupMap[$groupKey] = [
                'groupKey' => $groupKey,
                'name' => (string) ($link['groupLabel'] ?? $link['traitGroup']),
                'traitGroup' => (string) ($link['traitGroup'] ?? ''),
                'trackId' => isset($link['trackId']) ? (int) $link['trackId'] : null,
                'trackKey' => $link['trackKey'] ?? null,
                'grouped' => false,
                'sheetZone' => (string) ($link['sheetZone'] ?? 'main'),
                'leftModuleLabel' => $link['leftModuleLabel'] ?? null,
                'compactReadOnly' => (bool) ($link['compactReadOnly'] ?? false),
                'sortOrder' => (int) ($link['trackSortOrder'] ?? 0),
                'options' => [],
                'linkedTraits' => [],
            ];
        }

        $groupMap[$groupKey]['grouped'] = $groupMap[$groupKey]['grouped'] || (bool) $link['grouped'];
        $groupMap[$groupKey]['compactReadOnly'] = $groupMap[$groupKey]['compactReadOnly'] || (bool) ($link['compactReadOnly'] ?? false);
        $groupMap[$groupKey]['linkedTraits'][] = $link;
    }

    foreach ($groupMap as &$group) {
        sortTraitGroupItems($group['options']);
        sortTraitGroupItems($group['linkedTraits']);
    }
    unset($group);

    $groups = array_values($groupMap);
    usort($groups, static function (array $left, array $right): int {
        $leftZone = (string) ($left['sheetZone'] ?? 'main');
        $rightZone = (string) ($right['sheetZone'] ?? 'main');
        if ($leftZone !== $rightZone) {
            return strcmp($leftZone, $rightZone);
        }

        $leftOrder = (int) ($left['sortOrder'] ?? 0);
        $rightOrder = (int) ($right['sortOrder'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $groups;
}
