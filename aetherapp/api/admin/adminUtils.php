<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../characters/characterPointUtils.php';

function requirePrivilegedAdminAccess(PDO $pdo): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $currentUserId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
    if ($currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Geen gebruiker in sessie.']);
        exit;
    }

    $currentUserRole = getCurrentUserRole($pdo);
    if (!isPrivilegedUserRole($currentUserRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om de adminpagina te beheren.']);
        exit;
    }

    return [
        'idUser' => $currentUserId,
        'role' => $currentUserRole,
    ];
}

function requireAdministratorAccess(PDO $pdo): array
{
    $user = requirePrivilegedAdminAccess($pdo);
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
    try {
        $rows = dbAll(
            $pdo,
            'SELECT id, code, name, description
               FROM tblSkillType
              ORDER BY name ASC, id ASC'
        );
    } catch (Throwable $e) {
        return [];
    }

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
