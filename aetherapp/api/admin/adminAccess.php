<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/accessControl.php';

/** Authorisation for the skill editor; returns the current tblUser row. */
function aetherRequireAdminEditor(PDO $pdo, bool $administratorOnly = false): array
{
    $user = aetherRequireAuthenticatedUser($pdo);
    if ($administratorOnly ? $user['role'] !== AETHER_ROLE_ADMINISTRATOR : !aetherIsPrivilegedRole($user['role'])) {
        aetherJsonError(403, 'Je hebt geen rechten voor deze handeling.');
    }
    return $user;
}
