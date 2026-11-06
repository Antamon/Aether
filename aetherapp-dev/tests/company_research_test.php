<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-research-' . bin2hex(random_bytes(5));
mkdir($fixture . '/shares', 0777, true); mkdir($fixture . '/companies');
copy($root . '/api/shares/companyResearchService.php', $fixture . '/shares/companyResearchService.php');
copy($root . '/api/shares/shareSearchService.php', $fixture . '/shares/shareSearchService.php');
foreach (['companyQuarterService.php', 'companyQuarterMath.php'] as $file) copy($root . '/api/companies/' . $file, $fixture . '/companies/' . $file);
file_put_contents($fixture . '/companies/companyUtils.php', <<<'PHP'
<?php
function dbOne(PDO $pdo, string $sql, array $params): ?array { $stmt=$pdo->prepare($sql); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null; }
function getCompanyFoundationSnapshot(PDO $pdo, int $id): array { return ['foundationDate'=>'1924-01-01','initialCompanyValue'=>1000,'initialShareCount'=>100,'shareMarketPrice'=>10,'stability'=>0,'profitability'=>0]; }
function getCompanyLogoUrl(int $id): string { return "logo-$id.png"; }
function getCompanyTypeByValue(mixed $value): array { return ['label'=>'Test company']; }
PHP);
file_put_contents($fixture . '/companies/companyRichText.php', '<?php function aetherSanitizeCompanyDescription(string $text): string { return strip_tags($text); }');
file_put_contents($fixture . '/shares/shareService.php', <<<'PHP'
<?php
class AetherShareException extends RuntimeException { public function __construct(int $status, string $message) { parent::__construct($message, $status); } }
function aetherCanManageShareCompany(array $user): bool { return in_array($user['role'], ['director', 'administrator'], true); }
function aetherFetchCharacterAccessRecord(PDO $pdo, int $id): ?array { return in_array($id,[21,22],true) ? ['id'=>$id,'idUser'=>$id,'state'=>'active'] : null; }
function aetherCanViewCharacter(array $user, array $character): bool { return aetherCanManageShareCompany($user) || $user['id']===$character['idUser']; }
function aetherShareRequireManager(array $user): void { if (!aetherCanManageShareCompany($user)) throw new AetherShareException(403, 'Forbidden'); }
function aetherFinanceLockCharacters(PDO $pdo, array $ids): array { return [$ids[0] => ['id' => $ids[0]]]; }
function aetherShareRequireCharacter(array $characters, int $id, array $user, bool $active): array { return $characters[$id]; }
function aetherShareMutation(PDO $pdo, array $user, string $operation, string $key, array $input, callable $mutation, callable $authorize): array {
    $pdo->beginTransaction();
    try { $result = $mutation([]); $pdo->commit(); return $result; }
    catch (Throwable $error) { $pdo->rollBack(); throw $error; }
}
PHP);
require $fixture . '/shares/companyResearchService.php';
require $fixture . '/shares/shareSearchService.php';
require $root . '/api/shares/shareRules.php';
class ResearchPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $query = str_replace([' FOR UPDATE', 'INSERT IGNORE INTO'], ['', 'INSERT OR IGNORE INTO'], $query);
        $query = str_replace('ON DUPLICATE KEY UPDATE isKnown = VALUES(isKnown), isManual = 1',
            'ON CONFLICT(idCharacter, idSnapshot, idCompanyQuarterEvent) DO UPDATE SET isKnown = excluded.isKnown, isManual = 1', $query);
        return parent::prepare($query, $options);
    }
}
function researchCheck(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$matrix = [[60,80,100,100],[40,60,80,100],[20,40,60,80],[0,20,40,60],[0,0,20,40]];
foreach ($matrix as $visibility => $chances) foreach ($chances as $level => $chance) {
    researchCheck(aetherCompanyResearchChance($level + 1, $visibility + 1) === $chance, 'Incorrect chance table');
}
$pdo = new ResearchPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys=ON;
CREATE TABLE tblSchemaMigration (migrationCode TEXT);
INSERT INTO tblSchemaMigration VALUES ('0022_company_research');
CREATE TABLE tblLinkCharacterSkill (idCharacter INTEGER, idSkill INTEGER, level INTEGER);
CREATE TABLE tblCharacterSpecialisation (idCharacter INTEGER, idSkill INTEGER, idSkillSpecialisation INTEGER);
INSERT INTO tblLinkCharacterSkill VALUES (21,6,3), (22,6,1);
INSERT INTO tblCharacterSpecialisation VALUES (21,6,73), (22,6,72);
CREATE TABLE tblEvent (id INTEGER PRIMARY KEY, title TEXT, dateStart TEXT, dateEnd TEXT);
INSERT INTO tblEvent VALUES (1,'First','2025-05-01','2025-05-02'),(2,'Next','2026-05-01','2026-05-02');
CREATE TABLE tblCompanySnapshot (id INTEGER PRIMARY KEY, idCompany INTEGER, idEvent INTEGER);
INSERT INTO tblCompanySnapshot VALUES (11,5,1),(12,5,2);
ALTER TABLE tblCompanySnapshot ADD COLUMN companyValue NUMERIC DEFAULT 1000;
ALTER TABLE tblCompanySnapshot ADD COLUMN issuedShareCount INTEGER DEFAULT 100;
ALTER TABLE tblCompanySnapshot ADD COLUMN shareMarketPrice NUMERIC DEFAULT 10;
ALTER TABLE tblCompanySnapshot ADD COLUMN stability INTEGER DEFAULT 0;
ALTER TABLE tblCompanySnapshot ADD COLUMN profitability INTEGER DEFAULT 0;
CREATE TABLE tblCompany (id INTEGER PRIMARY KEY, companyName TEXT, foundationDate TEXT, description TEXT, isShareListed INTEGER, companyValue NUMERIC, stability INTEGER, profitability INTEGER, issuedShareCount INTEGER);
INSERT INTO tblCompany VALUES (5,'Company','1924-01-01','Description',1,1000,0,0,100);
CREATE TABLE tblCompanyQuarter (id INTEGER PRIMARY KEY, idCompany INTEGER, quarterYear INTEGER, quarterNumber INTEGER);
INSERT INTO tblCompanyQuarter VALUES (1,5,1924,1),(2,5,1927,1),(3,6,1924,1);
ALTER TABLE tblCompanyQuarter ADD COLUMN companyValue NUMERIC DEFAULT 1000;
ALTER TABLE tblCompanyQuarter ADD COLUMN issuedShareCount INTEGER DEFAULT 100;
ALTER TABLE tblCompanyQuarter ADD COLUMN shareMarketPrice NUMERIC DEFAULT 10;
ALTER TABLE tblCompanyQuarter ADD COLUMN stability INTEGER DEFAULT 0;
ALTER TABLE tblCompanyQuarter ADD COLUMN profitability INTEGER DEFAULT 0;
ALTER TABLE tblCompanyQuarter ADD COLUMN isManual INTEGER DEFAULT 0;
CREATE TABLE tblCompanyQuarterEvent (id INTEGER PRIMARY KEY AUTOINCREMENT, idCompanyQuarter INTEGER, description TEXT, publicity INTEGER);
INSERT INTO tblCompanyQuarterEvent VALUES (1,1,'Earlier event',1),(2,1,'Hidden event',5),(3,2,'Future event',1),(4,3,'Other company event',1);
CREATE TABLE tblCharacterCompanyResearch (idCharacter INTEGER, idSnapshot INTEGER, idCompanyQuarterEvent INTEGER REFERENCES tblCompanyQuarterEvent(id) ON DELETE CASCADE,
 effectiveSkillLevel INTEGER, chancePercent INTEGER, discoveryRoll INTEGER, isKnown INTEGER, isManual INTEGER DEFAULT 0,
 updatedAt TEXT DEFAULT 'test', PRIMARY KEY(idCharacter,idSnapshot,idCompanyQuarterEvent));");
researchCheck(aetherCompanyResearchLevel($pdo,21) === 4, 'Auditing ID 73 should add one level');
researchCheck(aetherCompanyResearchLevel($pdo,22) === 1, 'Stock market ID 72 must not affect auditing');
researchCheck(aetherShareEffectiveSearchLevel($pdo,22) === 2, 'Stock market ID 72 must add a search level');
researchCheck(aetherShareEffectiveSearchLevel($pdo,21) === 3, 'Auditing ID 73 must not affect the share search level');
aetherCompanyResearchInvestigate($pdo,21,5,1);
$snapshot = aetherCompanyResearchSnapshot($pdo,5,1);
$events = aetherCompanyResearchEvents($pdo,$snapshot,21);
researchCheck(count($events) === 2 && $events[0]['isKnown'], 'Exclude future/other company events and learn a 100% event');
$once = $pdo->query('SELECT * FROM tblCharacterCompanyResearch')->fetchAll(PDO::FETCH_ASSOC);
aetherCompanyResearchInvestigate($pdo,21,5,1);
researchCheck($pdo->query('SELECT * FROM tblCharacterCompanyResearch')->fetchAll(PDO::FETCH_ASSOC) === $once, 'Reopening must not reroll');
aetherCompanyResearchInvestigate($pdo,22,5,2);
$later = aetherCompanyResearchSnapshot($pdo,5,2);
$hidden = aetherCompanyResearchEvents($pdo,$later,22)[1];
researchCheck(!$hidden['isKnown'], 'A zero-percent event cannot be discovered');
$view = aetherCompanyResearchView($pdo,['id'=>22,'role'=>'participant'],22,5,2);
researchCheck(!array_filter($view['events'], static fn(array $event): bool => $event['description']==='Hidden event'), 'Hidden descriptions must never reach a participant browser');
researchCheck(!array_filter($view['events'], static fn(array $event): bool => array_key_exists('publicity', $event)), 'Participant responses must not expose visibility levels');
researchCheck(!array_filter($view['quarters'], static fn(array $row): bool => $row['year']>1926), 'The curve stops at the selected snapshot');
try { aetherCompanyResearchView($pdo,['id'=>21,'role'=>'participant'],22,5,2); throw new LogicException('Other character viewed'); }
catch (AetherShareException $error) { researchCheck($error->getCode()===403,'Wrong view access error'); }
$manager = ['id'=>1,'role'=>'director'];
$input = ['idCharacter'=>22,'idCompany'=>5,'idEvent'=>1,'revision'=>aetherCompanyResearchRevision(aetherCompanyResearchEvents($pdo,$snapshot,22)), 'knownEventIds'=>[1,2]];
aetherCompanyResearchSave($pdo,$manager,$input,'one');
researchCheck(aetherCompanyResearchEvents($pdo,$later,22)[1]['isKnown'], 'Earlier learning must survive a later failed attempt');
$before = $pdo->query('SELECT * FROM tblCharacterCompanyResearch')->fetchAll(PDO::FETCH_ASSOC);
try { aetherCompanyResearchSave($pdo,['id'=>2,'role'=>'participant'],$input,'two'); throw new LogicException('Participant edited knowledge'); }
catch (AetherShareException $error) { researchCheck($error->getCode()===403,'Wrong authorization error'); }
try { aetherCompanyResearchSave($pdo,$manager,$input,'three'); throw new LogicException('Stale revision accepted'); }
catch (AetherShareException $error) { researchCheck($error->getCode()===409,'Wrong conflict error'); }
researchCheck($before === $pdo->query('SELECT * FROM tblCharacterCompanyResearch')->fetchAll(PDO::FETCH_ASSOC),'Rejections must not alter knowledge');
$input['idEvent']=2; $input['revision']=aetherCompanyResearchRevision(aetherCompanyResearchEvents($pdo,$later,22)); $input['knownEventIds']=[];
aetherCompanyResearchSave($pdo,$manager,$input,'four');
researchCheck(!aetherCompanyResearchEvents($pdo,$later,22)[1]['isKnown'], 'Manager can remove learned events');
aetherCompanyQuarterSaveEvents($pdo,1,[['id'=>1,'description'=>'Corrected description','publicity'=>1],['id'=>2,'description'=>'Hidden event','publicity'=>5]]);
researchCheck($pdo->query('SELECT description FROM tblCompanyQuarterEvent WHERE id=1')->fetchColumn()==='Corrected description', 'Event edits keep stable IDs');
researchCheck((int)$pdo->query('SELECT COUNT(*) FROM tblCharacterCompanyResearch')->fetchColumn()===count($before), 'Editing a quarter must preserve knowledge');
foreach ([1,2,3,4] as $level) { $band=aetherShareSearchBand($level); researchCheck($band['chance']===10+20*$level,'Search level sequence'); }
foreach (glob($fixture.'/shares/*') as $file) unlink($file);
foreach (glob($fixture.'/companies/*') as $file) unlink($file);
rmdir($fixture.'/shares');rmdir($fixture.'/companies');rmdir($fixture);
echo "Research chances, specialisation IDs, saved rolls, cutoff, knowledge carry-forward, manager overrides, conflicts and event identity passed.\n";
