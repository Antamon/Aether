<?php
declare(strict_types=1);

/** Never accept the application's ordinary dev or production database by name. */
function aetherDisposableMariaDbName(string $dsn, string $expectedName): bool
{
    if (!preg_match('/^aether_disposable_[a-z0-9_]+$/D', $expectedName)) return false;
    if (!preg_match('/(?:^|;)dbname=([^;]+)/', $dsn, $match)) return false;
    return hash_equals($expectedName, $match[1]);
}

function aetherMariaDbConcurrencyAllowed(string $additionalFlag): bool
{
    return getenv('AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS') === 'YES'
        && getenv('AETHER_TEST_DB_DISPOSABLE') === 'YES'
        && getenv($additionalFlag) === 'YES'
        && aetherDisposableMariaDbName(
            (string) (getenv('AETHER_TEST_MYSQL_DSN') ?: ''),
            (string) (getenv('AETHER_TEST_MYSQL_DATABASE') ?: '')
        )
        && (string) (getenv('AETHER_TEST_MYSQL_USER') ?: '') !== ''
        && (string) (getenv('AETHER_TEST_MYSQL_PASSWORD') ?: '') !== '';
}

function aetherAssertDisposableMariaDbConnection(PDO $pdo): void
{
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!aetherDisposableMariaDbName(
        (string) (getenv('AETHER_TEST_MYSQL_DSN') ?: ''),
        (string) (getenv('AETHER_TEST_MYSQL_DATABASE') ?: '')
    ) || !hash_equals((string) getenv('AETHER_TEST_MYSQL_DATABASE'), $database)) {
        throw new RuntimeException('REFUSED: verbonden database is niet de expliciet toegestane wegwerpdatabase.');
    }
}
