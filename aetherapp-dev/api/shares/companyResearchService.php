<?php
declare(strict_types=1);

require_once __DIR__ . '/shareService.php';
require_once __DIR__ . '/../companies/companyQuarterService.php';
require_once __DIR__ . '/../companies/companyRichText.php';

function aetherCompanyResearchAvailable(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT 1 FROM tblSchemaMigration WHERE migrationCode = '0022_company_research' LIMIT 1");
    return $stmt->fetchColumn() !== false;
}

function aetherCompanyResearchChance(int $level, int $visibility): int
{
    if ($level < 1 || $level > 4 || $visibility < 1 || $visibility > 5) throw new InvalidArgumentException('Ongeldig onderzoeksniveau.');
    return max(0, min(100, 60 + 20 * ($level - $visibility)));
}

function aetherCompanyResearchLevel(PDO $pdo, int $characterId): int
{
    $stmt = $pdo->prepare('SELECT level FROM tblLinkCharacterSkill WHERE idCharacter = :id AND idSkill = 6');
    $stmt->execute(['id' => $characterId]);
    $level = (int) $stmt->fetchColumn();
    if ($level < 1) throw new AetherShareException(403, 'Dit personage heeft Economie nodig.');
    $stmt = $pdo->prepare('SELECT 1 FROM tblCharacterSpecialisation WHERE idCharacter = :id AND idSkill = 6 AND idSkillSpecialisation = 73 LIMIT 1');
    $stmt->execute(['id' => $characterId]);
    return min(3, $level) + ($stmt->fetchColumn() !== false ? 1 : 0);
}

