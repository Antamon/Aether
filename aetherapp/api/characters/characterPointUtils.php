<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/traitUtils.php';

function getCharacterPointSummary(PDO $pdo, array $character): array
{
    $isPlayer = ($character['type'] ?? '') === 'player';
    $idUser = (int) ($character['idUser'] ?? 0);
    $convertedExperience = max(0, min(6, (int) ($character['experienceToTrait'] ?? 0)));

    $totalExperience = 0;
    if ($isPlayer) {
        $totalExperience = 15;

        if ($idUser > 0) {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(e.ep), 0) AS total
                 FROM tblLinkEventUser AS leu
                 JOIN tblEvent AS e
                       ON leu.idEvent = e.id
                 WHERE leu.idUser = ?'
            );
            $stmt->execute([$idUser]);
            $totalExperience += (int) $stmt->fetchColumn();
        }
    }

    $baseStatusPoints = $isPlayer ? 8 : 0;
    $usedStatusPoints = 0;
    try {
        $linkedTraits = getCharacterTraitLinks($pdo, (int) ($character['id'] ?? 0));
        foreach ($linkedTraits as $linkedTrait) {
            $usedStatusPoints += calculateTraitPointCost($linkedTrait, (int) $linkedTrait['rank']);
        }
    } catch (Throwable $e) {
        $usedStatusPoints = 0;
    }
    $maxStatusPoints = $baseStatusPoints + ($convertedExperience * 2);
    $availableStatusPoints = max(0, $maxStatusPoints - $usedStatusPoints);

    return [
        'isPlayer' => $isPlayer,
        'totalExperience' => $totalExperience,
        'experienceToTrait' => $convertedExperience,
        'experienceBudget' => max(0, $totalExperience - $convertedExperience),
        'baseStatusPoints' => $baseStatusPoints,
        'usedStatusPoints' => $usedStatusPoints,
        'maxStatusPoints' => $maxStatusPoints,
        'availableStatusPoints' => $availableStatusPoints,
    ];
}
