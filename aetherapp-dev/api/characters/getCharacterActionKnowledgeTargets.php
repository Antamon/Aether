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
require_once __DIR__ . '/characterActionService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('getCharacterActionKnowledgeTargets', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}
try {
    aetherRequireCharacterActionAccess(
        $pdo,
        $currentUser,
        $input['idCharacter'],
        'Geen rechten om deze acties te bekijken.'
    );
    aetherJsonResponse(aetherBuildCharacterKnowledgeTargets(
        $pdo,
        $input['idCharacter'],
        $input['idEvent']
    ));
} catch (AetherCharacterActionException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('getCharacterActionKnowledgeTargets.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de wereldwijsdoelen niet laden.');
}
