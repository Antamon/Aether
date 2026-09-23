<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-finance-services-' . bin2hex(random_bytes(5));
mkdir($fixture . '/api/characters', 0777, true);
mkdir($fixture . '/api/companies', 0777, true);
mkdir($fixture . '/api/shared', 0777, true);
copy($root . '/api/shared/decimal.php', $fixture . '/api/shared/decimal.php');
copy($root . '/api/characters/characterFinanceService.php', $fixture . '/api/characters/characterFinanceService.php');
copy($root . '/api/characters/characterShareService.php', $fixture . '/api/characters/characterShareService.php');

file_put_contents($fixture . '/api/characters/economyUtils.php', <<<'PHP'
<?php
function isPrivilegedUserRole(string $role): bool { return in_array($role, ['director', 'administrator'], true); }
function canTransferFromCharacter(array $c, string $r): bool { return isPrivilegedUserRole($r) && $c['state'] !== 'draft'; }
function canManageCharacterEconomySnapshots(array $c, string $r, int $u): bool { return isPrivilegedUserRole($r) && $c['state'] !== 'draft'; }
function canManageCharacterSecurities(array $c, string $r, int $u): bool { return $c['state'] !== 'draft' && (isPrivilegedUserRole($r) || ($r === 'participant' && (int)$c['idUser'] === $u)); }
function canApproveCharacterSecuritiesSnapshots(array $c, string $r, int $u): bool { return isPrivilegedUserRole($r) && $c['state'] !== 'draft'; }
function normalizeCharacterSecuritiesManagerType(mixed $v): string { return (string)$v; }
function normalizeCharacterSecuritiesRiskProfile(mixed $v): int { return (int)$v; }
function getDefaultBankTransferDate(): string { return '1926-09-21'; }
function buildCharacterSecuritiesSnapshotWithdrawalDescription(int $id, string $title): string { return "[snapshot-withdrawal:$id] $title"; }
PHP);

file_put_contents($fixture . '/api/characters/characterFinanceRepository.php', <<<'PHP'
<?php
function aetherFinanceLockCharacters(PDO $pdo, array $ids): array { $out=[]; sort($ids); foreach($ids as$id)if(isset($GLOBALS['financeState']['characters'][$id]))$out[$id]=$GLOBALS['financeState']['characters'][$id]; return $out; }
function aetherFinanceLockCharacter(PDO $pdo, int $id): ?array { return $GLOBALS['financeState']['characters'][$id] ?? null; }
function aetherFinanceAdjustBankBalance(PDO $pdo, int $id, string $delta): void { $s=&$GLOBALS['financeState']; $s['characters'][$id]['bankaccount']=aetherDecimalAdd($s['characters'][$id]['bankaccount'],$delta); $s['writes']++; }
function aetherFinanceAdjustBalances(PDO $pdo, int $id, string $bank, string $securities): void { $s=&$GLOBALS['financeState']; $s['characters'][$id]['bankaccount']=aetherDecimalAdd($s['characters'][$id]['bankaccount'],$bank); $s['characters'][$id]['securitiesaccount']=aetherDecimalAdd($s['characters'][$id]['securitiesaccount'],$securities); $s['writes']++; }
function aetherFinanceInsertSecuritiesTransaction(PDO $pdo, array $values): int { $GLOBALS['financeState']['securitiesTransactions'][]=$values; $GLOBALS['financeState']['writes']++; return count($GLOBALS['financeState']['securitiesTransactions']); }
function aetherFinanceUpdateSecuritiesSettings(PDO $pdo,int $id,string $type,?int $manager,int $risk):void { $GLOBALS['financeState']['writes']++; }
function aetherFinanceActiveCharacterExists(PDO $pdo,int $id):bool{return isset($GLOBALS['financeState']['characters'][$id])&&$GLOBALS['financeState']['characters'][$id]['state']==='active';}
PHP);

