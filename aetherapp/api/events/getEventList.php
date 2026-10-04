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

try {
    $input = aetherValidateInput(aetherReadJsonObject(), aetherEventRequestSchema('list'));
    aetherJsonResponse(aetherGetEventList($pdo, $currentUser, (int) $input['idUser']));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherEventException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('getEventList.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon eventlijst niet laden.');
}
