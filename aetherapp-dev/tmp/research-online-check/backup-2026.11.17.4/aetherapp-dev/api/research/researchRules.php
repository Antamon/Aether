<?php
declare(strict_types=1);

final class AetherResearchException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message) { parent::__construct($message); }
}
function researchLevel(int $base, array $owned, array $bonuses): int
{
    return min(4, max(0, min(3, $base)) + (array_intersect($owned, $bonuses) ? 1 : 0));
}
function researchSelectRoute(array $routes, int $level, int $character, array $specialisations): array
{
    $matches = array_values(array_filter($routes, static function(array $r) use ($level,$character,$specialisations): bool {
        if ((int)$r['level'] !== $level) return false;
        if ($r['idRequiredSpecialisation'] && !in_array((int)$r['idRequiredSpecialisation'],$specialisations,true)) return false;
        return $r['kind'] !== 'character' || in_array($character,$r['characterIds'],true);
    }));
    $rank = ['general'=>0,'specialisation'=>1,'character'=>2];
    usort($matches, static fn(array $a,array $b): int => ($rank[$b['kind']] <=> $rank[$a['kind']]) ?: ((int)$b['priority'] <=> (int)$a['priority']));
    if (!$matches) throw new AetherResearchException(409,'Voor deze discovery is nog geen feedback beschikbaar.');
    if (isset($matches[1]) && $rank[$matches[0]['kind']] === $rank[$matches[1]['kind']] && (int)$matches[0]['priority'] === (int)$matches[1]['priority']) {
        throw new AetherResearchException(409,'Er zijn conflicterende feedbackvarianten. Vraag een spelleider om hulp.');
    }
    return $matches[0];
}
function researchValidateVariants(array $routes): void
{
    foreach ($routes as $i=>$a) foreach (array_slice($routes,$i+1) as $b) {
        if ((int)$a['level'] !== (int)$b['level'] || $a['kind'] !== $b['kind']) continue;
        if ($a['kind'] === 'general'
            || ($a['kind'] === 'specialisation' && (int)$a['priority'] === (int)$b['priority'])
            || ($a['kind'] === 'character' && array_intersect($a['characterIds'],$b['characterIds']))) {
            throw new AetherResearchException(422,'Conflicterende varianten: één algemene route, unieke specialisatieprioriteiten en maximaal één personagevariant per personage en niveau.');
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
