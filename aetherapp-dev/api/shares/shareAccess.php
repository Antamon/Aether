<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/../characters/characterAccess.php';
require_once __DIR__ . '/shareException.php';

function aetherShareMigrationActive(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tblSchemaMigration
          WHERE migrationCode IN ('0006_share_reform_schema',
                                  '0007_share_reform_backfill',
                                  '0008_share_domain_codes',
                                  '0009_share_status_initialization',
                                  '0010_holder_sale_offer')"
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_NUM);
    return (int) ($row[0] ?? 0) === 5;
}

function aetherCompanySnapshotHistoryAvailable(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->query("SELECT 1 FROM tblSchemaMigration
        WHERE migrationCode = '0012_company_snapshot_history' LIMIT 1");
    return $cache[$key] = $stmt->fetchColumn() !== false;
}

function aetherShareLegacyWritesClosed(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM tblSchemaMigration
          WHERE migrationCode = '0007_share_reform_backfill' LIMIT 1"
    );
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherRequireShareMigration(PDO $pdo): void
{
    if (!aetherShareMigrationActive($pdo)) {
        throw new AetherShareException(503, 'Het nieuwe aandelensysteem is nog niet beschikbaar.');
    }
}

function aetherCanTradeForCharacter(array $user, array $character): bool
{
    return aetherCanEditCharacter($user, $character)
        || aetherIsPrivilegedRole((string) ($user['role'] ?? ''));
}

function aetherIsCompanyBoardMember(PDO $pdo, array $user, int $companyId, int $characterId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
           FROM tblCompanyPersonnel AS cp
           JOIN tblCharacter AS c ON c.id = cp.idCharacter
          WHERE cp.idCompany = :idCompany
            AND cp.idCharacter = :idCharacter
            AND cp.isBoardMember = 1
            AND c.idUser = :idUser
          LIMIT 1'
    );
    $stmt->execute([
        'idCompany' => $companyId,
        'idCharacter' => $characterId,
        'idUser' => (int) ($user['id'] ?? 0),
    ]);
    return $stmt->fetchColumn() !== false;
}

function aetherCanManageShareCompany(array $user): bool
{
    return aetherIsPrivilegedRole((string) ($user['role'] ?? ''));
}
