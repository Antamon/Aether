<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/shares/shareRules.php';

$failures = [];
function shareRuleCheck(bool $valid, string $description): void
{
    global $failures;
    if (!$valid) $failures[] = $description;
}

foreach ([
    ['0.00', 0, 0], ['599999.99', 0, 0], ['600000.00', 1, 0],
    ['1200000.00', 2, 0], ['2500000.00', 3, 1],
    ['5000000.00', 4, 2], ['10000000.00', 5, 3],
    ['20000000.00', 6, 4], ['40000000.00', 7, 5],
] as [$value, $level, $staff]) {
    $status = aetherShareStatusForPortfolio($value);
    shareRuleCheck($status['level'] === $level
        && $status['usedStatusPoints'] === $level
        && $status['personnelRequirement'] === $staff, "Drempel {$value}");
}
shareRuleCheck(aetherShareFreeCount(100, 95, 0) === 5, 'Vrije voorraad na omzetting');
shareRuleCheck(aetherShareFreeCount(1000, 95, 0) === 905, 'Initiale correctie verandert alleen vrije voorraad');
shareRuleCheck(aetherShareFreeCount(1000, 1, 0) === 999, 'Een bestaand aandeel blijft een aandeel na correctie');
shareRuleCheck(aetherShareFreeCount(100, 10, 25) === 65, 'Anonieme aandelen zijn niet vrij');
shareRuleCheck(aetherShareValueAfterBuyback('1000.00', 100, 10) === '900.00', 'Inkoop houdt waarde per aandeel gelijk');
shareRuleCheck(aetherShareValueAfterBuyback('1000.01', 3, 1) === '666.67', 'Inkoop rondt op centen af');
shareRuleCheck(aetherSharePurchaseTotal('12.34', 3) === '37.02', 'Aankoopbedrag in centen');
foreach (['1199999.99' => 1, '2499999.99' => 2, '4999999.99' => 3,
          '9999999.99' => 4, '19999999.99' => 5, '39999999.99' => 6] as $value => $level) {
    shareRuleCheck(aetherShareStatusForPortfolio($value)['level'] === $level,
        "Net onder grens {$value}");
}
foreach ([
    1 => [30, 1, 10, 10, 40],
    2 => [50, 10, 30, 0, 20],
    3 => [70, 30, 60, -10, 10],
    4 => [90, 40, 80, -15, 5],
] as $level => [$chance, $quantityMin, $quantityMax, $priceMin, $priceMax]) {
    $band = aetherShareSearchBand($level);
    shareRuleCheck($band === compact('chance', 'quantityMin', 'quantityMax', 'priceMin', 'priceMax'),
                   "Fiscaliteit-niveau {$level}");
    shareRuleCheck(aetherShareSearchQuantity(101, $quantityMin) === intdiv(101 * $quantityMin + 99, 100),
                   "Voorraad afronden bij niveau {$level}");
    shareRuleCheck(aetherShareSearchPrice('100.00', $priceMin) === aetherMinorUnitsToDecimal(10000 + 100 * $priceMin),
                   "Minimumprijs niveau {$level}");
    shareRuleCheck(aetherShareSearchPrice('100.00', $priceMax) === aetherMinorUnitsToDecimal(10000 + 100 * $priceMax),
                   "Maximumprijs niveau {$level}");
}
shareRuleCheck(aetherShareSearchQuantity(1, 1) === 1, 'Een beschikbaar aandeel rondt naar een');
shareRuleCheck(aetherShareSearchPrice('0.01', -15) === '0.01', 'Marktprijs blijft minstens een cent');
foreach ([
    static fn() => aetherShareFreeCount(100, 101, 0),
    static fn() => aetherShareValueAfterBuyback('100.00', 100, 100),
    static fn() => aetherSharePurchaseTotal('0.00', 1),
    static fn() => aetherShareSearchBand(0),
] as $index => $invalid) {
    try {
        $invalid();
        shareRuleCheck(false, "Ongeldige casus {$index} toegestaan");
    } catch (DomainException $e) {
        // Expected.
    }
}
if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Share rules passed.\n";
