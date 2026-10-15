<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/shareService.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        aetherJsonResponse(['errorCode' => 'shares.method_not_allowed', 'error' => 'Gebruik GET.'], 405);
    }
    $user = aetherRequireAuthenticatedUser($pdo);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $id = filter_input(INPUT_GET, 'idCompany', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new AetherShareException(400, 'Bedrijf ontbreekt.');
    aetherJsonResponse(aetherShareViewCompany($pdo, $user, $id));
} catch (AetherShareException $e) {
    if (basename(dirname(__DIR__, 2)) === 'aetherapp-dev') {
        @error_log('[' . date('c') . '] getCompanyShares rejected (' . $e->getHttpStatus()
            . '): ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/share-errors.log');
    }
    aetherJsonResponse(['errorCode' => $e->getErrorCode(), 'error' => $e->getMessage()], $e->getHttpStatus());
} catch (Throwable $e) {
    $logMessage = 'getCompanyShares failed: ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($logMessage);
    if (basename(dirname(__DIR__, 2)) === 'aetherapp-dev') {
        @error_log('[' . date('c') . '] ' . $logMessage . PHP_EOL, 3, __DIR__ . '/share-errors.log');
    }
    aetherJsonError(500, 'Aandelengegevens zijn niet beschikbaar.');
}
