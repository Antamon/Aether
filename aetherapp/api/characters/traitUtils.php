<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

function calculateTraitPointCost(array $trait, int $rankValue): int
{
    $cost = (int) ($trait['cost'] ?? 0);
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
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'class' => $row['class'],
            'type' => $row['type'],
            'isUnique' => (bool) $row['isUnique'],
            'rankType' => $row['rankType'],
            'traitGroup' => $row['traitGroup'],
            'cost' => isset($row['cost']) ? (int) $row['cost'] : 0,
            'income' => isset($row['income']) ? (int) $row['income'] : null,
            'evolution' => isset($row['evolution']) ? (float) $row['evolution'] : null,
            'staffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
            'description' => $row['description'] ?? '',
        ];
    }, $rows);
}

function getCharacterTraitLinks(PDO $pdo, int $idCharacter, array $types = []): array
{
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
                t.cost,
                t.income,
                t.evolution,
                t.staffRequirements,
                t.description
             FROM tblLinkCharacterTrait AS lct
             JOIN tblTrait AS t
                   ON t.id = lct.idTrait
             WHERE lct.idCharacter = :idCharacter
             {$typeClause}
             ORDER BY t.traitGroup, t.name",
            $params
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'idTrait' => (int) $row['idTrait'],
            'rank' => (int) $row['rankValue'],
            'name' => $row['name'],
            'class' => $row['class'],
            'type' => $row['type'],
            'isUnique' => (bool) $row['isUnique'],
            'rankType' => $row['rankType'],
            'traitGroup' => $row['traitGroup'],
            'cost' => isset($row['cost']) ? (int) $row['cost'] : 0,
            'income' => isset($row['income']) ? (int) $row['income'] : null,
            'evolution' => isset($row['evolution']) ? (float) $row['evolution'] : null,
            'staffRequirements' => isset($row['staffRequirements']) ? (int) $row['staffRequirements'] : 0,
            'description' => $row['description'] ?? '',
        ];
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
                'options' => [],
                'linkedTraits' => [],
            ];
        }

        $groupMap[$groupName]['options'][] = $option;
    }

    foreach ($links as $link) {
        $groupName = $link['traitGroup'];
        if (!isset($groupMap[$groupName])) {
            $groupMap[$groupName] = [
                'name' => $groupName,
                'options' => [],
                'linkedTraits' => [],
            ];
        }

        $groupMap[$groupName]['linkedTraits'][] = $link;
    }

    return array_values($groupMap);
}
