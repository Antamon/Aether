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
require_once __DIR__ . '/characterTraitRepository.php';
require_once __DIR__ . '/characterTraitService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput($requestData, aetherCharacterRequestSchema('updateTrait', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM tblCharacter WHERE id = :idCharacter FOR UPDATE');
    $lock->execute(['idCharacter' => $input['idCharacter']]);
    $response = aetherUpdateCharacterTrait(
        $pdo,
        $currentUser,
        $input['idCharacter'],
        $input['idTrait'],
        (int) ($input['idCurrentTrait'] ?? 0),
        $input['action']
    );
    $pdo->commit();
    aetherJsonResponse($response);
} catch (AetherCharacterTraitException|AetherShareException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('updateTrait.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon trait niet bijwerken.');
}
