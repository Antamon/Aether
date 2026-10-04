<?php
declare(strict_types=1);

final class AetherConfigurationException extends RuntimeException {}

/**
 * Read the trusted local file and selected environment overrides in one place.
 * The optional arguments make this function testable without touching the real environment.
 */
function aetherLoadConfiguration(?string $file = null, ?array $environment = null): array
{
    $environment ??= aetherConfigurationEnvironment();
    $file ??= $environment['AETHER_CONFIG_FILE'] ?? dirname(__DIR__, 2) . '/config.local.php';
    $values = [];
    if ($file !== '') {
        if (is_file($file)) {
            if (!is_readable($file)) throw new AetherConfigurationException('Applicatieconfiguratie onleesbaar.');
            $loaded = (static fn(string $path): mixed => @require $path)($file);
            if (!is_array($loaded)) throw new AetherConfigurationException('Ongeldige applicatieconfiguratie.');
            $values = $loaded;
        } elseif (isset($environment['AETHER_CONFIG_FILE'])) {
            throw new AetherConfigurationException('Applicatieconfiguratie ontbreekt.');
        }
    }
    $database = $values['database'] ?? [];
    if (!is_array($database)) throw new AetherConfigurationException('Ongeldige databaseconfiguratie.');
    foreach (['dsn'=>'AETHER_DB_DSN','host'=>'AETHER_DB_HOST','name'=>'AETHER_DB_NAME','user'=>'AETHER_DB_USER','password'=>'AETHER_DB_PASSWORD'] as $key=>$envKey) {
        if (array_key_exists($envKey, $environment)) $database[$key] = $environment[$envKey];
    }
    $application = $values['application'] ?? [];
    if (!is_array($application)) throw new AetherConfigurationException('Ongeldige applicatieconfiguratie.');
    if (array_key_exists('AETHER_APP_ENV', $environment)) $application['environment'] = $environment['AETHER_APP_ENV'];
    $dsn = $database['dsn'] ?? null;
    if ($dsn === null) {
        $host = $database['host'] ?? null;
        $name = $database['name'] ?? null;
        if (!is_string($host) || trim($host) === '' || !is_string($name) || trim($name) === '') {
            throw new AetherConfigurationException('Databaseconfiguratie ontbreekt.');
        }
        if (preg_match('/^[A-Za-z0-9._:-]+$/D', $host) !== 1 || preg_match('/^[A-Za-z0-9_]+$/D', $name) !== 1) {
            throw new AetherConfigurationException('Ongeldige databaseconfiguratie.');
        }
        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    }
    if (!is_string($dsn) || preg_match('/^mysql:(?:host=[^;]+|unix_socket=[^;]+);dbname=[^;]+;charset=utf8mb4$/D', $dsn) !== 1) {
        throw new AetherConfigurationException('Ongeldige databaseconfiguratie.');
    }
    $user = $database['user'] ?? null;
    $password = $database['password'] ?? null;
    if (!is_string($user) || trim($user) === '' || !is_string($password) || $password === '') {
        throw new AetherConfigurationException('Databaseconfiguratie ontbreekt.');
    }
    $appEnvironment = $application['environment'] ?? 'production';
    if (!is_string($appEnvironment) || !in_array($appEnvironment, ['development', 'test', 'production'], true)) {
        throw new AetherConfigurationException('Ongeldige applicatieconfiguratie.');
    }
    return ['database'=>['dsn'=>$dsn,'user'=>$user,'password'=>$password], 'application'=>['environment'=>$appEnvironment]];
}

function aetherConfigurationEnvironment(): array
{
    $result = [];
    foreach (['AETHER_CONFIG_FILE','AETHER_DB_DSN','AETHER_DB_HOST','AETHER_DB_NAME','AETHER_DB_USER','AETHER_DB_PASSWORD','AETHER_APP_ENV'] as $key) {
        $value = getenv($key);
        if ($value !== false) $result[$key] = $value;
    }
    return $result;
}
