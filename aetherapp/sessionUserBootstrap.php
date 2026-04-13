<?php
declare(strict_types=1);

function aetherFindWordPressLoadPath(): ?string
{
    static $resolvedPath = false;

    if ($resolvedPath !== false) {
        return $resolvedPath ?: null;
    }

    $candidates = [];

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'wp-load.php';
    }

    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $candidates[] = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate)) {
            $resolvedPath = $candidate;
            return $candidate;
        }
    }

    $resolvedPath = null;
    return null;
}

function aetherLoadWordPressIfAvailable(): bool
{
    static $loaded = false;

    if ($loaded) {
        return function_exists('is_user_logged_in') && function_exists('wp_get_current_user');
    }

    $wpLoadPath = aetherFindWordPressLoadPath();
    if ($wpLoadPath === null) {
        return false;
    }

    require_once $wpLoadPath;
    $loaded = true;

    return function_exists('is_user_logged_in') && function_exists('wp_get_current_user');
}

function aetherHydrateSessionUserFromWordPress(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!aetherLoadWordPressIfAvailable()) {
        return false;
    }

    if (!is_user_logged_in()) {
        return false;
    }

    $wpUser = wp_get_current_user();
    $wpUserId = (int) ($wpUser->ID ?? 0);
    if ($wpUserId <= 0) {
        return false;
    }

    $firstName = '';
    $lastName = '';

    if (function_exists('get_user_meta')) {
        $firstName = (string) get_user_meta($wpUserId, 'first_name', true);
        $lastName = (string) get_user_meta($wpUserId, 'last_name', true);
    }

    if ($firstName === '' && isset($wpUser->first_name)) {
        $firstName = (string) $wpUser->first_name;
    }

    if ($lastName === '' && isset($wpUser->last_name)) {
        $lastName = (string) $wpUser->last_name;
    }

    $_SESSION['user'] = [
        'id' => $wpUserId,
        'username' => (string) ($wpUser->user_login ?? ''),
        'email' => (string) ($wpUser->user_email ?? ''),
        'displayName' => (string) ($wpUser->display_name ?? ''),
        'firstName' => $firstName,
        'lastName' => $lastName,
        'avatar' => function_exists('get_avatar_url') ? (string) get_avatar_url($wpUserId) : null,
        'source' => 'wordpress',
    ];

    return true;
}
