<?php

declare(strict_types=1);

if (!function_exists('aetherApplicationVersion')) {
    /**
     * Returns the opaque deployment identifier published in the root VERSION file.
     * The fallback keeps the endpoint usable without leaking filesystem errors.
     */
    function aetherApplicationVersion(?string $versionFile = null): string
    {
        $versionFile ??= dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'VERSION';
        $contents = @file_get_contents($versionFile);

        if ($contents === false) {
            return 'development';
        }

        $version = trim($contents);
        if ($version === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $version) !== 1) {
            return 'development';
        }

        return $version;
    }
}
