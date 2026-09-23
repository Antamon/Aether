<?php
declare(strict_types=1);

require_once __DIR__ . '/eventAccess.php';
require_once __DIR__ . '/eventRepository.php';

function aetherValidateEventDateOrder(string $dateStart, string $dateEnd): void
{
    if ($dateEnd < $dateStart) {
        throw new AetherEventException(422, 'De einddatum mag niet voor de begindatum liggen.');
    }
}

/** @return list<array<string, mixed>> */
function aetherGetEventList(PDO $pdo, array $currentUser, int $requestedUserId): array
{
    $userId = $requestedUserId > 0 ? $requestedUserId : (int) $currentUser['id'];
    if (!aetherCanReadEventParticipationForUser($currentUser, $userId)) {
        throw new AetherEventException(403, 'Je hebt geen rechten om deelnames van deze gebruiker te bekijken.');
    }
    if (!aetherEventUserExists($pdo, $userId)) {
        throw new AetherEventException(404, 'Gebruiker niet gevonden.');
    }

    $events = aetherFetchEventListForUser($pdo, $userId);
    foreach ($events as &$event) {
        $event['participation'] = (bool) $event['participation'];
        if ($event['ep'] !== null) $event['ep'] = (int) $event['ep'];
    }
    unset($event);
    return $events;
}

/** @param array<string, mixed> $input */
function aetherCreateEvent(PDO $pdo, array $currentUser, array $input): int
{
    aetherRequireEventManager($currentUser);
    aetherValidateEventDateOrder((string) $input['dateStart'], (string) $input['dateEnd']);
    return aetherInsertEvent($pdo, $input);
}

/** @param array<string, mixed> $input */
function aetherUpdateEvent(PDO $pdo, array $currentUser, array $input): int
{
    aetherRequireEventManager($currentUser);
    $eventId = (int) $input['id'];
    $changes = $input;
    unset($changes['id']);
    if ($changes === []) throw new AetherEventException(400, 'Geen velden om bij te werken.');

    $existing = aetherFetchEvent($pdo, $eventId);
    if ($existing === null) throw new AetherEventException(404, 'Event niet gevonden.');
    $dateStart = (string) ($changes['dateStart'] ?? $existing['dateStart']);
    $dateEnd = (string) ($changes['dateEnd'] ?? $existing['dateEnd']);
    aetherValidateEventDateOrder($dateStart, $dateEnd);
    return aetherUpdateEventRecord($pdo, $eventId, $changes);
}

function aetherUpdateEventParticipation(
    PDO $pdo,
    array $currentUser,
    int $eventId,
    int $requestedUserId,
    bool $participation
): int {
    aetherRequireEventManager($currentUser);
    $userId = $requestedUserId > 0 ? $requestedUserId : (int) $currentUser['id'];
    if (aetherFetchEvent($pdo, $eventId) === null) throw new AetherEventException(404, 'Event niet gevonden.');
    if (!aetherEventUserExists($pdo, $userId)) throw new AetherEventException(404, 'Gebruiker niet gevonden.');
    return $participation
        ? aetherAddEventParticipation($pdo, $eventId, $userId)
        : aetherRemoveEventParticipation($pdo, $eventId, $userId);
}
