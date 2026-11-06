<?php
declare(strict_types=1);

// Run the real repository and portfolio queries against an isolated SQL database.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('This isolated query test requires pdo_sqlite.');
}
$root = $argv[1] ?? dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-snapshot-valuation-' . bin2hex(random_bytes(5));
foreach (['shares', 'shared', 'companies', 'characters'] as $folder) mkdir($fixture . '/api/' . $folder, 0777, true);
foreach (['shares/shareService.php', 'shares/shareRepository.php', 'shares/shareOwnershipRepository.php', 'shares/shareCompanyOwnershipService.php', 'shares/shareRules.php', 'shares/shareException.php',
    'shares/sharePurchaseHistory.php', 'shares/shareMarketEvent.php', 'shared/decimal.php', 'shared/idempotency.php', 'shared/response.php'] as $file) {
    copy($root . '/api/' . $file, $fixture . '/api/' . $file);
}
file_put_contents($fixture . '/api/shares/shareAccess.php', <<<'PHP'
<?php
require_once __DIR__ . '/shareException.php';
function aetherRequireShareMigration(PDO $pdo): void {}
function aetherCanViewCharacter(array $user, array $character): bool { return true; }
function aetherCanManageShareCompany(array $user): bool { return false; }
function aetherFetchCharacterAccessRecord(PDO $pdo, int $id): ?array { return ['id' => $id, 'state' => 'active']; }
PHP);
file_put_contents($fixture . '/api/companies/companyUtils.php', '<?php function getCompanyLogoUrl(int $id): string { return "logo-$id.png"; }');
foreach (['shares/shareDraftService.php', 'characters/characterFinanceRepository.php', 'companies/companyLeadershipRules.php'] as $file) {
    file_put_contents($fixture . '/api/' . $file, '<?php');
}
require $fixture . '/api/shares/shareService.php';

function valuationCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function migrationPortfolio(PDO $pdo, string $root): array
{
    $sql = file_get_contents($root . '/sql/migrations/0020_company_snapshot_valuation.sql');
    if (!preg_match('/-- BEGIN canonical portfolio SELECT[^\n]*\n(.*?)-- END canonical portfolio SELECT/s', $sql, $match)) {
        throw new RuntimeException('The migration valuation query is missing.');
    }
    $statement = $pdo->prepare(str_replace('@aether_0020_apply', ':aether_0020_apply', $match[1]));
    $statement->execute(['aether_0020_apply' => 1]);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(<<<'SQL'
CREATE TABLE tblCompany (id INTEGER PRIMARY KEY, companyName TEXT, foundationDate TEXT,
    companyValue REAL, initialCompanyValue REAL, initialShareCount INTEGER, issuedShareCount INTEGER, isShareListed INTEGER);
CREATE TABLE tblEvent (id INTEGER PRIMARY KEY, title TEXT, dateStart TEXT, dateEnd TEXT);
CREATE TABLE tblCompanySnapshot (id INTEGER PRIMARY KEY, idCompany INTEGER, idEvent INTEGER,
    companyValue REAL, issuedShareCount INTEGER, shareMarketPrice REAL);
CREATE TABLE tblCompanyFoundationSnapshot (idCompany INTEGER PRIMARY KEY, initialCompanyValue REAL,
    initialShareCount INTEGER, shareMarketPrice REAL);
CREATE TABLE tblCompanyShareHolding (idCompany INTEGER, idCharacter INTEGER, shareCount INTEGER);
CREATE TABLE tblCharacterShareStatus (idCharacter INTEGER PRIMARY KEY, shareLevel INTEGER, usedStatusPoints INTEGER,
    personnelRequirement INTEGER, intrinsicPortfolioValue NUMERIC, valuationVersion INTEGER);
CREATE TABLE tblCompanyShareTransaction (id INTEGER, idCompany INTEGER, idCharacter INTEGER,
    idCounterpartyCharacter INTEGER, idEvent INTEGER, operationCode TEXT, shareCount INTEGER,
    unitPrice NUMERIC, totalAmount NUMERIC, createdAt TEXT, detailsJson TEXT);
INSERT INTO tblCompany VALUES (13, 'Van Lerius Transport & Spoorwegen', '1873-04-17', 2829164, 15000, 1, 100, 0);
INSERT INTO tblEvent VALUES (1, 'Trein', '2013-03-08', '2013-03-10'), (19, 'Holiday', '2027-05-15', '2027-05-17');
INSERT INTO tblCompanySnapshot VALUES (24, 13, 19, 2500000, 100, 25000), (25, 13, 1, 750000, 100, 7500);
INSERT INTO tblCompanyFoundationSnapshot VALUES (13, 15000, 1, 15000);
INSERT INTO tblCompanyShareHolding VALUES (13, 95, 24);
INSERT INTO tblCharacterShareStatus VALUES (95, 1, 1, 0, 678999.36, 7);
SQL);
$user = ['id' => 1, 'role' => 'participant'];
$portfolio = aetherShareViewPortfolio($pdo, $user, 95);
$holding = $portfolio['holdings'][0];
valuationCheck((float) $holding['companyValue'] === 2500000.0 && (float) $holding['marketUnitPrice'] === 25000.0,
    'Company value and market price must come from Holiday, even when the older snapshot has a larger ID.');
valuationCheck((float) $holding['intrinsicValue'] === 600000.0 && aetherSharePortfolioValue($pdo, 95) === '600000.00',
    '24 of 100 shares at a recorded value of 2.5 million must be worth 600,000 in both valuation paths.');
valuationCheck((float) migrationPortfolio($pdo, $root)[95] === 600000.0, 'The data-repair query uses the same recorded valuation as the application.');
valuationCheck((float) $portfolio['status']['intrinsicPortfolioValue'] === 600000.0 && $portfolio['status']['shareLevel'] === 1,
    'A stale cached intrinsic value cannot override the recorded valuation.');
$pdo->exec('UPDATE tblCompany SET companyValue = 9999999, issuedShareCount = 81');
valuationCheck(aetherSharePortfolioValue($pdo, 95) === '600000.00',
    'Unrecorded company value or count changes cannot leak into snapshot valuations.');
$company = ['id' => 13, 'companyValue' => '9999999.00'];
valuationCheck(aetherShareValueAfterPurchase($pdo, $company, '600000.00') === '2500000.00'
    && aetherShareValueAfterPurchase($pdo, $company, '-500000.00') === '2500000.00',
    'Positive or negative purchase corrections preserve the explicit valuation.');
$pdo->exec('UPDATE tblCompanySnapshot SET shareMarketPrice = NULL WHERE id = 24');
$holding = aetherShareViewPortfolio($pdo, $user, 95)['holdings'][0];
valuationCheck($holding['marketUnitPrice'] === null && (float) $holding['intrinsicValue'] === 600000.0,
    'An unknown latest market price must not fall back to a price from an earlier period.');
$pdo->exec('UPDATE tblCompanySnapshot SET companyValue = 0 WHERE id = 24');
valuationCheck(aetherSharePortfolioValue($pdo, 95) === '0.00'
    && aetherShareViewPortfolio($pdo, $user, 95)['status']['shareLevel'] === 0,
    'A zero-valued snapshot is authoritative and immediately lowers stale status points.');
valuationCheck((float) migrationPortfolio($pdo, $root)[95] === 0.0, 'The migration must also respect a recorded zero value.');
$pdo->exec('DELETE FROM tblCompanySnapshot');
$pdo->exec('UPDATE tblCompanyFoundationSnapshot SET initialShareCount = 100');
valuationCheck(aetherSharePortfolioValue($pdo, 95) === '3600.00'
    && aetherShareValueAfterPurchase($pdo, $company, '500.00') === '15000.00',
    'Without event snapshots the recorded founding valuation is authoritative.');
valuationCheck((float) migrationPortfolio($pdo, $root)[95] === 3600.0, 'Migration and runtime have the same founding fallback.');
$pdo->exec('DELETE FROM tblCompanyFoundationSnapshot');
$pdo->exec('UPDATE tblCompany SET companyValue = 2829164, issuedShareCount = 100');
valuationCheck(aetherSharePortfolioValue($pdo, 95) === '678999.36'
    && aetherShareValueAfterPurchase($pdo, ['id' => 13, 'companyValue' => '2829164.00'], '100.00') === '2829264.00',
    'A company without any recorded valuation retains the legacy fallback.');
valuationCheck(aetherSharePortfolioValue($pdo, 999) === '0.00', 'An empty portfolio stays zero.');

echo "Snapshot valuation queries, stale values, share counts, zero values, cache and purchase guards passed.\n";
