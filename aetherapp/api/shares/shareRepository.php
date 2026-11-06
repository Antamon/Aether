<?php
declare(strict_types=1);

require_once __DIR__ . '/shareOwnershipRepository.php';

/** Use the same dated valuation as the company card, including saved future snapshots. */
function aetherShareValuationSql(): array
{
    $recordedValue = 'COALESCE(valuationSnapshot.companyValue, valuationFoundation.initialCompanyValue)';
    return [
        'joins' => 'LEFT JOIN tblCompanySnapshot AS valuationSnapshot ON valuationSnapshot.id = (
            SELECT s.id FROM tblCompanySnapshot AS s JOIN tblEvent AS e ON e.id = s.idEvent
            WHERE s.idCompany = c.id ORDER BY e.dateStart DESC, s.id DESC LIMIT 1)
            LEFT JOIN tblCompanyFoundationSnapshot AS valuationFoundation ON valuationFoundation.idCompany = c.id',
        'recordedValue' => $recordedValue,
        'companyValue' => "COALESCE($recordedValue, c.companyValue)",
        'issuedShareCount' => 'CASE WHEN valuationSnapshot.id IS NOT NULL
            THEN COALESCE(valuationSnapshot.issuedShareCount, c.issuedShareCount)
            ELSE COALESCE(valuationFoundation.initialShareCount, c.issuedShareCount) END',
        // An unknown price in the latest snapshot must stay unknown.
        'marketUnitPrice' => 'CASE WHEN valuationSnapshot.id IS NOT NULL
            THEN valuationSnapshot.shareMarketPrice ELSE valuationFoundation.shareMarketPrice END',
    ];
}

