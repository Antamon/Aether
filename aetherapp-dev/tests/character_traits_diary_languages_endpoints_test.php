<?php
declare(strict_types=1);

if (defined('AETHER_CHARACTER_WRITES_TEST_BOOTSTRAP')) {
    final class CharacterWritesTestStatement extends PDOStatement
    {
        private array $params = [];
        public function __construct(private CharacterWritesTestPdo $pdo, private string $query) {}
        private function sql(): string { return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query); }
        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $sql = $this->sql();
            if (str_starts_with($sql, 'INSERT INTO tblLinkCharacterTrait')) {
                $this->pdo->write($sql, $this->params);
                if ($this->pdo->scenario === 'trait_server_error') throw new PDOException('SQLSTATE secret_trait_table');
                $this->pdo->traitLinks[(int) $this->params['idCharacter']][(int) $this->params['idTrait']] = ['id' => 99, 'rankValue' => (int) $this->params['rankValue']];
            } elseif (str_starts_with($sql, 'UPDATE tblLinkCharacterTrait SET idTrait')) {
                $this->pdo->write($sql, $this->params);
                foreach ($this->pdo->traitLinks as &$links) foreach ($links as $id => $row) if ((int) $row['id'] === (int) $this->params['id']) { unset($links[$id]); $links[(int) $this->params['idTrait']] = $row; }
            } elseif (str_starts_with($sql, 'UPDATE tblLinkCharacterTrait SET rankValue')) {
                if ($this->pdo->scenario === 'trait_server_error') throw new PDOException('SQLSTATE secret_trait_table');
                $this->pdo->write($sql, $this->params);
                foreach ($this->pdo->traitLinks as &$links) foreach ($links as &$row) if ((int) $row['id'] === (int) $this->params['id']) $row['rankValue'] = (int) $this->params['rankValue'];
            } elseif (str_starts_with($sql, 'DELETE FROM tblLinkCharacterTrait')) {
                $this->pdo->write($sql, $this->params);
                foreach ($this->pdo->traitLinks as &$links) foreach ($links as $id => $row) if ((int) $row['id'] === (int) $this->params['id']) unset($links[$id]);
            } elseif (str_starts_with($sql, 'UPDATE tblCharacterDiary')) {
                $this->pdo->write($sql, $this->params);
                $id = (int) $this->params['idDiary'];
                foreach (['goals','achievements','gossip1','gossip2','gossip3'] as $field) $this->pdo->diaries[$id][$field] = (string) $this->params[$field];
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterDiary')) {
                $this->pdo->write($sql, $this->params);
                $id = ++$this->pdo->lastId;
                $this->pdo->diaries[$id] = ['id'=>$id,'idCharacter'=>(int)$this->params['idCharacter'],'idEvent'=>(int)$this->params['idEvent'],'goals'=>$this->params['goals'],'achievements'=>$this->params['achievements'],'gossip1'=>$this->params['gossip1'],'gossip2'=>$this->params['gossip2'],'gossip3'=>$this->params['gossip3']];
            } elseif (str_starts_with($sql, 'INSERT INTO tblLanguage')) {
                $this->pdo->write($sql, $this->params);
                $id = ++$this->pdo->lastId; $this->pdo->languages[$id] = ['id'=>$id,'name'=>$this->params['name']];
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterLanguage')) {
                $this->pdo->write($sql, $this->params);
                if ($this->pdo->scenario === 'language_link_error') throw new PDOException('SQLSTATE secret_language_link');
                $this->pdo->characterLanguages[(int)$this->params['idCharacter']][] = (int)$this->params['idLanguage'];
            }
            return true;
        }
        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblUser')) return $this->pdo->users[(int)($this->params['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM tblCharacterLanguage')) return in_array((int)$this->params['idLanguage'],$this->pdo->characterLanguages[(int)$this->params['idCharacter']]??[],true) ? ['id'=>1] : false;
            if (str_contains($sql, 'FROM tblCharacter') && !str_contains($sql, 'Diary')) return $this->pdo->characters[(int)($this->params['id'] ?? 0)] ?? false;
            if (str_contains($sql, 'FROM tblLinkCharacterTrait') && !str_contains($sql, 'JOIN')) return $this->pdo->traitLinks[(int)$this->params['idCharacter']][(int)$this->params['idTrait']] ?? false;
            if (str_contains($sql, "t.`type` = 'profession'")) return false;
            if (str_contains($sql, 'FROM tblEvent')) return $this->pdo->events[(int)$this->params['idEvent']] ?? false;
            if (str_contains($sql, 'FROM tblCharacterDiary') && isset($this->params['idDiary'])) {
                $row = $this->pdo->diaries[(int)$this->params['idDiary']] ?? null;
                return $row && (int)$row['idCharacter'] === (int)$this->params['idCharacter'] ? $row : false;
            }
            if (str_contains($sql, 'FROM tblCharacterDiary')) {
                foreach ($this->pdo->diaries as $row) if ((int)$row['idCharacter']===(int)$this->params['idCharacter'] && (int)$row['idEvent']===(int)$this->params['idEvent']) return ['id'=>$row['id']];
                return false;
            }
            if (str_contains($sql, 'FROM tblLanguage') && str_contains($sql, 'WHERE id =')) return $this->pdo->languages[(int)$this->params['id']] ?? false;
            if (str_contains($sql, 'FROM tblLanguage') && str_contains($sql, 'LOWER')) {
                foreach ($this->pdo->languages as $row) if (strtolower(trim($row['name']))===strtolower(trim((string)$this->params['name']))) return $row;
                return false;
            }
            return false;
        }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
        public function fetchColumn(int $column = 0): mixed { return false; }
    }

    final class CharacterWritesTestPdo extends PDO
    {
        public array $users = [10=>['id'=>10,'username'=>'participant','firstName'=>'Part','lastName'=>'Icipant','role'=>'participant'],20=>['id'=>20,'username'=>'director','firstName'=>'Di','lastName'=>'Rector','role'=>'director'],30=>['id'=>30,'username'=>'admin','firstName'=>'Ad','lastName'=>'Min','role'=>'administrator']];
        public array $characters = [
            1=>['id'=>1,'idUser'=>10,'type'=>'player','state'=>'draft','class'=>'upper class','experienceToTrait'=>0,'physicalHealth'=>0,'mentalHealth'=>0],
            2=>['id'=>2,'idUser'=>99,'type'=>'player','state'=>'draft','class'=>'middle class','experienceToTrait'=>0,'physicalHealth'=>0,'mentalHealth'=>0],
            3=>['id'=>3,'idUser'=>10,'type'=>'extra','state'=>'active','class'=>'upper class','experienceToTrait'=>0,'physicalHealth'=>0,'mentalHealth'=>0],
            4=>['id'=>4,'idUser'=>10,'type'=>'player','state'=>'active','class'=>'upper class','experienceToTrait'=>0,'physicalHealth'=>0,'mentalHealth'=>0],
        ];
        public array $traits = [101=>['id'=>101,'name'=>'Sterk','class'=>'all','type'=>'status','rankType'=>'range_positive','isUnique'=>0,'grouped'=>false,'secret'=>false],102=>['id'=>102,'name'=>'Geheim','class'=>'all','type'=>'status','rankType'=>'singular','isUnique'=>0,'grouped'=>false,'secret'=>true],103=>['id'=>103,'name'=>'Nieuwe kwaliteit','class'=>'all','type'=>'quality','rankType'=>'singular','isUnique'=>0,'grouped'=>true,'secret'=>false],104=>['id'=>104,'name'=>'Oude kwaliteit','class'=>'all','type'=>'quality','rankType'=>'singular','isUnique'=>0,'grouped'=>true,'secret'=>false]];
        public array $traitLinks = [1=>[101=>['id'=>41,'rankValue'=>1],104=>['id'=>42,'rankValue'=>1]]];
        public array $events = [11=>['id'=>11,'title'=>'Event 11','dateStart'=>'2026-01-01','dateEnd'=>'2026-01-02'],12=>['id'=>12,'title'=>'Event 12','dateStart'=>'2026-02-01','dateEnd'=>'2026-02-02']];
        public array $diaries = [50=>['id'=>50,'idCharacter'=>1,'idEvent'=>11,'goals'=>'<p>Oud</p>','achievements'=>'<p>Oud</p>','gossip1'=>'<b>letterlijk</b>','gossip2'=>'','gossip3'=>''],51=>['id'=>51,'idCharacter'=>2,'idEvent'=>11,'goals'=>'ander','achievements'=>'ander','gossip1'=>'','gossip2'=>'','gossip3'=>''],52=>['id'=>52,'idCharacter'=>3,'idEvent'=>11,'goals'=>'<p>Extra doel</p>','achievements'=>'<p>Oud</p>','gossip1'=>'bewaar mij','gossip2'=>'','gossip3'=>'']];
        public array $languages = [7=>['id'=>7,'name'=>'Frans'],8=>['id'=>8,'name'=>'Duits']];
        public array $characterLanguages = [1=>[7]];
        public int $lastId = 100;
        public int $writeAttempts = 0;
        public int $committedWrites = 0;
        private ?array $snapshot = null;
        public function __construct(public string $scenario) {}
        public function prepare(string $query, array $options = []): PDOStatement|false { return new CharacterWritesTestStatement($this,$query); }
        public function beginTransaction(): bool { $this->snapshot=[$this->traitLinks,$this->diaries,$this->languages,$this->characterLanguages,$this->committedWrites]; return true; }
        public function commit(): bool { $this->snapshot=null; return true; }
        public function rollBack(): bool { if ($this->snapshot) [$this->traitLinks,$this->diaries,$this->languages,$this->characterLanguages,$this->committedWrites]=$this->snapshot; $this->snapshot=null; return true; }
        public function inTransaction(): bool { return $this->snapshot!==null; }
        public function lastInsertId(?string $name = null): string|false { return (string)$this->lastId; }
        public function write(string $sql,array $params): void { $this->writeAttempts++; $this->committedWrites++; $this->lastSql=$sql; $this->lastParams=$params; }
        public string $lastSql=''; public array $lastParams=[];
    }

    function getPDO(): PDO { global $pdo; return $pdo; }
    function dbOne(PDO $pdo,string $sql,array $params=[]): ?array { $s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null; }
    function dbAll(PDO $pdo,string $sql,array $params=[]): array { $s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC); }

    $scenario=(string)($argv[1]??'success'); $pdo=new CharacterWritesTestPdo($scenario);
    $uid=match($scenario){'unauthenticated'=>999,'director'=>20,'administrator'=>30,default=>10};
    session_start(); $_SESSION=['user'=>['id'=>$uid,'role'=>$scenario==='forged_role'?'administrator':'participant'],'aetherCsrfToken'=>'expected-token'];
    if ($scenario!=='missing_csrf') $_SERVER['HTTP_X_CSRF_TOKEN']=$scenario==='invalid_csrf'?'bad-token':'expected-token';
    register_shutdown_function(static function()use($pdo):void{ $s=http_response_code();fwrite(STDERR,'__STATE__:'.json_encode(['status'=>$s===false?200:$s,'writes'=>$pdo->committedWrites,'attempts'=>$pdo->writeAttempts,'sql'=>$pdo->lastSql,'params'=>$pdo->lastParams,'languages'=>$pdo->languages,'links'=>$pdo->characterLanguages])."\n"); });
    return;
}

