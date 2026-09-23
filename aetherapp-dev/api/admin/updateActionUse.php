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
    $input = aetherValidateInput(aetherReadJsonObject(), aetherEventRequestSchema('adminActionUseUpdate'));
    foreach (['rollBase', 'rollFinal'] as $field) {
        if ($input[$field] !== '' && preg_match('/^-?\d+$/D', (string) $input[$field]) !== 1) {
            throw new AetherValidationException([["field" => $field, 'code' => 'invalid_type', 'message' => "Veld {$field} moet leeg of een geheel getal zijn."]]);
        }
    }
    if (fetchAdminActionUseDetail($pdo, (int) $input['idActionUse']) === null) {
        aetherJsonError(404, 'Actie niet gevonden.');
    }
    $requestKey = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo,
        $currentUser,
        'event.admin.update_action_use',
        $requestKey,
        $input,
        static function () use ($pdo, $input): array {
            $action = updateAdminActionUse($pdo, (int) $input['idActionUse'], $input);
            if ($action === null) throw new RuntimeException('Actie niet gevonden.');
            return ['ok' => true, 'item' => $action];
        },
        static function () use ($pdo, $currentUser, $input): void {
            if (!aetherIsPrivilegedRole((string) $currentUser['role'])
                || fetchAdminActionUseDetail($pdo, (int) $input['idActionUse']) === null) {
                throw new AetherEventException(403, 'Deze actie is niet langer toegankelijk.');
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
    error_log('updateActionUse.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon deze actie niet bewaren.');
}
