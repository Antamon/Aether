<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/adminAccess.php';
require_once __DIR__ . '/adminSchemas.php';
require_once __DIR__ . '/adminSkillService.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/idempotency.php';
try {
    $pdo = getPDO();
    $currentUser = aetherRequireAdminEditor($pdo);
    aetherRequireCsrfToken();
    $data = aetherValidateAdminRequest('saveSkill', aetherReadJsonObject());
    $requestKey = aetherRequireIdempotencyKey();
    if (!aetherAdminSkillRequestAlreadyClaimed($pdo, $currentUser['id'], $requestKey)) {
        aetherAdminPreflightSaveSkill($pdo, $data);
    }
    $result = aetherRunIdempotentMutation(
        $pdo, $currentUser, 'admin.saveSkill', $requestKey, $data,
        static fn(): array => aetherAdminSaveSkill($pdo, $data, false),
        static function () use ($pdo, $data): void {
            if (aetherAdminSkillRow($pdo, $data['idSkill']) === null) throw new AdminSkillProblem(404, 'Vaardigheid niet gevonden.');
        }
    );
    aetherJsonResponse($result);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AdminSkillProblem $e) {
    if ($e->status === 422) aetherJsonValidationError([$e->getMessage()]);
    aetherJsonError($e->status, $e->getMessage());
} catch (AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('saveSkill: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de vaardigheid niet bewaren.');
}
