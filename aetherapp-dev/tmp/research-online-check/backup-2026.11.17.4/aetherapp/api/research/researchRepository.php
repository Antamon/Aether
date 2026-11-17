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
    $routes=researchAll($pdo,'SELECT * FROM tblResearchRoute WHERE idDiscovery = ? ORDER BY level,kind,priority DESC,id',[$discovery]);
    foreach ($routes as &$r) {
        $r['characterIds']=array_map('intval',researchQuery($pdo,'SELECT idCharacter FROM tblResearchRouteCharacter WHERE idRoute = ? ORDER BY idCharacter',[$r['id']])->fetchAll(PDO::FETCH_COLUMN));
    }
    return $routes;
}
function researchSteps(PDO $pdo,int $route): array
{
    $steps=researchAll($pdo,'SELECT * FROM tblResearchStep WHERE idRoute = ? ORDER BY position,id',[$route]);
    foreach ($steps as &$s) $s['conditions']=json_decode($s['conditionsJson'],true,512,JSON_THROW_ON_ERROR);
    return $steps;
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
