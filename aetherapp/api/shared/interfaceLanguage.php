<?php
declare(strict_types=1);

function aetherNormalizeInterfaceLanguage(?string $locale): string
{
    $language = strtolower(substr(str_replace('_', '-', trim($locale ?? '')), 0, 2));
    return $language === 'en' ? 'en' : 'nl';
}

/** Read the authenticated WordPress user's profile, never a client-supplied user id. */
function aetherProfileInterfaceLanguage(int $userId): string
{
    if (function_exists('get_user_locale')) {
        return aetherNormalizeInterfaceLanguage((string) get_user_locale($userId));
    }
    if (function_exists('get_user_meta')) {
        $locale = (string) get_user_meta($userId, 'locale', true);
        if ($locale !== '') return aetherNormalizeInterfaceLanguage($locale);
    }
    if (function_exists('get_locale')) return aetherNormalizeInterfaceLanguage((string) get_locale());
    return aetherNormalizeInterfaceLanguage((string) ($_SESSION['user']['locale'] ?? 'nl'));
}
