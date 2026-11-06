<?php
declare(strict_types=1);
$fixture = sys_get_temp_dir() . '/aether-company-view-' . bin2hex(random_bytes(5));
mkdir($fixture . '/companies', 0777, true);
mkdir($fixture . '/auth');
copy(__DIR__ . '/../api/companies/companyAccess.php', $fixture . '/companies/companyAccess.php');
file_put_contents($fixture . '/auth/accessControl.php', <<<'PHP'
<?php
function aetherRequireAuthenticatedUser(PDO $pdo): array { return $GLOBALS['viewUser']; }
function aetherIsPrivilegedRole(string $role): bool { return in_array($role, ['administrator', 'director'], true); }
function aetherJsonError(int $status, string $message): never { throw new RuntimeException($message, $status); }
PHP);
require $fixture . '/companies/companyAccess.php';
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE tblCharacter (id INTEGER, idUser INTEGER);
    CREATE TABLE tblCompanyPersonnel (idCompany INTEGER, idCharacter INTEGER, isBoardMember INTEGER);
    CREATE TABLE tblCompanyShareHolding (idCompany INTEGER, idCharacter INTEGER, shareCount INTEGER);
    INSERT INTO tblCharacter VALUES (10, 1), (20, 2);
    INSERT INTO tblCompanyPersonnel VALUES (1, 10, 0), (2, 10, 1), (3, 20, 1);
    INSERT INTO tblCompanyShareHolding VALUES (4, 10, 2), (5, 10, 0);');
function viewCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$viewUser = ['id' => 1, 'role' => 'participant'];
foreach ([1, 2, 4] as $id) viewCheck(requireCompanyViewAccess($pdo, $id) === $viewUser, 'Linked user denied');
foreach ([3, 5, 99] as $id) {
    try { requireCompanyViewAccess($pdo, $id); throw new LogicException('Unlinked user allowed'); }
    catch (RuntimeException $error) { viewCheck($error->getCode() === 403, 'Expected forbidden response'); }
}
viewCheck(!aetherCompanyUserIsBoardMember($pdo, $viewUser, 1), 'Ordinary employee received board rights');
viewCheck(aetherCompanyUserIsBoardMember($pdo, $viewUser, 2), 'Board membership missing');
viewCheck(!aetherCompanyUserIsBoardMember($pdo, $viewUser, 3), 'Another user board membership leaked');
foreach (['director', 'administrator'] as $role) {
    $viewUser['role'] = $role;
    viewCheck(requireCompanyViewAccess($pdo, 99) === $viewUser, 'Manager access changed');
}
unlink($fixture . '/auth/accessControl.php'); unlink($fixture . '/companies/companyAccess.php');
rmdir($fixture . '/auth'); rmdir($fixture . '/companies'); rmdir($fixture);
echo "Company view: employee, shareholder, board, unrelated user and manager access checks passed.\n";
