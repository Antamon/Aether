<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterFinanceService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();
try {
    $request = aetherReadJsonObject();
    $input = aetherValidateInput($request, aetherCharacterRequestSchema('saveCharacterEconomySnapshot', $request));
    $key = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo, $currentUser, 'character.economy_snapshot.create', $key, $input,
        fn(): array => aetherCreateEconomySnapshot($pdo, $currentUser, $input),
        static function () use ($pdo, $currentUser, $input): void {
            aetherAuthorizeEconomySnapshotReplay($pdo, $currentUser, (int) $input['idCharacter']);
        }
    );
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherFinanceException|AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('saveCharacterEconomySnapshot failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon economiesnapshot niet bewaren.');
}
