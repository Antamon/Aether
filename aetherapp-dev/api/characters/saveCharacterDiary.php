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
require_once __DIR__ . '/characterDiaryRepository.php';
require_once __DIR__ . '/characterDiaryService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('saveCharacterDiary', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    aetherJsonResponse(aetherSaveCharacterDiary($pdo, $currentUser, $input));
} catch (AetherCharacterDiaryException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('saveCharacterDiary.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon diary niet opslaan.');
}
