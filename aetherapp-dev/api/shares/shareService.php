<?php
declare(strict_types=1);

require_once __DIR__ . '/shareAccess.php';
require_once __DIR__ . '/shareRules.php';
require_once __DIR__ . '/shareMarketEvent.php';
require_once __DIR__ . '/shareRepository.php';
require_once __DIR__ . '/../characters/characterFinanceRepository.php';
require_once __DIR__ . '/../companies/companyLeadershipRules.php';
require_once __DIR__ . '/../companies/companyUtils.php';
require_once __DIR__ . '/../shared/idempotency.php';

function aetherShareRequireManager(array $user): void
{
    if (!aetherCanManageShareCompany($user)) {
        throw new AetherShareException(403, 'Geen rechten voor aandelenbeheer.');
    }
}

function aetherShareRequireCharacter(array $characters, int $id, array $user, bool $active = true): array
{
    $character = $characters[$id] ?? null;
    if ($character === null) throw new AetherShareException(404, 'Personage niet gevonden.');
    if (!aetherCanTradeForCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    if ($active && (string) $character['state'] !== 'active') {
        throw new AetherShareException(409, 'Alleen een actief personage kan aandelen verhandelen.');
    }
    return $character;
}

function aetherShareRequireCompany(array $companies, int $id): array
{
    $company = $companies[$id] ?? null;
    if ($company === null) throw new AetherShareException(404, 'Bedrijf niet gevonden.');
    if ((int) $company['issuedShareCount'] <= 0 || $company['initialShareCount'] === null) {
        throw new AetherShareException(409, 'De aandelen van dit bedrijf zijn niet geïnitialiseerd.');
    }
    return $company;
}

function aetherShareRevalue(PDO $pdo, array $characterIds): void
{
    $ids = array_values(array_unique(array_map('intval', $characterIds)));
    sort($ids, SORT_NUMERIC);
    foreach ($ids as $id) {
        $value = aetherSharePortfolioValue($pdo, $id);
        aetherShareSaveStatus($pdo, $id, $value, aetherShareStatusForPortfolio($value));
    }
}

/** All mutations use the same transaction and lock order. */
function aetherShareMutation(PDO $pdo, array $user, string $operation, string $key, array $input,
                             callable $mutation, callable $authorizeReplay): array
{
    aetherRequireShareMigration($pdo);
    if ($pdo->inTransaction()) throw new LogicException('Aandelenactie vereist een eigen transactie.');
    // A read after waiting for a character lock must see the other transaction's
    // committed portfolio, not an earlier repeatable-read view.
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    try {
        return aetherRunIdempotentMutation(
            $pdo, $user, 'shares.' . $operation, $key, $input,
            static function () use ($pdo, $input, $mutation): array {
                $companies = aetherSharesLockCompany($pdo, aetherShareMutationCompanyId($pdo, $input));
                return $mutation($companies);
            },
            static function () use ($pdo, $input, $authorizeReplay): void {
                $companies = aetherSharesLockCompany($pdo, aetherShareMutationCompanyId($pdo, $input));
                $authorizeReplay($companies);
            }
        );
    } catch (PDOException $e) {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($driverCode, [1205, 1213], true)) {
            throw new AetherShareException(409, 'De voorraad veranderde gelijktijdig. Probeer opnieuw.');
        }
        throw $e;
    }
}

function aetherShareMutationCompanyId(PDO $pdo, array $input): int
{
    if (isset($input['idCompany'])) return (int) $input['idCompany'];
    $stmt = $pdo->prepare('SELECT idCompany FROM tblShareOffer WHERE id = :id');
    $stmt->execute(['id' => (int) ($input['idOffer'] ?? 0)]);
    $id = $stmt->fetchColumn();
    if ($id === false) throw new AetherShareException(404, 'Aanbod niet gevonden.');
    return (int) $id;
}

