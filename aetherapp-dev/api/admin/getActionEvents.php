<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/adminUtils.php';

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);
    aetherJsonResponse(['events' => fetchAdminActionEventOptions($pdo)]);
} catch (Throwable $e) {
    error_log('getActionEvents.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de eventlijst voor acties niet laden.');
}
