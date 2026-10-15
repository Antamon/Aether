<?php
declare(strict_types=1);

/** Prefer an ongoing event, otherwise the nearest upcoming event (Belgian dates). */
function aetherShareChooseMarketEvent(array $events, ?string $today = null): ?array
{
    $today ??= (new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
    $current = [];
    $upcoming = [];
    foreach ($events as $event) {
        $start = substr((string) ($event['dateStart'] ?? ''), 0, 10);
        $end = substr((string) (($event['dateEnd'] ?? null) ?: $start), 0, 10);
        if ($start === '' || $end < $today) continue;
        if ($start <= $today) $current[] = $event;
        else $upcoming[] = $event;
    }
    usort($current, static fn(array $a, array $b): int =>
        [$b['dateStart'], (int) $b['id']] <=> [$a['dateStart'], (int) $a['id']]);
    usort($upcoming, static fn(array $a, array $b): int =>
        [$a['dateStart'], (int) $a['id']] <=> [$b['dateStart'], (int) $b['id']]);
    return $current[0] ?? $upcoming[0] ?? null;
}

function aetherShareDefaultMarketEvent(PDO $pdo): ?array
{
    return aetherShareChooseMarketEvent($pdo->query('SELECT id, title, dateStart, dateEnd FROM tblEvent')
        ->fetchAll(PDO::FETCH_ASSOC));
}

function aetherShareSelectedMarketEvent(PDO $pdo, int $eventId): ?array
{
    if ($eventId <= 0) return aetherShareDefaultMarketEvent($pdo);
    $stmt = $pdo->prepare('SELECT id, title, dateStart, dateEnd FROM tblEvent WHERE id = :idEvent');
    $stmt->execute(['idEvent' => $eventId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aetherShareCanUseMarketEvent(array $user, int $eventId, ?array $current): bool
{
    return $eventId > 0 && (aetherCanManageShareCompany($user)
        || $eventId === (int) ($current['id'] ?? 0));
}

function aetherShareRequireMarketEvent(PDO $pdo, int $eventId, array $user): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Eventcontrole vereist een transactie.');
    $stmt = $pdo->prepare('SELECT id FROM tblEvent WHERE id = :idEvent FOR UPDATE');
    $stmt->execute(['idEvent' => $eventId]);
    if ($stmt->fetchColumn() === false) {
        throw new AetherShareException(409, 'Dit event is niet meer koopbaar.', 'shares.event_closed');
    }
    if (!aetherShareCanUseMarketEvent($user, $eventId, aetherShareDefaultMarketEvent($pdo))) {
        throw new AetherShareException(403, 'Deelnemers kunnen alleen aandelen kopen of zoeken bij het huidige of eerstvolgende event.', 'shares.event_closed');
    }
}
