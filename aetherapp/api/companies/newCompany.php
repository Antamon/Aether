<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyService.php';
require_once __DIR__ . '/companySchemas.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/idempotency.php';

try {
    $pdo = getPDO();
    $currentUser = requirePrivilegedCompanyAccess($pdo, true);
    $input = aetherValidateInput(aetherReadJsonObject(), aetherCompanyCreateSchema());
    $requestKey = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo, $currentUser, 'company.create', $requestKey, $input,
        static fn(): array => aetherCreateCompany($pdo, $input['companyName']),
        static function () use ($pdo): void { requirePrivilegedCompanyAccess($pdo); }
    );
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('newCompany failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijf niet aanmaken.');
}
