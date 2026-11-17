<?php
declare(strict_types=1);
// Uses a disposable schema on an explicitly selected local MariaDB server, never the app database.
require_once dirname(__DIR__).'/api/research/researchReadService.php';
require_once dirname(__DIR__).'/api/shared/idempotency.php';
$dsn=getenv('RESEARCH_TEST_DSN');
if (!$dsn) {echo "SKIP: set RESEARCH_TEST_DSN for an isolated local MariaDB server.\n";exit;}
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D',$dsn)) throw new RuntimeException('Only an explicit local test server without a database name is accepted.');
$pdo=new PDO($dsn,getenv('RESEARCH_TEST_USER')?:'root',getenv('RESEARCH_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
function checkResearch(bool $ok,string $why): void {if (!$ok) throw new RuntimeException($why);}
function failResearch(callable $fn,int $status): void {try{$fn();}catch(AetherResearchException $e){checkResearch($e->status===$status,'Wrong status '.$e->status.': '.$e->getMessage());return;}throw new RuntimeException('Expected failure '.$status);}
function txResearch(PDO $pdo,callable $fn): mixed {$pdo->beginTransaction();try{researchLock($pdo);$v=$fn();$pdo->commit();return $v;}catch(Throwable $e){$pdo->rollBack();throw $e;}}
if (($argv[1]??'')==='--worker') {
    $args=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);$db=$args['database'];
    if (!preg_match('/^aether_research_test_[a-f0-9]+$/D',$db)) throw new RuntimeException('Not a test schema.');
    $pdo->exec('USE '.$db);$char=(int)$argv[3];
    $result=txResearch($pdo,function()use($pdo,$args,$char){usleep(100000);return researchScan($pdo,['id'=>$char,'role'=>'participant'],['idEvent'=>1,'idCharacter'=>$char,'code'=>$args['code']]);});
    echo researchJson($result);exit;
}
$db='aether_research_test_'.bin2hex(random_bytes(5));$pdo->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$pdo->exec('USE '.$db);
try {
    $pdo->exec("CREATE TABLE tblSchemaMigration(migrationCode VARCHAR(100) PRIMARY KEY,description TEXT);
        CREATE TABLE tblUser(id INT PRIMARY KEY,role VARCHAR(30));
        CREATE TABLE tblEvent(id INT PRIMARY KEY,title VARCHAR(100),dateStart DATE,dateEnd DATE);
        CREATE TABLE tblSkill(id INT PRIMARY KEY,name VARCHAR(100));
        CREATE TABLE tblSkillSpecialisation(id INT PRIMARY KEY,idSkill INT,name VARCHAR(100),kind VARCHAR(30));
        CREATE TABLE tblCharacter(id INT PRIMARY KEY,idUser INT,type VARCHAR(30),state VARCHAR(30),class VARCHAR(30),title VARCHAR(100),firstName VARCHAR(100),lastName VARCHAR(100));
        CREATE TABLE tblLinkEventCharacter(idEvent INT,idCharacter INT,PRIMARY KEY(idEvent,idCharacter));
        CREATE TABLE tblLinkCharacterSkill(idCharacter INT,idSkill INT,level INT);
        CREATE TABLE tblCharacterSpecialisation(idCharacter INT,idSkill INT,idSkillSpecialisation INT);
        INSERT INTO tblEvent VALUES(1,'Eén','2026-06-09','2026-06-11'),(2,'Twee','2027-06-09','2027-06-11'),(3,'Niet gekoppeld','2028-06-09','2028-06-11');
        INSERT INTO tblSkill VALUES(6,'Economie');
        INSERT INTO tblSkillSpecialisation VALUES(72,6,'Aandelenmarkt','specialisation'),(73,6,'Auditeren','specialisation');
        INSERT INTO tblUser VALUES(99,'director');");
    for($i=1;$i<=6;$i++) {
        researchQuery($pdo,"INSERT INTO tblUser VALUES(?,'participant')",[$i]);
        researchQuery($pdo,"INSERT INTO tblCharacter VALUES(?,?,?,'active','all','','Personage',?)",[$i,$i,$i===6?'extra':'player',(string)$i]);
        researchQuery($pdo,'INSERT INTO tblLinkEventCharacter VALUES(1,?),(2,?)',[$i,$i]);
        researchQuery($pdo,'INSERT INTO tblLinkCharacterSkill VALUES(?,6,?)',[$i,min(3,$i-1)]);
    }
    $pdo->exec('INSERT INTO tblCharacterSpecialisation VALUES(5,6,72),(5,6,73)');
    $migration=file_get_contents(dirname(__DIR__).'/sql/migrations/0024_research.sql');$pdo->exec($migration);$pdo->exec($migration);
    $idem=file_get_contents(dirname(__DIR__).'/sql/migrations/apply/0004_api_idempotency.sql');$pdo->exec(substr($idem,0,strpos($idem,'START TRANSACTION;')));
    $ad=['id'=>99,'role'=>'director'];$user=fn(int $id)=>['id'=>$id,'role'=>'participant'];
    $save=fn(string $op,array $data)=>txResearch($pdo,fn()=>researchAdminSave($pdo,$ad,$op,$data));
    $challenge=$save('challenge_save',['title'=>'Onderzoek','description'=>'<p>Test</p><script>evil()</script>','eventIds'=>[1,2]])['id'];
    checkResearch(!str_contains(researchOne($pdo,'SELECT description FROM tblResearchChallenge WHERE id=?',[$challenge])['description'],'evil'),'Sanitization');
    $make=function(string $name)use($save,$challenge,$pdo):array{
        $id=$save('discovery_save',['idChallenge'=>$challenge,'title'=>$name,'description'=>'','idSkill'=>6,'bonusIds'=>[72,73],'active'=>false])['id'];
        return researchDiscovery($pdo,$id);
    };
    $step=function(array $d,int $level,string $content,bool $confirm=false,array $conditions=[])use($save,$pdo):int{
        $route=researchOne($pdo,"SELECT id FROM tblResearchRoute WHERE idDiscovery=? AND level=? AND kind='general'",[$d['id'],$level]);
        $default=$level===0?researchOne($pdo,'SELECT * FROM tblResearchStep WHERE idRoute=? AND position=1 AND content=?',[$route['id'],AETHER_RESEARCH_DEFAULT_FEEDBACK]):null;
        return $save('step_save',array_merge($default?['id'=>(int)$default['id'],'revision'=>(int)$default['revision']]:[],['idRoute'=>(int)$route['id'],'content'=>$content,'confirmationRequired'=>$confirm,'conditions'=>$conditions]))['id'];
    };
    $activate=function(array $d)use($save,$pdo):void{
        $d=researchDiscovery($pdo,(int)$d['id']);$save('discovery_save',['id'=>(int)$d['id'],'revision'=>(int)$d['revision'],'title'=>$d['title'],'description'=>$d['description'],'idSkill'=>6,'bonusIds'=>[72,73],'active'=>true]);
    };
    $scan=fn(array $d,int $char,int $event=1)=>txResearch($pdo,fn()=>researchScan($pdo,$user($char),['idCharacter'=>$char,'idEvent'=>$event,'code'=>'aether-research:'.$d['qrToken']]));
    $advance=fn(array $d,int $char,int $s,string $action='shown',int $event=1)=>txResearch($pdo,fn()=>researchAdvance($pdo,$user($char),['idCharacter'=>$char,'idEvent'=>$event,'idDiscovery'=>$d['id'],'idStep'=>$s],$action));
    $state=fn(array $d,int $char,int $event=1)=>txResearch($pdo,fn()=>researchRead($pdo,$user($char),'detail',['idCharacter'=>$char,'idEvent'=>$event,'idDiscovery'=>$d['id']]));
    $instantId=$save('discovery_save',['idChallenge'=>$challenge,'title'=>'Immediate default','description'=>'','idSkill'=>6,'bonusIds'=>[72,73],'active'=>true])['id'];
    $instant=researchDiscovery($pdo,$instantId);checkResearch((bool)$instant['active'],'Discovery can be active at creation');
    $instantRoutes=researchFeedbackRoutes($pdo,$instantId);$defaultRoute=researchSelectRoute($instantRoutes,4,5,[72,73]);
    checkResearch((int)$defaultRoute['level']===0,'All empty higher levels fall back to untrained');
    $defaultSteps=researchSteps($pdo,(int)$defaultRoute['id']);checkResearch(count($defaultSteps)===1&&$defaultSteps[0]['content']==='No additional information','Untrained default created');
    $blank=$step($instant,4,'');$scan($instant,5);checkResearch($state($instant,5)['current']['content']==='No additional information','Blank active draft is not selected');
    $advance($instant,5,(int)$defaultSteps[0]['id']);checkResearch($state($instant,5)['status']==='completed','Fallback completion uses actual feedback route');
    $sparse=$make('Sparse levels');$first=$step($sparse,1,'Basisopdracht',true);$emptyMiddle=$step($sparse,1,'');$last=$step($sparse,1,'Vervolg');$step($sparse,2,'<p>&nbsp;</p>');$master=$step($sparse,3,'Meesterfeedback');$step($sparse,4,'');$activate($sparse);
    foreach([1=>0,2=>1,3=>1,4=>3,5=>3] as $char=>$sourceLevel){
        $scan($sparse,$char);$found=researchOne($pdo,'SELECT f.*,r.level AS sourceLevel FROM tblResearchFound f JOIN tblResearchRoute r ON r.id=f.idRoute WHERE f.idEvent=1 AND f.idCharacter=? AND f.idDiscovery=?',[$char,$sparse['id']]);
        checkResearch((int)$found['sourceLevel']===$sourceLevel&&(int)$found['effectiveLevel']===$char-1,'Nearest lower filled route preserves effective level');
    }
    $advance($sparse,3,$first);checkResearch($state($sparse,3)['status']==='awaiting_confirmation','Fallback retains confirmation requirement');
    researchQuery($pdo,'INSERT INTO tblResearchMedia(idStep,filename,mime,byteSize,sha256,bytes) VALUES (?,?,?,?,?,?)',[$last,'fallback.png','image/png',8,hash('sha256','fallback'),'fallback']);$fallbackMedia=(int)$pdo->lastInsertId();
    failResearch(fn()=>researchMediaPermission($pdo,$user(3),$fallbackMedia,1,3),404);
    failResearch(fn()=>$advance($sparse,3,$emptyMiddle),403);$advance($sparse,3,$first,'confirm');checkResearch($state($sparse,3)['current']['id']===$last,'Empty draft steps skipped after confirmation');
    researchMediaPermission($pdo,$user(3),$fallbackMedia,1,3);failResearch(fn()=>researchMediaPermission($pdo,$user(4),$fallbackMedia,1,4),404);
    $advance($sparse,3,$last);checkResearch($state($sparse,3)['status']==='completed','Fallback completes after all filled steps');
    $draft=researchOne($pdo,'SELECT * FROM tblResearchStep WHERE id=?',[$emptyMiddle]);
    failResearch(fn()=>$save('step_save',['id'=>$emptyMiddle,'revision'=>(int)$draft['revision'],'idRoute'=>(int)$draft['idRoute'],'content'=>'New mandatory step','conditions'=>[]]),409);
    $legacy=$make('Existing empty untrained');$zero=researchOne($pdo,"SELECT id FROM tblResearchRoute WHERE idDiscovery=? AND level=0 AND kind='general'",[$legacy['id']]);researchQuery($pdo,'DELETE FROM tblResearchStep WHERE idRoute=?',[$zero['id']]);$activate($legacy);checkResearch(researchSteps($pdo,(int)$zero['id'])[0]['content']===AETHER_RESEARCH_DEFAULT_FEEDBACK,'Activation initializes existing empty untrained feedback');
    $d=$make('Five levels');$levelSteps=[];for($l=0;$l<5;$l++)$levelSteps[$l]=$step($d,$l,"<p>Secret level $l</p>");$activate($d);
    for($char=1;$char<=5;$char++) {
        $scan($d,$char);$s=$state($d,$char);checkResearch(str_contains($s['current']['content'],'Secret level '.($char-1)),'Five effective levels');
        checkResearch(!array_key_exists('effectiveLevel',$s)&&!array_key_exists('conditions',$s['current']),'No hidden configuration in SF projection');
        checkResearch(researchOne($pdo,'SELECT completedAt FROM tblResearchFound WHERE idCharacter=? AND idDiscovery=?',[$char,$d['id']])['completedAt']===null,'Fetch is not displayed acknowledgement');
        $advance($d,$char,$levelSteps[$char-1]);$scan($d,$char);$advance($d,$char,$levelSteps[$char-1]);checkResearch($state($d,$char)['status']==='completed','Single displayed step completes once');
    }
    checkResearch(researchLevel(3,[72,73],[72,73])===4 && researchLevel(0,[72,73],[72,73])===1,'At most one bonus');
    failResearch(fn()=>researchRead($pdo,$user(2),'detail',['idCharacter'=>1,'idEvent'=>1,'idDiscovery'=>$d['id']]),403);
    failResearch(fn()=>researchScan($pdo,$user(1),['idCharacter'=>1,'idEvent'=>3,'code'=>$d['qrToken']]),403);
    failResearch(fn()=>researchAdminSave($pdo,$user(1),'challenge_save',[]),403);
    researchManager(['role'=>'administrator']);researchManager($ad);$scan($d,6); // active extra
    researchQuery($pdo,"UPDATE tblCharacter SET state='draft' WHERE id=6");failResearch(fn()=>$scan($d,6),403);$pdo->exec("UPDATE tblCharacter SET state='active' WHERE id=6");
    failResearch(fn()=>researchScan($pdo,$user(1),['idCharacter'=>1,'idEvent'=>1,'code'=>'wrong']),422);
    failResearch(fn()=>researchScan($pdo,$ad,['idCharacter'=>1,'idEvent'=>3,'code'=>$d['qrToken']]),409);
    $scan($d,1,2);checkResearch($state($d,1,2)['status']==='new_feedback','Event isolation');
    $v=$make('Variants');for($l=0;$l<5;$l++)$step($v,$l,'General');
    $special=$save('route_save',['idDiscovery'=>(int)$v['id'],'level'=>4,'kind'=>'specialisation','idRequiredSpecialisation'=>72,'priority'=>1,'characterIds'=>[]])['id'];
    $save('step_save',['idRoute'=>$special,'content'=>'Specialised','conditions'=>[]]);
    $personal=$save('route_save',['idDiscovery'=>(int)$v['id'],'level'=>4,'kind'=>'character','idRequiredSpecialisation'=>73,'priority'=>0,'characterIds'=>[5]])['id'];
    $save('step_save',['idRoute'=>$personal,'content'=>'Personal','conditions'=>[]]);
    $save('route_save',['idDiscovery'=>(int)$v['id'],'level'=>4,'kind'=>'character','characterIds'=>[5]]);
    $otherSpecial=$save('route_save',['idDiscovery'=>(int)$v['id'],'level'=>4,'kind'=>'specialisation','idRequiredSpecialisation'=>73,'priority'=>1])['id'];
    $save('step_save',['idRoute'=>$otherSpecial,'content'=>'Other specialised','conditions'=>[]]);
    $routes=researchFeedbackRoutes($pdo,(int)$v['id']);
    checkResearch(count(researchSelectRoutes($routes,4,5,[72,73]))===4,'General and every eligible specialisation/personage variant');
    checkResearch(count(researchSelectRoutes($routes,4,4,[72]))===2,'Specialisation adds to general feedback');
    checkResearch(!in_array($personal,array_map('intval',array_column(researchSelectRoutes($routes,4,5,[72]),'id')),true),'Required specialisation is a condition');
    $filledRoutes=researchFeedbackRoutes($pdo,(int)$v['id']);foreach($filledRoutes as &$r)if((int)$r['id']===$personal)$r['hasFeedback']=false;unset($r);
    checkResearch(in_array($special,array_map('intval',array_column(researchSelectRoutes($filledRoutes,4,5,[72,73]),'id')),true),'Empty personal variant does not hide populated specialisation feedback');
    foreach($filledRoutes as &$r)if((int)$r['level']===4)$r['hasFeedback']=false;unset($r);
    checkResearch((int)researchSelectRoute($filledRoutes,4,5,[72,73])['level']===3,'Empty variants and general route fall back one level');
    $activate($v);$scan($v,5);checkResearch(count($state($v,5)['currents'])===4,'All four eligible feedback branches are visible');
    $task=$make('Mixed steps');for($l=1;$l<5;$l++)$step($task,$l,'Other');$s1=$step($task,0,'One');$s2=$step($task,0,'Two',true);$s3=$step($task,0,'Three');$activate($task);$scan($task,1);
    failResearch(fn()=>$advance($task,1,$s3),409);$advance($task,1,$s1);failResearch(fn()=>$advance($task,1,$s2,'confirm'),409);$advance($task,1,$s2);checkResearch($state($task,1)['status']==='awaiting_confirmation','Confirmation required independently');
    $dep=$make('Both prerequisites');for($l=0;$l<5;$l++)$depSteps[$l]=$step($dep,$l,'Unlocked',false,[['type'=>'discoveries','ids'=>[(int)$task['id'],(int)$d['id']]]]);$activate($dep);$scan($dep,1);
    checkResearch($state($dep,1)['status']==='waiting','Pending mandatory task blocks dependent discovery');$advance($task,1,$s2,'confirm');checkResearch($state($task,1)['current']['id']===$s3,'Confirmation immediately reveals next');checkResearch($state($dep,1)['status']==='waiting','All steps must complete');$advance($task,1,$s3);checkResearch($state($dep,1)['status']==='new_feedback','All discovery prerequisites release');
    $timed=$make('Timer');$timedSteps=[];for($l=0;$l<5;$l++)$timedSteps[$l]=$step($timed,$l,'Timed secret',false,[['type'=>'duration','seconds'=>3600,'scope'=>'shared','discoveryId'=>(int)$timed['id'],'stepId'=>0,'action'=>'scanned','showCountdown'=>true]]);$activate($timed);
    $workerFile=tempnam(sys_get_temp_dir(),'research-workers');file_put_contents($workerFile,researchJson(['database'=>$db,'code'=>$timed['qrToken']]));$workers=[];
    foreach([1,2]as$char){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--worker',$workerFile,(string)$char],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    foreach($workers as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);checkResearch(proc_close($process)===0,'Concurrent worker failed: '.$err.$out);}unlink($workerFile);
    $clocks=researchAll($pdo,'SELECT * FROM tblResearchTimer WHERE idEvent=1 AND idStep IN ('.implode(',',$timedSteps).')');checkResearch(count($clocks)===5,'Each shared step has one clock');checkResearch(count(array_unique(array_column($clocks,'startedAt')))===1,'Concurrent starts choose one shared origin for all routes');
    $before=$clocks[0]['endsAt'];$scan($timed,1);checkResearch($state($timed,1)['status']==='waiting_time' && $state($timed,1)['current']===null,'First step timer hides contents');checkResearch($state($timed,1)['countdownSeconds']>0,'Optional countdown');
    checkResearch(researchOne($pdo,'SELECT endsAt FROM tblResearchTimer WHERE idEvent=1 AND idStep=?',[$clocks[0]['idStep']])['endsAt']===$before,'Rescan does not reset shared clock');
    $scan($timed,3);checkResearch(researchOne($pdo,'SELECT endsAt FROM tblResearchTimer WHERE idEvent=1 AND idStep=?',[$timedSteps[2]])['endsAt']===$before,'Late arrival uses same deadline');
    $scan($timed,1,2);checkResearch(researchOne($pdo,'SELECT idEvent FROM tblResearchTimer WHERE idEvent=2 AND idStep=?',[$timedSteps[0]])!==null,'Separate event clock');
    $personalTimer=$make('Personal timer');for($l=0;$l<5;$l++)$pt[$l]=$step($personalTimer,$l,'Hidden countdown',false,[['type'=>'duration','seconds'=>60,'scope'=>'character','discoveryId'=>(int)$personalTimer['id'],'stepId'=>0,'action'=>'scanned','showCountdown'=>false]]);$activate($personalTimer);$scan($personalTimer,1);usleep(10000);$scan($personalTimer,2);
    $a=researchOne($pdo,'SELECT * FROM tblResearchTimer WHERE idStep=? AND scopeCharacter=1',[$pt[0]]);$b=researchOne($pdo,'SELECT * FROM tblResearchTimer WHERE idStep=? AND scopeCharacter=2',[$pt[1]]);checkResearch($a['startedAt']!==$b['startedAt'],'Individual clock origins');checkResearch($state($personalTimer,1)['countdownSeconds']===null && $state($personalTimer,1)['timed'],'Hidden countdown still shows clock');
    $bytes='fake image test bytes';researchQuery($pdo,'INSERT INTO tblResearchMedia(idStep,filename,mime,byteSize,sha256,bytes) VALUES (?,?,?,?,?,?)',[$pt[0],'secret.png','image/png',strlen($bytes),hash('sha256',$bytes),$bytes]);$mid=(int)$pdo->lastInsertId();
    failResearch(fn()=>researchMediaPermission($pdo,$user(1),$mid,1,1),404);researchMediaPermission($pdo,$ad,$mid,0,0);
    researchQuery($pdo,"UPDATE tblResearchTimer SET endsAt='2000-01-01 00:00:00' WHERE idStep=? AND scopeCharacter=1",[$pt[0]]);checkResearch($state($personalTimer,1)['status']==='new_feedback','Elapsed timer releases');researchMediaPermission($pdo,$user(1),$mid,1,1);failResearch(fn()=>researchMediaPermission($pdo,$user(2),$mid,1,2),404);failResearch(fn()=>researchMediaPermission($pdo,$user(1),$mid,2,1),404);
    $advance($personalTimer,1,$pt[0]);researchMediaPermission($pdo,$user(1),$mid,1,1);
    checkResearch(researchLocalToUtc('2026-06-09T12:00','Europe/Brussels')==='2026-06-09 10:00:00','Event-local timezone conversion');failResearch(fn()=>researchLocalToUtc('2026-03-29T02:30','Europe/Brussels'),422);failResearch(fn()=>researchLocalToUtc('2026-10-25T02:30','Europe/Brussels'),422);
    $absolute=$make('Absolute time');for($l=0;$l<5;$l++)$at[$l]=$step($absolute,$l,'Fixed time',false,[['type'=>'absolute','times'=>['1'=>'2099-06-09T12:00','2'=>'2000-06-09T12:00'],'showCountdown'=>false]]);$activate($absolute);$scan($absolute,1);$scan($absolute,1,2);checkResearch($state($absolute,1)['status']==='waiting_time'&&$state($absolute,1,2)['status']==='new_feedback','Absolute time per event');
    $cycleA=$make('Cycle A');$cycleB=$make('Cycle B');$step($cycleA,0,'A',false,[['type'=>'discoveries','ids'=>[(int)$cycleB['id']]]]);failResearch(fn()=>$step($cycleB,0,'B',false,[['type'=>'discoveries','ids'=>[(int)$cycleA['id']]]]),422);
    $taskTimer=$make('Confirmation trigger');$ts1=$step($taskTimer,0,'Press button',true);$ts2=$step($taskTimer,0,'Later',false,[['type'=>'duration','seconds'=>10,'scope'=>'shared','discoveryId'=>(int)$taskTimer['id'],'stepId'=>$ts1,'action'=>'confirmed','showCountdown'=>true]]);for($l=1;$l<5;$l++)$step($taskTimer,$l,'Other');$activate($taskTimer);$scan($taskTimer,1);$advance($taskTimer,1,$ts1);checkResearch(!researchOne($pdo,'SELECT idStep FROM tblResearchTimer WHERE idStep=?',[$ts2]),'Showing alone does not start confirmation timer');$advance($taskTimer,1,$ts1,'confirm');checkResearch((bool)researchOne($pdo,'SELECT idStep FROM tblResearchTimer WHERE idStep=?',[$ts2]),'Confirmation starts timer');
    $idemInput=['idCharacter'=>1,'idEvent'=>1,'code'=>$timed['qrToken']];$key='research-test-request-123';$calls=0;
    for($i=0;$i<2;$i++)aetherRunIdempotentMutation($pdo,$user(1),'research.scan',$key,$idemInput,function()use($pdo,$user,$idemInput,&$calls){researchLock($pdo);$calls++;return researchScan($pdo,$user(1),$idemInput);},fn()=>researchAccess($pdo,$user(1),1,1));checkResearch($calls===1,'Idempotency replay skips mutation');
    $progress=txResearch($pdo,fn()=>researchRead($pdo,$ad,'progress',['idEvent'=>1,'idDiscovery'=>$task['id']]));checkResearch($progress['progress'][0]['steps'][1]['shownAt']!==null && $progress['progress'][0]['steps'][1]['confirmedAt']!==null,'Separate shown and confirmed tracking');
    echo "PASS: MariaDB migration twice; levels 0–4; bonus cap; all eligible personal/specialisation/general feedback; rich text; role/character/event access; visibility acknowledgements; sequential mixed confirmation; AND prerequisites; cycles; shared concurrent and personal timers; trigger actions; absolute times and DST; repeated scans; idempotency; protected media; progress.\n";
    if (getenv('RESEARCH_BROWSER_FIXTURE')) {
        $pdo->exec("ALTER TABLE tblUser ADD username VARCHAR(100),ADD firstName VARCHAR(100),ADD lastName VARCHAR(100); UPDATE tblUser SET username=CONCAT('test',id),firstName='Test',lastName=CAST(id AS CHAR)");
        file_put_contents(getenv('RESEARCH_BROWSER_FIXTURE'),researchJson(['database'=>$db,'dsn'=>$dsn,'user'=>getenv('RESEARCH_TEST_USER')?:'root','password'=>getenv('RESEARCH_TEST_PASSWORD')?:'']));
    }
} finally {if($pdo->inTransaction())$pdo->rollBack();if(!getenv('RESEARCH_BROWSER_FIXTURE'))$pdo->exec('DROP DATABASE '.$db);}
