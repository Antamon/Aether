<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/companyPersonnelActions.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    $user = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCsrfToken();
    $input = aetherCompanyPersonnelActionInput(aetherReadJsonObject());
    $key = aetherRequireIdempotencyKey();
    $response = aetherRunIdempotentMutation($pdo, $user, 'company.personnel.action', $key, $input,
        static fn(): array => aetherCompanyPersonnelAction($pdo, $user, $input),
        static function () use ($pdo, $user, $input): void {
            if (!aetherCompanyPersonnelActorCanEdit($pdo, $user, $input['idCompany'])) {
                throw new AetherCompanyException(403, 'Geen rechten voor dit bedrijf.');
            }
        });
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCompanyException|AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('saveCompanyPersonnelAction failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon personeelswijziging niet bewaren.');
}
