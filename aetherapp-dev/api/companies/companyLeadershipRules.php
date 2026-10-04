<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/decimal.php';

/** One value scale is shared by the leader trait, income and personnel rules. */
function aetherCompanyLeaderTier(mixed $value): array
{
    $amount = aetherNormalizeDecimal($value, 2);
    if (aetherDecimalCompare($amount, '30000.00') <= 0) return ['key' => 'micro', 'statusPoints' => 1, 'personnelRequirement' => 0];
    if (aetherDecimalCompare($amount, '450000.00') <= 0) return ['key' => 'family', 'statusPoints' => 2, 'personnelRequirement' => 1];
    if (aetherDecimalCompare($amount, '6750000.00') <= 0) return ['key' => 'national', 'statusPoints' => 3, 'personnelRequirement' => 2];
    if (aetherDecimalCompare($amount, '101250000.00') <= 0) return ['key' => 'small_international', 'statusPoints' => 4, 'personnelRequirement' => 3];
    return ['key' => 'large_international', 'statusPoints' => 5, 'personnelRequirement' => 4];
}

/** Power is evaluated as a binary double, then rounded half-up to cents once. */
function aetherCompanyLeaderMonthlyIncome(mixed $value): string
{
    $amount = aetherNormalizeDecimal($value, 2);
    if (aetherDecimalCompare($amount, '0.00') <= 0) return '0.00';
    return number_format(round(15.0 * pow((float) $amount, 0.28), 2, PHP_ROUND_HALF_UP), 2, '.', '');
}

function aetherCompanyLeadershipAvailable(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->query("SELECT 1 FROM tblSchemaMigration WHERE migrationCode = '0011_company_leadership' LIMIT 1");
    return $cache[$key] = $stmt->fetchColumn() !== false;
}

function aetherCompanyLeaderRowsForCharacter(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0 || !aetherCompanyLeadershipAvailable($pdo)
        || !aetherCharacterHasCompanyLeaderProfession($pdo, $idCharacter)) return [];
    $stmt = $pdo->prepare('SELECT cp.idCompany, cp.salaryIncreasePercentage, co.companyValue
                             FROM tblCompanyPersonnel AS cp
                             JOIN tblCompany AS co ON co.id = cp.idCompany
                            WHERE cp.idCharacter = :idCharacter
                            ORDER BY cp.idCompany');
    $stmt->execute(['idCharacter' => $idCharacter]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Leadership follows the character's profession, never a client-controlled personnel flag. */
function aetherCharacterHasCompanyLeaderProfession(PDO $pdo, int $idCharacter): bool
{
    if ($idCharacter <= 0) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM tblLinkCharacterTrait AS link
        JOIN tblTrait AS trait ON trait.id = link.idTrait
        WHERE link.idCharacter = :idCharacter
          AND trait.`class` = 'middle class' AND trait.`type` = 'profession'
          AND trait.name IN ('Bedrijfsleider', 'Bedrijfsleider klein', 'Bedrijfsleider groot')
        LIMIT 1");
    $stmt->execute(['idCharacter' => $idCharacter]);
    return $stmt->fetchColumn() !== false;
}

function aetherCharacterHasCompanyPersonnel(PDO $pdo, int $idCharacter): bool
{
    if ($idCharacter <= 0) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM tblCompanyPersonnel WHERE idCharacter = :idCharacter LIMIT 1');
    $stmt->execute(['idCharacter' => $idCharacter]);
    return $stmt->fetchColumn() !== false;
}

/** Historical A/B leader definitions are one semantic type once leadership is mapped. */
function aetherIsLegacyCompanyLeaderTrait(array $trait): bool
{
    if (($trait['leaderSemanticCode'] ?? null) === 'company_leader') return true;
    $code = (string) ($trait['domainCode'] ?? '');
    if (in_array($code, ['company_leader_legacy_small', 'company_leader_legacy_large'], true)) return true;
    return (string) ($trait['class'] ?? '') === 'middle class'
        && (string) ($trait['type'] ?? '') === 'profession'
        && in_array((string) ($trait['name'] ?? ''), ['Bedrijfsleider', 'Bedrijfsleider klein', 'Bedrijfsleider groot'], true);
}
