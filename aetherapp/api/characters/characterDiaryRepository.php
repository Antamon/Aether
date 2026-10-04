<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchCharacterDiaryEntry(PDO $pdo, int $characterId, int $diaryId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, idCharacter, idEvent, goals, achievements, gossip1, gossip2, gossip3
           FROM tblCharacterDiary
          WHERE id = :idDiary AND idCharacter = :idCharacter'
    );
    $stmt->execute(['idDiary' => $diaryId, 'idCharacter' => $characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherCharacterDiaryEventExists(PDO $pdo, int $eventId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM tblEvent WHERE id = :idEvent');
    $stmt->execute(['idEvent' => $eventId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherCharacterDiaryExistsForEvent(PDO $pdo, int $characterId, int $eventId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM tblCharacterDiary WHERE idCharacter = :idCharacter AND idEvent = :idEvent');
    $stmt->execute(['idCharacter' => $characterId, 'idEvent' => $eventId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

/** @param array<string, string> $values */
function aetherUpdateCharacterDiaryEntry(PDO $pdo, int $diaryId, int $characterId, int $userId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE tblCharacterDiary
            SET goals = :goals, achievements = :achievements,
                gossip1 = :gossip1, gossip2 = :gossip2, gossip3 = :gossip3,
                updatedAt = NOW(), updatedBy = :updatedBy
          WHERE id = :idDiary AND idCharacter = :idCharacter'
    );
    $stmt->execute([
        'goals' => $values['goals'], 'achievements' => $values['achievements'],
        'gossip1' => $values['gossip1'], 'gossip2' => $values['gossip2'], 'gossip3' => $values['gossip3'],
        'updatedBy' => $userId, 'idDiary' => $diaryId, 'idCharacter' => $characterId,
    ]);
}

/** @param array<string, string> $values */
function aetherInsertCharacterDiaryEntry(PDO $pdo, int $characterId, int $eventId, int $userId, array $values): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacterDiary
            (idCharacter, idEvent, goals, achievements, gossip1, gossip2, gossip3, createdAt, createdBy, updatedAt, updatedBy)
         VALUES
            (:idCharacter, :idEvent, :goals, :achievements, :gossip1, :gossip2, :gossip3, NOW(), :createdBy, NOW(), :updatedBy)'
    );
    $stmt->execute([
        'idCharacter' => $characterId, 'idEvent' => $eventId,
        'goals' => $values['goals'], 'achievements' => $values['achievements'],
        'gossip1' => $values['gossip1'], 'gossip2' => $values['gossip2'], 'gossip3' => $values['gossip3'],
        'createdBy' => $userId, 'updatedBy' => $userId,
    ]);
    return (int) $pdo->lastInsertId();
}
