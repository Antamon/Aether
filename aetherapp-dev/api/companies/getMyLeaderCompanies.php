<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/companyPersonnelActions.php';
require_once __DIR__ . '/../shared/response.php';

try {
    $pdo = getPDO();
    $user = aetherRequireAuthenticatedUser($pdo);
    if (!aetherCompanyLeadershipAvailable($pdo)) {
        aetherJsonResponse(['companies' => []]);
    }
    $stmt = $pdo->prepare("SELECT DISTINCT co.id, co.companyName
        FROM tblCompanyPersonnel AS cp
        JOIN tblCompany AS co ON co.id = cp.idCompany
        JOIN tblCharacter AS c ON c.id = cp.idCharacter
        WHERE c.idUser = :idUser AND EXISTS (
            SELECT 1 FROM tblLinkCharacterTrait AS link
            JOIN tblTrait AS trait ON trait.id = link.idTrait
            WHERE link.idCharacter = c.id AND trait.`class` = 'middle class'
              AND trait.`type` = 'profession'
              AND trait.name IN ('Bedrijfsleider', 'Bedrijfsleider klein', 'Bedrijfsleider groot'))
        ORDER BY co.companyName, co.id");
    $stmt->execute(['idUser' => (int) $user['id']]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as &$company) {
        $company['logoUrl'] = getCompanyLogoUrl((int) $company['id']);
    }
    unset($company);
    aetherJsonResponse(['companies' => $companies]);
} catch (Throwable $e) {
    error_log('getMyLeaderCompanies failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon de geleide bedrijven niet laden.');
}
