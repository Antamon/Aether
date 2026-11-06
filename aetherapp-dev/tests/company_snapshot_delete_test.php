<?php
declare(strict_types=1);

// Run the real cleanup queries with foreign keys enabled, without a live database.
$root = $argv[1] ?? dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-snapshot-delete-' . bin2hex(random_bytes(5));
foreach (['companies', 'characters', 'shared', 'shares'] as $folder) mkdir($fixture . '/api/' . $folder, 0777, true);
copy($root . '/api/companies/companySnapshotService.php', $fixture . '/api/companies/companySnapshotService.php');
foreach (['companies/companyUtils.php', 'companies/companyQuarterService.php', 'characters/companyShareUtils.php',
    'characters/characterFinanceService.php', 'shared/idempotency.php', 'shares/shareAccess.php',
    'shares/shareService.php', 'shares/shareDividendRules.php'] as $file) {
    file_put_contents($fixture . '/api/' . $file, '<?php');
}
require $fixture . '/api/companies/companySnapshotService.php';

function deletionCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function deletionDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(<<<'SQL'
PRAGMA foreign_keys = ON;
CREATE TABLE tblCompanySnapshot (id INTEGER PRIMARY KEY, idCompany INTEGER, idEvent INTEGER);
CREATE TABLE tblShareSearch (id INTEGER PRIMARY KEY, idCharacter INTEGER, idEvent INTEGER);
CREATE TABLE tblShareSearchAssessment (id INTEGER PRIMARY KEY, idSearch INTEGER REFERENCES tblShareSearch(id),
    idCompany INTEGER, idSnapshot INTEGER REFERENCES tblCompanySnapshot(id));
CREATE TABLE tblShareOffer (id INTEGER PRIMARY KEY, idSnapshot INTEGER REFERENCES tblCompanySnapshot(id),
    idAssessment INTEGER REFERENCES tblShareSearchAssessment(id), purchasedShareCount INTEGER DEFAULT 0);
CREATE TABLE tblShareOfferRecipient (idOffer INTEGER REFERENCES tblShareOffer(id), idCharacter INTEGER);
CREATE TABLE tblCompanyShareTransaction (id INTEGER PRIMARY KEY, idCompany INTEGER, idEvent INTEGER,
    operationCode TEXT, idOffer INTEGER REFERENCES tblShareOffer(id));
CREATE TABLE tblCharacterCompanyResearch (idSnapshot INTEGER REFERENCES tblCompanySnapshot(id) ON DELETE CASCADE);
INSERT INTO tblCompanySnapshot VALUES (10, 1, 5), (20, 2, 5), (30, 1, 6);
INSERT INTO tblShareSearch VALUES (1, 7, 5);
INSERT INTO tblShareSearchAssessment VALUES (1, 1, 1, 10), (2, 1, 2, 20), (3, 1, 1, 10);
INSERT INTO tblShareOffer VALUES (1, 10, 1, 0), (2, 20, 2, 0), (3, 10, NULL, 0), (4, NULL, 3, 0);
INSERT INTO tblShareOfferRecipient VALUES (1, 7), (1, 8), (2, 7), (3, 8), (4, 7);
INSERT INTO tblCharacterCompanyResearch VALUES (10), (20);
SQL);
    return $pdo;
}
function deleteSnapshot(PDO $pdo): void
{
    clearCompanySnapshotShareSearch($pdo, 1, ['id' => 10, 'idEvent' => 5]);
    $pdo->exec('DELETE FROM tblCompanySnapshot WHERE id = 10');
}

$pdo = deletionDatabase();
$pdo->beginTransaction();
deleteSnapshot($pdo);
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareOffer')->fetchColumn() === 1,
    'Unpurchased search/private offers and offers linked through assessments are removed.');
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareOfferRecipient')->fetchColumn() === 1,
    'All recipients of the deleted offers are removed.');
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareSearchAssessment')->fetchColumn() === 1,
    'Only the deleted snapshot assessments are removed, including unsuccessful searches.');
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblCharacterCompanyResearch')->fetchColumn() === 1,
    'Snapshot research is removed by its foreign key, other company knowledge stays.');
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareSearch')->fetchColumn() === 1,
    'The event-wide search remains available for other companies.');
$pdo->commit();
$pdo->exec('INSERT INTO tblCompanySnapshot VALUES (11, 1, 5)');
$pdo->exec('INSERT INTO tblShareSearchAssessment VALUES (4, 1, 1, 11)');
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareSearchAssessment WHERE idSnapshot = 11')->fetchColumn() === 1,
    'A recreated snapshot can receive a new search assessment for the same event and character.');

$pdo = deletionDatabase();
$pdo->beginTransaction();
deleteSnapshot($pdo);
$pdo->rollBack();
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareOffer')->fetchColumn() === 4,
    'A failure later in snapshot deletion rolls back all search/offer cleanup.');

foreach ([
    "INSERT INTO tblCompanyShareTransaction VALUES (1, 1, 5, 'buy_free', NULL)",
    "INSERT INTO tblCompanyShareTransaction VALUES (1, 1, 5, 'buy_from_holder', NULL)",
    "INSERT INTO tblCompanyShareTransaction VALUES (1, 1, 5, 'legacy_purchase', NULL)",
    "INSERT INTO tblCompanyShareTransaction VALUES (1, 1, 5, 'purchase_correction', NULL)",
    "INSERT INTO tblCompanyShareTransaction VALUES (1, 1, 6, 'buy_free', 1)",
    'UPDATE tblShareOffer SET purchasedShareCount = 1 WHERE id = 3',
] as $purchase) {
    $pdo = deletionDatabase();
    $pdo->exec($purchase);
    $pdo->beginTransaction();
    try {
        deleteSnapshot($pdo);
        throw new RuntimeException('A snapshot with an actual purchase must remain protected.');
    } catch (AetherCompanyFinanceException $e) {
        deletionCheck($e->getHttpStatus() === 409, 'Purchased snapshots return a conflict.');
        deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblShareOffer')->fetchColumn() === 4,
            'The purchase guard runs before any cleanup.');
    } finally {
        $pdo->rollBack();
    }
}
$pdo = deletionDatabase();
$pdo->exec("INSERT INTO tblCompanyShareTransaction VALUES (1, 2, 5, 'buy_free', 2), (2, 1, 6, 'buy_free', NULL)");
$pdo->beginTransaction();
deleteSnapshot($pdo);
$pdo->commit();
deletionCheck((int) $pdo->query('SELECT COUNT(*) FROM tblCompanyShareTransaction')->fetchColumn() === 2,
    'Purchases for another company/event do not block deletion and are preserved.');
echo "Snapshot deletion, purchase protection, search cleanup, foreign keys and rollback passed.\n";