function aetherCompanyResearchSnapshot(PDO $pdo, int $companyId, int $eventId): array
{
    $stmt = $pdo->prepare('SELECT s.*, e.dateStart, e.dateEnd, e.title AS eventTitle FROM tblCompanySnapshot s
        JOIN tblEvent e ON e.id = s.idEvent WHERE s.idCompany = :company AND s.idEvent = :event');
    $stmt->execute(['company' => $companyId, 'event' => $eventId]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$snapshot) throw new AetherShareException(404, 'Geen bedrijfssnapshot voor dit event.');
    return $snapshot;
}

/** Only events that have happened by the selected snapshot can be researched. */
function aetherCompanyResearchEvents(PDO $pdo, array $snapshot, int $characterId): array
{
    $last = aetherCompanyQuarterForOutgameDate($snapshot['dateEnd'] ?: $snapshot['dateStart']);
    $stmt = $pdo->prepare('SELECT e.id, e.description, e.publicity, q.quarterYear AS year, q.quarterNumber AS quarter
        FROM tblCompanyQuarterEvent e JOIN tblCompanyQuarter q ON q.id = e.idCompanyQuarter
        WHERE q.idCompany = :company AND (q.quarterYear * 4 + q.quarterNumber - 1) <= CAST(:last AS SIGNED)
        ORDER BY q.quarterYear, q.quarterNumber, e.id');
    $stmt->execute(['company' => $snapshot['idCompany'], 'last' => $last]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT k.*, event.dateStart FROM tblCharacterCompanyResearch k
        JOIN tblCompanySnapshot s ON s.id = k.idSnapshot JOIN tblEvent event ON event.id = s.idEvent
        WHERE k.idCharacter = :character AND s.idCompany = :company
          AND (event.dateStart < :date OR (event.dateStart = :sameDate AND s.id <= :snapshot))
        ORDER BY event.dateStart DESC, s.id DESC');
    $stmt->execute(['character' => $characterId, 'company' => $snapshot['idCompany'], 'date' => $snapshot['dateStart'],
        'sameDate' => $snapshot['dateStart'], 'snapshot' => $snapshot['id']]);
    $knowledge = []; $current = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (int) $row['idCompanyQuarterEvent'];
        if ((int) $row['idSnapshot'] === (int) $snapshot['id']) $current[$key] = $row;
        if (((bool) $row['isKnown'] && $row['discoveryRoll'] !== null) || (bool) $row['isManual']) $knowledge[$key] ??= $row;
    }
    foreach ($events as &$event) {
        $known = $knowledge[(int) $event['id']] ?? null;
        $event['isKnown'] = $known !== null && (bool) $known['isKnown'];
        $event['assessedHere'] = isset($current[(int) $event['id']]);
        $event['knowledgeSnapshotId'] = $known === null ? null : (int) $known['idSnapshot'];
        $event['updatedAt'] = [$known['updatedAt'] ?? null, $current[(int) $event['id']]['updatedAt'] ?? null];
    }
    unset($event);
    return $events;
}

/** Called inside the market-search transaction, after company and character locks. */
function aetherCompanyResearchInvestigate(PDO $pdo, int $characterId, int $companyId, int $eventId): void
{
    if (!aetherCompanyResearchAvailable($pdo)) return;
    $snapshot = aetherCompanyResearchSnapshot($pdo, $companyId, $eventId);
    $level = aetherCompanyResearchLevel($pdo, $characterId);
    $insert = $pdo->prepare('INSERT IGNORE INTO tblCharacterCompanyResearch
        (idCharacter, idSnapshot, idCompanyQuarterEvent, effectiveSkillLevel, chancePercent, discoveryRoll, isKnown)
        VALUES (:character, :snapshot, :event, :level, :chance, :roll, :known)');
    foreach (aetherCompanyResearchEvents($pdo, $snapshot, $characterId) as $event) {
        if ($event['assessedHere']) continue;
        $chance = aetherCompanyResearchChance($level, (int) $event['publicity']);
        $roll = $event['isKnown'] ? null : random_int(1, 100);
        $insert->execute(['character' => $characterId, 'snapshot' => $snapshot['id'], 'event' => $event['id'],
            'level' => $level, 'chance' => $chance, 'roll' => $roll, 'known' => (int) ($event['isKnown'] || $roll <= $chance)]);
    }
}

/** A successful earlier discovery remains known even if a later snapshot had a failed attempt. */
function aetherCompanyResearchRevision(array $events): string
{
    return hash('sha256', json_encode($events, JSON_THROW_ON_ERROR));
}

function aetherCompanyResearchView(PDO $pdo, array $user, int $characterId, int $companyId, int $eventId): array
{
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if (!$character || !aetherCanViewCharacter($user, $character)) throw new AetherShareException(403, 'Geen toegang tot dit personage.');
    $manager = aetherCanManageShareCompany($user);
    if (!$manager) aetherCompanyResearchLevel($pdo, $characterId);
    $snapshot = aetherCompanyResearchSnapshot($pdo, $companyId, $eventId);
    $stmt = $pdo->prepare('SELECT id, companyName, foundationDate, description, isShareListed FROM tblCompany WHERE id = :id');
    $stmt->execute(['id' => $companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $company['description'] = aetherSanitizeCompanyDescription((string) $company['description']);
    $company['logoUrl'] = getCompanyLogoUrl($companyId);
    foreach (['companyValue', 'issuedShareCount', 'shareMarketPrice'] as $field) $company[$field] = $snapshot[$field];
    $company['companyTypeLabel'] = getCompanyTypeByValue($snapshot['companyValue'])['label'];
    $last = aetherCompanyQuarterForOutgameDate($snapshot['dateEnd'] ?: $snapshot['dateStart']);
    $quarters = [];
    foreach (aetherCompanyQuarterTimeline($pdo, $companyId) as $quarter) {
        if (aetherCompanyQuarterIndex((int) $quarter['year'], (int) $quarter['quarter']) > $last) break;
        $quarters[] = ['year' => $quarter['year'], 'quarter' => $quarter['quarter'], 'companyValue' => $quarter['companyValue']];
    }
    $available = aetherCompanyResearchAvailable($pdo);
    $allEvents = $available ? aetherCompanyResearchEvents($pdo, $snapshot, $characterId) : [];
    $revision = aetherCompanyResearchRevision($allEvents);
    $visible = $manager ? $allEvents : array_values(array_filter($allEvents, static fn(array $event): bool => $event['isKnown']));
    if (!$manager) foreach ($visible as &$event) unset($event['publicity'], $event['updatedAt'], $event['assessedHere'], $event['knowledgeSnapshotId']);
    unset($event);
    return ['company' => $company, 'idSnapshot' => (int) $snapshot['id'], 'eventTitle' => $snapshot['eventTitle'],
        'quarters' => $quarters, 'events' => $visible, 'canEdit' => $manager && $available, 'canViewVisibility' => $manager,
        'revision' => $revision, 'researchAvailable' => $available];
}

function aetherCompanyResearchSave(PDO $pdo, array $user, array $input, string $key): array
{
    aetherShareRequireManager($user);
    return aetherShareMutation($pdo, $user, 'research.update', $key, $input,
        static function (array $companies) use ($pdo, $user, $input): array {
            aetherShareRequireManager($user);
            if (!aetherCompanyResearchAvailable($pdo)) throw new AetherShareException(503, 'Voer eerst migratie 0022 uit.');
            $id = (int) $input['idCharacter'];
            $characters = aetherFinanceLockCharacters($pdo, [$id]);
            aetherShareRequireCharacter($characters, $id, $user, false);
            $snapshot = aetherCompanyResearchSnapshot($pdo, (int) $input['idCompany'], (int) $input['idEvent']);
            $events = aetherCompanyResearchEvents($pdo, $snapshot, $id);
            if (!hash_equals(aetherCompanyResearchRevision($events), $input['revision'])) throw new AetherShareException(409, 'De gebeurtenissen zijn intussen gewijzigd. Heropen de bedrijfsweergave.');
            $selected = $input['knownEventIds'];
            $allowed = array_map(static fn(array $event): int => (int) $event['id'], $events);
            if (array_diff($selected, $allowed)) throw new AetherShareException(422, 'Onbekende bedrijfsgebeurtenis.');
            $stmt = $pdo->prepare('INSERT INTO tblCharacterCompanyResearch
                (idCharacter, idSnapshot, idCompanyQuarterEvent, effectiveSkillLevel, chancePercent, discoveryRoll, isKnown, isManual)
                VALUES (:character, :snapshot, :event, 1, 0, NULL, :known, 1)
                ON DUPLICATE KEY UPDATE isKnown = VALUES(isKnown), isManual = 1');
            foreach ($events as $event) {
                $known = in_array((int) $event['id'], $selected, true);
                if ($known === (bool) $event['isKnown']) continue;
                $stmt->execute(['character' => $id, 'snapshot' => $snapshot['id'], 'event' => $event['id'], 'known' => (int) $known]);
            }
            return ['status' => 'ok'];
        }, static function (array $companies) use ($user): void { aetherShareRequireManager($user); });
}
