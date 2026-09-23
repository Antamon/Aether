<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyLogoService.php';
require_once __DIR__ . '/companySchemas.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo, true);
    $input = aetherValidateInput(aetherReadFormFields(), aetherCompanyDetailSchema());
    if (array_keys($_FILES) !== ['logo']) {
        throw new AetherValidationException(['Verwacht precies één logobestand.']);
    }
    aetherJsonResponse(aetherUploadCompanyLogo($pdo, $input['id'], $_FILES['logo']));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherCompanyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    error_log('uploadCompanyLogo failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijfslogo niet bewaren.');
}
