<?php
declare(strict_types=1);

// Copy to config.local.php (never commit it), or place the real file outside
// the webroot and set AETHER_CONFIG_FILE to its absolute path on the host.
return [
    'database' => [
        'host' => 'YOUR_DATABASE_HOST',
        'name' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USER',
        'password' => 'YOUR_DATABASE_PASSWORD',
    ],
    'application' => ['environment' => 'development'],
];
