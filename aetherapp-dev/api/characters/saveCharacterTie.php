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
require_once __DIR__ . '/characterTieRepository.php';
require_once __DIR__ . '/characterTieService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('saveCharacterTie', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    $ownerCharacter = aetherFetchCharacterAccessRecord($pdo, $input['idCharacter']);
    if ($ownerCharacter === null) {
        aetherJsonError(404, 'Character niet gevonden.');
    }
    if (!aetherCanEditCharacter($currentUser, $ownerCharacter)) {
        aetherJsonError(403, 'Geen rechten om deze tie te wijzigen.');
    }

    $result = aetherSaveCharacterTie($pdo, $currentUser, $ownerCharacter, $input);
    aetherJsonResponse([
        'success' => true,
        'ties' => aetherBuildCharacterTieList($pdo, $input['idCharacter']),
        'syncedAddress' => $result['syncedAddress'],
    ]);
} catch (AetherCharacterTieException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('saveCharacterTie.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon tie niet opslaan.');
}
