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
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/characterPortraitImage.php';
require_once __DIR__ . '/characterPortraitService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadFormFields();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('uploadCharacterPortrait', $requestData));
    $unexpectedFiles = array_diff(array_keys($_FILES), ['portrait']);
    if ($unexpectedFiles !== []) {
        throw new AetherValidationException(['Onverwacht uploadveld: ' . implode(', ', $unexpectedFiles) . '.']);
    }
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

if (!isset($_FILES['portrait']) || !is_array($_FILES['portrait'])) {
    aetherJsonError(400, 'Geen portret ontvangen.');
}

try {
    aetherJsonResponse(aetherUploadCharacterPortrait($pdo, $currentUser, $input['id'], $_FILES['portrait']));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCharacterPortraitException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('uploadCharacterPortrait.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon portret niet bewaren.');
}
