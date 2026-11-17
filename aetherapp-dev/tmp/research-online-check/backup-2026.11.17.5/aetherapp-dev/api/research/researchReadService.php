<?php
declare(strict_types=1);
require_once __DIR__.'/researchAdminService.php';
function researchRead(PDO $pdo,array $user,string $op,array $in): array {
    $character=researchInt($in['idCharacter']??0,0);$event=researchInt($in['idEvent']??0,0);
    if ($op==='events') {
        researchCharacter($pdo,$user,$character);
        $sql='SELECT e.id,e.title,e.dateStart,e.dateEnd,COALESCE(re.timeZone,\'Europe/Brussels\') AS timeZone FROM tblEvent e LEFT JOIN tblResearchEvent re ON re.idEvent=e.id';
        $params=[];
        if (!aetherIsPrivilegedRole((string)$user['role'])) {$sql.=' JOIN tblLinkEventCharacter ec ON ec.idEvent=e.id AND ec.idCharacter=?';$params[]=$character;}
        return ['events'=>researchAll($pdo,$sql.' ORDER BY e.dateStart DESC,e.id DESC',$params)];
    }
    if (in_array($op,['found','detail'],true)) {
        researchAccess($pdo,$user,$character,$event);
        if ($op==='detail') {
            $found=researchOne($pdo,'SELECT * FROM tblResearchFound WHERE idEvent=? AND idCharacter=? AND idDiscovery=?',[$event,$character,researchInt($in['idDiscovery']??0)])??throw new AetherResearchException(404,'Scan deze discovery eerst.');
            return researchState($pdo,$event,$character,$found);
        }
        return ['discoveries'=>array_map(fn($f)=>researchState($pdo,$event,$character,$f,false),researchAll($pdo,'SELECT * FROM tblResearchFound WHERE idEvent=? AND idCharacter=? ORDER BY foundAt,idDiscovery',[$event,$character]))];
    }
    researchManager($user);
    if ($op==='catalog') return [
        'events'=>researchAll($pdo,'SELECT e.id,e.title,e.dateStart,e.dateEnd,COALESCE(re.timeZone,\'Europe/Brussels\') AS timeZone FROM tblEvent e LEFT JOIN tblResearchEvent re ON re.idEvent=e.id ORDER BY e.dateStart DESC'),
        'skills'=>researchAll($pdo,'SELECT id,name FROM tblSkill ORDER BY name'),
        'specialisations'=>researchAll($pdo,"SELECT id,idSkill,name FROM tblSkillSpecialisation WHERE kind='specialisation' ORDER BY name"),
        'characters'=>researchAll($pdo,"SELECT id,TRIM(CONCAT_WS(' ',NULLIF(title,''),firstName,lastName)) AS name FROM tblCharacter ORDER BY lastName,firstName"),
        'timeZones'=>DateTimeZone::listIdentifiers(),
    ];
    if ($op==='challenges') {
        $rows=researchAll($pdo,'SELECT c.* FROM tblResearchChallenge c'.($event?' JOIN tblResearchChallengeEvent ce ON ce.idChallenge=c.id AND ce.idEvent=?':'').' ORDER BY c.title',$event?[$event]:[]);
        foreach ($rows as &$c) $c['eventIds']=array_map('intval',researchQuery($pdo,'SELECT idEvent FROM tblResearchChallengeEvent WHERE idChallenge=?',[$c['id']])->fetchAll(PDO::FETCH_COLUMN));
        return ['challenges'=>$rows];
    }
    if ($op==='challenge') {
        $c=researchOne($pdo,'SELECT * FROM tblResearchChallenge WHERE id=?',[researchInt($in['idChallenge']??0)])??throw new AetherResearchException(404,'Challenge niet gevonden.');
        $c['eventIds']=array_map('intval',researchQuery($pdo,'SELECT idEvent FROM tblResearchChallengeEvent WHERE idChallenge=?',[$c['id']])->fetchAll(PDO::FETCH_COLUMN));
        $c['discoveries']=researchAll($pdo,'SELECT * FROM tblResearchDiscovery WHERE idChallenge=? ORDER BY id',[$c['id']]);
        foreach ($c['discoveries'] as &$d) {
            $d['used']=(bool)researchOne($pdo,'SELECT idDiscovery FROM tblResearchFound WHERE idDiscovery=? LIMIT 1',[$d['id']]);
            $d['bonusIds']=array_map('intval',researchQuery($pdo,'SELECT idSpecialisation FROM tblResearchBonus WHERE idDiscovery=?',[$d['id']])->fetchAll(PDO::FETCH_COLUMN));
            $d['routes']=researchRoutes($pdo,(int)$d['id']);
            foreach ($d['routes'] as &$r) {
                $r['steps']=researchSteps($pdo,(int)$r['id']);
                foreach ($r['steps'] as &$s) {
                    unset($s['conditionsJson']);
                    $s['media']=researchAll($pdo,'SELECT id,filename,mime,byteSize FROM tblResearchMedia WHERE idStep=? ORDER BY id',[$s['id']]);
                    foreach ($s['media'] as &$m) $m['url']='api/research/media.php?id='.(int)$m['id'];unset($m);
                }unset($s);
            }unset($r);
        }unset($d);
        return $c;
    }
    if ($op==='progress') {
        $where=[];$params=[];
        foreach (['idEvent'=>'f.idEvent','idChallenge'=>'d.idChallenge','idDiscovery'=>'f.idDiscovery','idCharacter'=>'f.idCharacter'] as $field=>$column) {
            $id=researchInt($in[$field]??0,0);if ($id) {$where[]=$column.'=?';$params[]=$id;}
        }
        $rows=researchAll($pdo,"SELECT f.*,d.title AS discoveryTitle,ch.title AS challengeTitle,e.title AS eventTitle,TRIM(CONCAT_WS(' ',NULLIF(c.title,''),c.firstName,c.lastName)) AS characterName
            FROM tblResearchFound f JOIN tblResearchDiscovery d ON d.id=f.idDiscovery JOIN tblResearchChallenge ch ON ch.id=d.idChallenge JOIN tblCharacter c ON c.id=f.idCharacter JOIN tblEvent e ON e.id=f.idEvent".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY e.dateStart DESC,c.lastName,c.firstName,d.id',$params);
        $out=[];
        foreach ($rows as $row) {
            $state=researchState($pdo,(int)$row['idEvent'],(int)$row['idCharacter'],$row,false);$row['status']=$state['status'];
            if (($in['status']??'')!=='' && $in['status']!==$row['status']) continue;
            $row['steps']=[];
            foreach (researchSteps($pdo,(int)$row['idRoute'],true) as $s) {
                $p=researchOne($pdo,'SELECT shownAt,completedAt,confirmedAt FROM tblResearchStepProgress WHERE idEvent=? AND idCharacter=? AND idStep=?',[$row['idEvent'],$row['idCharacter'],$s['id']])??[];
                $row['steps'][]=array_merge(['id'=>$s['id'],'position'=>$s['position'],'confirmationRequired'=>(bool)$s['confirmationRequired'],'shownAt'=>null,'completedAt'=>null,'confirmedAt'=>null],$p);
            }
            $row['timers']=researchAll($pdo,'SELECT t.*,s.position,c.firstName,c.lastName FROM tblResearchTimer t JOIN tblResearchStep s ON s.id=t.idStep JOIN tblCharacter c ON c.id=t.idStartedByCharacter WHERE t.idEvent=? AND s.idRoute=? AND t.scopeCharacter IN (0,?)',[$row['idEvent'],$row['idRoute'],$row['idCharacter']]);
            $out[]=$row;
        }
        return ['progress'=>$out];
    }
    throw new AetherResearchException(400,'Onbekende leesactie.');
}