/** Buying existing issued shares cannot change a recorded company valuation. */
function aetherShareValueAfterPurchase(PDO $pdo, array $company, string $amountDelta): string
{
    $valuation = aetherShareValuationSql();
    $stmt = $pdo->prepare("SELECT {$valuation['recordedValue']} FROM tblCompany AS c
        {$valuation['joins']} WHERE c.id = :idCompany");
    $stmt->execute(['idCompany' => (int) $company['id']]);
    $recorded = $stmt->fetchColumn();
    return $recorded === null || $recorded === false
        ? aetherDecimalAdd((string) $company['companyValue'], $amountDelta)
        : aetherNormalizeDecimal((string) $recorded);
}

/** A mutation changes one company; character locks serialize cross-company portfolio updates. */
function aetherSharesLockCompany(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare('SELECT id, companyValue, initialCompanyValue, initialShareCount,
                                 issuedShareCount, isShareListed, foundationDate
                            FROM tblCompany WHERE id = :idCompany FOR UPDATE');
    $stmt->execute(['idCompany' => $companyId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int) $row['id']] = $row;
    return $rows;
}

function aetherShareHolderIds(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare('SELECT idCharacter FROM tblCompanyShareHolding WHERE idCompany = :idCompany ORDER BY idCharacter');
    $stmt->execute(['idCompany' => $companyId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Called only after the company row has been locked. */
function aetherShareStock(PDO $pdo, int $companyId, int $issued): array
{
    $allHoldings = aetherShareOwnershipRowsSql($pdo);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(shareCount), 0) FROM $allHoldings AS ownership WHERE idCompany = :idCompany");
    $stmt->execute(['idCompany' => $companyId]);
    $held = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT shareCount FROM tblCompanyAnonymousShares WHERE idCompany = :idCompany');
    $stmt->execute(['idCompany' => $companyId]);
    $anonymous = (int) ($stmt->fetchColumn() ?: 0);
    return ['issued' => $issued, 'held' => $held, 'anonymous' => $anonymous,
            'free' => aetherShareFreeCount($issued, $held, $anonymous)];
}

function aetherLatestCompanySnapshotId(PDO $pdo, int $companyId): ?int
{
    $stmt = $pdo->prepare('SELECT s.id FROM tblCompanySnapshot AS s
        JOIN tblEvent AS e ON e.id = s.idEvent
        WHERE s.idCompany = :idCompany ORDER BY e.dateStart DESC, s.id DESC LIMIT 1');
    $stmt->execute(['idCompany' => $companyId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function aetherShareLockOffer(PDO $pdo, int $offerId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tblShareOffer WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $offerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aetherShareOfferRecipient(PDO $pdo, int $offerId, int $characterId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM tblShareOfferRecipient WHERE idOffer = :idOffer AND idCharacter = :idCharacter');
    $stmt->execute(['idOffer' => $offerId, 'idCharacter' => $characterId]);
    return $stmt->fetchColumn() !== false;
}

function aetherShareAddHolding(PDO $pdo, int $companyId, int $characterId, int $quantity): void
{
    $stmt = $pdo->prepare('INSERT INTO tblCompanyShareHolding (idCompany, idCharacter, shareCount)
                           VALUES (:idCompany, :idCharacter, :shareCount)
                           ON DUPLICATE KEY UPDATE shareCount = shareCount + VALUES(shareCount)');
    $stmt->execute(['idCompany' => $companyId, 'idCharacter' => $characterId, 'shareCount' => $quantity]);
}

function aetherShareLockHolding(PDO $pdo, int $companyId, int $characterId): ?array
{
    $stmt = $pdo->prepare('SELECT id, shareCount FROM tblCompanyShareHolding
                           WHERE idCompany = :idCompany AND idCharacter = :idCharacter FOR UPDATE');
    $stmt->execute(['idCompany' => $companyId, 'idCharacter' => $characterId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aetherShareRemoveHolding(PDO $pdo, int $companyId, int $characterId,
                                  int $currentCount, int $quantity): void
{
    if ($quantity <= 0 || $quantity > $currentCount) {
        throw new DomainException('Onvoldoende aandelen bij de verkoper.');
    }
    if ($quantity === $currentCount) {
        $stmt = $pdo->prepare('DELETE FROM tblCompanyShareHolding
                               WHERE idCompany = :idCompany AND idCharacter = :idCharacter');
        $stmt->execute(['idCompany' => $companyId, 'idCharacter' => $characterId]);
    } else {
        $stmt = $pdo->prepare('UPDATE tblCompanyShareHolding SET shareCount = shareCount - :quantity
                               WHERE idCompany = :idCompany AND idCharacter = :idCharacter');
        $stmt->execute(['quantity' => $quantity, 'idCompany' => $companyId, 'idCharacter' => $characterId]);
    }
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Aandelenbezit is tijdens de overdracht gewijzigd.');
}

function aetherSharePortfolioValue(PDO $pdo, int $characterId): string
{
    $valuation = aetherShareValuationSql();
    $stmt = $pdo->prepare("SELECT COALESCE(ROUND(SUM(h.shareCount * {$valuation['companyValue']}
                                 / NULLIF({$valuation['issuedShareCount']}, 0)), 2), 0.00)
                            FROM tblCompanyShareHolding h JOIN tblCompany c ON c.id = h.idCompany
                            {$valuation['joins']} WHERE h.idCharacter = :idCharacter");
    $stmt->execute(['idCharacter' => $characterId]);
    return aetherNormalizeDecimal((string) $stmt->fetchColumn());
}

function aetherShareSaveStatus(PDO $pdo, int $characterId, string $value, array $status): void
{
    $stmt = $pdo->prepare('INSERT INTO tblCharacterShareStatus
                             (idCharacter, shareLevel, usedStatusPoints, personnelRequirement, intrinsicPortfolioValue, valuationVersion)
                           VALUES (:idCharacter, :shareLevel, :usedStatusPoints, :personnelRequirement, :value, 1)
                           ON DUPLICATE KEY UPDATE shareLevel = VALUES(shareLevel),
                             usedStatusPoints = VALUES(usedStatusPoints),
                             personnelRequirement = VALUES(personnelRequirement),
                             intrinsicPortfolioValue = VALUES(intrinsicPortfolioValue),
                             valuationVersion = valuationVersion + 1');
    $stmt->execute(['idCharacter' => $characterId, 'shareLevel' => $status['level'],
                    'usedStatusPoints' => $status['usedStatusPoints'],
                    'personnelRequirement' => $status['personnelRequirement'], 'value' => $value]);
}

function aetherShareInsertTransaction(PDO $pdo, array $row): int
{
    $stmt = $pdo->prepare('INSERT INTO tblCompanyShareTransaction
        (idCompany, idCharacter, idCounterpartyCharacter, idOffer, idEvent, actorUserId,
         operationCode, shareCount, unitPrice, totalAmount, companyValueBefore,
         companyValueAfter, requestKey, detailsJson)
        VALUES (:idCompany, :idCharacter, :idCounterpartyCharacter, :idOffer, :idEvent,
                :actorUserId, :operationCode, :shareCount, :unitPrice, :totalAmount,
                :companyValueBefore, :companyValueAfter, :requestKey, :detailsJson)');
    $stmt->execute($row);
    return (int) $pdo->lastInsertId();
}

function aetherShareInsertValueHistory(PDO $pdo, int $companyId, ?int $shareTransactionId,
                                       int $actorUserId, string $reason, string $before, string $after,
                                       ?string $detailsJson = null): void
{
    $stmt = $pdo->prepare('INSERT INTO tblCompanyValueHistory
        (idCompany, idShareTransaction, actorUserId, reasonCode, valueBefore, valueDelta, valueAfter, detailsJson)
        VALUES (:idCompany, :idShareTransaction, :actorUserId, :reasonCode, :before, :delta, :after, :detailsJson)');
    $stmt->execute(['idCompany' => $companyId, 'idShareTransaction' => $shareTransactionId,
                    'actorUserId' => $actorUserId, 'reasonCode' => $reason, 'before' => $before,
                    'delta' => aetherDecimalSubtract($after, $before), 'after' => $after,
                    'detailsJson' => $detailsJson]);
}
