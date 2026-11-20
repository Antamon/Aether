<?php
declare(strict_types=1);

if (!function_exists('aetherJsonResponse')) {
    function aetherJsonResponse(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        // Translated responses contain the same private, authorized game data as the source.
        if (!headers_sent()) header('Vary: X-Aether-Content-Language', false);
        $contentLanguage = (string)($_SERVER['HTTP_X_AETHER_CONTENT_LANGUAGE'] ?? '');
        if (!defined('AETHER_SKIP_CONTENT_TRANSLATION') && $status < 400 && ($GLOBALS['pdo'] ?? null) instanceof PDO && in_array($contentLanguage, ['nl', 'en'], true)) {
            require_once __DIR__ . '/contentLanguage.php';
            $changes = [];
            if (isset($GLOBALS['AETHER_TRANSLATION_SAVE'])) {
                require_once __DIR__.'/translationChanges.php';
                try { $changes = aetherSavedTranslationChanges($GLOBALS['pdo'], $data); }
                catch (DomainException $error) { aetherJsonError(409,$error->getMessage()); }
                catch (InvalidArgumentException $error) { aetherJsonError(422,$error->getMessage()); }
                catch (Throwable $error) { aetherJsonError(503,'De vertaling kon niet worden bewaard. Probeer opnieuw.'); }
            }
            $data = aetherContentResponse($GLOBALS['pdo'], $data, $contentLanguage, $changes);
        }
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('aetherJsonError')) {
    function aetherJsonError(int $status, string $message): never
    {
        aetherJsonResponse(['error' => $message], $status);
    }
}

if (!function_exists('aetherJsonValidationError')) {
    /** @param list<string> $errors */
    function aetherJsonValidationError(array $errors): never
    {
        http_response_code(422);
        echo json_encode(
            ['error' => 'Ongeldige invoer.', 'validationErrors' => $errors],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}
