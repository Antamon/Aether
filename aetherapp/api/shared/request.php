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

    aetherCaptureRequestTranslationChanges($input);
    return $input;
}

/** @return array<string, mixed> */
function aetherReadFormFields(): array
{
    aetherCaptureRequestTranslationChanges($_POST);
    return $_POST;
}

/** Snapshot only allowlisted text saves; never start Google work while parsing a request. */
function aetherCaptureRequestTranslationChanges(array $input): void
{
    if (!(($GLOBALS['pdo'] ?? null) instanceof PDO)) return;
    try {
        require_once __DIR__.'/translationChanges.php';
        aetherCaptureTranslationSave($GLOBALS['pdo'], $input);
    } catch (Throwable $error) { unset($GLOBALS['AETHER_TRANSLATION_SAVE']); }
}
