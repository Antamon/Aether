<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$fixture=sys_get_temp_dir().'/aether-discovery-edit-'.bin2hex(random_bytes(5));
foreach(['shares','shared'] as $folder) mkdir($fixture.'/'.$folder,0777,true);
foreach(['shareSearchService.php','shareRules.php','shareRepository.php','shareOwnershipRepository.php','shareException.php'] as $file) copy($root.'/api/shares/'.$file,$fixture.'/shares/'.$file);
copy($root.'/api/shared/decimal.php',$fixture.'/shared/decimal.php');
file_put_contents($fixture.'/shares/companyResearchService.php', <<<'PHP'
<?php
function aetherCompanyResearchSnapshot(PDO $pdo,int $company,int $event):array {
    $stmt=$pdo->prepare('SELECT * FROM tblCompanySnapshot WHERE idCompany=:company AND idEvent=:event');$stmt->execute(compact('company','event'));
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new AetherShareException(404,'Missing snapshot');
}
PHP);
file_put_contents($fixture.'/shares/shareService.php', <<<'PHP'
<?php
require_once __DIR__.'/shareException.php';require_once __DIR__.'/shareRules.php';require_once __DIR__.'/shareRepository.php';
function aetherShareRequireManager(array $user):void {if(!in_array($user['role'],['director','administrator'],true))throw new AetherShareException(403,'Forbidden');}
function aetherFinanceLockCharacters(PDO $pdo,array $ids):array {return [$ids[0]=>['id'=>$ids[0],'bankaccount'=>'500.00']];}
function aetherShareRequireCharacter(array $characters,int $id,array $user,bool $active):array {return $characters[$id];}
function aetherShareRequireCompany(array $companies,int $id):array {return $companies[$id];}
function aetherShareMutation(PDO $pdo,array $user,string $operation,string $key,array $input,callable $mutation,callable $authorize):array {
    $pdo->beginTransaction();try{$row=$pdo->query('SELECT * FROM tblCompany WHERE id='.(int)$input['idCompany'])->fetch(PDO::FETCH_ASSOC);
    $out=$mutation([(int)$row['id']=>$row]);$pdo->commit();return $out;}catch(Throwable $error){$pdo->rollBack();throw $error;}
}
PHP);
require $fixture.'/shares/shareSearchService.php';
class DiscoveryPdo extends PDO {
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        $query=str_replace(' FOR UPDATE','',$query);
        $query=str_replace('ON DUPLICATE KEY UPDATE lastCheckedAt = NOW()',
            'ON CONFLICT(idCharacter,idEvent) DO UPDATE SET lastCheckedAt=CURRENT_TIMESTAMP',$query);
        return parent::prepare($query,$options);
    }
}
function discoveryCheck(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$pdo=new DiscoveryPdo('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE tblCompany(id INTEGER PRIMARY KEY,companyValue NUMERIC,issuedShareCount INTEGER,isShareListed INTEGER,initialShareCount INTEGER);
INSERT INTO tblCompany VALUES(5,1000,100,1,100);
CREATE TABLE tblCompanySnapshot(id INTEGER PRIMARY KEY,idCompany INTEGER,idEvent INTEGER,shareMarketPrice NUMERIC);
INSERT INTO tblCompanySnapshot VALUES(11,5,19,10);
CREATE TABLE tblLinkCharacterSkill(idCharacter INTEGER,idSkill INTEGER,level INTEGER);INSERT INTO tblLinkCharacterSkill VALUES(21,6,2);
CREATE TABLE tblCharacterSpecialisation(idCharacter INTEGER,idSkill INTEGER,idSkillSpecialisation INTEGER);
CREATE TABLE tblShareSearch(id INTEGER PRIMARY KEY AUTOINCREMENT,idCharacter INTEGER,idEvent INTEGER,lastCheckedAt TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(idCharacter,idEvent));
CREATE TABLE tblShareSearchAssessment(id INTEGER PRIMARY KEY AUTOINCREMENT,idSearch INTEGER,idCompany INTEGER,idSnapshot INTEGER,effectiveSkillLevel INTEGER,
discoveryRoll INTEGER,quantityRoll INTEGER,priceRoll INTEGER,wasDiscovered INTEGER);
CREATE TABLE tblShareOffer(id INTEGER PRIMARY KEY AUTOINCREMENT,idCompany INTEGER,idEvent INTEGER,idSnapshot INTEGER,idAssessment INTEGER,sourceCode TEXT,
statusCode TEXT DEFAULT 'open',offeredShareCount INTEGER,purchasedShareCount INTEGER DEFAULT 0,unitPrice NUMERIC,expiresAt TEXT);
CREATE TABLE tblShareOfferRecipient(idOffer INTEGER,idCharacter INTEGER,recipientKind TEXT);
CREATE TABLE tblCompanyShareHolding(idCompany INTEGER,idCharacter INTEGER,shareCount INTEGER);INSERT INTO tblCompanyShareHolding VALUES(5,22,10);
CREATE TABLE tblCompanyAnonymousShares(idCompany INTEGER,shareCount INTEGER);INSERT INTO tblCompanyAnonymousShares VALUES(5,20);
CREATE TABLE tblCompanyValueHistory(id INTEGER PRIMARY KEY,idCompany INTEGER,idShareTransaction INTEGER,actorUserId INTEGER,reasonCode TEXT,valueBefore NUMERIC,valueDelta NUMERIC,valueAfter NUMERIC,detailsJson TEXT);");
$manager=['id'=>1,'role'=>'director'];$input=['idCompany'=>5,'idCharacter'=>21,'idEvent'=>19,'shareCount'=>8,'unitPrice'=>'9.25'];
$result=aetherShareSaveDiscovery($pdo,$manager,$input,'add');
discoveryCheck($result['shareCount']===8,'Manager creates an exact quantity');
$offer=$pdo->query('SELECT * FROM tblShareOffer')->fetch(PDO::FETCH_ASSOC);
discoveryCheck((float)$offer['unitPrice']===9.25&&$offer['sourceCode']==='search','Manager sets a real searchable offer price');
discoveryCheck((int)$pdo->query('SELECT idCharacter FROM tblShareOfferRecipient')->fetchColumn()===21,'The correct character receives the offer');
discoveryCheck((int)$pdo->query('SELECT wasDiscovered FROM tblShareSearchAssessment')->fetchColumn()===1,'The result is marked as discovered');
$input['shareCount']=12;$input['unitPrice']='11.50';aetherShareSaveDiscovery($pdo,$manager,$input,'edit');
discoveryCheck((int)$pdo->query('SELECT COUNT(*) FROM tblShareOffer')->fetchColumn()===1,'Editing reuses the existing offer');
discoveryCheck((int)$pdo->query('SELECT offeredShareCount FROM tblShareOffer')->fetchColumn()===12,'The actual quantity is updated');
discoveryCheck((int)$pdo->query('SELECT COUNT(*) FROM tblCompanyValueHistory')->fetchColumn()===2,'Each successful change is audited');
discoveryCheck((float)$pdo->query('SELECT companyValue FROM tblCompany')->fetchColumn()===1000.0,'Creating an offer does not change company value');
function discoveryReject(PDO $pdo,int $expected,callable $action):void {
    $before=$pdo->query('SELECT * FROM tblShareOffer')->fetchAll(PDO::FETCH_ASSOC);
    try{$action();throw new LogicException('Invalid edit accepted');}catch(AetherShareException $error){discoveryCheck($error->getHttpStatus()===$expected,'Wrong error');}
    discoveryCheck($pdo->query('SELECT * FROM tblShareOffer')->fetchAll(PDO::FETCH_ASSOC)===$before,'Rejected edit must not alter the offer');
}
discoveryReject($pdo,403,fn()=>aetherShareSaveDiscovery($pdo,['id'=>2,'role'=>'participant'],$input,'forbidden'));
discoveryReject($pdo,409,fn()=>aetherShareSaveDiscovery($pdo,$manager,[...$input,'shareCount'=>71],'stock'));
$pdo->exec('UPDATE tblShareOffer SET purchasedShareCount=1');
discoveryReject($pdo,409,fn()=>aetherShareSaveDiscovery($pdo,$manager,$input,'purchased'));
$pdo->exec("UPDATE tblShareOffer SET purchasedShareCount=0;INSERT INTO tblShareOfferRecipient VALUES(1,22,'shared')");
discoveryReject($pdo,409,fn()=>aetherShareSaveDiscovery($pdo,$manager,$input,'shared'));
foreach(glob($fixture.'/shares/*')as$file)unlink($file);foreach(glob($fixture.'/shared/*')as$file)unlink($file);
rmdir($fixture.'/shares');rmdir($fixture.'/shared');rmdir($fixture);
echo "Share discovery creation, exact quantity/price edits, recipients, audit, stock and purchased/shared safeguards passed.\n";
