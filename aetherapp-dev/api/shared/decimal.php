<?php
declare(strict_types=1);

/**
 * Normalize a plain decimal value without using binary floating-point arithmetic.
 *
 * @throws InvalidArgumentException
 */
function aetherNormalizeDecimal(mixed $value, int $scale = 2): string
{
    if (is_int($value)) {
        $raw = (string) $value;
    } elseif (is_float($value)) {
        if (!is_finite($value)) {
            throw new InvalidArgumentException('Het bedrag moet eindig zijn.');
        }
        $raw = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($raw)) {
            throw new InvalidArgumentException('Het bedrag is ongeldig.');
        }
    } elseif (is_string($value)) {
        $raw = trim($value);
    } else {
        throw new InvalidArgumentException('Het bedrag moet een getal zijn.');
    }

    if (!preg_match('/^-?(?:0|[1-9]\d*)(?:\.(\d+))?$/D', $raw, $matches)) {
        throw new InvalidArgumentException('Het bedrag moet een gewoon decimaal getal zijn.');
    }

    $fraction = $matches[1] ?? '';
    if (strlen($fraction) > $scale) {
        throw new InvalidArgumentException("Het bedrag mag maximaal {$scale} decimalen bevatten.");
    }

    $negative = str_starts_with($raw, '-');
    $unsigned = $negative ? substr($raw, 1) : $raw;
    [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
    $integer = ltrim($integer, '0');
    $integer = $integer === '' ? '0' : $integer;
    $fraction = str_pad($fraction, $scale, '0');
    $normalized = $integer . ($scale > 0 ? '.' . $fraction : '');

    return $negative && preg_match('/^0(?:\.0+)?$/D', $normalized) !== 1
        ? '-' . $normalized
        : $normalized;
}

function aetherDecimalToMinorUnits(string $value, int $scale = 2): int
{
    $normalized = aetherNormalizeDecimal($value, $scale);
    $negative = str_starts_with($normalized, '-');
    $unsigned = $negative ? substr($normalized, 1) : $normalized;
    [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
    $digits = ltrim($integer . str_pad($fraction, $scale, '0'), '0');
    $minor = $digits === '' ? 0 : (int) $digits;
    return $negative ? -$minor : $minor;
}

function aetherMinorUnitsToDecimal(int $minor, int $scale = 2): string
{
    $negative = $minor < 0;
    $digits = str_pad((string) abs($minor), $scale + 1, '0', STR_PAD_LEFT);
    $integer = $scale > 0 ? substr($digits, 0, -$scale) : $digits;
    $fraction = $scale > 0 ? substr($digits, -$scale) : '';
    return ($negative ? '-' : '') . $integer . ($scale > 0 ? '.' . $fraction : '');
}

function aetherDecimalCompare(string $left, string $right, int $scale = 2): int
{
    return aetherDecimalToMinorUnits($left, $scale) <=> aetherDecimalToMinorUnits($right, $scale);
}

function aetherDecimalMultiplyRatio(string $value, int $numerator, int $denominator): string
{
    if ($denominator <= 0) {
        throw new InvalidArgumentException('De deler moet groter zijn dan nul.');
    }
    $minor = aetherDecimalToMinorUnits($value);
    $product = $minor * $numerator;
    $rounded = intdiv(abs($product) + intdiv($denominator, 2), $denominator);
    return aetherMinorUnitsToDecimal($product < 0 ? -$rounded : $rounded);
}

function aetherDecimalAdd(string $left, string $right): string
{
    return aetherMinorUnitsToDecimal(aetherDecimalToMinorUnits($left) + aetherDecimalToMinorUnits($right));
}

function aetherDecimalSubtract(string $left, string $right): string
{
    return aetherMinorUnitsToDecimal(aetherDecimalToMinorUnits($left) - aetherDecimalToMinorUnits($right));
}
