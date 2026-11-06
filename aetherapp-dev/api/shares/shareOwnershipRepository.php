<?php
declare(strict_types=1);

function aetherCompanyShareOwnershipAvailable(PDO $pdo): bool
{
    static $cache;
    $cache ??= new WeakMap();
    if (isset($cache[$pdo])) return $cache[$pdo];
    try {
        $stmt = $pdo->query("SELECT 1 FROM tblSchemaMigration WHERE migrationCode = '0023_company_share_ownership' LIMIT 1");
        return $cache[$pdo] = $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        // Old installations and isolated fixtures can predate the migration registry.
        if ($e->getCode() === '42S02' || str_contains($e->getMessage(), 'no such table: tblSchemaMigration')) return false;
        throw $e;
    }
}

/** SQL source exclusively for totals; character portfolios remain character-only. */
function aetherShareOwnershipRowsSql(PDO $pdo): string
{
    return aetherCompanyShareOwnershipAvailable($pdo)
        ? '(SELECT idCompany, shareCount FROM tblCompanyShareHolding UNION ALL SELECT idCompany, shareCount FROM tblCompanyShareCompanyHolding)'
        : 'tblCompanyShareHolding';
}
