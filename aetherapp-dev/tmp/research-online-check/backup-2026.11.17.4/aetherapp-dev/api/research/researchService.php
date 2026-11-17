<?php
declare(strict_types=1);
require_once __DIR__.'/researchAccess.php';

function researchTimer(PDO $pdo,int $event,int $character,array $step,array $condition): ?array
{
    $scope=$condition['scope']==='shared' ? 0 : $character;
    $timer=researchOne($pdo,'SELECT * FROM tblResearchTimer WHERE idEvent = ? AND idStep = ? AND scopeCharacter = ?',[$event,$step['id'],$scope]);
    if ($timer) return $timer;
    $params=[$event,$condition['discoveryId'],$condition['stepId'],$condition['action']];
    $where=$scope ? ' AND idCharacter = ?' : '';
    if ($scope) $params[]=$character;
    $action=researchOne($pdo,'SELECT * FROM tblResearchAction WHERE idEvent = ? AND idDiscovery = ? AND idStep = ? AND actionCode = ?'.$where.' ORDER BY occurredAt,id LIMIT 1',$params);
    if (!$action) return null;
    $end=(new DateTimeImmutable($action['occurredAt'],new DateTimeZone('UTC')))->modify('+'.$condition['seconds'].' seconds')->format('Y-m-d H:i:s.u');
    researchQuery($pdo,'INSERT IGNORE INTO tblResearchTimer(idEvent,idStep,scopeCharacter,idStartedByCharacter,idStartAction,startedAt,endsAt) VALUES (?,?,?,?,?,?,?)',
        [$event,$step['id'],$scope,$action['idCharacter'],$action['id'],$action['occurredAt'],$end]);
    return researchOne($pdo,'SELECT * FROM tblResearchTimer WHERE idEvent = ? AND idStep = ? AND scopeCharacter = ?',[$event,$step['id'],$scope]);
}
function researchGate(PDO $pdo,int $event,int $character,array $step,string $now): array
{
    $ready=true;$timed=false;$waitingTime=false;$countdown=null;
    foreach ($step['conditions'] as $c) {
        if ($c['type']==='discoveries') {
            foreach ($c['ids'] as $id) {
                $row=researchOne($pdo,'SELECT completedAt FROM tblResearchFound WHERE idEvent = ? AND idCharacter = ? AND idDiscovery = ?',[$event,$character,$id]);
                if (!$row || !$row['completedAt']) $ready=false;
            }
        } elseif ($c['type']==='step') {
            $row=researchOne($pdo,'SELECT completedAt FROM tblResearchStepProgress WHERE idEvent = ? AND idCharacter = ? AND idStep = ?',[$event,$character,$c['id']]);
            if (!$row || !$row['completedAt']) $ready=false;
        } elseif (in_array($c['type'],['duration','absolute'],true)) {
            $timed=true;
            $end=$c['type']==='duration' ? (researchTimer($pdo,$event,$character,$step,$c)['endsAt']??null) : ($c['utc'][(string)$event]??null);
            if (!$end || $end>$now) { $ready=false;$waitingTime=true; }
            if ($c['showCountdown'] && $end) {
                $remaining=max(0,(new DateTimeImmutable($end,new DateTimeZone('UTC')))->getTimestamp()-(new DateTimeImmutable($now,new DateTimeZone('UTC')))->getTimestamp());
                $countdown=max($countdown??0,$remaining);
            }
        }
    }
    return ['ready'=>$ready,'timed'=>$timed,'waitingTime'=>$waitingTime,'countdownSeconds'=>$countdown];
}
function researchStepContent(PDO $pdo,array $step,int $event,int $character,array $progress=[]): array
{
    $media=researchAll($pdo,'SELECT id,mime,filename FROM tblResearchMedia WHERE idStep = ? ORDER BY id',[$step['id']]);
    foreach ($media as &$m) {
        $m['url']='api/research/media.php?id='.(int)$m['id'].'&idEvent='.$event.'&idCharacter='.$character;
        unset($m['id']);
    }
    return ['id'=>(int)$step['id'],'position'=>(int)$step['position'],'content'=>$step['content'],
        'confirmationRequired'=>(bool)$step['confirmationRequired'],'shownAt'=>$progress['shownAt']??null,
        'completedAt'=>$progress['completedAt']??null,'confirmedAt'=>$progress['confirmedAt']??null,'media'=>$media,
        'timed'=>(bool)array_filter($step['conditions'],static fn(array $c):bool=>in_array($c['type'],['duration','absolute'],true))];
}
/** Projection deliberately excludes route, level, conditions and undisclosed media. */
function researchState(PDO $pdo,int $event,int $character,array $found,bool $includeContent=true): array
{
    $d=researchDiscovery($pdo,(int)$found['idDiscovery']);
    $base=['id'=>(int)$d['id'],'title'=>$d['title'],'status'=>'unavailable','timed'=>false,'countdownSeconds'=>null,'current'=>null,'history'=>[]];
    try { researchDiscoveryEvent($pdo,$d,$event); } catch(AetherResearchException $e) { return $base; }
    $steps=researchSteps($pdo,(int)$found['idRoute']);
    if (!$steps) return $base;
    foreach ($steps as $step) {
        $p=researchOne($pdo,'SELECT * FROM tblResearchStepProgress WHERE idEvent = ? AND idCharacter = ? AND idStep = ?',[$event,$character,$step['id']])??[];
        if (!empty($p['completedAt'])) {
            if ($includeContent && !empty($p['shownAt'])) $base['history'][]=researchStepContent($pdo,$step,$event,$character,$p);
            continue;
        }
        $gate=researchGate($pdo,$event,$character,$step,researchNow());
        $base['timed']=$gate['timed'];$base['countdownSeconds']=$gate['countdownSeconds'];
        if (!$gate['ready']) { $base['status']=$gate['waitingTime']?'waiting_time':'waiting';return $base; }
        $base['status']=!empty($p['shownAt']) && (bool)$step['confirmationRequired'] ? 'awaiting_confirmation' : 'new_feedback';
        if ($includeContent) $base['current']=researchStepContent($pdo,$step,$event,$character,$p);
        return $base;
    }
    $base['status']='completed';
    return $base;
}
function researchRecordAction(PDO $pdo,array $user,int $event,int $character,int $discovery,int $step,string $action,string $now): void
{
    researchQuery($pdo,'INSERT IGNORE INTO tblResearchAction(idEvent,idCharacter,idDiscovery,idStep,actionCode,occurredAt,actorUserId) VALUES (?,?,?,?,?,?,?)',
        [$event,$character,$discovery,$step,$action,$now,$user['id']]);
    // Materialize clocks even if nobody has opened the target discovery yet.
    $targets=researchAll($pdo,'SELECT s.* FROM tblResearchStep s JOIN tblResearchRoute r ON r.id = s.idRoute JOIN tblResearchDiscovery d ON d.id = r.idDiscovery
        JOIN tblResearchChallengeEvent ce ON ce.idChallenge = d.idChallenge WHERE ce.idEvent = ?',[$event]);
    foreach ($targets as $target) foreach (json_decode($target['conditionsJson'],true,512,JSON_THROW_ON_ERROR) as $condition) {
        if ($condition['type']==='duration' && (int)$condition['discoveryId']===$discovery && (int)$condition['stepId']===$step && $condition['action']===$action) {
            researchTimer($pdo,$event,$character,$target,$condition);
        }
    }
}
function researchFinishIfComplete(PDO $pdo,array $user,int $event,int $character,array $found,string $now): void
{
    foreach (researchSteps($pdo,(int)$found['idRoute']) as $step) {
        $p=researchOne($pdo,'SELECT completedAt FROM tblResearchStepProgress WHERE idEvent = ? AND idCharacter = ? AND idStep = ?',[$event,$character,$step['id']]);
        if (!$p || !$p['completedAt']) return;
    }
    if (!researchSteps($pdo,(int)$found['idRoute'])) return;
    researchQuery($pdo,'UPDATE tblResearchFound SET completedAt = COALESCE(completedAt,?) WHERE idEvent = ? AND idCharacter = ? AND idDiscovery = ?',[$now,$event,$character,$found['idDiscovery']]);
    researchRecordAction($pdo,$user,$event,$character,(int)$found['idDiscovery'],0,'discovery_completed',$now);
}
function researchScan(PDO $pdo,array $user,array $input): array
{
    $event=(int)$input['idEvent'];$character=(int)$input['idCharacter'];
    researchAccess($pdo,$user,$character,$event);
    $token=trim((string)$input['code']);
    if (str_starts_with($token,'aether-research:')) $token=substr($token,16);
    if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new AetherResearchException(422,'Dit is geen geldige Aether-onderzoekscode.');
    $row=researchOne($pdo,'SELECT id FROM tblResearchDiscovery WHERE qrToken = ?',[$token]);
    if (!$row) throw new AetherResearchException(404,'Deze onderzoekscode bestaat niet.');
    $d=researchDiscovery($pdo,(int)$row['id']);researchDiscoveryEvent($pdo,$d,$event);
    $existing=researchOne($pdo,'SELECT * FROM tblResearchFound WHERE idEvent = ? AND idCharacter = ? AND idDiscovery = ?',[$event,$character,$d['id']]);
    if (!$existing) {
        $base=researchOne($pdo,'SELECT level FROM tblLinkCharacterSkill WHERE idCharacter = ? AND idSkill = ?',[$character,$d['idSkill']]);
        $owned=array_map('intval',researchQuery($pdo,'SELECT cs.idSkillSpecialisation FROM tblCharacterSpecialisation cs
            JOIN tblSkillSpecialisation sp ON sp.id = cs.idSkillSpecialisation AND sp.idSkill = cs.idSkill WHERE cs.idCharacter = ? AND sp.kind = \'specialisation\'',[$character])->fetchAll(PDO::FETCH_COLUMN));
        $bonus=array_map('intval',researchQuery($pdo,'SELECT idSpecialisation FROM tblResearchBonus WHERE idDiscovery = ?',[$d['id']])->fetchAll(PDO::FETCH_COLUMN));
        $level=researchLevel((int)($base['level']??0),$owned,$bonus);
        $route=researchSelectRoute(researchRoutes($pdo,(int)$d['id']),$level,$character,$owned);
        researchQuery($pdo,'INSERT INTO tblResearchFound(idEvent,idCharacter,idDiscovery,idRoute,effectiveLevel,foundAt) VALUES (?,?,?,?,?,?)',[$event,$character,$d['id'],$route['id'],$level,researchNow()]);
    }
    researchRecordAction($pdo,$user,$event,$character,(int)$d['id'],0,'scanned',researchNow());
    return ['idDiscovery'=>(int)$d['id']];
}
function researchAdvance(PDO $pdo,array $user,array $input,string $action,bool $override=false): array
{
    $event=(int)$input['idEvent'];$character=(int)$input['idCharacter'];$id=(int)$input['idDiscovery'];$stepId=(int)$input['idStep'];
    researchAccess($pdo,$user,$character,$event);
    if ($override) researchManager($user);
    $found=researchOne($pdo,'SELECT * FROM tblResearchFound WHERE idEvent = ? AND idCharacter = ? AND idDiscovery = ?',[$event,$character,$id]);
    if (!$found) throw new AetherResearchException(404,'Scan deze discovery eerst.');
    $d=researchDiscovery($pdo,$id);researchDiscoveryEvent($pdo,$d,$event);
    $step=researchOne($pdo,'SELECT * FROM tblResearchStep WHERE id = ? AND idRoute = ?',[$stepId,$found['idRoute']]);
    if (!$step) throw new AetherResearchException(403,'Deze feedbackstap is niet beschikbaar voor dit personage.');
    $progress=researchOne($pdo,'SELECT * FROM tblResearchStepProgress WHERE idEvent = ? AND idCharacter = ? AND idStep = ?',[$event,$character,$stepId])??[];
    if (!empty($progress['completedAt'])) return ['saved'=>true];
    foreach (researchSteps($pdo,(int)$found['idRoute']) as $earlier) {
        if ((int)$earlier['position'] >= (int)$step['position']) break;
        $p=researchOne($pdo,'SELECT completedAt FROM tblResearchStepProgress WHERE idEvent = ? AND idCharacter = ? AND idStep = ?',[$event,$character,$earlier['id']]);
        if (!$p || !$p['completedAt']) throw new AetherResearchException(409,'Rond eerst de vorige feedbackstap af.');
    }
    $step['conditions']=json_decode($step['conditionsJson'],true,512,JSON_THROW_ON_ERROR);
    $now=researchNow();
    if (!$override && !researchGate($pdo,$event,$character,$step,$now)['ready']) throw new AetherResearchException(409,'Deze feedback is nog niet beschikbaar.');
    if ($action==='confirm' && !$override && (!(bool)$step['confirmationRequired'] || empty($progress['shownAt']))) {
        throw new AetherResearchException(409,'Bekijk eerst de opdracht voordat je haar bevestigt.');
    }
    $complete=$override || $action==='confirm' || !(bool)$step['confirmationRequired'];
    // An AD override is not proof that the SF actually saw or confirmed the feedback.
    $shown=$override ? ($progress['shownAt']??null) : ($progress['shownAt']??$now);
    $confirmed=$action==='confirm' && !$override ? $now : ($progress['confirmedAt']??null);
    if (!$progress) researchQuery($pdo,'INSERT INTO tblResearchStepProgress(idEvent,idCharacter,idStep,shownAt,completedAt,confirmedAt) VALUES (?,?,?,?,?,?)',[$event,$character,$stepId,$shown,$complete?$now:null,$confirmed]);
    else researchQuery($pdo,'UPDATE tblResearchStepProgress SET shownAt = ?, completedAt = ?, confirmedAt = ? WHERE idEvent = ? AND idCharacter = ? AND idStep = ?',[$shown,$complete?$now:null,$confirmed,$event,$character,$stepId]);
    if (!$override) researchRecordAction($pdo,$user,$event,$character,$id,$stepId,'shown',$shown);
    if ($confirmed) researchRecordAction($pdo,$user,$event,$character,$id,$stepId,'confirmed',$confirmed);
    if ($complete) researchRecordAction($pdo,$user,$event,$character,$id,$stepId,'step_completed',$now);
    if ($override) researchAudit($pdo,$user,'progress_override',$input);
    researchFinishIfComplete($pdo,$user,$event,$character,$found,$now);
    return ['saved'=>true];
}
function researchMediaPermission(PDO $pdo,array $user,int $media,int $event,int $character): array
{
    $m=researchOne($pdo,'SELECT m.id,m.idStep,m.filename,m.mime,m.byteSize,r.idDiscovery,r.id AS idRoute
        FROM tblResearchMedia m JOIN tblResearchStep s ON s.id = m.idStep JOIN tblResearchRoute r ON r.id = s.idRoute WHERE m.id = ?',[$media]);
    if (!$m) throw new AetherResearchException(404,'Media niet beschikbaar.');
    if (aetherIsPrivilegedRole((string)$user['role']) && $character===0) return $m;
    researchAccess($pdo,$user,$character,$event);
    $found=researchOne($pdo,'SELECT * FROM tblResearchFound WHERE idEvent = ? AND idCharacter = ? AND idDiscovery = ? AND idRoute = ?',[$event,$character,$m['idDiscovery'],$m['idRoute']]);
    if (!$found) throw new AetherResearchException(404,'Media niet beschikbaar.');
    $state=researchState($pdo,$event,$character,$found);
    $allowed=array_column($state['history'],'id');if ($state['current']) $allowed[]=$state['current']['id'];
    if (!in_array((int)$m['idStep'],$allowed,true)) throw new AetherResearchException(404,'Media niet beschikbaar.');
    return $m;
}
