<?php
declare(strict_types=1);

require_once __DIR__ . '/characterFinanceRepository.php';

/** @return array<int, array<string, mixed>> */
function aetherShareLockCompanies(PDO $pdo, array $companyIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $companyIds), static fn(int $id): bool => $id > 0)));
    sort($ids, SORT_NUMERIC);
    if ($ids === []) return [];
    $stmt = $pdo->prepare(
        'SELECT id, companyName, companyValue FROM tblCompany WHERE id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id FOR UPDATE'
    );
    $stmt->execute($ids);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int) $row['id']] = $row;
    return $rows;
}

function aetherShareFetchLink(PDO $pdo, int $linkId, bool $forUpdate = false): ?array
{
    $stmt = $pdo->prepare(
        'SELECT lct.id, lct.idCharacter, lct.rankValue, lct.idTrait,
                c.type AS characterType, c.state AS characterState, c.idUser, c.bankaccount,
                lctc.idCompany, COALESCE(lctc.extraPercentage, 0) AS extraPercentage
           FROM tblLinkCharacterTrait AS lct
           JOIN tblCharacter AS c ON c.id = lct.idCharacter
           LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
          WHERE lct.id = :idLinkCharacterTrait' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute(['idLinkCharacterTrait' => $linkId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherShareAllocatedPercentage(PDO $pdo, int $companyId, int $excludeLinkId = 0): int
{
    $rows = dbAll(
        $pdo,
        'SELECT lct.id, lct.idTrait, lct.rankValue, COALESCE(lctc.extraPercentage, 0) AS extraPercentage
           FROM tblLinkCharacterTraitCompany AS lctc
           JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
          WHERE lctc.idCompany = :idCompany
          FOR UPDATE',
        ['idCompany' => $companyId]
    );
    $total = 0;
    foreach ($rows as $row) {
        if ($excludeLinkId > 0 && (int) $row['id'] === $excludeLinkId) continue;
        $trait = getTraitDefinition($pdo, (int) $row['idTrait']);
        if ($trait && isCompanyShareTrait($trait)) {
            $total += max(0, (int) $row['rankValue'] + (int) $row['extraPercentage']);
        }
    }
    return $total;
}

function aetherShareUpsertCompanyLink(PDO $pdo, int $linkId, ?int $companyId, int $extraPercentage): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkCharacterTraitCompany (idLinkCharacterTrait, idCompany, extraPercentage)
         VALUES (:idLinkCharacterTrait, :idCompany, :extraPercentage)
         ON DUPLICATE KEY UPDATE idCompany = VALUES(idCompany), extraPercentage = VALUES(extraPercentage)'
    );
    $stmt->execute([
        'idLinkCharacterTrait' => $linkId, 'idCompany' => $companyId,
        'extraPercentage' => max(0, $extraPercentage),
    ]);
}

function aetherShareFindCharacterTraitLink(PDO $pdo, int $characterId, int $traitId): ?array
{
    return dbOne($pdo, 'SELECT id FROM tblLinkCharacterTrait WHERE idCharacter = :idCharacter AND idTrait = :idTrait LIMIT 1', [
        'idCharacter' => $characterId, 'idTrait' => $traitId,
    ]);
}

function aetherShareInsertTraitLink(PDO $pdo, int $characterId, int $traitId): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkCharacterTrait (idCharacter, idTrait, rankValue) VALUES (:idCharacter, :idTrait, 1)'
    );
    $stmt->execute(['idCharacter' => $characterId, 'idTrait' => $traitId]);
    return (int) $pdo->lastInsertId();
}

function aetherShareSetBaseRank(PDO $pdo, int $linkId, int $rank): void
{
    $stmt = $pdo->prepare('UPDATE tblLinkCharacterTrait SET rankValue = :rankValue WHERE id = :id');
    $stmt->execute(['rankValue' => $rank, 'id' => $linkId]);
}

function aetherShareDeleteLink(PDO $pdo, int $linkId): void
{
    $pdo->prepare('DELETE FROM tblLinkCharacterTraitCompany WHERE idLinkCharacterTrait = :id')->execute(['id' => $linkId]);
    $pdo->prepare('DELETE FROM tblLinkCharacterTrait WHERE id = :id')->execute(['id' => $linkId]);
}
