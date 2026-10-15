<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/eventDetailService.php';
$user = aetherRequireAuthenticatedUser($pdo);
try {
    $multipart = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
    $raw = $multipart ? aetherReadFormFields() : aetherReadJsonObject();
    $action = $raw['action'] ?? 'read';
    $schema = [
        'action' => ['type' => 'enum', 'values' => ['read', 'gossip', 'snapshot', 'link', 'upload', 'deleteImage'], 'default' => 'read'],
        'idEvent' => ['type' => 'int', 'min' => 1, 'required' => true],
    ];
    if ($action === 'link') $schema += [
        'idCharacter' => ['type' => 'int', 'min' => 1, 'required' => true],
        'linked' => ['type' => 'bool', 'required' => true],
    ];
    if (in_array($action, ['gossip', 'snapshot'], true)) $schema['idCharacter'] = ['type' => 'int', 'min' => 1, 'required' => true];
    $input = aetherValidateInput($raw, $schema);
    if (in_array($action, ['gossip', 'snapshot'], true)) {
        aetherJsonResponse(aetherGetEventCharacterData($pdo, $user, $input['idEvent'], $input['idCharacter'], $action));
    }
    if ($action !== 'read') {
        aetherRequireCsrfToken();
        if ($action !== 'link') aetherRequireEventManager($user);
        if (!aetherFetchEvent($pdo, $input['idEvent'])) throw new AetherEventException(404, 'Event niet gevonden.');
    }
    if ($action === 'link') aetherSetEventCharacter($pdo, $user, $input['idEvent'], $input['idCharacter'], $input['linked']);
    if ($action === 'upload') {
        if (array_keys($_FILES) !== ['image']) throw new AetherEventException(422, 'Kies één eventafbeelding.');
        aetherUploadEventImage($input['idEvent'], $_FILES['image']);
    }
    if ($action === 'deleteImage') {
        $path = aetherEventImageDirectory() . '/' . $input['idEvent'] . '.png';
        if (is_file($path) && !unlink($path)) throw new RuntimeException('Afbeelding niet verwijderd.');
    }
    if ($action === 'read') aetherJsonResponse(aetherGetEventDetails($pdo, $user, $input['idEvent']));
    aetherJsonResponse(['ok' => true, 'imageUrl' => aetherEventImageUrl($input['idEvent'])]);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherEventException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('eventDetails failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon eventgegevens niet verwerken.');
}
