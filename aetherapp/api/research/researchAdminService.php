<?php
declare(strict_types=1);
require_once __DIR__.'/researchService.php';
require_once __DIR__.'/../shared/validation.php';
require_once __DIR__.'/../shared/richText.php';

function researchInt(mixed $value,int $min=1,int $max=2147483647): int {
    return aetherValidateInput(['value'=>$value],['value'=>['type'=>'int','required'=>true,'min'=>$min,'max'=>$max]])['value'];
}
function researchIds(mixed $ids): array {
    if (!is_array($ids) || !array_is_list($ids)) throw new AetherResearchException(422,'Kies een geldige lijst.');
    return array_values(array_unique(array_map(fn($id)=>researchInt($id),$ids)));
}
function researchText(mixed $value,int $max=200,bool $required=true): string {
    return aetherValidateInput(['value'=>$value],['value'=>['type'=>'string','required'=>true,'minLength'=>$required?1:0,'maxLength'=>$max]])['value'];
}
function researchHtml(mixed $html): string { return aetherSanitizeRichText(researchText($html,100000,false),['p','h4','strong','em','u','ul','ol','li','br','blockquote']); }
function researchBool(mixed $value): bool { return aetherValidateInput(['value'=>$value],['value'=>['type'=>'bool','required'=>true]])['value']; }
function researchFields(array $data,array $allowed): void {
    if (array_diff(array_keys($data),$allowed)) throw new AetherResearchException(422,'Onverwachte invoervelden.');
}
function researchRevision(array $row,array $data): void {
    if ((int)$row['revision']!==researchInt($data['revision']??0)) throw new AetherResearchException(409,'Dit onderdeel werd intussen gewijzigd. Vernieuw en probeer opnieuw.');
}
function researchUnused(PDO $pdo,int $discovery): void {
    if (researchOne($pdo,'SELECT idDiscovery FROM tblResearchFound WHERE idDiscovery = ? LIMIT 1',[$discovery]))
        throw new AetherResearchException(409,'Deze discovery is al gevonden. De feedbackroute en voorwaarden blijven daarom vast; inhoud en activering kun je nog wijzigen. Maak voor een nieuwe route een nieuwe discovery.');
}
function researchSpec(PDO $pdo,int $id,?int $skill=null): void {
    $s=researchOne($pdo,"SELECT idSkill FROM tblSkillSpecialisation WHERE id = ? AND kind = 'specialisation'",[$id]);
    if (!$s || ($skill!==null && (int)$s['idSkill']!==$skill)) throw new AetherResearchException(422,'Kies een passende specialisatie.');
}
function researchConditions(PDO $pdo,array $d,array $step,mixed $input): array {
    if (!is_array($input) || !array_is_list($input)) throw new AetherResearchException(422,'Ongeldige voorwaarden.');
    $result=[];$timers=[];
    foreach ($input as $c) {
        if (!is_array($c)) throw new AetherResearchException(422,'Ongeldige voorwaarde.');
        $type=$c['type']??'';
        if ($type==='step') {
            researchFields($c,['type','id']);$id=researchInt($c['id']??0);
            $earlier=researchOne($pdo,'SELECT id FROM tblResearchStep WHERE id = ? AND idRoute = ? AND position < ?',[$id,$step['idRoute'],$step['position']]);
            if (!$earlier) throw new AetherResearchException(422,'Kies een eerdere stap uit dezelfde feedbackroute.');
            $result[]=['type'=>'step','id'=>$id];continue;
        }
        if ($type==='discoveries') {
            researchFields($c,['type','ids']);$ids=researchIds($c['ids']??null);
            if (!$ids) throw new AetherResearchException(422,'Kies minstens één discovery.');
            foreach ($ids as $id) {
                $other=researchDiscovery($pdo,$id);
                if ($id===(int)$d['id'] || (int)$other['idChallenge']!==(int)$d['idChallenge']) throw new AetherResearchException(422,'Kies andere discoveries uit deze challenge.');
            }
            $result[]=['type'=>$type,'ids'=>$ids];continue;
        }
        if (!in_array($type,['duration','absolute'],true) || isset($timers[$type])) throw new AetherResearchException(422,'Gebruik maximaal één tijdsduur en één vast tijdstip per stap.');
        $timers[$type]=true;$show=researchBool($c['showCountdown']??false);
        if ($type==='absolute') {
            researchFields($c,['type','times','showCountdown']);$times=$c['times']??[];$utc=[];
            if (!is_array($times)) throw new AetherResearchException(422,'Ongeldige tijdstippen.');
            $events=researchAll($pdo,'SELECT ce.idEvent,COALESCE(e.timeZone,\'Europe/Brussels\') AS timeZone FROM tblResearchChallengeEvent ce LEFT JOIN tblResearchEvent e ON e.idEvent = ce.idEvent WHERE ce.idChallenge = ?',[$d['idChallenge']]);
            $clean=[];foreach ($events as $event) {
                $local=researchText($times[$event['idEvent']]??'',16);$clean[(string)$event['idEvent']]=$local;
                $utc[(string)$event['idEvent']]=researchLocalToUtc($local,$event['timeZone']);
            }
            $result[]=['type'=>$type,'times'=>$clean,'utc'=>$utc,'showCountdown'=>$show];continue;
        }
        researchFields($c,['type','seconds','scope','discoveryId','stepId','action','showCountdown']);
        $seconds=researchInt($c['seconds']??0,1,31536000);$scope=$c['scope']??'';
        if (!in_array($scope,['shared','character'],true)) throw new AetherResearchException(422,'Kies gedeelde klok of per personage.');
        $source=researchDiscovery($pdo,researchInt($c['discoveryId']??0));$sourceStep=researchInt($c['stepId']??0,0);
        if ((int)$source['idChallenge']!==(int)$d['idChallenge']) throw new AetherResearchException(422,'De klok start door een handeling uit dezelfde challenge.');
        $action=$c['action']??'';
        if (!in_array($action,['scanned','shown','confirmed','step_completed','discovery_completed'],true)) throw new AetherResearchException(422,'Kies een geldige starthandeling.');
        if (in_array($action,['scanned','discovery_completed'],true)) {
            if ($sourceStep!==0 || ($action==='discovery_completed' && (int)$source['id']===(int)$d['id'])) throw new AetherResearchException(422,'Deze starthandeling zou op zichzelf wachten.');
        } else {
            $s=researchOne($pdo,'SELECT s.* FROM tblResearchStep s JOIN tblResearchRoute r ON r.id = s.idRoute WHERE s.id = ? AND r.idDiscovery = ?',[$sourceStep,$source['id']]);
            if (!$s || ($action==='confirmed' && !(int)$s['confirmationRequired'])) throw new AetherResearchException(422,'Kies een bestaande stap met de juiste starthandeling.');
            if ((int)$source['id']===(int)$d['id'] && ((int)$s['idRoute']!==(int)$step['idRoute'] || (int)$s['position']>=(int)$step['position'])) throw new AetherResearchException(422,'Een eigen klok kan alleen starten bij scannen of bij een eerdere stap in dezelfde route.');
        }
        $result[]=['type'=>$type,'seconds'=>$seconds,'scope'=>$scope,'discoveryId'=>(int)$source['id'],'stepId'=>$sourceStep,'action'=>$action,'showCountdown'=>$show];
    }
    return $result;
}
function researchValidateGraph(PDO $pdo): void {
    $edges=[];
    foreach (researchAll($pdo,'SELECT r.idDiscovery,s.conditionsJson FROM tblResearchStep s JOIN tblResearchRoute r ON r.id = s.idRoute') as $s) {
        foreach (json_decode($s['conditionsJson'],true,512,JSON_THROW_ON_ERROR) as $c) {
            if ($c['type']==='discoveries') foreach ($c['ids'] as $id) $edges[(int)$s['idDiscovery']][]=$id;
            if ($c['type']==='duration' && (int)$c['discoveryId']!==(int)$s['idDiscovery']) $edges[(int)$s['idDiscovery']][]=$c['discoveryId'];
        }
    }
    researchAssertAcyclic($edges);
}
function researchAdminSave(PDO $pdo,array $user,string $op,array $in): array {
    researchManager($user);$id=researchInt($in['id']??0,0);
    if ($op==='challenge_save') {
        researchFields($in,['id','revision','title','description','eventIds','active']);
        $title=researchText($in['title']??'');$html=researchHtml($in['description']??'');$ids=researchIds($in['eventIds']??null);
        if (!$ids) throw new AetherResearchException(422,'Koppel minstens één event.');
        foreach ($ids as $event) if (!researchOne($pdo,'SELECT id FROM tblEvent WHERE id = ?',[$event])) throw new AetherResearchException(422,'Event niet gevonden.');
        if ($id) {
            $row=researchOne($pdo,'SELECT * FROM tblResearchChallenge WHERE id = ?',[$id])??throw new AetherResearchException(404,'Challenge niet gevonden.');researchRevision($row,$in);
            $old=array_map('intval',researchQuery($pdo,'SELECT idEvent FROM tblResearchChallengeEvent WHERE idChallenge = ?',[$id])->fetchAll(PDO::FETCH_COLUMN));
            if (array_diff($ids,$old) || array_diff($old,$ids)) {
                // Absolute times and existing progress must not become detached from their event.
                foreach (researchAll($pdo,'SELECT id FROM tblResearchDiscovery WHERE idChallenge = ?',[$id]) as $d) {
                    researchUnused($pdo,(int)$d['id']);
                    foreach (researchRoutes($pdo,(int)$d['id']) as $r) foreach (researchSteps($pdo,(int)$r['id']) as $s) foreach ($s['conditions'] as $c)
                        if ($c['type']==='absolute') throw new AetherResearchException(409,'Verwijder eerst de vaste tijdsvoorwaarden voordat je events wijzigt.');
                }
            }
            researchQuery($pdo,'UPDATE tblResearchChallenge SET title=?,description=?,active=?,revision=revision+1 WHERE id=?',[$title,$html,(int)researchBool($in['active']??true),$id]);
        } else { researchQuery($pdo,'INSERT INTO tblResearchChallenge(title,description) VALUES (?,?)',[$title,$html]);$id=(int)$pdo->lastInsertId(); }
        researchQuery($pdo,'DELETE FROM tblResearchChallengeEvent WHERE idChallenge=?',[$id]);
        foreach ($ids as $event) researchQuery($pdo,'INSERT INTO tblResearchChallengeEvent(idChallenge,idEvent) VALUES (?,?)',[$id,$event]);
    } elseif ($op==='discovery_save') {
        researchFields($in,['id','revision','idChallenge','title','description','idSkill','bonusIds','active']);
        $title=researchText($in['title']??'');$html=researchHtml($in['description']??'');$skill=researchInt($in['idSkill']??0);$bonus=researchIds($in['bonusIds']??[]);
        if (!researchOne($pdo,'SELECT id FROM tblSkill WHERE id=?',[$skill])) throw new AetherResearchException(422,'Vaardigheid niet gevonden.');
        foreach ($bonus as $spec) researchSpec($pdo,$spec,$skill);
        if ($id) {
            $row=researchDiscovery($pdo,$id);researchRevision($row,$in);
            $old=array_map('intval',researchQuery($pdo,'SELECT idSpecialisation FROM tblResearchBonus WHERE idDiscovery=?',[$id])->fetchAll(PDO::FETCH_COLUMN));
            if ((int)$row['idSkill']!==$skill || array_diff($old,$bonus) || array_diff($bonus,$old)) researchUnused($pdo,$id);
            $active=researchBool($in['active']??false);
            researchEnsureDefaultFeedback($pdo,$id);
            researchQuery($pdo,'UPDATE tblResearchDiscovery SET title=?,description=?,idSkill=?,active=?,revision=revision+1 WHERE id=?',[$title,$html,$skill,(int)$active,$id]);
        } else {
            $challenge=researchInt($in['idChallenge']??0);
            if (!researchOne($pdo,'SELECT id FROM tblResearchChallenge WHERE id=?',[$challenge])) throw new AetherResearchException(422,'Challenge niet gevonden.');
            researchQuery($pdo,'INSERT INTO tblResearchDiscovery(idChallenge,title,description,idSkill,qrToken) VALUES (?,?,?,?,?)',[$challenge,$title,$html,$skill,bin2hex(random_bytes(32))]);$id=(int)$pdo->lastInsertId();
            for ($level=0;$level<=4;$level++) researchQuery($pdo,"INSERT INTO tblResearchRoute(idDiscovery,level,kind) VALUES (?,?,'general')",[$id,$level]);
            researchEnsureDefaultFeedback($pdo,$id);
            researchQuery($pdo,'UPDATE tblResearchDiscovery SET active=? WHERE id=?',[(int)researchBool($in['active']??true),$id]);
        }
        researchQuery($pdo,'DELETE FROM tblResearchBonus WHERE idDiscovery=?',[$id]);foreach ($bonus as $spec) researchQuery($pdo,'INSERT INTO tblResearchBonus(idDiscovery,idSpecialisation) VALUES (?,?)',[$id,$spec]);
    } elseif ($op==='route_save') {
        researchFields($in,['id','revision','idDiscovery','level','kind','idRequiredSpecialisation','priority','characterIds']);
        $d=researchDiscovery($pdo,researchInt($in['idDiscovery']??0));researchUnused($pdo,(int)$d['id']);
        $level=researchInt($in['level']??-1,0,4);$kind=$in['kind']??'';$required=researchInt($in['idRequiredSpecialisation']??0,0);$priority=0;$chars=researchIds($in['characterIds']??[]);
        $general=researchOne($pdo,"SELECT id FROM tblResearchRoute WHERE idDiscovery=? AND level=? AND kind='general'",[$d['id'],$level]);
        if (!$general || !researchSteps($pdo,(int)$general['id'],true)) throw new AetherResearchException(422,'Vul eerst de standaardfeedback van dit niveau in voordat je een variant toevoegt.');
        if (!in_array($kind,['character','specialisation'],true)) throw new AetherResearchException(422,'Kies personage- of specialisatievariant.');
        if ($required) researchSpec($pdo,$required,(int)$d['idSkill']);
        if (($kind==='specialisation' && (!$required || $chars)) || ($kind==='character' && !$chars)) throw new AetherResearchException(422,'Kies de vereiste specialisatie of de betrokken personages.');
        foreach ($chars as $char) if (!researchOne($pdo,'SELECT id FROM tblCharacter WHERE id=?',[$char])) throw new AetherResearchException(422,'Personage niet gevonden.');
        if ($id) {
            $row=researchOne($pdo,'SELECT * FROM tblResearchRoute WHERE id=? AND idDiscovery=?',[$id,$d['id']])??throw new AetherResearchException(404,'Variant niet gevonden.');researchRevision($row,$in);
            if ($row['kind']==='general') throw new AetherResearchException(422,'De algemene route blijft behouden.');
            researchQuery($pdo,'UPDATE tblResearchRoute SET level=?,kind=?,idRequiredSpecialisation=?,priority=?,revision=revision+1 WHERE id=?',[$level,$kind,$required?:null,$priority,$id]);
        } else {researchQuery($pdo,'INSERT INTO tblResearchRoute(idDiscovery,level,kind,idRequiredSpecialisation,priority) VALUES (?,?,?,?,?)',[$d['id'],$level,$kind,$required?:null,$priority]);$id=(int)$pdo->lastInsertId();}
        researchQuery($pdo,'DELETE FROM tblResearchRouteCharacter WHERE idRoute=?',[$id]);foreach ($chars as $char) researchQuery($pdo,'INSERT INTO tblResearchRouteCharacter(idRoute,idCharacter) VALUES (?,?)',[$id,$char]);
        researchValidateVariants(researchRoutes($pdo,(int)$d['id']));
        researchQuery($pdo,'UPDATE tblResearchDiscovery SET active=0,revision=revision+1 WHERE id=?',[$d['id']]);
    } elseif ($op==='step_save') {
        researchFields($in,['id','revision','idRoute','content','confirmationRequired','conditions','variant','followUp']);
        $route=researchOne($pdo,'SELECT * FROM tblResearchRoute WHERE id=?',[researchInt($in['idRoute']??0)])??throw new AetherResearchException(404,'Route niet gevonden.');$d=researchDiscovery($pdo,(int)$route['idDiscovery']);
        if (isset($in['variant'])) {
            if (!is_array($in['variant']) || $route['kind']==='general') throw new AetherResearchException(422,'Ongeldige feedbackvariant.');
            researchAdminSave($pdo,$user,'route_save',array_merge($in['variant'],['id'=>(int)$route['id'],'idDiscovery'=>(int)$d['id'],'level'=>(int)$route['level']]));
        }
        $html=researchHtml($in['content']??'');$confirm=researchBool($in['confirmationRequired']??false);
        if ($id) {$step=researchOne($pdo,'SELECT * FROM tblResearchStep WHERE id=? AND idRoute=?',[$id,$route['id']])??throw new AetherResearchException(404,'Stap niet gevonden.');researchRevision($step,$in);}
        else {researchUnused($pdo,(int)$d['id']);$step=['idRoute'=>$route['id'],'position'=>(int)(researchOne($pdo,'SELECT MAX(position) AS p FROM tblResearchStep WHERE idRoute=?',[$route['id']])['p']??0)+1];}
        if ($id && researchStepHasFeedback($pdo,$step)!==researchStepHasFeedback($pdo,array_merge($step,['content'=>$html]))) researchUnused($pdo,(int)$d['id']);
        $conditions=researchConditions($pdo,$d,$step,$in['conditions']??[]);$json=researchJson($conditions);
        if ($id) {
            if ((bool)$step['confirmationRequired']!==$confirm || researchJson(json_decode($step['conditionsJson'],true))!==$json) researchUnused($pdo,(int)$d['id']);
            researchQuery($pdo,'UPDATE tblResearchStep SET content=?,confirmationRequired=?,conditionsJson=?,revision=revision+1 WHERE id=?',[$html,(int)$confirm,$json,$id]);
        } else {researchQuery($pdo,'INSERT INTO tblResearchStep(idRoute,position,content,confirmationRequired,conditionsJson) VALUES (?,?,?,?,?)',[$route['id'],$step['position'],$html,(int)$confirm,$json]);$id=(int)$pdo->lastInsertId();}
        researchEnsureDefaultFeedback($pdo,(int)$d['id']);
        if (researchBool($in['followUp']??false)) {
            $position=(int)$step['position'];
            $next=researchOne($pdo,'SELECT id FROM tblResearchStep WHERE idRoute=? AND position>? ORDER BY position LIMIT 1',[$route['id'],$position]);
            if (!$next) {
                researchUnused($pdo,(int)$d['id']);
                researchQuery($pdo,'INSERT INTO tblResearchStep(idRoute,position,content,confirmationRequired,conditionsJson) VALUES (?,?,?,0,?)',[$route['id'],$position+1,'','[]']);
            }
        }
        researchValidateGraph($pdo);
    } elseif ($op==='timezone_save') {
        researchFields($in,['id','timeZone']);$zone=researchText($in['timeZone']??'',64);
        if (!in_array($zone,DateTimeZone::listIdentifiers(),true) || !researchOne($pdo,'SELECT id FROM tblEvent WHERE id=?',[$id])) throw new AetherResearchException(422,'Ongeldig event of tijdzone.');
        // Existing absolute instants retain their stored UTC meaning; editors show the saved zone.
        foreach (researchAll($pdo,'SELECT s.conditionsJson FROM tblResearchStep s JOIN tblResearchRoute r ON r.id=s.idRoute JOIN tblResearchDiscovery d ON d.id=r.idDiscovery JOIN tblResearchChallengeEvent ce ON ce.idChallenge=d.idChallenge WHERE ce.idEvent=?',[$id]) as $s)
            foreach (json_decode($s['conditionsJson'],true) as $c) if ($c['type']==='absolute') throw new AetherResearchException(409,'Er zijn al vaste tijdstippen voor dit event. Verwijder die eerst voordat je de tijdzone wijzigt.');
        researchQuery($pdo,'INSERT INTO tblResearchEvent(idEvent,timeZone) VALUES (?,?) ON DUPLICATE KEY UPDATE timeZone=VALUES(timeZone)',[$id,$zone]);
    } elseif ($op==='timer_adjust') {
        researchFields($in,['id','idEvent','idStep','scopeCharacter','localEnd','reason']);$event=researchInt($in['idEvent']??0);$step=researchInt($in['idStep']??0);$scope=researchInt($in['scopeCharacter']??0,0);researchText($in['reason']??'',1000);
        $zone=researchOne($pdo,'SELECT timeZone FROM tblResearchEvent WHERE idEvent=?',[$event])['timeZone']??'Europe/Brussels';$end=researchLocalToUtc(researchText($in['localEnd']??'',16),$zone);
        if (!researchOne($pdo,'SELECT idStep FROM tblResearchTimer WHERE idEvent=? AND idStep=? AND scopeCharacter=?',[$event,$step,$scope])) throw new AetherResearchException(404,'Klok niet gestart.');
        researchQuery($pdo,'UPDATE tblResearchTimer SET endsAt=? WHERE idEvent=? AND idStep=? AND scopeCharacter=?',[$end,$event,$step,$scope]);
    } elseif ($op==='media_link') {
        researchFields($in,['idStep','idMedia']);
        $step=researchOne($pdo,'SELECT s.*,r.idDiscovery FROM tblResearchStep s JOIN tblResearchRoute r ON r.id=s.idRoute WHERE s.id=?',[researchInt($in['idStep']??0)])??throw new AetherResearchException(404,'Stap niet gevonden.');
        if (!researchStepHasFeedback($pdo,$step)) researchUnused($pdo,(int)$step['idDiscovery']);
        $source=researchInt($in['idMedia']??0);
        if (!researchOne($pdo,'SELECT id FROM tblResearchMedia WHERE id=?',[$source])) throw new AetherResearchException(404,'Media niet gevonden.');
        researchQuery($pdo,'INSERT IGNORE INTO tblResearchMedia(idStep,filename,mime,byteSize,sha256,bytes) SELECT ?,filename,mime,byteSize,sha256,bytes FROM tblResearchMedia WHERE id=?',[$step['id'],$source]);
        $id=(int)$step['id'];
    } elseif ($op==='media_delete') {
        researchFields($in,['id']);
        $media=researchOne($pdo,'SELECT m.idStep,s.content,d.id AS idDiscovery FROM tblResearchMedia m JOIN tblResearchStep s ON s.id=m.idStep JOIN tblResearchRoute r ON r.id=s.idRoute JOIN tblResearchDiscovery d ON d.id=r.idDiscovery WHERE m.id=?',[$id]);
        if ($media && !researchTextHasContent($media['content']) && !researchOne($pdo,'SELECT id FROM tblResearchMedia WHERE idStep=? AND id<>? LIMIT 1',[$media['idStep'],$id])) researchUnused($pdo,(int)$media['idDiscovery']);
        researchQuery($pdo,'DELETE FROM tblResearchMedia WHERE id=?',[$id]);
        if ($media) researchEnsureDefaultFeedback($pdo,(int)$media['idDiscovery']);
    } else throw new AetherResearchException(400,'Onbekende beheeractie.');
    researchAudit($pdo,$user,$op,array_merge($in,['id'=>$id]));return ['id'=>$id,'saved'=>true];
}
