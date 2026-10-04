<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterRepository.php';
require_once __DIR__ . '/characterService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('updateCharacter', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$characterId = (int) $input['id'];
$validatedPayload = $input;
unset($input['id']);

try {
    if (array_intersect(['bankaccount', 'securitiesaccount', 'state'], array_keys($input)) !== []) {
        $preflightCharacter = aetherFetchCharacterForUpdate($pdo, $characterId);
        if ($preflightCharacter === null) throw new AetherCharacterUpdateException(404, 'Personage niet gevonden.');
        if (!aetherCanEditCharacter($currentUser, $preflightCharacter)) {
            throw new AetherCharacterUpdateException(403, 'Je hebt geen rechten om dit personage te wijzigen.');
        }
        aetherPrepareCharacterUpdate($pdo, $currentUser, $preflightCharacter, $input);
        $key = aetherRequireIdempotencyKey();
        $rowCount = aetherRunIdempotentMutation(
            $pdo,
            $currentUser,
            'character.direct_balance_update',
            $key,
            $validatedPayload,
            static fn(): int => aetherUpdateCharacterUseCase($pdo, $currentUser, $characterId, $input, true)
        );
    } else {
        $rowCount = aetherUpdateCharacterUseCase($pdo, $currentUser, $characterId, $input);
    }
    aetherJsonResponse($rowCount);
} catch (AetherCharacterUpdateException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('updateCharacter failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon character niet bijwerken.');
}
