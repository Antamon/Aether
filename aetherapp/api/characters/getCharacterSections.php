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
        aetherCharacterRequestSchema('getCharacterSections', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $input['idCharacter'], 'view');
    aetherJsonResponse(aetherBuildCharacterSectionsReadModel($pdo, $input['idCharacter']));
} catch (Throwable $e) {
    error_log('getCharacterSections.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon character sections niet ophalen.');
}
