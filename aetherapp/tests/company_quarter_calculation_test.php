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
checkQuarter(aetherCompanyQuarterTimelineEnd('2026-10-21', null, []) === aetherCompanyQuarterIndex(1926, 4),
    'Zonder toekomstig event of snapshot moet de grafiek tot de huidige speeldatum lopen.');
checkQuarter(aetherCompanyQuarterTimelineEnd('2026-10-21', ['dateStart' => '2027-02-01', 'dateEnd' => null], [])
    === aetherCompanyQuarterIndex(1927, 1), 'Het eerstvolgende eventkwartaal ontbreekt.');
checkQuarter(aetherCompanyQuarterTimelineEnd('2026-10-21', null, [['dateStart' => '2020-01-01', 'dateEnd' => '2020-01-02']])
    === aetherCompanyQuarterIndex(1926, 4), 'Een oude snapshot mag de grafiek niet vóór vandaag afkappen.');
checkQuarter(aetherCompanyQuarterTimelineEnd('2026-10-21', ['dateStart' => '2027-02-01'], [
    ['dateStart' => '2028-06-30', 'dateEnd' => '2028-07-01'], ['dateStart' => '2027-04-01', 'dateEnd' => null]])
    === aetherCompanyQuarterIndex(1928, 3), 'Een latere aangemaakte snapshot moet volledig zichtbaar zijn, ook over een kwartaalgrens.');
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
echo "Quarter timeline range, interpolation and drag boundaries passed.\n";
