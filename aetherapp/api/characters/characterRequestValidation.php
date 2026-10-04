<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/characterSchemas.php';

/**
 * Tijdelijke klassenaam voor bestaande tests en aanroepen die characterfouten vangen.
 * Verwijderen zodra alle charactercode rechtstreeks AetherValidationException gebruikt.
 */
class CharacterRequestValidationException extends AetherValidationException
{
}

/**
 * Tijdelijke route-facade die het characterschema aan de generieke engine koppelt.
 * Verwijderen zodra endpoints zelf aetherValidateInput() met een moduleschema aanroepen.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function aetherValidateCharacterRequest(string $route, array $input): array
{
    try {
        return aetherValidateInput($input, aetherCharacterRequestSchema($route, $input));
    } catch (AetherValidationException $e) {
        throw new CharacterRequestValidationException($e->getErrors());
    }
}

/**
 * Tijdelijke request-facade voor het oude charactercontract: lege input valt terug
 * op $_POST en JSON-formaatfouten blijven HTTP 422. Verwijderen per endpoint zodra
 * het expliciet aetherReadJsonObject() of aetherReadFormFields() gebruikt.
 *
 * @return array<string, mixed>
 */
function aetherReadCharacterJsonRequest(string $route): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        $input = aetherReadFormFields();
    } else {
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            aetherCharacterValidationFailure(['Requestbody bevat geen geldige JSON.']);
        }
    }
    if (!is_array($input) || (array_is_list($input) && $input !== [])) {
        aetherCharacterValidationFailure(['Requestbody moet een JSON-object zijn.']);
    }
    try {
        return aetherValidateCharacterRequest($route, $input);
    } catch (CharacterRequestValidationException $e) {
        aetherCharacterValidationFailure($e->getValidationErrors());
    }
}

/**
 * Tijdelijke facade voor multipart- en reeds geparseerde characterinput.
 * Verwijderen zodra deze routes de generieke engine rechtstreeks aanroepen.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function aetherValidateCharacterRequestOrFail(string $route, array $input): array
{
    try {
        return aetherValidateCharacterRequest($route, $input);
    } catch (CharacterRequestValidationException $e) {
        aetherCharacterValidationFailure($e->getValidationErrors());
    }
}

/**
 * Tijdelijke 422-respons om de bestaande character-API exact te behouden.
 * Verwijderen zodra een gedeelde validatierespons als afzonderlijke stap is ingevoerd.
 *
 * @param list<string> $errors
 */
function aetherCharacterValidationFailure(array $errors): never
{
    http_response_code(422);
    echo json_encode(['error' => 'Ongeldige invoer.', 'validationErrors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}
