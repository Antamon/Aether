<?php
declare(strict_types=1);
$root=getenv('AETHER_TEST_ROOT')?:dirname(__DIR__);
require_once $root.'/api/shared/translationChanges.php';
require_once $root.'/api/shared/translationService.php';
require_once $root.'/api/shared/contentLanguage.php';
function savedCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$config=tempnam(sys_get_temp_dir(),'aether-save-policy-');
file_put_contents($config,'<?php return '.var_export(['database'=>['host'=>'localhost','name'=>'test','user'=>'test','password'=>'test'],'translation'=>['enabled'=>true,'googleApiKey'=>'fake-key','monthlyCharacterLimit'=>500000,'maxAutomaticCharacters'=>2000]],true).';');
putenv('AETHER_CONFIG_FILE='.$config);
try {
 $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $pdo->exec("CREATE TABLE tblCharacterDiary(id INTEGER,idCharacter INTEGER,idEvent INTEGER,goals TEXT,achievements TEXT,gossip1 TEXT,gossip2 TEXT,gossip3 TEXT);
 CREATE TABLE tblContentTranslation(sourceHash TEXT,targetLanguage TEXT,sourceLanguage TEXT,sourceText TEXT,translatedText TEXT,format TEXT,status TEXT,provider TEXT,PRIMARY KEY(sourceHash,targetLanguage));
 CREATE TABLE tblTranslationUsage(budgetKey TEXT,monthKey TEXT,sentCharacters INTEGER DEFAULT 0,successfulCharacters INTEGER DEFAULT 0,PRIMARY KEY(budgetKey,monthKey));
 CREATE TABLE tblTranslationRequest(budgetKey TEXT,sourceHash TEXT,targetLanguage TEXT,state TEXT DEFAULT 'idle',attemptToken TEXT DEFAULT '',retryAfter TEXT,sourceLanguage TEXT DEFAULT '',translatedText TEXT,format TEXT DEFAULT 'plain',PRIMARY KEY(budgetKey,sourceHash,targetLanguage));
 INSERT INTO tblCharacterDiary VALUES(1,7,4,'Oud doel','Ongewijzigd resultaat','Privégerucht','','');");
 $GLOBALS['AETHER_AUTHENTICATED_USER']=['id'=>1,'role'=>'director'];$_SESSION=['aetherTranslationSecret'=>str_repeat('s',64)];
 $_SERVER=['REQUEST_METHOD'=>'POST','SCRIPT_NAME'=>'/aether/aetherapp/api/characters/getCharacterDiary.php','HTTP_X_AETHER_CONTENT_LANGUAGE'=>'nl'];
 aetherCaptureTranslationSave($pdo,['idCharacter'=>7]);savedCheck(!isset($GLOBALS['AETHER_TRANSLATION_SAVE']),'Read must not capture a save.');
 $read=aetherContentResponse($pdo,['entries'=>[['goals'=>'Existing untranslated text','achievements'=>'Another untranslated text']]],'nl');
 savedCheck(empty($read['aetherMissingContent']),'Opening a page must not enqueue any text.');
 $_SERVER['SCRIPT_NAME']='/aether/aetherapp/api/characters/saveCharacterDiary.php';
 $input=['idCharacter'=>7,'idEvent'=>4,'goals'=>'<p>Dit is een test</p>','achievements'=>'Ongewijzigd resultaat'];
 aetherCaptureTranslationSave($pdo,$input);
 $pdo->prepare('UPDATE tblCharacterDiary SET goals=? WHERE id=1')->execute([$input['goals']]);
 $changes=aetherSavedTranslationChanges($pdo,['success'=>true]);
 savedCheck(count($changes)===1&&reset($changes)['source']===$input['goals'],'Only the persisted changed field may be queued.');
 $saved=aetherContentResponse($pdo,['success'=>true,'entries'=>[['goals'=>$input['goals'],'achievements'=>'Ongewijzigd resultaat','gossip1'=>'Privégerucht']]],'nl',$changes);
 $jobs=$saved['aetherMissingContent']??[];savedCheck(count($jobs)===1,'One edited text, one job; never the other diary entries.');$job=$jobs[0];
 savedCheck($job['targetLanguage']==='en'&&$job['sourceLanguage']==='nl','Only translate toward the other language.');
 savedCheck(aetherTranslationGrantValid($GLOBALS['AETHER_AUTHENTICATED_USER'],$job['source'],'html|saved|nl',$job['automaticGrant']),'Automatic receipt is bound to saved content and source language.');
 savedCheck(!aetherTranslationGrantValid($GLOBALS['AETHER_AUTHENTICATED_USER'],$job['source'],'html|saved|nl',$job['grant']),'A viewing receipt must not authorize Google work.');
 savedCheck(!aetherTranslationGrantValid(['id'=>2],$job['source'],'html|saved|nl',$job['automaticGrant']),'Saved receipt is bound to user.');
 $calls=0;$transport=static function()use(&$calls):array{$calls++;return ['text'=>'<p>This is a test</p>','sourceLanguage'=>'nl'];};
 aetherTranslationAutomatic($pdo,$job['source'],'en','html',null,$transport);
 aetherTranslationAutomatic($pdo,$job['source'],'en','html',null,$transport);
 savedCheck($calls===1,'Repeated requests reuse the translated text.');
 savedCheck(aetherTranslationStatus($pdo)['used']===22,'Only the 22 changed source characters, including HTML, are reserved.');
 aetherCaptureTranslationSave($pdo,$input);savedCheck(aetherSavedTranslationChanges($pdo,['success'=>true])===[],'Saving unchanged text must generate no work.');
 $input['goals']='Not persisted';aetherCaptureTranslationSave($pdo,$input);
 savedCheck(aetherSavedTranslationChanges($pdo,['success'=>true])===[],'Submitted text which was not persisted must not be translated.');
 // English input uses only Dutch as the target.
 $_SERVER['HTTP_X_AETHER_CONTENT_LANGUAGE']='en';$pdo->exec("INSERT INTO tblCharacterDiary VALUES(2,8,5,'','','','','')");$input['idCharacter']=8;$input['idEvent']=5;$input['goals']='Brand new English';aetherCaptureTranslationSave($pdo,$input);
 $pdo->prepare('UPDATE tblCharacterDiary SET goals=? WHERE id=2')->execute([$input['goals']]);$changes=aetherSavedTranslationChanges($pdo,1);
 $saved=aetherContentResponse($pdo,1,'en',$changes);savedCheck($saved['aetherPayload']===1&&$saved['aetherMissingContent'][0]['targetLanguage']==='nl','Scalar save contracts stay unchanged; English text targets Dutch.');
 // Known source language takes precedence over the surrounding UI language.
 $language=aetherSavedTranslationLanguage($pdo,hash('sha256','New Dutch wording'),[hash('sha256','<p>Dit is een test</p>')=>'<p>Dit is een test</p>'],'en');
 savedCheck($language==='nl','Changing the interface language must not change the known source language of edited text.');
 // A rolled-back save cannot start translation.
 $input['idCharacter']=7;$input['idEvent']=4;$input['goals']='Rolled back';aetherCaptureTranslationSave($pdo,$input);$pdo->beginTransaction();$pdo->prepare('UPDATE tblCharacterDiary SET goals=? WHERE id=1')->execute([$input['goals']]);$pdo->rollBack();
 savedCheck(aetherSavedTranslationChanges($pdo,[])===[],'Rolled-back changes never start work.');
 $input['goals']=str_repeat('a',2001);aetherCaptureTranslationSave($pdo,$input);$pdo->prepare('UPDATE tblCharacterDiary SET goals=? WHERE id=1')->execute([$input['goals']]);
 $changes=aetherSavedTranslationChanges($pdo,[]);$saved=aetherContentResponse($pdo,[],'en',$changes);savedCheck(empty($saved['aetherMissingContent']),'Long edited text remains manual only.');
 savedCheck(aetherTranslationStatus($pdo)['used']===22,'Reads, unchanged saves and long texts consume zero additional characters.');
 echo "PASS: page reads cost zero; only persisted changed fields translate; other language only; domain/user-bound receipts; unchanged/rolled-back/long text costs zero; duplicate requests reuse cache; test diary costs exactly 22 source characters.\n";
}finally{unlink($config);putenv('AETHER_CONFIG_FILE');}
