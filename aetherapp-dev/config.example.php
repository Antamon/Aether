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
    'translation' => [
        'enabled' => false,
        'googleApiKey' => 'YOUR_GOOGLE_CLOUD_TRANSLATION_API_KEY',
        'monthlyCharacterLimit' => 500000,
        'maxAutomaticCharacters' => 2000,
        'budgetKey' => 'aether-google',
        'usageTimeZone' => 'Europe/Brussels',
        // When both websites use one Google project, point BOTH to the same usage database.
        // null uses this application's database. Credentials stay server-side.
        'usageDatabase' => null, // ['dsn'=>'mysql:host=HOST;dbname=DB;charset=utf8mb4','user'=>'USER','password'=>'PASSWORD']
    ],
];
