<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/accessControl.php';

function aetherCompanyUserIsBoardMember(PDO $pdo, array $user, int $idCompany): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM tblCompanyPersonnel cp JOIN tblCharacter c ON c.id = cp.idCharacter
        WHERE cp.idCompany = :idCompany AND c.idUser = :idUser AND cp.isBoardMember = 1 LIMIT 1');
    $stmt->execute(['idCompany' => $idCompany, 'idUser' => (int) $user['id']]);
    return $stmt->fetchColumn() !== false;
}

function requireCompanyViewAccess(PDO $pdo, int $idCompany): array
{
    $user = aetherRequireAuthenticatedUser($pdo);
    if (aetherIsPrivilegedRole((string) $user['role'])) return $user;
    $stmt = $pdo->prepare('SELECT 1 FROM tblCharacter c WHERE c.idUser = :idUser AND (
        EXISTS (SELECT 1 FROM tblCompanyPersonnel cp WHERE cp.idCharacter = c.id AND cp.idCompany = :employer)
        OR EXISTS (SELECT 1 FROM tblCompanyShareHolding h WHERE h.idCharacter = c.id AND h.idCompany = :holding AND h.shareCount > 0)) LIMIT 1');
    $stmt->execute(['idUser' => (int) $user['id'], 'employer' => $idCompany, 'holding' => $idCompany]);
    if ($stmt->fetchColumn() === false) aetherJsonError(403, 'Geen toegang tot dit bedrijf.');
    return $user;
}

/** Company management has always been reserved for directors and administrators.
 * Personnel, character or share links do not grant management access.
 */
function requirePrivilegedCompanyAccess(PDO $pdo, bool $requireCsrf = false): array
{
    $user = aetherRequireAuthenticatedUser($pdo);
    if (!aetherIsPrivilegedRole($user['role'])) {
        aetherJsonError(403, 'Je hebt geen rechten om bedrijven te beheren.');
    }
    if ($requireCsrf) {
        aetherRequireCsrfToken();
    }
    return $user;
}
