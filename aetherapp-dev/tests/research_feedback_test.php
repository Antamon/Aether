<?php
declare(strict_types=1);
// Fast service regression using an in-memory SQLite fixture. MariaDB migration coverage
// remains in research_integration_test.php; only INSERT IGNORE is adapted at runtime.
require_once dirname(__DIR__).'/api/research/researchReadService.php';
final class FeedbackTestPdo extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        return parent::prepare(str_replace('INSERT IGNORE','INSERT OR IGNORE',$query),$options);
    }
}
function feedbackCheck(bool $ok,string $message): void {if (!$ok) throw new RuntimeException($message);}
function feedbackFails(callable $action,int $status): void {
    try {$action();} catch(AetherResearchException $error) {feedbackCheck($error->status===$status,$error->getMessage());return;}
    throw new RuntimeException('Expected failure '.$status);
}
$pdo=new FeedbackTestPdo('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE tblSchemaMigration(migrationCode TEXT,description TEXT);
    CREATE TABLE tblEvent(id INTEGER PRIMARY KEY,title TEXT,dateStart TEXT,dateEnd TEXT);
    CREATE TABLE tblSkill(id INTEGER PRIMARY KEY,name TEXT);
    CREATE TABLE tblSkillSpecialisation(id INTEGER PRIMARY KEY,idSkill INTEGER,name TEXT,kind TEXT);
    CREATE TABLE tblCharacter(id INTEGER PRIMARY KEY,idUser INTEGER,type TEXT,state TEXT,class TEXT,title TEXT,firstName TEXT,lastName TEXT);
    CREATE TABLE tblLinkEventCharacter(idEvent INTEGER,idCharacter INTEGER);
    CREATE TABLE tblCharacterSpecialisation(idCharacter INTEGER,idSkill INTEGER,idSkillSpecialisation INTEGER);
    CREATE TABLE tblLinkCharacterSkill(idCharacter INTEGER,idSkill INTEGER,level INTEGER);
    INSERT INTO tblEvent VALUES(1,'Test','2026-01-01','2026-12-31');
    INSERT INTO tblSkill VALUES(6,'Speuren'),(7,'Economie');
    INSERT INTO tblSkillSpecialisation VALUES(72,6,'Sporen','specialisation'),(73,6,'Geuren','specialisation'),(74,7,'Auditeren','specialisation');
    INSERT INTO tblCharacter VALUES(1,1,'player','active','all','','Een',''),(2,2,'player','active','all','','Twee','');
    INSERT INTO tblLinkEventCharacter VALUES(1,1),(1,2);
    INSERT INTO tblLinkCharacterSkill VALUES(1,6,1),(2,6,1);
    INSERT INTO tblCharacterSpecialisation VALUES(1,6,72),(1,6,73);");
$schema=file_get_contents(dirname(__DIR__).'/sql/migrations/0024_research.sql');
$schema=preg_replace('/BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$schema);
$schema=preg_replace('/\bUNSIGNED\b|CHARACTER SET ascii COLLATE ascii_bin/','',$schema);
$schema=preg_replace('/\) ENGINE=InnoDB(?: DEFAULT CHARSET=utf8mb4)?/',')',$schema);
$schema=preg_replace('/UNIQUE KEY \w+\(/','UNIQUE(',$schema);
$schema=preg_replace('/^\s*KEY ix_research_action_trigger[^\n]+\n/m','',$schema);
$pdo->exec(str_replace('INSERT IGNORE','INSERT OR IGNORE',$schema));
$admin=['id'=>99,'role'=>'director'];$user=['id'=>1,'role'=>'participant'];
$save=function(string $op,array $input)use($pdo,$admin):array{
    $pdo->beginTransaction();try{$result=researchAdminSave($pdo,$admin,$op,$input);$pdo->commit();return $result;}
    catch(Throwable $error){$pdo->rollBack();throw $error;}
};
$challenge=$save('challenge_save',['title'=>'Test','description'=>'','eventIds'=>[1]])['id'];
$discovery=$save('discovery_save',['title'=>'Stof','description'=>'','idChallenge'=>$challenge,'idSkill'=>6])['id'];
$route=(int)researchOne($pdo,"SELECT id FROM tblResearchRoute WHERE idDiscovery=? AND level=1 AND kind='general'",[$discovery])['id'];
$first=$save('step_save',['idRoute'=>$route,'content'=>'Algemeen','conditions'=>[],'followUp'=>true])['id'];
$steps=researchSteps($pdo,$route);feedbackCheck(count($steps)===2 && $steps[1]['content']==='' && (int)$steps[1]['position']===2,'Checkbox creates one blank following row');
$save('step_save',['id'=>$first,'revision'=>1,'idRoute'=>$route,'content'=>'Algemeen','followUp'=>true]);
feedbackCheck(count(researchSteps($pdo,$route))===2,'Repeated save cannot duplicate the follow-up');
$next=(int)$steps[1]['id'];
$save('step_save',['id'=>$next,'revision'=>1,'idRoute'=>$route,'content'=>'Algemeen vervolg']);
$variant=function(string $kind,int $spec,array $characters=[])use($save,$discovery):int{
    return $save('route_save',['idDiscovery'=>$discovery,'level'=>1,'kind'=>$kind,'idRequiredSpecialisation'=>$spec,'characterIds'=>$characters])['id'];
};
$special=$variant('specialisation',72);$special2=$variant('specialisation',73);
$personal=$variant('character',0,[1]);$personal2=$variant('character',0,[1]);
feedbackFails(fn()=>$variant('specialisation',74),422);
$s=$save('step_save',['idRoute'=>$special,'content'=>'Sporen','confirmationRequired'=>true])['id'];
$s2=$save('step_save',['idRoute'=>$special2,'content'=>'Geuren'])['id'];
$p=$save('step_save',['idRoute'=>$personal,'content'=>'Persoonlijk'])['id'];
$p2=$save('step_save',['idRoute'=>$personal2,'content'=>'Later persoonlijk','conditions'=>[['type'=>'absolute','times'=>[1=>'2099-01-01T12:00'],'showCountdown'=>true]]])['id'];
$routes=researchFeedbackRoutes($pdo,$discovery);
feedbackCheck(count(researchSelectRoutes($routes,1,1,[72,73]))===5,'All matching variants coexist, including equal priorities and overlapping characters');
feedbackCheck(count(researchSelectRoutes($routes,1,2,[]))===1,'Unqualified character gets only general feedback');
feedbackCheck((int)researchSelectRoutes($routes,4,1,[72,73])[0]['level']===1,'Empty levels fall back');
// Save variant metadata and its feedback atomically; a stale step rolls both back.
$specialRow=researchOne($pdo,'SELECT * FROM tblResearchRoute WHERE id=?',[$special]);
$variantInput=['id'=>$special,'revision'=>(int)$specialRow['revision'],'idDiscovery'=>$discovery,'level'=>1,'kind'=>'specialisation','idRequiredSpecialisation'=>72,'characterIds'=>[]];
feedbackFails(fn()=>$save('step_save',['id'=>$s,'revision'=>999,'idRoute'=>$special,'content'=>'Wrong','variant'=>$variantInput]),409);
feedbackCheck((int)researchOne($pdo,'SELECT revision FROM tblResearchRoute WHERE id=?',[$special])['revision']===(int)$specialRow['revision'],'Failed combined edit rolls back the variant');
$save('step_save',['id'=>$s,'revision'=>1,'idRoute'=>$special,'content'=>'Sporen aangepast','confirmationRequired'=>true,'variant'=>$variantInput]);
// Activate and scan both a qualified and an unqualified character.
$d=researchDiscovery($pdo,$discovery);
$save('discovery_save',['id'=>$discovery,'revision'=>(int)$d['revision'],'title'=>$d['title'],'description'=>'','idSkill'=>6,'active'=>true]);
researchScan($pdo,$user,['idEvent'=>1,'idCharacter'=>1,'code'=>$d['qrToken']]);
researchScan($pdo,['id'=>2,'role'=>'participant'],['idEvent'=>1,'idCharacter'=>2,'code'=>$d['qrToken']]);
$found=researchOne($pdo,'SELECT * FROM tblResearchFound WHERE idCharacter=1');
$state=fn()=>researchState($pdo,1,1,$found);
feedbackCheck(count($state()['currents'])===4 && $state()['status']==='new_feedback','Ready feedback is shown while another branch waits');
feedbackCheck(!in_array($p2,array_column($state()['currents'],'id'),true),'Future feedback stays hidden');
$pdo->exec('DELETE FROM tblCharacterSpecialisation WHERE idCharacter=1');
feedbackCheck(count($state()['currents'])===4,'Selected feedback remains fixed after specialisation changes');
$pdo->exec("DELETE FROM tblResearchAction WHERE actionCode='feedback_selected' AND idCharacter=1;
    INSERT INTO tblCharacterSpecialisation VALUES(1,6,72),(1,6,73);");
feedbackCheck(count($state()['currents'])===4,'Older discoveries without a selection snapshot expand to all qualifying routes');
$advance=fn(int $step,string $action='shown')=>researchAdvance($pdo,$user,['idEvent'=>1,'idCharacter'=>1,'idDiscovery'=>$discovery,'idStep'=>$step],$action);
feedbackFails(fn()=>$advance($next),409);
$advance($s);$advance($p);$advance($s2);
feedbackCheck(in_array($first,array_column($state()['currents'],'id'),true),'Other branches are independent of a pending confirmation');
feedbackCheck(in_array($s,array_column($state()['currents'],'id'),true),'Confirmation remains available');
$advance($first);feedbackCheck(in_array($next,array_column($state()['currents'],'id'),true),'Follow-up appears after its own preceding step');
$advance($next);$advance($s,'confirm');
feedbackCheck($state()['status']==='waiting_time','All ready branches complete before the remaining timer');
feedbackCheck(!researchOne($pdo,'SELECT completedAt FROM tblResearchFound WHERE idCharacter=1')['completedAt'],'Discovery waits for every branch');
feedbackFails(fn()=>researchAdvance($pdo,['id'=>2,'role'=>'participant'],['idEvent'=>1,'idCharacter'=>2,'idDiscovery'=>$discovery,'idStep'=>$s],'shown'),403);
foreach ([$s,$p2] as $step) researchQuery($pdo,'INSERT INTO tblResearchMedia(idStep,filename,mime,byteSize,sha256,bytes) VALUES (?,?,?,?,?,?)',[$step,'test.png','image/png',1,(string)$step,'x']);
$media=researchAll($pdo,'SELECT id,idStep FROM tblResearchMedia ORDER BY id');
researchMediaPermission($pdo,$user,(int)$media[0]['id'],1,1);
feedbackFails(fn()=>researchMediaPermission($pdo,$user,(int)$media[1]['id'],1,1),404);
researchQuery($pdo,"UPDATE tblResearchStep SET conditionsJson='[]' WHERE id=?",[$p2]);
researchMediaPermission($pdo,$user,(int)$media[1]['id'],1,1);
$advance($p2);
feedbackCheck($state()['status']==='completed' && (bool)researchOne($pdo,'SELECT completedAt FROM tblResearchFound WHERE idCharacter=1')['completedAt'],'Completes only after all branches');
feedbackFails(fn()=>$save('step_save',['id'=>$next,'revision'=>2,'idRoute'=>$route,'content'=>'Algemeen vervolg','followUp'=>true]),409);
$progress=researchRead($pdo,$admin,'progress',['idEvent'=>1,'idDiscovery'=>$discovery,'idCharacter'=>1]);
feedbackCheck(count($progress['progress'][0]['steps'])===6,'Admin progress includes all eligible branches');
echo "PASS: all eligible feedback, independent progression/confirmation/timers, media authorization, qualification, atomic edits and follow-up creation/locking.\n";
