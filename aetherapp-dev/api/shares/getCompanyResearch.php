<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/companyResearchService.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') aetherJsonError(405, 'Gebruik GET.');
    $user = aetherRequireAuthenticatedUser($pdo);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $ids = [];
    foreach (['idCharacter', 'idCompany', 'idEvent'] as $field) {
        $ids[$field] = filter_input(INPUT_GET, $field, FILTER_VALIDATE_INT);
        if (!$ids[$field] || $ids[$field] < 1) throw new AetherShareException(422, 'Kies een personage, bedrijf en event.');
    }
    aetherJsonResponse(aetherCompanyResearchView($pdo, $user, $ids['idCharacter'], $ids['idCompany'], $ids['idEvent']));
} catch (AetherShareException $error) {
    aetherJsonResponse(['error' => $error->getMessage(), 'errorCode' => $error->getErrorCode()], $error->getHttpStatus());
} catch (Throwable $error) {
    error_log('getCompanyResearch: ' . $error->getMessage());
    aetherJsonError(500, 'Kon de bedrijfsinformatie niet laden.');
}
