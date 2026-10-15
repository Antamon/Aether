<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/decimal.php';

/** @return array{level:int,usedStatusPoints:int,personnelRequirement:int} */
function aetherShareStatusForPortfolio(string $intrinsicValue): array
{
    $value = aetherDecimalToMinorUnits($intrinsicValue);
    if ($value < 0) {
        throw new InvalidArgumentException('Een portefeuillewaarde kan niet negatief zijn.');
    }

    // De eerste grens is inclusief; onder Fr 600.000 blijft het niveau nul.
    $thresholds = [60000000, 120000000, 250000000, 500000000,
                   1000000000, 2000000000];
    $level = 0;
    foreach ($thresholds as $index => $threshold) {
        if ($value < $threshold) {
            break;
        }
        $level = $index + 1;
    }
    if ($level === 6) {
        $threshold = $thresholds[5];
        while ($threshold <= intdiv(PHP_INT_MAX, 2) && $value >= $threshold * 2) {
            $threshold *= 2;
            $level++;
        }
    }

    return [
        'level' => $level,
        'usedStatusPoints' => $level,
        'personnelRequirement' => max(0, $level - 2),
    ];
}

function aetherShareFreeCount(int $issued, int $held, int $anonymous): int
{
    if ($issued <= 0 || $held < 0 || $anonymous < 0 || $held > $issued - $anonymous) {
        throw new DomainException('Ongeldige aandelenvoorraad.');
    }
    return $issued - $held - $anonymous;
}

/** The company buys back and retires only free shares. */
function aetherShareValueAfterBuyback(string $companyValue, int $issued, int $quantity): string
{
    if ($issued <= 0 || $quantity <= 0 || $quantity >= $issued) {
        throw new DomainException('Ongeldig aantal voor bedrijfsinkoop.');
    }
    $value = aetherNormalizeDecimal($companyValue);
    if (aetherDecimalCompare($value, '0.00') < 0) {
        throw new DomainException('Ongeldige bedrijfswaarde.');
    }
    return aetherDecimalMultiplyRatio($value, $issued - $quantity, $issued);
}

function aetherSharePurchaseTotal(string $unitPrice, int $quantity): string
{
    if ($quantity <= 0 || $quantity > 1000000) {
        throw new DomainException('Ongeldig aantal aandelen.');
    }
    $price = aetherNormalizeDecimal($unitPrice);
    if (aetherDecimalCompare($price, '0.00') <= 0) {
        throw new DomainException('Aandeelprijs moet positief zijn.');
    }
    $total = aetherDecimalToMinorUnits($price) * $quantity;
    if ($total > 999999999999) {
        throw new DomainException('De betaling overschrijdt het bestaande geldkolombereik.');
    }
    return aetherMinorUnitsToDecimal($total);
}

/** The ranges are inclusive. priceRoll is stored as modifier + 100 in the unsigned SQL column. */
function aetherShareSearchBand(int $level): array
{
    return match ($level) {
        1 => ['chance' => 30, 'quantityMin' => 1, 'quantityMax' => 10, 'priceMin' => 10, 'priceMax' => 40],
        2 => ['chance' => 50, 'quantityMin' => 10, 'quantityMax' => 30, 'priceMin' => 0, 'priceMax' => 20],
        3 => ['chance' => 70, 'quantityMin' => 30, 'quantityMax' => 60, 'priceMin' => -10, 'priceMax' => 10],
        4 => ['chance' => 90, 'quantityMin' => 40, 'quantityMax' => 80, 'priceMin' => -15, 'priceMax' => 5],
        default => throw new DomainException('Ongeldig Fiscaliteit-zoekniveau.'),
    };
}

function aetherShareSearchQuantity(int $freeShares, int $percent): int
{
    if ($freeShares <= 0 || $percent < 1 || $percent > 100) {
        throw new DomainException('Ongeldige zoekvoorraad.');
    }
    return intdiv($freeShares * $percent + 99, 100);
}

function aetherShareSearchPrice(string $marketPrice, int $modifier): string
{
    if ($modifier < -99 || $modifier > 100) throw new DomainException('Ongeldige prijsaanpassing.');
    $price = aetherDecimalMultiplyRatio($marketPrice, 100 + $modifier, 100);
    if (aetherDecimalCompare($price, '0.00') <= 0) {
        throw new DomainException('De marktprijs is te laag voor een geldig aanbod.');
    }
    return $price;
}
