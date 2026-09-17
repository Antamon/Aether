<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/accessControl.php';

function aetherCanViewCharacter(array $user, array $character): bool
{
    if (aetherIsPrivilegedRole((string) ($user['role'] ?? ''))) {
        return true;
    }

    return ($user['role'] ?? '') === AETHER_ROLE_PARTICIPANT
        && (int) ($character['idUser'] ?? 0) === (int) ($user['id'] ?? 0);
}

function aetherCanEditCharacter(array $user, array $character): bool
{
    if (aetherIsPrivilegedRole((string) ($user['role'] ?? ''))) {
        return true;
    }

    return ($user['role'] ?? '') === AETHER_ROLE_PARTICIPANT
        && (string) ($character['type'] ?? '') === 'player'
        && (int) ($character['idUser'] ?? 0) === (int) ($user['id'] ?? 0);
}

function aetherCanEditDraftCharacter(array $user, array $character): bool
{
    return aetherIsPrivilegedRole((string) ($user['role'] ?? ''))
        || (aetherCanEditCharacter($user, $character)
            && (string) ($character['state'] ?? '') === 'draft');
}

function aetherCanEditCharacterDiaryAchievements(array $user, array $character): bool
{
    return aetherCanEditCharacter($user, $character)
        || (($user['role'] ?? '') === AETHER_ROLE_PARTICIPANT
            && (string) ($character['type'] ?? '') === 'extra'
            && (int) ($character['idUser'] ?? 0) === (int) ($user['id'] ?? 0));
}

function aetherFetchCharacterAccessRecord(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, idUser, type, state, `class`
           FROM tblCharacter
          WHERE id = :id'
    );
    $stmt->execute(['id' => $characterId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    return $character ?: null;
}

function aetherRequireCharacterAccess(
    PDO $pdo,
    array $user,
    int $characterId,
    string $access = 'view'
): array {
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if ($character === null) {
        aetherJsonError(404, 'Personage niet gevonden.');
    }

    $allowed = match ($access) {
        'view' => aetherCanViewCharacter($user, $character),
        'edit' => aetherCanEditCharacter($user, $character),
        'edit_draft' => aetherCanEditDraftCharacter($user, $character),
        'diary_achievements' => aetherCanEditCharacterDiaryAchievements($user, $character),
        default => false,
    };

    if (!$allowed) {
        aetherJsonError(403, 'Je hebt geen rechten voor dit personage.');
    }

    return $character;
}

function aetherCanChangeCharacterAuthorityField(array $user): bool
{
    return aetherIsPrivilegedRole((string) ($user['role'] ?? ''));
}

function aetherCanManageSkill(PDO $pdo, array $user, int $skillId): bool
{
    if (aetherIsPrivilegedRole((string) ($user['role'] ?? ''))) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT visibility FROM tblSkill WHERE id = :id');
    $stmt->execute(['id' => $skillId]);
    return (string) $stmt->fetchColumn() === 'public';
}

function aetherRequireSkillAccess(PDO $pdo, array $user, int $skillId): void
{
    if (!aetherCanManageSkill($pdo, $user, $skillId)) {
        aetherJsonError(403, 'Je hebt geen rechten om deze vaardigheid te beheren.');
    }
}
