<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** @param null|callable(string,string,string,array):PDO $factory */
function aetherCreateDatabaseConnection(array $config, ?callable $factory = null): PDO
{
    $database = $config['database'] ?? null;
    if (!is_array($database) || !isset($database['dsn'], $database['user'], $database['password'])) {
        throw new AetherConfigurationException('Databaseconfiguratie ontbreekt.');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $factory ??= static fn(string $dsn, string $user, string $password, array $pdoOptions): PDO => new PDO($dsn, $user, $password, $pdoOptions);
    return $factory($database['dsn'], $database['user'], $database['password'], $options);
}
