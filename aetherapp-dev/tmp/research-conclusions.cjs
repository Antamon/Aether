const fs=require('fs');let p='api/research/researchService.php',s=fs.readFileSync(p,'utf8');
const a=s.indexOf('    $existing=researchOne(',s.indexOf('function researchScan(')),b=s.indexOf("    return ['idDiscovery'",a);
let body=s.slice(a,b).replace("0,'scanned',researchNow()", "0,$registrationAction,researchNow()");
s=s.slice(0,a)+"    if (!empty($d['isConclusion'])) throw new AetherResearchException(403,'Conclusies worden automatisch beschikbaar na het afronden van de challenge.');\n    researchRegisterFound($pdo,$user,$event,$character,$d);\n"+s.slice(b);
const pos=s.indexOf('function researchAdvance(');
s=s.slice(0,pos)+`function researchRegisterFound(PDO $pdo,array $user,int $event,int $character,array $d,string $registrationAction='scanned'): void
{
`+body+`}
function researchConclusionReady(PDO $pdo,int $event,int $character,int $challenge): bool
{
    $counts=researchOne($pdo,'SELECT COUNT(*) AS total,COUNT(f.completedAt) AS completed FROM tblResearchDiscovery d LEFT JOIN tblResearchFound f ON f.idDiscovery=d.id AND f.idEvent=? AND f.idCharacter=? WHERE d.idChallenge=? AND d.isConclusion=0',[$event,$character,$challenge]);
    return (int)$counts['total']>0 && (int)$counts['total']===(int)$counts['completed'];
}
function researchUnlockConclusions(PDO $pdo,array $user,int $event,int $character): void
{
    // Reevaluate elapsed timers before deciding whether the complete challenge is done.
    foreach (researchAll($pdo,'SELECT f.* FROM tblResearchFound f JOIN tblResearchDiscovery d ON d.id=f.idDiscovery WHERE f.idEvent=? AND f.idCharacter=? AND d.isConclusion=0',[$event,$character]) as $found) researchState($pdo,$event,$character,$found,false);
    $conclusions=researchAll($pdo,'SELECT d.id FROM tblResearchDiscovery d JOIN tblResearchChallenge c ON c.id=d.idChallenge JOIN tblResearchChallengeEvent ce ON ce.idChallenge=c.id WHERE ce.idEvent=? AND d.isConclusion=1 AND d.active=1 AND c.active=1',[$event]);
    foreach ($conclusions as $row) {
        $d=researchDiscovery($pdo,(int)$row['id']);
        if (researchConclusionReady($pdo,$event,$character,(int)$d['idChallenge'])) researchRegisterFound($pdo,$user,$event,$character,$d,'conclusion_available');
    }
}
`+s.slice(pos);
s=s.replace("    $steps=researchFoundSteps($pdo,$character,$found);", "    if (!empty($d['isConclusion']) && !researchConclusionReady($pdo,$event,$character,(int)$d['idChallenge'])) return $base;\n    $steps=researchFoundSteps($pdo,$character,$found);");
// Only the state function owns $base/$d; don't add this guard inside completion helper.
s=s.replace("function researchFinishIfComplete(PDO $pdo,array $user,int $event,int $character,array $found,string $now): void\n{\n    if (!empty($d['isConclusion']) && !researchConclusionReady($pdo,$event,$character,(int)$d['idChallenge'])) return $base;", "function researchFinishIfComplete(PDO $pdo,array $user,int $event,int $character,array $found,string $now): void\n{");
s=s.replace("    $d=researchDiscovery($pdo,$id);researchDiscoveryEvent($pdo,$d,$event);", "    $d=researchDiscovery($pdo,$id);researchDiscoveryEvent($pdo,$d,$event);\n    if (!empty($d['isConclusion']) && !researchConclusionReady($pdo,$event,$character,(int)$d['idChallenge'])) throw new AetherResearchException(403,'Rond eerst alle gewone discoveries van deze challenge af.');");
fs.writeFileSync(p,s);
p='api/research/researchReadService.php';s=fs.readFileSync(p,'utf8').replace("        return ['discoveries'=>array_map", "        researchUnlockConclusions($pdo,$user,$event,$character);\n        return ['discoveries'=>array_map");fs.writeFileSync(p,s);
p='js/researchQrPdf.js';s=fs.readFileSync(p,'utf8').replace("        if (!challenge.discoveries?.length)", "        challenge={...challenge,discoveries:(challenge.discoveries||[]).filter(d=>!Number(d.isConclusion)&&d.qrToken)};\n        if (!challenge.discoveries?.length)");fs.writeFileSync(p,s);
