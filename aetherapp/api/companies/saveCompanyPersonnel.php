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
    $input = aetherValidateCompanyPersonnelRequest(aetherReadJsonObject());
    $requestKey = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation(
        $pdo, $currentUser, 'company.personnel.save', $requestKey, $input,
        static fn(): array => aetherSaveCompanyPersonnel($pdo, $input),
        static function () use ($pdo, $input): void {
            requirePrivilegedCompanyAccess($pdo);
            aetherCompanyRequireExisting($pdo, $input['idCompany'], true);
        }
    );
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCompanyException|AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('saveCompanyPersonnel failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon het bedrijfspersoneel niet bewaren.');
}
