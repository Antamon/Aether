<?php
declare(strict_types=1);

require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSkillActionUtils.php';

final class AetherCharacterActionException extends RuntimeException
{
    public function __construct(
        private int $httpStatus,
        string $message,
        private bool $includeNullDetails = false
    )
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function includesNullDetails(): bool
    {
        return $this->includeNullDetails;
    }
}

/** @return array<string, mixed> */
function aetherRequireCharacterActionAccess(PDO $pdo, array $currentUser, int $characterId, string $deniedMessage): array
{
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if ($character === null) {
        throw new AetherCharacterActionException(404, 'Personage niet gevonden.');
    }
    if (!aetherCanViewCharacter($currentUser, $character)) {
        throw new AetherCharacterActionException(403, $deniedMessage);
    }

    return $character;
}

/** @return list<array<string, mixed>> */
function aetherFilterVisibleCharacterActionGroups(PDO $pdo, array $currentUser, array $groups): array
{
    if (aetherIsPrivilegedRole((string) $currentUser['role'])) {
        return $groups;
    }

    $visibleGroups = [];
    foreach ($groups as $group) {
        $visibleSkills = [];
        foreach ((array) ($group['skills'] ?? []) as $skill) {
            if (aetherCanManageSkill($pdo, $currentUser, (int) ($skill['idSkill'] ?? 0))) {
                $visibleSkills[] = $skill;
            }
        }
        if ($visibleSkills === []) {
            continue;
        }
        $group['skills'] = $visibleSkills;
        $visibleGroups[] = $group;
    }

    return $visibleGroups;
}

/** @return array<string, mixed> */
function aetherBuildCharacterActionCatalog(PDO $pdo, array $currentUser, int $characterId): array
{
    return [
        'events' => fetchCharacterActionEvents($pdo),
        'worldKnowledgeLevel' => getCharacterSkillLevelByIdForGossip(
            $pdo,
            $characterId,
            AETHER_WORLD_KNOWLEDGE_SKILL_ID
        ),
        'psiBurn' => getCharacterPsiBurn($pdo, $characterId),
        'actions' => aetherFilterVisibleCharacterActionGroups(
            $pdo,
            $currentUser,
            buildCharacterPsiActionGroups($pdo, $characterId)
        ),
    ];
}

/** @return array<string, mixed> */
function aetherBuildCharacterKnowledgeTargets(PDO $pdo, int $characterId, int $eventId): array
{
    $worldKnowledgeLevel = getCharacterSkillLevelByIdForGossip(
        $pdo,
        $characterId,
        AETHER_WORLD_KNOWLEDGE_SKILL_ID
    );

    return [
        'worldKnowledgeLevel' => $worldKnowledgeLevel,
        'attemptCount' => getCharacterEventGossipAttemptCount($pdo, $characterId, $eventId),
        'targets' => fetchVisibleKnowledgeTargetsForViewer($pdo, $characterId, $eventId, $worldKnowledgeLevel),
    ];
}

/** @return array<string, mixed> */
function aetherRevealCharacterActionKnowledge(
    PDO $pdo,
    int $characterId,
    int $eventId,
    int $sourceCharacterId
): array {
    $worldKnowledgeLevel = getCharacterSkillLevelByIdForGossip(
        $pdo,
        $characterId,
        AETHER_WORLD_KNOWLEDGE_SKILL_ID
    );
    if ($worldKnowledgeLevel <= 0) {
        throw new AetherCharacterActionException(403, 'Dit personage bezit Wereldwijs niet.');
    }

    $pdo->beginTransaction();
    try {
        $result = revealKnowledgeGossip(
            $pdo,
            $characterId,
            $eventId,
            $sourceCharacterId,
            $worldKnowledgeLevel
        );
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException && !$e instanceof PDOException) {
            throw new AetherCharacterActionException(400, $e->getMessage(), true);
        }
        throw $e;
    }
}

/** @return array<string, mixed> */
function aetherUseCharacterSkillAction(
    PDO $pdo,
    array $currentUser,
    array $character,
    int $eventId,
    int $skillId,
    string $actionCode,
    string $actionSubtype,
    bool $clearBurn
): array {
    if ($actionCode !== AETHER_SKILL_ACTION_CODE_PSI) {
        throw new AetherCharacterActionException(400, 'Deze actiecode wordt nog niet ondersteund.');
    }
    if ($actionSubtype === '') {
        throw new AetherCharacterActionException(400, 'Voor psi-acties is een gave-type verplicht.');
    }
    if (!aetherCanManageSkill($pdo, $currentUser, $skillId)) {
        throw new AetherCharacterActionException(403, 'Je hebt geen rechten om deze vaardigheid te gebruiken.');
    }

    $pdo->beginTransaction();
    try {
        $result = executeCharacterPsiSkillUse(
            $pdo,
            $character,
            $eventId,
            $skillId,
            $actionSubtype,
            $clearBurn,
            (int) $currentUser['id']
        );
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException && !$e instanceof PDOException) {
            throw new AetherCharacterActionException(400, $e->getMessage(), true);
        }
        throw $e;
    }
}
