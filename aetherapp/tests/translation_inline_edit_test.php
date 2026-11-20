<?php
declare(strict_types=1);
$root=getenv('AETHER_TEST_ROOT')?:dirname(__DIR__);
require_once $root.'/api/shared/translationChanges.php';
require_once $root.'/api/shared/translationService.php';
require_once $root.'/api/shared/contentLanguage.php';
function inlineCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE tblEvent(id INTEGER PRIMARY KEY,title TEXT,description TEXT);
 CREATE TABLE tblContentTranslation(sourceHash TEXT,targetLanguage TEXT,sourceLanguage TEXT,sourceText TEXT,translatedText TEXT,format TEXT,status TEXT,provider TEXT,PRIMARY KEY(sourceHash,targetLanguage));
 INSERT INTO tblEvent VALUES(1,'Cut!','Description');");
$s=$pdo->prepare('INSERT INTO tblContentTranslation VALUES(?,?,?,?,?,?,?,?)');$s->execute([hash('sha256','Cut!'),'nl','en','Cut!','Snee!','plain','ready','google-nmt']);
$GLOBALS['AETHER_AUTHENTICATED_USER']=['id'=>1,'role'=>'director'];$_SESSION=['aetherTranslationSecret'=>str_repeat('s',64)];
$_SERVER=['REQUEST_METHOD'=>'POST','SCRIPT_NAME'=>'/aether/aetherapp/api/events/updateEvent.php','HTTP_X_AETHER_CONTENT_LANGUAGE'=>'nl'];
$row=aetherTranslationCached($pdo,'Cut!','nl');$version=aetherTranslationVersion($row);
$packet=['id'=>1,'title'=>'Cut!','_aetherTranslationEdits'=>[['path'=>['title'],'sourceHash'=>hash('sha256','Cut!'),'version'=>$version]]];
$input=aetherCaptureTranslationSave($pdo,$packet);
inlineCheck($input===['id'=>1,'title'=>'Cut!'],'Translation metadata never enters domain validation.');
inlineCheck(aetherTranslationCached($pdo,'Cut!','nl')['translatedText']==='Snee!','No correction before normal authorization/save.');
$pdo->prepare('UPDATE tblEvent SET title=? WHERE id=?')->execute([$input['title'],$input['id']]);
$changes=aetherSavedTranslationChanges($pdo,1);
inlineCheck($pdo->query('SELECT title FROM tblEvent WHERE id=1')->fetchColumn()==='Cut!','English original must stay untouched.');
$corrected=aetherTranslationCached($pdo,'Cut!','nl');
inlineCheck($corrected['translatedText']==='Cut!'&&$corrected['provider']==='manual','Dutch correction is saved as manual.');
inlineCheck($changes===[],'Manual correction cannot create automatic Google work.');
$response=aetherContentResponse($pdo,['title'=>'Cut!'],'nl');
inlineCheck($response['aetherContentTranslations'][0]['text']==='Cut!','Identity correction must be returned to clear a previously rendered Snee!.');
$input=aetherCaptureTranslationSave($pdo,$packet);inlineCheck(aetherSavedTranslationChanges($pdo,1)===[],'Retrying the same correction is idempotent.');
$stale=$packet;$stale['title']='Knip!';
try{aetherCaptureTranslationSave($pdo,$stale);throw new RuntimeException('Stale correction was accepted.');}catch(DomainException $expected){}
$pdo->exec("UPDATE tblEvent SET title='New original' WHERE id=1");
try{aetherCaptureTranslationSave($pdo,$packet);throw new RuntimeException('Changed source was accepted.');}catch(DomainException $expected){}
$pdo->exec("UPDATE tblEvent SET title='Cut!' WHERE id=1");
$forged=$packet;$forged['_aetherTranslationEdits'][0]['path']=['description'];
try{aetherCaptureTranslationSave($pdo,$forged);throw new RuntimeException('Wrong field accepted.');}catch(DomainException $expected){}
$GLOBALS['AETHER_AUTHENTICATED_USER']['role']='participant';
try{aetherCaptureTranslationSave($pdo,$packet);throw new RuntimeException('Participant gained translation rights.');}catch(AetherInlineTranslationForbidden $expected){}
$GLOBALS['AETHER_AUTHENTICATED_USER']['role']='director';
$_SERVER['HTTP_X_AETHER_CONTENT_LANGUAGE']='en';$sourceEdit=aetherCaptureTranslationSave($pdo,['id'=>1,'title'=>'New English title']);
inlineCheck($sourceEdit['title']==='New English title','Editing in the source language remains a normal source edit.');
// Existing rich-text and nested personnel fields follow the same path checks.
$pdo->exec("CREATE TABLE tblCharacterSection(idCharacter INTEGER,section TEXT,content TEXT);INSERT INTO tblCharacterSection VALUES(7,'knowledge','<p>English source</p>');
 CREATE TABLE tblCompanyPersonnel(idCharacter INTEGER,idCompany INTEGER,functionDescription TEXT);INSERT INTO tblCompanyPersonnel VALUES(7,3,'Manager');");
$s->execute([hash('sha256','<p>English source</p>'),'nl','en','<p>English source</p>','<p>Nederlands</p>','html','ready','google-nmt']);
$_SERVER['HTTP_X_AETHER_CONTENT_LANGUAGE']='nl';$_SERVER['SCRIPT_NAME']='/aether/aetherapp/api/characters/saveCharacterSection.php';
$rich=aetherTranslationCached($pdo,'<p>English source</p>','nl');
$input=aetherCaptureTranslationSave($pdo,['idCharacter'=>7,'section'=>'knowledge','content'=>'<p>Verbeterd</p><script>alert(1)</script>','_aetherTranslationEdits'=>[['path'=>['content'],'sourceHash'=>$rich['sourceHash'],'version'=>aetherTranslationVersion($rich)]]]);
inlineCheck($input['content']==='<p>English source</p>','Source HTML remains the domain value.');aetherSavedTranslationChanges($pdo,['success'=>true]);
inlineCheck(aetherTranslationCached($pdo,'<p>English source</p>','nl')['translatedText']==='<p>Verbeterd</p>','Manual rich text uses the existing sanitizer.');
$s->execute([hash('sha256','Manager'),'nl','en','Manager','Beheerder','plain','ready','google-nmt']);
$staff=aetherTranslationCached($pdo,'Manager','nl');$_SERVER['SCRIPT_NAME']='/aether/aetherapp/api/companies/saveCompanyPersonnel.php';
$input=aetherCaptureTranslationSave($pdo,['idCompany'=>3,'personnel'=>[['idCharacter'=>7,'functionDescription'=>'Bedrijfsleider']],'_aetherTranslationEdits'=>[['path'=>['personnel',0,'functionDescription'],'sourceHash'=>$staff['sourceHash'],'version'=>aetherTranslationVersion($staff)]]]);
inlineCheck($input['personnel'][0]['functionDescription']==='Manager','Nested personnel edit preserves the correct source record.');aetherSavedTranslationChanges($pdo,[]);
inlineCheck(aetherTranslationCached($pdo,'Manager','nl')['translatedText']==='Bedrijfsleider','Nested translation correction saved.');
echo "PASS: Cut!/Snee! correction preserves original, identity refresh, manual provider, zero Google jobs, idempotency, version/source conflicts, permissions, rich text and nested fields.\n";
