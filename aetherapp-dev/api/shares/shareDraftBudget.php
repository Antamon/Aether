<?php
declare(strict_types=1);
require_once __DIR__ . '/shareRules.php';
require_once __DIR__ . '/shareException.php';

function aetherIsShareCapitalTrait(array $trait): bool
{
    return !empty($trait['traitFlags']['share_capital']) || in_array('share_capital', $trait['traitFlagKeys'] ?? [], true);
}

function aetherShareDraftCapital(int $level): string
{
    if ($level < 0 || $level > 34) throw new AetherShareException(422, 'Ongeldig aandelenniveau.');
    $amounts = [0, 60000000, 120000000, 250000000, 500000000, 1000000000, 2000000000];
    $minor = $amounts[min($level, 6)];
    for ($i = 6; $i < $level; $i++) {
        if ($minor > intdiv(PHP_INT_MAX, 2)) throw new AetherShareException(422, 'Aandelenkapitaal is te groot.');
        $minor *= 2;
    }
    return aetherMinorUnitsToDecimal($minor);
}

function aetherShareDraftBudget(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT purchasedLevel, budgetAmount, spentAmount, closedAt FROM tblCharacterShareDraftBudget WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['remainingAmount'] = $row['closedAt'] === null ? aetherDecimalSubtract((string) $row['budgetAmount'], (string) $row['spentAmount']) : '0.00';
    return $row;
}

function aetherShareCloseDraftBudget(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE tblCharacterShareDraftBudget SET closedAt = COALESCE(closedAt, NOW()) WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $id]);
}

/** Caller holds the character lock, also used by purchases and state changes. */
function aetherShareSetDraftLevel(PDO $pdo, int $id, int $level): void
{
    $budget = aetherShareDraftBudget($pdo, $id);
    if ($budget && $budget['closedAt'] !== null) throw new AetherShareException(409, 'Dit startkapitaal is al afgesloten.');
    $amount = aetherShareDraftCapital($level);
    if ($budget && aetherDecimalCompare((string) $budget['spentAmount'], $amount) > 0) {
        throw new AetherShareException(409, 'Dit niveau is te laag voor het al bestede aandelenkapitaal.');
    }
    if ($level === 0) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterShareDraftBudget WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $id]); return;
    }
    $stmt = $pdo->prepare('INSERT INTO tblCharacterShareDraftBudget (idCharacter, purchasedLevel, budgetAmount)
        VALUES (:idCharacter, :level, :amount) ON DUPLICATE KEY UPDATE purchasedLevel = VALUES(purchasedLevel), budgetAmount = VALUES(budgetAmount)');
    $stmt->execute(['idCharacter' => $id, 'level' => $level, 'amount' => $amount]);
}

/** Snapshot zero means the foundation, otherwise an event snapshot. */
function aetherShareDraftQuotes(PDO $pdo, ?int $companyId = null): array
{
    $where = $companyId === null ? '' : ' AND c.id = :idCompany';
    $stmt = $pdo->prepare("SELECT c.id AS idCompany, c.companyName, s.id AS idSnapshot, s.idEvent,
        e.title AS eventTitle, e.dateStart AS quoteDate, s.shareMarketPrice AS unitPrice, 0 AS foundationOrder,
        GREATEST(0, c.issuedShareCount - COALESCE((SELECT SUM(h.shareCount) FROM tblCompanyShareHolding h WHERE h.idCompany = c.id), 0)
            - COALESCE((SELECT a.shareCount FROM tblCompanyAnonymousShares a WHERE a.idCompany = c.id), 0)) AS freeShareCount
        FROM tblCompany c JOIN tblCompanySnapshot s ON s.idCompany = c.id JOIN tblEvent e ON e.id = s.idEvent
        WHERE c.initialShareCount IS NOT NULL AND c.issuedShareCount > 0 $where
        UNION ALL SELECT c.id, c.companyName, 0, NULL, 'Oprichting', f.foundationDate, f.shareMarketPrice, 1,
        GREATEST(0, c.issuedShareCount - COALESCE((SELECT SUM(h.shareCount) FROM tblCompanyShareHolding h WHERE h.idCompany = c.id), 0)
            - COALESCE((SELECT a.shareCount FROM tblCompanyAnonymousShares a WHERE a.idCompany = c.id), 0))
        FROM tblCompany c JOIN tblCompanyFoundationSnapshot f ON f.idCompany = c.id
        WHERE c.initialShareCount IS NOT NULL AND c.issuedShareCount > 0" . ($companyId === null ? '' : ' AND c.id = :foundationCompany') . '
        ORDER BY idCompany, foundationOrder, quoteDate DESC, idSnapshot DESC');
    $stmt->execute($companyId === null ? [] : ['idCompany' => $companyId, 'foundationCompany' => $companyId]);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC); $seen = [];
    foreach ($quotes as &$quote) {
        $id = (int) $quote['idCompany']; $quote['isCurrent'] = !isset($seen[$id]); $seen[$id] = true;
    }
    unset($quote);
    return $quotes;
}
