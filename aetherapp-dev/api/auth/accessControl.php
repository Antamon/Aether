<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sessionUserBootstrap.php';
require_once __DIR__ . '/../shared/response.php';

const AETHER_ROLE_PARTICIPANT = 'participant';
const AETHER_ROLE_DIRECTOR = 'director';
const AETHER_ROLE_ADMINISTRATOR = 'administrator';

function aetherStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function aetherIsKnownRole(string $role): bool
{
    return in_array($role, [
        AETHER_ROLE_PARTICIPANT,
        AETHER_ROLE_DIRECTOR,
        AETHER_ROLE_ADMINISTRATOR,
    ], true);
}

function aetherIsPrivilegedRole(string $role): bool
{
    return $role === AETHER_ROLE_DIRECTOR || $role === AETHER_ROLE_ADMINISTRATOR;
}

function aetherEnsureSessionIdentity(): int
{
    aetherStartSession();

    if (empty($_SESSION['user']['id'])) {
        aetherHydrateSessionUserFromWordPress();
    }

    return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
}

function aetherLoadAuthenticatedUser(PDO $pdo, bool $provisionParticipant = false): ?array
{
    $userId = aetherEnsureSessionIdentity();
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, firstName, lastName, role
           FROM tblUser
          WHERE id = :id'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user && $provisionParticipant) {
        $stmt = $pdo->prepare(
            'INSERT INTO tblUser (id, username, firstName, lastName, role)
             VALUES (:id, :username, :firstName, :lastName, :role)'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => (string) ($_SESSION['user']['username'] ?? ''),
            'firstName' => (string) ($_SESSION['user']['firstName'] ?? ''),
            'lastName' => (string) ($_SESSION['user']['lastName'] ?? ''),
            'role' => AETHER_ROLE_PARTICIPANT,
        ]);

        $user = [
            'id' => $userId,
            'username' => (string) ($_SESSION['user']['username'] ?? ''),
            'firstName' => (string) ($_SESSION['user']['firstName'] ?? ''),
            'lastName' => (string) ($_SESSION['user']['lastName'] ?? ''),
            'role' => AETHER_ROLE_PARTICIPANT,
        ];
    }

    if (!$user || !aetherIsKnownRole((string) ($user['role'] ?? ''))) {
        return null;
    }

    $user['id'] = (int) $user['id'];
    $user['role'] = (string) $user['role'];

    return $user;
}

function aetherRequireAuthenticatedUser(PDO $pdo): array
{
    try {
        $user = aetherLoadAuthenticatedUser($pdo);
    } catch (Throwable $e) {
        aetherJsonError(500, 'Server error while checking access.');
    }

    if ($user === null) {
        aetherJsonError(401, 'Not authenticated');
    }

    return $user;
}

function aetherRequirePrivilegedUser(PDO $pdo): array
{
    $user = aetherRequireAuthenticatedUser($pdo);
    if (!aetherIsPrivilegedRole($user['role'])) {
        aetherJsonError(403, 'Je hebt geen rechten voor deze handeling.');
    }

    return $user;
}

function aetherGetCsrfToken(): string
{
    aetherStartSession();
    if (empty($_SESSION['aetherCsrfToken']) || !is_string($_SESSION['aetherCsrfToken'])) {
        $_SESSION['aetherCsrfToken'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['aetherCsrfToken'];
}

function aetherCsrfTokenMatches(?string $providedToken, ?string $expectedToken): bool
{
    return is_string($providedToken)
        && is_string($expectedToken)
        && $providedToken !== ''
        && $expectedToken !== ''
        && hash_equals($expectedToken, $providedToken);
}

function aetherRequireCsrfToken(): void
{
    aetherStartSession();
    $providedToken = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        ? (string) $_SERVER['HTTP_X_CSRF_TOKEN']
        : null;
    $expectedToken = isset($_SESSION['aetherCsrfToken'])
        ? (string) $_SESSION['aetherCsrfToken']
        : null;

    if (!aetherCsrfTokenMatches($providedToken, $expectedToken)) {
        aetherJsonError(403, 'Ongeldig of ontbrekend CSRF-token. Vernieuw de pagina en probeer opnieuw.');
    }
}

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
