<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);

    echo json_encode([
        'events' => fetchAdminKnowledgeEventOptions($pdo),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de eventlijst voor wereldwijs niet laden.',
        'details' => $e->getMessage(),
    ]);
}