file_put_contents($fixture . '/api/characters/companyShareUtils.php', <<<'PHP'
<?php
function canManageCompanyShareAssignments(array $c,string $r,int $u):bool{return isPrivilegedUserRole($r)||($r==='participant'&&$c['type']==='player'&&(int)$c['idUser']===$u);}
function canIncreaseCompanyShareRank(array $c,string $r,int $u):bool{return $c['state']!=='draft'&&(isPrivilegedUserRole($r)||($r==='participant'&&$c['type']==='player'&&(int)$c['idUser']===$u));}
function canDecreaseCompanyShareRank(array $c,string $r,int $u):bool{return canIncreaseCompanyShareRank($c,$r,$u);}
function findCompanyShareTraitIdForCompanyType(PDO $p,string $class,string $shareClass,string $key):?int{return 500;}
function getTraitDefinition(PDO $p,int $id):?array{return ['id'=>$id,'rank'=>1,'baseRank'=>1,'shareExtraRank'=>0,'shareClass'=>'A','companyTypeKey'=>'micro'];}
function isCompanyShareTrait(array $t):bool{return true;}
function companyMatchesShareTrait(array $t,array $c):bool{return true;}
function getCompanyShareTotalRank(array $t):int{return (int)$t['baseRank']+(int)$t['shareExtraRank'];}
function getCompanyShareBaseRank(array $t):int{return (int)$t['baseRank'];}
function getCompanyShareExtraRank(array $t):int{return (int)$t['shareExtraRank'];}
PHP);

file_put_contents($fixture . '/api/companies/companyUtils.php', <<<'PHP'
<?php
function enrichCompanyWithType(array $company):array{$company['companyTypeKey']='micro';return $company;}
PHP);

file_put_contents($fixture . '/api/characters/characterShareRepository.php', <<<'PHP'
<?php
function aetherShareLockCompanies(PDO $pdo,array $ids):array{$out=[];sort($ids);foreach($ids as$id)if(isset($GLOBALS['financeState']['companies'][$id]))$out[$id]=$GLOBALS['financeState']['companies'][$id];return $out;}
function aetherShareAllocatedPercentage(PDO $pdo,int $companyId,int $exclude=0):int{return (int)$GLOBALS['financeState']['allocated'][$companyId];}
function aetherShareFindCharacterTraitLink(PDO $pdo,int $characterId,int $traitId):?array{foreach($GLOBALS['financeState']['links'] as$id=>$l)if($l['idCharacter']===$characterId&&$l['idTrait']===$traitId)return ['id'=>$id];return null;}
function aetherShareInsertTraitLink(PDO $pdo,int $characterId,int $traitId):int{$id=++$GLOBALS['financeState']['nextLink'];$GLOBALS['financeState']['links'][$id]=['id'=>$id,'idCharacter'=>$characterId,'idTrait'=>$traitId,'rankValue'=>1,'idCompany'=>null,'extraPercentage'=>0];$GLOBALS['financeState']['writes']++;return $id;}
function aetherShareUpsertCompanyLink(PDO $pdo,int $linkId,?int $companyId,int $extra):void{$old=$GLOBALS['financeState']['links'][$linkId]['idCompany'];if($old)$GLOBALS['financeState']['allocated'][$old]-=1+$GLOBALS['financeState']['links'][$linkId]['extraPercentage'];$GLOBALS['financeState']['links'][$linkId]['idCompany']=$companyId;$GLOBALS['financeState']['links'][$linkId]['extraPercentage']=$extra;if($companyId)$GLOBALS['financeState']['allocated'][$companyId]+=1+$extra;$GLOBALS['financeState']['writes']++;}
function aetherShareFetchLink(PDO $pdo,int $id,bool $lock=false):?array{return $GLOBALS['financeState']['links'][$id]??null;}
function aetherShareSetBaseRank(PDO $pdo,int $id,int $rank):void{$GLOBALS['financeState']['links'][$id]['rankValue']=$rank;$GLOBALS['financeState']['writes']++;}
function aetherShareDeleteLink(PDO $pdo,int $id):void{$company=$GLOBALS['financeState']['links'][$id]['idCompany'];if($company)$GLOBALS['financeState']['allocated'][$company]-=1+$GLOBALS['financeState']['links'][$id]['extraPercentage'];unset($GLOBALS['financeState']['links'][$id]);$GLOBALS['financeState']['writes']++;}
PHP);

require $fixture . '/api/characters/characterFinanceService.php';
require $fixture . '/api/characters/characterShareService.php';

