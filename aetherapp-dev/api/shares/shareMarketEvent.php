<?php
declare(strict_types=1);

/** The latest event is the default when no event has been selected in the UI. */
function aetherShareDefaultMarketEvent(PDO $pdo): ?array
{
    $stmt = $pdo->query('SELECT id, title, dateStart, dateEnd FROM tblEvent
                         ORDER BY dateStart DESC, id DESC LIMIT 1');
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aetherShareSelectedMarketEvent(PDO $pdo, int $eventId): ?array
{
    if ($eventId <= 0) return aetherShareDefaultMarketEvent($pdo);
    $stmt = $pdo->prepare('SELECT id, title, dateStart, dateEnd FROM tblEvent WHERE id = :idEvent');
    $stmt->execute(['idEvent' => $eventId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aetherShareRequireMarketEvent(PDO $pdo, int $eventId): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Eventcontrole vereist een transactie.');
    $stmt = $pdo->prepare('SELECT id FROM tblEvent WHERE id = :idEvent FOR UPDATE');
    $stmt->execute(['idEvent' => $eventId]);
    if ($stmt->fetchColumn() === false) {
        throw new AetherShareException(409, 'Dit event is niet meer koopbaar.', 'shares.event_closed');
    }
}
