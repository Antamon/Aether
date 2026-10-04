<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/../events/eventSchemas.php';
require_once __DIR__ . '/../events/eventKnowledgeService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $input = aetherValidateInput(aetherReadJsonObject(), aetherEventRequestSchema('deleteKnowledgeUnlock'));
    aetherAuthorizeKnowledgeUnlockReplay($pdo, $currentUser, $input);
    $requestKey = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo,
        $currentUser,
        'event.knowledge.delete_unlock',
        $requestKey,
        $input,
        static fn(): array => aetherDeleteEventKnowledgeUnlock($pdo, $currentUser, $input),
        static function () use ($pdo, $currentUser, $input): void {
            aetherAuthorizeKnowledgeUnlockReplay($pdo, $currentUser, $input);
        }
    );
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherEventException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('deleteKnowledgeUnlock.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon deze wereldwijsontdekking niet verwijderen.');
}
