<?php
declare(strict_types=1);

require_once __DIR__ . '/companyService.php';

function aetherCompanyLogoUploadIsTrusted(string $path): bool
{
    $override = $GLOBALS['aetherCompanyLogoUploadVerifier'] ?? null;
    return is_callable($override) ? (bool) $override($path) : is_uploaded_file($path);
}

function aetherCompanyLogoMoveUploadedFile(string $source, string $target): bool
{
    $override = $GLOBALS['aetherCompanyLogoMoveUploadedFile'] ?? null;
    return is_callable($override) ? (bool) $override($source, $target) : move_uploaded_file($source, $target);
}

function aetherCompanyLogoRename(string $source, string $target): bool
{
    $override = $GLOBALS['aetherCompanyLogoRename'] ?? null;
    return is_callable($override) ? (bool) $override($source, $target) : @rename($source, $target);
}

function aetherCompanyLogoUnlink(string $path): bool
{
    $override = $GLOBALS['aetherCompanyLogoUnlink'] ?? null;
    return is_callable($override) ? (bool) $override($path) : @unlink($path);
}

function aetherUploadCompanyLogo(PDO $pdo, int $idCompany, array $upload): array
{
    aetherCompanyRequireExisting($pdo, $idCompany);
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new AetherCompanyException(400, 'Het opladen van het logo is mislukt.');
    }
    $tmpPath = $upload['tmp_name'] ?? null;
    if (!is_string($tmpPath) || !aetherCompanyLogoUploadIsTrusted($tmpPath)) {
        throw new AetherCompanyException(400, 'Geen geldig uploadbestand ontvangen.');
    }
    $actualSize = @filesize($tmpPath);
    if ($actualSize === false || $actualSize <= 0 || $actualSize > 10 * 1024 * 1024) {
        throw new AetherCompanyException(400, 'Geen geldig uploadbestand ontvangen.');
    }
    $imageInfo = @getimagesize($tmpPath);
    $mime = is_array($imageInfo) ? strtolower((string) ($imageInfo['mime'] ?? '')) : '';
    $extensions = aetherCompanyLogoMimeExtensions();
    if ($imageInfo === false || !isset($extensions[$mime])) {
        throw new AetherCompanyException(400, 'Geef een geldige afbeeldingsfile op.');
    }
    if ((int) $imageInfo[0] * (int) $imageInfo[1] > 20000000) {
        throw new AetherCompanyException(400, 'De afbeelding is te groot.');
    }
    if (class_exists('finfo')) {
        $detectedMime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath));
        if ($detectedMime !== $mime) {
            throw new AetherCompanyException(400, 'Geef een geldige afbeeldingsfile op.');
        }
    }
    $targetExtension = $extensions[$mime];
    $temporaryOutput = null;
    $source = null;
    $target = null;
    try {
        $directory = getCompanyLogoDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Logomap is niet beschikbaar.');
        }
        $temporaryOutput = tempnam($directory, 'aether-logo-');
        if ($temporaryOutput === false) {
            throw new RuntimeException('Logo kon niet tijdelijk worden bewaard.');
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            // Some shared PHP hosts do not enable GD. The file was already checked as
            // a safe raster image; retain its detected format instead of failing every upload.
            if (!aetherCompanyLogoMoveUploadedFile($tmpPath, $temporaryOutput)) {
                throw new RuntimeException('Logo kon niet tijdelijk worden bewaard.');
            }
        } else {
            $imageData = file_get_contents($tmpPath);
            $source = $imageData === false ? false : @imagecreatefromstring($imageData);
            if ($source === false) {
                throw new AetherCompanyException(400, 'Geef een geldige afbeeldingsfile op.');
            }
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                throw new AetherCompanyException(400, 'De afbeelding heeft geen geldige afmetingen.');
            }
            $scale = min(1, 800 / max($sourceWidth, $sourceHeight));
            $target = imagecreatetruecolor(max(1, (int) round($sourceWidth * $scale)), max(1, (int) round($sourceHeight * $scale)));
            if ($target === false) {
                throw new RuntimeException('Logo-doelafbeelding kon niet worden gemaakt.');
            }
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            if (!imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $sourceWidth, $sourceHeight)) {
                throw new RuntimeException('Logo-herschaling mislukt.');
            }
            if ($temporaryOutput === false || !imagepng($target, $temporaryOutput)) {
                throw new RuntimeException('Logo kon niet worden bewaard.');
            }
        }
        $targetExtension = function_exists('imagecreatefromstring') && function_exists('imagepng') ? 'png' : $targetExtension;
        $targetPath = getCompanyLogoAbsolutePath($idCompany, $targetExtension);
        // tempnam() creates a private file (usually 0600). After rename Apache may
        // run as another user and return 403 unless the image is made readable.
        if (!@chmod($temporaryOutput, 0644)) {
            throw new RuntimeException('Logo kon niet leesbaar worden gemaakt.');
        }
        if (!aetherCompanyLogoRename($temporaryOutput, $targetPath)) {
            throw new RuntimeException('Logo kon niet worden geactiveerd.');
        }
        $temporaryOutput = null;
        foreach (aetherCompanyLogoManagedPaths($idCompany) as $oldPath) {
            if ($oldPath !== $targetPath && !aetherCompanyLogoUnlink($oldPath)) {
                error_log('Kon oud bedrijfslogo niet opruimen: ' . basename($oldPath));
            }
        }
        clearstatcache(true, $targetPath);
    } finally {
        if ($target !== null) imagedestroy($target);
        if ($source !== null) imagedestroy($source);
        if (is_string($temporaryOutput) && is_file($temporaryOutput)) aetherCompanyLogoUnlink($temporaryOutput);
    }
    return ['status' => 'ok', 'logoUrl' => getCompanyLogoUrl($idCompany)];
}

function aetherDeleteCompanyLogo(PDO $pdo, int $idCompany): array
{
    aetherCompanyRequireExisting($pdo, $idCompany);
    foreach (aetherCompanyLogoManagedPaths($idCompany) as $path) {
        if (!aetherCompanyLogoUnlink($path)) {
            throw new RuntimeException('Logo kon niet worden verwijderd.');
        }
    }
    return ['status' => 'ok'];
}
