<?php
declare(strict_types=1);

require_once __DIR__ . '/eventAccess.php';
require_once __DIR__ . '/eventRepository.php';
require_once __DIR__ . '/../characters/gossipKnowledgeUtils.php';

function aetherAuthorizeEventKnowledgeTargetReplay(PDO $pdo, array $currentUser, int $eventId, int $characterId): void
{
    aetherRequireEventManager($currentUser);
    if (!aetherEventKnowledgeTargetExists($pdo, $eventId, $characterId)) {
        throw new AetherEventException(404, 'Eventgossip voor dit personage niet gevonden.');
    }
}

/** @return array{ok: true, idEvent: int, idCharacter: int, isVisible: bool} */
function aetherSaveEventKnowledgeVisibility(PDO $pdo, array $currentUser, array $input): array
{
    $eventId = (int) $input['idEvent'];
    $characterId = (int) $input['idCharacter'];
    aetherAuthorizeEventKnowledgeTargetReplay($pdo, $currentUser, $eventId, $characterId);
    setGossipVisibilityState($pdo, $eventId, $characterId, (bool) $input['isVisible'], (int) $currentUser['id']);
    return ['ok' => true, 'idEvent' => $eventId, 'idCharacter' => $characterId, 'isVisible' => (bool) $input['isVisible']];
}

function aetherAuthorizeKnowledgeUnlockReplay(PDO $pdo, array $currentUser, array $input): void
{
    aetherRequireEventManager($currentUser);
    if (aetherFetchEvent($pdo, (int) $input['idEvent']) === null) {
        throw new AetherEventException(404, 'Event niet gevonden.');
    }
}

/** @return array{ok: true, idEvent: int, idSourceCharacter: int, idViewerCharacter: int, attemptCount: int} */
function aetherDeleteEventKnowledgeUnlock(PDO $pdo, array $currentUser, array $input): array
{
    aetherAuthorizeKnowledgeUnlockReplay($pdo, $currentUser, $input);
    $eventId = (int) $input['idEvent'];
    $sourceId = (int) $input['idSourceCharacter'];
    $viewerId = (int) $input['idViewerCharacter'];
    lockCharacterEventGossipAttemptCount($pdo, $viewerId, $eventId);
    if (lockExistingGossipUnlockRow($pdo, $viewerId, $eventId, $sourceId) === null) {
        throw new AetherEventException(404, 'Wereldwijsontdekking niet gevonden.');
    }
    deleteGossipUnlockState($pdo, $viewerId, $eventId, $sourceId);
    $attemptCount = decrementCharacterEventGossipAttemptCount($pdo, $viewerId, $eventId);
    return [
        'ok' => true,
        'idEvent' => $eventId,
        'idSourceCharacter' => $sourceId,
        'idViewerCharacter' => $viewerId,
        'attemptCount' => $attemptCount,
    ];
}

