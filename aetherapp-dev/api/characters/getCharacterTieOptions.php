<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterTieRepository.php';
require_once __DIR__ . '/characterTieService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = array_merge($_GET, aetherReadFormFields());
    aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('getCharacterTieOptions', $requestData)
    );
    aetherJsonResponse(aetherBuildCharacterTieOptions($pdo, $currentUser));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getCharacterTieOptions.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon character lijst voor ties niet ophalen.');
}
