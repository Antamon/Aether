<?php
declare(strict_types=1);

if (defined('AETHER_LIFECYCLE_PORTRAIT_TEST_BOOTSTRAP')) {
    final class LifecyclePortraitStatement extends PDOStatement
    {
        private array $params = [];
        private int $rowCountValue = 0;
        public function __construct(private LifecyclePortraitPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }
        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $sql = $this->sql();
            $this->rowCountValue = 0;
            if (str_starts_with($sql, 'DELETE') || str_starts_with($sql, 'UPDATE')) {
                if ($this->pdo->scenario === 'database_mid_error' && str_contains($sql, 'DELETE cps FROM')) {
                    throw new PDOException('SQLSTATE[HY000]: secret_company_personnel_path');
                }
                $this->pdo->write($sql, $this->params);
                $this->rowCountValue = 1;
                if (str_starts_with($sql, 'DELETE FROM tblCharacter WHERE')) {
                    unset($this->pdo->characters[(int) $this->params['idCharacter']]);
                }
                if (str_starts_with($sql, 'UPDATE tblCharacter SET securitiesManagerType')) {
                    foreach ($this->pdo->characters as &$character) {
                        if (($character['securitiesManagerCharacterId'] ?? null) === (int) $this->params['idCharacter']) {
                            $character['securitiesManagerType'] = 'self';
                            $character['securitiesManagerCharacterId'] = null;
                        }
                    }
                    unset($character);
                }
            }
            return true;
        }
        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser')) return $this->pdo->users[(int) ($this->params['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM information_schema.TABLES')) return ['1' => 1];
            if (str_contains($sql, 'FROM tblCharacter')) return $this->pdo->characters[(int) ($this->params['id'] ?? 0)] ?? false;
            return false;
        }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
        public function fetchColumn(int $column = 0): mixed { $row=$this->fetch(); return $row===false?false:array_values($row)[$column]??false; }
        public function rowCount(): int { return $this->rowCountValue; }
    }

    final class LifecyclePortraitPdo extends PDO
    {
        public array $users = [
            10=>['id'=>10,'username'=>'participant','firstName'=>'Part','lastName'=>'Icipant','role'=>'participant'],
            20=>['id'=>20,'username'=>'director','firstName'=>'Di','lastName'=>'Rector','role'=>'director'],
            30=>['id'=>30,'username'=>'administrator','firstName'=>'Ad','lastName'=>'Min','role'=>'administrator'],
        ];
        public array $characters = [
            1=>['id'=>1,'idUser'=>10,'type'=>'player','state'=>'active','class'=>'upper class','firstName'=>'Eigen','lastName'=>'Speler'],
            2=>['id'=>2,'idUser'=>99,'type'=>'player','state'=>'draft','class'=>'middle class','firstName'=>'Andere','lastName'=>'Speler','securitiesManagerType'=>'third','securitiesManagerCharacterId'=>1],
            3=>['id'=>3,'idUser'=>10,'type'=>'extra','state'=>'active','class'=>'upper class','firstName'=>'Eigen','lastName'=>'Extra'],
        ];
        public int $writeAttempts=0, $committedWrites=0, $commits=0, $rollbacks=0;
        public array $deleteOrder=[], $writeParams=[];
        private ?array $snapshot=null;
        private int $stagedWrites=0;
        public function __construct(public string $scenario) {}
        public function prepare(string $query,array $options=[]):PDOStatement|false{return new LifecyclePortraitStatement($this,$query);}
        public function beginTransaction():bool{$this->snapshot=$this->characters;$this->stagedWrites=0;return true;}
        public function commit():bool{$this->committedWrites+=$this->stagedWrites;$this->stagedWrites=0;$this->snapshot=null;$this->commits++;return true;}
        public function rollBack():bool{if($this->snapshot!==null)$this->characters=$this->snapshot;$this->stagedWrites=0;$this->snapshot=null;$this->rollbacks++;return true;}
        public function inTransaction():bool{return $this->snapshot!==null;}
        public function write(string $sql,array $params):void{$this->writeAttempts++;$this->deleteOrder[]=$sql;$this->writeParams[]=$params;if($this->inTransaction())$this->stagedWrites++;else $this->committedWrites++;}
    }

    function getPDO(): PDO { global $pdo; return $pdo; }
    function dbOne(PDO $pdo,string $sql,array $params=[]):?array{$s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
    function dbAll(PDO $pdo,string $sql,array $params=[]):array{$s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}

    $scenario=(string)($argv[1]??'success');
    $pdo=new LifecyclePortraitPdo($scenario);
    $uid=match($scenario){'unauthenticated'=>999,'director'=>20,'administrator'=>30,default=>10};
    session_start();
    $_SESSION=['user'=>['id'=>$uid,'role'=>$scenario==='forged_role'?'administrator':'participant'],'aetherCsrfToken'=>'expected-token'];
    if($scenario!=='missing_csrf')$_SERVER['HTTP_X_CSRF_TOKEN']=$scenario==='invalid_csrf'?'bad-token':'expected-token';

    $route=basename((string)($_SERVER['SCRIPT_FILENAME']??''));
    $createdUpload=null;
    if($route==='uploadCharacterPortrait.php'){
        $_POST=['id'=>in_array($scenario,['foreign_character','forged_role'],true)?'2':($scenario==='own_extra'?'3':'1')];
        if($scenario==='unknown_character')$_POST=['id'=>'999'];
        if($scenario==='unexpected_field')$_POST['path']='../../outside.png';
        if($scenario==='missing_upload'){
            $_FILES=[];
        }else{
            $base=sys_get_temp_dir().'/portrait-upload-'.bin2hex(random_bytes(5));
            $createdUpload=$scenario==='extension_mismatch'?$base.'.php.jpg':$base.'.png';
            $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLzWQAAAABJRU5ErkJggg==',true);
            if($scenario==='disallowed_mime')file_put_contents($createdUpload,'plain text');
            elseif($scenario==='corrupt_image')file_put_contents($createdUpload,"\x89PNG\r\ncorrupt");
            elseif($scenario==='too_large'){ $h=fopen($createdUpload,'wb');ftruncate($h,10*1024*1024+1);fclose($h); }
            else file_put_contents($createdUpload,$png);
            $_FILES=['portrait'=>['name'=>$scenario==='extension_mismatch'?'shell.php.jpg':'portrait.png','type'=>$scenario==='disallowed_mime'?'image/png':'text/x-php','tmp_name'=>$createdUpload,'error'=>$scenario==='invalid_upload'?UPLOAD_ERR_PARTIAL:UPLOAD_ERR_OK,'size'=>@filesize($createdUpload)?:0]];
            if($scenario==='unexpected_file')$_FILES['payload']=$_FILES['portrait'];
        }
    }

    $GLOBALS['aetherPortraitUploadVerifier']=static fn(string $path):bool=>is_file($path);
    $GLOBALS['aetherPortraitImageProcessor']=static function(string $source,string $target)use($scenario):void{
        if($scenario==='processor_error')throw new RuntimeException('secret image path C:/server/private');
        if(!copy($source,$target))throw new RuntimeException('copy failed');
    };
    if(in_array($scenario,['portrait_rename_error','lifecycle_rename_error'],true)){
        $GLOBALS['aetherPortraitRename']=static function(string $source,string $target):bool{
            if(str_contains(str_replace('\\','/',$target),'/.quarantine/'))return false;
            return rename($source,$target);
        };
    }
    if($scenario==='cleanup_error'){
        $GLOBALS['aetherPortraitUnlink']=static function(string $path):bool{
            if(str_contains(str_replace('\\','/',$path),'/.quarantine/'))return false;
            return unlink($path);
        };
    }

    register_shutdown_function(static function()use($pdo,$createdUpload):void{
        if($createdUpload&&is_file($createdUpload))@unlink($createdUpload);
        $root=dirname((string) $_SERVER['SCRIPT_FILENAME'],3);$portrait=$root.'/img/portret';$files=[];
        if(is_dir($portrait))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($portrait,FilesystemIterator::SKIP_DOTS)) as $item){if($item->isFile())$files[]=str_replace('\\','/',substr($item->getPathname(),strlen($portrait)+1));}
        sort($files);$status=http_response_code();
        fwrite(STDERR,'__LIFECYCLE_STATE__:'.json_encode(['status'=>$status===false?200:$status,'writes'=>$pdo->committedWrites,'attempts'=>$pdo->writeAttempts,'commits'=>$pdo->commits,'rollbacks'=>$pdo->rollbacks,'characters'=>$pdo->characters,'deleteOrder'=>$pdo->deleteOrder,'writeParams'=>$pdo->writeParams,'files'=>$files])."\n");
    });
    return;
}

