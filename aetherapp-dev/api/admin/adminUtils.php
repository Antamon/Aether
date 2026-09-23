<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../characters/characterPointUtils.php';
require_once __DIR__ . '/../characters/characterMediaUtils.php';
require_once __DIR__ . '/../characters/gossipKnowledgeUtils.php';
require_once __DIR__ . '/../characters/characterSkillActionUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';

function requirePrivilegedAdminAccess(PDO $pdo, bool $requireCsrf = false): array
{
    $user = aetherRequireAuthenticatedUser($pdo);
    if (!aetherIsPrivilegedRole($user['role'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om de adminpagina te beheren.']);
        exit;
    }

    if ($requireCsrf) {
        aetherRequireCsrfToken();
    }

    return [
        'idUser' => $user['id'],
        'role' => $user['role'],
    ];
}

function requireAdministratorAccess(PDO $pdo, bool $requireCsrf = false): array
{
    $user = requirePrivilegedAdminAccess($pdo, $requireCsrf);
    if (($user['role'] ?? '') !== 'administrator') {
        http_response_code(403);
        echo json_encode(['error' => 'Alleen administrators kunnen categorieën beheren.']);
        exit;
    }

    return $user;
}

function normalizeAdminTextKey(string $value): string
{
    $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));
    if ($normalized === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($normalized, 'UTF-8');
    }

    return strtolower($normalized);
}

function getSkillVisibilityStorageMap(PDO $pdo): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $enumValues = [];
    try {
        $column = dbOne(
            $pdo,
            'SELECT COLUMN_TYPE
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tableName
                AND COLUMN_NAME = :columnName',
            [
                'tableName' => 'tblSkill',
                'columnName' => 'visibility',
            ]
        );

        $columnType = (string) ($column['COLUMN_TYPE'] ?? '');
        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches)) {
            $enumValues = array_map(static function (string $value): string {
                return stripcslashes($value);
            }, $matches[1]);
        }
    } catch (Throwable $e) {
        $enumValues = [];
    }

    if (count($enumValues) === 0) {
        try {
            $columnRow = dbOne($pdo, "SHOW COLUMNS FROM tblSkill LIKE 'visibility'");
            $columnType = (string) ($columnRow['Type'] ?? '');
            if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches)) {
                $enumValues = array_map(static function (string $value): string {
                    return stripcslashes($value);
                }, $matches[1]);
            }
        } catch (Throwable $e) {
            $enumValues = [];
        }
    }

    $distinctValues = [];
    try {
        $rows = dbAll($pdo, 'SELECT DISTINCT visibility FROM tblSkill');
        foreach ($rows as $row) {
            $value = trim((string) ($row['visibility'] ?? ''));
            if ($value !== '') {
                $distinctValues[] = $value;
            }
        }
    } catch (Throwable $e) {
        $distinctValues = [];
    }

    $allValues = array_values(array_unique(array_merge($enumValues, $distinctValues)));

    $publicValue = 'public';
    foreach ($allValues as $value) {
        if (normalizeAdminTextKey($value) === 'public') {
            $publicValue = $value;
            break;
        }
    }

    $secretValue = null;
    foreach (['secret', 'private', 'hidden'] as $candidate) {
        foreach ($allValues as $value) {
            if (normalizeAdminTextKey($value) === $candidate) {
                $secretValue = $value;
                break 2;
            }
        }
    }

    if ($secretValue === null) {
        foreach ($allValues as $value) {
            if (normalizeAdminTextKey($value) !== normalizeAdminTextKey($publicValue)) {
                $secretValue = $value;
                break;
            }
        }
    }

    if ($secretValue === null) {
        $secretValue = 'secret';
    }

    $cache = [
        'public' => $publicValue,
        'secret' => $secretValue,
    ];

    return $cache;
}

function isSkillVisibilitySecret(PDO $pdo, mixed $value): bool
{
    $visibility = trim((string) $value);
    if ($visibility === '') {
        return false;
    }

    $map = getSkillVisibilityStorageMap($pdo);
    return normalizeAdminTextKey($visibility) !== normalizeAdminTextKey($map['public']);
}

