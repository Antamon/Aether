<?php
declare(strict_types=1);
require_once __DIR__.'/../api/shared/translationService.php';
require_once __DIR__.'/../api/shared/contentLanguage.php';
function googleCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function translationFixture():PDO {
    $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $p->exec("CREATE TABLE tblContentTranslation(sourceHash TEXT,targetLanguage TEXT,sourceLanguage TEXT,sourceText TEXT,translatedText TEXT,format TEXT,status TEXT,provider TEXT,PRIMARY KEY(sourceHash,targetLanguage));
        CREATE TABLE tblTranslationUsage(budgetKey TEXT,monthKey TEXT,sentCharacters INTEGER DEFAULT 0,successfulCharacters INTEGER DEFAULT 0,PRIMARY KEY(budgetKey,monthKey));
        CREATE TABLE tblTranslationRequest(budgetKey TEXT,sourceHash TEXT,targetLanguage TEXT,state TEXT DEFAULT 'idle',attemptToken TEXT DEFAULT '',retryAfter TEXT,sourceLanguage TEXT DEFAULT '',translatedText TEXT,format TEXT DEFAULT 'plain',PRIMARY KEY(budgetKey,sourceHash,targetLanguage));");return $p;
}
$settings=aetherTranslationSettings(['enabled'=>true,'googleApiKey'=>'test-key-not-real','monthlyCharacterLimit'=>10,'maxAutomaticCharacters'=>8]);
googleCheck(aetherTranslationSettings([])['maxAutomaticCharacters']===2000,'The agreed automatic limit is 2,000 characters.');
googleCheck(aetherGoogleTranslationResponse('{"data":{"translations":[{"translatedText":"Tom &amp; Jerry","detectedSourceLanguage":"nl"}]}}','en','plain')['text']==='Tom & Jerry','Decode provider text entities without exposing HTML.');
$boundary=translationFixture();$boundarySettings=aetherTranslationSettings(['enabled'=>true,'googleApiKey'=>'fake-test-key']);$boundaryCalls=0;
$boundaryTransport=function()use(&$boundaryCalls):array{$boundaryCalls++;return ['text'=>'Translated','sourceLanguage'=>'nl'];};
googleCheck(aetherTranslationAutomatic($boundary,str_repeat('a',2000),'en','plain',$boundarySettings,$boundaryTransport)['state']==='ready','Exactly 2,000 characters may be translated.');
googleCheck(aetherTranslationAutomatic($boundary,str_repeat('b',2001),'en','plain',$boundarySettings,$boundaryTransport)['state']==='manual_required'&&$boundaryCalls===1,'2,001 characters must never reach Google.');
$now=new DateTimeImmutable('2026-11-19 12:00:00+00:00');$p=translationFixture();$calls=0;
$transport=function(string $text,string $target,string $format,array $settings)use(&$calls,$p,$now):array {
    $calls++;googleCheck(!$p->inTransaction(),'No transaction or lock may be held during network I/O.');
    $nested=aetherTranslationAutomatic($p,$text,$target,$format,$settings,fn()=>throw new RuntimeException('Duplicate network request'),$now);
    googleCheck($nested['state']==='pending','Concurrent duplicate must observe the in-flight claim.');
    return ['text'=>'Hello','sourceLanguage'=>'nl'];
};
$result=aetherTranslationAutomatic($p,'Hallo','en','plain',$settings,$transport,$now);
googleCheck($result['state']==='ready'&&$calls===1,'Initial translation.');
googleCheck(aetherTranslationAutomatic($p,'Hallo','en','plain',$settings,$transport,$now)['state']==='ready'&&$calls===1,'Ready translations are reused.');
$p->exec('DELETE FROM tblContentTranslation');
googleCheck(aetherTranslationAutomatic($p,'Hallo','en','plain',$settings,$transport,$now)['state']==='ready'&&$calls===1,'Shared request cache is reusable after a local cache miss.');
googleCheck(aetherTranslationAutomatic($p,str_repeat('x',9),'en','plain',$settings,$transport,$now)['state']==='manual_required'&&$calls===1,'Long text never reaches Google.');
googleCheck(aetherTranslationAutomatic($p,'Wereld','en','plain',$settings,$transport,$now)['state']==='quota_exhausted'&&$calls===1,'Reject whole requests before they cross the monthly cap.');
googleCheck(aetherTranslationStatus($p,$settings,$now)['used']===5,'Only the reserved input is counted.');
googleCheck(aetherTranslationStatus($p,$settings,$now->modify('+1 month'))['used']===0,'New calendar month has a separate budget.');

$unicode=translationFixture();$unicodeSettings=array_replace($settings,['monthlyCharacterLimit'=>2]);
aetherTranslationAutomatic($unicode,'é😀','en','plain',$unicodeSettings,fn()=>['text'=>'two','sourceLanguage'=>'nl'],$now);
googleCheck(aetherTranslationStatus($unicode,$unicodeSettings,$now)['used']===2&&aetherTranslationStatus($unicode,$unicodeSettings,$now)['exhausted'],'Count Unicode code points, not bytes.');
$failed=translationFixture();$failCalls=0;$fail=function()use(&$failCalls):array{$failCalls++;throw new RuntimeException('Private provider error must not leak.');};
googleCheck(aetherTranslationAutomatic($failed,'fout','en','plain',$settings,$fail,$now)['state']==='unavailable','Provider failure leaves source usable.');
googleCheck(aetherTranslationAutomatic($failed,'fout','en','plain',$settings,$fail,$now)['state']==='pending'&&$failCalls===1,'Failures have a cooldown.');
googleCheck(aetherTranslationStatus($failed,$settings,$now)['used']===4&&aetherTranslationStatus($failed,$settings,$now)['successful']===0,'Uncertain/failed attempts remain counted conservatively.');
aetherTranslationAutomatic($failed,'fout','en','plain',$settings,$fail,$now->modify('+6 minutes'));
googleCheck(aetherTranslationStatus($failed,$settings,$now)['used']===8&&$failCalls===2,'A retry reserves new capacity.');

$_SESSION=['aetherTranslationSecret'=>str_repeat('a',64)];$admin=['id'=>7,'role'=>'administrator'];$director=['id'=>8,'role'=>'director'];
$grant=aetherTranslationGrant($admin,'verborgen tekst','plain');
googleCheck(aetherTranslationGrantValid($admin,'verborgen tekst','plain',$grant),'Authorized source grant.');
googleCheck(!aetherTranslationGrantValid($director,'verborgen tekst','plain',$grant)&&!aetherTranslationGrantValid($admin,'andere tekst','plain',$grant)&&!aetherTranslationGrantValid($admin,'verborgen tekst','html',$grant),'User, source and format cannot be substituted.');
googleCheck(!aetherTranslationGrantValid($admin,'verborgen tekst','plain',aetherTranslationGrant($admin,'verborgen tekst','plain',time()-1)),'Expired grants are rejected.');
$version=aetherTranslationVersion(aetherTranslationCached($p,'Hallo','en'));
$p->beginTransaction();$saved=aetherTranslationSaveManual($p,'Hallo','en','plain','Welcome','nl',$version);$p->commit();
googleCheck($saved['text']==='Welcome'&&aetherTranslationCached($p,'Hallo','en')['provider']==='manual','Manual correction replaces a cached machine translation.');
try{$p->beginTransaction();aetherTranslationSaveManual($p,'Hallo','en','plain','Stale','nl',$version);throw new RuntimeException('Stale edit accepted');}catch(DomainException $e){$p->rollBack();}
googleCheck(aetherTranslationAutomatic($p,'Hallo','en','plain',$settings,$transport,$now)['text']==='Welcome'&&$calls===1,'Automatic work never overwrites a manual translation.');
$p->beginTransaction();$safe=aetherTranslationSaveManual($p,'<p>Bron</p>','en','html','<p>Safe</p><script>alert(1)</script>','nl',aetherTranslationVersion(null));$p->commit();
googleCheck(!str_contains($safe['text'],'script')&&str_contains($safe['text'],'Safe'),'Manual HTML uses existing sanitization.');
googleCheck(aetherTranslationGrantValid($admin,'verborgen tekst','plain',$grant),'Reading/saving other sources does not change a grant.');
echo "PASS: monthly cap/rollover, code points, in-flight deduplication, caching, failure cooldown/accounting, long-text exclusion, grants, manual corrections, stale writes and HTML safety\n";
