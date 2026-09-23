<?php
declare(strict_types=1);

/** @return list<array<string, mixed>> */
function aetherFetchEventListForUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.id, e.type, e.title, e.description, e.dateStart, e.dateEnd, e.venue, e.ep,
                CASE WHEN leu.idUser IS NULL THEN 0 ELSE 1 END AS participation
           FROM tblEvent AS e
           LEFT JOIN tblLinkEventUser AS leu
             ON leu.idEvent = e.id
            AND leu.idUser = :idUser
          ORDER BY e.dateStart'
    );
    $stmt->execute(['idUser' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function aetherFetchEvent(PDO $pdo, int $eventId, bool $forUpdate = false): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, type, title, description, dateStart, dateEnd, venue, ep
           FROM tblEvent
          WHERE id = :id' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute(['id' => $eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherEventUserExists(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM tblUser WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    return $stmt->fetchColumn() !== false;
}

/** @param array<string, mixed> $event */
function aetherInsertEvent(PDO $pdo, array $event): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblEvent (type, title, description, dateStart, dateEnd, venue, ep)
         VALUES (:type, :title, :description, :dateStart, :dateEnd, :venue, :ep)'
    );
    $stmt->execute([
        'type' => $event['type'], 'title' => $event['title'], 'description' => $event['description'],
        'dateStart' => $event['dateStart'], 'dateEnd' => $event['dateEnd'],
        'venue' => $event['venue'], 'ep' => $event['ep'],
    ]);
    return (int) $pdo->lastInsertId();
}

/** @param array<string, mixed> $changes */
function aetherUpdateEventRecord(PDO $pdo, int $eventId, array $changes): int
{
    $columns = [
        'type' => 'type', 'title' => 'title', 'description' => 'description',
        'dateStart' => 'dateStart', 'dateEnd' => 'dateEnd', 'venue' => 'venue', 'ep' => 'ep',
    ];
    $sets = [];
    $params = ['id' => $eventId];
    foreach ($columns as $field => $column) {
        if (!array_key_exists($field, $changes)) continue;
        $sets[] = "`{$column}` = :{$field}";
        $params[$field] = $changes[$field];
    }
    if ($sets === []) throw new LogicException('Geen eventvelden om bij te werken.');
    $stmt = $pdo->prepare('UPDATE tblEvent SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);
    return $stmt->rowCount();
}

function aetherFetchEventParticipationId(PDO $pdo, int $eventId, int $userId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM tblLinkEventUser WHERE idEvent = :idEvent AND idUser = :idUser'
    );
    $stmt->execute(['idEvent' => $eventId, 'idUser' => $userId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
}

function aetherAddEventParticipation(PDO $pdo, int $eventId, int $userId): int
{
    $existingId = aetherFetchEventParticipationId($pdo, $eventId, $userId);
    if ($existingId !== null) return $existingId;

    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkEventUser (idEvent, idUser) VALUES (:idEvent, :idUser)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmt->execute(['idEvent' => $eventId, 'idUser' => $userId]);
    return (int) $pdo->lastInsertId();
}

function aetherRemoveEventParticipation(PDO $pdo, int $eventId, int $userId): int
{
    $stmt = $pdo->prepare(
        'DELETE FROM tblLinkEventUser WHERE idEvent = :idEvent AND idUser = :idUser'
    );
    $stmt->execute(['idEvent' => $eventId, 'idUser' => $userId]);
    return $stmt->rowCount();
}

function aetherEventKnowledgeTargetExists(PDO $pdo, int $eventId, int $characterId): bool
{
    $stmt = $pdo->prepare(
        'SELECT d.id
           FROM tblCharacterDiary AS d
           JOIN tblEvent AS e ON e.id = d.idEvent
           JOIN tblCharacter AS c ON c.id = d.idCharacter
          WHERE d.idEvent = :idEvent AND d.idCharacter = :idCharacter'
    );
    $stmt->execute(['idEvent' => $eventId, 'idCharacter' => $characterId]);
    return $stmt->fetchColumn() !== false;
}

