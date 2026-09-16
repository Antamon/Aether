<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idActionUse = (int) ($input['idActionUse'] ?? 0);

if ($idActionUse <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldige actie geselecteerd.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo, true);

    deleteAdminActionUse($pdo, $idActionUse);

    echo json_encode([
        'ok' => true,
        'idActionUse' => $idActionUse,
    ]);
} catch (Throwable $e) {
    $isRuntime = $e instanceof RuntimeException;
    http_response_code($isRuntime ? 400 : 500);
    echo json_encode([
        'error' => $isRuntime ? $e->getMessage() : 'Kon deze actie niet verwijderen.',
        'details' => $isRuntime ? null : $e->getMessage(),
    ]);
}
