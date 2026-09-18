<?php
declare(strict_types=1);

require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSkillRepository.php';

final class AetherCharacterSkillException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

/** @return array<string, mixed> */
function aetherUpdateCharacterSkill(PDO $pdo, int $characterId, int $skillId, string $action): array
{
    $skillLink = aetherFetchCharacterSkillLink($pdo, $characterId, $skillId);
    if ($skillLink === null) {
        return ['error' => 'Skill niet gevonden voor dit personage.'];
    }

    $character = aetherFetchCharacterSkillPointRecord($pdo, $characterId);
    if ($character === null) {
        return ['error' => 'Personage niet gevonden.'];
    }

    $level = (int) $skillLink['level'];
    $pointSummary = getCharacterPointSummary($pdo, $character);
    $isPlayer = (bool) $pointSummary['isPlayer'];
    $maxExperience = $pointSummary['experienceBudget'];
    $usedExperience = getCharacterSkillExperienceCost($pdo, $characterId);

    if ($action === 'up') {
        if ($level >= 3) {
            return ['error' => 'Maximum vaardigheidsniveau bereikt.'];
        }
        $cost = $level === 0 ? 1 : ($level === 1 ? 2 : 3);
        if ($isPlayer && $maxExperience !== null && ($usedExperience + $cost) > $maxExperience) {
            return ['error' => 'Onvoldoende ervaringspunten.'];
        }
        $level++;
    } elseif ($action === 'down') {
        if ($level <= 0) {
            return ['error' => 'Niveau is al 0.'];
        }
        $level--;
    }

    $pdo->beginTransaction();
    try {
        if ($action === 'delete') {
            aetherDeleteCharacterSkillSpecialisations($pdo, $characterId, $skillId);
            aetherDeleteCharacterSkillLink($pdo, $characterId, $skillId);
        } else {
            if ($action === 'down' && $level === 0) {
                aetherDeleteCharacterDisciplineSpecialisations($pdo, $characterId, $skillId);
            }
            aetherUpdateCharacterSkillLevel($pdo, $characterId, $skillId, $level);
        }

        $skills = aetherFetchCharacterSkillFeedbackRows($pdo, $characterId);
        $usedExperience = getCharacterSkillExperienceCost($pdo, $characterId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'skills' => $skills,
        'usedExperience' => $usedExperience,
        'maxExperience' => $maxExperience,
    ];
}

/** @return array<string, mixed> */
function aetherAddCharacterSkillSpecialisation(
    PDO $pdo,
    array $currentUser,
    int $characterId,
    int $skillId,
    int $specialisationId,
    string $name,
    ?string $kindFromClient
): array {
    $character = aetherFetchCharacterSkillPointRecord($pdo, $characterId);
    if ($character === null) {
        throw new AetherCharacterSkillException(500, 'Personage niet gevonden.');
    }

    $pointSummary = getCharacterPointSummary($pdo, $character);
    $isPlayer = (bool) $pointSummary['isPlayer'];
    $maxExperience = $pointSummary['experienceBudget'];
    $definitionMustBeCreated = false;

    if ($specialisationId > 0) {
        $specialisation = aetherFetchSkillSpecialisationForSkill($pdo, $specialisationId, $skillId);
        if ($specialisation === null) {
            throw new AetherCharacterSkillException(500, 'Onbekende specialisatie.');
        }
        $specialisationKind = (string) ($specialisation['kind'] ?: 'specialisation');
    } else {
        if ($name === '') {
            throw new AetherCharacterSkillException(500, 'Geen naam opgegeven voor nieuwe specialisatie.');
        }
        $specialisation = aetherFindSkillSpecialisationByName($pdo, $skillId, $name);
        if ($specialisation !== null) {
            $specialisationId = (int) $specialisation['id'];
            $specialisationKind = (string) ($specialisation['kind'] ?: 'specialisation');
        } else {
            $definitionMustBeCreated = true;
            $specialisationKind = $kindFromClient === 'discipline'
                && aetherIsPrivilegedRole((string) $currentUser['role'])
                ? 'discipline'
                : 'specialisation';
        }
    }

    $alreadyLinked = !$definitionMustBeCreated
        && aetherCharacterSkillSpecialisationIsLinked($pdo, $characterId, $skillId, $specialisationId);
    if ($isPlayer && $maxExperience !== null && $specialisationKind !== 'discipline' && !$alreadyLinked) {
        $usedExperience = getCharacterSkillExperienceCost($pdo, $characterId);
        if ($usedExperience + 2 > $maxExperience) {
            return ['error' => 'Onvoldoende ervaringspunten voor deze specialisatie.'];
        }
    }

    if ($alreadyLinked) {
        return ['success' => true];
    }

    $pdo->beginTransaction();
    try {
        if ($definitionMustBeCreated) {
            $specialisationId = aetherInsertSkillSpecialisationDefinition(
                $pdo,
                $skillId,
                $name,
                $specialisationKind
            );
        }
        aetherInsertCharacterSkillSpecialisation($pdo, $characterId, $skillId, $specialisationId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['success' => true];
}
