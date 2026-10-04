<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchLanguageCharacter(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare('SELECT id, idUser, type, `class`, experienceToTrait, physicalHealth, mentalHealth FROM tblCharacter WHERE id = :id');
    $stmt->execute(['id' => $characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @return array<string, mixed>|null */
function aetherFetchLanguageDefinition(PDO $pdo, int $languageId): ?array
{
    $stmt = $pdo->prepare('SELECT id, name FROM tblLanguage WHERE id = :id');
    $stmt->execute(['id' => $languageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @return array<string, mixed>|null */
function aetherFindLanguageDefinitionByName(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare('SELECT id, name FROM tblLanguage WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherCharacterLanguageIsLinked(PDO $pdo, int $characterId, int $languageId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM tblCharacterLanguage WHERE idCharacter = :idCharacter AND idLanguage = :idLanguage');
    $stmt->execute(['idCharacter' => $characterId, 'idLanguage' => $languageId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherInsertLanguageDefinition(PDO $pdo, string $name, int $userId): int
{
    $stmt = $pdo->prepare('INSERT INTO tblLanguage (name, createdAt, createdBy) VALUES (:name, NOW(), :createdBy)');
    $stmt->execute(['name' => $name, 'createdBy' => $userId > 0 ? $userId : null]);
    return (int) $pdo->lastInsertId();
}

function aetherInsertCharacterLanguage(PDO $pdo, int $characterId, int $languageId, int $userId): void
{
    $stmt = $pdo->prepare('INSERT INTO tblCharacterLanguage (idCharacter, idLanguage, createdAt, createdBy) VALUES (:idCharacter, :idLanguage, NOW(), :createdBy)');
    $stmt->execute(['idCharacter' => $characterId, 'idLanguage' => $languageId, 'createdBy' => $userId > 0 ? $userId : null]);
}
