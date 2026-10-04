<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyService.php';
require_once __DIR__ . '/companyFinanceSchemas.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';

try {
    $pdo = getPDO();
    $currentUser = requirePrivilegedCompanyAccess($pdo, true);
    $input = aetherValidateInput(aetherReadJsonObject(), aetherCompanyUpdateSchema());
    $id = (int) $input['id'];
    unset($input['id']);
    if ($input === []) throw new AetherCompanyException(400, 'Geen velden om bij te werken.');
    $requestKey = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo, $currentUser, 'company.update', $requestKey, ['id' => $id] + $input,
        static fn(): array => aetherUpdateCompany($pdo, $id, $input),
        static function () use ($pdo, $id): void {
            requirePrivilegedCompanyAccess($pdo);
            aetherCompanyRequireExisting($pdo, $id, true);
        }
    );
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCompanyException|AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('updateCompany failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijf niet bewaren.');
}
