<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$companyName = trim((string) ($postData['companyName'] ?? ''));
if ($companyName === '') {
    http_response_code(400);
    echo json_encode(['error' => 'De bedrijfsnaam is verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $foundationDate = getDefaultCompanyFoundationDate();

    $stmt = $pdo->prepare(
        'INSERT INTO tblCompany (
            companyName,
            description,
            foundationDate,
            companyValue,
            stability,
            profitability
        ) VALUES (
            :companyName,
            :description,
            :foundationDate,
            :companyValue,
            :stability,
            :profitability
        )'
    );

    $stmt->execute([
        'companyName' => $companyName,
        'description' => '',
        'foundationDate' => $foundationDate,
        'companyValue' => 0,
        'stability' => 0,
        'profitability' => 0,
    ]);

    echo json_encode([
        'id' => (int) $pdo->lastInsertId(),
        'companyName' => $companyName,
        'description' => '',
        'foundationDate' => $foundationDate,
        'companyValue' => 0,
        'stability' => 0,
        'profitability' => 0,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon bedrijf niet aanmaken.']);
}
