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
require_once __DIR__ . '/characterLanguageRepository.php';
require_once __DIR__ . '/characterLanguageService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('addCharacterLanguage', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    aetherJsonResponse(aetherAddCharacterLanguage(
        $pdo,
        $currentUser,
        $input['idCharacter'],
        (int) ($input['idLanguage'] ?? 0),
        (string) ($input['name'] ?? '')
    ));
} catch (AetherCharacterLanguageException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('addCharacterLanguage.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon taal niet toevoegen.');
}
