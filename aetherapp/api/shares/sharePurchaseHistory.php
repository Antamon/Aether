<?php
declare(strict_types=1);

/** Purchase periods are in game years; event dates are exactly 100 years later. */
function aetherShareQuarterFromDate(string $date, bool $outgame = true): ?array
{
    if (!preg_match('/^(\d{4})-(\d{2})-\d{2}/', $date, $parts)) return null;
    $month = (int) $parts[2];
    if ($month < 1 || $month > 12) return null;
    return ['purchaseYear' => (int) $parts[1] - ($outgame ? 100 : 0),
        'purchaseQuarter' => intdiv($month - 1, 3) + 1];
}

function aetherShareEventForQuarter(array $events, int $year, int $quarter): ?array
{
    return aetherShareChooseMarketEvent($events, sprintf('%04d-%02d-01', $year + 100, ($quarter - 1) * 3 + 1));
}

function aetherSharePurchasePeriod(PDO $pdo, array $input, array $user, ?int $eventId,
    bool $restrictParticipant = true, ?string $foundationDate = null, ?int $companyId = null): array
{
    if (!aetherCanManageShareCompany($user)) {
        $today = aetherShareQuarterFromDate((new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d'));
        if ((array_key_exists('purchaseYear', $input) || array_key_exists('purchaseQuarter', $input))
            && ((int) ($input['purchaseYear'] ?? 0) !== $today['purchaseYear']
                || (int) ($input['purchaseQuarter'] ?? 0) !== $today['purchaseQuarter'])) {
            throw new AetherShareException(403, 'Alleen admins en directors kunnen een ander aankoopkwartaal kiezen.');
        }
        return ['idEvent' => $eventId] + $today;
    }
    $events = $pdo->query('SELECT id, title, dateStart, dateEnd FROM tblEvent')->fetchAll(PDO::FETCH_ASSOC);
    if ($companyId !== null) {
        $stmt = $pdo->prepare('SELECT DISTINCT idEvent FROM tblCompanySnapshot WHERE idCompany = :idCompany');
        $stmt->execute(['idCompany' => $companyId]);
        $snapshotEvents = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'idEvent'));
        $events = array_values(array_filter($events, static fn(array $event): bool => in_array((int) $event['id'], $snapshotEvents, true)));
    }
    $event = null;
    foreach ($events as $candidate) if ((int) $candidate['id'] === $eventId) $event = $candidate;
    $hasYear = array_key_exists('purchaseYear', $input);
    $hasQuarter = array_key_exists('purchaseQuarter', $input);
    if ($hasYear !== $hasQuarter) throw new AetherShareException(422, 'Kies een jaar en kwartaal.');
    if (!$hasYear) {
        $period = $event ? aetherShareQuarterFromDate((string) $event['dateStart'])
            : aetherShareQuarterFromDate((string) $foundationDate, false);
        return ['idEvent' => $eventId] + ($period ?? ['purchaseYear' => null, 'purchaseQuarter' => null]);
    }
    $year = (int) $input['purchaseYear']; $quarter = (int) $input['purchaseQuarter'];
    if ($year < 1000 || $year > 9899 || $quarter < 1 || $quarter > 4) {
        throw new AetherShareException(422, 'Kies een geldig speljaar en kwartaal.');
    }
    $next = aetherShareEventForQuarter($events, $year, $quarter);
    if (!$next && $companyId !== null) {
        throw new AetherShareException(409, 'Voor dit kwartaal bestaat geen toekomstige snapshot van dit bedrijf.');
    }
    if (!$next || ($eventId !== null && (int) $next['id'] !== $eventId)) {
        throw new AetherShareException(409, 'Dit kwartaal hoort niet bij het gekozen event. Kies het eerstvolgende event voor dit kwartaal.');
    }
    return ['idEvent' => (int) $next['id'], 'purchaseYear' => $year, 'purchaseQuarter' => $quarter];
}

function aetherShareValidatePurchaseFoundation(array $company, array $period): void
{
    $foundation = aetherShareQuarterFromDate((string) ($company['foundationDate'] ?? ''), false);
    if ($foundation && $period['purchaseYear'] !== null
        && $period['purchaseYear'] * 4 + $period['purchaseQuarter'] < $foundation['purchaseYear'] * 4 + $foundation['purchaseQuarter']) {
        throw new AetherShareException(422, 'De aankoop kan niet vóór het oprichtingskwartaal van het bedrijf vallen.');
    }
}

