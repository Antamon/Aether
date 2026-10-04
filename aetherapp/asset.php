<?php

declare(strict_types=1);

require_once __DIR__ . '/api/shared/assets.php';
require_once __DIR__ . '/api/shared/cache.php';
require_once __DIR__ . '/api/shared/response.php';

aetherSendNoStoreHeaders(false);

$assetPath = isset($_GET['path']) && is_string($_GET['path']) ? $_GET['path'] : '';

try {
    if (aetherAssetFile($assetPath, __DIR__) === null) {
        aetherJsonError(404, 'Asset niet gevonden.');
    }

    header('Location: ' . aetherAssetUrl($assetPath, '', __DIR__), true, 302);
    exit;
} catch (InvalidArgumentException) {
    aetherJsonError(400, 'Ongeldig assetpad.');
}
