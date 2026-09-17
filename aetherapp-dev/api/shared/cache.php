<?php

declare(strict_types=1);

if (!function_exists('aetherNoStoreHeaderValues')) {
    /** @return array<string, string> */
    function aetherNoStoreHeaderValues(bool $private = true): array
    {
        return [
            'Cache-Control' => ($private ? 'private, ' : '') . 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}

if (!function_exists('aetherSendNoStoreHeaders')) {
    function aetherSendNoStoreHeaders(bool $private = true): void
    {
        foreach (aetherNoStoreHeaderValues($private) as $name => $value) {
            header($name . ': ' . $value, true);
        }
    }
}
