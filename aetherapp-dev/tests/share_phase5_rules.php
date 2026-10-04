<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/companies/companyLeadershipRules.php';
require_once __DIR__ . '/../api/shares/shareDividendRules.php';

function expectPhase5(mixed $actual, mixed $expected, string $name): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($name . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

foreach ([
    ['0.00', 'micro', 1, 0],
    ['30000.00', 'micro', 1, 0],
    ['30000.01', 'family', 2, 1],
    ['450000.00', 'family', 2, 1],
    ['450000.01', 'national', 3, 2],
    ['6750000.01', 'small_international', 4, 3],
    ['101250000.01', 'large_international', 5, 4],
] as [$value, $key, $points, $staff]) {
    $tier = aetherCompanyLeaderTier($value);
    expectPhase5($tier['key'], $key, 'bedrijfstype ' . $value);
    expectPhase5($tier['statusPoints'], $points, 'statuspunten ' . $value);
    expectPhase5($tier['personnelRequirement'], $staff, 'personeelsvoorwaarde ' . $value);
}
expectPhase5(aetherCompanyLeaderMonthlyIncome('0.00'), '0.00', 'nulinkomen');
expectPhase5(aetherCompanyLeaderMonthlyIncome('1.00'), '15.00', 'basisinkomen');
expectPhase5(aetherShareDividendPool('110.00'), '100.00', 'dividendbasis');
expectPhase5(aetherShareDividendAllocation('100.00', [1 => 1, 2 => 1], 1),
    ['payouts' => [1 => '33.33', 2 => '33.33'], 'anonymousAmount' => '33.34'],
    'anonieme aandelen delen mee in de noemer');
expectPhase5(aetherShareDividendAllocation('100.00', [], 0),
    ['payouts' => [], 'anonymousAmount' => '0.00'], 'geen verkochte aandelen');
expectPhase5(aetherShareDividendAllocation('0.02', [10 => 1, 20 => 1, 30 => 1], 0),
    ['payouts' => [10 => '0.01', 20 => '0.01', 30 => '0.00'], 'anonymousAmount' => '0.00'],
    'restcenten gaan deterministisch naar aandeelhouders');
expectPhase5(aetherShareDividendAllocation('0.03', [], 2),
    ['payouts' => [], 'anonymousAmount' => '0.03'],
    'anonieme aandelen ontvangen nooit een personage-uitbetaling');

echo "Phase 5 pure rules OK\n";
