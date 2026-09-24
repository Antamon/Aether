<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/shared/config.php';
require_once __DIR__ . '/../api/shared/database.php';
require_once __DIR__ . '/../api/shared/runtime.php';

final class ConfigTestPdo extends PDO { public function __construct() {} }
$failures = [];
function configCheck(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }
$dir = sys_get_temp_dir() . '/aether-config-test-' . bin2hex(random_bytes(6));
mkdir($dir, 0700);
$file = $dir . '/config.php';
try {
    file_put_contents($file, <<<'PHP'
<?php
return ['database' => ['host' => 'localhost', 'name' => 'aether_disposable_test', 'user' => 'placeholder_user', 'password' => 'placeholder_password'], 'application' => ['environment' => 'test']];
PHP);
    $config = aetherLoadConfiguration($file, []);
    configCheck($config['database']['dsn'] === 'mysql:host=localhost;dbname=aether_disposable_test;charset=utf8mb4', 'DSN of charset');
    configCheck($config['application']['environment'] === 'test', 'Omgeving uit bestand');
    $overridden = aetherLoadConfiguration($file, ['AETHER_DB_DSN'=>'mysql:host=example.invalid;dbname=override_test;charset=utf8mb4','AETHER_DB_USER'=>'override_user','AETHER_DB_PASSWORD'=>'override_password','AETHER_APP_ENV'=>'development']);
    configCheck($overridden['database']['user'] === 'override_user' && $overridden['database']['password'] === 'override_password' && str_contains($overridden['database']['dsn'],'override_test'), 'Environment override');
    $seen = [];
    $pdo = aetherCreateDatabaseConnection($config, static function (string $dsn, string $user, string $password, array $options) use (&$seen): PDO {
        $seen = ['dsn'=>$dsn,'user'=>$user,'password'=>$password,'options'=>$options];
        return new ConfigTestPdo();
    });
    configCheck($pdo instanceof PDO && $seen['options'][PDO::ATTR_ERRMODE] === PDO::ERRMODE_EXCEPTION && $seen['options'][PDO::ATTR_DEFAULT_FETCH_MODE] === PDO::FETCH_ASSOC && $seen['options'][PDO::ATTR_EMULATE_PREPARES] === false, 'Vaste veilige PDO-opties');
    foreach ([
        [$dir.'/missing.php', ['AETHER_CONFIG_FILE'=>$dir.'/missing.php']],
        [$dir.'/absent.php', []],
    ] as [$path,$env]) {
        try { aetherLoadConfiguration($path,$env); configCheck(false,'Ontbrekende configuratie niet geweigerd'); }
        catch (AetherConfigurationException $e) { configCheck(!str_contains($e->getMessage(),'placeholder_password'),'Configuratiefout lekt geheim'); }
    }
    file_put_contents($file, '<?php return ["database" => ["host" => 7, "name" => "x", "user" => "u", "password" => "p"]];');
    try { aetherLoadConfiguration($file,[]); configCheck(false,'Verkeerd datatype niet geweigerd'); }
    catch (AetherConfigurationException $e) { configCheck(true,'Typeweigering'); }
    $all = array_fill_keys(['php','pdo','pdo_mysql','json','session','mbstring','dom','libxml','gd','fileinfo'],true);
    configCheck(aetherMissingRuntimeRequirements('core',$all) === [], 'Volledige runtime');
    $without=$all; $without['pdo_mysql']=false; $without['gd']=false; $without['fileinfo']=false;
    configCheck(aetherMissingRuntimeRequirements('core',$without) === ['pdo_mysql'], 'Core vereist PDO MySQL maar niet uploadextensies');
    configCheck(aetherMissingRuntimeRequirements('portrait_upload',$without) === ['pdo_mysql','gd','fileinfo'], 'Portretvereisten');
    configCheck(aetherMissingRuntimeRequirements('logo_upload',$without) === ['pdo_mysql','fileinfo'], 'Logovereisten');
    configCheck(aetherRuntimeDirectoriesWritable([$dir]), 'Testmap schrijfbaar');
    configCheck(!aetherRuntimeDirectoriesWritable([$dir.'/missing']), 'Ontbrekende noodzakelijke map geweigerd');
    $localPath = dirname(__DIR__) . '/config.local.php';
    if (is_file($localPath)) {
        $local = aetherLoadConfiguration($localPath, []);
        configCheck(isset($local['database']['dsn'], $local['database']['user'], $local['database']['password']), 'Gemigreerde lokale configuratie geladen');
    }
    $bootstrap = (string) file_get_contents(dirname(__DIR__) . '/db.php');
    configCheck(str_contains($bootstrap, '{"error":"Server error."}') && !str_contains($bootstrap, '$exception->getMessage()'), 'Databasefout blijft generiek');
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__) . '/db.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        array_merge(getenv(), ['AETHER_CONFIG_FILE' => $dir . '/missing.php'])
    );
    if (is_resource($process)) {
        $publicOutput = stream_get_contents($pipes[1]);
        $serverLog = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        configCheck(trim((string) $publicOutput) === '{"error":"Server error."}', 'Bootstrapfout bevat alleen generieke JSON');
        configCheck(!str_contains((string) $serverLog, 'placeholder_password') && !str_contains((string) $serverLog, $dir), 'Bootstraplog zonder configuratiedetails');
    } else {
        configCheck(false, 'Geïsoleerde bootstraptest kon niet starten');
    }
} finally {
    if (is_file($file)) unlink($file);
    rmdir($dir);
}
if ($failures) { fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL); exit(1); }
echo "Configuration and runtime tests passed.\n";
