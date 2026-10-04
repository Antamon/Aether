<?php
declare(strict_types=1);

require_once __DIR__ . '/adminSkillRepository.php';

final class AdminSkillProblem extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function aetherAdminSkillCatalog(PDO $pdo): array
{
    return ['skills' => fetchAdminSkillList($pdo), 'skillTypes' => fetchAdminSkillTypeOptions($pdo)];
}

function aetherAdminCreateSkill(PDO $pdo, string $name): array
{
    $pdo->beginTransaction();
    try {
        if (aetherAdminSkillNameExists($pdo, $name)) throw new AdminSkillProblem(409, 'Er bestaat al een vaardigheid met deze naam.');
        $visibility = getSkillVisibilityStorageMap($pdo)['public'];
        $pdo->prepare('INSERT INTO tblSkill (name, description, beginner, professional, master, visibility) VALUES (:name, :description, :beginner, :professional, :master, :visibility)')
            ->execute(['name' => $name, 'description' => '', 'beginner' => '', 'professional' => '', 'master' => '', 'visibility' => $visibility]);
        $id = (int) $pdo->lastInsertId();
        $result = ['skill' => fetchAdminSkillDetail($pdo, $id), 'skills' => fetchAdminSkillList($pdo)];
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Read-only rejection before an idempotency claim; repeated under lock in the mutation. */
function aetherAdminPreflightSaveSkill(PDO $pdo, array $data, bool $lock = false): array
{
    $id = $data['idSkill'];
    if (aetherAdminSkillRow($pdo, $id, $lock) === null) throw new AdminSkillProblem(404, 'Vaardigheid niet gevonden.');
    if (aetherAdminSkillNameExists($pdo, $data['name'], $id)) throw new AdminSkillProblem(409, 'Er bestaat al een andere vaardigheid met deze naam.');
    $types = [];
    foreach (fetchAdminSkillTypeOptions($pdo) as $type) $types[$type['idSkillType']] = $type;
    foreach ($data['categoryIds'] as $typeId) if (!isset($types[$typeId])) throw new AdminSkillProblem(422, 'Ongeldige categorie.');
    $existing = [];
    foreach (aetherAdminSkillSpecialisations($pdo, $id, $lock) as $row) $existing[(int)$row['id']] = $row;
    $seenIds = $seenNames = [];
    foreach ($data['specialisations'] as $spec) {
        $specId = $spec['idSkillSpecialisation']; $key = normalizeAdminTextKey($spec['name']);
        if ($key === '' || isset($seenNames[$key]) || ($specId > 0 && (!isset($existing[$specId]) || isset($seenIds[$specId])))) {
            throw new AdminSkillProblem(422, 'Ongeldige of dubbele specialisatie.');
        }
        $seenNames[$key] = true;
        if ($specId > 0) $seenIds[$specId] = true;
    }
    foreach ($existing as $specId => $_) {
        if (!isset($seenIds[$specId]) && aetherAdminSpecialisationHasDependents($pdo, $specId, $lock)) {
            throw new AdminSkillProblem(409, 'Specialisatie is nog gekoppeld aan characters of companypersoneel.');
        }
    }
    return [$types, $existing, $seenIds];
}

/** $manageTransaction=false when the shared idempotency layer owns the transaction. */
function aetherAdminSaveSkill(PDO $pdo, array $data, bool $manageTransaction = true): array
{
    $id = $data['idSkill'];
    if ($manageTransaction) $pdo->beginTransaction();
    try {
        [$types, $existing, $seenIds] = aetherAdminPreflightSaveSkill($pdo, $data, true);
        $kind = 'specialisation';
        foreach ($data['categoryIds'] as $typeId) {
            if (normalizeAdminTextKey((string) $types[$typeId]['code']) === 'discipline') $kind = 'discipline';
        }
        $visibilityMap = getSkillVisibilityStorageMap($pdo);
        $pdo->prepare('UPDATE tblSkill SET name = :name, description = :description, beginner = :beginner, professional = :professional, master = :master, visibility = :visibility WHERE id = :idSkill')
            ->execute([
                'idSkill' => $id, 'name' => $data['name'], 'description' => $data['description'],
                'beginner' => $data['beginner'], 'professional' => $data['professional'], 'master' => $data['master'],
                'visibility' => $data['isSecret'] ? $visibilityMap['secret'] : $visibilityMap['public'],
            ]);
        $pdo->prepare('DELETE FROM tblLinkSkillType WHERE idSkill = :idSkill')->execute(['idSkill' => $id]);
        $insertType = $pdo->prepare('INSERT INTO tblLinkSkillType (idSkill, idSkillType) VALUES (:idSkill, :idSkillType)');
        foreach ($data['categoryIds'] as $typeId) $insertType->execute(['idSkill' => $id, 'idSkillType' => $typeId]);
        $deleteSpec = $pdo->prepare('DELETE FROM tblSkillSpecialisation WHERE id = :idSkillSpecialisation AND idSkill = :idSkill');
        foreach ($existing as $specId => $_row) {
            if (!isset($seenIds[$specId])) $deleteSpec->execute(['idSkillSpecialisation' => $specId, 'idSkill' => $id]);
        }
        $updateSpec = $pdo->prepare('UPDATE tblSkillSpecialisation SET name = :name, kind = :kind WHERE id = :idSkillSpecialisation AND idSkill = :idSkill');
        $insertSpec = $pdo->prepare('INSERT INTO tblSkillSpecialisation (idSkill, name, kind) VALUES (:idSkill, :name, :kind)');
        foreach ($data['specialisations'] as $spec) {
            $specId = $spec['idSkillSpecialisation'];
            $specKind = $specId > 0 ? (string) $existing[$specId]['kind'] : $kind;
            if ($specId > 0) $updateSpec->execute(['idSkillSpecialisation' => $specId, 'idSkill' => $id, 'name' => $spec['name'], 'kind' => $specKind]);
            else $insertSpec->execute(['idSkill' => $id, 'name' => $spec['name'], 'kind' => $specKind]);
        }
        $result = ['skill' => fetchAdminSkillDetail($pdo, $id), 'skills' => fetchAdminSkillList($pdo)];
        if ($manageTransaction) $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($manageTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function aetherAdminSaveSkillType(PDO $pdo, array $data): array
{
    $action = $data['action'];
    $id = $data['idSkillType'];
    $name = $data['name'];
    $pdo->beginTransaction();
    try {
        if ($action !== 'create' && aetherAdminSkillTypeRow($pdo, $id, true) === null) throw new AdminSkillProblem(404, 'Categorie niet gevonden.');
        if ($action !== 'delete' && aetherAdminSkillTypeNameExists($pdo, $name, $id)) throw new AdminSkillProblem(409, 'Er bestaat al een categorie met deze naam.');
        if ($action === 'create') {
            $pdo->prepare('INSERT INTO tblSkillType (code, name, description) VALUES (:code, :name, :description)')
                ->execute(['code' => buildUniqueAdminSkillTypeCode($pdo, $name), 'name' => $name, 'description' => '']);
        } elseif ($action === 'update') {
            $pdo->prepare('UPDATE tblSkillType SET name = :name WHERE id = :idSkillType')->execute(['name' => $name, 'idSkillType' => $id]);
        } else {
            $pdo->prepare('DELETE FROM tblLinkSkillType WHERE idSkillType = :idSkillType')->execute(['idSkillType' => $id]);
            $pdo->prepare('DELETE FROM tblSkillType WHERE id = :idSkillType')->execute(['idSkillType' => $id]);
        }
        $result = ['skillTypes' => fetchAdminSkillTypeOptions($pdo)];
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
