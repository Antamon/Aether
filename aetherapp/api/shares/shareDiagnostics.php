<?php
declare(strict_types=1);

/** Log share endpoint failures locally on the development site only. */
function aetherLogShareEndpointFailure(string $endpoint, Throwable $error): void
{
    error_log($endpoint . ' failed: ' . get_class($error) . ': ' . $error->getMessage()
        . ' in ' . $error->getFile() . ':' . $error->getLine());
    if (basename(dirname(__DIR__, 2)) !== 'aetherapp-dev') return;
    @error_log('[' . date('c') . '] ' . $endpoint . ' failed: '
        . get_class($error) . ': ' . $error->getMessage()
        . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL,
        3, __DIR__ . '/share-errors.log');
}

function aetherRegisterShareEndpointFatalLog(string $endpoint): void
{
    if (basename(dirname(__DIR__, 2)) !== 'aetherapp-dev') return;
    register_shutdown_function(static function () use ($endpoint): void {
        $error = error_get_last();
        if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR,
            E_COMPILE_ERROR, E_USER_ERROR], true)) return;
        @error_log('[' . date('c') . '] ' . $endpoint . ' fatal: '
            . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL,
            3, __DIR__ . '/share-errors.log');
    });
}
