<?php
declare(strict_types=1);

require_once __DIR__ . '/../shares/shareAccess.php';

function aetherCompanyList(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, companyName FROM tblCompany ORDER BY companyName ASC, id ASC');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aetherCompanyExists(PDO $pdo, int $idCompany, bool $lock = false): bool
{
    $stmt = $pdo->prepare('SELECT id FROM tblCompany WHERE id = :idCompany' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute(['idCompany' => $idCompany]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherCompanyInsert(PDO $pdo, string $name, string $foundationDate): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblCompany
            (companyName, description, foundationDate, companyValue, stability, profitability,
             initialCompanyValue, initialShareCount, issuedShareCount)
         VALUES (:companyName, :description, :foundationDate, :companyValue, :stability, :profitability,
                 0.00, 100, 100)'
    );
    $stmt->execute([
        'companyName' => $name, 'description' => '', 'foundationDate' => $foundationDate,
        'companyValue' => '0.00', 'stability' => 0, 'profitability' => 0,
    ]);
    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO tblCompanyAnonymousShares (idCompany, shareCount) VALUES (:idCompany, 0)');
    $stmt->execute(['idCompany' => $id]);
    if (aetherCompanySnapshotHistoryAvailable($pdo)) {
        $stmt = $pdo->prepare("INSERT INTO tblCompanyFoundationSnapshot
            (idCompany, foundationDate, initialCompanyValue, initialShareCount,
             shareMarketPrice, anonymousShareCount, stability, profitability, sourceCode)
            VALUES (:idCompany, :foundationDate, 0.00, 100, 0.00, 0, 0, 0, 'recorded')");
        $stmt->execute(['idCompany' => $id, 'foundationDate' => $foundationDate]);
    }
    return $id;
}

function aetherCompanyLockForUpdate(PDO $pdo, int $idCompany): ?array
{
    $stmt = $pdo->prepare('SELECT id, companyValue FROM tblCompany WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $idCompany]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function aetherCompanyUpdateFields(PDO $pdo, int $idCompany, array $fields): void
{
    $columns = ['companyName', 'description', 'foundationDate', 'companyValue', 'stability', 'profitability'];
    $parts = [];
    $params = ['id' => $idCompany];
    foreach ($columns as $column) {
        if (!array_key_exists($column, $fields)) continue;
        $parts[] = $column . ' = :' . $column;
        $params[$column] = $fields[$column];
    }
    $parts[] = 'updatedAt = CURRENT_TIMESTAMP';
    $stmt = $pdo->prepare('UPDATE tblCompany SET ' . implode(', ', $parts) . ' WHERE id = :id');
    $stmt->execute($params);
}
