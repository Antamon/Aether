<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterLanguageUtils.php';
require_once __DIR__ . '/characterSchemas.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('deleteCharacterLanguage', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idCharacter = $input['idCharacter'];
$idCharacterLanguage = $input['idCharacterLanguage'];

try {
    if (!characterLanguageSchemaReady($pdo)) {
        aetherJsonResponse(['success' => true]);
    }

    $character = aetherFetchCharacterAccessRecord($pdo, $idCharacter);
    if ($character === null) {
        aetherJsonError(404, 'Personage niet gevonden.');
    }
    if (!canCurrentUserManageCharacterLanguages($character, $currentUser['role'], (int) $currentUser['id'])) {
        aetherJsonError(403, 'Geen rechten om talen te beheren.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM tblCharacterLanguage
          WHERE id = :id
            AND idCharacter = :idCharacter'
    );
    $stmt->execute([
        'id' => $idCharacterLanguage,
        'idCharacter' => $idCharacter,
    ]);

    if ($stmt->rowCount() < 1) {
        aetherJsonError(404, 'Taal-link niet gevonden.');
    }

    aetherJsonResponse(['success' => true]);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon taal niet verwijderen.');
}
