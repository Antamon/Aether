<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyAccess.php';
require_once __DIR__ . '/companyRepository.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    aetherJsonResponse(aetherCompanyList($pdo));
} catch (Throwable $e) {
    error_log('getCompanyList failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon bedrijvenlijst niet laden.');
}
