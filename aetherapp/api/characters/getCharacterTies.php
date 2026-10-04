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
require_once __DIR__ . '/characterTieRepository.php';
require_once __DIR__ . '/characterTieService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('getCharacterTies', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $input['idCharacter'], 'view');
    aetherJsonResponse(aetherBuildCharacterTieList($pdo, $input['idCharacter']));
} catch (Throwable $e) {
    error_log('getCharacterTies.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon ties niet ophalen.');
}
