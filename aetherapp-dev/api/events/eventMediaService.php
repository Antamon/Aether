<?php
declare(strict_types=1);

require_once __DIR__ . '/eventAccess.php';

function aetherEventImageDirectory(): string
{
    return dirname(__DIR__, 2) . '/img/events';
}

function aetherEventImageUrl(int $id): ?string
{
    $path = aetherEventImageDirectory() . '/' . $id . '.png';
    clearstatcache(true, $path);
    return $id > 0 && is_file($path) && !is_link($path)
        ? 'img/events/' . $id . '.png?v=' . hash_file('sha256', $path) : null;
}

function aetherUploadEventImage(int $id, array $upload, ?string $directory = null): void
{
    $path = (string) ($upload['tmp_name'] ?? '');
    $verifier = $GLOBALS['aetherEventImageUploadVerifier'] ?? null;
    $trusted = is_callable($verifier) ? (bool) $verifier($path) : is_uploaded_file($path);
    if ($id <= 0 || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !$trusted) {
        throw new AetherEventException(422, 'Geen geldig uploadbestand ontvangen.');
    }
    $size = filesize($path);
    $info = @getimagesize($path);
    $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
    if (!$size || $size > 10 * 1024 * 1024 || !$info
        || !in_array($info['mime'] ?? '', $allowed, true)
        || (new finfo(FILEINFO_MIME_TYPE))->file($path) !== $info['mime']
        || $info[0] * $info[1] > 20000000) {
        throw new AetherEventException(422, 'Gebruik een afbeelding van maximaal 10 MB en 20 megapixels.');
    }
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('GD ontbreekt.');
    $source = @imagecreatefromstring((string) file_get_contents($path));
    if (!$source) throw new AetherEventException(422, 'De afbeelding kon niet worden verwerkt.');
    // Landscape counterpart of the character portrait: 4:3, centered crop.
    $width = imagesx($source); $height = imagesy($source);
    $cropWidth = min($width, (int) round($height * 4 / 3));
    $cropHeight = min($height, (int) round($width * 3 / 4));
    $targetWidth = min(1200, $cropWidth);
    $targetHeight = max(1, (int) round($targetWidth * 3 / 4));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    $working = null;
    try {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0,
            (int) (($width - $cropWidth) / 2), (int) (($height - $cropHeight) / 2),
            $targetWidth, $targetHeight, $cropWidth, $cropHeight);
        $directory ??= aetherEventImageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Afbeeldingsmap niet beschikbaar.');
        }
        $working = tempnam($directory, '.incoming-');
        if (!$working || !imagepng($target, $working) || !chmod($working, 0644)
            || !rename($working, $directory . '/' . $id . '.png')) {
            throw new RuntimeException('Eventafbeelding niet bewaard.');
        }
    } finally {
        unset($source, $target);
        if ($working && is_file($working)) unlink($working);
    }
}
