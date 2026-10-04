<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/characterSchemas.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = aetherReadJsonObject();
    aetherValidateInput($requestData, aetherCharacterRequestSchema('getCharacterList', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    if (aetherIsPrivilegedRole($currentUser['role'])) {
        $characters = dbAll(
            $pdo,
            'SELECT id, idUser, firstName, lastName, type, state, class
               FROM tblCharacter
           ORDER BY firstName, lastName'
        );
    } else {
        $characters = dbAll(
            $pdo,
            'SELECT id, idUser, firstName, lastName, type, state, class
               FROM tblCharacter
              WHERE idUser = :uid
           ORDER BY firstName, lastName',
            ['uid' => (int) $currentUser['id']]
        );
    }

    foreach ($characters as &$character) {
        $character['portraitUrl'] = getCharacterPortraitUrl((int) ($character['id'] ?? 0));
    }
    unset($character);

    aetherJsonResponse($characters);
} catch (Throwable $e) {
    aetherJsonError(500, 'Server error while loading character list.');
}
