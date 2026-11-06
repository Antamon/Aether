<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-board-personnel-' . bin2hex(random_bytes(5));
mkdir($fixture . '/companies', 0777, true); mkdir($fixture . '/shared');
copy($root . '/api/companies/companyPersonnelActions.php', $fixture . '/companies/companyPersonnelActions.php');
file_put_contents($fixture . '/companies/companySchemas.php', '<?php require ' . var_export($root . '/api/shared/validation.php', true) . ';');
file_put_contents($fixture . '/shared/idempotency.php', '<?php');
file_put_contents($fixture . '/companies/companyService.php', <<<'PHP'
<?php
class AetherCompanyException extends RuntimeException {
    public function __construct(int $status, string $message) { parent::__construct($message, $status); }
}
function aetherIsPrivilegedRole(string $role): bool { return in_array($role, ['administrator','director'], true); }
function aetherCompanyUserIsBoardMember(PDO $pdo, array $user, int $id): bool { return $user['id'] === 1 && $id === 7; }
function aetherCompanyRequireExisting(PDO $pdo, int $id, bool $lock): void {}
function aetherCompanyLeadershipAvailable(PDO $pdo): bool { return true; }
function aetherPersonnelCharacterExists(PDO $pdo, int $id): bool { return true; }
function aetherCharacterHasCompanyLeaderProfession(PDO $pdo, int $id): bool { return false; }
function refreshCompanySnapshotsForCurrentPersonnel(PDO $pdo, int $id): void {}
function getCharacterCompanySalaryIncreaseAmount(PDO $pdo, array $character, int $id): int { return 0; }
PHP);
require $fixture . '/companies/companyPersonnelActions.php';
class BoardPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_starts_with($query, 'DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation')) {
            $query = 'DELETE FROM tblCompanyPersonnelSkillSpecialisation
                WHERE idCompanyPersonnelSkill IN (SELECT id FROM tblCompanyPersonnelSkill WHERE idCompanyPersonnel = :id)';
        }
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$pdo = new BoardPdo('sqlite::memory:');
$pdo->exec("CREATE TABLE tblCompany (id INTEGER, companyValue REAL);
    CREATE TABLE tblCharacter (id INTEGER, firstName TEXT, lastName TEXT, class TEXT, state TEXT);
    CREATE TABLE tblCompanyPersonnel (id INTEGER PRIMARY KEY, idCompany INTEGER, idCharacter INTEGER, importance TEXT,
        salaryIncreasePercentage REAL, isBoardMember INTEGER, isCompanyLeader INTEGER, functionDescription TEXT);
    CREATE TABLE tblCompanyPersonnelSkill (id INTEGER PRIMARY KEY, idCompanyPersonnel INTEGER);
    CREATE TABLE tblCompanyPersonnelSkillSpecialisation (id INTEGER PRIMARY KEY, idCompanyPersonnelSkill INTEGER);
    INSERT INTO tblCompany VALUES (7, 1000);
    INSERT INTO tblCharacter VALUES (21,'Ada','Test','middle class','active');
    INSERT INTO tblCompanyPersonnel VALUES (1,7,21,'Critical',0,0,0,'Oud');");
function boardCheck(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$user = ['id'=>1,'role'=>'participant'];
$input = aetherCompanyPersonnelActionInput(['idCompany'=>7,'idCharacter'=>21,'action'=>'update',
    'salaryIncreasePercentage'=>'12.50','functionDescription'=>'Directeur','isBoardMember'=>true]);
aetherCompanyPersonnelAction($pdo, $user, $input);
$row = $pdo->query('SELECT * FROM tblCompanyPersonnel')->fetch(PDO::FETCH_ASSOC);
boardCheck($row['functionDescription'] === 'Directeur' && (float)$row['salaryIncreasePercentage'] === 12.5
    && (int)$row['isBoardMember'] === 1 && $row['importance'] === 'Critical', 'Allowed fields or protected importance changed');
foreach (['importance'=>'Low','skills'=>[], 'level'=>3,'specialisations'=>[]] as $key=>$value) {
    try { aetherCompanyPersonnelActionInput($input + [$key=>$value]); throw new LogicException('Protected field accepted'); }
    catch (AetherValidationException $error) {}
}
try { aetherCompanyPersonnelAction($pdo, ['id'=>2,'role'=>'participant'], $input); throw new LogicException('Unrelated user accepted'); }
catch (AetherCompanyException $error) { boardCheck($error->getCode() === 403, 'Wrong access error'); }
 aetherCompanyPersonnelAction($pdo, $user, aetherCompanyPersonnelActionInput(['idCompany'=>7,'idCharacter'=>21,'action'=>'remove']));
boardCheck($pdo->query('SELECT COUNT(*) FROM tblCompanyPersonnel WHERE idCharacter = 21')->fetchColumn() === 0,
    'Board member could not remove personnel');
$pdo->exec("INSERT INTO tblCharacter VALUES (22,'Bob','Test','middle class','active')");
aetherCompanyPersonnelAction($pdo, $user, aetherCompanyPersonnelActionInput(['idCompany'=>7,'idCharacter'=>22,'action'=>'add',
    'salaryIncreasePercentage'=>'3.25','functionDescription'=>'Secretaris','isBoardMember'=>true]));
$added = $pdo->query('SELECT * FROM tblCompanyPersonnel WHERE idCharacter=22')->fetch(PDO::FETCH_ASSOC);
boardCheck($added['functionDescription']==='Secretaris' && $added['importance']==='Moderate'
    && (float)$added['salaryIncreasePercentage']===3.25 && (int)$added['isBoardMember']===1, 'Board addition fields changed');
foreach (glob($fixture . '/companies/*') as $file) unlink($file);
foreach (glob($fixture . '/shared/*') as $file) unlink($file);
rmdir($fixture . '/companies'); rmdir($fixture . '/shared'); rmdir($fixture);
echo "Board personnel: add, update, remove, salary, description, board membership and protected-field checks passed.\n";
