<?php
declare(strict_types=1);
if (($argv[1]??'')==='--worker') {
    $scenario=$argv[2];$fixture=$argv[3];
    $_SERVER['REQUEST_METHOD']=$scenario==='participant_status'?'GET':'POST';
    if ($scenario==='participant_status') $_GET=['op'=>'status'];
    if (!str_contains($scenario,'csrf')) $_SERVER['HTTP_X_CSRF_TOKEN']='test-csrf';
    session_start();$_SESSION=['user'=>['id'=>1],'aetherCsrfToken'=>'test-csrf','aetherTranslationSecret'=>str_repeat('b',64)];
    $GLOBALS['testRole']=str_starts_with($scenario,'participant')?'participant':'director';
    register_shutdown_function(static function():void{fwrite(STDERR,'HTTP_STATUS:'.(http_response_code()?:200));});
    require $fixture.'/api/translations/http.php';exit;
}
$root=dirname(__DIR__);$fixture=sys_get_temp_dir().'/aether-translation-http-'.bin2hex(random_bytes(5));
foreach(['api/shared','api/auth','api/translations','vendor']as$folder)mkdir($fixture.'/'.$folder,0777,true);
foreach(['auth/accessControl.php','translations/http.php']as$file)copy($root.'/api/'.$file,$fixture.'/api/'.$file);
foreach(glob($root.'/api/shared/*.php')as$file)copy($file,$fixture.'/api/shared/'.basename($file));
// Real translation/auth/request handlers, with only external bootstrap and replay storage replaced.
file_put_contents($fixture.'/vendor/autoload.php','<?php require '.var_export($root.'/vendor/autoload.php',true).';');
file_put_contents($fixture.'/sessionUserBootstrap.php','<?php function aetherLoadWordPressIfAvailable():bool{return false;} function aetherHydrateSessionUserFromWordPress():bool{return false;}');
file_put_contents($fixture.'/api/shared/idempotency.php', '<?php class AetherIdempotencyException extends RuntimeException {function getHttpStatus(){return 409;}} function aetherRequireIdempotencyKey(){return "test-idempotency-key";} function aetherRunIdempotentMutation($p,$u,$o,$k,$i,$f,$r){$p->beginTransaction();try{$v=$f();$p->commit();return $v;}catch(Throwable $e){$p->rollBack();throw $e;}}');
file_put_contents($fixture.'/db.php', <<<'PHP'
<?php
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE tblUser(id INTEGER,username TEXT,firstName TEXT,lastName TEXT,role TEXT);");
$s=$pdo->prepare('INSERT INTO tblUser VALUES(1,?,?,?,?)');$s->execute(['test','Test','User',$GLOBALS['testRole']]);
$pdo->exec('CREATE TABLE tblContentTranslation(sourceHash TEXT,targetLanguage TEXT,sourceLanguage TEXT,sourceText TEXT,translatedText TEXT,format TEXT,status TEXT,provider TEXT,PRIMARY KEY(sourceHash,targetLanguage))');
PHP);
try {
    $input=['op'=>'read','source'=>'Known','target'=>'en','format'=>'plain'];
    $expires=time()+3600;$input['grant']=$expires.'.'.hash_hmac('sha256','1|'.hash('sha256','Known').'|plain|'.$expires,str_repeat('b',64));
    foreach(['participant_status'=>403,'participant_read'=>403,'participant_save'=>403,'director_csrf'=>403,'director_forged'=>403,'director_read'=>200,'director_save'=>200]as$scenario=>$expected){
        $body=$input;if(str_contains($scenario,'save'))$body+=['text'=>'Translated','sourceLanguage'=>'nl','version'=>hash('sha256','null')];
        if(str_contains($scenario,'save'))$body['op']='save';
        if($scenario==='director_forged')$body['source']='A secret not in the authorized response';
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$scenario,$fixture],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fwrite($pipes[0],json_encode($body));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($process);
        if(!preg_match('/HTTP_STATUS:(\d+)/',$err,$m)||(int)$m[1]!==$expected)throw new RuntimeException($scenario.': '.$out.' '.$err);
        if(!is_array(json_decode($out,true)))throw new RuntimeException('Invalid JSON: '.$scenario.' '.$out);
        if(str_contains($out,'test-csrf')||str_contains($out,str_repeat('b',64)))throw new RuntimeException('Secret leaked.');
    }
    echo "PASS: real HTTP handlers enforce participant/director roles, CSRF, authorized-source grants, manual read/save and secret protection\n";
}finally{
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());rmdir($fixture);
}
