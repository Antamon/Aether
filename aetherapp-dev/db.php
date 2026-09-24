<?php
declare(strict_types=1);

// Compatibility bootstrap for the existing routes. Credentials live in a
// deployment-specific, untracked configuration file or environment variables.
@ini_set('display_errors', '0');
try {
    require_once __DIR__ . '/api/shared/config.php';
    require_once __DIR__ . '/api/shared/database.php';
    require_once __DIR__ . '/api/shared/runtime.php';

    aetherRequireRuntime('core');
    $pdo = aetherCreateDatabaseConnection(aetherLoadConfiguration());
} catch (Throwable $exception) {
    error_log('Aether database bootstrap unavailable.');
    if (!headers_sent()) {
        http_response_code(500);
        header('Cache-Control: private, no-store');
        header('Content-Type: application/json; charset=utf-8');
    }
    echo '{"error":"Server error."}';
    exit;
}

function dbAll(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function dbOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function getPDO(): PDO
{
    global $pdo;
    return $pdo;
}
