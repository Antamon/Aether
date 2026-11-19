<?php
declare(strict_types=1);
$root = getenv('AETHER_TEST_ROOT') ?: dirname(__DIR__);
require_once $root.'/api/shared/config.php';
require_once $root.'/api/shared/database.php';
require_once $root.'/api/shared/translationService.php';
$file = tempnam(sys_get_temp_dir(), 'aether-config-isolation-');
$database = ['host'=>'localhost', 'name'=>'test_aether', 'user'=>'test_aether', 'password'=>'test:+{}~[]'];
$translation = ['enabled'=>true, 'googleApiKey'=>'test-key-not-real', 'monthlyCharacterLimit'=>500000, 'maxAutomaticCharacters'=>2000, 'budgetKey'=>'aether-google', 'usageTimeZone'=>'Europe/Brussels', 'usageDatabase'=>null];
try {
    $baseline = null;
    foreach ([null, ['enabled'=>false], $translation] as $settings) {
        $raw = ['database'=>$database, 'application'=>['environment'=>'production']];
        if ($settings !== null) $raw['translation'] = $settings;
        file_put_contents($file, '<?php declare(strict_types=1); return '.var_export($raw,true).';');
        $loaded = aetherLoadConfiguration($file, []);
        $baseline ??= $loaded['database'];
        if ($loaded['database'] !== $baseline) throw new RuntimeException('Translation settings changed database credentials.');
        $captured = null;
        aetherCreateDatabaseConnection($loaded, static function ($dsn,$user,$password,$options) use (&$captured): PDO {
            $captured = [$dsn,$user,$password];
            return new PDO('sqlite::memory:');
        });
        if ($captured !== [$baseline['dsn'],$baseline['user'],$baseline['password']]) throw new RuntimeException('PDO received different credentials.');
        if ($settings === $translation) {
            $validated = aetherTranslationSettings($loaded['translation']);
            if (!$validated['enabled'] || $validated['maxAutomaticCharacters'] !== 2000 || $validated['monthlyCharacterLimit'] !== 500000) throw new RuntimeException('Translation configuration not preserved.');
        }
    }
    echo "PASS: full translation block, disabled translation and omitted translation use identical database credentials; special characters and translation limits are preserved. No Google requests.\n";
} finally { unlink($file); }
