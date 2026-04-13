<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

function characterLanguageTableExists(PDO $pdo, string $tableName): bool
{
    $row = dbOne(
        $pdo,
        'SELECT 1
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
          LIMIT 1',
        ['table' => $tableName]
    );

    return $row !== null;
}

function characterLanguageSchemaReady(PDO $pdo): bool
{
    return characterLanguageTableExists($pdo, 'tblLanguage')
        && characterLanguageTableExists($pdo, 'tblCharacterLanguage');
}

function getCharacterLanguageClass(PDO $pdo, array $character): string
{
    $characterClass = trim((string) ($character['class'] ?? ''));
    if ($characterClass !== '') {
        return $characterClass;
    }

    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0) {
        return '';
    }

    try {
        $row = dbOne(
            $pdo,
            'SELECT `class`
               FROM tblCharacter
              WHERE id = :id',
            ['id' => $idCharacter]
        );
    } catch (Throwable $e) {
        return '';
    }

    return trim((string) ($row['class'] ?? ''));
}

function characterHasLanguageAccessTrait(PDO $pdo, int $idCharacter): bool
{
    if ($idCharacter <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
               FROM tblLinkCharacterTrait
              WHERE idCharacter = :idCharacter
                AND idTrait IN (173, 178)'
        );
        $stmt->execute(['idCharacter' => $idCharacter]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function canCharacterUseWrittenLanguages(PDO $pdo, array $character): bool
{
    $characterClass = getCharacterLanguageClass($pdo, $character);
    if ($characterClass === 'upper class' || $characterClass === 'middle class') {
        return true;
    }

    if ($characterClass !== 'lower class') {
        return false;
    }

    return characterHasLanguageAccessTrait($pdo, (int) ($character['id'] ?? 0));
}

function getCharacterAcademicusSkillLevel(PDO $pdo, int $idCharacter, ?array $skills = null): int
{
    if ($idCharacter <= 0) {
        return 0;
    }

    if (is_array($skills)) {
        foreach ($skills as $skill) {
            $skillId = (int) ($skill['id'] ?? 0);
            $skillName = mb_strtolower(trim((string) ($skill['name'] ?? '')));
            if ($skillId === 1 || $skillName === 'academicus') {
                return max(0, (int) ($skill['level'] ?? 0));
            }
        }
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(lcs.level), 0)
               FROM tblLinkCharacterSkill AS lcs
               JOIN tblSkill AS s
                 ON s.id = lcs.idSkill
              WHERE lcs.idCharacter = :idCharacter
                AND (lcs.idSkill = 1 OR LOWER(s.name) = \'academicus\')'
        );
        $stmt->execute(['idCharacter' => $idCharacter]);
        return max(0, (int) $stmt->fetchColumn());
    } catch (Throwable $e) {
        return 0;
    }
}

function getCharacterFreeLanguageSlotCount(PDO $pdo, array $character, ?array $skills = null): int
{
    if (!canCharacterUseWrittenLanguages($pdo, $character)) {
        return 0;
    }

    return 2 + getCharacterAcademicusSkillLevel($pdo, (int) ($character['id'] ?? 0), $skills);
}

function getCharacterLanguages(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0 || !characterLanguageSchemaReady($pdo)) {
        return [];
    }

    try {
        return dbAll(
            $pdo,
            'SELECT
                cl.id,
                cl.idLanguage,
                l.name
             FROM tblCharacterLanguage AS cl
             JOIN tblLanguage AS l
               ON l.id = cl.idLanguage
             WHERE cl.idCharacter = :idCharacter
             ORDER BY LOWER(l.name), l.name, cl.id',
            ['idCharacter' => $idCharacter]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function getCharacterLanguageExperienceCost(PDO $pdo, array $character, ?array $skills = null): int
{
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0 || !characterLanguageSchemaReady($pdo)) {
        return 0;
    }

    if (!canCharacterUseWrittenLanguages($pdo, $character)) {
        return 0;
    }

    $selectedLanguageCount = count(getCharacterLanguages($pdo, $idCharacter));
    $freeSlots = getCharacterFreeLanguageSlotCount($pdo, $character, $skills);

    return max(0, $selectedLanguageCount - $freeSlots) * 2;
}

function canCurrentUserManageCharacterLanguages(array $character, string $role, int $currentUserId): bool
{
    if ($role === 'administrator' || $role === 'director') {
        return true;
    }

    return $role === 'participant'
        && (string) ($character['type'] ?? '') === 'player'
        && (int) ($character['idUser'] ?? 0) === $currentUserId;
}

function getCharacterLanguageSummary(PDO $pdo, array $character, ?array $skills = null, ?array $pointSummary = null): array
{
    $schemaReady = characterLanguageSchemaReady($pdo);
    $isVisible = $schemaReady && canCharacterUseWrittenLanguages($pdo, $character);
    $languages = $isVisible ? getCharacterLanguages($pdo, (int) ($character['id'] ?? 0)) : [];
    $freeSlots = $isVisible ? getCharacterFreeLanguageSlotCount($pdo, $character, $skills) : 0;
    $selectedCount = count($languages);
    $paidLanguageCount = max(0, $selectedCount - $freeSlots);
    $remainingExperience = isset($pointSummary['remainingExperience'])
        ? (int) $pointSummary['remainingExperience']
        : 0;
    $canBuyWithExperience = (string) ($character['type'] ?? '') === 'player'
        ? $remainingExperience >= 2
        : true;

    return [
        'isVisible' => $isVisible,
        'languages' => array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'idLanguage' => (int) ($row['idLanguage'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }, $languages),
        'freeSlots' => $freeSlots,
        'selectedCount' => $selectedCount,
        'usedFreeSlots' => min($selectedCount, $freeSlots),
        'paidLanguageCount' => $paidLanguageCount,
        'languageExperienceCost' => $paidLanguageCount * 2,
        'remainingExperience' => $remainingExperience,
        'canBuyWithExperience' => $canBuyWithExperience,
        'canAddLanguage' => $isVisible && ($selectedCount < $freeSlots || $canBuyWithExperience),
    ];
}
