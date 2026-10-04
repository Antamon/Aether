<?php
declare(strict_types=1);

/** @return array<int, array<string, mixed>> */
function aetherFinanceLockCharacters(PDO $pdo, array $characterIds): array
{
    $ids = array_values(array_unique(array_map('intval', $characterIds)));
    sort($ids, SORT_NUMERIC);
    if ($ids === [] || in_array(0, $ids, true)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, idUser, type, state, `class`, bankaccount, securitiesaccount,
                securitiesManagerType, securitiesManagerCharacterId, securitiesRiskProfile
           FROM tblCharacter
          WHERE id IN ({$placeholders})
          ORDER BY id
          FOR UPDATE"
    );
    $stmt->execute($ids);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

function aetherFinanceLockCharacter(PDO $pdo, int $characterId): ?array
{
    return aetherFinanceLockCharacters($pdo, [$characterId])[$characterId] ?? null;
}

function aetherFinanceFetchEvent(PDO $pdo, int $eventId): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, dateStart, dateEnd FROM tblEvent WHERE id = :id');
    $stmt->execute(['id' => $eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherFinanceInsertBankTransfer(PDO $pdo, array $values): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacterBankTransaction
            (idSourceCharacter, idTargetCharacter, amount, transactionDate, description, createdAt, createdBy)
         VALUES
            (:idSourceCharacter, :idTargetCharacter, :amount, :transactionDate, :description, NOW(), :createdBy)'
    );
    $stmt->execute($values);
    return (int) $pdo->lastInsertId();
}

function aetherFinanceAdjustBankBalance(PDO $pdo, int $characterId, string $amount): void
{
    $stmt = $pdo->prepare(
        'UPDATE tblCharacter SET bankaccount = bankaccount + :amount WHERE id = :idCharacter'
    );
    $stmt->execute(['amount' => $amount, 'idCharacter' => $characterId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Character balance update failed.');
    }
}

function aetherFinanceAdjustBalances(
    PDO $pdo,
    int $characterId,
    string $bankDelta,
    string $securitiesDelta
): void {
    $stmt = $pdo->prepare(
        'UPDATE tblCharacter
            SET bankaccount = bankaccount + :bankDelta,
                securitiesaccount = securitiesaccount + :securitiesDelta
          WHERE id = :idCharacter'
    );
    $stmt->execute([
        'bankDelta' => $bankDelta,
        'securitiesDelta' => $securitiesDelta,
        'idCharacter' => $characterId,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Character balances update failed.');
    }
}

function aetherFinanceLockBankTransaction(PDO $pdo, int $transactionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, idSourceCharacter, idTargetCharacter, amount
           FROM tblCharacterBankTransaction WHERE id = :id FOR UPDATE'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherFinanceFetchBankTransaction(PDO $pdo, int $transactionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, idSourceCharacter, idTargetCharacter, amount
           FROM tblCharacterBankTransaction WHERE id = :id'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherFinanceDeleteBankTransaction(PDO $pdo, int $transactionId): void
{
    $stmt = $pdo->prepare('DELETE FROM tblCharacterBankTransaction WHERE id = :id');
    $stmt->execute(['id' => $transactionId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Bank transaction delete failed.');
    }
}

function aetherFinanceSnapshotExists(PDO $pdo, int $characterId, int $eventId): bool
{
    $stmt = $pdo->prepare(
        'SELECT id FROM tblCharacterEconomySnapshot
          WHERE idCharacter = :idCharacter AND idEvent = :idEvent LIMIT 1'
    );
    $stmt->execute(['idCharacter' => $characterId, 'idEvent' => $eventId]);
    return $stmt->fetchColumn() !== false;
}

function aetherFinanceInsertEconomySnapshot(PDO $pdo, array $values): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO tblCharacterEconomySnapshot
            (idCharacter, idEvent, amount, transactionDate, securitiesBalanceSnapshot,
             securitiesManagerType, securitiesManagerCharacterId, securitiesRiskProfile,
             securitiesManagerSkillLevel, securitiesBasePercentage,
             securitiesVariationLimitPercentage, securitiesVariationPercentage,
             securitiesReturnPercentage, securitiesReturnAmount, securitiesStatus, createdAt, createdBy)
         VALUES
            (:idCharacter, :idEvent, :amount, :transactionDate, :securitiesBalanceSnapshot,
             :securitiesManagerType, :securitiesManagerCharacterId, :securitiesRiskProfile,
             :securitiesManagerSkillLevel, :securitiesBasePercentage,
             :securitiesVariationLimitPercentage, :securitiesVariationPercentage,
             :securitiesReturnPercentage, :securitiesReturnAmount, :securitiesStatus, NOW(), :createdBy)"
    );
    $stmt->execute($values);
    return (int) $pdo->lastInsertId();
}

function aetherFinanceLockEconomySnapshot(PDO $pdo, int $snapshotId, ?int $characterId = null): ?array
{
    $characterClause = $characterId === null ? '' : ' AND ces.idCharacter = :idCharacter';
    $params = ['idSnapshot' => $snapshotId];
    if ($characterId !== null) {
        $params['idCharacter'] = $characterId;
    }
    $stmt = $pdo->prepare(
        "SELECT ces.*, e.title AS eventTitle, e.dateEnd,
                c.state, c.idUser, c.type, c.`class`,
                withdrawalTx.id AS securitiesSnapshotWithdrawalTransactionId,
                withdrawalTx.bankAmount AS securitiesSnapshotWithdrawalBankAmount
           FROM tblCharacterEconomySnapshot AS ces
           JOIN tblCharacter AS c ON c.id = ces.idCharacter
           JOIN tblEvent AS e ON e.id = ces.idEvent
           LEFT JOIN tblCharacterSecuritiesTransaction AS withdrawalTx
             ON withdrawalTx.idCharacter = ces.idCharacter
            AND withdrawalTx.description LIKE CONCAT('[snapshot-withdrawal:', ces.id, ']%')
          WHERE ces.id = :idSnapshot{$characterClause}
          FOR UPDATE"
    );
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherFinanceFetchSnapshotCharacterId(PDO $pdo, int $snapshotId): ?int
{
    $stmt = $pdo->prepare('SELECT idCharacter FROM tblCharacterEconomySnapshot WHERE id = :idSnapshot');
    $stmt->execute(['idSnapshot' => $snapshotId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
}

function aetherFinanceInsertSecuritiesTransaction(PDO $pdo, array $values): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacterSecuritiesTransaction
            (idCharacter, direction, securitiesAmount, bankAmount, transactionDate, description, createdBy)
         VALUES
            (:idCharacter, :direction, :securitiesAmount, :bankAmount, :transactionDate, :description, :createdBy)'
    );
    $stmt->execute($values);
    return (int) $pdo->lastInsertId();
}

function aetherFinanceUpdateSecuritiesSettings(PDO $pdo, int $characterId, string $managerType, ?int $managerId, int $risk): void
{
    $stmt = $pdo->prepare(
        'UPDATE tblCharacter SET securitiesManagerType = :managerType,
            securitiesManagerCharacterId = :managerCharacterId, securitiesRiskProfile = :riskProfile
          WHERE id = :idCharacter'
    );
    $stmt->execute([
        'managerType' => $managerType, 'managerCharacterId' => $managerId,
        'riskProfile' => $risk, 'idCharacter' => $characterId,
    ]);
}

function aetherFinanceActiveCharacterExists(PDO $pdo, int $characterId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM tblCharacter WHERE id = :idCharacter AND state = 'active'");
    $stmt->execute(['idCharacter' => $characterId]);
    return $stmt->fetchColumn() !== false;
}

function aetherFinanceDeleteEconomySnapshot(PDO $pdo, int $snapshotId): void
{
    $stmt = $pdo->prepare('DELETE FROM tblCharacterEconomySnapshot WHERE id = :idSnapshot');
    $stmt->execute(['idSnapshot' => $snapshotId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Economy snapshot delete failed.');
    }
}

function aetherFinanceUpdateSnapshot(PDO $pdo, int $snapshotId, array $fields): void
{
    $allowed = [
        'securitiesVariationPercentage', 'securitiesReturnPercentage', 'securitiesReturnAmount',
        'securitiesStatus', 'securitiesApprovedAt', 'securitiesApprovedBy',
        'securitiesSnapshotWithdrawalAmount',
    ];
    $parts = [];
    foreach (array_keys($fields) as $field) {
        if (!in_array($field, $allowed, true)) {
            throw new LogicException("Unsupported snapshot field {$field}.");
        }
        $parts[] = "{$field} = :{$field}";
    }
    $fields['idSnapshot'] = $snapshotId;
    $stmt = $pdo->prepare('UPDATE tblCharacterEconomySnapshot SET ' . implode(', ', $parts) . ' WHERE id = :idSnapshot');
    $stmt->execute($fields);
}

function aetherFinanceUpdateSecuritiesTransaction(PDO $pdo, int $transactionId, array $values): void
{
    $values['id'] = $transactionId;
    $stmt = $pdo->prepare(
        'UPDATE tblCharacterSecuritiesTransaction
            SET securitiesAmount = :securitiesAmount, bankAmount = :bankAmount,
                transactionDate = :transactionDate, description = :description
          WHERE id = :id'
    );
    $stmt->execute($values);
}

function aetherFinanceDeleteSecuritiesTransaction(PDO $pdo, int $transactionId): void
{
    $stmt = $pdo->prepare('DELETE FROM tblCharacterSecuritiesTransaction WHERE id = :id');
    $stmt->execute(['id' => $transactionId]);
}
