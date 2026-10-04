<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/../events/eventSchemas.php';
require_once __DIR__ . '/../events/eventAccess.php';
require_once __DIR__ . '/adminUtils.php';

$pdo = getPDO();
$currentUser = aetherRequirePrivilegedUser($pdo);
aetherRequireCsrfToken();

try {
    $input = aetherValidateInput(aetherReadJsonObject(), aetherEventRequestSchema('adminActionUseDelete'));
    $requestKey = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo,
        $currentUser,
        'event.admin.delete_action_use',
        $requestKey,
        $input,
        static function () use ($pdo, $input): array {
            if (fetchAdminActionUseDetail($pdo, (int) $input['idActionUse']) === null) {
                throw new AetherEventException(404, 'Actie niet gevonden.');
            }
            deleteAdminActionUse($pdo, (int) $input['idActionUse']);
            return ['ok' => true, 'idActionUse' => (int) $input['idActionUse']];
        },
        static function () use ($currentUser): void {
            if (!aetherIsPrivilegedRole((string) $currentUser['role'])) {
                throw new AetherEventException(403, 'Je hebt geen rechten om deze actie te beheren.');
            }
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
    if ($e instanceof RuntimeException && !$e instanceof PDOException) {
        aetherJsonResponse(['error' => $e->getMessage(), 'details' => null], 400);
    }
    error_log('deleteActionUse.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon deze actie niet verwijderen.');
}
