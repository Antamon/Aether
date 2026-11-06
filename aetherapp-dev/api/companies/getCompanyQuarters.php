<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/companyQuarterService.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    if (!aetherCompanyQuarterHistoryAvailable($pdo))
        aetherJsonError(503, 'Voer eerst migratie 0016 voor bedrijfskwartalen uit.');
    $input = aetherReadJsonObject();
    $id = filter_var($input['idCompany'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) aetherJsonError(422, 'Kies een bedrijf.');
    $user = requireCompanyViewAccess($pdo, $id);
    $canEdit = aetherIsPrivilegedRole((string) $user['role']);
    $quarters = aetherCompanyQuarterTimeline($pdo, $id, $canEdit);
    if (!$canEdit) foreach ($quarters as &$quarter) {
        unset($quarter['stability'], $quarter['profitability'], $quarter['snapshotEvent'], $quarter['idEvent']);
        $quarter['events'] = [];
    }
    unset($quarter);
    aetherJsonResponse(['quarters' => $quarters, 'canEdit' => $canEdit]);
} catch (Throwable $error) {
    error_log('getCompanyQuarters failed: ' . $error->getMessage());
    $status = (int) $error->getCode();
    if ($status === 404 || $status === 409) aetherJsonError($status, $error->getMessage());
    if ($error instanceof PDOException && $error->getCode() === '42S02')
        aetherJsonError(503, 'Voer eerst migratie 0016_company_quarters uit.');
    aetherJsonError(500, 'Kon de kwartaalhistoriek niet laden.');
}
