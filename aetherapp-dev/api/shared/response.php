<?php
declare(strict_types=1);

if (!function_exists('aetherJsonResponse')) {
    function aetherJsonResponse(mixed $data, int $status = 200): never
    {
        http_response_code($status);
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
