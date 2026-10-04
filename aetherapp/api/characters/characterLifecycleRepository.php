<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchCharacterForDeletion(PDO $pdo, int $characterId, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT id, idUser, type, state, firstName, lastName FROM tblCharacter WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute(['id' => $characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherCharacterLifecycleTableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
          LIMIT 1'
    );
    $stmt->execute(['table' => $tableName]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherDeleteCharacterCompanyPersonnel(PDO $pdo, int $characterId): void
{
    if (!aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnel')) return;
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnelSkillSpecialisation')
        && aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnelSkill')) {
        $stmt = $pdo->prepare(
            'DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation cpss
              INNER JOIN tblCompanyPersonnelSkill cps ON cps.id = cpss.idCompanyPersonnelSkill
              INNER JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel
             WHERE cp.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnelSkill')) {
        $stmt = $pdo->prepare(
            'DELETE cps FROM tblCompanyPersonnelSkill cps
              INNER JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel
             WHERE cp.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $characterId]);
    }
    $stmt = $pdo->prepare('DELETE FROM tblCompanyPersonnel WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $characterId]);
}

function aetherDeleteCharacterShareLinks(PDO $pdo, int $characterId): void
{
    if (!aetherCharacterLifecycleTableExists($pdo, 'tblLinkCharacterTrait')
        || !aetherCharacterLifecycleTableExists($pdo, 'tblLinkCharacterTraitCompany')) return;
    $stmt = $pdo->prepare(
        'DELETE lctc FROM tblLinkCharacterTraitCompany lctc
          INNER JOIN tblLinkCharacterTrait lct ON lct.id = lctc.idLinkCharacterTrait
         WHERE lct.idCharacter = :idCharacter'
    );
    $stmt->execute(['idCharacter' => $characterId]);
}

function aetherDeleteCharacterManualRelations(PDO $pdo, int $characterId): void
{
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanySnapshotPayout')) {
        $stmt = $pdo->prepare('DELETE FROM tblCompanySnapshotPayout WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterBankTransaction')) {
        $stmt = $pdo->prepare(
            'DELETE FROM tblCharacterBankTransaction
              WHERE idSourceCharacter = :idSourceCharacter OR idTargetCharacter = :idTargetCharacter'
        );
        $stmt->execute(['idSourceCharacter' => $characterId, 'idTargetCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterSecuritiesTransaction')) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterSecuritiesTransaction WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterEconomySnapshot')) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterEconomySnapshot WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    aetherDeleteCharacterCompanyPersonnel($pdo, $characterId);
    aetherDeleteCharacterShareLinks($pdo, $characterId);
    if (aetherCharacterLifecycleTableExists($pdo, 'tblLinkCharacterSkill')) {
        $stmt = $pdo->prepare('DELETE FROM tblLinkCharacterSkill WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterLanguage')) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterLanguage WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }

    // Avoid a live character continuing to point at the character that is about to be removed.
    $stmt = $pdo->prepare(
        "UPDATE tblCharacter
            SET securitiesManagerType = 'self', securitiesManagerCharacterId = NULL
          WHERE securitiesManagerCharacterId = :idCharacter"
    );
    $stmt->execute(['idCharacter' => $characterId]);
}

function aetherDeleteCharacterRecord(PDO $pdo, int $characterId): void
{
    $stmt = $pdo->prepare('DELETE FROM tblCharacter WHERE id = :idCharacter');
    $stmt->execute(['idCharacter' => $characterId]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Het personage kon niet verwijderd worden.');
    }
}
