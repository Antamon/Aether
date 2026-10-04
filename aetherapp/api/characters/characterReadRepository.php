<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchCharacterReadModelBase(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tblCharacter WHERE id = ?');
    $stmt->execute([$characterId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    return $character ?: null;
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterReadSkills(PDO $pdo, int $characterId, bool $includeSecret): array
{
    $stmt = $pdo->prepare(
        "SELECT
            lcs.idSkill AS id,
            s.name,
            s.description,
            s.beginner,
            s.professional,
            s.master,
            lcs.level
         FROM tblLinkCharacterSkill AS lcs
         JOIN tblSkill AS s
           ON s.id = lcs.idSkill
         WHERE lcs.idCharacter = ?
           AND (s.visibility = 'public' OR ? = 1)
         ORDER BY s.name"
    );
    $stmt->execute([$characterId, $includeSecret ? 1 : 0]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterReadSpecialisations(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            cs.idSkill,
            ss.id,
            ss.name,
            ss.kind
         FROM tblCharacterSpecialisation AS cs
         JOIN tblSkillSpecialisation AS ss
           ON ss.id = cs.idSkillSpecialisation
         WHERE cs.idCharacter = ?
         ORDER BY ss.name'
    );
    $stmt->execute([$characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterReadSkillTypes(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            lst.idSkill,
            st.id AS idSkillType,
            st.code,
            st.name,
            st.description
         FROM tblLinkSkillType AS lst
         JOIN tblSkillType AS st
           ON st.id = lst.idSkillType
         WHERE lst.idSkill IN (
             SELECT idSkill
             FROM tblLinkCharacterSkill
             WHERE idCharacter = ?
         )
         ORDER BY st.name'
    );
    $stmt->execute([$characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function aetherFetchCharacterParticipantName(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT firstName, lastName FROM tblUser WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user
        ? trim((string) $user['firstName'] . ' ' . (string) $user['lastName'])
        : null;
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterDiaryEntries(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        'SELECT d.*, e.title AS eventTitle, e.dateStart, e.dateEnd
           FROM tblCharacterDiary d
           JOIN tblEvent e ON e.id = d.idEvent
          WHERE d.idCharacter = :idCharacter
          ORDER BY e.dateStart DESC, e.id DESC'
    );
    $stmt->execute([':idCharacter' => $characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterDiaryAvailableEvents(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.id, e.title, e.dateStart
           FROM tblEvent e
          WHERE e.id NOT IN (
              SELECT idEvent FROM tblCharacterDiary WHERE idCharacter = :idCharacter
          )
          ORDER BY e.dateStart DESC, e.id DESC'
    );
    $stmt->execute([':idCharacter' => $characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array{section: mixed, content: mixed}> */
function aetherFetchCharacterSectionRows(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        'SELECT section, content
           FROM tblCharacterSection
          WHERE idCharacter = :idCharacter'
    );
    $stmt->execute([':idCharacter' => $characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
