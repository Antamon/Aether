<?php
declare(strict_types=1);

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/companyQuarterMath.php';

function aetherCompanyQuarterHistoryAvailable(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM tblSchemaMigration
        WHERE migrationCode = '0016_company_quarters'");
    return (int) $stmt->fetchColumn() === 1;
}

function aetherCompanyQuarterRefreshAfterSnapshotChange(PDO $pdo, int $companyId,
    string $dateStart, ?string $dateEnd): void
{
    if (!aetherCompanyQuarterHistoryAvailable($pdo)) return;
    $startIndex = aetherCompanyQuarterForOutgameDate($dateStart);
    $endIndex = max($startIndex, aetherCompanyQuarterForOutgameDate($dateEnd ?: $dateStart));
    $timeline = aetherCompanyQuarterTimeline($pdo, $companyId);
    $previousSnapshot = null;
    $nextSnapshot = null;
    foreach ($timeline as $row) {
        $index = aetherCompanyQuarterIndex((int) $row['year'], (int) $row['quarter']);
        if ($index < $startIndex && $row['source'] === 'snapshot') $previousSnapshot = $index;
        if ($index > $endIndex && $row['source'] === 'snapshot') {
            $nextSnapshot = $index;
            break;
        }
    }
    // Redraw the surrounding snapshot interval; quarter events stay attached.
    $firstAffected = $previousSnapshot ?? ($timeline ? aetherCompanyQuarterIndex(
        (int) $timeline[0]['year'], (int) $timeline[0]['quarter']) : $startIndex);
    $lastAffected = $nextSnapshot ?? ($timeline ? aetherCompanyQuarterIndex(
        (int) $timeline[count($timeline) - 1]['year'], (int) $timeline[count($timeline) - 1]['quarter']) + 1 : $endIndex + 1);
    $startOperator = $previousSnapshot === null ? '>=' : '>';
    $stmt = $pdo->prepare('UPDATE tblCompanyQuarter SET isManual = 0
        WHERE idCompany = :idCompany AND isManual = 1
          AND (quarterYear * 4 + quarterNumber - 1) ' . $startOperator . ' :firstAffected
          AND (quarterYear * 4 + quarterNumber - 1) < :lastAffected');
    $stmt->execute(['idCompany' => $companyId, 'firstAffected' => $firstAffected,
        'lastAffected' => $lastAffected]);
    aetherCompanyQuarterTimeline($pdo, $companyId, true);
}

function aetherCompanyQuarterTimeline(PDO $pdo, int $companyId, bool $persist = false): array
{
    $company = dbOne($pdo, 'SELECT id, foundationDate, companyValue, stability, profitability,
        issuedShareCount FROM tblCompany WHERE id = :id', ['id' => $companyId]);
    if ($company === null) throw new RuntimeException('Bedrijf niet gevonden.', 404);
    $foundation = getCompanyFoundationSnapshot($pdo, $companyId);
    $foundationDate = $foundation['foundationDate'] ?? $company['foundationDate'] ?? null;
    if (!$foundationDate) return [];
    $first = aetherCompanyQuarterForDate($foundationDate);
    $last = aetherCompanyQuarterForOutgameDate(
        (new DateTimeImmutable('today', new DateTimeZone('Europe/Brussels')))->format('Y-m-d')
    );
    if ($first > $last) return [];
    if ($last - $first > 2000) throw new RuntimeException('De oprichtingsdatum ligt te ver in het verleden.', 409);

    $stmt = $pdo->prepare('SELECT id, quarterYear, quarterNumber, companyValue, issuedShareCount,
        shareMarketPrice, stability, profitability, isManual
        FROM tblCompanyQuarter WHERE idCompany = :idCompany');
    $stmt->execute(['idCompany' => $companyId]);
    $manual = []; $events = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $manual[aetherCompanyQuarterIndex((int) $item['quarterYear'], (int) $item['quarterNumber'])] = $item;
        $events[(int) $item['id']] = [];
    }
    if ($events !== []) {
        $stmt = $pdo->prepare('SELECT e.idCompanyQuarter, e.id, e.description, e.publicity
            FROM tblCompanyQuarterEvent e JOIN tblCompanyQuarter q ON q.id = e.idCompanyQuarter
            WHERE q.idCompany = :idCompany ORDER BY e.id');
        $stmt->execute(['idCompany' => $companyId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $events[(int) $event['idCompanyQuarter']][] = [
                'id' => (int) $event['id'], 'description' => $event['description'],
                'publicity' => (int) $event['publicity'],
            ];
        }
    }
    $stmt = $pdo->prepare('SELECT s.id, s.idEvent, e.title, e.dateStart, e.dateEnd,
        s.companyValue, s.issuedShareCount, s.shareMarketPrice, s.stability, s.profitability
        FROM tblCompanySnapshot s JOIN tblEvent e ON e.id = s.idEvent
        WHERE s.idCompany = :idCompany ORDER BY e.dateStart, s.id');
    $stmt->execute(['idCompany' => $companyId]);
    $snapshots = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $snapshot) {
        $start = aetherCompanyQuarterForOutgameDate($snapshot['dateStart']);
        $endDate = $snapshot['dateEnd'] ?: $snapshot['dateStart'];
        $end = aetherCompanyQuarterForOutgameDate($endDate);
        for ($index = max($first, $start); $index <= min($last, max($start, $end)); $index++) $snapshots[$index] = $snapshot;
    }
    $previous = [
        'companyValue' => (float) ($foundation['initialCompanyValue'] ?? $company['companyValue']),
        'issuedShareCount' => (int) ($foundation['initialShareCount'] ?? $company['issuedShareCount'] ?? 0),
        'shareMarketPrice' => isset($foundation['shareMarketPrice']) ? (float) $foundation['shareMarketPrice'] : null,
        'stability' => (int) ($foundation['stability'] ?? $company['stability']),
        'profitability' => (int) ($foundation['profitability'] ?? $company['profitability']),
    ];
    $anchors = [$first => $previous];
    foreach ($manual as $index => $saved) {
        if ($index < $first || $index > $last || (int) $saved['isManual'] !== 1) continue;
        $anchors[$index] = [
            'companyValue' => (float) $saved['companyValue'],
            'issuedShareCount' => (int) $saved['issuedShareCount'],
            'shareMarketPrice' => $saved['shareMarketPrice'] === null ? null : (float) $saved['shareMarketPrice'],
            'stability' => (int) $saved['stability'],
            'profitability' => (int) $saved['profitability'],
        ];
    }
    ksort($snapshots);
    foreach ($snapshots as $index => $snapshot) {
        $base = $previous;
        $nearestIndex = $first - 1;
        foreach ($anchors as $anchorIndex => $anchorRow) {
            if ($anchorIndex <= $index && $anchorIndex > $nearestIndex) {
                $base = $anchorRow;
                $nearestIndex = $anchorIndex;
            }
        }
        foreach (array_keys($base) as $key) {
            if ($snapshot[$key] !== null || $key === 'shareMarketPrice') {
                $base[$key] = $snapshot[$key] === null ? null : 0 + $snapshot[$key];
            }
        }
        $anchors[$index] = $base;
    }
    ksort($anchors);
    $anchorIndexes = array_keys($anchors);
    $result = [];
    $insert = $persist ? $pdo->prepare('INSERT IGNORE INTO tblCompanyQuarter
        (idCompany, quarterYear, quarterNumber, companyValue, issuedShareCount,
         shareMarketPrice, stability, profitability, isManual)
        VALUES (:idCompany, :quarterYear, :quarterNumber, :companyValue, :issuedShareCount,
                :shareMarketPrice, :stability, :profitability, 0)') : null;
    $update = $persist ? $pdo->prepare('UPDATE tblCompanyQuarter SET companyValue = :companyValue,
        issuedShareCount = :issuedShareCount, shareMarketPrice = :shareMarketPrice,
        stability = :stability, profitability = :profitability
        WHERE id = :id AND isManual = 0') : null;
    $anchorPosition = 0;
    for ($index = $first; $index <= $last; $index++) {
        [$year, $quarter] = aetherCompanyQuarterBounds($index);
        while (isset($anchorIndexes[$anchorPosition + 1]) && $anchorIndexes[$anchorPosition + 1] <= $index) $anchorPosition++;
        $fromIndex = $anchorIndexes[$anchorPosition];
        $toIndex = $anchorIndexes[$anchorPosition + 1] ?? null;
        $row = $index === $fromIndex || $toIndex === null
            ? $anchors[$fromIndex]
            : aetherCompanyQuarterInterpolate($anchors[$fromIndex], $anchors[$toIndex],
                ($index - $fromIndex) / ($toIndex - $fromIndex));
        $source = isset($snapshots[$index]) ? 'snapshot'
            : (isset($manual[$index]) && (int) $manual[$index]['isManual'] === 1 ? 'aangepast' : 'lineair');
        if ($persist && (!isset($manual[$index]) || (int) $manual[$index]['isManual'] === 0)) {
            $fields = ['companyValue', 'issuedShareCount', 'shareMarketPrice', 'stability', 'profitability'];
            $changed = !isset($manual[$index]);
            if (!$changed) foreach ($fields as $field) {
                if ($manual[$index][$field] === null || $row[$field] === null) {
                    if ($manual[$index][$field] !== $row[$field]) { $changed = true; break; }
                } elseif ((float) $manual[$index][$field] !== (float) $row[$field]) { $changed = true; break; }
            }
            if ($changed) {
                $values = ['companyValue' => $row['companyValue'],
                    'issuedShareCount' => $row['issuedShareCount'],
                    'shareMarketPrice' => $row['shareMarketPrice'],
                    'stability' => $row['stability'], 'profitability' => $row['profitability']];
                if (isset($manual[$index])) $update->execute(['id' => $manual[$index]['id']] + $values);
                else $insert->execute(['idCompany' => $companyId, 'quarterYear' => $year,
                    'quarterNumber' => $quarter] + $values);
            }
        }
        $result[] = ['year' => $year, 'quarter' => $quarter, 'source' => $source,
            'snapshotEvent' => isset($snapshots[$index]) ? $snapshots[$index]['title'] : null,
            'events' => $events[(int) ($manual[$index]['id'] ?? 0)] ?? []] + $row;
    }
    return $result;
}

/** Save all staged graph and modal changes in one transaction. */
function aetherCompanySaveQuarterEdits(PDO $pdo, int $companyId, array $input): array
{
    $edits = $input['edits'] ?? null;
    if (!is_array($edits) || count($edits) < 1 || count($edits) > 500) {
        throw new RuntimeException('Ongeldige grafiekbewerking.', 422);
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM tblCompany WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $companyId]);
        if ($stmt->fetchColumn() === false) throw new RuntimeException('Bedrijf niet gevonden.', 404);
        $timeline = aetherCompanyQuarterTimeline($pdo, $companyId);
        $byIndex = [];
        foreach ($timeline as $row) {
            $byIndex[aetherCompanyQuarterIndex((int) $row['year'], (int) $row['quarter'])] = $row;
        }
        $upsert = $pdo->prepare('INSERT INTO tblCompanyQuarter
                (idCompany, quarterYear, quarterNumber, companyValue, issuedShareCount,
                 shareMarketPrice, stability, profitability, isManual)
                VALUES (:idCompany, :quarterYear, :quarterNumber, :companyValue, :issuedShareCount,
                        :shareMarketPrice, :stability, :profitability, :isManual)
                ON DUPLICATE KEY UPDATE companyValue = VALUES(companyValue),
                    issuedShareCount = VALUES(issuedShareCount),
                    shareMarketPrice = VALUES(shareMarketPrice), stability = VALUES(stability),
                    profitability = VALUES(profitability), isManual = VALUES(isManual)');
        $find = $pdo->prepare('SELECT id FROM tblCompanyQuarter WHERE idCompany = :idCompany
            AND quarterYear = :year AND quarterNumber = :quarter');
        $deleteEvents = $pdo->prepare('DELETE FROM tblCompanyQuarterEvent WHERE idCompanyQuarter = :id');
        $insertEvent = $pdo->prepare('INSERT INTO tblCompanyQuarterEvent
            (idCompanyQuarter, description, publicity) VALUES (:id, :description, :publicity)');
        $seen = [];
        foreach ($edits as $edit) {
            if (!is_array($edit)) throw new RuntimeException('Ongeldige kwartaalgegevens.', 422);
            $year = filter_var($edit['year'] ?? null, FILTER_VALIDATE_INT);
            $quarter = filter_var($edit['quarter'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($year) || !is_int($quarter) || $year < 1000 || $year > 9999
                || $quarter < 1 || $quarter > 4) {
                throw new RuntimeException('Ongeldig kwartaal.', 422);
            }
            $index = aetherCompanyQuarterIndex($year, $quarter);
            if (isset($seen[$index]) || !isset($byIndex[$index])) throw new RuntimeException('Dubbel of onbekend kwartaal.', 422);
            $seen[$index] = true;
            $original = $byIndex[$index];
            if ($original['source'] === 'snapshot' && !array_key_exists('events', $edit)) {
                throw new RuntimeException('Snapshotpunten kunnen niet worden versleept.', 422);
            }
            $values = [];
            foreach (['companyValue', 'issuedShareCount', 'shareMarketPrice', 'stability', 'profitability'] as $field) {
                $values[$field] = $original['source'] === 'snapshot' ? $original[$field] : ($edit[$field] ?? null);
            }
            if ($original['source'] !== 'snapshot' && (!is_numeric($values['companyValue'])
                || ($values['shareMarketPrice'] !== null && !is_numeric($values['shareMarketPrice']))
                || !is_numeric($values['issuedShareCount']) || !is_numeric($values['stability'])
                || !is_numeric($values['profitability']) || (float) $values['companyValue'] < 0
                || (float) $values['companyValue'] > 9999999999.99
                || ($values['shareMarketPrice'] !== null && ((float) $values['shareMarketPrice'] < 0
                    || (float) $values['shareMarketPrice'] > 9999999999.99))
                || filter_var($values['issuedShareCount'], FILTER_VALIDATE_INT) === false
                || (int) $values['issuedShareCount'] < 0 || (int) $values['issuedShareCount'] > 1000000
                || filter_var($values['stability'], FILTER_VALIDATE_INT) === false
                || filter_var($values['profitability'], FILTER_VALIDATE_INT) === false
                || (int) $values['stability'] < -7 || (int) $values['stability'] > 7
                || (int) $values['profitability'] < -7 || (int) $values['profitability'] > 7)) {
                throw new RuntimeException('Controleer de kwartaalcijfers.', 422);
            }
            $upsert->execute([
                'idCompany' => $companyId, 'quarterYear' => $year, 'quarterNumber' => $quarter,
                'companyValue' => round((float) $values['companyValue'], 2),
                'issuedShareCount' => (int) $values['issuedShareCount'],
                'shareMarketPrice' => $values['shareMarketPrice'] === null ? null : round((float) $values['shareMarketPrice'], 2),
                'stability' => (int) $values['stability'], 'profitability' => (int) $values['profitability'],
                'isManual' => $original['source'] === 'snapshot' ? 0 : 1,
            ]);
            if (!array_key_exists('events', $edit)) continue;
            if (!is_array($edit['events']) || count($edit['events']) > 30) throw new RuntimeException('Ongeldige gebeurtenissenlijst.', 422);
            $find->execute(['idCompany' => $companyId, 'year' => $year, 'quarter' => $quarter]);
            $savedId = (int) $find->fetchColumn();
            $deleteEvents->execute(['id' => $savedId]);
            foreach ($edit['events'] as $event) {
                if (!is_array($event) || !is_string($event['description'] ?? null)
                    || trim($event['description']) === '' || mb_strlen($event['description']) > 2000
                    || !in_array($event['publicity'] ?? null, [1, 2, 3, 4, 5], true)) {
                    throw new RuntimeException('Controleer de bedrijfsgebeurtenissen.', 422);
                }
                $insertEvent->execute(['id' => $savedId, 'description' => trim($event['description']),
                    'publicity' => $event['publicity']]);
            }
        }
        $result = aetherCompanyQuarterTimeline($pdo, $companyId, true);
        $pdo->commit();
        return ['status' => 'ok', 'changedQuarters' => count($edits), 'quarters' => $result];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function aetherCompanySaveQuarter(PDO $pdo, int $companyId, array $input): array
{
    $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
    $quarter = filter_var($input['quarter'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $quarter === false || $year < 1000 || $year > 9999 || $quarter < 1 || $quarter > 4) {
        throw new RuntimeException('Ongeldig kwartaal.', 422);
    }
    $eventItems = $input['events'] ?? [];
    if (!is_array($eventItems) || count($eventItems) > 30) throw new RuntimeException('Ongeldige gebeurtenissenlijst.', 422);
    $cleanEvents = [];
    foreach ($eventItems as $item) {
        if (!is_array($item) || !is_string($item['description'] ?? null)
            || trim($item['description']) === '' || mb_strlen($item['description']) > 2000
            || !in_array($item['publicity'] ?? null, [1, 2, 3, 4, 5], true)) {
            throw new RuntimeException('Controleer de beschrijving en zichtbaarheid van elke gebeurtenis.', 422);
        }
        $cleanEvents[] = ['description' => trim($item['description']), 'publicity' => $item['publicity']];
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM tblCompany WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $companyId]);
        if ($lock->fetchColumn() === false) throw new RuntimeException('Bedrijf niet gevonden.', 404);
        $timeline = aetherCompanyQuarterTimeline($pdo, $companyId);
        $selected = null;
        foreach ($timeline as $row) if ($row['year'] === $year && $row['quarter'] === $quarter) { $selected = $row; break; }
        if ($selected === null) throw new RuntimeException('Kwartaal valt buiten de levensduur van dit bedrijf.', 422);
        $manual = $selected['source'] !== 'snapshot';
        $values = [];
        foreach (['companyValue', 'issuedShareCount', 'shareMarketPrice', 'stability', 'profitability'] as $key) {
            $values[$key] = $manual ? ($input[$key] ?? null) : $selected[$key];
        }
        if ($manual && (!is_numeric($values['companyValue']) || !is_numeric($values['shareMarketPrice'])
            || !is_numeric($values['issuedShareCount']) || !is_numeric($values['stability']) || !is_numeric($values['profitability'])
            || (float) $values['companyValue'] < 0 || (float) $values['companyValue'] > 9999999999.99
            || (float) $values['shareMarketPrice'] < 0 || (float) $values['shareMarketPrice'] > 9999999999.99
            || filter_var($values['issuedShareCount'], FILTER_VALIDATE_INT) === false
            || (int) $values['issuedShareCount'] < 0 || (int) $values['issuedShareCount'] > 1000000
            || filter_var($values['stability'], FILTER_VALIDATE_INT) === false
            || filter_var($values['profitability'], FILTER_VALIDATE_INT) === false
            || (int) $values['stability'] < -7 || (int) $values['stability'] > 7
            || (int) $values['profitability'] < -7 || (int) $values['profitability'] > 7)) {
            throw new RuntimeException('Controleer de kwartaalcijfers.', 422);
        }
        $values = [
            'companyValue' => round((float) $values['companyValue'], 2),
            'issuedShareCount' => (int) $values['issuedShareCount'],
            'shareMarketPrice' => $values['shareMarketPrice'] === null
                ? null : round((float) $values['shareMarketPrice'], 2),
            'stability' => (int) $values['stability'],
            'profitability' => (int) $values['profitability'],
        ];
        $stmt = $pdo->prepare(($manual ? 'INSERT INTO' : 'INSERT IGNORE INTO') . ' tblCompanyQuarter (idCompany, quarterYear, quarterNumber,
            companyValue, issuedShareCount, shareMarketPrice, stability, profitability, isManual)
            VALUES (:idCompany, :quarterYear, :quarterNumber, :companyValue, :issuedShareCount,
                :shareMarketPrice, :stability, :profitability, :isManual)' . ($manual ? '
            ON DUPLICATE KEY UPDATE companyValue = VALUES(companyValue), issuedShareCount = VALUES(issuedShareCount),
                shareMarketPrice = VALUES(shareMarketPrice), stability = VALUES(stability),
                profitability = VALUES(profitability), isManual = VALUES(isManual)' : ''));
        $stmt->execute(['idCompany' => $companyId, 'quarterYear' => $year, 'quarterNumber' => $quarter]
            + $values + ['isManual' => $manual ? 1 : 0]);
        $stmt = $pdo->prepare('SELECT id FROM tblCompanyQuarter WHERE idCompany = :idCompany
            AND quarterYear = :quarterYear AND quarterNumber = :quarterNumber FOR UPDATE');
        $stmt->execute(['idCompany' => $companyId, 'quarterYear' => $year, 'quarterNumber' => $quarter]);
        $id = (int) $stmt->fetchColumn();
        if (!$id) throw new RuntimeException('Kwartaal kon niet worden bewaard.', 409);
        $stmt = $pdo->prepare('DELETE FROM tblCompanyQuarterEvent WHERE idCompanyQuarter = :id');
        $stmt->execute(['id' => $id]);
        $stmt = $pdo->prepare('INSERT INTO tblCompanyQuarterEvent (idCompanyQuarter, description, publicity)
            VALUES (:id, :description, :publicity)');
        foreach ($cleanEvents as $item) $stmt->execute(['id' => $id] + $item);
        aetherCompanyQuarterTimeline($pdo, $companyId, true);
        $pdo->commit();
        return ['status' => 'ok'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
