<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/traitUtils.php';
require_once __DIR__ . '/characterLanguageUtils.php';

function getCurrentUserRole(PDO $pdo): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionRole = $_SESSION['user']['role'] ?? 'participant';
    $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;

    if ($userId <= 0) {
        return $sessionRole;
    }

    try {
        $stmt = $pdo->prepare('SELECT role FROM tblUser WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $roleFromDb = $stmt->fetchColumn();
        if (is_string($roleFromDb) && $roleFromDb !== '') {
            return $roleFromDb;
        }
    } catch (Throwable $e) {
        // Fallback to session role below.
    }

    return $sessionRole;
}

function isPrivilegedUserRole(string $role): bool
{
    return $role === 'administrator' || $role === 'director';
}

function getVisibleAvailableStatusPoints(array $pointSummary, string $role): int
{
    if (isPrivilegedUserRole($role)) {
        return (int) ($pointSummary['maxStatusPoints'] ?? 0) - (int) ($pointSummary['usedStatusPoints'] ?? 0);
    }

    return (int) ($pointSummary['availableStatusPoints'] ?? 0);
}

function calculateHealthExperienceCost(array $character): int
{
    $physicalHealth = (int) ($character['physicalHealth'] ?? 0);
    $mentalHealth = (int) ($character['mentalHealth'] ?? 0);

    return ($physicalHealth + $mentalHealth) * 3;
}

function getCharacterSkillExperienceCost(PDO $pdo, int $idCharacter): int
{
    if ($idCharacter <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(
             CASE level
                 WHEN 1 THEN 1
                 WHEN 2 THEN 3
                 WHEN 3 THEN 6
                 ELSE 0
             END
         ),0)
         FROM tblLinkCharacterSkill
         WHERE idCharacter = ?'
    );
    $stmt->execute([$idCharacter]);
    $usedSkillExperience = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM tblCharacterSpecialisation AS cs
         JOIN tblSkillSpecialisation AS ss
           ON ss.id = cs.idSkillSpecialisation
         WHERE cs.idCharacter = ?
           AND (ss.kind IS NULL OR ss.kind <> 'discipline')"
    );
    $stmt->execute([$idCharacter]);
    $nonDisciplineSpecialisations = (int) $stmt->fetchColumn();

    $languageExperience = getCharacterLanguageExperienceCost($pdo, ['id' => $idCharacter]);

    return $usedSkillExperience + ($nonDisciplineSpecialisations * 2) + $languageExperience;
}

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

    $healthExperienceCost = $isPlayer ? calculateHealthExperienceCost($character) : 0;
    $usedSkillExperience = $isPlayer ? getCharacterSkillExperienceCost($pdo, (int) ($character['id'] ?? 0)) : 0;
    $baseStatusPoints = $isPlayer ? 8 : 0;
    $usedStatusPoints = 0;
    try {
        $linkedTraits = getCharacterTraitLinks($pdo, (int) ($character['id'] ?? 0));
        foreach ($linkedTraits as $linkedTrait) {
            $rankForCost = isset($linkedTrait['baseRank'])
                ? (int) $linkedTrait['baseRank']
                : (int) $linkedTrait['rank'];
            $usedStatusPoints += calculateTraitPointCost($linkedTrait, $rankForCost);
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
        'healthExperienceCost' => $healthExperienceCost,
        'usedSkillExperience' => $usedSkillExperience,
        'experienceBudget' => max(0, $totalExperience - $convertedExperience - $healthExperienceCost),
        'remainingExperience' => max(0, $totalExperience - $convertedExperience - $healthExperienceCost - $usedSkillExperience),
        'baseStatusPoints' => $baseStatusPoints,
        'usedStatusPoints' => $usedStatusPoints,
        'maxStatusPoints' => $maxStatusPoints,
        'availableStatusPoints' => $availableStatusPoints,
    ];
}
