<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/companyPersonnelActions.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    $user = aetherRequireAuthenticatedUser($pdo);
    $input = aetherValidateInput(aetherReadJsonObject(), aetherCompanyDetailSchema());
    if (!aetherCompanyPersonnelActorCanEdit($pdo, $user, $input['id'])) {
        aetherJsonError(403, 'Je hebt geen rechten om dit bedrijfspersoneel te bekijken.');
    }
    aetherJsonResponse(aetherCompanyPersonnelLimitedView($pdo, $input['id']));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getCompanyPersonnelForLeader failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijfspersoneel niet laden.');
}
