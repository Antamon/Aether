<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterRepository.php';
require_once __DIR__ . '/characterService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('updateCharacter', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$characterId = $input['id'];
unset($input['id']);

try {
    $currentCharacter = aetherFetchCharacterForUpdate($pdo, $characterId);
    if ($currentCharacter === null) {
        aetherJsonError(404, 'Personage niet gevonden.');
    }
    if (!aetherCanEditCharacter($currentUser, $currentCharacter)) {
        aetherJsonError(403, 'Je hebt geen rechten om dit personage te wijzigen.');
    }

    $fields = aetherPrepareCharacterUpdate($pdo, $currentUser, $currentCharacter, $input);
    $rowCount = aetherApplyCharacterUpdate($pdo, $currentCharacter, $fields);
    aetherJsonResponse($rowCount);
} catch (AetherCharacterUpdateException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon character niet bijwerken.');
}
