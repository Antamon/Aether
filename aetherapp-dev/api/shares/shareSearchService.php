<?php
declare(strict_types=1);

require_once __DIR__ . '/shareService.php';

function aetherShareEffectiveSearchLevel(PDO $pdo, int $characterId): int
{
    $stmt = $pdo->query("SELECT code, entityId FROM tblShareDomainCode
                          WHERE code IN ('skill.fiscality', 'skill_specialisation.stock_market')");
    $codes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $codes[$row['code']] = (int) $row['entityId'];
    if (!isset($codes['skill.fiscality'], $codes['skill_specialisation.stock_market'])) {
        throw new AetherShareException(503, 'Fiscaliteit is niet geconfigureerd.', 'shares.domain_unavailable');
    }
    $stmt = $pdo->prepare('SELECT level FROM tblLinkCharacterSkill
                           WHERE idCharacter = :idCharacter AND idSkill = :idSkill');
    $stmt->execute(['idCharacter' => $characterId, 'idSkill' => $codes['skill.fiscality']]);
    $level = (int) ($stmt->fetchColumn() ?: 0);
    if ($level < 1) {
        throw new AetherShareException(403, 'Dit personage heeft Fiscaliteit nodig.', 'shares.fiscality_required');
    }
    $stmt = $pdo->prepare('SELECT 1 FROM tblCharacterSpecialisation
                           WHERE idCharacter = :idCharacter AND idSkill = :idSkill
                             AND idSkillSpecialisation = :idSpecialisation LIMIT 1');
    $stmt->execute(['idCharacter' => $characterId, 'idSkill' => $codes['skill.fiscality'],
                    'idSpecialisation' => $codes['skill_specialisation.stock_market']]);
    return min(4, min(3, $level) + ($stmt->fetchColumn() !== false ? 1 : 0));
}

/** Candidate company IDs are read before locks, then each company is locked in ID order. */
function aetherShareMarketCompanyIds(PDO $pdo, int $eventId, int $characterId): array
{
    $stmt = $pdo->prepare('SELECT DISTINCT c.id FROM tblCompany c
                            JOIN tblCompanySnapshot s ON s.idCompany = c.id AND s.idEvent = :idEvent
                            LEFT JOIN tblShareSearch existingSearch
                              ON existingSearch.idEvent = s.idEvent
                             AND existingSearch.idCharacter = :idCharacter
                           WHERE NOT EXISTS (SELECT 1 FROM tblShareSearchAssessment checked
                                               WHERE checked.idSearch = existingSearch.id
                                                 AND checked.idCompany = c.id
                                                 AND checked.idSnapshot = s.id)
                           ORDER BY c.id');
    $stmt->execute(['idEvent' => $eventId, 'idCharacter' => $characterId]);
    return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function aetherShareSearch(PDO $pdo, array $user, array $input, string $key): array
{
    aetherRequireShareMigration($pdo);
    $characterId = (int) $input['idCharacter'];
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanTradeForCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    if ($pdo->inTransaction()) throw new LogicException('Zoekactie vereist een eigen transactie.');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    try {
        return aetherRunIdempotentMutation($pdo, $user, 'shares.market.search', $key, $input,
            static function () use ($pdo, $user, $characterId, $input): array {
                $event = aetherShareSelectedMarketEvent($pdo, (int) ($input['idEvent'] ?? 0));
                if (!$event) throw new AetherShareException(404, 'Event niet gevonden.', 'shares.no_market_event');
                $eventId = (int) $event['id'];
                $companyIds = aetherShareMarketCompanyIds($pdo, $eventId, $characterId);
                $companies = [];
                foreach ($companyIds as $id) $companies += aetherSharesLockCompany($pdo, $id);
                $characters = aetherFinanceLockCharacters($pdo, [$characterId]);
                aetherShareRequireCharacter($characters, $characterId, $user);
                $level = aetherShareEffectiveSearchLevel($pdo, $characterId);
                $band = aetherShareSearchBand($level);
                $stmt = $pdo->prepare('INSERT INTO tblShareSearch (idCharacter, idEvent)
                                       VALUES (:idCharacter, :idEvent)
                                       ON DUPLICATE KEY UPDATE lastCheckedAt = NOW()');
                $stmt->execute(['idCharacter' => $characterId, 'idEvent' => $eventId]);
                $stmt = $pdo->prepare('SELECT id FROM tblShareSearch
                                       WHERE idCharacter = :idCharacter AND idEvent = :idEvent FOR UPDATE');
                $stmt->execute(['idCharacter' => $characterId, 'idEvent' => $eventId]);
                $searchId = (int) $stmt->fetchColumn();
                $added = 0;
                foreach ($companies as $companyId => $company) {
                    $stock = aetherShareStock($pdo, (int) $companyId, (int) $company['issuedShareCount']);
                    $stmt = $pdo->prepare('SELECT id, shareMarketPrice FROM tblCompanySnapshot
                                           WHERE idCompany = :idCompany AND idEvent = :idEvent ORDER BY id');
                    $stmt->execute(['idCompany' => $companyId, 'idEvent' => $eventId]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $snapshot) {
                        $stmt = $pdo->prepare('SELECT id FROM tblShareSearchAssessment
                                               WHERE idSearch = :idSearch AND idCompany = :idCompany
                                                 AND idSnapshot = :idSnapshot');
                        $stmt->execute(['idSearch' => $searchId, 'idCompany' => $companyId,
                                        'idSnapshot' => $snapshot['id']]);
                        if ($stmt->fetchColumn() !== false) continue;
                        $discovery = random_int(1, 100);
                        $found = $discovery <= $band['chance'];
                        $quantityPercent = random_int($band['quantityMin'], $band['quantityMax']);
                        $priceModifier = random_int($band['priceMin'], $band['priceMax']);
                        $stmt = $pdo->prepare('INSERT INTO tblShareSearchAssessment
                            (idSearch, idCompany, idSnapshot, effectiveSkillLevel, discoveryRoll,
                             quantityRoll, priceRoll, wasDiscovered)
                            VALUES (:idSearch, :idCompany, :idSnapshot, :level, :discovery,
                                    :quantity, :price, :found)');
                        $stmt->execute(['idSearch' => $searchId, 'idCompany' => $companyId,
                                        'idSnapshot' => $snapshot['id'], 'level' => $level,
                                        'discovery' => $discovery, 'quantity' => $quantityPercent,
                                        'price' => $priceModifier + 100,
                                        'found' => $found ? 1 : 0]);
                        $assessmentId = (int) $pdo->lastInsertId();
                        $added++;
                        if (!$found || (int) $company['isShareListed'] !== 1
                            || $stock['free'] <= 0 || (float) ($snapshot['shareMarketPrice'] ?? 0) <= 0) continue;
                        $quantity = aetherShareSearchQuantity($stock['free'], $quantityPercent);
                        $unitPrice = aetherShareSearchPrice((string) $snapshot['shareMarketPrice'], $priceModifier);
                        $stmt = $pdo->prepare("INSERT INTO tblShareOffer
                            (idCompany, idEvent, idSnapshot, idAssessment, sourceCode,
                             offeredShareCount, unitPrice)
                            VALUES (:idCompany, :idEvent, :idSnapshot, :idAssessment,
                                    'search', :quantity, :unitPrice)");
                        $stmt->execute(['idCompany' => $companyId, 'idEvent' => $eventId,
                                        'idSnapshot' => $snapshot['id'], 'idAssessment' => $assessmentId,
                                        'quantity' => $quantity, 'unitPrice' => $unitPrice]);
                        $offerId = (int) $pdo->lastInsertId();
                        $stmt = $pdo->prepare("INSERT INTO tblShareOfferRecipient
                            (idOffer, idCharacter, recipientKind)
                            VALUES (:idOffer, :idCharacter, 'discoverer')");
                        $stmt->execute(['idOffer' => $offerId, 'idCharacter' => $characterId]);
                    }
                }
                aetherShareRequireMarketEvent($pdo, $eventId);
                return ['idEvent' => $eventId, 'assessmentsAdded' => $added];
            },
            static function () use ($pdo, $user, $characterId): void {
                $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
                if (!$character || !aetherCanTradeForCharacter($user, $character)
                    || (string) $character['state'] !== 'active') {
                    throw new AetherShareException(403, 'Geen rechten voor dit personage.');
                }
                aetherShareEffectiveSearchLevel($pdo, $characterId);
            });
    } catch (PDOException $e) {
        if (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
            throw new AetherShareException(409, 'De markt veranderde gelijktijdig. Probeer opnieuw.');
        }
        throw $e;
    }
}

function aetherShareShareDiscovery(PDO $pdo, array $user, array $input, string $key): array
{
    return aetherShareMutation($pdo, $user, 'market.share', $key, $input,
        static function (array $companies) use ($pdo, $user, $input): array {
            $offerId = (int) $input['idOffer'];
            $recipientId = (int) $input['idRecipientCharacter'];
            $stmt = $pdo->prepare('SELECT o.idCompany, o.idEvent, s.idCharacter AS finderId
                                   FROM tblShareOffer o
                                   JOIN tblShareSearchAssessment a ON a.id = o.idAssessment
                                   JOIN tblShareSearch s ON s.id = a.idSearch
                                  WHERE o.id = :idOffer');
            $stmt->execute(['idOffer' => $offerId]);
            $preview = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$preview) throw new AetherShareException(404, 'Vondst niet gevonden.');
            aetherShareRequireCompany($companies, (int) $preview['idCompany']);
            $finderId = (int) $preview['finderId'];
            if ($finderId === $recipientId) throw new AetherShareException(400, 'Kies een ander personage.');
            $characters = aetherFinanceLockCharacters($pdo, [$finderId, $recipientId]);
            aetherShareRequireCharacter($characters, $finderId, $user);
            if (!isset($characters[$recipientId]) || $characters[$recipientId]['state'] !== 'active') {
                throw new AetherShareException(404, 'Ontvanger niet gevonden of niet actief.');
            }
            $offer = aetherShareLockOffer($pdo, $offerId);
            if (!$offer || $offer['sourceCode'] !== 'search' || $offer['statusCode'] !== 'open'
                || (int) $offer['idCompany'] !== (int) $preview['idCompany']) {
                throw new AetherShareException(409, 'Vondst is niet meer beschikbaar.');
            }
            if ((int) ($input['idEvent'] ?? 0) !== (int) $offer['idEvent']) {
                throw new AetherShareException(409, 'Deze vondst hoort bij een ander event.');
            }
            aetherShareRequireMarketEvent($pdo, (int) $offer['idEvent']);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tblShareOfferRecipient
                                   WHERE idOffer = :idOffer AND recipientKind = 'shared'");
            $stmt->execute(['idOffer' => $offerId]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new AetherShareException(409, 'Deze vondst is al gedeeld.', 'shares.already_shared');
            }
            $stmt = $pdo->prepare("INSERT INTO tblShareOfferRecipient
                (idOffer, idCharacter, recipientKind) VALUES (:idOffer, :idCharacter, 'shared')");
            $stmt->execute(['idOffer' => $offerId, 'idCharacter' => $recipientId]);
            return ['idOffer' => $offerId, 'idRecipientCharacter' => $recipientId];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            $stmt = $pdo->prepare('SELECT s.idCharacter FROM tblShareOffer o
                JOIN tblShareSearchAssessment a ON a.id = o.idAssessment
                JOIN tblShareSearch s ON s.id = a.idSearch WHERE o.id = :idOffer');
            $stmt->execute(['idOffer' => (int) $input['idOffer']]);
            $finder = aetherFetchCharacterAccessRecord($pdo, (int) $stmt->fetchColumn());
            if (!$finder || !aetherCanTradeForCharacter($user, $finder)
                || (string) $finder['state'] !== 'active') {
                throw new AetherShareException(403, 'Geen rechten voor deze vondst.');
            }
        });
}

function aetherShareViewSearch(PDO $pdo, array $user, int $characterId, int $selectedEventId = 0): array
{
    aetherRequireShareMigration($pdo);
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanViewCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    $current = aetherShareSelectedMarketEvent($pdo, $selectedEventId);
    if ($selectedEventId > 0 && !$current) {
        throw new AetherShareException(404, 'Event niet gevonden.');
    }
    $stmt = $pdo->prepare('SELECT e.id, e.title, e.dateStart, e.dateEnd,
                                 s.id AS idSearch, s.lastCheckedAt,
                                 COUNT(a.id) AS assessedCompanies,
                                 COALESCE(SUM(a.wasDiscovered), 0) AS discoveredCompanies
                            FROM tblEvent e
                            LEFT JOIN tblShareSearch s ON s.idEvent = e.id AND s.idCharacter = :idCharacter
                            LEFT JOIN tblShareSearchAssessment a ON a.idSearch = s.id
                           GROUP BY e.id, e.title, e.dateStart, e.dateEnd, s.id, s.lastCheckedAt
                           ORDER BY e.dateStart DESC, e.id DESC');
    $stmt->execute(['idCharacter' => $characterId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT s.idEvent, a.idCompany, c.companyName, a.idSnapshot,
                                 a.effectiveSkillLevel, a.wasDiscovered,
                                 o.id AS idOffer, o.statusCode, o.offeredShareCount,
                                 sr.idCharacter AS sharedRecipientId,
                                 o.purchasedShareCount, o.unitPrice
                            FROM tblShareSearch s
                            JOIN tblShareSearchAssessment a ON a.idSearch = s.id
                            JOIN tblCompany c ON c.id = a.idCompany
                            LEFT JOIN tblShareOffer o ON o.idAssessment = a.id
                            LEFT JOIN tblShareOfferRecipient sr
                              ON sr.idOffer = o.id AND sr.recipientKind = 'shared'
                           WHERE s.idCharacter = :idCharacter AND a.wasDiscovered = 1 AND o.id IS NOT NULL
                           ORDER BY s.idEvent DESC, a.idCompany, a.idSnapshot");
    $stmt->execute(['idCharacter' => $characterId]);
    $discoveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $assessments = [];
    if ($current && aetherCanManageShareCompany($user)) {
        $stmt = $pdo->prepare('SELECT c.id AS idCompany, c.companyName, c.isShareListed,
                                     snapshot.id AS idSnapshot, snapshot.shareMarketPrice,
                                     a.id AS idAssessment, a.effectiveSkillLevel,
                                     a.discoveryRoll, a.quantityRoll, a.priceRoll, a.wasDiscovered,
                                     o.id AS idOffer, o.statusCode, o.offeredShareCount,
                                     o.purchasedShareCount, o.unitPrice
                                FROM tblCompanySnapshot snapshot
                                JOIN tblCompany c ON c.id = snapshot.idCompany
                                LEFT JOIN tblShareSearch searchRecord
                                  ON searchRecord.idCharacter = :idCharacter
                                 AND searchRecord.idEvent = snapshot.idEvent
                                LEFT JOIN tblShareSearchAssessment a
                                  ON a.idSearch = searchRecord.id
                                 AND a.idCompany = c.id AND a.idSnapshot = snapshot.id
                                LEFT JOIN tblShareOffer o ON o.idAssessment = a.id
                               WHERE snapshot.idEvent = :idEvent
                               ORDER BY c.companyName, c.id, snapshot.id');
        $stmt->execute(['idCharacter' => $characterId, 'idEvent' => (int) $current['id']]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($assessments as &$assessment) {
            if ($assessment['idAssessment'] === null) continue;
            $band = aetherShareSearchBand((int) $assessment['effectiveSkillLevel']);
            $assessment['chancePercent'] = $band['chance'];
            $assessment['quantityMin'] = $band['quantityMin'];
            $assessment['quantityMax'] = $band['quantityMax'];
            $assessment['priceMin'] = $band['priceMin'];
            $assessment['priceMax'] = $band['priceMax'];
        }
        unset($assessment);
    }
    return ['currentEvent' => $current, 'events' => $events,
            'discoveries' => $discoveries, 'assessments' => $assessments,
            'canEditAssessments' => aetherCanManageShareCompany($user),
            'offers' => aetherShareViewOffers($pdo, $user, $characterId, (int) ($current['id'] ?? 0))];
}

function aetherShareRecipientOptions(PDO $pdo, array $user, int $finderId): array
{
    aetherRequireShareMigration($pdo);
    if ($finderId === 0 && aetherCanManageShareCompany($user)) {
        $stmt = $pdo->query("SELECT id, firstName, lastName FROM tblCharacter
                              WHERE state = 'active' ORDER BY firstName, lastName, id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $finder = aetherFetchCharacterAccessRecord($pdo, $finderId);
    if (!$finder || !aetherCanTradeForCharacter($user, $finder)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    $stmt = $pdo->prepare("SELECT id, firstName, lastName FROM tblCharacter
                           WHERE state = 'active' AND id <> :idCharacter
                           ORDER BY firstName, lastName, id");
    $stmt->execute(['idCharacter' => $finderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** A small fingerprint for polling. No stock or offer is reserved by reading it. */
function aetherShareMarketVersion(PDO $pdo, array $user, int $characterId, int $selectedEventId = 0): array
{
    aetherRequireShareMigration($pdo);
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanViewCharacter($user, $character)) {
        throw new AetherShareException(403, 'Geen rechten voor dit personage.');
    }
    $current = aetherShareSelectedMarketEvent($pdo, $selectedEventId);
    if ($selectedEventId > 0 && !$current) {
        throw new AetherShareException(404, 'Event niet gevonden.');
    }
    $parts = [$current['id'] ?? 0];
    if ($current) {
        $stmt = $pdo->prepare('SELECT c.id, c.issuedShareCount, c.isShareListed,
                                     s.id AS idSnapshot, s.shareMarketPrice,
                                     COALESCE((SELECT SUM(h.shareCount)
                                                 FROM tblCompanyShareHolding h
                                                WHERE h.idCompany = c.id), 0) AS held,
                                     COALESCE(a.shareCount, 0) AS anonymous
                                FROM tblCompany c
                                JOIN tblCompanySnapshot s ON s.idCompany = c.id AND s.idEvent = :idEvent
                                LEFT JOIN tblCompanyAnonymousShares a ON a.idCompany = c.id
                               ORDER BY c.id, s.id');
        $stmt->execute(['idEvent' => (int) $current['id']]);
        $parts[] = $stmt->fetchAll(PDO::FETCH_NUM);
    }
    $stmt = $pdo->prepare("SELECT o.id, o.statusCode, o.offeredShareCount, o.purchasedShareCount,
                                 o.unitPrice, o.idEvent, r.recipientKind,
                                 (SELECT COUNT(*) FROM tblShareOfferRecipient sr
                                   WHERE sr.idOffer = o.id AND sr.recipientKind = 'shared') AS sharedCount
                            FROM tblShareOfferRecipient r
                            JOIN tblShareOffer o ON o.id = r.idOffer
                           WHERE r.idCharacter = :idCharacter ORDER BY o.id");
    $stmt->execute(['idCharacter' => $characterId]);
    $parts[] = $stmt->fetchAll(PDO::FETCH_NUM);
    $stmt = $pdo->prepare('SELECT a.id, a.discoveryRoll, a.quantityRoll, a.priceRoll,
                                 a.wasDiscovered, a.assessedAt
                            FROM tblShareSearchAssessment a
                            JOIN tblShareSearch s ON s.id = a.idSearch
                           WHERE s.idCharacter = :idCharacter AND s.idEvent = :idEvent
                           ORDER BY a.id');
    $stmt->execute(['idCharacter' => $characterId, 'idEvent' => (int) ($current['id'] ?? 0)]);
    $parts[] = $stmt->fetchAll(PDO::FETCH_NUM);
    return ['idEvent' => $current ? (int) $current['id'] : null,
            'version' => hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR))];
}

/** Correct the three recorded dice rolls without rewriting any completed trade. */
function aetherShareUpdateAssessment(PDO $pdo, array $user, array $input, string $key): array
{
    if (!aetherCanManageShareCompany($user)) {
        throw new AetherShareException(403, 'Alleen admins en directors mogen worpen aanpassen.');
    }
    return aetherShareMutation($pdo, $user, 'market.assessment.update', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            $companyId = (int) $input['idCompany'];
            $company = aetherShareRequireCompany($companies, $companyId);
            $stmt = $pdo->prepare('SELECT a.*, s.idCharacter, s.idEvent,
                                         snapshot.shareMarketPrice
                                    FROM tblShareSearchAssessment a
                                    JOIN tblShareSearch s ON s.id = a.idSearch
                                    JOIN tblCompanySnapshot snapshot ON snapshot.id = a.idSnapshot
                                   WHERE a.id = :idAssessment AND a.idCompany = :idCompany
                                     AND snapshot.idCompany = a.idCompany
                                     AND snapshot.idEvent = s.idEvent FOR UPDATE');
            $stmt->execute(['idAssessment' => (int) $input['idAssessment'],
                            'idCompany' => $companyId]);
            $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$assessment || (int) $assessment['idCharacter'] !== (int) $input['idCharacter']
                || (int) $assessment['idEvent'] !== (int) $input['idEvent']) {
                throw new AetherShareException(404, 'Zoekresultaat niet gevonden voor dit event.');
            }
            $band = aetherShareSearchBand((int) $assessment['effectiveSkillLevel']);
            $discovery = (int) $input['discoveryRoll'];
            $quantity = (int) $input['quantityRoll'];
            $price = (int) $input['priceRoll'];
            if ($quantity < $band['quantityMin'] || $quantity > $band['quantityMax']
                || $price < 100 + $band['priceMin'] || $price > 100 + $band['priceMax']) {
                throw new AetherShareException(422, 'Worp buiten het bereik van dit Fiscaliteit-niveau.');
            }
            $stmt = $pdo->prepare('SELECT o.id, o.statusCode, o.purchasedShareCount,
                                         (SELECT COUNT(*) FROM tblShareOfferRecipient r
                                           WHERE r.idOffer = o.id AND r.recipientKind = \'shared\') AS sharedCount
                                    FROM tblShareOffer o WHERE o.idAssessment = :idAssessment FOR UPDATE');
            $stmt->execute(['idAssessment' => (int) $assessment['id']]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($offer && ((int) $offer['purchasedShareCount'] > 0 || (int) $offer['sharedCount'] > 0)) {
                throw new AetherShareException(409, 'Deze vondst is al gedeeld of gekocht; de oorspronkelijke worpen blijven bewaard.');
            }
            $found = $discovery <= $band['chance'];
            $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
            $tradeable = $found && (int) $company['isShareListed'] === 1
                && $stock['free'] > 0 && (float) ($assessment['shareMarketPrice'] ?? 0) > 0;
            $stmt = $pdo->prepare('UPDATE tblShareSearchAssessment
                                      SET discoveryRoll = :discovery, quantityRoll = :quantity,
                                          priceRoll = :price, wasDiscovered = :found
                                    WHERE id = :idAssessment');
            $stmt->execute(['discovery' => $discovery, 'quantity' => $quantity,
                            'price' => $price, 'found' => $found ? 1 : 0,
                            'idAssessment' => (int) $assessment['id']]);
            if ($tradeable) {
                $offered = aetherShareSearchQuantity($stock['free'], $quantity);
                $unitPrice = aetherShareSearchPrice((string) $assessment['shareMarketPrice'], $price - 100);
                if ($offer) {
                    $stmt = $pdo->prepare("UPDATE tblShareOffer
                        SET statusCode = 'open', offeredShareCount = :count, unitPrice = :price
                        WHERE id = :idOffer");
                    $stmt->execute(['count' => $offered, 'price' => $unitPrice, 'idOffer' => $offer['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tblShareOffer
                        (idCompany, idEvent, idSnapshot, idAssessment, sourceCode,
                         offeredShareCount, unitPrice)
                        VALUES (:idCompany, :idEvent, :idSnapshot, :idAssessment,
                                'search', :count, :price)");
                    $stmt->execute(['idCompany' => $companyId, 'idEvent' => (int) $assessment['idEvent'],
                                    'idSnapshot' => (int) $assessment['idSnapshot'],
                                    'idAssessment' => (int) $assessment['id'],
                                    'count' => $offered, 'price' => $unitPrice]);
                    $stmt = $pdo->prepare("INSERT INTO tblShareOfferRecipient
                        (idOffer, idCharacter, recipientKind) VALUES (:idOffer, :idCharacter, 'discoverer')");
                    $stmt->execute(['idOffer' => (int) $pdo->lastInsertId(),
                                    'idCharacter' => (int) $assessment['idCharacter']]);
                }
            } elseif ($offer && $offer['statusCode'] === 'open') {
                $stmt = $pdo->prepare("UPDATE tblShareOffer SET statusCode = 'cancelled' WHERE id = :idOffer");
                $stmt->execute(['idOffer' => $offer['id']]);
            }
            $value = aetherNormalizeDecimal((string) $company['companyValue']);
            aetherShareInsertValueHistory($pdo, $companyId, null, (int) $user['id'],
                'share_search_rolls_corrected', $value, $value,
                json_encode(['idAssessment' => (int) $assessment['id'],
                             'before' => ['discovery' => (int) $assessment['discoveryRoll'],
                                          'quantity' => $assessment['quantityRoll'],
                                          'price' => $assessment['priceRoll']],
                             'after' => ['discovery' => $discovery, 'quantity' => $quantity,
                                         'price' => $price],
                             'requestKey' => $key], JSON_THROW_ON_ERROR));
            return ['idAssessment' => (int) $assessment['id'], 'wasDiscovered' => $found];
        },
        static function () use ($user): void {
            if (!aetherCanManageShareCompany($user)) {
                throw new AetherShareException(403, 'Geen rechten voor deze correctie.');
            }
        });
}
