<?php
declare(strict_types=1);

/** Only explicit text-saving endpoints participate. Reads never create paid work. */
function aetherTranslationSaveScopes(string $route, array $input): array
{
    $row = static fn(string $table,array $fields,int $id,array $resultPath=[]): array => ['table'=>$table,'fields'=>$fields,'where'=>'id = ?','params'=>[$id],'resultPath'=>$resultPath];
    switch ($route) {
        case 'characters/saveCharacterDiary.php':
            return [['table'=>'tblCharacterDiary','fields'=>['goals','achievements','gossip1','gossip2','gossip3'],'where'=>'idCharacter = ? AND idEvent = ?','params'=>[(int)($input['idCharacter']??0),(int)($input['idEvent']??0)]]];
        case 'characters/saveCharacterSection.php':
            return [['table'=>'tblCharacterSection','fields'=>['content'],'where'=>'idCharacter = ? AND section = ?','params'=>[(int)($input['idCharacter']??0),(string)($input['section']??'')]]];
        case 'characters/saveCharacterTie.php':
            return [['table'=>'tblCharacterTie','fields'=>['description'],'where'=>'idCharacter = ?','params'=>[(int)($input['idCharacter']??0)]]];
        case 'characters/newCharacter.php':
            return [$row('tblCharacter',['nationality'],0,['@scalar'])];
        case 'characters/addSkillSpecialisation.php':
            return [['table'=>'tblSkillSpecialisation','fields'=>['name'],'where'=>'idSkill = ?','params'=>[(int)($input['idSkill']??0)]]];
        case 'admin/updateActionUse.php':
            return [$row('tblCharacterSkillActionUse',['resultTitle','resultText'],(int)($input['idActionUse']??0))];
        case 'characters/updateCharacter.php':
            return [$row('tblCharacter',['nationality'],(int)($input['id']??0))];
        case 'admin/saveSkill.php':
            $id=(int)($input['idSkill']??0);
            return [$row('tblSkill',['name','description','beginner','professional','master'],$id),['table'=>'tblSkillSpecialisation','fields'=>['name'],'where'=>'idSkill = ?','params'=>[$id]]];
        case 'admin/newSkill.php':
            return [$row('tblSkill',['name'],0,['skill','idSkill'])];
        case 'admin/saveSkillType.php':
            return ($input['action']??'')==='create' ? [['table'=>'tblSkillType','fields'=>['name'],'where'=>'name = ?','params'=>[(string)($input['name']??'')]]] : [$row('tblSkillType',['name'],(int)($input['idSkillType']??0))];
        case 'admin/traits.php':
            return [$row('tblTrait',['name','description','traitGroup'],(int)($input['id']??0),['trait','id'])];
        case 'companies/updateCompany.php':
            return [$row('tblCompany',['description'],(int)($input['id']??0))];
        case 'companies/saveCompanyPersonnel.php':
        case 'companies/saveCompanyPersonnelAction.php':
            return [['table'=>'tblCompanyPersonnel','fields'=>['functionDescription'],'where'=>'idCompany = ?','params'=>[(int)($input['idCompany']??0)]]];
        case 'companies/saveCompanyQuarter.php':
            return [['table'=>'tblCompanyQuarterEvent','fields'=>['description'],'where'=>'idCompanyQuarter IN (SELECT id FROM tblCompanyQuarter WHERE idCompany = ?)','params'=>[(int)($input['idCompany']??0)]]];
        case 'events/updateEvent.php':
            return [$row('tblEvent',['title','description'],(int)($input['id']??0))];
        case 'events/newEvent.php':
            return [$row('tblEvent',['title','description'],0,['@scalar'])];
        case 'research/http.php':
            $table=match($input['op']??'') {'challenge_save'=>'tblResearchChallenge','discovery_save'=>'tblResearchDiscovery','step_save'=>'tblResearchStep',default=>null};
            return $table ? [$row($table,$table==='tblResearchStep'?['content']:['title','description'],(int)($input['id']??0),['id'])] : [];
    }
    return [];
}
function aetherTranslationSaveCanonical(string $value): string
{
    return trim(aetherTranslationSafeHtml($value));
}
function aetherTranslationScopeValues(PDO $pdo,array $scope): array
{
    // All identifiers and predicates come from the fixed server-side route map above.
    $statement=$pdo->prepare('SELECT '.implode(',',$scope['fields']).' FROM '.$scope['table'].' WHERE '.$scope['where']);
    $statement->execute($scope['params']);$values=[];
    foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $row)foreach($scope['fields'] as $field)if(is_string($row[$field]??null)&&trim($row[$field])!=='')$values[$field][hash('sha256',$row[$field])]=$row[$field];
    return $values;
}
function aetherCaptureTranslationSave(PDO $pdo,array $input): void
{
    unset($GLOBALS['AETHER_TRANSLATION_SAVE']);
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_array($GLOBALS['AETHER_AUTHENTICATED_USER']??null)) return;
    $route=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??''));$position=strpos($route,'/api/');
    if($position===false)return;$scopes=aetherTranslationSaveScopes(substr($route,$position+5),$input);if(!$scopes)return;
    require_once __DIR__.'/translationService.php';
    $submitted=[];
    $walk=static function(array $values)use(&$walk,&$submitted):void{foreach($values as $field=>$value){if(is_array($value))$walk($value);elseif(is_string($value)&&trim($value)!=='')$submitted[(string)$field][aetherTranslationSaveCanonical($value)]=true;}};
    $walk($input);$captured=[];
    foreach($scopes as $scope){try{$scope['before']=aetherTranslationScopeValues($pdo,$scope);$captured[]=$scope;}catch(Throwable $e){/* Optional metadata must never block saving game data. */}}
    $language=(string)($_SERVER['HTTP_X_AETHER_CONTENT_LANGUAGE']??'');
    if(!in_array($language,['nl','en'],true))$language='nl';
    $GLOBALS['AETHER_TRANSLATION_SAVE']=['scopes'=>$captured,'submitted'=>$submitted,'sourceLanguage'=>$language,'userId'=>(int)$GLOBALS['AETHER_AUTHENTICATED_USER']['id']];
}
/** Existing source-language metadata keeps editing a Dutch source in an English UI Dutch. */
function aetherSavedTranslationLanguage(PDO $pdo,string $hash,array $previous,string $fallback): string
{
    foreach ([[$hash],array_keys($previous)] as $hashes) {
        if (!$hashes) continue;
        try {
            $s=$pdo->prepare('SELECT DISTINCT sourceLanguage FROM tblContentTranslation WHERE sourceHash IN ('.implode(',',array_fill(0,count($hashes),'?')).") AND status = 'ready'");
            $s->execute($hashes);$languages=array_values(array_intersect(['nl','en'],$s->fetchAll(PDO::FETCH_COLUMN)));
            if (count($languages)===1) return $languages[0];
        } catch (Throwable $error) { /* Missing optional metadata uses the input language. */ }
    }
    return $fallback;
}
/** Call only after a successful save, outside the domain transaction. */
function aetherSavedTranslationChanges(PDO $pdo,mixed $result): array
{
    $context=$GLOBALS['AETHER_TRANSLATION_SAVE']??null;unset($GLOBALS['AETHER_TRANSLATION_SAVE']);
    if(!$context||$pdo->inTransaction()||(int)($GLOBALS['AETHER_AUTHENTICATED_USER']['id']??0)!==$context['userId'])return [];
    $changes=[];
    foreach($context['scopes'] as $scope){
        if(($scope['params'][0]??null)===0&&!empty($scope['resultPath'])){
            $id=$result;foreach($scope['resultPath'] as $part){$id=$part==='@scalar'?$id:(is_array($id)?($id[$part]??null):null);}
            if(!is_numeric($id)||(int)$id<=0)continue;$scope['params'][0]=(int)$id;
        }
        try{foreach(aetherTranslationScopeValues($pdo,$scope) as $field=>$values)foreach($values as $hash=>$source){
            if(isset($scope['before'][$field][$hash])||!isset($context['submitted'][$field][aetherTranslationSaveCanonical($source)]))continue;
            $changes[$hash]=['source'=>$source,'sourceLanguage'=>aetherSavedTranslationLanguage($pdo,$hash,$scope['before'][$field]??[],$context['sourceLanguage'])];
        }}catch(Throwable $e){/* Saving succeeded; optional translation failure must not undo it. */}
    }
    return $changes;
}