function aetherShareViewCompany(PDO $pdo, array $user, int $companyId): array
{
    aetherRequireShareMigration($pdo);
    $stmt = $pdo->prepare('SELECT id, companyName, companyValue, initialCompanyValue,
                                 initialShareCount, issuedShareCount, isShareListed
                            FROM tblCompany WHERE id = :id');
    $stmt->execute(['id' => $companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) throw new AetherShareException(404, 'Bedrijf niet gevonden.');
    aetherShareRequireCompany([$companyId => $company], $companyId);
    if (!aetherCanManageShareCompany($user) && (int) $company['isShareListed'] !== 1) {
        $stmt = $pdo->prepare('SELECT 1 FROM tblCharacter c
                              WHERE c.idUser = :idUser
                                AND (EXISTS (SELECT 1 FROM tblCompanyPersonnel cp
                                              WHERE cp.idCompany = :idCompanyBoard
                                                AND cp.idCharacter = c.id AND cp.isBoardMember = 1)
                                  OR EXISTS (SELECT 1 FROM tblCompanyShareHolding h
                                              WHERE h.idCompany = :idCompanyHolding AND h.idCharacter = c.id)
                                  OR EXISTS (SELECT 1 FROM tblShareOfferRecipient r
                                              JOIN tblShareOffer o ON o.id = r.idOffer
                                              WHERE o.idCompany = :idCompanyOffer AND o.statusCode = \'open\'
                                                AND r.idCharacter = c.id)) LIMIT 1');
        $stmt->execute(['idUser' => (int) $user['id'], 'idCompanyBoard' => $companyId,
                        'idCompanyHolding' => $companyId, 'idCompanyOffer' => $companyId]);
        if ($stmt->fetchColumn() === false) {
            throw new AetherShareException(403, 'Geen toegang tot dit bedrijf.');
        }
    }
    $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
    $stmt = $pdo->prepare('SELECT s.id, s.idEvent, s.shareMarketPrice,
                                 evt.title AS eventTitle, evt.dateStart AS eventDate
                           FROM tblCompanySnapshot AS s
                           JOIN tblEvent AS evt ON evt.id = s.idEvent
                           WHERE s.idCompany = :idCompany ORDER BY evt.dateStart DESC, s.id DESC');
    $stmt->execute(['idCompany' => $companyId]);
    $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = ['company' => $company, 'stock' => $stock, 'snapshots' => $snapshots,
               'canManageShares' => aetherCanManageShareCompany($user)];
    $stmt = $pdo->prepare('SELECT cp.idCharacter FROM tblCompanyPersonnel cp
                           JOIN tblCharacter c ON c.id = cp.idCharacter
                          WHERE cp.idCompany = :idCompany AND cp.isBoardMember = 1
                            AND c.idUser = :idUser ORDER BY cp.idCharacter');
    $stmt->execute(['idCompany' => $companyId, 'idUser' => (int) $user['id']]);
    $result['myBoardCharacterIds'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (aetherCanManageShareCompany($user)) {
        $stmt = $pdo->prepare("SELECT h.idCharacter, h.shareCount,
                                     TRIM(CONCAT_WS(' ', NULLIF(c.title, ''), c.firstName, c.lastName)) AS displayName
                                FROM tblCompanyShareHolding h
                                JOIN tblCharacter c ON c.id = h.idCharacter
                               WHERE h.idCompany = :idCompany ORDER BY h.idCharacter");
        $stmt->execute(['idCompany' => $companyId]);
        $result['holdings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT idCharacter FROM tblCompanyPersonnel
                               WHERE idCompany = :idCompany AND isBoardMember = 1 ORDER BY idCharacter');
        $stmt->execute(['idCompany' => $companyId]);
        $result['boardCharacterIds'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $stmt = $pdo->prepare("SELECT id, idCompany, offeredShareCount, purchasedShareCount, unitPrice
                                 FROM tblShareOffer
                                WHERE idCompany = :idCompany AND sourceCode = 'private_board'
                                  AND statusCode = 'open' ORDER BY id DESC");
        $stmt->execute(['idCompany' => $companyId]);
        $result['managerPrivateOffers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $result;
}

function aetherShareViewOffers(PDO $pdo, array $user, int $characterId, int $selectedEventId = 0): array
{
    aetherRequireShareMigration($pdo);
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanViewCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    $currentEvent = aetherShareSelectedMarketEvent($pdo, $selectedEventId);
    if ($selectedEventId > 0 && !$currentEvent) {
        throw new AetherShareException(404, 'Event niet gevonden.');
    }
    $stmt = $pdo->prepare("SELECT o.id, o.idCompany, c.companyName, o.idEvent, o.idSnapshot,
                                 o.sourceCode, r.recipientKind,
                                 o.statusCode, o.offeredShareCount, o.purchasedShareCount,
                                 o.unitPrice, o.expiresAt,
                                 GREATEST(0, LEAST(o.offeredShareCount - o.purchasedShareCount,
                                   CASE WHEN o.sourceCode = 'holder_sale'
                                     THEN COALESCE(sellerHolding.shareCount, 0)
                                     ELSE c.issuedShareCount
                                       - COALESCE((SELECT SUM(h.shareCount) FROM tblCompanyShareHolding h
                                                    WHERE h.idCompany = c.id), 0)
                                       - COALESCE(a.shareCount, 0)
                                   END)) AS availableShareCount
                            FROM tblShareOfferRecipient r
                            JOIN tblShareOffer o ON o.id = r.idOffer
                            JOIN tblCompany c ON c.id = o.idCompany
                            LEFT JOIN tblCompanySnapshot snapshotQuote ON snapshotQuote.id = o.idSnapshot
                            LEFT JOIN tblCompanyShareHolding sellerHolding
                              ON sellerHolding.idCompany = o.idCompany
                             AND sellerHolding.idCharacter = o.idOfferingCharacter
                            LEFT JOIN tblCompanyAnonymousShares a ON a.idCompany = c.id
                           WHERE r.idCharacter = :idCharacter AND o.statusCode = 'open'
                             AND (o.expiresAt IS NULL OR o.expiresAt > NOW())
                             AND (o.sourceCode <> 'search' OR
                                  (o.idEvent = :idEvent AND c.isShareListed = 1
                                   AND snapshotQuote.idCompany = c.id
                                   AND snapshotQuote.idEvent = o.idEvent
                                   AND snapshotQuote.shareMarketPrice > 0))
                           ORDER BY o.id DESC");
    $stmt->execute(['idCharacter' => $characterId,
                    'idEvent' => (int) ($currentEvent['id'] ?? 0)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aetherShareViewCreatedOffers(PDO $pdo, array $user, int $characterId): array
{
    aetherRequireShareMigration($pdo);
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanTradeForCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    $stmt = $pdo->prepare("SELECT o.id, o.idCompany, c.companyName, o.sourceCode,
                                 o.offeredShareCount, o.purchasedShareCount, o.unitPrice
                            FROM tblShareOffer o
                            JOIN tblCompany c ON c.id = o.idCompany
                           WHERE o.idOfferingCharacter = :idCharacter
                             AND o.sourceCode IN ('holder_sale', 'private_board')
                             AND o.statusCode = 'open'
                             AND (o.expiresAt IS NULL OR o.expiresAt > NOW())
                           ORDER BY o.id DESC");
    $stmt->execute(['idCharacter' => $characterId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aetherShareViewPortfolio(PDO $pdo, array $user, int $characterId): array
{
    aetherRequireShareMigration($pdo);
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanViewCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    $stmt = $pdo->prepare('SELECT h.idCompany, c.companyName, h.shareCount,
                                 c.issuedShareCount, c.companyValue,
                                 ROUND(h.shareCount * c.companyValue / c.issuedShareCount, 2) AS intrinsicValue
                            FROM tblCompanyShareHolding h
                            JOIN tblCompany c ON c.id = h.idCompany
                           WHERE h.idCharacter = :idCharacter ORDER BY c.companyName, c.id');
    $stmt->execute(['idCharacter' => $characterId]);
    $holdings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT shareLevel, usedStatusPoints, personnelRequirement,
                                 intrinsicPortfolioValue, valuationVersion
                            FROM tblCharacterShareStatus WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $characterId]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'shareLevel' => 0, 'usedStatusPoints' => 0, 'personnelRequirement' => 0,
        'intrinsicPortfolioValue' => '0.00', 'valuationVersion' => 0,
    ];
    return ['holdings' => $holdings, 'status' => $status];
}

function aetherShareCreatePrivateOffer(PDO $pdo, array $user, array $input, string $key): array
{
    return aetherShareMutation($pdo, $user, 'offer.private.create', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            $companyId = (int) $input['idCompany'];
            $offeringId = (int) $input['idOfferingCharacter'];
            $recipientId = (int) $input['idRecipientCharacter'];
            $quantity = (int) $input['shareCount'];
            $company = aetherShareRequireCompany($companies, $companyId);
            if ($offeringId > 0 && $offeringId === $recipientId) throw new AetherShareException(400, 'Kies een ander ontvangend personage.');
            $characters = aetherFinanceLockCharacters($pdo, $offeringId > 0
                ? [$offeringId, $recipientId] : [$recipientId]);
            if ($offeringId > 0) aetherShareRequireCharacter($characters, $offeringId, $user);
            if (!isset($characters[$recipientId]) || (string) $characters[$recipientId]['state'] !== 'active') {
                throw new AetherShareException(404, 'Ontvanger niet gevonden of niet actief.');
            }
            if ($offeringId === 0 ? !aetherCanManageShareCompany($user)
                : !aetherIsCompanyBoardMember($pdo, $user, $companyId, $offeringId)) {
                throw new AetherShareException(403, 'Geen recht om namens dit bedrijf aandelen aan te bieden.');
            }
            $snapshotId = (int) ($input['idSnapshot'] ?? 0);
            $eventId = null;
            if ($snapshotId > 0) {
                $stmt = $pdo->prepare('SELECT idEvent FROM tblCompanySnapshot
                    WHERE id = :idSnapshot AND idCompany = :idCompany FOR UPDATE');
                $stmt->execute(['idSnapshot' => $snapshotId, 'idCompany' => $companyId]);
                $eventId = $stmt->fetchColumn();
                if ($eventId === false) throw new AetherShareException(404, 'Snapshot niet gevonden voor dit bedrijf.');
            }
            $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
            if ($quantity > $stock['free']) throw new AetherShareException(409, 'Onvoldoende vrije aandelen.');
            $stmt = $pdo->prepare("INSERT INTO tblShareOffer
                (idCompany, idOfferingCharacter, idSnapshot, idEvent, sourceCode, offeredShareCount, unitPrice)
                VALUES (:idCompany, :idOfferingCharacter, :idSnapshot, :idEvent, 'private_board', :shareCount, :unitPrice)");
            $stmt->execute(['idCompany' => $companyId, 'idOfferingCharacter' => $offeringId ?: null,
                            'idSnapshot' => $snapshotId ?: null, 'idEvent' => $eventId,
                            'shareCount' => $quantity, 'unitPrice' => $input['unitPrice']]);
            $offerId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO tblShareOfferRecipient (idOffer, idCharacter, recipientKind)
                                   VALUES (:idOffer, :idCharacter, 'private')");
            $stmt->execute(['idOffer' => $offerId, 'idCharacter' => $recipientId]);
            $value = aetherNormalizeDecimal((string) $company['companyValue']);
            aetherShareInsertValueHistory($pdo, $companyId, null, (int) $user['id'],
                'share_offer_created', $value, $value,
                json_encode(['idOffer' => $offerId, 'idSnapshot' => $snapshotId ?: null,
                             'recipientCharacterId' => $recipientId,
                             'shareCount' => $quantity, 'unitPrice' => $input['unitPrice'],
                             'requestKey' => $key], JSON_THROW_ON_ERROR));
            return ['idOffer' => $offerId, 'statusCode' => 'open'];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            aetherShareRequireCompany($companies, (int) $input['idCompany']);
            if ((int) $input['idOfferingCharacter'] === 0 ? !aetherCanManageShareCompany($user)
                : !aetherIsCompanyBoardMember($pdo, $user, (int) $input['idCompany'], (int) $input['idOfferingCharacter'])) {
                throw new AetherShareException(403, 'Bestuursrecht vervallen.');
            }
        });
}

function aetherShareCreateHolderOffer(PDO $pdo, array $user, array $input, string $key): array
{
    return aetherShareMutation($pdo, $user, 'offer.holder.create', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            $companyId = (int) $input['idCompany'];
            $sellerId = (int) $input['idSellingCharacter'];
            $buyerId = (int) $input['idRecipientCharacter'];
            $quantity = (int) $input['shareCount'];
            $company = aetherShareRequireCompany($companies, $companyId);
            if ($sellerId === $buyerId) throw new AetherShareException(400, 'Kies een ander ontvangend personage.');
            $characters = aetherFinanceLockCharacters($pdo, [$sellerId, $buyerId]);
            aetherShareRequireCharacter($characters, $sellerId, $user);
            if (!isset($characters[$buyerId]) || (string) $characters[$buyerId]['state'] !== 'active') {
                throw new AetherShareException(404, 'Ontvanger niet gevonden of niet actief.');
            }
            $holding = aetherShareLockHolding($pdo, $companyId, $sellerId);
            if (!$holding || (int) $holding['shareCount'] < $quantity) {
                throw new AetherShareException(409, 'De verkoper bezit niet genoeg aandelen.');
            }
            $stmt = $pdo->prepare("INSERT INTO tblShareOffer
                (idCompany, idOfferingCharacter, sourceCode, offeredShareCount, unitPrice)
                VALUES (:idCompany, :idOfferingCharacter, 'holder_sale', :shareCount, :unitPrice)");
            $stmt->execute(['idCompany' => $companyId, 'idOfferingCharacter' => $sellerId,
                            'shareCount' => $quantity, 'unitPrice' => $input['unitPrice']]);
            $offerId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO tblShareOfferRecipient (idOffer, idCharacter, recipientKind)
                                   VALUES (:idOffer, :idCharacter, 'private')");
            $stmt->execute(['idOffer' => $offerId, 'idCharacter' => $buyerId]);
            $value = aetherNormalizeDecimal((string) $company['companyValue']);
            aetherShareInsertValueHistory($pdo, $companyId, null, (int) $user['id'],
                'holder_offer_created', $value, $value,
                json_encode(['idOffer' => $offerId, 'sellerCharacterId' => $sellerId,
                             'recipientCharacterId' => $buyerId, 'shareCount' => $quantity,
                             'unitPrice' => $input['unitPrice'], 'requestKey' => $key], JSON_THROW_ON_ERROR));
            return ['idOffer' => $offerId, 'statusCode' => 'open'];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            aetherShareRequireCompany($companies, (int) $input['idCompany']);
            $seller = aetherFetchCharacterAccessRecord($pdo, (int) $input['idSellingCharacter']);
            if (!$seller || !aetherCanTradeForCharacter($user, $seller)) {
                throw new AetherShareException(403, 'Geen rechten voor de verkoper.');
            }
        });
}

function aetherShareCancelOffer(PDO $pdo, array $user, array $input, string $key): array
{
    return aetherShareMutation($pdo, $user, 'offer.cancel', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            $offer = aetherShareLockOffer($pdo, (int) $input['idOffer']);
            if (!$offer) throw new AetherShareException(404, 'Aanbod niet gevonden.');
            aetherShareRequireCompany($companies, (int) $offer['idCompany']);
            if (!aetherShareCanCancelOffer($pdo, $user, $offer)) {
                throw new AetherShareException(403, 'Geen rechten voor dit aanbod.');
            }
            if ($offer['statusCode'] !== 'open') throw new AetherShareException(409, 'Aanbod is niet meer open.');
            $stmt = $pdo->prepare("UPDATE tblShareOffer SET statusCode = 'cancelled' WHERE id = :id");
            $stmt->execute(['id' => $offer['id']]);
            $company = $companies[(int) $offer['idCompany']];
            $value = aetherNormalizeDecimal((string) $company['companyValue']);
            aetherShareInsertValueHistory($pdo, (int) $offer['idCompany'], null, (int) $user['id'],
                'share_offer_cancelled', $value, $value,
                json_encode(['idOffer' => (int) $offer['id'], 'requestKey' => $key], JSON_THROW_ON_ERROR));
            return ['idOffer' => (int) $offer['id'], 'statusCode' => 'cancelled'];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            $offer = aetherShareLockOffer($pdo, (int) $input['idOffer']);
            if (!$offer) throw new AetherShareException(404, 'Aanbod niet gevonden.');
            if (!aetherShareCanCancelOffer($pdo, $user, $offer)) {
                throw new AetherShareException(403, 'Geen rechten voor dit aanbod.');
            }
        });
}

function aetherShareCanCancelOffer(PDO $pdo, array $user, array $offer): bool
{
    if (aetherCanManageShareCompany($user)) return true;
    $offeringId = (int) ($offer['idOfferingCharacter'] ?? 0);
    if ($offeringId <= 0) return false;
    if ($offer['sourceCode'] === 'private_board') {
        return aetherIsCompanyBoardMember($pdo, $user, (int) $offer['idCompany'], $offeringId);
    }
    if ($offer['sourceCode'] === 'holder_sale') {
        $seller = aetherFetchCharacterAccessRecord($pdo, $offeringId);
        return $seller !== null && aetherCanTradeForCharacter($user, $seller);
    }
    return false;
}

function aetherShareBuyOffer(PDO $pdo, array $user, array $input, string $key): array
{
    return aetherShareMutation($pdo, $user, 'offer.buy', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            $offerId = (int) $input['idOffer'];
            $buyerId = (int) $input['idCharacter'];
            $quantity = (int) $input['shareCount'];
            // Resolve the offer's company before locking characters or the offer row.
            $stmt = $pdo->prepare('SELECT idCompany, sourceCode, idOfferingCharacter FROM tblShareOffer WHERE id = :id');
            $stmt->execute(['id' => $offerId]);
            $offerPreview = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$offerPreview) throw new AetherShareException(404, 'Aanbod niet gevonden.');
            $companyId = (int) $offerPreview['idCompany'];
            $company = aetherShareRequireCompany($companies, $companyId);
            $sellerId = (int) ($offerPreview['idOfferingCharacter'] ?? 0);
            $affected = $offerPreview['sourceCode'] === 'holder_sale'
                ? [$buyerId, $sellerId]
                : [...aetherShareHolderIds($pdo, $companyId), $buyerId];
            $affected = array_values(array_unique($affected));
            $characters = aetherFinanceLockCharacters($pdo, $affected);
            $buyer = aetherShareRequireCharacter($characters, $buyerId, $user);
            $offer = aetherShareLockOffer($pdo, $offerId);
            if (!$offer || (int) $offer['idCompany'] !== $companyId || $offer['statusCode'] !== 'open'
                || $offer['sourceCode'] !== $offerPreview['sourceCode']
                || (int) ($offer['idOfferingCharacter'] ?? 0) !== $sellerId) {
                throw new AetherShareException(409, 'Aanbod is niet meer beschikbaar.');
            }
            if (!aetherShareOfferRecipient($pdo, $offerId, $buyerId)) {
                throw new AetherShareException(403, 'Dit aanbod is niet voor dit personage.');
            }
            if ($offer['expiresAt'] !== null && $offer['expiresAt'] <= date('Y-m-d H:i:s')) {
                throw new AetherShareException(409, 'Aanbod is verlopen.');
            }
            if (!in_array($offer['sourceCode'], ['private_board', 'holder_sale', 'search'], true)) {
                throw new AetherShareException(409, 'Dit aanbodtype is niet koopbaar.');
            }
            if ($offer['sourceCode'] === 'search') {
                if ((int) ($input['idEvent'] ?? 0) !== (int) $offer['idEvent']) {
                    throw new AetherShareException(409, 'Dit aanbod hoort bij een ander event.');
                }
                aetherShareRequireMarketEvent($pdo, (int) $offer['idEvent']);
                if ((int) $company['isShareListed'] !== 1) {
                    throw new AetherShareException(409, 'Het bedrijf is niet meer beursgenoteerd.');
                }
                $stmt = $pdo->prepare('SELECT 1 FROM tblCompanySnapshot
                    WHERE id = :idSnapshot AND idCompany = :idCompany
                      AND idEvent = :idEvent AND shareMarketPrice > 0');
                $stmt->execute(['idSnapshot' => (int) $offer['idSnapshot'],
                                'idCompany' => $companyId, 'idEvent' => (int) $offer['idEvent']]);
                if ($stmt->fetchColumn() === false) {
                    throw new AetherShareException(409, 'De marktmomentopname is niet meer geldig.');
                }
            }
            $remaining = (int) $offer['offeredShareCount'] - (int) $offer['purchasedShareCount'];
            if ($quantity > $remaining) {
                throw new AetherShareException(409, 'De beschikbare voorraad is gewijzigd.');
            }
            $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
            if ($offer['sourceCode'] !== 'holder_sale' && $quantity > $stock['free']) {
                throw new AetherShareException(409, 'De beschikbare voorraad is gewijzigd.');
            }
            $total = aetherSharePurchaseTotal((string) $offer['unitPrice'], $quantity);
            if (aetherDecimalCompare((string) ($buyer['bankaccount'] ?? '0.00'), $total) < 0) {
                throw new AetherShareException(409, 'Onvoldoende saldo op de bankrekening.');
            }
            $before = aetherNormalizeDecimal((string) $company['companyValue']);
            $after = $before;
            if ($offer['sourceCode'] !== 'holder_sale') {
                $after = aetherDecimalAdd($before, $total);
                if (aetherDecimalCompare($after, '9999999999.99') > 0) {
                    throw new AetherShareException(409, 'De bedrijfswaarde overschrijdt het toegestane bereik.');
                }
                aetherFinanceAdjustBankBalance($pdo, $buyerId, '-' . $total);
                aetherShareAddHolding($pdo, $companyId, $buyerId, $quantity);
                $stmt = $pdo->prepare('UPDATE tblCompany SET companyValue = :value WHERE id = :id');
                $stmt->execute(['value' => $after, 'id' => $companyId]);
            } else {
                $sellerId = (int) $offer['idOfferingCharacter'];
                if ($sellerId <= 0 || $sellerId === $buyerId
                    || !isset($characters[$sellerId]) || (string) $characters[$sellerId]['state'] !== 'active') {
                    throw new AetherShareException(409, 'Verkoper is niet meer beschikbaar.');
                }
                $holding = aetherShareLockHolding($pdo, $companyId, $sellerId);
                if (!$holding || (int) $holding['shareCount'] < $quantity) {
                    throw new AetherShareException(409, 'De verkoper bezit niet genoeg aandelen meer.');
                }
                $sellerBalance = aetherDecimalAdd((string) ($characters[$sellerId]['bankaccount'] ?? '0.00'), $total);
                if (aetherDecimalCompare($sellerBalance, '9999999999.99') > 0) {
                    throw new AetherShareException(409, 'De rekening van de verkoper kan dit bedrag niet ontvangen.');
                }
                aetherFinanceAdjustBankBalance($pdo, $buyerId, '-' . $total);
                aetherFinanceAdjustBankBalance($pdo, $sellerId, $total);
                aetherShareRemoveHolding($pdo, $companyId, $sellerId, (int) $holding['shareCount'], $quantity);
                aetherShareAddHolding($pdo, $companyId, $buyerId, $quantity);
            }
            $stmt = $pdo->prepare('UPDATE tblShareOffer SET purchasedShareCount = :purchased,
                                     statusCode = :statusCode WHERE id = :idOffer');
            $stmt->execute(['purchased' => (int) $offer['purchasedShareCount'] + $quantity,
                            'statusCode' => $quantity === $remaining ? 'filled' : 'open',
                            'idOffer' => $offerId]);
            $transactionId = aetherShareInsertTransaction($pdo, [
                'idCompany' => $companyId, 'idCharacter' => $buyerId,
                'idCounterpartyCharacter' => $offer['sourceCode'] === 'holder_sale' ? $sellerId : null,
                'idOffer' => $offerId, 'idEvent' => $offer['idEvent'], 'actorUserId' => (int) $user['id'],
                'operationCode' => $offer['sourceCode'] === 'holder_sale' ? 'buy_from_holder' : 'buy_free',
                'shareCount' => $quantity,
                'unitPrice' => $offer['unitPrice'], 'totalAmount' => $total,
                'companyValueBefore' => $before, 'companyValueAfter' => $after,
                'requestKey' => $key, 'detailsJson' => null,
            ]);
            if ($offer['sourceCode'] !== 'holder_sale') {
                aetherShareInsertValueHistory($pdo, $companyId, $transactionId, (int) $user['id'],
                                              'free_share_purchase', $before, $after);
            }
            aetherShareRevalue($pdo, $affected);
            return ['transactionId' => $transactionId, 'idOffer' => $offerId,
                    'shareCount' => $quantity, 'totalAmount' => $total,
                    'companyValue' => $after,
                    'freeShares' => $stock['free'] - ($offer['sourceCode'] !== 'holder_sale' ? $quantity : 0)];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            $character = aetherFetchCharacterAccessRecord($pdo, (int) $input['idCharacter']);
            if (!$character || !aetherCanTradeForCharacter($user, $character)
                || (string) $character['state'] !== 'active') {
                throw new AetherShareException(403, 'Geen rechten voor dit personage.');
            }
            if (!aetherShareOfferRecipient($pdo, (int) $input['idOffer'], (int) $input['idCharacter'])) {
                throw new AetherShareException(403, 'Dit aanbod is niet voor dit personage.');
            }
        });
}

function aetherShareManageCompany(PDO $pdo, array $user, string $action, array $input, string $key): array
{
    aetherShareRequireManager($user);
    return aetherShareMutation($pdo, $user, 'company.' . $action, $key, $input,
        static function (array $companies) use ($pdo, $user, $action, $input, $key): array {
            aetherShareRequireManager($user);
            $companyId = (int) $input['idCompany'];
            $company = aetherShareRequireCompany($companies, $companyId);
            $before = aetherNormalizeDecimal((string) $company['companyValue']);
            $affected = in_array($action, ['foundation', 'initial', 'value', 'buyback'], true)
                ? aetherShareHolderIds($pdo, $companyId) : [];
            $characters = aetherFinanceLockCharacters($pdo, $affected);
            if (count($characters) !== count($affected)) {
                throw new AetherShareException(409, 'Een aandeelhouder ontbreekt.');
            }
            $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
            $after = $before;
            $revalue = false;
            $details = [];
            switch ($action) {
                case 'foundation':
                    if (!aetherCompanySnapshotHistoryAvailable($pdo)) {
                        throw new AetherShareException(503, 'Voer eerst de snapshotgeschiedenismigratie uit.');
                    }
                    $stmt = $pdo->prepare('SELECT * FROM tblCompanyFoundationSnapshot
                                           WHERE idCompany = :idCompany FOR UPDATE');
                    $stmt->execute(['idCompany' => $companyId]);
                    $foundation = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($foundation === false) throw new AetherShareException(409, 'De oprichtingssnapshot ontbreekt.');
                    $initialValue = (string) $input['initialCompanyValue'];
                    $initialCount = (int) $input['initialShareCount'];
                    $issued = (int) $company['issuedShareCount']
                        + $initialCount - (int) $company['initialShareCount'];
                    $hasEventSnapshots = aetherLatestCompanySnapshotId($pdo, $companyId) !== null;
                    $foundingAnonymous = array_key_exists('anonymousShareCount', $input)
                        ? (int) $input['anonymousShareCount']
                        : ($foundation['anonymousShareCount'] === null ? null : (int) $foundation['anonymousShareCount']);
                    if ($foundingAnonymous !== null && $foundingAnonymous > $initialCount) {
                        throw new AetherShareException(409, 'Meer anonieme aandelen dan aandelen bij oprichting.');
                    }
                    $currentAnonymous = !$hasEventSnapshots && array_key_exists('anonymousShareCount', $input)
                        ? (int) $input['anonymousShareCount'] : $stock['anonymous'];
                    if ($issued <= 0 || $issued < $stock['held'] + $currentAnonymous) {
                        throw new AetherShareException(409, 'De correctie laat te weinig uitgegeven aandelen over.');
                    }
                    $companyFields = 'initialCompanyValue = :initialValue,
                        initialShareCount = :initialCount, issuedShareCount = :issued';
                    $companyParams = ['initialValue' => $initialValue, 'initialCount' => $initialCount,
                        'issued' => $issued, 'idCompany' => $companyId];
                    if (!$hasEventSnapshots) {
                        $companyFields .= ', companyValue = :companyValue';
                        $companyParams['companyValue'] = $initialValue;
                        $after = $initialValue;
                        foreach (['stability', 'profitability'] as $field) {
                            if (array_key_exists($field, $input)) {
                                $companyFields .= ', ' . $field . ' = :' . $field;
                                $companyParams[$field] = (int) $input[$field];
                            }
                        }
                    }
                    $stmt = $pdo->prepare('UPDATE tblCompany SET ' . $companyFields . ' WHERE id = :idCompany');
                    $stmt->execute($companyParams);
                    if (!$hasEventSnapshots && array_key_exists('anonymousShareCount', $input)) {
                        $stmt = $pdo->prepare('INSERT INTO tblCompanyAnonymousShares (idCompany, shareCount)
                            VALUES (:idCompany, :shareCount)
                            ON DUPLICATE KEY UPDATE shareCount = VALUES(shareCount)');
                        $stmt->execute(['shareCount' => $currentAnonymous, 'idCompany' => $companyId]);
                    }
                    $stmt = $pdo->prepare("UPDATE tblCompanyFoundationSnapshot SET
                        initialCompanyValue = :initialValue, initialShareCount = :initialCount,
                        shareMarketPrice = COALESCE(:marketPrice, shareMarketPrice),
                        anonymousShareCount = COALESCE(:anonymousCount, anonymousShareCount),
                        stability = COALESCE(:stability, stability),
                        profitability = COALESCE(:profitability, profitability),
                        sourceCode = 'corrected' WHERE idCompany = :idCompany");
                    $stmt->execute(['initialValue' => $initialValue, 'initialCount' => $initialCount,
                        'marketPrice' => $input['shareMarketPrice'] ?? null,
                        'anonymousCount' => $input['anonymousShareCount'] ?? null,
                        'stability' => $input['stability'] ?? null,
                        'profitability' => $input['profitability'] ?? null,
                        'idCompany' => $companyId]);
                    $details = ['oldInitialValue' => $company['initialCompanyValue'],
                        'newInitialValue' => $initialValue, 'oldInitialCount' => (int) $company['initialShareCount'],
                        'newInitialCount' => $initialCount, 'foundationAnonymous' => $foundingAnonymous,
                        'foundationMarketPrice' => $input['shareMarketPrice'] ?? $foundation['shareMarketPrice']];
                    $revalue = $issued !== (int) $company['issuedShareCount'] || $after !== $before;
                    break;
                case 'initial':
                    if (aetherCompanySnapshotHistoryAvailable($pdo)) {
                        throw new AetherShareException(409, 'Bewerk de aandelen bij oprichting in de oprichtingssnapshot.');
                    }
                    $initialValue = (string) $input['initialCompanyValue'];
                    $initialCount = (int) $input['initialShareCount'];
                    $difference = $initialCount - (int) $company['initialShareCount'];
                    $issued = (int) $company['issuedShareCount'] + $difference;
                    if ($issued <= 0 || $issued < $stock['held'] + $stock['anonymous']) {
                        throw new AetherShareException(409, 'De correctie laat te weinig uitgegeven aandelen over.');
                    }
                    $stmt = $pdo->prepare('UPDATE tblCompany SET initialCompanyValue = :value,
                                             initialShareCount = :initialCount, issuedShareCount = :issued
                                           WHERE id = :idCompany');
                    $stmt->execute(['value' => $initialValue, 'initialCount' => $initialCount,
                                    'issued' => $issued, 'idCompany' => $companyId]);
                    $details = ['oldInitialValue' => $company['initialCompanyValue'],
                                'newInitialValue' => $initialValue,
                                'oldInitialCount' => (int) $company['initialShareCount'],
                                'newInitialCount' => $initialCount, 'newIssuedCount' => $issued];
                    $revalue = $difference !== 0;
                    break;
                case 'value':
                    if (!aetherCompanySnapshotHistoryAvailable($pdo)
                        || aetherLatestCompanySnapshotId($pdo, $companyId) !== (int) $input['idSnapshot']) {
                        throw new AetherShareException(409, 'Alleen de recentste event-snapshot bepaalt de huidige bedrijfswaarde.');
                    }
                    $after = (string) $input['companyValue'];
                    $stmt = $pdo->prepare('UPDATE tblCompany SET companyValue = :value WHERE id = :idCompany');
                    $stmt->execute(['value' => $after, 'idCompany' => $companyId]);
                    $stmt = $pdo->prepare('UPDATE tblCompanySnapshot SET companyValue = :value
                                           WHERE id = :idSnapshot AND idCompany = :idCompany');
                    $stmt->execute(['value' => $after, 'idSnapshot' => $input['idSnapshot'],
                                    'idCompany' => $companyId]);
                    refreshCompanySnapshotsForCurrentPersonnel($pdo, $companyId, (int) $input['idSnapshot']);
                    $details = ['idSnapshot' => (int) $input['idSnapshot'],
                                'oldCompanyValue' => $before, 'newCompanyValue' => $after];
                    $revalue = $after !== $before;
                    break;
                case 'anonymous':
                    if (!aetherCompanySnapshotHistoryAvailable($pdo)
                        || aetherLatestCompanySnapshotId($pdo, $companyId) !== (int) $input['idSnapshot']) {
                        throw new AetherShareException(409, 'Bewerk de anonieme aandelen in de recentste event-snapshot.');
                    }
                    $quantity = (int) $input['shareCount'];
                    if ($quantity > $stock['anonymous'] + $stock['free']) {
                        throw new AetherShareException(409, 'Onvoldoende vrije aandelen.');
                    }
                    $stmt = $pdo->prepare('INSERT INTO tblCompanyAnonymousShares (idCompany, shareCount)
                                           VALUES (:idCompany, :shareCount)
                                           ON DUPLICATE KEY UPDATE shareCount = VALUES(shareCount)');
                    $stmt->execute(['idCompany' => $companyId, 'shareCount' => $quantity]);
                    $stmt = $pdo->prepare('UPDATE tblCompanySnapshot SET anonymousShareCount = :shareCount
                        WHERE id = :idSnapshot AND idCompany = :idCompany');
                    $stmt->execute(['shareCount' => $quantity, 'idSnapshot' => $input['idSnapshot'],
                                    'idCompany' => $companyId]);
                    $details = ['oldAnonymousCount' => $stock['anonymous'], 'newAnonymousCount' => $quantity];
                    break;
                case 'buyback':
                    $quantity = (int) $input['shareCount'];
                    if ($quantity > $stock['free'] || $quantity >= $stock['issued']) {
                        throw new AetherShareException(409, 'Onvoldoende vrije aandelen voor inkoop.');
                    }
                    $after = aetherShareValueAfterBuyback($before, $stock['issued'], $quantity);
                    $stmt = $pdo->prepare('UPDATE tblCompany SET companyValue = :value,
                                             issuedShareCount = :issued WHERE id = :idCompany');
                    $stmt->execute(['value' => $after, 'issued' => $stock['issued'] - $quantity,
                                    'idCompany' => $companyId]);
                    $transactionId = aetherShareInsertTransaction($pdo, [
                        'idCompany' => $companyId, 'idCharacter' => null,
                        'idCounterpartyCharacter' => null, 'idOffer' => null, 'idEvent' => null,
                        'actorUserId' => (int) $user['id'], 'operationCode' => 'buyback_free',
                        'shareCount' => $quantity, 'unitPrice' => null, 'totalAmount' => '0.00',
                        'companyValueBefore' => $before, 'companyValueAfter' => $after,
                        'requestKey' => $key,
                        'detailsJson' => json_encode(['issuedBefore' => $stock['issued'],
                                                      'issuedAfter' => $stock['issued'] - $quantity], JSON_THROW_ON_ERROR),
                    ]);
                    aetherShareInsertValueHistory($pdo, $companyId, $transactionId, (int) $user['id'],
                                                  'free_share_buyback', $before, $after);
                    $revalue = true;
                    break;
                case 'listing':
                    $stmt = $pdo->prepare('UPDATE tblCompany SET isShareListed = :listed WHERE id = :idCompany');
                    $stmt->execute(['listed' => $input['isShareListed'] ? 1 : 0, 'idCompany' => $companyId]);
                    $details = ['oldListed' => (int) $company['isShareListed'],
                                'newListed' => (bool) $input['isShareListed']];
                    break;
                case 'market_price':
                    $stmt = $pdo->prepare('SELECT shareMarketPrice FROM tblCompanySnapshot
                                           WHERE id = :idSnapshot AND idCompany = :idCompany FOR UPDATE');
                    $stmt->execute(['idSnapshot' => $input['idSnapshot'], 'idCompany' => $companyId]);
                    $oldPrice = $stmt->fetchColumn();
                    if ($oldPrice === false) throw new AetherShareException(404, 'Snapshot niet gevonden.');
                    $stmt = $pdo->prepare('UPDATE tblCompanySnapshot SET shareMarketPrice = :price WHERE id = :idSnapshot');
                    $stmt->execute(['price' => $input['unitPrice'], 'idSnapshot' => $input['idSnapshot']]);
                    $details = ['idSnapshot' => (int) $input['idSnapshot'],
                                'oldMarketPrice' => $oldPrice, 'newMarketPrice' => $input['unitPrice']];
                    break;
                case 'board':
                    $characterId = (int) $input['idCharacter'];
                    $stmt = $pdo->prepare('SELECT isBoardMember, isCompanyLeader FROM tblCompanyPersonnel
                                           WHERE idCompany = :idCompany AND idCharacter = :idCharacter FOR UPDATE');
                    $stmt->execute(['idCompany' => $companyId, 'idCharacter' => $characterId]);
                    $boardRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($boardRow === false) throw new AetherShareException(404, 'Personeelslid niet gevonden.');
                    if (aetherCharacterHasCompanyLeaderProfession($pdo, $characterId) && !$input['isBoardMember']) {
                        throw new AetherShareException(409, 'Een bedrijfsleider blijft lid van de raad van bestuur.');
                    }
                    $stmt = $pdo->prepare('UPDATE tblCompanyPersonnel SET isBoardMember = :board
                                           WHERE idCompany = :idCompany AND idCharacter = :idCharacter');
                    $stmt->execute(['board' => $input['isBoardMember'] ? 1 : 0,
                                    'idCompany' => $companyId, 'idCharacter' => $characterId]);
                    $details = ['idCharacter' => $characterId, 'oldBoard' => (int) $boardRow['isBoardMember'],
                                'newBoard' => (bool) $input['isBoardMember']];
                    break;
                default:
                    throw new LogicException('Onbekende aandelenbeheeractie.');
            }
            if ($action !== 'buyback') {
                $details['requestKey'] = $key;
                aetherShareInsertValueHistory($pdo, $companyId, null, (int) $user['id'],
                    'share_' . $action, $before, $after,
                    json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }
            if ($revalue) aetherShareRevalue($pdo, $affected);
            return ['idCompany' => $companyId, 'companyValue' => $after,
                    'stock' => aetherShareStock($pdo, $companyId,
                        in_array($action, ['foundation', 'initial'], true) ? $issued
                            : ($action === 'buyback' ? $stock['issued'] - $quantity : $stock['issued'])),
                    'status' => 'ok'];
        },
        static function (array $companies) use ($user, $input): void {
            aetherShareRequireManager($user);
            aetherShareRequireCompany($companies, (int) $input['idCompany']);
        });
}
