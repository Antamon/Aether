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

$target=$base.'/aetherapp/config.local.php';
if(hash_file('sha256',$target)!=='d22e0692ef8932f299b7315f15b10027cf890903416c931837932c3661f54063')throw new RuntimeException('Configuration changed since inspection; stopped.');
$v=savedAssignments(file_get_contents($base.'/economie/php/dbconnect.php'));
$dsn='mysql:host='.$v['servername'].';dbname='.$v['dbname'].';charset=utf8mb4';
$pdo=new PDO($dsn,$v['username'],$v['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$pdo->query("SELECT COUNT(*) FROM tblResearchStep WHERE content LIKE '%duur satijn%'")->fetchColumn()!==1||(int)$pdo->query("SELECT COUNT(*) FROM tblResearchStep WHERE content LIKE '%jurk van de bruid%'")->fetchColumn()!==1)throw new RuntimeException('Expected live research feedback missing; stopped.');
$original=file_get_contents($target);$config=require $target;
$config['database']=['dsn'=>$dsn,'user'=>$v['username'],'password'=>$v['password']];
$contents="<?php\ndeclare(strict_types=1);\nreturn ".var_export($config,true).";\n";
$private=getcwd().'/.aether-config-recovery-20261117';
if(!is_dir($private)&&!mkdir($private,0700))throw new RuntimeException('Could not create private recovery directory.');
$backup=$private.'/aetherapp-config-before.php';
if(!is_file($backup)){file_put_contents($backup,$original,LOCK_EX);chmod($backup,0600);}
$stage=$private.'/aetherapp-config-restored.php';file_put_contents($stage,$contents,LOCK_EX);chmod($stage,fileperms($target)&0777);
require_once $base.'/aetherapp/api/shared/config.php';require_once $base.'/aetherapp/api/shared/database.php';
$validated=aetherLoadConfiguration($stage,[]);$test=aetherCreateDatabaseConnection($validated);
if((int)$test->query('SELECT 1')->fetchColumn()!==1)throw new RuntimeException('Staged connection failed.');
if(!rename($stage,$target))throw new RuntimeException('Could not publish restored configuration.');
if(hash_file('sha256',$target)!==hash('sha256',$contents))throw new RuntimeException('Published configuration differs.');
if(function_exists('opcache_invalidate'))opcache_invalidate($target,true);
echo json_encode(['productionConfigRestored'=>true,'privateBackupCreated'=>true,'databaseVerified'=>true,'databaseRowsChanged'=>false]).PHP_EOL;