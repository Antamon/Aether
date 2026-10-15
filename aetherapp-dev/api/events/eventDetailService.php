<?php
declare(strict_types=1);

require_once __DIR__ . '/eventService.php';
require_once __DIR__ . '/eventMediaService.php';
require_once __DIR__ . '/../characters/characterMediaUtils.php';

function aetherEventGossipIsFilled(?string $value): bool
{
    $plain = html_entity_decode(strip_tags($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_match('/[^\s\x{00A0}\x{200B}]/u', $plain) === 1;
}

function aetherGetEventDetails(PDO $pdo, array $user, int $id): array
{
    $event = aetherFetchEvent($pdo, $id);
    if (!$event) throw new AetherEventException(404, 'Event niet gevonden.');
    $event['imageUrl'] = aetherEventImageUrl($id);
    $manager = aetherCanManageEvents($user);
    try {
        $stmt = $pdo->prepare('SELECT c.id, c.idUser, c.type, c.state, c.firstName, c.lastName,
            CASE WHEN l.idCharacter IS NULL THEN 0 ELSE 1 END AS linked
            FROM tblCharacter c LEFT JOIN tblLinkEventCharacter l
            ON l.idCharacter = c.id AND l.idEvent = :idEvent
            WHERE c.type IN (\'player\', \'extra\') ORDER BY c.lastName, c.firstName, c.id');
        $stmt->execute(['idEvent' => $id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $snapshots = [];
        $gossip = [];
        if ($manager || ($user['role'] ?? '') === 'participant') {
            $ownerScope = $manager ? '' : ' AND statusCharacter.idUser = :statusUser';
            $statusParams = ['idEvent' => $id];
            if (!$manager) $statusParams['statusUser'] = (int) $user['id'];
            $stmt = $pdo->prepare('SELECT DISTINCT s.idCharacter FROM tblCharacterEconomySnapshot s
                JOIN tblCharacter statusCharacter ON statusCharacter.id = s.idCharacter
                WHERE s.idEvent = :idEvent' . $ownerScope);
            $stmt->execute($statusParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $snapshot) $snapshots[(int) $snapshot['idCharacter']] = true;
            $stmt = $pdo->prepare('SELECT d.idCharacter, d.gossip1, d.gossip2, d.gossip3 FROM tblCharacterDiary d
                JOIN tblCharacter statusCharacter ON statusCharacter.id = d.idCharacter
                WHERE d.idEvent = :idEvent' . $ownerScope . ' ORDER BY d.id');
            $stmt->execute($statusParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $diary) {
                $gossip[(int) $diary['idCharacter']] = aetherEventGossipIsFilled($diary['gossip1'])
                    && aetherEventGossipIsFilled($diary['gossip2']) && aetherEventGossipIsFilled($diary['gossip3']);
            }
        }
        $characters = [];
        foreach ($rows as $character) {
            $canViewStatus = aetherCanViewCharacter($user, $character);
            $canLink = aetherCanLinkEventCharacter($user, $character);
            if (!$manager && !($character['linked'] && ($character['type'] === 'player' || $canViewStatus)) && !$canLink) continue;
            $character['linked'] = (bool) $character['linked'];
            $character['canOpen'] = aetherCanViewCharacter($user, $character);
            $character['canViewStatus'] = $canViewStatus;
            $character['canLink'] = $canLink;
            if ($canViewStatus) {
                $character['hasSnapshot'] = isset($snapshots[(int) $character['id']]);
                $character['gossipComplete'] = $gossip[(int) $character['id']] ?? false;
            }
            $character['portraitUrl'] = getCharacterPortraitUrl((int) $character['id']);
            unset($character['idUser'], $character['state']);
            $characters[] = $character;
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            throw new AetherEventException(503, 'Voer eerst de SQL-migratie 0014_event_character_links.sql uit.');
        }
        throw $e;
    }
    return ['event' => $event, 'characters' => $characters, 'canManage' => $manager];
}

function aetherGetEventCharacterData(PDO $pdo, array $user, int $idEvent, int $idCharacter, string $kind): array
{
    if (!in_array($kind, ['gossip', 'snapshot'], true)) throw new AetherEventException(422, 'Ongeldig gegevenstype.');
    $character = aetherFetchCharacterAccessRecord($pdo, $idCharacter);
    if (!$character || !aetherCanViewCharacter($user, $character)) throw new AetherEventException(403, 'Geen rechten voor de gegevens van dit personage.');
    $stmt = $pdo->prepare('SELECT c.firstName, c.lastName, e.title AS eventTitle
        FROM tblLinkEventCharacter l JOIN tblCharacter c ON c.id = l.idCharacter
        JOIN tblEvent e ON e.id = l.idEvent WHERE l.idEvent = :idEvent AND l.idCharacter = :idCharacter');
    $stmt->execute(['idEvent' => $idEvent, 'idCharacter' => $idCharacter]);
    $identity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$identity) throw new AetherEventException(404, 'Personage is niet aan dit event gekoppeld.');
    $fields = $kind === 'gossip' ? 'gossip1, gossip2, gossip3'
        : 'amount, transactionDate, securitiesBalanceSnapshot, securitiesManagerType,
            securitiesRiskProfile, securitiesManagerSkillLevel, securitiesBasePercentage,
            securitiesVariationLimitPercentage, securitiesVariationPercentage,
            securitiesReturnPercentage, securitiesReturnAmount, securitiesStatus';
    $table = $kind === 'gossip' ? 'tblCharacterDiary' : 'tblCharacterEconomySnapshot';
    $stmt = $pdo->prepare("SELECT $fields FROM $table WHERE idEvent = :idEvent AND idCharacter = :idCharacter ORDER BY id DESC LIMIT 1");
    $stmt->execute(['idEvent' => $idEvent, 'idCharacter' => $idCharacter]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$details && $kind === 'snapshot') return ['identity' => $identity, 'details' => null,
        'message' => 'Financiële toestand nog niet aangepast voor "' . $identity['eventTitle'] . '".'];
    if (!$details) throw new AetherEventException(404, 'Deze gegevens zijn niet meer beschikbaar.');
    return ['identity' => $identity, 'details' => $details];
}

function aetherSetEventCharacter(PDO $pdo, array $user, int $idEvent, int $idCharacter, bool $linked): void
{
    $pdo->beginTransaction();
    try {
        if (!aetherFetchEvent($pdo, $idEvent, true)) throw new AetherEventException(404, 'Event niet gevonden.');
        $stmt = $pdo->prepare('SELECT id, idUser, type, state, `class` FROM tblCharacter WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $idCharacter]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$character || !in_array($character['type'], ['player', 'extra'], true)) {
            throw new AetherEventException(404, 'Personage niet gevonden.');
        }
        if (!aetherCanLinkEventCharacter($user, $character) || (!$linked && !aetherCanManageEvents($user))) {
            throw new AetherEventException(403, 'Je kunt alleen je eigen spelerspersonages buiten Draft toevoegen.');
        }
        $stmt = $pdo->prepare($linked
            ? 'INSERT INTO tblLinkEventCharacter (idEvent, idCharacter) VALUES (:idEvent, :idCharacter)
                ON DUPLICATE KEY UPDATE idCharacter = VALUES(idCharacter)'
            : 'DELETE FROM tblLinkEventCharacter WHERE idEvent = :idEvent AND idCharacter = :idCharacter');
        $stmt->execute(['idEvent' => $idEvent, 'idCharacter' => $idCharacter]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
