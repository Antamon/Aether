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
        'SELECT id
           FROM tblCompany
          WHERE id = :id',
        ['id' => $id]
    );

    if ($company === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
        exit;
    }

    $logoPath = getCompanyLogoAbsolutePath($id);
    if (is_file($logoPath) && !unlink($logoPath)) {
        throw new RuntimeException('Kon logo niet verwijderen.');
    }

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon bedrijfslogo niet verwijderen.']);
}
