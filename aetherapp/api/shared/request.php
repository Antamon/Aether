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

    return aetherCaptureRequestTranslationChanges($input);
}

/** @return array<string, mixed> */
function aetherReadFormFields(): array
{
    return aetherCaptureRequestTranslationChanges($_POST);
}

/** Snapshot only allowlisted text saves; never start Google work while parsing a request. */
function aetherCaptureRequestTranslationChanges(array $input): array
{
    if (!(($GLOBALS['pdo'] ?? null) instanceof PDO)) { unset($input['_aetherTranslationEdits']);return $input; }
    try {
        require_once __DIR__.'/translationChanges.php';
        return aetherCaptureTranslationSave($GLOBALS['pdo'], $input);
    } catch (AetherInlineTranslationForbidden $error) { aetherJsonError(403,$error->getMessage()); }
    catch (DomainException $error) { aetherJsonError(409,$error->getMessage()); }
    catch (InvalidArgumentException $error) { aetherJsonError(422,$error->getMessage()); }
    catch (Throwable $error) {
        unset($GLOBALS['AETHER_TRANSLATION_SAVE']);
        if(!empty($input['_aetherTranslationEdits']))aetherJsonError(503,'De vertaling kon niet worden bewaard. Probeer opnieuw.');
        unset($input['_aetherTranslationEdits']);return $input;
    }
}
