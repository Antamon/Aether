<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

function getCharacterPortraitDirectory(): string
{
    return dirname(__DIR__, 2) . '/img/portret';
}

function getCharacterPortraitAbsolutePath(int $characterId): string
{
    return getCharacterPortraitDirectory() . '/' . $characterId . '.png';
}

function getCharacterPortraitPublicPath(int $characterId): string
{
    return 'img/portret/' . $characterId . '.png';
}

function getCharacterPortraitUrl(int $characterId): ?string
{
    $portraitPath = getCharacterPortraitAbsolutePath($characterId);
    if (!is_file($portraitPath)) {
        return null;
    }

    return getCharacterPortraitPublicPath($characterId) . '?v=' . filemtime($portraitPath);
}

function canManageCharacterPortrait(array $character, string $role, int $currentUserId): bool
{
    if (isPrivilegedUserRole($role)) {
        return true;
    }

    return $role === 'participant'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}
