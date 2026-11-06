<?php
declare(strict_types=1);

// Exercise the real mutation, idempotency, ownership queries and dividend processing.
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-company-shares-' . bin2hex(random_bytes(5));
foreach (['shares', 'shared', 'companies', 'characters'] as $folder) mkdir($fixture . '/' . $folder, 0777, true);
foreach (['shares/shareService.php', 'shares/shareRepository.php', 'shares/shareOwnershipRepository.php',
    'shares/shareCompanyOwnershipService.php', 'shares/shareRules.php', 'shares/shareException.php',
    'shares/shareDividendRules.php', 'shares/sharePurchaseHistory.php', 'shares/shareMarketEvent.php',
    'shared/decimal.php', 'shared/idempotency.php', 'shared/response.php', 'companies/companySnapshotService.php'] as $file) {
    copy($root . '/api/' . $file, $fixture . '/' . $file);
}
file_put_contents($fixture . '/shares/shareAccess.php', <<<'PHP'
<?php
require_once __DIR__ . '/shareException.php';
function aetherRequireShareMigration(PDO $pdo): void {}
function aetherCanManageShareCompany(array $user): bool { return in_array($user['role'], ['administrator', 'director'], true); }
function aetherShareLegacyWritesClosed(PDO $pdo): bool { return true; }
PHP);
file_put_contents($fixture . '/companies/companyUtils.php', <<<'PHP'
<?php
function dbAll(PDO $pdo, string $sql, array $params): array { $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC); }
function aetherFinanceDecimal(mixed $value): string { return aetherNormalizeDecimal((string) $value); }
function aetherFinanceLockCharacters(PDO $pdo, array $ids): array { return []; }
PHP);
foreach (['shares/shareDraftService.php', 'characters/characterFinanceRepository.php', 'companies/companyLeadershipRules.php',
    'characters/companyShareUtils.php', 'characters/characterFinanceService.php', 'companies/companyQuarterService.php'] as $file) {
    file_put_contents($fixture . '/' . $file, '<?php');
}
require $fixture . '/shares/shareService.php';
require $fixture . '/companies/companySnapshotService.php';

class CompanyOwnershipPdo extends PDO
{
    public function exec(string $statement): int|false {
        return str_starts_with($statement, 'SET TRANSACTION') ? 0 : parent::exec($statement);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $query = str_replace([' FOR UPDATE', 'NOW()'], ['', 'CURRENT_TIMESTAMP'], $query);
        $query = str_replace('DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 7 YEAR)', "datetime('now', '+7 years')", $query);
        $query = str_replace('ON DUPLICATE KEY UPDATE id = id', 'ON CONFLICT(idUser, operation, requestKey) DO NOTHING', $query);
        $query = str_replace('ON DUPLICATE KEY UPDATE shareCount = shareCount + VALUES(shareCount)',
            'ON CONFLICT(idCompany, idHoldingCompany) DO UPDATE SET shareCount = shareCount + excluded.shareCount', $query);
        return parent::prepare($query, $options);
    }
}
function ownershipCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = new CompanyOwnershipPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE tblSchemaMigration(migrationCode TEXT); INSERT INTO tblSchemaMigration VALUES('0023_company_share_ownership');
CREATE TABLE tblCompany(id INTEGER PRIMARY KEY, companyValue NUMERIC, initialCompanyValue NUMERIC, initialShareCount INTEGER,
    issuedShareCount INTEGER, isShareListed INTEGER, foundationDate TEXT);
INSERT INTO tblCompany VALUES(3,1000,1000,100,100,1,'1900-01-01'),(9,500,500,NULL,0,0,'1900-01-01'),(10,777,777,100,100,1,'1999-01-01');
CREATE TABLE tblCharacter(id INTEGER PRIMARY KEY, bankaccount NUMERIC);INSERT INTO tblCharacter VALUES(9,1234.56);
CREATE TABLE tblCompanyShareHolding(idCompany INTEGER,idCharacter INTEGER,shareCount INTEGER);INSERT INTO tblCompanyShareHolding VALUES(3,9,10);
CREATE TABLE tblCompanyAnonymousShares(idCompany INTEGER,shareCount INTEGER);INSERT INTO tblCompanyAnonymousShares VALUES(3,20);
CREATE TABLE tblCompanyShareCompanyHolding(idCompany INTEGER,idHoldingCompany INTEGER,shareCount INTEGER CHECK(shareCount>0),PRIMARY KEY(idCompany,idHoldingCompany));
CREATE TABLE tblCompanyShareCompanyTransaction(id INTEGER PRIMARY KEY AUTOINCREMENT,idCompany INTEGER,idHoldingCompany INTEGER,actorUserId INTEGER,
    shareCount INTEGER,purchaseYear INTEGER,purchaseQuarter INTEGER,issuerValueBefore NUMERIC,issuerValueAfter NUMERIC,holderValueBefore NUMERIC,holderValueAfter NUMERIC,requestKey TEXT);
CREATE TABLE tblApiIdempotency(id INTEGER PRIMARY KEY AUTOINCREMENT,idUser INTEGER,operation TEXT,requestKey TEXT,payloadHash TEXT,status TEXT,
    responseStatus INTEGER,responseJson TEXT,createdAt TEXT,updatedAt TEXT,expiresAt TEXT,UNIQUE(idUser,operation,requestKey));
