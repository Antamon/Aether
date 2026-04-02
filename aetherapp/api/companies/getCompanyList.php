<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $companies = dbAll(
        $pdo,
        'SELECT id, companyName
           FROM tblCompany
       ORDER BY companyName ASC, id ASC'
    );

    echo json_encode($companies);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon bedrijvenlijst niet laden.']);
}