/** Original purchases stay immutable. Corrections are separate audited transactions. */
function aetherSharePurchaseWithCorrections(array $purchase, array $corrections): array
{
    $details = json_decode((string) ($purchase['detailsJson'] ?? ''), true) ?: [];
    $period = aetherShareQuarterFromDate((string) ($purchase['eventDate'] ?? ''));
    $purchase['purchaseYear'] = $details['purchaseYear'] ?? $period['purchaseYear'] ?? null;
    $purchase['purchaseQuarter'] = $details['purchaseQuarter'] ?? $period['purchaseQuarter'] ?? null;
    $purchase['revision'] = 0;
    $purchase['draftCapitalPurchase'] = !empty($details['draftCapitalPurchase']);
    $purchase['legacyOwnership'] = !empty($details['legacyOwnership']);
    $purchase['purchaseDateSource'] = $details['purchaseDateSource'] ?? null;
    foreach ($corrections as $correction) {
        $change = json_decode((string) ($correction['detailsJson'] ?? ''), true) ?: [];
        if ((int) ($change['originalTransactionId'] ?? 0) !== (int) $purchase['id']) continue;
        foreach (['shareCount', 'unitPrice', 'totalAmount', 'idEvent', 'purchaseYear', 'purchaseQuarter', 'purchaseDateSource'] as $key) {
            if (array_key_exists($key, $change['after'] ?? [])) $purchase[$key] = $change['after'][$key];
        }
        $purchase['eventTitle'] = $correction['eventTitle'] ?? $purchase['eventTitle'] ?? null;
        $purchase['eventDate'] = $correction['eventDate'] ?? $purchase['eventDate'] ?? null;
        $purchase['revision'] = (int) $correction['id'];
    }
    unset($purchase['detailsJson']);
    return $purchase;
}