$root=dirname(__DIR__); $fixture=sys_get_temp_dir().'/aether-character-writes-'.bin2hex(random_bytes(5));
function copyTree(string $from,string $to):void{ if(!is_dir($to))mkdir($to,0777,true);foreach(new DirectoryIterator($from) as $i){if($i->isDot())continue;$dest=$to.'/'.$i->getFilename();$i->isDir()?copyTree($i->getPathname(),$dest):copy($i->getPathname(),$dest);} }
function removeTree(string $path):void{if(!is_dir($path))return;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $i){$i->isDir()?rmdir($i->getPathname()):unlink($i->getPathname());}rmdir($path);}
copyTree($root.'/api',$fixture.'/api'); copyTree($root.'/vendor',$fixture.'/vendor'); copy($root.'/sessionUserBootstrap.php',$fixture.'/sessionUserBootstrap.php');
file_put_contents($fixture.'/db.php',"<?php\ndefine('AETHER_CHARACTER_WRITES_TEST_BOOTSTRAP',true);require ".var_export(__FILE__,true).";\n");
file_put_contents($fixture.'/api/characters/characterPointUtils.php', <<<'PHP'
<?php
function getCharacterPointSummary(PDO $pdo,array $character):array{return ['availableStatusPoints'=>$pdo->scenario==='trait_no_points'?0:10,'remainingExperience'=>$pdo->scenario==='language_no_points'?0:10,'isPlayer'=>($character['type']??'')==='player'];}
function isPrivilegedUserRole(string $role):bool{return in_array($role,['director','administrator'],true);}
PHP);
file_put_contents($fixture.'/api/characters/traitUtils.php', <<<'PHP'
<?php
function getTraitDefinition(PDO $pdo,int $id):?array{return $pdo->traits[$id]??null;}
function traitHasFlag(array $trait,string $flag):bool{return !empty($trait[$flag]);}
function isGroupedTrait(array $trait):bool{return !empty($trait['grouped']);}
function areTraitsInSameSelectionGroup(array $a,array $b):bool{return ($a['type']??'')===($b['type']??'');}
function getCharacterTraitLinks(PDO $pdo,int $id,array $types=[]):array{$out=[];foreach($pdo->traitLinks[$id]??[] as $tid=>$link){$t=$pdo->traits[$tid]??null;if($t&&(!$types||in_array($t['type'],$types,true)))$out[]=$t+$link;}return $out;}
function calculateTraitPointCost(array $trait,int $rank):int{return max(0,$rank);}
function isCompanyShareTrait(array $trait):bool{return false;}
function getCompanyShareBaseRank(array $trait):int{return (int)$trait['baseRank'];}
function getCompanyShareDraftStep(array $trait):int{return 1;}
PHP);
file_put_contents($fixture.'/api/characters/characterLanguageUtils.php', <<<'PHP'
<?php
function characterLanguageSchemaReady(PDO $pdo):bool{return $pdo->scenario!=='language_schema_missing';}
function canCurrentUserManageCharacterLanguages(array $c,string $r,int $u):bool{return in_array($r,['director','administrator'],true)||($r==='participant'&&($c['type']??'')==='player'&&(int)$c['idUser']===$u);}
function canCharacterUseWrittenLanguages(PDO $pdo,array $c):bool{return ($c['class']??'')!=='lower class';}
function getCharacterFreeLanguageSlotCount(PDO $pdo,array $c,?array $s=null):int{return $pdo->scenario==='language_no_points'?1:2;}
function getCharacterLanguages(PDO $pdo,int $id):array{return array_map(fn($lid)=>$pdo->languages[$lid],$pdo->characterLanguages[$id]??[]);}
PHP);
file_put_contents($fixture.'/api/characters/characterReadService.php', <<<'PHP'
<?php
require_once __DIR__.'/characterRichText.php';
function aetherBuildCharacterDiaryReadModel(PDO $pdo,int $id):array{if($pdo->scenario==='diary_read_error')throw new PDOException('SQLSTATE secret_diary_read');$entries=[];$used=[];foreach($pdo->diaries as $row){if((int)$row['idCharacter']!==$id)continue;$used[]=(int)$row['idEvent'];$event=$pdo->events[(int)$row['idEvent']];$entries[]=aetherSanitizeCharacterDiaryRow($row+['eventTitle'=>$event['title'],'dateStart'=>$event['dateStart'],'dateEnd'=>$event['dateEnd']]);}$available=[];foreach($pdo->events as $e)if(!in_array((int)$e['id'],$used,true))$available[]=$e;return ['entries'=>$entries,'availableEvents'=>$available];}
PHP);

