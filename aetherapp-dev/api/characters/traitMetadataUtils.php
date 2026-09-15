<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

function buildTraitMetadataInClause(array $ids, string $prefix = 'traitMeta'): array
{
    $placeholders = [];
    $params = [];

    foreach (array_values($ids) as $index => $id) {
        $key = $prefix . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = (int) $id;
    }

    return [
        'clause' => implode(', ', $placeholders),
        'params' => $params,
    ];
}

function normalizeTraitGroupKey(string $value): string
{
    $normalized = mb_strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/u', '_', $normalized) ?? '';
    return trim($normalized, '_');
}

function defaultTraitSheetZone(array $row): string
{
    $type = (string) ($row['type'] ?? '');
    $traitGroup = (string) ($row['traitGroup'] ?? '');

    if ($type === 'profession') {
        return 'left';
    }

    if ($traitGroup === 'Adeldom' || $traitGroup === 'Adellijke titel') {
        return 'left';
    }

    return 'main';
}

function defaultTraitLeftModuleLabel(array $row, string $sheetZone): ?string
{
    if ($sheetZone !== 'left') {
        return null;
    }

    if ((string) ($row['type'] ?? '') === 'profession') {
        return 'Beroep';
    }

    if ((string) ($row['traitGroup'] ?? '') === 'Adellijke titel') {
        return 'Titel';
    }

    if ((string) ($row['traitGroup'] ?? '') !== '') {
        return (string) $row['traitGroup'];
    }

    return null;
}

function defaultTraitCompactReadOnly(array $row, string $sheetZone): bool
{
    if ($sheetZone !== 'left') {
        return false;
    }

    if ((string) ($row['type'] ?? '') === 'profession') {
        return true;
    }

    $traitGroup = (string) ($row['traitGroup'] ?? '');
    return $traitGroup === 'Adeldom' || $traitGroup === 'Adellijke titel';
}

function getTraitSelectionGroupKey(array $trait): string
{
    $trackId = (int) ($trait['trackId'] ?? 0);
    if ($trackId > 0) {
        return 'track:' . $trackId;
    }

    $trackKey = trim((string) ($trait['trackKey'] ?? ''));
    if ($trackKey !== '') {
        return 'track:' . $trackKey;
    }

    $type = normalizeTraitGroupKey((string) ($trait['type'] ?? ''));
    $traitGroup = normalizeTraitGroupKey((string) ($trait['traitGroup'] ?? ''));

    return 'group:' . $type . ':' . $traitGroup;
}

function areTraitsInSameSelectionGroup(array $leftTrait, array $rightTrait): bool
{
    return getTraitSelectionGroupKey($leftTrait) === getTraitSelectionGroupKey($rightTrait);
}