function fetchAdminSkillTypeOptions(PDO $pdo): array
{
    $rows = dbAll(
        $pdo,
        'SELECT id, code, name, description
           FROM tblSkillType
          ORDER BY name ASC, id ASC'
    );

    return array_map(static function (array $row): array {
        return [
            'idSkillType' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
        ];
    }, $rows);
}

function buildAdminSkillTypeCode(string $name): string
{
    $normalized = trim($name);
    if ($normalized === '') {
        return 'skill_type';
    }

    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = $ascii;
        }
    }

    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    $normalized = trim($normalized, '_');

    return $normalized !== '' ? $normalized : 'skill_type';
}

function buildUniqueAdminSkillTypeCode(PDO $pdo, string $name, int $excludeIdSkillType = 0): string
{
    $baseCode = buildAdminSkillTypeCode($name);
    $candidate = $baseCode;
    $suffix = 2;

    while (true) {
        $params = ['code' => $candidate];
        $sql = 'SELECT id
                  FROM tblSkillType
                 WHERE code = :code';

        if ($excludeIdSkillType > 0) {
            $sql .= ' AND id <> :idSkillType';
            $params['idSkillType'] = $excludeIdSkillType;
        }

        $existing = dbOne($pdo, $sql . ' LIMIT 1', $params);
        if ($existing === null) {
            return $candidate;
        }

        $candidate = $baseCode . '_' . $suffix;
        $suffix++;
    }
}

function fetchAdminSkillList(PDO $pdo): array
{
    $rows = dbAll(
        $pdo,
        'SELECT id, name, visibility
           FROM tblSkill
          ORDER BY name ASC, id ASC'
    );

    return array_map(static function (array $row) use ($pdo): array {
        return [
            'idSkill' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'isSecret' => isSkillVisibilitySecret($pdo, $row['visibility'] ?? ''),
        ];
    }, $rows);
}

function buildAdminSkillHolderDisplayName(array $row): string
{
    $firstName = trim((string) ($row['firstName'] ?? ''));
    $lastName = trim((string) ($row['lastName'] ?? ''));
    $displayName = trim($lastName . ' ' . $firstName);

    if ($displayName !== '') {
        return $displayName;
    }

    return 'Personage #' . (int) ($row['idCharacter'] ?? 0);
}

function getAdminSkillHolderLevelOrder(int $level): int
{
    return match ($level) {
        1 => 1,
        2 => 2,
        3 => 3,
        0 => 4,
        default => 5,
    };
}

function getAdminSkillHolderLevelLabel(int $level): string
{
    return match ($level) {
        1 => 'Beginneling',
        2 => 'Deskundige',
        3 => 'Meester',
        0 => 'Ongetraind',
        default => 'Overig',
    };
}

function fetchAdminSkillHolderGroups(PDO $pdo, int $idSkill): array
{
    if ($idSkill <= 0) {
        return [];
    }

    $rows = dbAll(
        $pdo,
        'SELECT
            c.id AS idCharacter,
            c.firstName,
            c.lastName,
            c.title,
            lcs.level,
            ss.id AS idSkillSpecialisation,
            ss.name AS specialisationName
         FROM tblLinkCharacterSkill AS lcs
         JOIN tblCharacter AS c
           ON c.id = lcs.idCharacter
         LEFT JOIN tblCharacterSpecialisation AS cs
           ON cs.idCharacter = lcs.idCharacter
          AND cs.idSkill = lcs.idSkill
         LEFT JOIN tblSkillSpecialisation AS ss
           ON ss.id = cs.idSkillSpecialisation
         WHERE lcs.idSkill = :idSkill
         ORDER BY
            CASE
                WHEN lcs.level = 1 THEN 1
                WHEN lcs.level = 2 THEN 2
                WHEN lcs.level = 3 THEN 3
                WHEN lcs.level = 0 THEN 4
                ELSE 5
            END ASC,
            c.lastName ASC,
            c.firstName ASC,
            ss.name ASC',
        ['idSkill' => $idSkill]
    );

    $groups = [];
    foreach ($rows as $row) {
        $level = (int) ($row['level'] ?? 0);
        if (!isset($groups[$level])) {
            $groups[$level] = [];
        }

        $idCharacter = (int) ($row['idCharacter'] ?? 0);
        if ($idCharacter <= 0) {
            continue;
        }

        if (!isset($groups[$level][$idCharacter])) {
            $groups[$level][$idCharacter] = [
                'idCharacter' => $idCharacter,
                'displayName' => buildAdminSkillHolderDisplayName($row),
                'specialisations' => [],
            ];
        }

        $specialisationName = trim((string) ($row['specialisationName'] ?? ''));
        if ($specialisationName !== '' && !in_array($specialisationName, $groups[$level][$idCharacter]['specialisations'], true)) {
            $groups[$level][$idCharacter]['specialisations'][] = $specialisationName;
        }
    }

    $result = [];
    $levels = array_keys($groups);
    usort($levels, static function (int $left, int $right): int {
        return getAdminSkillHolderLevelOrder($left) <=> getAdminSkillHolderLevelOrder($right);
    });

    foreach ($levels as $level) {
        $characters = array_values($groups[$level]);
        usort($characters, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['displayName'] ?? ''), (string) ($right['displayName'] ?? ''));
        });

        $result[] = [
            'level' => $level,
            'label' => getAdminSkillHolderLevelLabel($level),
            'characters' => $characters,
        ];
    }

    return $result;
}

