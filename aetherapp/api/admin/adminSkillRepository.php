<?php
declare(strict_types=1);

require_once __DIR__ . '/adminUtils.php';

function aetherAdminSkillRequestAlreadyClaimed(PDO $pdo, int $userId, string $requestKey): bool
{
    return dbOne($pdo,
        'SELECT id FROM tblApiIdempotency WHERE idUser = :idUser AND operation = :operation AND requestKey = :requestKey LIMIT 1',
        ['idUser' => $userId, 'operation' => 'admin.saveSkill', 'requestKey' => $requestKey]) !== null;
}

function aetherAdminSkillRow(PDO $pdo, int $id, bool $lock = false): ?array
{
    return dbOne($pdo, 'SELECT id FROM tblSkill WHERE id = :idSkill' . ($lock ? ' FOR UPDATE' : ''), ['idSkill' => $id]);
}

function aetherAdminSkillNameExists(PDO $pdo, string $name, int $except = 0): bool
{
    return dbOne($pdo,
        'SELECT id FROM tblSkill WHERE LOWER(name) = LOWER(:name) AND id <> :idSkill LIMIT 1',
        ['name' => $name, 'idSkill' => $except]) !== null;
}

function aetherAdminSkillSpecialisations(PDO $pdo, int $idSkill, bool $lock = true): array
{
    return dbAll($pdo,
        'SELECT id, name, kind FROM tblSkillSpecialisation WHERE idSkill = :idSkill' . ($lock ? ' FOR UPDATE' : ''),
        ['idSkill' => $idSkill]);
}

function aetherAdminSpecialisationHasDependents(PDO $pdo, int $id, bool $lock = true): bool
{
    foreach (['tblCharacterSpecialisation', 'tblCompanyPersonnelSkillSpecialisation'] as $table) {
        // Table names are a fixed server-side list, never request data.
        if (dbOne($pdo, "SELECT id FROM {$table} WHERE idSkillSpecialisation = :id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]) !== null) {
            return true;
        }
    }
    return false;
}

function aetherAdminSkillTypeRow(PDO $pdo, int $id, bool $lock = false): ?array
{
    return dbOne($pdo, 'SELECT id FROM tblSkillType WHERE id = :idSkillType' . ($lock ? ' FOR UPDATE' : ''), ['idSkillType' => $id]);
}

function aetherAdminSkillTypeNameExists(PDO $pdo, string $name, int $except = 0): bool
{
    return dbOne($pdo,
        'SELECT id FROM tblSkillType WHERE LOWER(name) = LOWER(:name) AND id <> :idSkillType LIMIT 1',
        ['name' => $name, 'idSkillType' => $except]) !== null;
}
