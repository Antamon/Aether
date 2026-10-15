<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/shareSearchService.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        aetherJsonResponse(['errorCode' => 'shares.method_not_allowed', 'error' => 'Gebruik GET.'], 405);
    }
    $user = aetherRequireAuthenticatedUser($pdo);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $id = filter_input(INPUT_GET, 'idCharacter', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new AetherShareException(400, 'Personage ontbreekt.');
    $eventId = filter_input(INPUT_GET, 'idEvent', FILTER_VALIDATE_INT);
    if (isset($_GET['idEvent']) && (!$eventId || $eventId < 1)) {
        throw new AetherShareException(400, 'Ongeldig event.');
    }
    aetherJsonResponse(aetherShareViewSearch($pdo, $user, $id, (int) ($eventId ?: 0)));
} catch (AetherShareException $e) {
    aetherJsonResponse(['errorCode' => $e->getErrorCode(), 'error' => $e->getMessage()], $e->getHttpStatus());
} catch (Throwable $e) {
    error_log('getCharacterShareSearch failed: ' . $e->getMessage());
    aetherJsonError(500, 'De aandelenzoekactie is niet beschikbaar.');
}
