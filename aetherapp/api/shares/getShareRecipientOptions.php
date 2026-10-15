<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/shareDiagnostics.php';
aetherRegisterShareEndpointFatalLog('getShareRecipientOptions');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/shareSearchService.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        aetherJsonResponse(['errorCode' => 'shares.method_not_allowed', 'error' => 'Gebruik GET.'], 405);
    }
    $user = aetherRequireAuthenticatedUser($pdo);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $id = filter_input(INPUT_GET, 'idCharacter', FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 0) throw new AetherShareException(400, 'Personage ontbreekt.');
    aetherJsonResponse(['recipients' => aetherShareRecipientOptions($pdo, $user, $id)]);
} catch (AetherShareException $e) {
    aetherJsonResponse(['errorCode' => $e->getErrorCode(), 'error' => $e->getMessage()], $e->getHttpStatus());
} catch (Throwable $e) {
    aetherLogShareEndpointFailure('getShareRecipientOptions', $e);
    aetherJsonError(500, 'Ontvangers zijn niet beschikbaar.');
}
