<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/accessControl.php';

/** Company management has always been reserved for directors and administrators.
 * Personnel, character or share links do not grant management access.
 */
function requirePrivilegedCompanyAccess(PDO $pdo, bool $requireCsrf = false): array
{
    $user = aetherRequireAuthenticatedUser($pdo);
    if (!aetherIsPrivilegedRole($user['role'])) {
        aetherJsonError(403, 'Je hebt geen rechten om bedrijven te beheren.');
    }
    if ($requireCsrf) {
        aetherRequireCsrfToken();
    }
    return $user;
}
