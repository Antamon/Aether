<?php
declare(strict_types=1);
function savedAssignments(string $source):array{
    $tokens=token_get_all($source);$values=[];
    for($i=0;$i<count($tokens);$i++){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_VARIABLE)continue;
        $name=substr($tokens[$i][1],1);if(!in_array($name,['servername','username','password','dbname'],true)||isset($values[$name]))continue;
        $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(($tokens[$j]??null)!=='=')continue;$j++;
        while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(!is_array($tokens[$j]??null)||$tokens[$j][0]!==T_CONSTANT_ENCAPSED_STRING)continue;
        $literal=$tokens[$j][1];$values[$name]=$literal[0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],substr($literal,1,-1)):stripcslashes(substr($literal,1,-1));
    }return $values;
}
$prod=require $base.'/aetherapp/config.local.php';$expected=$prod['database']['name'];
foreach(['/economie/php/dbconnect.php','/functions.php']as$file){
    $v=savedAssignments(file_get_contents($base.$file));$out=['source'=>$file,'complete'=>count($v)===4,'sameDatabase'=>($v['dbname']??null)===$expected];
    if(count($v)===4){
        try{$pdo=new PDO('mysql:host='.$v['servername'].';dbname='.$v['dbname'].';charset=utf8mb4',$v['username'],$v['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);$out['connected']=true;
            $out['hasAppUsers']=(bool)$pdo->query("SHOW TABLES LIKE 'tblUser'")->fetchColumn();$out['hasResearch']=(bool)$pdo->query("SHOW TABLES LIKE 'tblResearchDiscovery'")->fetchColumn();
        }catch(Throwable $e){$out['errorCode']=$e->getCode();}
    }echo json_encode($out).PHP_EOL;
}
