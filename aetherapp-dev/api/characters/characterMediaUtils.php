<?php
declare(strict_types=1);

require_once __DIR__ . '/characterAccess.php';

function getCharacterPortraitDirectory(): string
{
    return dirname(__DIR__, 2) . '/img/portret';
}

/** Legacy path retained so existing numeric portraits remain readable. */
function getCharacterPortraitAbsolutePath(int $characterId): string
{
    return getCharacterPortraitDirectory() . '/' . $characterId . '.png';
}

/** Legacy public path retained for compatibility with existing portraits. */
function getCharacterPortraitPublicPath(int $characterId): string
{
    return 'img/portret/' . $characterId . '.png';
}

function aetherCharacterPortraitFilenameBelongsTo(string $filename, int $characterId): bool
{
    return $filename === $characterId . '.png'
        || preg_match('/^' . preg_quote((string) $characterId, '/') . '-[a-f0-9]{32}\.png$/D', $filename) === 1;
}

/** @return list<string> */
function aetherGetCharacterPortraitPaths(int $characterId): array
{
    if ($characterId <= 0) {
        return [];
    }
    $directory = getCharacterPortraitDirectory();
    if (!is_dir($directory)) {
        return [];
    }

    $paths = [];
    foreach (new DirectoryIterator($directory) as $item) {
        if (!$item->isFile()
            || $item->isLink()
            || !aetherCharacterPortraitFilenameBelongsTo($item->getFilename(), $characterId)) {
            continue;
        }
        $paths[] = $item->getPathname();
    }

    usort($paths, static function (string $left, string $right): int {
        $leftTime = @filemtime($left) ?: 0;
        $rightTime = @filemtime($right) ?: 0;
        return $rightTime <=> $leftTime ?: strcmp(basename($right), basename($left));
    });
    return $paths;
}

function aetherGetCurrentCharacterPortraitPath(int $characterId): ?string
{
    return aetherGetCharacterPortraitPaths($characterId)[0] ?? null;
}

function aetherCreateCharacterPortraitFilename(int $characterId): string
{
    return $characterId . '-' . bin2hex(random_bytes(16)) . '.png';
}

function aetherCharacterPortraitPathForFilename(string $filename, int $characterId): string
{
    if (!aetherCharacterPortraitFilenameBelongsTo($filename, $characterId) || basename($filename) !== $filename) {
        throw new RuntimeException('Ongeldige portretbestandsnaam.');
    }
    return getCharacterPortraitDirectory() . '/' . $filename;
}

function aetherCharacterPortraitPathIsManaged(string $path, int $characterId): bool
{
    $directory = realpath(getCharacterPortraitDirectory());
    $parent = realpath(dirname($path));
    return $directory !== false
        && $parent !== false
        && hash_equals($directory, $parent)
        && aetherCharacterPortraitFilenameBelongsTo(basename($path), $characterId);
}

function getCharacterPortraitUrl(int $characterId): ?string
{
    $portraitPath = aetherGetCurrentCharacterPortraitPath($characterId);
    if ($portraitPath === null) {
        return null;
    }
    $modified = @filemtime($portraitPath);
    $version = $modified !== false ? (string) $modified : '0';
    return 'img/portret/' . rawurlencode(basename($portraitPath)) . '?v=' . $version;
}

/** Compatibility helper for existing read models. */
function canManageCharacterPortrait(array $character, string $role, int $currentUserId): bool
{
    return aetherCanManageCharacterPortrait(
        ['id' => $currentUserId, 'role' => $role],
        $character
    );
}

function aetherPortraitRename(string $source, string $target): bool
{
    $override = $GLOBALS['aetherPortraitRename'] ?? null;
    return is_callable($override) ? (bool) $override($source, $target) : @rename($source, $target);
}

function aetherPortraitUnlink(string $path): bool
{
    $override = $GLOBALS['aetherPortraitUnlink'] ?? null;
    return is_callable($override) ? (bool) $override($path) : @unlink($path);
}

function aetherPortraitUploadIsTrusted(string $path): bool
{
    $override = $GLOBALS['aetherPortraitUploadVerifier'] ?? null;
    return is_callable($override) ? (bool) $override($path) : is_uploaded_file($path);
}

function aetherEnsurePortraitDirectories(): void
{
    $directory = getCharacterPortraitDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Kon portretmap niet aanmaken.');
    }
    $quarantine = $directory . '/.quarantine';
    if (!is_dir($quarantine) && !mkdir($quarantine, 0775, true) && !is_dir($quarantine)) {
        throw new RuntimeException('Kon portretquarantaine niet aanmaken.');
    }
}

/** @param list<string> $paths @return array<string, string> original => staged */
function aetherStageCharacterPortraitPaths(array $paths, int $characterId): array
{
    if ($paths === []) {
        return [];
    }
    aetherEnsurePortraitDirectories();
    $staged = [];
    try {
        foreach ($paths as $path) {
            if (!aetherCharacterPortraitPathIsManaged($path, $characterId) || !is_file($path) || is_link($path)) {
                continue;
            }
            $target = getCharacterPortraitDirectory() . '/.quarantine/' . bin2hex(random_bytes(16)) . '.tmp';
            if (!aetherPortraitRename($path, $target)) {
                throw new RuntimeException('Kon portret niet naar quarantaine verplaatsen.');
            }
            $staged[$path] = $target;
        }
    } catch (Throwable $e) {
        aetherRestoreStagedCharacterPortraits($staged);
        throw $e;
    }
    return $staged;
}

/** @param array<string, string> $staged */
function aetherRestoreStagedCharacterPortraits(array $staged): void
{
    foreach (array_reverse($staged, true) as $original => $temporary) {
        if (is_file($temporary) && !aetherPortraitRename($temporary, $original)) {
            error_log('Kon gequarantaineerd characterportret niet herstellen: ' . basename($temporary));
        }
    }
}

/** @param array<string, string> $staged */
function aetherPurgeStagedCharacterPortraits(array $staged): void
{
    foreach ($staged as $temporary) {
        if (is_file($temporary) && !aetherPortraitUnlink($temporary)) {
            error_log('Kon gequarantaineerd characterportret niet opruimen: ' . basename($temporary));
        }
    }
}