$root=dirname(__DIR__);$fixture=sys_get_temp_dir().'/aether-lifecycle-portrait-'.bin2hex(random_bytes(5));
function lifecycleCopyTree(string $from,string $to):void{if(!is_dir($to))mkdir($to,0777,true);foreach(new DirectoryIterator($from)as$i){if($i->isDot())continue;$dest=$to.'/'.$i->getFilename();$i->isDir()?lifecycleCopyTree($i->getPathname(),$dest):copy($i->getPathname(),$dest);}}
function lifecycleRemoveTree(string $path):void{if(!is_dir($path))return;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$i){$i->isDir()?rmdir($i->getPathname()):unlink($i->getPathname());}rmdir($path);}
lifecycleCopyTree($root.'/api',$fixture.'/api');copy($root.'/sessionUserBootstrap.php',$fixture.'/sessionUserBootstrap.php');mkdir($fixture.'/img/portret/.quarantine',0777,true);copy($root.'/img/portret/.quarantine/.htaccess',$fixture.'/img/portret/.quarantine/.htaccess');
file_put_contents($fixture.'/db.php',"<?php\ndefine('AETHER_LIFECYCLE_PORTRAIT_TEST_BOOTSTRAP',true);require ".var_export(__FILE__,true).";\n");

$failures=[];
function lifecycleCheck(bool $condition,string $message):void{global$failures;if(!$condition)$failures[]=$message;}
function resetPortraitFixture(string $fixture,bool $withOwn=true):void{$dir=$fixture.'/img/portret';foreach(new DirectoryIterator($dir)as$i){if($i->isDot()||$i->getFilename()==='.quarantine')continue;if($i->isFile()||$i->isLink())unlink($i->getPathname());}$q=$dir.'/.quarantine';foreach(new DirectoryIterator($q)as$i){if($i->isDot()||$i->getFilename()==='.htaccess')continue;if($i->isFile())unlink($i->getPathname());}if($withOwn)file_put_contents($dir.'/1.png','old portrait');file_put_contents($dir.'/2.png','other portrait');file_put_contents($dir.'/999.png','unrelated portrait');file_put_contents($dir.'/default.png','default image');file_put_contents($dir.'/1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png.php','dangerous unrelated');}
function runLifecycleRoute(string $fixture,string $route,string $scenario,array|string $request=[],bool $withOwn=true):array{resetPortraitFixture($fixture,$withOwn);$p=proc_open([PHP_BINARY,$fixture.'/api/characters/'.$route,$scenario],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('route start failed');fwrite($pipes[0],is_string($request)?$request:json_encode($request));fclose($pipes[0]);$body=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($p);preg_match('/__LIFECYCLE_STATE__:(\{.*\})/',$err,$m);return['body'=>$body,'json'=>json_decode($body,true),'state'=>isset($m[1])?json_decode($m[1],true):null,'exit'=>$exit,'err'=>$err];}
function expectLifecycle(string $fixture,string $route,string $scenario,array|string $request,int $status,int $writes=0,bool $withOwn=true):array{$r=runLifecycleRoute($fixture,$route,$scenario,$request,$withOwn);lifecycleCheck(($r['state']['status']??0)===$status,"$route/$scenario status; body={$r['body']} stderr={$r['err']}");lifecycleCheck(($r['state']['writes']??-1)===$writes,"$route/$scenario writes");lifecycleCheck($r['exit']===0,"$route/$scenario exit");if($status>=400&&$withOwn){foreach(['1.png','2.png','999.png','default.png','1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png.php']as$file){lifecycleCheck(in_array($file,$r['state']['files']??[],true),"$route/$scenario preserves $file on failure");}}return$r;}

try{
    $r=expectLifecycle($fixture,'uploadCharacterPortrait.php','success',[],200);lifecycleCheck(($r['json']['status']??'')==='ok','upload success status');lifecycleCheck(preg_match('#^img/portret/1-[a-f0-9]{32}\.png\?v=\d+$#',(string)($r['json']['portraitUrl']??''))===1,'random portrait URL');lifecycleCheck(!in_array('1.png',$r['state']['files'],true),'old portrait removed after replacement');lifecycleCheck(count(array_filter($r['state']['files'],static fn(string$f):bool=>preg_match('/^1-[a-f0-9]{32}\.png$/',$f)===1))===1,'one active random portrait');
    $r=expectLifecycle($fixture,'uploadCharacterPortrait.php','extension_mismatch',[],200);lifecycleCheck(($r['json']['status']??'')==='ok','actual PNG accepted despite supplied extension/MIME');
    foreach(['disallowed_mime','corrupt_image']as$s){$r=expectLifecycle($fixture,'uploadCharacterPortrait.php',$s,[],400);lifecycleCheck(in_array('1.png',$r['state']['files'],true),"$s keeps old portrait");}
    expectLifecycle($fixture,'uploadCharacterPortrait.php','too_large',[],422);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','missing_upload',[],400);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','invalid_upload',[],400);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','foreign_character',[],403);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','forged_role',[],403);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','own_extra',[],200);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','director',[],200);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','administrator',[],200);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','unauthenticated',[],401);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','missing_csrf',[],403);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','invalid_csrf',[],403);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','unexpected_field',[],422);
    expectLifecycle($fixture,'uploadCharacterPortrait.php','unexpected_file',[],422);
    foreach(['processor_error','portrait_rename_error']as$s){$r=expectLifecycle($fixture,'uploadCharacterPortrait.php',$s,[],500);lifecycleCheck(in_array('1.png',$r['state']['files'],true),"$s retains old portrait");lifecycleCheck(!str_contains($r['body'],'secret')&&!str_contains($r['body'],'C:/'),"$s generic 500");lifecycleCheck(count(array_filter($r['state']['files'],static fn(string$f):bool=>str_starts_with($f,'.incoming-')))===0,"$s cleans incoming file");}

    $r=expectLifecycle($fixture,'deleteCharacterPortrait.php','success',['id'=>1],200);lifecycleCheck($r['json']===['status'=>'ok'],'portrait delete response');lifecycleCheck(!in_array('1.png',$r['state']['files'],true)&&in_array('2.png',$r['state']['files'],true)&&in_array('default.png',$r['state']['files'],true),'portrait delete scope');lifecycleCheck(in_array('1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png.php',$r['state']['files'],true),'executable-looking unrelated file preserved');
    expectLifecycle($fixture,'deleteCharacterPortrait.php','success',['id'=>1],200,0,false);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','foreign_character',['id'=>2],403);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','forged_role',['id'=>2],403);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','unknown_character',['id'=>999],404);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','director',['id'=>2],200);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','administrator',['id'=>2],200);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','unauthenticated',['id'=>1],401);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','missing_csrf',['id'=>1],403);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','invalid_csrf',['id'=>1],403);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','success','{}',422);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','success',['id'=>0],422);
    expectLifecycle($fixture,'deleteCharacterPortrait.php','success',['id'=>1,'path'=>'../../default.png'],422);
    $r=expectLifecycle($fixture,'deleteCharacterPortrait.php','portrait_rename_error',['id'=>1],500);lifecycleCheck(in_array('1.png',$r['state']['files'],true),'failed staging restores portrait');lifecycleCheck(!str_contains($r['body'],'quarantaine'),'delete generic 500');
    $r=expectLifecycle($fixture,'deleteCharacterPortrait.php','cleanup_error',['id'=>1],200);lifecycleCheck(!in_array('1.png',$r['state']['files'],true)&&count(array_filter($r['state']['files'],static fn(string$f):bool=>str_starts_with($f,'.quarantine/')))>=2,'cleanup failure leaves only inaccessible quarantine data');

    $r=expectLifecycle($fixture,'deleteCharacter.php','success',['id'=>1],200,12);lifecycleCheck($r['json']===['success'=>true,'id'=>1,'name'=>'Eigen Speler'],'character delete response');lifecycleCheck(!isset($r['state']['characters']['1'])&&isset($r['state']['characters']['2']),'only selected character deleted');lifecycleCheck(($r['state']['characters']['2']['securitiesManagerType']??null)==='self'&&array_key_exists('securitiesManagerCharacterId',$r['state']['characters']['2'])&&$r['state']['characters']['2']['securitiesManagerCharacterId']===null,'live securities-manager reference reset');lifecycleCheck(!in_array('1.png',$r['state']['files'],true)&&in_array('2.png',$r['state']['files'],true)&&in_array('999.png',$r['state']['files'],true),'character portrait cleanup scope');lifecycleCheck(count($r['state']['deleteOrder'])===12,'all manual, reference and character mutations executed');
    $expectedMutations=['tblCompanySnapshotPayout','tblCharacterBankTransaction','tblCharacterSecuritiesTransaction','tblCharacterEconomySnapshot','tblCompanyPersonnelSkillSpecialisation','tblCompanyPersonnelSkill cps','tblCompanyPersonnel WHERE','tblLinkCharacterTraitCompany','tblLinkCharacterSkill','tblCharacterLanguage','UPDATE tblCharacter SET securitiesManagerType','DELETE FROM tblCharacter WHERE'];foreach($expectedMutations as$i=>$fragment){lifecycleCheck(str_contains((string)($r['state']['deleteOrder'][$i]??''),$fragment),"character delete mutation order $fragment");lifecycleCheck(in_array(1,array_values($r['state']['writeParams'][$i]??[]),true),"character delete prepared id $fragment");}
    expectLifecycle($fixture,'deleteCharacter.php','director',['id'=>2],200,12);
    expectLifecycle($fixture,'deleteCharacter.php','administrator',['id'=>2],200,12);
    expectLifecycle($fixture,'deleteCharacter.php','foreign_character',['id'=>2],403);
    expectLifecycle($fixture,'deleteCharacter.php','forged_role',['id'=>2],403);
    expectLifecycle($fixture,'deleteCharacter.php','unknown_character',['id'=>999],404);
    expectLifecycle($fixture,'deleteCharacter.php','success',['id'=>3],403);
    expectLifecycle($fixture,'deleteCharacter.php','unauthenticated',['id'=>1],401);
    expectLifecycle($fixture,'deleteCharacter.php','missing_csrf',['id'=>1],403);
    expectLifecycle($fixture,'deleteCharacter.php','invalid_csrf',['id'=>1],403);
    expectLifecycle($fixture,'deleteCharacter.php','success','{}',422);
    expectLifecycle($fixture,'deleteCharacter.php','success',['id'=>'nope'],422);
    expectLifecycle($fixture,'deleteCharacter.php','success',['id'=>1,'path'=>'../../default.png'],422);
    $r=expectLifecycle($fixture,'deleteCharacter.php','database_mid_error',['id'=>1],500);lifecycleCheck(($r['state']['rollbacks']??0)===1&&($r['state']['writes']??-1)===0&&isset($r['state']['characters']['1']),'database rollback restores character');lifecycleCheck(in_array('1.png',$r['state']['files'],true),'database rollback restores portrait');lifecycleCheck(!str_contains($r['body'],'SQLSTATE')&&!str_contains($r['body'],'secret_company'),'character generic 500');
    $r=expectLifecycle($fixture,'deleteCharacter.php','lifecycle_rename_error',['id'=>1],500);lifecycleCheck(($r['state']['attempts']??-1)===0&&in_array('1.png',$r['state']['files'],true),'filesystem staging failure precedes DB writes');
    $r=expectLifecycle($fixture,'deleteCharacter.php','cleanup_error',['id'=>1],200,12);lifecycleCheck(!isset($r['state']['characters']['1'])&&!in_array('1.png',$r['state']['files'],true),'post-commit cleanup failure keeps deletion consistent');

    foreach(['uploadCharacterPortrait.php','deleteCharacterPortrait.php','deleteCharacter.php']as$route){$source=file_get_contents($root.'/api/characters/'.$route);lifecycleCheck(substr_count((string)$source,"\n")<60,"$route thin endpoint");lifecycleCheck(!str_contains((string)$source,'session_start()'),"$route no duplicate session");lifecycleCheck(str_contains((string)$source,'aetherValidateInput('),"$route schema validation");}
    $front=file_get_contents($root.'/js/characterFunctions.js');$api=file_get_contents($root.'/js/apiCharacter.js');lifecycleCheck(str_contains((string)$front,'new FormData()')&&str_contains((string)$front,'formData.append("portrait", file)')&&str_contains((string)$front,'uploadCharacterPortrait.php'),'multipart frontend contract');lifecycleCheck(str_contains((string)$api,'deleteCharacter.php')&&str_contains((string)$api,'body: { id }'),'delete frontend contract');
}finally{lifecycleRemoveTree($fixture);}
if($failures){foreach($failures as$f)fwrite(STDERR,"FAIL: $f\n");exit(1);}echo"character lifecycle/portrait endpoint tests passed.\n";
