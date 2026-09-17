<?php

declare(strict_types=1);

require_once __DIR__ . '/api/shared/appVersion.php';
require_once __DIR__ . '/api/shared/cache.php';
require_once __DIR__ . '/api/shared/response.php';

aetherSendNoStoreHeaders(false);
header('Content-Type: application/json; charset=utf-8');
aetherJsonResponse(['version' => aetherApplicationVersion()]);
