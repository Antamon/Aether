<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/economyUtils.php';

const AETHER_WORLD_KNOWLEDGE_SKILL_ID = 32;

function canViewCharacterActions(array $character, string $role, int $userId): bool
{
    if (isPrivilegedUserRole($role)) {
        return true;
    }

    return $role === 'participant'
        && (int) ($character['idUser'] ?? 0) === $userId;
}

function fetchCharacterActionEvents(PDO $pdo): array
{
    $rows = dbAll(
        $pdo,
        'SELECT id, title, dateStart, dateEnd
           FROM tblEvent
          ORDER BY dateStart DESC, id DESC'
    );

    return array_map(static function (array $row): array {
        return [
            'idEvent' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'dateStart' => (string) ($row['dateStart'] ?? ''),
            'dateEnd' => (string) ($row['dateEnd'] ?? ''),
        ];
    }, $rows);
}

/** @return array<string, mixed>|null */
function fetchCharacterActionEvent(PDO $pdo, int $idEvent): ?array
{
    if ($idEvent <= 0) return null;
    return dbOne(
        $pdo,
        'SELECT id, title, dateStart, dateEnd FROM tblEvent WHERE id = :idEvent',
        ['idEvent' => $idEvent]
    );
}

function getCharacterSkillLevelByIdForGossip(PDO $pdo, int $idCharacter, int $idSkill): int
{
    if ($idCharacter <= 0 || $idSkill <= 0) {
        return 0;
    }

    $row = dbOne(
        $pdo,
        'SELECT level
           FROM tblLinkCharacterSkill
          WHERE idCharacter = :idCharacter
            AND idSkill = :idSkill',
        [
            'idCharacter' => $idCharacter,
            'idSkill' => $idSkill,
        ]
    );

    return max(0, min(3, (int) ($row['level'] ?? 0)));
}

function getDefaultGossipVisibilityForCharacterType(string $characterType): bool
{
    return trim($characterType) === 'player';
}

function formatGossipKnowledgeDisplayName(array $row): string
{
    $firstName = trim((string) ($row['firstName'] ?? ''));
    $lastName = trim((string) ($row['lastName'] ?? ''));
    $displayName = trim($lastName . ' ' . $firstName);

    return $displayName !== '' ? $displayName : ('Personage #' . (int) ($row['idCharacter'] ?? 0));
}

function fetchGossipVisibilityState(PDO $pdo, int $idEvent, int $idCharacter, string $characterType): bool
{
    $row = dbOne(
        $pdo,
        'SELECT isVisible
           FROM tblCharacterDiaryVisibility
          WHERE idEvent = :idEvent
            AND idCharacter = :idCharacter',
        [
            'idEvent' => $idEvent,
            'idCharacter' => $idCharacter,
        ]
    );

    if ($row === null) {
        return getDefaultGossipVisibilityForCharacterType($characterType);
    }

    return (bool) ((int) ($row['isVisible'] ?? 0));
}

function setGossipVisibilityState(PDO $pdo, int $idEvent, int $idCharacter, bool $isVisible, int $updatedBy): void
{
    $pdo->prepare(
        'INSERT INTO tblCharacterDiaryVisibility (idEvent, idCharacter, isVisible, updatedAt, updatedBy)
         VALUES (:idEvent, :idCharacter, :isVisible, NOW(), :updatedBy)
         ON DUPLICATE KEY UPDATE
             isVisible = VALUES(isVisible),
             updatedAt = VALUES(updatedAt),
             updatedBy = VALUES(updatedBy)'
    )->execute([
        'idEvent' => $idEvent,
        'idCharacter' => $idCharacter,
        'isVisible' => $isVisible ? 1 : 0,
        'updatedBy' => $updatedBy > 0 ? $updatedBy : null,
    ]);
}

function fetchGossipUnlockRow(PDO $pdo, int $idViewerCharacter, int $idEvent, int $idSourceCharacter): ?array
{
    return dbOne(
        $pdo,
        'SELECT unlockGossip1, unlockGossip2, unlockGossip3
           FROM tblCharacterEventGossipUnlock
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent
            AND idSourceCharacter = :idSourceCharacter',
        [
            'idViewerCharacter' => $idViewerCharacter,
            'idEvent' => $idEvent,
            'idSourceCharacter' => $idSourceCharacter,
        ]
    );
}