function fetchTraitMetadataMaps(PDO $pdo, array $traitIds): array
{
    $traitIds = array_values(array_unique(array_filter(array_map('intval', $traitIds), static fn(int $id): bool => $id > 0)));
    $maps = [
        'tracks' => [],
        'flags' => [],
        'shares' => [],
        'shareAllowedCompanyTypes' => [],
    ];

    if (count($traitIds) === 0) {
        return $maps;
    }

    $in = buildTraitMetadataInClause($traitIds);

    try {
        $rows = dbAll(
            $pdo,
            "SELECT
                tts.idTrait,
                tt.id AS trackId,
                tt.trackKey,
                tt.label AS trackLabel,
                tt.sheetZone,
                tt.leftModuleLabel,
                tt.sortOrder AS trackSortOrder,
                tt.isExclusive,
                tt.compactReadOnly,
                tts.stepOrder
             FROM tblTraitTrackStep AS tts
             JOIN tblTraitTrack AS tt
               ON tt.id = tts.idTraitTrack
             WHERE tts.idTrait IN ({$in['clause']})",
            $in['params']
        );

        foreach ($rows as $row) {
            $maps['tracks'][(int) $row['idTrait']] = [
                'trackId' => (int) ($row['trackId'] ?? 0),
                'trackKey' => (string) ($row['trackKey'] ?? ''),
                'trackLabel' => (string) ($row['trackLabel'] ?? ''),
                'sheetZone' => (string) ($row['sheetZone'] ?? 'main'),
                'leftModuleLabel' => isset($row['leftModuleLabel']) ? (string) $row['leftModuleLabel'] : null,
                'trackSortOrder' => (int) ($row['trackSortOrder'] ?? 0),
                'isExclusive' => (bool) ($row['isExclusive'] ?? true),
                'compactReadOnly' => (bool) ($row['compactReadOnly'] ?? false),
                'stepOrder' => (int) ($row['stepOrder'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        // Metadata table not available yet.
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT idTrait, flagKey
             FROM tblTraitFlag
             WHERE idTrait IN ({$in['clause']})",
            $in['params']
        );

        foreach ($rows as $row) {
            $idTrait = (int) ($row['idTrait'] ?? 0);
            $flagKey = (string) ($row['flagKey'] ?? '');
            if ($idTrait <= 0 || $flagKey === '') {
                continue;
            }

            if (!isset($maps['flags'][$idTrait])) {
                $maps['flags'][$idTrait] = [];
            }

            $maps['flags'][$idTrait][$flagKey] = true;
        }
    } catch (Throwable $e) {
        // Metadata table not available yet.
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT
                idTrait,
                shareClass,
                displayUnit,
                draftStep,
                costStep,
                staffStep
             FROM tblTraitShareDefinition
             WHERE idTrait IN ({$in['clause']})",
            $in['params']
        );

        foreach ($rows as $row) {
            $maps['shares'][(int) $row['idTrait']] = [
                'shareClass' => (string) ($row['shareClass'] ?? ''),
                'displayUnit' => (string) ($row['displayUnit'] ?? 'percentage'),
                'draftStep' => (int) ($row['draftStep'] ?? 10),
                'costStep' => (int) ($row['costStep'] ?? 10),
                'staffStep' => (int) ($row['staffStep'] ?? 10),
            ];
        }
    } catch (Throwable $e) {
        // Metadata table not available yet.
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT idTrait, companyTypeKey
             FROM tblTraitShareAllowedCompanyType
             WHERE idTrait IN ({$in['clause']})
             ORDER BY sortOrder ASC, companyTypeKey ASC",
            $in['params']
        );

        foreach ($rows as $row) {
            $idTrait = (int) ($row['idTrait'] ?? 0);
            $companyTypeKey = (string) ($row['companyTypeKey'] ?? '');
            if ($idTrait <= 0 || $companyTypeKey === '') {
                continue;
            }

            if (!isset($maps['shareAllowedCompanyTypes'][$idTrait])) {
                $maps['shareAllowedCompanyTypes'][$idTrait] = [];
            }

            $maps['shareAllowedCompanyTypes'][$idTrait][] = $companyTypeKey;
        }
    } catch (Throwable $e) {
        // Metadata table not available yet.
    }

    return $maps;
}

function enrichTraitDefinitionRows(PDO $pdo, array $rows): array
{
    if (count($rows) === 0) {
        return [];
    }

    $metadataMaps = fetchTraitMetadataMaps(
        $pdo,
        array_map(static fn(array $row): int => (int) ($row['idTrait'] ?? $row['id'] ?? 0), $rows)
    );

    return array_map(static function (array $row) use ($metadataMaps): array {
        $idTrait = (int) ($row['idTrait'] ?? $row['id'] ?? 0);
        $track = $metadataMaps['tracks'][$idTrait] ?? null;
        $flags = $metadataMaps['flags'][$idTrait] ?? [];
        $shareDefinition = $metadataMaps['shares'][$idTrait] ?? null;
        $allowedCompanyTypeKeys = $metadataMaps['shareAllowedCompanyTypes'][$idTrait] ?? [];

        $sheetZone = $track['sheetZone'] ?? defaultTraitSheetZone($row);
        $groupKey = $track !== null && (string) ($track['trackKey'] ?? '') !== ''
            ? 'track:' . (string) $track['trackKey']
            : 'group:' . normalizeTraitGroupKey((string) ($row['type'] ?? '')) . ':' . normalizeTraitGroupKey((string) ($row['traitGroup'] ?? ''));
        $groupLabel = $track['trackLabel'] ?? (string) ($row['traitGroup'] ?? '');

        $row['trackId'] = $track['trackId'] ?? null;
        $row['trackKey'] = $track['trackKey'] ?? null;
        $row['trackLabel'] = $track['trackLabel'] ?? null;
        $row['sheetZone'] = $sheetZone;
        $row['leftModuleLabel'] = $track['leftModuleLabel'] ?? defaultTraitLeftModuleLabel($row, $sheetZone);
        $row['trackSortOrder'] = $track['trackSortOrder'] ?? 0;
        $row['trackStepOrder'] = $track['stepOrder'] ?? 0;
        $row['compactReadOnly'] = $track['compactReadOnly'] ?? defaultTraitCompactReadOnly($row, $sheetZone);
        $row['groupKey'] = $groupKey;
        $row['groupLabel'] = $groupLabel !== '' ? $groupLabel : (string) ($row['traitGroup'] ?? '');
        $row['grouped'] = $track !== null ? true : (bool) ($row['grouped'] ?? false);
        $row['traitFlagKeys'] = array_keys($flags);
        $row['traitFlags'] = $flags;

        $row['isCompanyShare'] = $shareDefinition !== null;
        $row['shareClass'] = $shareDefinition['shareClass'] ?? null;
        $row['shareDisplayUnit'] = $shareDefinition['displayUnit'] ?? null;
        $row['shareDraftStep'] = $shareDefinition['draftStep'] ?? null;
        $row['shareCostStep'] = $shareDefinition['costStep'] ?? null;
        $row['shareStaffStep'] = $shareDefinition['staffStep'] ?? null;
        $row['allowedCompanyTypeKeys'] = $allowedCompanyTypeKeys;

        return $row;
    }, $rows);
}

function traitHasFlag(array $trait, string $flagKey): bool
{
    $traitFlags = $trait['traitFlags'] ?? [];
    if (is_array($traitFlags) && isset($traitFlags[$flagKey]) && $traitFlags[$flagKey]) {
        return true;
    }

    $flagKeys = $trait['traitFlagKeys'] ?? [];
    return is_array($flagKeys) && in_array($flagKey, $flagKeys, true);
}
