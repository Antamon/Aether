<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sessionUserBootstrap.php';
require_once __DIR__ . '/../shared/session.php';
require_once __DIR__ . '/../shared/cache.php';
require_once __DIR__ . '/../shared/response.php';

// Authenticated API responses can contain user-bound data and must not be cached.
aetherSendNoStoreHeaders(true);

const AETHER_ROLE_PARTICIPANT = 'participant';
const AETHER_ROLE_DIRECTOR = 'director';
const AETHER_ROLE_ADMINISTRATOR = 'administrator';

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

    // A WordPress logout or account switch must invalidate an old Aether session.
    // Also verify older sessions without a source marker when WordPress is present.
    $wordpressSource = ($_SESSION['user']['source'] ?? null) === 'wordpress';
    $wordpressAvailable = function_exists('aetherLoadWordPressIfAvailable') && aetherLoadWordPressIfAvailable();
    if (!empty($_SESSION['user']['id']) && ($wordpressSource || $wordpressAvailable)) {
        if (!$wordpressAvailable || !is_user_logged_in()
            || (int) (wp_get_current_user()->ID ?? 0) !== (int) ($_SESSION['user']['id'] ?? 0)) {
            unset($_SESSION['user'], $_SESSION['aetherCsrfToken']);
        }
    }

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
