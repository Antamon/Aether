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
require_once __DIR__ . '/characterTraitRepository.php';
require_once __DIR__ . '/characterTraitService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('updateTrait', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    aetherJsonResponse(aetherUpdateCharacterTrait(
        $pdo,
        $currentUser,
        $input['idCharacter'],
        $input['idTrait'],
        (int) ($input['idCurrentTrait'] ?? 0),
        $input['action']
    ));
} catch (AetherCharacterTraitException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('updateTrait.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon trait niet bijwerken.');
}
