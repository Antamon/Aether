<?php

declare(strict_types=1);

require_once __DIR__ . '/appVersion.php';

if (!function_exists('aetherIsExternalAssetUrl')) {
    function aetherIsExternalAssetUrl(string $assetPath): bool
    {
        return preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $assetPath) === 1;
    }
}

if (!function_exists('aetherNormalizeAssetPath')) {
    function aetherNormalizeAssetPath(string $assetPath): string
    {
        $assetPath = trim(str_replace('\\', '/', $assetPath));
        if ($assetPath === '' || str_contains($assetPath, "\0")) {
            throw new InvalidArgumentException('Ongeldig assetpad.');
        }

        $path = ltrim($assetPath, '/');
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Ongeldig assetpad.');
            }
        }

        if (preg_match('/\.(?:css|js)$/i', $path) !== 1) {
            throw new InvalidArgumentException('Alleen lokale CSS- en JavaScriptassets zijn toegestaan.');
        }

        return $path;
    }
}

if (!function_exists('aetherAssetFile')) {
    function aetherAssetFile(string $assetPath, ?string $applicationRoot = null): ?string
    {
        $path = aetherNormalizeAssetPath($assetPath);
        $root = realpath($applicationRoot ?? dirname(__DIR__, 2));
        if ($root === false) {
            return null;
        }

        $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($candidate === false || !is_file($candidate)) {
            return null;
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalizedCandidate = str_replace('\\', '/', $candidate);
        $insideRoot = DIRECTORY_SEPARATOR === '\\'
            ? str_starts_with(strtolower($normalizedCandidate), strtolower($rootPrefix))
            : str_starts_with($normalizedCandidate, $rootPrefix);

        return $insideRoot ? $candidate : null;
    }
}

if (!function_exists('aetherAssetUrl')) {
    /**
     * Builds a cache-busting URL for a local CSS/JS file. External URLs are returned unchanged.
     * Missing files use the deployment version and never emit filemtime warnings.
     */
    function aetherAssetUrl(
        string $assetPath,
        string $urlBase = '',
        ?string $applicationRoot = null,
        ?string $fallbackVersionFile = null
    ): string {
        if (aetherIsExternalAssetUrl($assetPath)) {
            return $assetPath;
        }

        $path = aetherNormalizeAssetPath($assetPath);
        $file = aetherAssetFile($path, $applicationRoot);
        $modified = $file === null ? false : @filemtime($file);
        $version = $modified === false
            ? aetherApplicationVersion($fallbackVersionFile)
            : (string) $modified;

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $prefix = $urlBase === '' ? '' : rtrim($urlBase, '/') . '/';

        return $prefix . $encodedPath . '?v=' . rawurlencode($version);
    }
}
