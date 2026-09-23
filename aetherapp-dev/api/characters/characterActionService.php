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
function aetherAuthorizeCharacterSkillActionReplay(
    PDO $pdo,
    array $currentUser,
    array $input
): array {
    $character = aetherRequireCharacterActionAccess(
        $pdo,
        $currentUser,
        (int) $input['idCharacter'],
        'Geen rechten om deze actie uit te voeren.'
    );
    if (!aetherCanManageSkill($pdo, $currentUser, (int) $input['idSkill'])) {
        throw new AetherCharacterActionException(403, 'Je hebt geen rechten om deze vaardigheid te gebruiken.');
    }
    if (fetchCharacterActionEvent($pdo, (int) $input['idEvent']) === null) {
        throw new AetherCharacterActionException(404, 'Event niet gevonden.');
    }
    if (validateCharacterPsiSkill(
        $pdo,
        (int) $input['idCharacter'],
        (int) $input['idSkill'],
        (string) $input['actionSubtype']
    ) === null) {
        throw new AetherCharacterActionException(403, 'Deze vaardigheid is niet langer beschikbaar voor dit personage.');
    }
    return $character;
}

function aetherAuthorizeKnowledgeRevealReplay(PDO $pdo, array $currentUser, array $input): void
{
    aetherRequireCharacterActionAccess(
        $pdo,
        $currentUser,
        (int) $input['idCharacter'],
        'Geen rechten om deze actie uit te voeren.'
    );
    if (fetchCharacterActionEvent($pdo, (int) $input['idEvent']) === null) {
        throw new AetherCharacterActionException(404, 'Event niet gevonden.');
    }
    $level = getCharacterSkillLevelByIdForGossip(
        $pdo,
        (int) $input['idCharacter'],
        AETHER_WORLD_KNOWLEDGE_SKILL_ID
    );
    $target = fetchKnowledgeTargetDiaryRow($pdo, (int) $input['idEvent'], (int) $input['idSourceCharacter']);
    if ($level <= 0 || $target === null || !fetchGossipVisibilityState(
        $pdo,
        (int) $input['idEvent'],
        (int) $input['idSourceCharacter'],
        (string) ($target['type'] ?? '')
    )) {
        throw new AetherCharacterActionException(403, 'Deze wereldwijsroddels zijn niet langer beschikbaar.');
    }
}

/** @template T @param callable():T $mutation @return T */
function aetherRunCharacterActionMutation(PDO $pdo, callable $mutation): mixed
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $result = $mutation();
        if ($ownsTransaction) $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof RuntimeException && !$e instanceof PDOException
            && !$e instanceof AetherCharacterActionException) {
            throw new AetherCharacterActionException(400, $e->getMessage(), true);
        }
        throw $e;
    }
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

    return aetherRunCharacterActionMutation($pdo, static function () use (
        $pdo,
        $characterId,
        $eventId,
        $sourceCharacterId,
        $worldKnowledgeLevel
    ): array {
        return revealKnowledgeGossip(
            $pdo,
            $characterId,
            $eventId,
            $sourceCharacterId,
            $worldKnowledgeLevel
        );
    });
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

    return aetherRunCharacterActionMutation($pdo, static function () use (
        $pdo,
        $character,
        $eventId,
        $skillId,
        $actionSubtype,
        $clearBurn,
        $currentUser
    ): array {
        return executeCharacterPsiSkillUse(
            $pdo,
            $character,
            $eventId,
            $skillId,
            $actionSubtype,
            $clearBurn,
            (int) $currentUser['id']
        );
    });
}
