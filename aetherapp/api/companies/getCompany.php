<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyService.php';
require_once __DIR__ . '/companySchemas.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);
    $input = aetherValidateInput(aetherReadJsonObject(), aetherCompanyDetailSchema());
    $company = getCompanyDetailData($pdo, $input['id']);
    if ($company === null) {
        aetherJsonError(404, 'Bedrijf niet gevonden.');
    }
    aetherJsonResponse($company);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getCompany failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijf niet laden.');
}