function fetchAdminKnowledgeEventOptions(PDO $pdo): array
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

function fetchAdminActionEventOptions(PDO $pdo): array
{
    $rows = dbAll(
        $pdo,
        'SELECT
            e.id,
            e.title,
            e.dateStart,
            e.dateEnd,
            COUNT(u.id) AS actionCount,
            MAX(u.createdAt) AS lastActionAt
         FROM tblEvent AS e
         LEFT JOIN tblCharacterSkillActionUse AS u
           ON u.idEvent = e.id
         GROUP BY e.id, e.title, e.dateStart, e.dateEnd
         ORDER BY e.dateStart DESC, e.id DESC'
    );

    return array_map(static function (array $row): array {
        return [
            'idEvent' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'dateStart' => (string) ($row['dateStart'] ?? ''),
            'dateEnd' => (string) ($row['dateEnd'] ?? ''),
            'actionCount' => (int) ($row['actionCount'] ?? 0),
            'lastActionAt' => (string) ($row['lastActionAt'] ?? ''),
        ];
    }, $rows);
}

function getAdminKnowledgeCharacterTypeOrder(string $type): int
{
    return match ($type) {
        'extra' => 1,
        'player' => 2,
        default => 3,
    };
}

function getAdminKnowledgeCharacterTypeLabel(string $type): string
{
    return match ($type) {
        'extra' => 'Figurantenrollen',
        'player' => 'Spelerspersonages',
        default => 'Overige personages',
    };
}

