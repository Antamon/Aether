<?php
declare(strict_types=1);
require_once __DIR__.'/../characters/characterAccess.php';
require_once __DIR__.'/researchRepository.php';
function researchManager(array $user): void
{
    if (!aetherIsPrivilegedRole((string)($user['role']??''))) throw new AetherResearchException(403,'Alleen admins en directors kunnen Onderzoek beheren.');
}
function researchCharacter(PDO $pdo,array $user,int $character): array
{
    $c=aetherFetchCharacterAccessRecord($pdo,$character);
    if (!$c || !aetherCanViewCharacter($user,$c)) throw new AetherResearchException(403,'Je hebt geen toegang tot dit personage.');
    if (!aetherIsPrivilegedRole((string)$user['role']) && (!in_array($c['type'],['player','extra'],true) || $c['state']!=='active')) {
        throw new AetherResearchException(403,'Onderzoek kan alleen met je actieve spelerspersonage of figurantenrol.');
    }
    return $c;
}
function researchAccess(PDO $pdo,array $user,int $character,int $event): array
{
    $c=researchCharacter($pdo,$user,$character);
    if (!researchOne($pdo,'SELECT id FROM tblEvent WHERE id = ?',[$event])) throw new AetherResearchException(404,'Event niet gevonden.');
    if (!aetherIsPrivilegedRole((string)$user['role']) && !researchOne($pdo,
        'SELECT idCharacter FROM tblLinkEventCharacter WHERE idEvent = ? AND idCharacter = ?',[$event,$character])) {
        throw new AetherResearchException(403,'Dit personage is niet gekoppeld aan het gekozen event.');
    }
    return $c;
}
function researchDiscoveryEvent(PDO $pdo,array $d,int $event): void
{
    if (!(int)$d['active'] || !(int)$d['challengeActive']) throw new AetherResearchException(409,'Deze onderzoekscode is niet actief.');
    if (!researchOne($pdo,'SELECT idEvent FROM tblResearchChallengeEvent WHERE idChallenge = ? AND idEvent = ?',[$d['idChallenge'],$event])) {
        throw new AetherResearchException(409,'Deze discovery hoort niet bij het gekozen event.');
    }
}
