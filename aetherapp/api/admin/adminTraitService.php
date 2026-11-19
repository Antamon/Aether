<?php
declare(strict_types=1);
require_once __DIR__.'/../shared/validation.php';

final class AdminTraitProblem extends RuntimeException
{
    public function __construct(public readonly int $status,string $message){parent::__construct($message);}
}
function aetherAdminTraitQuery(PDO $pdo,string $sql,array $params=[]): PDOStatement
{
    $statement=$pdo->prepare($sql);$statement->execute($params);return $statement;
}
function aetherAdminTraitCatalog(PDO $pdo): array
{
    return aetherAdminTraitQuery($pdo,'SELECT id,name,`type`,`class`,traitGroup FROM tblTrait ORDER BY name,`class`,`type`,id')->fetchAll(PDO::FETCH_ASSOC);
}
function aetherAdminTraitVersion(array $row): string
{
    return hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function aetherAdminTraitDetail(PDO $pdo,int $id): array
{
    $row=aetherAdminTraitQuery($pdo,'SELECT * FROM tblTrait WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new AdminTraitProblem(404,'Eigenschap niet gevonden.');
    $row['version']=aetherAdminTraitVersion($row);
    $row['inUse']=(bool)aetherAdminTraitQuery($pdo,'SELECT id FROM tblLinkCharacterTrait WHERE idTrait=? LIMIT 1',[$id])->fetchColumn();
    $holders=aetherAdminTraitQuery($pdo,"SELECT c.id,c.title,c.firstName,c.lastName,l.rankValue FROM tblLinkCharacterTrait l JOIN tblCharacter c ON c.id=l.idCharacter WHERE l.idTrait=? AND c.state='active' ORDER BY l.rankValue,c.lastName,c.firstName,c.id",[$id])->fetchAll(PDO::FETCH_ASSOC);
    $groups=[];
    foreach($holders as $holder){
        $rank=(int)$holder['rankValue'];$key=$row['rankType']==='singular'?0:$rank;
        $groups[$key]??=['level'=>$key,'label'=>$row['rankType']==='singular'?'Personages':'Rang '.$rank,'characters'=>[]];
        $groups[$key]['characters'][(int)$holder['id']]=['idCharacter'=>(int)$holder['id'],'displayName'=>trim($holder['lastName'].' '.$holder['firstName']),'portraitUrl'=>function_exists('getCharacterPortraitUrl')?getCharacterPortraitUrl((int)$holder['id']):null,'specialisations'=>[]];
    }
    foreach($groups as &$group)$group['characters']=array_values($group['characters']);unset($group);
    $row['holders']=array_values($groups);return $row;
}
function aetherAdminValidateTrait(array $input): array
{
    $nullEvolution=array_key_exists('evolution',$input)&&$input['evolution']===null;
    if($nullEvolution)$input['evolution']='0.00';
    elseif(isset($input['evolution'])&&is_string($input['evolution']))$input['evolution']=str_replace(',','.',trim($input['evolution']));
    $data=aetherValidateInput($input,[
        'id'=>['type'=>'int','required'=>true,'min'=>0],
        'version'=>['type'=>'string','required'=>true,'maxLength'=>64],
        'name'=>['type'=>'string','required'=>true,'trim'=>true,'minLength'=>1,'maxLength'=>150],
        'description'=>['type'=>'string','required'=>true,'maxLength'=>16000],
        'class'=>['type'=>'enum','required'=>true,'values'=>['all','upper class','middle class','lower class']],
        'type'=>['type'=>'enum','required'=>true,'values'=>['status','profession','quality']],
        'rankType'=>['type'=>'enum','required'=>true,'values'=>['singular','range_positive','range_signed']],
        'traitGroup'=>['type'=>'string','required'=>true,'trim'=>true,'maxLength'=>100],
        'isUnique'=>['type'=>'bool','required'=>true], 'grouped'=>['type'=>'bool','required'=>true],
        'cost'=>['type'=>'nullable_int','required'=>true,'min'=>-2147483648,'max'=>2147483647],
        'income'=>['type'=>'nullable_int','required'=>true,'min'=>-2147483648,'max'=>2147483647],
        'evolution'=>['type'=>'decimal','required'=>true,'scale'=>2,'min'=>'-9999.99','max'=>'9999.99'],
        'staffRequirements'=>['type'=>'int','required'=>true,'min'=>0,'max'=>2147483647],
    ]);
    if($nullEvolution)$data['evolution']=null;
    return $data;
}
/** Caller owns the existing idempotent transaction. */
function aetherAdminSaveTrait(PDO $pdo,array $data): array
{
    $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    // Same lock order for creates and updates; serialize catalog duplicate checks.
    aetherAdminTraitQuery($pdo,'SELECT id FROM tblTrait ORDER BY id'.$lock)->fetchAll();
    $id=$data['id'];
    if($id){
        $current=aetherAdminTraitDetail($pdo,$id);
        if(!hash_equals($current['version'],$data['version']))throw new AdminTraitProblem(409,'Deze eigenschap werd intussen gewijzigd. Herlaad de eigenschap en probeer opnieuw.');
        foreach(['class','type','rankType','traitGroup','grouped','isUnique'] as $field){
            if($current['inUse'] && (string)$current[$field]!==((is_bool($data[$field]))?(string)(int)$data[$field]:(string)$data[$field]))throw new AdminTraitProblem(409,'Deze eigenschap is al aan personages gekoppeld. Type, klasse, groep en ranginstellingen kunnen daarom niet gewijzigd worden.');
        }
    }
    $duplicate=aetherAdminTraitQuery($pdo,'SELECT id FROM tblTrait WHERE LOWER(name)=LOWER(?) AND `class`=? AND `type`=? AND traitGroup=? AND id<>? LIMIT 1',[$data['name'],$data['class'],$data['type'],$data['traitGroup'],$id])->fetchColumn();
    if($duplicate)throw new AdminTraitProblem(409,'Een eigenschap met deze naam, klasse, type en groep bestaat al.');
    $fields=['name','description','class','type','rankType','traitGroup','isUnique','grouped','cost','income','evolution','staffRequirements'];
    $values=array_map(static fn($field)=>is_bool($data[$field])?(int)$data[$field]:$data[$field],$fields);
    if($id)aetherAdminTraitQuery($pdo,'UPDATE tblTrait SET '.implode(',',array_map(static fn($field)=>'`'.$field.'`=?',$fields)).' WHERE id=?',[...$values,$id]);
    else{
        aetherAdminTraitQuery($pdo,'INSERT INTO tblTrait ('.implode(',',array_map(static fn($field)=>'`'.$field.'`',$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',$values);
        $id=(int)$pdo->lastInsertId();
    }
    return ['trait'=>aetherAdminTraitDetail($pdo,$id),'traits'=>aetherAdminTraitCatalog($pdo)];
}
