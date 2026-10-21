<?php
declare(strict_types=1);

function aetherCompanyQuarterIndex(int $year, int $quarter): int
{
    return $year * 4 + $quarter - 1;
}

function aetherCompanyQuarterForDate(string $date): int
{
    $time = new DateTimeImmutable($date);
    return aetherCompanyQuarterIndex((int) $time->format('Y'), intdiv((int) $time->format('n') - 1, 3) + 1);
}

function aetherCompanyQuarterForOutgameDate(string $date): int
{
    return aetherCompanyQuarterForDate((new DateTimeImmutable($date))->modify('-100 years')->format('Y-m-d'));
}

function aetherCompanyQuarterBounds(int $index): array
{
    return [intdiv($index, 4), $index % 4 + 1];
}

/** Interpolate four figures; issued shares belong to the preceding anchor. */
function aetherCompanyQuarterInterpolate(array $from, array $to, float $fraction): array
{
    $result = ['issuedShareCount' => (int) $from['issuedShareCount']];
    foreach (['companyValue', 'shareMarketPrice', 'stability', 'profitability'] as $field) {
        $start = $from[$field];
        $end = $to[$field];
        if ($start === null || $end === null) {
            $result[$field] = $start;
            continue;
        }
        $value = (float) $start + ((float) $end - (float) $start) * $fraction;
        $result[$field] = round($value, in_array($field, ['stability', 'profitability'], true) ? 0 : 2);
        if (in_array($field, ['stability', 'profitability'], true)) $result[$field] = (int) $result[$field];
    }
    return $result;
}

/** Calculate a drag preview from the stored timeline without crossing snapshot anchors. */
function aetherCompanyQuarterDragValues(array $timeline, int $center, string $metric,
    float $target, int $radius): array
{
    $limits = [
        'companyValue' => [0, 9999999999.99, 2],
        'issuedShareCount' => [0, 1000000, 0],
        'shareMarketPrice' => [0, 9999999999.99, 2],
        'stability' => [-7, 7, 0],
        'profitability' => [-7, 7, 0],
    ];
    if (!isset($limits[$metric]) || $radius < 0 || $radius > 8 || !is_finite($target)
        || !isset($timeline[$center]) || $timeline[$center]['source'] === 'snapshot'
        || $timeline[$center][$metric] === null) {
        throw new InvalidArgumentException('Ongeldige grafiekbewerking.', 422);
    }
    [$minimum, $maximum, $decimals] = $limits[$metric];
    if ($target < $minimum || $target > $maximum) {
        throw new InvalidArgumentException('De gesleepte waarde valt buiten het toegestane bereik.', 422);
    }
    $target = round($target, $decimals);
    $difference = $target - (float) $timeline[$center][$metric];
    $changed = [];
    foreach ([-1, 1] as $direction) {
        for ($distance = $direction === -1 ? 1 : 0; $distance <= $radius; $distance++) {
            $index = $center + $direction * $distance;
            if (!isset($timeline[$index])) break;
            $row = $timeline[$index];
            if ($row['source'] === 'snapshot' || $row[$metric] === null) break;
            $weight = 1 - $distance / ($radius + 1);
            $value = round(max($minimum, min($maximum,
                (float) $row[$metric] + $difference * $weight)), $decimals);
            if ((float) $row[$metric] === $value) continue;
            $next = $row;
            $next[$metric] = $value;
            if ($metric === 'companyValue' && $row['shareMarketPrice'] !== null
                && (float) $row['companyValue'] > 0) {
                $next['shareMarketPrice'] = round(min(9999999999.99,
                    (float) $row['shareMarketPrice'] * $value / (float) $row['companyValue']), 2);
            }
            $changed[$index] = $next;
        }
    }
    ksort($changed);
    return $changed;
}