CREATE TABLE tblCompanySnapshotPayout(id INTEGER PRIMARY KEY,idCompanySnapshot INTEGER,idCharacter INTEGER,amount NUMERIC,transactionDate TEXT,description TEXT);
CREATE TABLE tblCompanySnapshotCompanyPayout(idCompanySnapshot INTEGER,idHoldingCompany INTEGER,shareCount INTEGER,amount NUMERIC,PRIMARY KEY(idCompanySnapshot,idHoldingCompany));
SQL);
$manager = ['id' => 1, 'role' => 'director'];
$input = ['idCompany' => 3, 'idHoldingCompany' => 9, 'shareCount' => 12];
$valuesBefore = $pdo->query('SELECT * FROM tblCompany ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$response = aetherShareAddCompanyHolding($pdo, $manager, $input, 'request-company-1');
ownershipCheck(aetherShareStock($pdo, 3, 100)['free'] === 58, 'Company stock is deducted from free shares');
$replay = aetherShareAddCompanyHolding($pdo, $manager, $input, 'request-company-1');
ownershipCheck($response === $replay && (int)$pdo->query('SELECT COUNT(*) FROM tblCompanyShareCompanyTransaction')->fetchColumn() === 1, 'Replay cannot double assign stock');
aetherShareAddCompanyHolding($pdo, ['id'=>2,'role'=>'administrator'], [...$input,'shareCount'=>3], 'request-company-2');
ownershipCheck((int)$pdo->query('SELECT shareCount FROM tblCompanyShareCompanyHolding')->fetchColumn() === 15, 'Multiple allocations are aggregated');
ownershipCheck((int)$pdo->query('SELECT COUNT(*) FROM tblCompanyShareCompanyTransaction')->fetchColumn() === 2, 'Separate allocations retain their history');
ownershipCheck($pdo->query('SELECT * FROM tblCompany ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $valuesBefore, 'Both company records remain exactly unchanged, without any snapshot');
ownershipCheck((float)$pdo->query('SELECT bankaccount FROM tblCharacter')->fetchColumn() === 1234.56, 'Assignments do not debit a character');
foreach ([[403,['id'=>9,'role'=>'participant'],$input,'forbidden'],[422,$manager,[...$input,'idHoldingCompany'=>3],'self'],
    [409,$manager,[...$input,'shareCount'=>56],'stock'],[404,$manager,[...$input,'idHoldingCompany'=>777],'missing'],
    [409,$manager,[...$input,'idHoldingCompany'=>10],'future'],[422,$manager,[...$input,'shareCount'=>0],'zero']] as [$status,$user,$payload,$key]) {
    try { aetherShareAddCompanyHolding($pdo,$user,$payload,$key);throw new LogicException('Invalid assignment accepted'); }
    catch (AetherShareException $e) { ownershipCheck($e->getHttpStatus() === $status,'Expected rejection status'); }
    ownershipCheck((int)$pdo->query('SELECT shareCount FROM tblCompanyShareCompanyHolding')->fetchColumn() === 15,'Failed mutations cannot change holdings');
}
try { aetherShareAddCompanyHolding($pdo,$manager,[...$input,'shareCount'=>14],'request-company-1');throw new LogicException('Changed replay accepted'); }
catch (AetherIdempotencyException $e) { ownershipCheck($e->getHttpStatus() === 409,'Changed replay must conflict'); }

$allocation = aetherShareMixedDividendAllocation('100.00', [9=>25], [9=>25], 50);
ownershipCheck($allocation['payouts'][9] === '25.00' && $allocation['companyPayouts'][9] === '25.00', 'Overlapping character/company IDs remain distinct');
$paid = createCompanySnapshotDividendPayouts($pdo, ['id'=>11,'title'=>'Test','dateStart'=>'2026-11-01'], 3, '110.00');
ownershipCheck($paid === '55.55', 'Company dividend is included in assigned payouts rather than retained twice');
ownershipCheck((float)$pdo->query('SELECT amount FROM tblCompanySnapshotCompanyPayout')->fetchColumn() === 33.33, 'Company receives its weighted dividend registration');
ownershipCheck((float)$pdo->query('SELECT bankaccount FROM tblCharacter')->fetchColumn() === 1256.78, 'Character receives only its own fraction');
ownershipCheck($pdo->query('SELECT * FROM tblCompany ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $valuesBefore, 'Registering company dividends cannot change company values');
reverseCompanySnapshotDividendPayouts($pdo,11);
ownershipCheck((int)$pdo->query('SELECT COUNT(*) FROM tblCompanySnapshotCompanyPayout')->fetchColumn() === 0,'Snapshot reversal clears the company payout');
ownershipCheck((float)$pdo->query('SELECT bankaccount FROM tblCharacter')->fetchColumn() === 1234.56,'Snapshot reversal restores character bank');
foreach (['shares','shared','companies','characters'] as $folder) { foreach(glob($fixture.'/'.$folder.'/*') as $file) unlink($file);rmdir($fixture.'/'.$folder); }
rmdir($fixture);
echo "Company share ownership, exact stock, permissions, replay safety, unchanged values and mixed dividends passed.\n";
