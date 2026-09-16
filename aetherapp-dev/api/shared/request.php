<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

/** @return array<string, mixed> */
function aetherReadJsonObject(): array
{
    $raw = file_get_contents('php://input');
    if (PHP_SAPI === 'cli' && ($raw === false || $raw === '')) {
        $raw = file_get_contents('php://stdin');
    }
    if ($raw === false || trim($raw) === '') {
        aetherJsonError(400, 'Requestbody bevat geen geldige JSON.');
    }

    try {
        $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $topLevelValue = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        aetherJsonError(400, 'Requestbody bevat geen geldige JSON.');
    }

    if (!$topLevelValue instanceof stdClass || !is_array($input)) {
        aetherJsonError(400, 'Requestbody moet een JSON-object zijn.');
    }

    return $input;
}

/** @return array<string, mixed> */
function aetherReadFormFields(): array
{
    return $_POST;
}
