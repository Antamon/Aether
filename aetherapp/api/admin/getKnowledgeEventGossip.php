<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idEvent = (int) ($input['idEvent'] ?? 0);

if ($idEvent <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig event geselecteerd.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);

    echo json_encode([
        'groups' => fetchAdminKnowledgeEventGossipGroups($pdo, $idEvent),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de wereldwijsgegevens van dit event niet laden.',
        'details' => $e->getMessage(),
    ]);
}
