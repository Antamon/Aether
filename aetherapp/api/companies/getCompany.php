<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];
$id = (int) ($postData['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig bedrijf ID.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $company = dbOne(
        $pdo,
        'SELECT id, companyName, description, foundationDate, companyValue, stability, profitability
           FROM tblCompany
          WHERE id = :id',
        ['id' => $id]
    );

    if ($company === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
        exit;
    }

    $company['companyValue'] = round((float) ($company['companyValue'] ?? 0), 2);
    $company['stability'] = normalizeCompanySliderValue($company['stability'] ?? 0);
    $company['profitability'] = normalizeCompanySliderValue($company['profitability'] ?? 0);
    $company = enrichCompanyWithType($company);
    $company['logoUrl'] = getCompanyLogoUrl((int) $company['id']);

    echo json_encode($company);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon bedrijf niet laden.']);
}
