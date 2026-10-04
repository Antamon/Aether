<?php
declare(strict_types=1);

const AETHER_CHARACTER_PORTRAIT_MAX_BYTES = 10 * 1024 * 1024;

/** @return array<int, string> image type => MIME */
function aetherCharacterPortraitAllowedImageTypes(): array
{
    $types = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_GIF => 'image/gif',
    ];
    foreach ([
        'IMAGETYPE_WEBP' => 'image/webp',
        'IMAGETYPE_BMP' => 'image/bmp',
        'IMAGETYPE_WBMP' => 'image/vnd.wap.wbmp',
        'IMAGETYPE_AVIF' => 'image/avif',
    ] as $constant => $mime) {
        if (defined($constant)) {
            $types[constant($constant)] = $mime;
        }
    }
    return $types;
}

/** @return array{width: int, height: int, mime: string} */
function aetherInspectCharacterPortrait(string $path): array
{
    $size = @filesize($path);
    if ($size === false || $size <= 0) {
        throw new InvalidArgumentException('Portret moet groter dan 0 en maximaal 10 MB zijn.');
    }
    if ($size > AETHER_CHARACTER_PORTRAIT_MAX_BYTES) {
        throw new LengthException('Portret moet groter dan 0 en maximaal 10 MB zijn.');
    }

    $image = @getimagesize($path);
    $type = is_array($image) ? (int) ($image[2] ?? 0) : 0;
    $allowed = aetherCharacterPortraitAllowedImageTypes();
    if (!is_array($image) || !isset($allowed[$type])) {
        throw new UnexpectedValueException('Geef een geldige afbeeldingsfile op.');
    }
    $expectedMime = $allowed[$type];
    $imageMime = strtolower((string) ($image['mime'] ?? ''));
    if ($imageMime !== '' && $imageMime !== $expectedMime) {
        throw new UnexpectedValueException('Geef een geldige afbeeldingsfile op.');
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->file($path));
        $equivalentMimes = defined('IMAGETYPE_BMP') && $type === constant('IMAGETYPE_BMP')
            ? ['image/bmp', 'image/x-ms-bmp']
            : [$expectedMime];
        if (!in_array($detectedMime, $equivalentMimes, true)) {
            throw new UnexpectedValueException('Geef een geldige afbeeldingsfile op.');
        }
    }

    $width = (int) ($image[0] ?? 0);
    $height = (int) ($image[1] ?? 0);
    if ($width <= 0 || $height <= 0) {
        throw new UnexpectedValueException('De afbeelding heeft geen geldige afmetingen.');
    }
    return ['width' => $width, 'height' => $height, 'mime' => $expectedMime];
}

function aetherWriteCharacterPortraitPng(string $sourcePath, string $targetPath): void
{
    $override = $GLOBALS['aetherPortraitImageProcessor'] ?? null;
    if (is_callable($override)) {
        $override($sourcePath, $targetPath);
        return;
    }
    if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
        throw new RuntimeException('GD-afbeeldingsondersteuning ontbreekt.');
    }

    $imageData = @file_get_contents($sourcePath);
    $sourceImage = $imageData !== false ? @imagecreatefromstring($imageData) : false;
    if ($sourceImage === false) {
        throw new UnexpectedValueException('Geef een geldige afbeeldingsfile op.');
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    $targetRatio = 35 / 45;
    $sourceRatio = $sourceWidth / $sourceHeight;
    $cropWidth = $sourceWidth;
    $cropHeight = $sourceHeight;
    $srcX = 0;
    $srcY = 0;
    if ($sourceRatio > $targetRatio) {
        $cropWidth = (int) round($sourceHeight * $targetRatio);
        $srcX = (int) floor(($sourceWidth - $cropWidth) / 2);
    } elseif ($sourceRatio < $targetRatio) {
        $cropHeight = (int) round($sourceWidth / $targetRatio);
        $srcY = (int) floor(($sourceHeight - $cropHeight) / 2);
    }

    $targetHeight = min(1024, $cropHeight);
    $targetWidth = max(1, (int) round($targetHeight * $targetRatio));
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($targetImage === false) {
        imagedestroy($sourceImage);
        throw new RuntimeException('Kon doelafbeelding niet aanmaken.');
    }

    try {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
        if (!imagecopyresampled($targetImage, $sourceImage, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $cropWidth, $cropHeight)
            || !imagepng($targetImage, $targetPath)) {
            throw new RuntimeException('Kon portret niet verwerken.');
        }
    } finally {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    }
}
