<?php
declare(strict_types=1);

function aetherPersonnelCharacterExists(PDO $pdo, int $idCharacter): bool
{
    $stmt = $pdo->prepare("SELECT id FROM tblCharacter WHERE id = :idCharacter AND `state` <> 'draft' FOR UPDATE");
    $stmt->execute(['idCharacter' => $idCharacter]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherPersonnelSkillExists(PDO $pdo, int $idSkill): bool
{
    $stmt = $pdo->prepare('SELECT id FROM tblSkill WHERE id = :idSkill');
    $stmt->execute(['idSkill' => $idSkill]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherPersonnelSpecialisationForSkill(PDO $pdo, int $idSkillSpecialisation, int $idSkill): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM tblSkillSpecialisation WHERE id = :idSkillSpecialisation AND idSkill = :idSkill');
    $stmt->execute(['idSkillSpecialisation' => $idSkillSpecialisation, 'idSkill' => $idSkill]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : (int) $row['id'];
}

function aetherPersonnelSpecialisationByName(PDO $pdo, int $idSkill, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM tblSkillSpecialisation WHERE idSkill = :idSkill AND LOWER(name) = LOWER(:name) LIMIT 1');
    $stmt->execute(['idSkill' => $idSkill, 'name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : (int) $row['id'];
}

function aetherPersonnelInsertSpecialisation(PDO $pdo, int $idSkill, string $name): int
{
    $stmt = $pdo->prepare('INSERT INTO tblSkillSpecialisation (idSkill, name, kind) VALUES (:idSkill, :name, :kind)');
    $stmt->execute(['idSkill' => $idSkill, 'name' => $name, 'kind' => 'specialisation']);
    return (int) $pdo->lastInsertId();
}

function aetherPersonnelDeleteCompanyLinks(PDO $pdo, int $idCompany): void
{
    foreach ([
        'DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation cpss JOIN tblCompanyPersonnelSkill cps ON cps.id = cpss.idCompanyPersonnelSkill JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel WHERE cp.idCompany = :idCompany',
        'DELETE cps FROM tblCompanyPersonnelSkill cps JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel WHERE cp.idCompany = :idCompany',
        'DELETE FROM tblCompanyPersonnel WHERE idCompany = :idCompany',
    ] as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['idCompany' => $idCompany]);
    }
}

function aetherPersonnelInsert(PDO $pdo, int $idCompany, array $entry): int
{
    $stmt = $pdo->prepare('INSERT INTO tblCompanyPersonnel (idCompany, idCharacter, importance, salaryIncreasePercentage) VALUES (:idCompany, :idCharacter, :importance, :salaryIncreasePercentage)');
    $stmt->execute([
        'idCompany' => $idCompany, 'idCharacter' => $entry['idCharacter'],
        'importance' => $entry['importance'], 'salaryIncreasePercentage' => $entry['salaryIncreasePercentage'],
    ]);
    return (int) $pdo->lastInsertId();
}

function aetherPersonnelInsertSkill(PDO $pdo, int $idCompanyPersonnel, array $skill): int
{
    $stmt = $pdo->prepare('INSERT INTO tblCompanyPersonnelSkill (idCompanyPersonnel, idSkill, level) VALUES (:idCompanyPersonnel, :idSkill, :level)');
    $stmt->execute(['idCompanyPersonnel' => $idCompanyPersonnel, 'idSkill' => $skill['idSkill'], 'level' => $skill['level']]);
    return (int) $pdo->lastInsertId();
}

function aetherPersonnelInsertSkillSpecialisation(PDO $pdo, int $idCompanyPersonnelSkill, int $idSkillSpecialisation): void
{
    $stmt = $pdo->prepare('INSERT INTO tblCompanyPersonnelSkillSpecialisation (idCompanyPersonnelSkill, idSkillSpecialisation) VALUES (:idCompanyPersonnelSkill, :idSkillSpecialisation)');
    $stmt->execute(['idCompanyPersonnelSkill' => $idCompanyPersonnelSkill, 'idSkillSpecialisation' => $idSkillSpecialisation]);
}
