<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/decimal.php';

/** Keep the established 100/110 profit basis, then apportion by sold shares. */
function aetherShareDividendPool(string $profitAmount): string
{
    if (aetherDecimalCompare($profitAmount, '0.00') <= 0) return '0.00';
    return aetherDecimalMultiplyRatio($profitAmount, 100, 110);
}

/**
 * Allocate whole cents by largest remainder. Anonymous shares take their fraction
 * of the pool but receive no character payout; that money stays with the company.
 * @param array<int,int> $holdings character ID => number of shares
 * @return array{payouts:array<int,string>,anonymousAmount:string}
 */
function aetherShareDividendAllocation(string $pool, array $holdings, int $anonymousShares): array
{
    $minor = aetherDecimalToMinorUnits($pool);
    if ($minor < 0 || $anonymousShares < 0) throw new DomainException('Ongeldige dividendbasis.');
    $sold = $anonymousShares;
    foreach ($holdings as $id => $shares) {
        if ((int) $id <= 0 || $shares <= 0) throw new DomainException('Ongeldig aandelenbezit.');
        $sold += $shares;
    }
    if ($sold === 0 || $minor === 0) return ['payouts' => [], 'anonymousAmount' => '0.00'];
    $weights = $holdings;
    if ($anonymousShares > 0) $weights[0] = $anonymousShares;
    $assigned = [];
    $remainders = [];
    $allocated = 0;
    foreach ($weights as $id => $shares) {
        $product = $minor * $shares;
        $base = intdiv($product, $sold);
        $assigned[(int) $id] = $base;
        $remainders[(int) $id] = $product % $sold;
        $allocated += $base;
    }
    $order = array_keys($remainders);
    usort($order, static fn(int $a, int $b): int =>
        ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
    for ($i = 0, $left = $minor - $allocated; $i < $left; $i++) $assigned[$order[$i]]++;
    $payouts = [];
    foreach ($holdings as $id => $_) $payouts[(int) $id] = aetherMinorUnitsToDecimal($assigned[(int) $id]);
    return ['payouts' => $payouts,
            'anonymousAmount' => aetherMinorUnitsToDecimal($assigned[0] ?? 0)];
}
