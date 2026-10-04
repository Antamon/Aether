<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchTraitCharacter(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare('SELECT id, type, `class`, state, idUser, experienceToTrait FROM tblCharacter WHERE id = :id');
    $stmt->execute(['id' => $characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @return array<string, mixed>|null */
function aetherFetchCharacterTraitLink(PDO $pdo, int $characterId, int $traitId): ?array
{
    $stmt = $pdo->prepare('SELECT id, rankValue FROM tblLinkCharacterTrait WHERE idCharacter = :idCharacter AND idTrait = :idTrait');
    $stmt->execute(['idCharacter' => $characterId, 'idTrait' => $traitId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherCharacterHasProfessionTrait(PDO $pdo, int $characterId): bool
{
    $stmt = $pdo->prepare("SELECT lct.id FROM tblLinkCharacterTrait AS lct JOIN tblTrait AS t ON t.id = lct.idTrait WHERE lct.idCharacter = :idCharacter AND t.`type` = 'profession'");
    $stmt->execute(['idCharacter' => $characterId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherInsertCharacterTraitLink(PDO $pdo, int $characterId, int $traitId, int $rankValue): void
{
    $stmt = $pdo->prepare('INSERT INTO tblLinkCharacterTrait (idCharacter, idTrait, rankValue) VALUES (:idCharacter, :idTrait, :rankValue)');
    $stmt->execute(['idCharacter' => $characterId, 'idTrait' => $traitId, 'rankValue' => $rankValue]);
}

function aetherChangeCharacterTraitLink(PDO $pdo, int $linkId, int $traitId): void
{
    $stmt = $pdo->prepare('UPDATE tblLinkCharacterTrait SET idTrait = :idTrait WHERE id = :id');
    $stmt->execute(['idTrait' => $traitId, 'id' => $linkId]);
}

function aetherDeleteCharacterTraitLink(PDO $pdo, int $linkId): void
{
    $stmt = $pdo->prepare('DELETE FROM tblLinkCharacterTrait WHERE id = :id');
    $stmt->execute(['id' => $linkId]);
}

function aetherUpdateCharacterTraitRank(PDO $pdo, int $linkId, int $rankValue): void
{
    $stmt = $pdo->prepare('UPDATE tblLinkCharacterTrait SET rankValue = :rankValue WHERE id = :id');
    $stmt->execute(['rankValue' => $rankValue, 'id' => $linkId]);
}

/** @return array<string, mixed>|null */
function aetherFetchCharacterTraitCompanyLink(PDO $pdo, int $linkId): ?array
{
    $stmt = $pdo->prepare('SELECT idCompany, COALESCE(extraPercentage, 0) AS extraPercentage FROM tblLinkCharacterTraitCompany WHERE idLinkCharacterTrait = :idLinkCharacterTrait');
    $stmt->execute(['idLinkCharacterTrait' => $linkId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function aetherFetchCompanyTraitShareLinks(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare('SELECT lct.id, lct.idTrait, lct.rankValue, COALESCE(lctc.extraPercentage, 0) AS extraPercentage FROM tblLinkCharacterTraitCompany AS lctc JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait WHERE lctc.idCompany = :idCompany');
    $stmt->execute(['idCompany' => $companyId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
