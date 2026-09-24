<?php
declare(strict_types=1);

/** Cookie settings derived from the requested app route, never from a browser role or ID. */
function aetherSessionCookieOptions(array $server): array
{
    $script = (string) ($server['SCRIPT_NAME'] ?? '');
    $path = str_replace('\\', '/', $script);
    $apiPosition = strpos($path, '/api/');
    $base = $apiPosition === false ? dirname($path) : substr($path, 0, $apiPosition);
    $base = '/' . trim(str_replace('\\', '/', $base), '/');
    $cookiePath = $base === '/' ? '/' : $base . '/';
    $https = strtolower((string) ($server['HTTPS'] ?? ''));

    return [
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => in_array($https, ['on', '1'], true) || (string) ($server['SERVER_PORT'] ?? '') === '443',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function aetherStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(aetherSessionCookieOptions($_SERVER));
    session_start();
}
