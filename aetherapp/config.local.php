<?php
declare(strict_types=1);

// Private deployment configuration. Never commit or share credentials.
return array (
  'database' =>
  array (
    'host' => 'localhost',
    'name' => 'c8q4331sl_oneiros_be_aether',
    'user' => 'c8q4331sl_oneiros_be_aether',
    'password' => ':ZG+Yn{{~Hr4a]gR',
  ),
  'application' =>
  array (
    'environment' => 'production',
  ),
  'translation' =>
  array (
    'enabled' => true,
    'googleApiKey' => 'AIzaSyDFi8F4ZQugKLsm-OCHWh63hQ-RZ89RpwA',
    'monthlyCharacterLimit' => 500000,
    'maxAutomaticCharacters' => 2000,
    'budgetKey' => 'aether-google',
    'usageTimeZone' => 'Europe/Brussels',
    'usageDatabase' => NULL,
  ),
);
