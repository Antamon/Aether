<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/eventSchemas.php';
require_once __DIR__ . '/eventService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $input = aetherValidateInput(aetherReadJsonObject(), aetherEventRequestSchema('create'));
    aetherJsonResponse(aetherCreateEvent($pdo, $currentUser, $input));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherEventException $e) {
    if ($e->getHttpStatus() === 422) aetherJsonValidationError([$e->getMessage()]);
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('newEvent.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon event niet aanmaken.');
}
