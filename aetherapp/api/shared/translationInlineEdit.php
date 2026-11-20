<?php
declare(strict_types=1);

final class AetherInlineTranslationForbidden extends RuntimeException {}

function aetherTranslationEditPath(array $path): string { return json_encode(array_map('strval',$path),JSON_THROW_ON_ERROR); }
function aetherTranslationEditValue(array $input,array $path): mixed { $value=$input;foreach($path as $part){if(!is_array($value)||!array_key_exists($part,$value))return null;$value=$value[$part];}return $value; }
function aetherSetTranslationEditValue(array &$input,array $path,string $value): void { $target=&$input;foreach($path as $part)$target=&$target[$part];$target=$value; }
/** Resolve only the text fields of records covered by the normal save endpoint. */
function aetherTranslationEditablePaths(PDO $pdo,array $scopes,array $input): array
{
    $paths=[];
    foreach($scopes as $scope){
        $table=$scope['table'];$keys=match($table){'tblSkillSpecialisation','tblCompanyQuarterEvent'=>['id'],'tblCompanyPersonnel'=>['idCharacter'],default=>[]};
        $fields=array_unique([...$scope['fields'],...$keys]);
        $s=$pdo->prepare('SELECT '.implode(',',$fields).' FROM '.$table.' WHERE '.$scope['where']);$s->execute($scope['params']);
        foreach($s->fetchAll(PDO::FETCH_ASSOC)as$row){
            $prefixes=[[]];
            if($table==='tblSkillSpecialisation'){
                $prefixes=[];
                foreach($input['specialisations']??[]as$i=>$item)if((int)($item['idSkillSpecialisation']??0)===(int)$row['id'])$prefixes[]=['specialisations',$i];
            }elseif($table==='tblCompanyPersonnel'){
                $prefixes=[];
                if(isset($input['personnel'])){foreach($input['personnel']as$i=>$item)if((int)($item['idCharacter']??0)===(int)$row['idCharacter'])$prefixes[]=['personnel',$i];}
                elseif((int)($input['idCharacter']??0)===(int)$row['idCharacter'])$prefixes[]=[];
            }elseif($table==='tblCompanyQuarterEvent'){
                $prefixes=[];foreach($input['events']??[]as$i=>$item)if((int)($item['id']??0)===(int)$row['id'])$prefixes[]=['events',$i];
            }
            foreach($prefixes as$prefix)foreach($scope['fields']as$field){$path=[...$prefix,$field];if(is_string($row[$field]??null))$paths[aetherTranslationEditPath($path)]=['source'=>$row[$field],'path'=>$path];}
        }
    }
    return $paths;
}
/** Rewrite translated edits to the original source before normal domain validation/authorization. */
function aetherPrepareInlineTranslationEdits(PDO $pdo,array $scopes,array &$input,array $edits,string $language,array $user): array
{
    if(!$edits)return [];
    if(!in_array($user['role']??'', ['administrator','director'],true))throw new AetherInlineTranslationForbidden('Alleen administrators en directors kunnen vertalingen aanpassen.');
    if(count($edits)>200)throw new InvalidArgumentException('Te veel vertalingen in één bewerking.');
    $allowed=aetherTranslationEditablePaths($pdo,$scopes,$input);$pending=[];
    foreach($edits as$edit){
        if(!is_array($edit)||!is_array($edit['path']??null)||!array_is_list($edit['path'])||count($edit['path'])>10)throw new InvalidArgumentException('Ongeldige vertaalbewerking.');
        foreach($edit['path']as$part)if(!is_string($part)&&!is_int($part))throw new InvalidArgumentException('Ongeldige vertaalbewerking.');
        $item=$allowed[aetherTranslationEditPath($edit['path'])]??null;
        if(!$item||!is_string($edit['sourceHash']??null)||!hash_equals(hash('sha256',$item['source']),$edit['sourceHash']))throw new DomainException('De oorspronkelijke tekst is gewijzigd. Vernieuw de pagina en probeer opnieuw.');
        $row=aetherTranslationCached($pdo,$item['source'],$language);
        if(!$row||$row['status']!=='ready'||!in_array($row['sourceLanguage'],['nl','en'],true)||$row['sourceLanguage']===$language)throw new DomainException('De vertaling is gewijzigd. Vernieuw de pagina en probeer opnieuw.');
        $text=aetherTranslationEditValue($input,$item['path']);
        if(!is_string($text)||mb_strlen($text,'UTF-8')>500000)throw new InvalidArgumentException('Ongeldige vertaalinvoer.');
        if($row['format']==='html')$text=aetherTranslationSafeHtml($text);
        if(trim(strip_tags($text))==='')throw new InvalidArgumentException('Vul een vertaling in.');
        if($text!==$row['translatedText']){
            if(!is_string($edit['version']??null)||!hash_equals(aetherTranslationVersion($row),$edit['version']))throw new DomainException('De vertaling is ondertussen gewijzigd. Open de tekst opnieuw.');
            if(isset($pending[$edit['sourceHash']])&&$pending[$edit['sourceHash']]['text']!==$text)throw new InvalidArgumentException('Dezelfde tekst heeft verschillende vertalingen in dit formulier.');
            $pending[$edit['sourceHash']]=['source'=>$item['source'],'target'=>$language,'format'=>$row['format'],'text'=>$text,'sourceLanguage'=>$row['sourceLanguage'],'version'=>$edit['version']];
        }
        aetherSetTranslationEditValue($input,$item['path'],$item['source']);
    }
    return $pending;
}
/** Normal save authorization must have succeeded before any correction is stored. */
function aetherApplyInlineTranslationEdits(PDO $pdo,array $edits): void
{
    if(!$edits)return;
    $pdo->beginTransaction();
    try{
        foreach($edits as$edit){
            $current=aetherTranslationCached($pdo,$edit['source'],$edit['target']);
            if($current&&$current['status']==='ready'&&$current['translatedText']===$edit['text'])continue;
            aetherTranslationSaveManual($pdo,$edit['source'],$edit['target'],$edit['format'],$edit['text'],$edit['sourceLanguage'],$edit['version']);
        }
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