$failures=[];
function check(bool $ok,string $message):void{global $failures;if(!$ok)$failures[]=$message;}
function runRoute(string $route,string $scenario,array|string $request):array{global $fixture;$p=proc_open([PHP_BINARY,$fixture.'/api/characters/'.$route,$scenario],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],is_string($request)?$request:json_encode($request));fclose($pipes[0]);$body=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($p);preg_match('/__STATE__:(\{.*\})/',$err,$m);return ['body'=>$body,'json'=>json_decode($body,true),'state'=>isset($m[1])?json_decode($m[1],true):null,'exit'=>$exit,'err'=>$err];}
function expect(string $route,string $scenario,array|string $request,int $status,int $writes):array{$r=runRoute($route,$scenario,$request);check(($r['state']['status']??0)===$status,"$route/$scenario status; body={$r['body']} stderr={$r['err']}");check(($r['state']['writes']??-1)===$writes,"$route/$scenario writes; body={$r['body']}");check($r['exit']===0,"$route/$scenario exit: {$r['err']}");return $r;}

try{
    $r=expect('updateTrait.php','success',['action'=>'rank_up','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],200,1);check($r['json']===['status'=>'ok'],'trait success response');check(($r['state']['params']??[])===['rankValue'=>2,'id'=>41],'trait prepared parameters');
    $r=expect('updateTrait.php','success',['action'=>'change','idCharacter'=>1,'idTrait'=>103,'idCurrentTrait'=>104],200,1);check(($r['state']['params']??[])===['idTrait'=>103,'id'=>42],'trait change prepared parameters');
    expect('updateTrait.php','success',['action'=>'remove','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],200,1);
    expect('updateTrait.php','director',['action'=>'add','idCharacter'=>2,'idTrait'=>101,'idCurrentTrait'=>0],200,1);
    expect('updateTrait.php','administrator',['action'=>'add','idCharacter'=>2,'idTrait'=>101,'idCurrentTrait'=>0],200,1);
    expect('updateTrait.php','success',['action'=>'add','idCharacter'=>2,'idTrait'=>101,'idCurrentTrait'=>0],403,0);
    expect('updateTrait.php','success',['action'=>'add','idCharacter'=>4,'idTrait'=>101,'idCurrentTrait'=>0],403,0);
    expect('updateTrait.php','success',['action'=>'add','idCharacter'=>1,'idTrait'=>102,'idCurrentTrait'=>0],403,0);
    $r=expect('updateTrait.php','trait_no_points',['action'=>'rank_up','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],200,0);check(($r['json']['error']??'')==='Onvoldoende statuspunten.','trait point limit');
    $r=expect('updateTrait.php','success',['action'=>'rank_down','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],200,0);check(($r['json']['error']??'')==='De rang kan niet lager dan 1.','trait minimum rank');
    expect('updateTrait.php','invalid_csrf',['action'=>'rank_up','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],403,0);
    expect('updateTrait.php','unauthenticated',['action'=>'rank_up','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],401,0);
    expect('updateTrait.php','success',['action'=>'rank_up','idCharacter'=>999,'idTrait'=>101,'idCurrentTrait'=>0],404,0);
    expect('updateTrait.php','success',['action'=>'rank_up','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0,'column`=1'=>1],422,0);
    $r=expect('updateTrait.php','trait_server_error',['action'=>'rank_up','idCharacter'=>1,'idTrait'=>101,'idCurrentTrait'=>0],500,0);check(!str_contains($r['body'],'SQLSTATE'),'trait generic 500');

    $payload=['idCharacter'=>1,'idEvent'=>12,'goals'=>'<p><strong>Doel</strong><script>x</script></p>','achievements'=>'<h4 onclick="x">Winst</h4>','gossip1'=>'<b>letterlijk</b>','gossip2'=>'','gossip3'=>''];
    $r=expect('saveCharacterDiary.php','success',$payload,200,1);check(($r['json']['success']??false)===true,'diary response: '.$r['body']);check(($r['state']['params']['idCharacter']??0)===1&&($r['state']['params']['idEvent']??0)===12&&($r['state']['params']['createdBy']??0)===10,'diary prepared parameters');$entries=$r['json']['entries']??[];$entry=$entries!==[]?end($entries):[];check(str_contains((string)($entry['goals']??''),'<strong>Doel</strong>')&&!str_contains((string)($entry['goals']??''),'script'),'diary rich text');check(($entry['gossip1']??'')==='<b>letterlijk</b>','gossip plain payload preserved');
    expect('saveCharacterDiary.php','success',['idCharacter'=>1,'idDiary'=>999,'idEvent'=>11,'goals'=>'','achievements'=>'','gossip1'=>'','gossip2'=>'','gossip3'=>''],404,0);
    expect('saveCharacterDiary.php','success',['idCharacter'=>1,'idEvent'=>999,'goals'=>'','achievements'=>'','gossip1'=>'','gossip2'=>'','gossip3'=>''],404,0);
    expect('saveCharacterDiary.php','success',['idCharacter'=>2,'idEvent'=>12,'goals'=>'','achievements'=>'','gossip1'=>'','gossip2'=>'','gossip3'=>''],403,0);
    expect('saveCharacterDiary.php','success',['idCharacter'=>1,'idEvent'=>11,'goals'=>'','achievements'=>'','gossip1'=>'','gossip2'=>'','gossip3'=>''],400,0);
    $extra=expect('saveCharacterDiary.php','success',['idCharacter'=>3,'idDiary'=>52,'idEvent'=>11,'goals'=>'gewijzigd','achievements'=>'<strong>Nieuw</strong><script>x</script>','gossip1'=>'gewijzigd','gossip2'=>'','gossip3'=>''],200,1);$extraEntry=array_values(array_filter($extra['json']['entries']??[],static fn(array $row):bool=>(int)$row['id']===52))[0]??[];check(($extraEntry['goals']??'')==='<p>Extra doel</p>'&&($extraEntry['gossip1']??'')==='bewaar mij','extra can only edit achievements');check(str_contains((string)($extraEntry['achievements']??''),'<strong>Nieuw</strong>')&&!str_contains((string)($extraEntry['achievements']??''),'script'),'extra achievement sanitized');
    expect('saveCharacterDiary.php','director',['idCharacter'=>2,'idEvent'=>12,'goals'=>'','achievements'=>'','gossip1'=>'','gossip2'=>'','gossip3'=>''],200,1);
    expect('saveCharacterDiary.php','administrator',['idCharacter'=>2,'idDiary'=>51,'idEvent'=>11,'goals'=>'','achievements'=>'','gossip1'=>'','gossip2'=>'','gossip3'=>''],200,1);
    $r=expect('saveCharacterDiary.php','diary_read_error',$payload,500,0);check(!str_contains($r['body'],'SQLSTATE'),'diary generic 500');
    expect('saveCharacterDiary.php','invalid_csrf',$payload,403,0);

    $r=expect('addCharacterLanguage.php','success',['idCharacter'=>1,'idLanguage'=>8],200,1);check($r['json']===['success'=>true],'language response');check(($r['state']['params']??[])===['idCharacter'=>1,'idLanguage'=>8,'createdBy'=>10],'language prepared parameters');
    $r=expect('addCharacterLanguage.php','success',['idCharacter'=>1,'name'=>'Spaans'],200,2);check(count($r['state']['languages'])===3,'custom language definition committed');
    expect('addCharacterLanguage.php','director',['idCharacter'=>2,'idLanguage'=>8],200,1);
    expect('addCharacterLanguage.php','success',['idCharacter'=>2,'idLanguage'=>8],403,0);
    expect('addCharacterLanguage.php','success',['idCharacter'=>1,'idLanguage'=>999],404,0);
    expect('addCharacterLanguage.php','success',['idCharacter'=>999,'idLanguage'=>8],404,0);
    expect('addCharacterLanguage.php','success',['idCharacter'=>1,'idLanguage'=>7],400,0);
    expect('addCharacterLanguage.php','success',['idCharacter'=>1,'name'=>' frans '],400,0);
    expect('addCharacterLanguage.php','language_no_points',['idCharacter'=>1,'name'=>'Spaans'],400,0);
    $r=expect('addCharacterLanguage.php','language_link_error',['idCharacter'=>1,'name'=>'Spaans'],500,0);check(!str_contains($r['body'],'SQLSTATE'),'language generic 500');check(count($r['state']['languages'])===2,'new language rolled back');
    expect('addCharacterLanguage.php','invalid_csrf',['idCharacter'=>1,'idLanguage'=>8],403,0);
    expect('addCharacterLanguage.php','success',['idCharacter'=>1,'idLanguage'=>8,'role'=>'administrator'],422,0);

    foreach(['updateTrait.php','saveCharacterDiary.php','addCharacterLanguage.php'] as $route){$source=file_get_contents($root.'/api/characters/'.$route);check(substr_count((string)$source,"\n")<60,"$route is dun");check(str_contains((string)$source,'aetherReadJsonObject()'),"$route explicit JSON");check(!str_contains((string)$source,'session_start()'),"$route no session_start");check(!str_contains((string)$source,'aetherReadCharacterJsonRequest'),"$route no facade");}
}finally{removeTree($fixture);}
if($failures){foreach($failures as $f)fwrite(STDERR,"FAIL: $f\n");exit(1);}echo "character trait/diary/language endpoint tests passed.\n";
