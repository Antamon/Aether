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
require_once __DIR__ . '/characterReadRepository.php';
require_once __DIR__ . '/characterReadService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('getCharacter', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    $character = aetherFetchCharacterReadModelBase($pdo, $input['id']);
    if ($character === null) {
        aetherJsonError(404, 'Personage niet gevonden.');
    }
    if (!aetherCanViewCharacter($currentUser, $character)) {
        aetherJsonError(403, 'Je hebt geen rechten om dit personage te bekijken.');
    }

    aetherJsonResponse(aetherBuildCharacterReadModel($pdo, $currentUser, $character));
} catch (Throwable $e) {
    error_log('getCharacter.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon character niet ophalen.');
}