/** Create the lock target when needed and return its latest state under a row lock. */
function lockGossipUnlockRow(PDO $pdo, int $idViewerCharacter, int $idEvent, int $idSourceCharacter): array
{
    $pdo->prepare(
        'INSERT INTO tblCharacterEventGossipUnlock (
            idViewerCharacter, idEvent, idSourceCharacter,
            unlockGossip1, unlockGossip2, unlockGossip3, createdAt, updatedAt
         ) VALUES (
            :idViewerCharacter, :idEvent, :idSourceCharacter, 0, 0, 0, NOW(), NOW()
         )
         ON DUPLICATE KEY UPDATE id = id'
    )->execute([
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
        'idSourceCharacter' => $idSourceCharacter,
    ]);

    $stmt = $pdo->prepare(
        'SELECT unlockGossip1, unlockGossip2, unlockGossip3
           FROM tblCharacterEventGossipUnlock
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent
            AND idSourceCharacter = :idSourceCharacter
          FOR UPDATE'
    );
    $stmt->execute([
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
        'idSourceCharacter' => $idSourceCharacter,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Kon de gossipontgrendeling niet vergrendelen.');
    return $row;
}

function lockExistingGossipUnlockRow(
    PDO $pdo,
    int $idViewerCharacter,
    int $idEvent,
    int $idSourceCharacter
): ?array {
    $stmt = $pdo->prepare(
        'SELECT unlockGossip1, unlockGossip2, unlockGossip3
           FROM tblCharacterEventGossipUnlock
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent
            AND idSourceCharacter = :idSourceCharacter
          FOR UPDATE'
    );
    $stmt->execute([
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
        'idSourceCharacter' => $idSourceCharacter,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function buildUnlockedGossipState(?array $row): array
{
    return [
        1 => (bool) ((int) ($row['unlockGossip1'] ?? 0)),
        2 => (bool) ((int) ($row['unlockGossip2'] ?? 0)),
        3 => (bool) ((int) ($row['unlockGossip3'] ?? 0)),
    ];
}

function saveUnlockedGossipState(PDO $pdo, int $idViewerCharacter, int $idEvent, int $idSourceCharacter, array $state): void
{
    $pdo->prepare(
        'INSERT INTO tblCharacterEventGossipUnlock (
            idViewerCharacter,
            idEvent,
            idSourceCharacter,
            unlockGossip1,
            unlockGossip2,
            unlockGossip3,
            createdAt,
            updatedAt
         ) VALUES (
            :idViewerCharacter,
            :idEvent,
            :idSourceCharacter,
            :unlockGossip1,
            :unlockGossip2,
            :unlockGossip3,
            NOW(),
            NOW()
         )
         ON DUPLICATE KEY UPDATE
            unlockGossip1 = GREATEST(unlockGossip1, VALUES(unlockGossip1)),
            unlockGossip2 = GREATEST(unlockGossip2, VALUES(unlockGossip2)),
            unlockGossip3 = GREATEST(unlockGossip3, VALUES(unlockGossip3)),
            updatedAt = VALUES(updatedAt)'
    )->execute([
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
        'idSourceCharacter' => $idSourceCharacter,
        'unlockGossip1' => !empty($state[1]) ? 1 : 0,
        'unlockGossip2' => !empty($state[2]) ? 1 : 0,
        'unlockGossip3' => !empty($state[3]) ? 1 : 0,
    ]);
}

function getCharacterEventGossipAttemptCount(PDO $pdo, int $idViewerCharacter, int $idEvent): int
{
    $row = dbOne(
        $pdo,
        'SELECT attemptCount
           FROM tblCharacterEventGossipAttempt
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent',
        [
            'idViewerCharacter' => $idViewerCharacter,
            'idEvent' => $idEvent,
        ]
    );

    return max(0, (int) ($row['attemptCount'] ?? 0));
}

/** Ensure and lock the viewer/event attempt counter before evaluating reveal chances. */
function lockCharacterEventGossipAttemptCount(PDO $pdo, int $idViewerCharacter, int $idEvent): int
{
    $pdo->prepare(
        'INSERT INTO tblCharacterEventGossipAttempt (idViewerCharacter, idEvent, attemptCount, updatedAt)
         VALUES (:idViewerCharacter, :idEvent, 0, NOW())
         ON DUPLICATE KEY UPDATE id = id'
    )->execute(['idViewerCharacter' => $idViewerCharacter, 'idEvent' => $idEvent]);

    $stmt = $pdo->prepare(
        'SELECT attemptCount
           FROM tblCharacterEventGossipAttempt
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent
          FOR UPDATE'
    );
    $stmt->execute(['idViewerCharacter' => $idViewerCharacter, 'idEvent' => $idEvent]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Kon de gossipattempt niet vergrendelen.');
    return max(0, (int) $row['attemptCount']);
}

function incrementCharacterEventGossipAttemptCount(PDO $pdo, int $idViewerCharacter, int $idEvent): int
{
    $pdo->prepare(
        'INSERT INTO tblCharacterEventGossipAttempt (idViewerCharacter, idEvent, attemptCount, updatedAt)
         VALUES (:idViewerCharacter, :idEvent, 1, NOW())
         ON DUPLICATE KEY UPDATE
            attemptCount = attemptCount + 1,
            updatedAt = NOW()'
    )->execute([
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
    ]);

    return getCharacterEventGossipAttemptCount($pdo, $idViewerCharacter, $idEvent);
}

function decrementCharacterEventGossipAttemptCount(PDO $pdo, int $idViewerCharacter, int $idEvent): int
{
    $nextAttemptCount = max(0, getCharacterEventGossipAttemptCount($pdo, $idViewerCharacter, $idEvent) - 1);

    if ($nextAttemptCount <= 0) {
        $pdo->prepare(
            'DELETE FROM tblCharacterEventGossipAttempt
              WHERE idViewerCharacter = :idViewerCharacter
                AND idEvent = :idEvent'
        )->execute([
            'idViewerCharacter' => $idViewerCharacter,
            'idEvent' => $idEvent,
        ]);

        return 0;
    }

    $pdo->prepare(
        'UPDATE tblCharacterEventGossipAttempt
            SET attemptCount = :attemptCount,
                updatedAt = NOW()
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent'
    )->execute([
        'attemptCount' => $nextAttemptCount,
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
    ]);

    return $nextAttemptCount;
}

function buildGossipPayloadFromDiaryRow(array $row): array
{
    $result = [];
    foreach ([1, 2, 3] as $level) {
        $key = 'gossip' . $level;
        $value = trim((string) ($row[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $result[$level] = [
            'level' => $level,
            'title' => 'Gossip ' . $level,
            'value' => $value,
        ];
    }

    return $result;
}

function getUnlockedGossipLevels(array $gossipPayload, array $unlockState, int $viewerSkillLevel): array
{
    $levels = [];
    foreach ($gossipPayload as $level => $gossip) {
        if ($level > $viewerSkillLevel) {
            continue;
        }
        if (!empty($unlockState[$level])) {
            $levels[] = $gossip;
        }
    }

    usort($levels, static function (array $left, array $right): int {
        return ((int) ($left['level'] ?? 0)) <=> ((int) ($right['level'] ?? 0));
    });

    return $levels;
}

function getHighestUnlockedGossipLevel(array $gossipPayload, array $unlockState, int $viewerSkillLevel): int
{
    $highest = 0;
    foreach ($gossipPayload as $level => $_gossip) {
        if ($level > $viewerSkillLevel) {
            continue;
        }
        if (!empty($unlockState[$level])) {
            $highest = max($highest, (int) $level);
        }
    }
    return $highest;
}

function getHighestUnlockedGossipLevelFromState(array $unlockState): int
{
    $highest = 0;
    foreach ([1, 2, 3] as $level) {
        if (!empty($unlockState[$level])) {
            $highest = $level;
        }
    }
    return $highest;
}

function getGossipLevelLabel(int $level): string
{
    return match ($level) {
        1 => 'Niveau 1',
        2 => 'Niveau 2',
        3 => 'Niveau 3',
        default => 'Geen',
    };
}

function deleteGossipUnlockState(PDO $pdo, int $idViewerCharacter, int $idEvent, int $idSourceCharacter): void
{
    $pdo->prepare(
        'DELETE FROM tblCharacterEventGossipUnlock
          WHERE idViewerCharacter = :idViewerCharacter
            AND idEvent = :idEvent
            AND idSourceCharacter = :idSourceCharacter'
    )->execute([
        'idViewerCharacter' => $idViewerCharacter,
        'idEvent' => $idEvent,
        'idSourceCharacter' => $idSourceCharacter,
    ]);
}

function fetchGossipUnlockDiscoverers(PDO $pdo, int $idEvent, int $idSourceCharacter): array
{
    if ($idEvent <= 0 || $idSourceCharacter <= 0) {
        return [];
    }

    $rows = dbAll(
        $pdo,
        'SELECT
            u.idViewerCharacter,
            viewer.firstName,
            viewer.lastName,
            u.unlockGossip1,
            u.unlockGossip2,
            u.unlockGossip3
         FROM tblCharacterEventGossipUnlock AS u
         JOIN tblCharacter AS viewer
           ON viewer.id = u.idViewerCharacter
         WHERE u.idEvent = :idEvent
           AND u.idSourceCharacter = :idSourceCharacter
         ORDER BY viewer.lastName ASC, viewer.firstName ASC, viewer.id ASC',
        [
            'idEvent' => $idEvent,
            'idSourceCharacter' => $idSourceCharacter,
        ]
    );

    $discoverers = [];
    foreach ($rows as $row) {
        $unlockState = buildUnlockedGossipState($row);
        $highestLevel = getHighestUnlockedGossipLevelFromState($unlockState);
        if ($highestLevel <= 0) {
            continue;
        }

        $discoverers[] = [
            'idViewerCharacter' => (int) ($row['idViewerCharacter'] ?? 0),
            'displayName' => formatGossipKnowledgeDisplayName([
                'idCharacter' => (int) ($row['idViewerCharacter'] ?? 0),
                'firstName' => (string) ($row['firstName'] ?? ''),
                'lastName' => (string) ($row['lastName'] ?? ''),
            ]),
            'unlockedGossipLevel' => $highestLevel,
            'unlockedGossipLevelLabel' => getGossipLevelLabel($highestLevel),
        ];
    }

    return $discoverers;
}

function getGossipRevealChanceOutOfTen(int $attemptNumber, int $viewerSkillLevel, int $gossipLevel): int
{
    $delay = max(0, $viewerSkillLevel - $gossipLevel);
    $penalty = max(0, ($attemptNumber - 1) - $delay);
    return max(0, min(10, 10 - $penalty));
}

function fetchVisibleKnowledgeTargetsForViewer(PDO $pdo, int $idViewerCharacter, int $idEvent, int $viewerSkillLevel): array
{
    if ($idViewerCharacter <= 0 || $idEvent <= 0 || $viewerSkillLevel <= 0) {
        return [];
    }

    $rows = dbAll(
        $pdo,
        'SELECT
            c.id AS idCharacter,
            c.firstName,
            c.lastName,
            c.type,
            d.gossip1,
            d.gossip2,
            d.gossip3,
            v.isVisible,
            u.unlockGossip1,
            u.unlockGossip2,
            u.unlockGossip3
         FROM tblCharacterDiary AS d
         JOIN tblCharacter AS c
           ON c.id = d.idCharacter
         LEFT JOIN tblCharacterDiaryVisibility AS v
           ON v.idEvent = d.idEvent
          AND v.idCharacter = d.idCharacter
         LEFT JOIN tblCharacterEventGossipUnlock AS u
           ON u.idViewerCharacter = :idViewerCharacterUnlock
          AND u.idEvent = d.idEvent
          AND u.idSourceCharacter = d.idCharacter
         WHERE d.idEvent = :idEvent
           AND c.id <> :idViewerCharacterFilter
           AND (
                TRIM(COALESCE(d.gossip1, \'\')) <> \'\'
                OR TRIM(COALESCE(d.gossip2, \'\')) <> \'\'
                OR TRIM(COALESCE(d.gossip3, \'\')) <> \'\'
           )
         ORDER BY c.lastName ASC, c.firstName ASC, c.id ASC',
        [
            'idViewerCharacterUnlock' => $idViewerCharacter,
            'idViewerCharacterFilter' => $idViewerCharacter,
            'idEvent' => $idEvent,
        ]
    );

    $targets = [];
    foreach ($rows as $row) {
        $isVisible = $row['isVisible'] !== null
            ? (bool) ((int) $row['isVisible'])
            : getDefaultGossipVisibilityForCharacterType((string) ($row['type'] ?? ''));

        if (!$isVisible) {
            continue;
        }

        $unlockState = buildUnlockedGossipState($row);
        $gossipPayload = buildGossipPayloadFromDiaryRow($row);
        $accessibleGossips = array_filter($gossipPayload, static function (array $gossip) use ($viewerSkillLevel): bool {
            return (int) ($gossip['level'] ?? 0) <= $viewerSkillLevel;
        });
        if (count($accessibleGossips) === 0) {
            continue;
        }

        $targets[] = [
            'idCharacter' => (int) ($row['idCharacter'] ?? 0),
            'displayName' => formatGossipKnowledgeDisplayName($row),
            'portraitUrl' => getCharacterPortraitUrl((int) ($row['idCharacter'] ?? 0)),
            'type' => (string) ($row['type'] ?? ''),
            'unlockedGossipLevel' => getHighestUnlockedGossipLevel($gossipPayload, $unlockState, $viewerSkillLevel),
            'isFullyUnlocked' => count(getUnlockedGossipLevels($gossipPayload, $unlockState, $viewerSkillLevel))
                >= count($accessibleGossips),
        ];
    }

    return $targets;
}

function fetchKnowledgeTargetDiaryRow(PDO $pdo, int $idEvent, int $idSourceCharacter): ?array
{
    return dbOne(
        $pdo,
        'SELECT
            d.idCharacter AS idCharacter,
            d.idEvent,
            d.gossip1,
            d.gossip2,
            d.gossip3,
            c.firstName,
            c.lastName,
            c.type
         FROM tblCharacterDiary AS d
         JOIN tblCharacter AS c
           ON c.id = d.idCharacter
         WHERE d.idEvent = :idEvent
           AND d.idCharacter = :idSourceCharacter',
        [
            'idEvent' => $idEvent,
            'idSourceCharacter' => $idSourceCharacter,
        ]
    );
}

function revealKnowledgeGossip(PDO $pdo, int $idViewerCharacter, int $idEvent, int $idSourceCharacter, int $viewerSkillLevel): array
{
    $row = fetchKnowledgeTargetDiaryRow($pdo, $idEvent, $idSourceCharacter);
    if ($row === null) {
        throw new RuntimeException('Geen gossip gevonden voor dit personage en event.');
    }

    $isVisible = fetchGossipVisibilityState($pdo, $idEvent, $idSourceCharacter, (string) ($row['type'] ?? ''));
    if (!$isVisible) {
        throw new RuntimeException('Deze gossip is niet zichtbaar.');
    }

    $gossipPayload = buildGossipPayloadFromDiaryRow($row);
    if (count($gossipPayload) === 0) {
        throw new RuntimeException('Dit personage heeft geen gossip voor dit event.');
    }
    if (count(array_filter($gossipPayload, static function (array $gossip) use ($viewerSkillLevel): bool {
        return (int) ($gossip['level'] ?? 0) <= $viewerSkillLevel;
    })) === 0) {
        throw new RuntimeException('Dit personage heeft geen gossip op een niveau dat Wereldwijs kan onthullen.');
    }

    // De viewer/eventcounter wordt altijd eerst vergrendeld. Daarna volgt de concrete
    // viewer/event/source-unlockrij. Zo delen gelijktijdige reveals één actuele pogingsteller.
    $attemptNumber = lockCharacterEventGossipAttemptCount($pdo, $idViewerCharacter, $idEvent);
    $unlockState = buildUnlockedGossipState(
        lockGossipUnlockRow($pdo, $idViewerCharacter, $idEvent, $idSourceCharacter)
    );

    $pendingLevels = [];
    foreach ($gossipPayload as $level => $gossip) {
        if ($level > $viewerSkillLevel) {
            continue;
        }
        if (empty($unlockState[$level])) {
            $pendingLevels[] = (int) $level;
        }
    }

    $newlyUnlocked = [];

    if (count($pendingLevels) > 0) {
        $attemptNumber = incrementCharacterEventGossipAttemptCount($pdo, $idViewerCharacter, $idEvent);

        foreach ($pendingLevels as $level) {
            $chance = getGossipRevealChanceOutOfTen($attemptNumber, $viewerSkillLevel, $level);
            if ($chance > 0 && random_int(1, 10) <= $chance) {
                $unlockState[$level] = true;
                $newlyUnlocked[] = $level;
            }
        }

        saveUnlockedGossipState($pdo, $idViewerCharacter, $idEvent, $idSourceCharacter, $unlockState);
    }

    return [
        'attemptCount' => $attemptNumber,
        'newlyUnlockedLevels' => $newlyUnlocked,
        'unlockedGossips' => getUnlockedGossipLevels($gossipPayload, $unlockState, $viewerSkillLevel),
        'unlockedGossipLevel' => getHighestUnlockedGossipLevel($gossipPayload, $unlockState, $viewerSkillLevel),
        'displayName' => formatGossipKnowledgeDisplayName($row),
        'portraitUrl' => getCharacterPortraitUrl((int) ($row['idCharacter'] ?? 0)),
        'type' => (string) ($row['type'] ?? ''),
        'isFullyUnlocked' => count(getUnlockedGossipLevels($gossipPayload, $unlockState, $viewerSkillLevel))
            >= count(array_filter($gossipPayload, static function (array $gossip) use ($viewerSkillLevel): bool {
                return (int) ($gossip['level'] ?? 0) <= $viewerSkillLevel;
            })),
    ];
}
