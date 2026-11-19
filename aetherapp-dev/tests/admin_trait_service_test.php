<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/admin/adminTraitService.php';
function traitCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function traitFails(callable $fn,int $status):void{try{$fn();}catch(AdminTraitProblem $e){traitCheck($e->status===$status,'Wrong error');return;}catch(AetherValidationException $e){traitCheck($status===422,'Wrong validation');return;}throw new RuntimeException('Expected '.$status);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE tblTrait(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,description TEXT,class TEXT,type TEXT,rankType TEXT,traitGroup TEXT,isUnique INT,grouped INT,cost INT,income INT,evolution NUMERIC,staffRequirements INT,customMetadata TEXT DEFAULT 'preserve');
CREATE TABLE tblCharacter(id INT,title TEXT,firstName TEXT,lastName TEXT,state TEXT);
CREATE TABLE tblLinkCharacterTrait(id INT,idTrait INT,idCharacter INT,rankValue INT);
INSERT INTO tblCharacter VALUES(1,'','Ada','Active','active'),(2,'','Bob','Inactive','inactive');");
$save=function(array $input)use($pdo){$pdo->beginTransaction();try{$result=aetherAdminSaveTrait($pdo,aetherAdminValidateTrait($input));$pdo->commit();return $result;}catch(Throwable $e){$pdo->rollBack();throw $e;}};
$input=['id'=>0,'version'=>'','name'=>'Test status','description'=>'Original','class'=>'all','type'=>'status','rankType'=>'range_positive','traitGroup'=>'Test','isUnique'=>false,'grouped'=>false,'cost'=>1,'income'=>null,'evolution'=>'0,15','staffRequirements'=>0];
try{$created=$save($input);}catch(AetherValidationException $e){throw new RuntimeException(implode('; ',$e->getValidationErrors()));}$trait=$created['trait'];traitCheck($trait['id']>0&&abs((float)$trait['evolution']-.15)<.0001,'Create handles decimal comma');
$input['id']=(int)$trait['id'];$input['version']=$trait['version'];$input['description']='<script>plain text</script>';
$result=$save($input);traitCheck($result['trait']['description']===$input['description'],'Plain text retained');
traitFails(fn()=>$save($input),409);$input['version']=$result['trait']['version'];
traitFails(fn()=>$save(array_merge($input,['cost'=>'one'])),422);traitFails(fn()=>$save(array_merge($input,['type'=>'admin'])),422);traitFails(fn()=>$save(array_merge($input,['idUser'=>99])),422);
traitFails(fn()=>$save(array_merge($input,['id'=>0,'version'=>''])),409);
$pdo->exec('INSERT INTO tblLinkCharacterTrait VALUES(1,1,1,2),(2,1,2,3)');$detail=aetherAdminTraitDetail($pdo,(int)$trait['id']);
traitCheck($detail['inUse']&&count($detail['holders'])===1&&$detail['holders'][0]['characters'][0]['idCharacter']===1,'Only active holders, grouped by rank');
$input['version']=$detail['version'];traitFails(fn()=>$save(array_merge($input,['rankType'=>'singular'])),409);
$input['income']=-50;$input['evolution']=null;$result=$save($input);traitCheck($result['trait']['income']===-50&&$result['trait']['evolution']===null,'Nullable values and negative income saved');
traitCheck($result['trait']['customMetadata']==='preserve'&&(int)$pdo->query('SELECT COUNT(*) FROM tblLinkCharacterTrait')->fetchColumn()===2,'Metadata and ownership untouched');
traitCheck(count(aetherAdminTraitCatalog($pdo))===1,'Catalog ready for dropdown');
echo "PASS: trait create/update, decimal comma, null values, validation, duplicate/stale writes, active holders and linked-data protection.\n";