function fetchAdminKnowledgeEventGossipGroups(PDO $pdo, int $idEvent): array
{
    if ($idEvent <= 0) {
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
            v.isVisible
         FROM tblCharacterDiary AS d
         JOIN tblCharacter AS c
           ON c.id = d.idCharacter
         LEFT JOIN tblCharacterDiaryVisibility AS v
           ON v.idEvent = d.idEvent
          AND v.idCharacter = d.idCharacter
         WHERE d.idEvent = :idEvent
           AND (
                TRIM(COALESCE(d.gossip1, \'\')) <> \'\'
                OR TRIM(COALESCE(d.gossip2, \'\')) <> \'\'
                OR TRIM(COALESCE(d.gossip3, \'\')) <> \'\'
           )
         ORDER BY
            CASE
                WHEN c.type = \'extra\' THEN 1
                WHEN c.type = \'player\' THEN 2
                ELSE 3
            END ASC,
            c.lastName ASC,
            c.firstName ASC,
            c.id ASC',
        ['idEvent' => $idEvent]
    );

    $groups = [];
    foreach ($rows as $row) {
        $type = trim((string) ($row['type'] ?? ''));
        if (!isset($groups[$type])) {
            $groups[$type] = [];
        }

        $gossips = [];
        foreach ([
            'gossip1' => 'Gossip 1',
            'gossip2' => 'Gossip 2',
            'gossip3' => 'Gossip 3',
        ] as $key => $label) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                $gossips[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        if (count($gossips) === 0) {
            continue;
        }

        $groups[$type][] = [
            'idCharacter' => (int) ($row['idCharacter'] ?? 0),
            'displayName' => buildAdminSkillHolderDisplayName($row),
            'portraitUrl' => getCharacterPortraitUrl((int) ($row['idCharacter'] ?? 0)),
            'type' => $type,
            'isVisible' => $row['isVisible'] !== null
                ? (bool) ((int) $row['isVisible'])
                : getDefaultGossipVisibilityForCharacterType($type),
            'discoverers' => fetchGossipUnlockDiscoverers($pdo, $idEvent, (int) ($row['idCharacter'] ?? 0)),
            'gossips' => $gossips,
        ];
    }

    $types = array_keys($groups);
    usort($types, static function (string $left, string $right): int {
        return getAdminKnowledgeCharacterTypeOrder($left) <=> getAdminKnowledgeCharacterTypeOrder($right);
    });

    $result = [];
    foreach ($types as $type) {
        $characters = $groups[$type];
        usort($characters, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['displayName'] ?? ''), (string) ($right['displayName'] ?? ''));
        });

        $result[] = [
            'type' => $type,
            'label' => getAdminKnowledgeCharacterTypeLabel($type),
            'characters' => $characters,
        ];
    }

    return $result;
}

function decodeAdminJsonColumn(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function formatAdminSkillActionCodeLabel(string $actionCode): string
{
    $normalized = normalizeSkillActionCode($actionCode);

    return match ($normalized) {
        AETHER_SKILL_ACTION_CODE_PSI => 'Psi',
        default => ucwords(str_replace('_', ' ', $normalized !== '' ? $normalized : trim($actionCode))),
    };
}

function formatAdminSkillActionSubtypeLabel(string $actionCode, string $actionSubtype): string
{
    $normalizedActionCode = normalizeSkillActionCode($actionCode);
    $normalizedSubtype = normalizeSkillActionCode($actionSubtype);
    if ($normalizedSubtype === '') {
        return '';
    }

    if ($normalizedActionCode === AETHER_SKILL_ACTION_CODE_PSI) {
        foreach (getPsiCategoryDefinitions() as $categoryCode => $definition) {
            if ($normalizedSubtype === $categoryCode) {
                return (string) ($definition['label'] ?? $actionSubtype);
            }

            foreach ((array) ($definition['aliases'] ?? []) as $alias) {
                if ($normalizedSubtype === normalizeSkillActionCode((string) $alias)) {
                    return (string) ($definition['label'] ?? $actionSubtype);
                }
            }
        }
    }

    return ucwords(str_replace('_', ' ', $normalizedSubtype));
}

function fetchAdminActionUseRows(PDO $pdo, array $filters = []): array
{
    $where = [];
    $params = [];

    $idEvent = (int) ($filters['idEvent'] ?? 0);
    if ($idEvent > 0) {
        $where[] = 'u.idEvent = :idEvent';
        $params['idEvent'] = $idEvent;
    }

    $idActionUse = (int) ($filters['idActionUse'] ?? 0);
    if ($idActionUse > 0) {
        $where[] = 'u.id = :idActionUse';
        $params['idActionUse'] = $idActionUse;
    }

    $whereSql = count($where) > 0
        ? ('WHERE ' . implode(' AND ', $where))
        : '';

    return dbAll(
        $pdo,
        'SELECT
            u.id AS idActionUse,
            u.idCharacter,
            u.idEvent,
            u.idSkill,
            u.actionCode,
            u.actionSubtype,
            u.rollBase,
            u.rollModifier,
            u.rollFinal,
            u.resultCode,
            u.resultTitle,
            u.resultText,
            u.stateBefore,
            u.stateAfter,
            u.metadata,
            u.createdAt,
            u.createdBy,
            c.firstName,
            c.lastName,
            c.type AS characterType,
            e.title AS eventTitle,
            s.name AS skillName
         FROM tblCharacterSkillActionUse AS u
         JOIN tblCharacter AS c
           ON c.id = u.idCharacter
         JOIN tblEvent AS e
           ON e.id = u.idEvent
         LEFT JOIN tblSkill AS s
           ON s.id = u.idSkill
         ' . $whereSql . '
         ORDER BY u.createdAt DESC, u.id DESC',
        $params
    );
}

function buildAdminActionUsePayload(array $row): array
{
    $actionCode = (string) ($row['actionCode'] ?? '');
    $actionSubtype = (string) ($row['actionSubtype'] ?? '');
    $stateBefore = decodeAdminJsonColumn($row['stateBefore'] ?? null);
    $stateAfter = decodeAdminJsonColumn($row['stateAfter'] ?? null);
    $metadata = decodeAdminJsonColumn($row['metadata'] ?? null);

    return [
        'idActionUse' => (int) ($row['idActionUse'] ?? 0),
        'idEvent' => (int) ($row['idEvent'] ?? 0),
        'eventTitle' => (string) ($row['eventTitle'] ?? ''),
        'character' => [
            'idCharacter' => (int) ($row['idCharacter'] ?? 0),
            'displayName' => buildAdminSkillHolderDisplayName($row),
            'portraitUrl' => getCharacterPortraitUrl((int) ($row['idCharacter'] ?? 0)),
            'type' => (string) ($row['characterType'] ?? ''),
        ],
        'skill' => [
            'idSkill' => ($row['idSkill'] ?? null) !== null ? (int) $row['idSkill'] : 0,
            'name' => (string) ($row['skillName'] ?? ''),
        ],
        'actionCode' => $actionCode,
        'actionLabel' => formatAdminSkillActionCodeLabel($actionCode),
        'actionSubtype' => $actionSubtype,
        'actionSubtypeLabel' => formatAdminSkillActionSubtypeLabel($actionCode, $actionSubtype),
        'roll' => [
            'base' => ($row['rollBase'] ?? null) !== null ? (int) $row['rollBase'] : null,
            'modifier' => (int) ($row['rollModifier'] ?? 0),
            'final' => ($row['rollFinal'] ?? null) !== null ? (int) $row['rollFinal'] : null,
        ],
        'result' => [
            'code' => (string) ($row['resultCode'] ?? ''),
            'title' => (string) ($row['resultTitle'] ?? ''),
            'text' => (string) ($row['resultText'] ?? ''),
        ],
        'state' => [
            'before' => $stateBefore,
            'after' => $stateAfter,
        ],
        'metadata' => $metadata,
        'createdAt' => (string) ($row['createdAt'] ?? ''),
        'createdBy' => ($row['createdBy'] ?? null) !== null ? (int) $row['createdBy'] : null,
    ];
}

function fetchAdminActionEventUses(PDO $pdo, int $idEvent): array
{
    if ($idEvent <= 0) {
        return [];
    }

    $rows = fetchAdminActionUseRows($pdo, ['idEvent' => $idEvent]);
    $items = [];

    foreach ($rows as $row) {
        $items[] = buildAdminActionUsePayload($row);
    }

    return $items;
}

function fetchAdminActionUseDetail(PDO $pdo, int $idActionUse): ?array
{
    if ($idActionUse <= 0) {
        return null;
    }

    $rows = fetchAdminActionUseRows($pdo, ['idActionUse' => $idActionUse]);
    if (count($rows) < 1) {
        return null;
    }

    return buildAdminActionUsePayload($rows[0]);
}

function normalizeAdminActionUseCreatedAt(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        throw new RuntimeException('Tijdstip is verplicht.');
    }

    $date = date_create_immutable($trimmed);
    if (!$date instanceof DateTimeImmutable) {
        throw new RuntimeException('Ongeldig tijdstip.');
    }

    return $date->format('Y-m-d H:i:s');
}

function updateAdminActionUse(PDO $pdo, int $idActionUse, array $input): ?array
{
    if ($idActionUse <= 0) {
        throw new RuntimeException('Geen geldige actie geselecteerd.');
    }

    if (fetchAdminActionUseDetail($pdo, $idActionUse) === null) {
        throw new RuntimeException('Actie niet gevonden.');
    }

    $actionCode = trim((string) ($input['actionCode'] ?? ''));
    if ($actionCode === '') {
        throw new RuntimeException('Actiecode is verplicht.');
    }

    $createdAt = normalizeAdminActionUseCreatedAt((string) ($input['createdAt'] ?? ''));
    $idSkill = (int) ($input['idSkill'] ?? 0);

    $pdo->prepare(
        'UPDATE tblCharacterSkillActionUse
            SET idSkill = :idSkill,
                actionCode = :actionCode,
                actionSubtype = :actionSubtype,
                rollBase = :rollBase,
                rollModifier = :rollModifier,
                rollFinal = :rollFinal,
                resultTitle = :resultTitle,
                resultText = :resultText,
                createdAt = :createdAt
          WHERE id = :idActionUse'
    )->execute([
        'idSkill' => $idSkill > 0 ? $idSkill : null,
        'actionCode' => $actionCode,
        'actionSubtype' => trim((string) ($input['actionSubtype'] ?? '')) !== ''
            ? trim((string) ($input['actionSubtype'] ?? ''))
            : null,
        'rollBase' => trim((string) ($input['rollBase'] ?? '')) !== ''
            ? (int) $input['rollBase']
            : null,
        'rollModifier' => (int) ($input['rollModifier'] ?? 0),
        'rollFinal' => trim((string) ($input['rollFinal'] ?? '')) !== ''
            ? (int) $input['rollFinal']
            : null,
        'resultTitle' => trim((string) ($input['resultTitle'] ?? '')) !== ''
            ? trim((string) ($input['resultTitle'] ?? ''))
            : null,
        'resultText' => trim((string) ($input['resultText'] ?? '')) !== ''
            ? trim((string) ($input['resultText'] ?? ''))
            : null,
        'createdAt' => $createdAt,
        'idActionUse' => $idActionUse,
    ]);

    return fetchAdminActionUseDetail($pdo, $idActionUse);
}

function deleteAdminActionUse(PDO $pdo, int $idActionUse): void
{
    if ($idActionUse <= 0) {
        throw new RuntimeException('Geen geldige actie geselecteerd.');
    }

    $stmt = $pdo->prepare('DELETE FROM tblCharacterSkillActionUse WHERE id = :idActionUse');
    $stmt->execute(['idActionUse' => $idActionUse]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Actie niet gevonden.');
    }
}

function fetchAdminSkillDetail(PDO $pdo, int $idSkill): ?array
{
    if ($idSkill <= 0) {
        return null;
    }

    $skill = dbOne(
        $pdo,
        'SELECT id, name, description, beginner, professional, master, visibility
           FROM tblSkill
          WHERE id = :idSkill',
        ['idSkill' => $idSkill]
    );

    if ($skill === null) {
        return null;
    }

    $categoryRows = dbAll(
        $pdo,
        'SELECT st.id, st.code, st.name, st.description
           FROM tblLinkSkillType AS lst
           JOIN tblSkillType AS st
             ON st.id = lst.idSkillType
          WHERE lst.idSkill = :idSkill
          ORDER BY st.name ASC, st.id ASC',
        ['idSkill' => $idSkill]
    );

    $specialisationRows = dbAll(
        $pdo,
        'SELECT id, name, kind
           FROM tblSkillSpecialisation
          WHERE idSkill = :idSkill
          ORDER BY name ASC, id ASC',
        ['idSkill' => $idSkill]
    );

    return [
        'idSkill' => (int) ($skill['id'] ?? 0),
        'name' => (string) ($skill['name'] ?? ''),
        'description' => (string) ($skill['description'] ?? ''),
        'beginner' => (string) ($skill['beginner'] ?? ''),
        'professional' => (string) ($skill['professional'] ?? ''),
        'master' => (string) ($skill['master'] ?? ''),
        'isSecret' => isSkillVisibilitySecret($pdo, $skill['visibility'] ?? ''),
        'categories' => array_map(static function (array $row): array {
            return [
                'idSkillType' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
            ];
        }, $categoryRows),
        'categoryIds' => array_values(array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $categoryRows)),
        'specialisations' => array_map(static function (array $row): array {
            return [
                'idSkillSpecialisation' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'specialisation'),
            ];
        }, $specialisationRows),
        'holders' => fetchAdminSkillHolderGroups($pdo, $idSkill),
    ];
}
