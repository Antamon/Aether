<?php
declare(strict_types=1);
require_once __DIR__.'/researchRules.php';

function researchQuery(PDO $pdo,string $sql,array $params=[]): PDOStatement { $s=$pdo->prepare($sql);$s->execute($params);return $s; }
function researchAll(PDO $pdo,string $sql,array $params=[]): array { return researchQuery($pdo,$sql,$params)->fetchAll(PDO::FETCH_ASSOC); }
function researchOne(PDO $pdo,string $sql,array $params=[]): ?array { return researchQuery($pdo,$sql,$params)->fetch(PDO::FETCH_ASSOC) ?: null; }
function researchNow(): string { return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'); }
function researchJson(mixed $value): string { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function researchLock(PDO $pdo): void
{
    if (!researchOne($pdo,'SELECT id FROM tblResearchLock WHERE id = 1 FOR UPDATE')) throw new AetherResearchException(503,'Onderzoek is nog niet geïnstalleerd.');
}
function researchRoutes(PDO $pdo,int $discovery): array
{
    $routes=researchAll($pdo,"SELECT * FROM tblResearchRoute WHERE idDiscovery = ? ORDER BY level,CASE kind WHEN 'general' THEN 0 WHEN 'specialisation' THEN 1 ELSE 2 END,id",[$discovery]);
    foreach ($routes as &$r) {
        $r['characterIds']=array_map('intval',researchQuery($pdo,'SELECT idCharacter FROM tblResearchRouteCharacter WHERE idRoute = ? ORDER BY idCharacter',[$r['id']])->fetchAll(PDO::FETCH_COLUMN));
    }
    return $routes;
}
function researchStepHasFeedback(PDO $pdo,array $step): bool
{
    return researchTextHasContent((string)$step['content']) || (bool)researchOne($pdo,'SELECT id FROM tblResearchMedia WHERE idStep=? LIMIT 1',[$step['id']]);
}
function researchSteps(PDO $pdo,int $route,bool $filledOnly=false): array
{
    $steps=researchAll($pdo,'SELECT * FROM tblResearchStep WHERE idRoute = ? ORDER BY position,id',[$route]);
    foreach ($steps as &$s) $s['conditions']=json_decode($s['conditionsJson'],true,512,JSON_THROW_ON_ERROR);
    unset($s);
    if ($filledOnly) $steps=array_values(array_filter($steps,static fn(array $step):bool=>researchStepHasFeedback($pdo,$step)));
    return $steps;
}
function researchFeedbackRoutes(PDO $pdo,int $discovery): array
{
    $routes=researchRoutes($pdo,$discovery);
    foreach ($routes as &$route) $route['hasFeedback']=(bool)researchSteps($pdo,(int)$route['id'],true);
    return $routes;
}
/** Current selected branches; QR rescans replace this selection while keeping step progress. */
function researchFoundRoutes(PDO $pdo,int $character,array $found): array
{
    $selected=array_map('intval',researchQuery($pdo,"SELECT DISTINCT s.idRoute FROM tblResearchAction a JOIN tblResearchStep s ON s.id=a.idStep WHERE a.idEvent=? AND a.idCharacter=? AND a.idDiscovery=? AND a.actionCode='feedback_selected'",[$found['idEvent'],$character,$found['idDiscovery']])->fetchAll(PDO::FETCH_COLUMN));
    $routes=researchFeedbackRoutes($pdo,(int)$found['idDiscovery']);
    if ($selected) return array_values(array_filter($routes,static fn(array $route):bool=>in_array((int)$route['id'],$selected,true)));
    $owned=array_map('intval',researchQuery($pdo,"SELECT cs.idSkillSpecialisation FROM tblCharacterSpecialisation cs JOIN tblSkillSpecialisation sp ON sp.id=cs.idSkillSpecialisation AND sp.idSkill=cs.idSkill WHERE cs.idCharacter=? AND sp.kind='specialisation'",[$character])->fetchAll(PDO::FETCH_COLUMN));
    $matches=researchSelectRoutes($routes,(int)$found['effectiveLevel'],$character,$owned);
    $actor=researchOne($pdo,"SELECT actorUserId FROM tblResearchAction WHERE idEvent=? AND idCharacter=? AND idDiscovery=? AND actionCode IN ('scanned','conclusion_available') ORDER BY id LIMIT 1",[$found['idEvent'],$character,$found['idDiscovery']]);
    $actorId=(int)($actor['actorUserId']??researchOne($pdo,'SELECT idUser FROM tblCharacter WHERE id=?',[$character])['idUser']);
    foreach ($matches as $route) {
        $steps=researchSteps($pdo,(int)$route['id'],true);
        researchQuery($pdo,'INSERT IGNORE INTO tblResearchAction(idEvent,idCharacter,idDiscovery,idStep,actionCode,occurredAt,actorUserId) VALUES (?,?,?,?,?,?,?)',[$found['idEvent'],$character,$found['idDiscovery'],$steps[0]['id'],'feedback_selected',researchNow(),$actorId]);
    }
    return $matches;
}
function researchFoundSteps(PDO $pdo,int $character,array $found): array
{
    $steps=[];
    foreach (researchFoundRoutes($pdo,$character,$found) as $route) foreach (researchSteps($pdo,(int)$route['id'],true) as $step) $steps[]=$step;
    return $steps;
}
function researchEnsureDefaultFeedback(PDO $pdo,int $discovery): void
{
    $route=researchOne($pdo,"SELECT id FROM tblResearchRoute WHERE idDiscovery=? AND level=0 AND kind='general'",[$discovery]);
    if (!$route || researchSteps($pdo,(int)$route['id'],true)) return;
    $steps=researchSteps($pdo,(int)$route['id']);
    if ($steps) researchQuery($pdo,'UPDATE tblResearchStep SET content=?,revision=revision+1 WHERE id=?',[AETHER_RESEARCH_DEFAULT_FEEDBACK,$steps[0]['id']]);
    else researchQuery($pdo,'INSERT INTO tblResearchStep(idRoute,position,content,confirmationRequired,conditionsJson) VALUES (?,1,?,0,?)',[$route['id'],AETHER_RESEARCH_DEFAULT_FEEDBACK,'[]']);
}
function researchDiscovery(PDO $pdo,int $id): array
{
    return researchOne($pdo,'SELECT d.*, c.active AS challengeActive FROM tblResearchDiscovery d JOIN tblResearchChallenge c ON c.id = d.idChallenge WHERE d.id = ?',[$id])
        ?? throw new AetherResearchException(404,'Discovery niet gevonden.');
}
function researchAudit(PDO $pdo,array $user,string $action,array $details): void
{
    researchQuery($pdo,'INSERT INTO tblResearchAudit(actorUserId,actionCode,detailsJson,createdAt) VALUES (?,?,?,?)',[(int)$user['id'],$action,researchJson($details),researchNow()]);
}
