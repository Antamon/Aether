<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchCharacterSkillLink(PDO $pdo, int $characterId, int $skillId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT level
           FROM tblLinkCharacterSkill
          WHERE idCharacter = ? AND idSkill = ?'
    );
    $stmt->execute([$characterId, $skillId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function aetherFetchCharacterSkillPointRecord(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, type, idUser, experienceToTrait, physicalHealth, mentalHealth
           FROM tblCharacter
          WHERE id = ?'
    );
    $stmt->execute([$characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function aetherDeleteCharacterDisciplineSpecialisations(PDO $pdo, int $characterId, int $skillId): void
{
    $stmt = $pdo->prepare(
        "DELETE cs
           FROM tblCharacterSpecialisation cs
           JOIN tblSkillSpecialisation ss ON ss.id = cs.idSkillSpecialisation
          WHERE cs.idCharacter = ?
            AND cs.idSkill = ?
            AND ss.kind = 'discipline'"
    );
    $stmt->execute([$characterId, $skillId]);
}

function aetherDeleteCharacterSkillSpecialisations(PDO $pdo, int $characterId, int $skillId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM tblCharacterSpecialisation
          WHERE idCharacter = ? AND idSkill = ?'
    );
    $stmt->execute([$characterId, $skillId]);
}

function aetherDeleteCharacterSkillLink(PDO $pdo, int $characterId, int $skillId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM tblLinkCharacterSkill
          WHERE idCharacter = ? AND idSkill = ?'
    );
    $stmt->execute([$characterId, $skillId]);
}

function aetherUpdateCharacterSkillLevel(PDO $pdo, int $characterId, int $skillId, int $level): void
{
    $stmt = $pdo->prepare(
        'UPDATE tblLinkCharacterSkill
            SET level = ?
          WHERE idCharacter = ? AND idSkill = ?'
    );
    $stmt->execute([$level, $characterId, $skillId]);
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterSkillFeedbackRows(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.description,
                s.beginner, s.professional, s.master,
                cs.level
           FROM tblSkill s
           JOIN tblLinkCharacterSkill cs ON cs.idSkill = s.id
          WHERE cs.idCharacter = ?
          ORDER BY s.name'
    );
    $stmt->execute([$characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function aetherFetchSkillSpecialisationForSkill(PDO $pdo, int $specialisationId, int $skillId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, kind
           FROM tblSkillSpecialisation
          WHERE id = ? AND idSkill = ?
          LIMIT 1'
    );
    $stmt->execute([$specialisationId, $skillId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function aetherFindSkillSpecialisationByName(PDO $pdo, int $skillId, string $name): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, kind
           FROM tblSkillSpecialisation
          WHERE idSkill = ? AND LOWER(name) = LOWER(?)
          LIMIT 1'
    );
    $stmt->execute([$skillId, $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function aetherCharacterSkillSpecialisationIsLinked(
    PDO $pdo,
    int $characterId,
    int $skillId,
    int $specialisationId
): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
           FROM tblCharacterSpecialisation
          WHERE idCharacter = ?
            AND idSkill = ?
            AND idSkillSpecialisation = ?'
    );
    $stmt->execute([$characterId, $skillId, $specialisationId]);

    return (int) $stmt->fetchColumn() > 0;
}

function aetherInsertSkillSpecialisationDefinition(PDO $pdo, int $skillId, string $name, string $kind): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblSkillSpecialisation (idSkill, name, kind)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$skillId, $name, $kind]);

    return (int) $pdo->lastInsertId();
}

function aetherInsertCharacterSkillSpecialisation(
    PDO $pdo,
    int $characterId,
    int $skillId,
    int $specialisationId
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacterSpecialisation (idCharacter, idSkill, idSkillSpecialisation)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $stmt->execute([$characterId, $skillId, $specialisationId]);
}

function aetherInsertCharacterSkillLink(PDO $pdo, int $characterId, int $skillId, int $level): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
         VALUES (:idCharacter, :idSkill, :level)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $stmt->execute([
        'idCharacter' => $characterId,
        'idSkill' => $skillId,
        'level' => $level,
    ]);
}
