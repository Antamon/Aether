<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../events/eventSchemas.php';
require_once __DIR__ . '/../events/eventRepository.php';
require_once __DIR__ . '/adminUtils.php';

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);
    $input = aetherValidateInput(aetherReadJsonObject(), aetherEventRequestSchema('eventId'));
    if (aetherFetchEvent($pdo, (int) $input['idEvent']) === null) aetherJsonError(404, 'Event niet gevonden.');
    aetherJsonResponse(['items' => fetchAdminActionEventUses($pdo, (int) $input['idEvent'])]);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getActionEventUses.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de acties van dit event niet laden.');
}
