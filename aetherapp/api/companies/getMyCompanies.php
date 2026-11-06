<?php
declare(strict_types=1);
require_once __DIR__ . '/companyAccess.php';
require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    $user = aetherRequireAuthenticatedUser($pdo);
    $stmt = $pdo->prepare("SELECT DISTINCT co.id, co.companyName
        FROM tblCompany co WHERE EXISTS (
            SELECT 1 FROM tblCharacter c WHERE c.idUser = :idUser AND (
                EXISTS (SELECT 1 FROM tblCompanyPersonnel cp WHERE cp.idCompany = co.id AND cp.idCharacter = c.id)
                OR EXISTS (SELECT 1 FROM tblCompanyShareHolding h WHERE h.idCompany = co.id AND h.idCharacter = c.id AND h.shareCount > 0)))
        ORDER BY co.companyName, co.id");
    $stmt->execute(['idUser' => (int) $user['id']]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as &$company) $company['logoUrl'] = getCompanyLogoUrl((int) $company['id']);
    unset($company);
    aetherJsonResponse(['companies' => $companies]);
} catch (Throwable $e) {
    error_log('getMyCompanies failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de gekoppelde bedrijven niet laden.');
}
