<?php
declare(strict_types=1);
$base=$argv[1];
$source=file_get_contents($base.'/aetherapp-dev/db.legacy.local.php');
$tokens=token_get_all($source);$values=[];
for($i=0;$i<count($tokens);$i++){
    if(!is_array($tokens[$i])||$tokens[$i][0]!==T_VARIABLE)continue;
    $name=substr($tokens[$i][1],1);if(!in_array($name,['dsn','dbUser','dbPass','host','dbname','user','pass'],true))continue;
    $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
    if(($tokens[$j]??null)!=='=')continue;$j++;
    while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
    if(!is_array($tokens[$j]??null)||$tokens[$j][0]!==T_CONSTANT_ENCAPSED_STRING)continue;
    $literal=$tokens[$j][1];$values[$name]=$literal[0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],substr($literal,1,-1)):stripcslashes(substr($literal,1,-1));
}
$candidates=[];
if(isset($values['dbUser'],$values['dbPass'],$values['dsn'])&&str_starts_with($values['dsn'],'mysql:'))$candidates['legacy_main']=[$values['dsn'],$values['dbUser'],$values['dbPass']];
if(isset($values['host'],$values['dbname'],$values['user'],$values['pass']))$candidates['legacy_fallback']=['mysql:host='.$values['host'].';dbname='.$values['dbname'].';charset=utf8mb4',$values['user'],$values['pass']];
foreach($candidates as $label=>[$dsn,$user,$pass]){
    $out=['candidate'=>$label];try{$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);$out['connected']=true;$out['hasAppUsers']=(bool)$pdo->query('SELECT COUNT(*) FROM tblUser')->fetchColumn();$out['hasResearchTable']=(bool)$pdo->query("SHOW TABLES LIKE 'tblResearchDiscovery'")->fetchColumn();}
    catch(Throwable $e){$out['errorCode']=$e->getCode();$out['errorClass']=get_class($e);}
    echo json_encode($out).PHP_EOL;
}