function aetherSharePurchaseCorrections(PDO $pdo, int $companyId, int $characterId): array
{
    $stmt = $pdo->prepare("SELECT t.id, t.detailsJson, e.title AS eventTitle, e.dateStart AS eventDate
        FROM tblCompanyShareTransaction t LEFT JOIN tblEvent e ON e.id = t.idEvent
        WHERE t.idCompany = :idCompany AND t.idCharacter = :idCharacter
          AND t.operationCode = 'purchase_correction' ORDER BY t.id");
    $stmt->execute(['idCompany' => $companyId, 'idCharacter' => $characterId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aetherSharePurchaseQuote(PDO $pdo, int $companyId, int $eventId): string
{
    $stmt = $pdo->prepare('SELECT shareMarketPrice FROM tblCompanySnapshot
        WHERE idCompany = :idCompany AND idEvent = :idEvent ORDER BY id DESC LIMIT 1');
    $stmt->execute(['idCompany' => $companyId, 'idEvent' => $eventId]);
    $price = $stmt->fetchColumn();
    if ($price === false || $price === null || aetherDecimalCompare((string) $price, '0.00') <= 0) {
        throw new AetherShareException(409, 'De eerstvolgende bedrijfssnapshot heeft geen geldige marktprijs.');
    }
    return aetherNormalizeDecimal((string) $price);
}

function aetherShareCheckBankChange(array $character, string $delta): void
{
    if (aetherDecimalCompare($delta, '0.00') === 0) return;
    $balance = aetherDecimalAdd((string) ($character['bankaccount'] ?? '0.00'), $delta);
    if (aetherDecimalCompare($balance, '0.00') < 0) throw new AetherShareException(409, 'Onvoldoende banksaldo om de aankoop te corrigeren.');
    if (aetherDecimalCompare($balance, '9999999999.99') > 0) throw new AetherShareException(409, 'Het nieuwe banksaldo overschrijdt het toegestane bereik.');
}

/** Add historical dates/ownership records, without purchasing or booking money again. */
function aetherShareInitializePurchaseHistory(PDO $pdo, array $user, array $input, string $key): array
{
    aetherShareRequireManager($user);
    return aetherShareMutation($pdo, $user, 'purchase.history.initialize', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            aetherShareRequireManager($user);
            $companyId = (int) $input['idCompany']; $characterId = (int) $input['idCharacter'];
            $company = aetherShareRequireCompany($companies, $companyId);
            $characters = aetherFinanceLockCharacters($pdo, [$characterId]);
            aetherShareRequireCharacter($characters, $characterId, $user, false);
            $holding = aetherShareLockHolding($pdo, $companyId, $characterId);
            $stmt = $pdo->prepare("SELECT t.*, e.title AS eventTitle, e.dateStart AS eventDate
                FROM tblCompanyShareTransaction t LEFT JOIN tblEvent e ON e.id = t.idEvent
                WHERE t.idCompany = :idCompany
                  AND (t.idCharacter = :idCharacter OR t.idCounterpartyCharacter = :counterparty)
                  AND t.operationCode IN ('buy_free', 'buy_from_holder', 'legacy_purchase', 'purchase_correction')
                ORDER BY t.id");
            $stmt->execute(['idCompany' => $companyId, 'idCharacter' => $characterId, 'counterparty' => $characterId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $corrections = array_values(array_filter($rows, static fn(array $row): bool => $row['operationCode'] === 'purchase_correction'));
            $undated = []; $recorded = 0;
            foreach ($rows as $row) {
                if ($row['operationCode'] === 'purchase_correction') continue;
                $purchase = aetherSharePurchaseWithCorrections($row, $corrections);
                if ((int) $purchase['idCharacter'] === $characterId) {
                    $recorded += (int) $purchase['shareCount'];
                    if (!$purchase['purchaseYear'] || !$purchase['purchaseQuarter']) $undated[] = $purchase;
                } elseif ($purchase['operationCode'] === 'buy_from_holder' && (int) $purchase['idCounterpartyCharacter'] === $characterId) {
                    $recorded -= (int) $purchase['shareCount'];
                }
            }
            $missing = max(0, (int) ($holding['shareCount'] ?? 0) - $recorded);
            if (!$missing && !$undated) return ['initializedPurchases' => 0];
            $stmt = $pdo->prepare('SELECT createdAt FROM tblCharacter WHERE id = :idCharacter');
            $stmt->execute(['idCharacter' => $characterId]);
            $createdAt = (string) $stmt->fetchColumn();
            $period = aetherShareQuarterFromDate($createdAt);
            if (!$period || $period['purchaseYear'] < 1000 || $period['purchaseYear'] > 9899) {
                throw new AetherShareException(409, 'De aanmaakdatum van dit personage ontbreekt. Daardoor kan het aankoopkwartaal niet automatisch worden aangevuld.');
            }
            $period['purchaseDateSource'] = 'character_created';
            $value = aetherNormalizeDecimal((string) $company['companyValue']);
            $initialized = 0;
            foreach ($undated as $purchase) {
                $before = array_intersect_key($purchase, array_flip(['idEvent', 'shareCount', 'unitPrice', 'totalAmount', 'purchaseYear', 'purchaseQuarter']));
                $after = array_replace($before, $period);
                aetherShareInsertTransaction($pdo, [
                    'idCompany' => $companyId, 'idCharacter' => $characterId,
                    'idCounterpartyCharacter' => $purchase['idCounterpartyCharacter'], 'idOffer' => $purchase['idOffer'],
                    'idEvent' => $purchase['idEvent'], 'actorUserId' => (int) $user['id'],
                    'operationCode' => 'purchase_correction', 'shareCount' => (int) $purchase['shareCount'],
                    'unitPrice' => $purchase['unitPrice'], 'totalAmount' => $purchase['totalAmount'],
                    'companyValueBefore' => $value, 'companyValueAfter' => $value,
                    'requestKey' => substr(hash('sha256', $key . ':date:' . $purchase['id']), 0, 64),
                    'detailsJson' => json_encode(['originalTransactionId' => (int) $purchase['id'],
                        'before' => $before, 'after' => $after, 'adjustBank' => false,
                        'bankDelta' => '0.00', 'counterpartyBankDelta' => '0.00', 'draftCapitalDelta' => '0.00'], JSON_THROW_ON_ERROR),
                ]);
                $initialized++;
            }
            if ($missing) {
                aetherShareInsertTransaction($pdo, [
                    'idCompany' => $companyId, 'idCharacter' => $characterId,
                    'idCounterpartyCharacter' => null, 'idOffer' => null, 'idEvent' => null,
                    'actorUserId' => (int) $user['id'], 'operationCode' => 'legacy_purchase',
                    'shareCount' => $missing, 'unitPrice' => null, 'totalAmount' => '0.00',
                    'companyValueBefore' => $value, 'companyValueAfter' => $value, 'requestKey' => $key,
                    'detailsJson' => json_encode(['legacyOwnership' => true, 'characterCreatedAt' => $createdAt] + $period, JSON_THROW_ON_ERROR),
                ]);
                $initialized++;
            }
            return ['initializedPurchases' => $initialized];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            aetherShareRequireManager($user);
            aetherShareRequireCompany($companies, (int) $input['idCompany']);
            $character = aetherFetchCharacterAccessRecord($pdo, (int) $input['idCharacter']);
            if (!$character || !aetherCanTradeForCharacter($user, $character)) throw new AetherShareException(403, 'Geen rechten voor dit personage.');
        });
}

function aetherShareUpdatePurchase(PDO $pdo, array $user, array $input, string $key): array
{
    aetherShareRequireManager($user);
    return aetherShareMutation($pdo, $user, 'purchase.update', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            aetherShareRequireManager($user);
            $companyId = (int) $input['idCompany']; $buyerId = (int) $input['idCharacter'];
            $company = aetherShareRequireCompany($companies, $companyId);
            // The company lock serializes purchases/corrections; character locks come next.
            $stmt = $pdo->prepare("SELECT t.*, e.title AS eventTitle, e.dateStart AS eventDate
                FROM tblCompanyShareTransaction t LEFT JOIN tblEvent e ON e.id = t.idEvent
                WHERE t.id = :idTransaction AND t.idCompany = :idCompany AND t.idCharacter = :idCharacter
                  AND t.operationCode IN ('buy_free', 'buy_from_holder', 'legacy_purchase')");
            $stmt->execute(['idTransaction' => (int) $input['idTransaction'], 'idCompany' => $companyId, 'idCharacter' => $buyerId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$original) throw new AetherShareException(404, 'Aankoop niet gevonden voor dit personage en bedrijf.');
            $purchase = aetherSharePurchaseWithCorrections($original, aetherSharePurchaseCorrections($pdo, $companyId, $buyerId));
            if ((int) $input['revision'] !== $purchase['revision']) {
                throw new AetherShareException(409, 'Deze aankoop werd ondertussen gewijzigd. Sluit de modal en open de aankoop opnieuw.');
            }
            $holderSale = $original['operationCode'] === 'buy_from_holder';
            $sellerId = $holderSale ? (int) $original['idCounterpartyCharacter'] : 0;
            if ($holderSale && ($sellerId <= 0 || $sellerId === $buyerId)) throw new AetherShareException(409, 'De oorspronkelijke verkoper is niet beschikbaar.');
            $affected = array_values(array_unique([...aetherShareHolderIds($pdo, $companyId), $buyerId, ...($sellerId ? [$sellerId] : [])]));
            $characters = aetherFinanceLockCharacters($pdo, $affected);
            $buyer = aetherShareRequireCharacter($characters, $buyerId, $user, false);
            if ($sellerId && !isset($characters[$sellerId])) throw new AetherShareException(409, 'De oorspronkelijke verkoper is niet beschikbaar.');
            $sameFoundationPeriod = $purchase['idEvent'] === null
                && (int) $input['purchaseYear'] === (int) $purchase['purchaseYear']
                && (int) $input['purchaseQuarter'] === (int) $purchase['purchaseQuarter'];
            $period = $sameFoundationPeriod ? ['idEvent' => null, 'purchaseYear' => (int) $input['purchaseYear'],
                'purchaseQuarter' => (int) $input['purchaseQuarter']] : aetherSharePurchasePeriod($pdo, $input, $user, null, true, null, $companyId);
            if (!$sameFoundationPeriod || $purchase['purchaseDateSource'] !== 'character_created') aetherShareValidatePurchaseFoundation($company, $period);
            if ($period['idEvent'] !== null) aetherShareRequireMarketEvent($pdo, (int) $period['idEvent'], $user);
            $unknownPrice = $purchase['unitPrice'] === null;
            $price = (int) $period['idEvent'] === (int) $purchase['idEvent']
                ? ($unknownPrice ? null : aetherNormalizeDecimal((string) $purchase['unitPrice']))
                : aetherSharePurchaseQuote($pdo, $companyId, (int) $period['idEvent']);
            $quantity = (int) $input['shareCount'];
            $quantityDelta = $quantity - (int) $purchase['shareCount'];
            $total = $price === null ? (string) $purchase['totalAmount'] : aetherSharePurchaseTotal($price, $quantity);
            $amountDelta = aetherDecimalSubtract($total, (string) $purchase['totalAmount']);
            $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
            $buyerHolding = aetherShareLockHolding($pdo, $companyId, $buyerId);
            if ($quantityDelta < 0 && (int) ($buyerHolding['shareCount'] ?? 0) < -$quantityDelta) {
                throw new AetherShareException(409, 'Dit personage bezit niet genoeg aandelen meer om de aankoop zo te verlagen.');
            }
            $sellerHolding = $holderSale ? aetherShareLockHolding($pdo, $companyId, $sellerId) : null;
            if ($quantityDelta > 0 && ($holderSale ? (int) ($sellerHolding['shareCount'] ?? 0) : $stock['free']) < $quantityDelta) {
                throw new AetherShareException(409, $holderSale ? 'De verkoper bezit niet genoeg aandelen meer.' : 'Onvoldoende vrije aandelen voor deze correctie.');
            }
            $offer = (int) ($original['idOffer'] ?? 0) > 0 ? aetherShareLockOffer($pdo, (int) $original['idOffer']) : null;
            if ($offer && $quantityDelta !== 0) {
                $purchased = (int) $offer['purchasedShareCount'] + $quantityDelta;
                if ($purchased < 0 || $purchased > (int) $offer['offeredShareCount']) {
                    throw new AetherShareException(409, 'Het gewijzigde aantal past niet binnen het oorspronkelijke aanbod.');
                }
                $status = $offer['statusCode'];
                if (in_array($status, ['open', 'filled'], true)) $status = $purchased === (int) $offer['offeredShareCount'] ? 'filled' : 'open';
                $stmt = $pdo->prepare('UPDATE tblShareOffer SET purchasedShareCount = :purchased, statusCode = :statusCode WHERE id = :idOffer');
                $stmt->execute(['purchased' => $purchased, 'statusCode' => $status, 'idOffer' => $offer['id']]);
            }
            $draftDelta = '0.00';
            if ($purchase['draftCapitalPurchase'] && aetherDecimalCompare($amountDelta, '0.00') !== 0) {
                $budget = aetherShareDraftBudget($pdo, $buyerId);
                if (!$budget) throw new AetherShareException(409, 'Het oorspronkelijke aandelenstartkapitaal is niet beschikbaar.');
                $spent = aetherDecimalAdd($budget['spentAmount'], $amountDelta);
                if (aetherDecimalCompare($spent, '0.00') < 0 || aetherDecimalCompare($spent, $budget['budgetAmount']) > 0) {
                    throw new AetherShareException(409, 'Deze correctie past niet binnen het oorspronkelijke aandelenstartkapitaal.');
                }
                $stmt = $pdo->prepare('UPDATE tblCharacterShareDraftBudget SET spentAmount = :spent WHERE idCharacter = :idCharacter');
                $stmt->execute(['spent' => $spent, 'idCharacter' => $buyerId]);
                $draftDelta = $amountDelta;
            }
            if ($unknownPrice && $input['adjustBank']) {
                throw new AetherShareException(409, 'Het oorspronkelijke aankoopbedrag is onbekend. Laat Bankrekening aanpassen uit voor deze historische correctie.');
            }
            $bankDelta = $input['adjustBank'] ? aetherDecimalSubtract('0.00', $amountDelta) : '0.00';
            $sellerDelta = $holderSale && $input['adjustBank'] ? $amountDelta : '0.00';
            aetherShareCheckBankChange($buyer, $bankDelta);
            if ($sellerId) aetherShareCheckBankChange($characters[$sellerId], $sellerDelta);
            $before = aetherNormalizeDecimal((string) $company['companyValue']);
            $after = $holderSale || $unknownPrice ? $before : aetherShareValueAfterPurchase($pdo, $company, $amountDelta);
            if (aetherDecimalCompare($after, '0.00') < 0 || aetherDecimalCompare($after, '9999999999.99') > 0) {
                throw new AetherShareException(409, 'De gecorrigeerde bedrijfswaarde ligt buiten het toegestane bereik.');
            }
            if ($quantityDelta > 0) {
                if ($holderSale) aetherShareRemoveHolding($pdo, $companyId, $sellerId, (int) $sellerHolding['shareCount'], $quantityDelta);
                aetherShareAddHolding($pdo, $companyId, $buyerId, $quantityDelta);
            } elseif ($quantityDelta < 0) {
                aetherShareRemoveHolding($pdo, $companyId, $buyerId, (int) $buyerHolding['shareCount'], -$quantityDelta);
                if ($holderSale) aetherShareAddHolding($pdo, $companyId, $sellerId, -$quantityDelta);
            }
            if (aetherDecimalCompare($bankDelta, '0.00') !== 0) aetherFinanceAdjustBankBalance($pdo, $buyerId, $bankDelta);
            if ($sellerId && aetherDecimalCompare($sellerDelta, '0.00') !== 0) aetherFinanceAdjustBankBalance($pdo, $sellerId, $sellerDelta);
            if ($after !== $before) {
                $stmt = $pdo->prepare('UPDATE tblCompany SET companyValue = :value WHERE id = :id');
                $stmt->execute(['value' => $after, 'id' => $companyId]);
            }
            $keys = array_flip(['shareCount', 'unitPrice', 'totalAmount', 'idEvent', 'purchaseYear', 'purchaseQuarter']);
            $next = ['shareCount' => $quantity, 'unitPrice' => $price, 'totalAmount' => $total] + $period;
            $next['purchaseDateSource'] = (int) $period['purchaseYear'] === (int) $purchase['purchaseYear']
                && (int) $period['purchaseQuarter'] === (int) $purchase['purchaseQuarter']
                ? $purchase['purchaseDateSource'] : 'manual';
            $details = json_encode(['originalTransactionId' => (int) $original['id'],
                'before' => array_intersect_key($purchase, $keys), 'after' => $next,
                'adjustBank' => $input['adjustBank'], 'bankDelta' => $bankDelta,
                'counterpartyBankDelta' => $sellerDelta, 'draftCapitalDelta' => $draftDelta], JSON_THROW_ON_ERROR);
            $correctionId = aetherShareInsertTransaction($pdo, [
                'idCompany' => $companyId, 'idCharacter' => $buyerId, 'idCounterpartyCharacter' => $sellerId ?: null,
                'idOffer' => $original['idOffer'], 'idEvent' => $period['idEvent'], 'actorUserId' => (int) $user['id'],
                'operationCode' => 'purchase_correction', 'shareCount' => $quantity, 'unitPrice' => $price,
                'totalAmount' => $total, 'companyValueBefore' => $before, 'companyValueAfter' => $after,
                'requestKey' => $key, 'detailsJson' => $details,
            ]);
            if ($after !== $before) aetherShareInsertValueHistory($pdo, $companyId, $correctionId, (int) $user['id'], 'share_purchase_correction', $before, $after, $details);
            aetherShareRevalue($pdo, $affected);
            return ['transactionId' => (int) $original['id'], 'revision' => $correctionId,
                'bankDelta' => $bankDelta, 'purchase' => $next];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            aetherShareRequireManager($user);
            aetherShareRequireCompany($companies, (int) $input['idCompany']);
            $character = aetherFetchCharacterAccessRecord($pdo, (int) $input['idCharacter']);
            if (!$character || !aetherCanTradeForCharacter($user, $character)) throw new AetherShareException(403, 'Geen rechten voor dit personage.');
        });
}