final class FinanceServicePdo extends PDO { public function __construct() {} }
$pdo = new FinanceServicePdo();
$participant = ['id'=>10,'role'=>'participant'];
$director = ['id'=>20,'role'=>'director'];
$administrator = ['id'=>30,'role'=>'administrator'];
$GLOBALS['financeState'] = [
    'characters'=>[
        1=>['id'=>1,'idUser'=>10,'type'=>'player','state'=>'active','class'=>'middle class','bankaccount'=>'1000.00','securitiesaccount'=>'100.00'],
        2=>['id'=>2,'idUser'=>99,'type'=>'player','state'=>'active','class'=>'middle class','bankaccount'=>'1000.00','securitiesaccount'=>'0.00'],
    ],
    'companies'=>[7=>['id'=>7,'companyName'=>'Test','companyValue'=>'10000.00']],
    'allocated'=>[7=>99], 'links'=>[], 'nextLink'=>40, 'securitiesTransactions'=>[], 'writes'=>0,
];

$failures=[];
function financeServiceAssert(bool $ok,string $message):void{global$failures;if(!$ok)$failures[]=$message;}

$deposit=aetherSaveSecuritiesPortfolio($pdo,$participant,['action'=>'deposit','idCharacter'=>1,'amount'=>'10.00']);
financeServiceAssert($deposit===['success'=>true]&&$GLOBALS['financeState']['characters'][1]['bankaccount']==='990.00'&&$GLOBALS['financeState']['characters'][1]['securitiesaccount']==='110.00','Geldige effectenstorting wijzigt beide saldi niet exact.');
$withdraw=aetherSaveSecuritiesPortfolio($pdo,$participant,['action'=>'manual_withdrawal','idCharacter'=>1,'amount'=>'10.00']);
financeServiceAssert($withdraw===['success'=>true]&&$GLOBALS['financeState']['characters'][1]['bankaccount']==='997.50'&&$GLOBALS['financeState']['characters'][1]['securitiesaccount']==='100.00','Effectenopname past de bestaande 75%-regel niet exact toe.');

$before=$GLOBALS['financeState'];
try{aetherSaveSecuritiesPortfolio($pdo,$participant,['action'=>'deposit','idCharacter'=>2,'amount'=>'1.00']);financeServiceAssert(false,'Participant beheert een vreemd effectenaccount.');}
catch(AetherFinanceException $e){financeServiceAssert($e->getHttpStatus()===403&&$GLOBALS['financeState']===$before,'Geweigerde effectenactie schrijft toch gegevens.');}

$buy=aetherBuyCompanyShare($pdo,$participant,['idCharacter'=>1,'idCompany'=>7,'shareClass'=>'A']);
financeServiceAssert($buy['success']===true&&$buy['unitPrice']===100.0&&$GLOBALS['financeState']['characters'][1]['bankaccount']==='897.50'&&$GLOBALS['financeState']['allocated'][7]===100,'Aankoop van het laatste aandeel verwerkt prijs, saldo of allocatie fout.');
$before=$GLOBALS['financeState'];
try{aetherBuyCompanyShare($pdo,$director,['idCharacter'=>2,'idCompany'=>7,'shareClass'=>'A']);financeServiceAssert(false,'Een aandeel boven 100% werd gekocht.');}
catch(AetherFinanceException $e){financeServiceAssert($GLOBALS['financeState']===$before,'Geweigerde aandelenaankoop wijzigt gegevens.');}

$linkId=(int)$buy['idLinkCharacterTrait'];
$sale=aetherSaveCompanyShare($pdo,$participant,['action'=>'decrease_rank','idLinkCharacterTrait'=>$linkId]);
financeServiceAssert($sale===['success'=>true,'saleValue'=>100.0]&&$GLOBALS['financeState']['characters'][1]['bankaccount']==='997.50'&&$GLOBALS['financeState']['allocated'][7]===99,'Aandelenverkoop herstelt saldo of beschikbaarheid niet.');

$GLOBALS['financeState']['allocated'][7]=98;
$adminBuy=aetherBuyCompanyShare($pdo,$administrator,['idCharacter'=>2,'idCompany'=>7,'shareClass'=>'A']);
financeServiceAssert($adminBuy['success']===true&&$GLOBALS['financeState']['characters'][2]['bankaccount']==='900.00','Administrator behield de bestaande aandelentoegang niet.');

if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}
echo "Character finance service tests passed.\n";

register_shutdown_function(static function()use($fixture):void{
    if(!is_dir($fixture))return;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
    rmdir($fixture);
});
