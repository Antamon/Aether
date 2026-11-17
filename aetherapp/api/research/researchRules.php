<?php
declare(strict_types=1);

final class AetherResearchException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message) { parent::__construct($message); }
}
const AETHER_RESEARCH_DEFAULT_FEEDBACK = 'No additional information';
function researchTextHasContent(string $html): bool
{
    return preg_replace('/[\s\x{00a0}\x{200b}]+/u','',html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8')) !== '';
}
function researchLevel(int $base, array $owned, array $bonuses): int
{
    return min(4, max(0, min(3, $base)) + (array_intersect($owned, $bonuses) ? 1 : 0));
}
function researchSelectRoutes(array $routes, int $level, int $character, array $specialisations): array
{
    for ($candidate=$level;$candidate>=0;$candidate--) {
        $matches = array_values(array_filter($routes, static function(array $r) use ($candidate,$character,$specialisations): bool {
            if ((int)$r['level'] !== $candidate || (isset($r['hasFeedback']) && !$r['hasFeedback'])) return false;
            if ($r['idRequiredSpecialisation'] && !in_array((int)$r['idRequiredSpecialisation'],$specialisations,true)) return false;
            return $r['kind'] !== 'character' || in_array($character,$r['characterIds'],true);
        }));
        $rank = ['general'=>0,'specialisation'=>1,'character'=>2];
        usort($matches, static fn(array $a,array $b): int => ($rank[$a['kind']] <=> $rank[$b['kind']]) ?: ((int)$a['id'] <=> (int)$b['id']));
        if (!$matches) continue;
        return $matches;
    }
    throw new AetherResearchException(409,'Voor deze discovery is nog geen feedback beschikbaar.');
}
function researchSelectRoute(array $routes, int $level, int $character, array $specialisations): array
{
    return researchSelectRoutes($routes,$level,$character,$specialisations)[0];
}
function researchValidateVariants(array $routes): void
{
    foreach ($routes as $i=>$a) foreach (array_slice($routes,$i+1) as $b) {
        if ((int)$a['level'] === (int)$b['level'] && $a['kind'] === 'general' && $b['kind'] === 'general') {
            throw new AetherResearchException(422,'Gebruik één algemene feedbackroute per niveau.');
        }
    }
}
function researchAssertAcyclic(array $edges): void
{
    $visiting=[];$done=[];
    $visit = function(int $node) use (&$visit,&$visiting,&$done,$edges): void {
        if (isset($visiting[$node])) throw new AetherResearchException(422,'Deze voorwaarden maken een cirkel tussen discoveries.');
        if (isset($done[$node])) return;
        $visiting[$node]=true;
        foreach ($edges[$node] ?? [] as $next) $visit((int)$next);
        unset($visiting[$node]);$done[$node]=true;
    };
    foreach (array_keys($edges) as $node) $visit((int)$node);
}
/** Reject missing/ambiguous civil times instead of silently choosing a DST offset. */
function researchLocalToUtc(string $local, string $zone): string
{
    if (!in_array($zone,DateTimeZone::listIdentifiers(),true)) throw new AetherResearchException(422,'Ongeldige eventtijdzone.');
    $naive=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new DateTimeZone('UTC'));
    if (!$naive || $naive->format('Y-m-d\TH:i') !== $local) throw new AetherResearchException(422,'Vul een geldige datum en tijd in.');
    $tz=new DateTimeZone($zone);$candidates=[];
    foreach ($tz->getTransitions($naive->getTimestamp()-172800,$naive->getTimestamp()+172800) as $transition) {
        $stamp=$naive->getTimestamp()-(int)$transition['offset'];
        $date=(new DateTimeImmutable('@'.$stamp))->setTimezone($tz);
        if ($date->format('Y-m-d\TH:i') === $local) $candidates[$stamp]=true;
    }
    if (count($candidates)!==1) throw new AetherResearchException(422,'Dit lokale tijdstip bestaat niet of komt tweemaal voor door de zomer-/wintertijd. Kies een ondubbelzinnig tijdstip.');
    return gmdate('Y-m-d H:i:s',(int)array_key_first($candidates));
}
