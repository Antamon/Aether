<?php
declare(strict_types=1);
$dsn=getenv('AETHER_TEST_MYSQL_DSN')?:'';$user=getenv('AETHER_TEST_MYSQL_USER')?:'';$pass=getenv('AETHER_TEST_MYSQL_PASSWORD')?:'';
if($dsn===''||getenv('AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS')!=='YES'){echo "SKIP: echte MariaDB-concurrencytest vereist expliciete testdatabaseconfiguratie.\n";exit(0);}
if(($argv[1]??'')==='worker'){
 [,,$mode,$kind,$dsn64,$user64,$pass64,$table,$ready,$release]=$argv;
 $pdo=new PDO(base64_decode($dsn64),base64_decode($user64),base64_decode($pass64),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $column=$kind==='payment'?'balance':'allocated';$pdo->beginTransaction();
 $value=(int)$pdo->query("SELECT ".$column." FROM ".$table." WHERE id=1 FOR UPDATE")->fetchColumn();
 if($mode==='first'){file_put_contents($ready,'1');while(!is_file($release))usleep(10000);}
 $ok=$kind==='payment'?$value>=8000:$value<100;
 if($ok){$change=$kind==='payment'?'-8000':'+1';$pdo->exec("UPDATE ".$table." SET ".$column."=".$column.$change." WHERE id=1");}
 $pdo->commit();echo $ok?'accepted':'rejected';exit;
}
$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if(!preg_match('/(?:test|dev|ci)/i',$database)){fwrite(STDERR,"REFUSED: geen herkenbare testdatabase.\n");exit(1);}
$suffix=bin2hex(random_bytes(5));$failures=[];
foreach(['payment'=>['balance',10000,2000],'share'=>['allocated',99,100]]as$kind=>$config){
 $table='tmp_aether_finance_'.$kind.'_'.$suffix;$column=$config[0];
 $pdo->exec("CREATE TABLE ".$table." (id INT PRIMARY KEY, ".$column." BIGINT NOT NULL) ENGINE=InnoDB");
 $pdo->exec("INSERT INTO ".$table." (id,".$column.") VALUES (1,".(int)$config[1].")");
 $ready=sys_get_temp_dir().'/aether-ready-'.$suffix.'-'.$kind;$release=sys_get_temp_dir().'/aether-release-'.$suffix.'-'.$kind;
 $args=static fn(string $mode):string=>implode(' ',array_map('escapeshellarg',[PHP_BINARY,__FILE__,'worker',$mode,$kind,base64_encode($dsn),base64_encode($user),base64_encode($pass),$table,$ready,$release]));
 $p1=proc_open($args('first'),[1=>['pipe','w'],2=>['pipe','w']],$pipes1);$deadline=microtime(true)+5;while(!is_file($ready)&&microtime(true)<$deadline)usleep(10000);
 $p2=proc_open($args('second'),[1=>['pipe','w'],2=>['pipe','w']],$pipes2);usleep(250000);file_put_contents($release,'go');
 $out1=stream_get_contents($pipes1[1]);$out2=stream_get_contents($pipes2[1]);foreach($pipes1 as$p)fclose($p);foreach($pipes2 as$p)fclose($p);proc_close($p1);proc_close($p2);
 $value=(int)$pdo->query("SELECT ".$column." FROM ".$table." WHERE id=1")->fetchColumn();
 if($out1!=='accepted'||$out2!=='rejected'||$value!==(int)$config[2])$failures[]=$kind." overlap faalde.";
 $pdo->exec("DROP TABLE ".$table);@unlink($ready);@unlink($release);
}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "MariaDB finance concurrency tests passed.\n";
