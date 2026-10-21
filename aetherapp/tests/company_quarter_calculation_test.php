<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/companies/companyQuarterMath.php';

function checkQuarter(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$from = [
    'companyValue' => 100000.0, 'issuedShareCount' => 100,
    'shareMarketPrice' => 1000.0, 'stability' => -2, 'profitability' => 3,
];
$to = [
    'companyValue' => 200000.0, 'issuedShareCount' => 250,
    'shareMarketPrice' => 1600.0, 'stability' => 2, 'profitability' => -1,
];
$quarter = aetherCompanyQuarterInterpolate($from, $to, 0.25);
checkQuarter($quarter['companyValue'] === 125000.0, 'Bedrijfswaarde wordt niet lineair verdeeld.');
checkQuarter($quarter['shareMarketPrice'] === 1150.0, 'Marktprijs wordt niet lineair verdeeld.');
checkQuarter($quarter['stability'] === -1 && $quarter['profitability'] === 2,
    'Stabiliteit en rendabiliteit worden niet lineair verdeeld.');
checkQuarter($quarter['issuedShareCount'] === 100, 'Uitgegeven aandelen mogen niet worden geïnterpoleerd.');
$unknownPrice = $from;
$unknownPrice['shareMarketPrice'] = null;
checkQuarter(aetherCompanyQuarterInterpolate($unknownPrice, $to, 0.5)['shareMarketPrice'] === null,
    'Een onbekende aandeelprijs mag niet als nulprijs verschijnen.');
checkQuarter(aetherCompanyQuarterForDate('1926-04-01') === aetherCompanyQuarterForDate('1926-03-31') + 1,
    'Kwartaalgrens klopt niet.');
checkQuarter(aetherCompanyQuarterForOutgameDate('2026-04-01') === aetherCompanyQuarterForDate('1926-04-01'),
    'Eventdatum is niet precies honderd jaar teruggeschoven.');
$timeline = [
    ['year' => 1926, 'quarter' => 1, 'source' => 'snapshot', 'companyValue' => 100.0, 'shareMarketPrice' => 10.0],
    ['year' => 1926, 'quarter' => 2, 'source' => 'lineair', 'companyValue' => 100.0, 'shareMarketPrice' => 10.0],
    ['year' => 1926, 'quarter' => 3, 'source' => 'lineair', 'companyValue' => 100.0, 'shareMarketPrice' => 10.0],
    ['year' => 1926, 'quarter' => 4, 'source' => 'lineair', 'companyValue' => 100.0, 'shareMarketPrice' => 10.0],
    ['year' => 1927, 'quarter' => 1, 'source' => 'snapshot', 'companyValue' => 100.0, 'shareMarketPrice' => 10.0],
];
$dragged = aetherCompanyQuarterDragValues($timeline, 2, 'companyValue', 140.0, 2);
checkQuarter(array_keys($dragged) === [1, 2, 3], 'Sleepactie mag snapshots niet wijzigen.');
checkQuarter($dragged[2]['companyValue'] === 140.0 && $dragged[2]['shareMarketPrice'] === 14.0,
    'Een versleept punt moet de aandeelprijs evenredig aanpassen.');
echo "Quarter interpolation and drag boundaries passed.\n";
