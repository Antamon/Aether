<?php
declare(strict_types=1);

/** @return list<array<string, mixed>> */
function aetherFetchCharacterTieRows(PDO $pdo, int $characterId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            t.id,
            t.idCharacterTarget,
            t.relationType,
            t.description,
            c.firstName,
            c.lastName,
            c.title,
            c.class,
            EXISTS(
                SELECT 1 FROM tblCharacterTie AS reverseTie
                 WHERE reverseTie.idCharacter = t.idCharacterTarget
                   AND reverseTie.idCharacterTarget = t.idCharacter
                   AND reverseTie.relationType = 'superior'
            ) AS hasReverseSuperior,
            EXISTS(
                SELECT 1 FROM tblCharacterTie AS reverseTie
                 WHERE reverseTie.idCharacter = t.idCharacterTarget
                   AND reverseTie.idCharacterTarget = t.idCharacter
                   AND reverseTie.relationType = 'landlord'
            ) AS hasReverseLandlord,
            EXISTS(
                SELECT 1 FROM tblCharacterTie AS reverseTie
                 WHERE reverseTie.idCharacter = t.idCharacterTarget
                   AND reverseTie.idCharacterTarget = t.idCharacter
                   AND reverseTie.relationType = 'household_staff'
            ) AS hasReverseHouseholdStaff,
            EXISTS(
                SELECT 1 FROM tblCharacterTie AS reverseTie
                 WHERE reverseTie.idCharacter = t.idCharacterTarget
                   AND reverseTie.idCharacterTarget = t.idCharacter
                   AND reverseTie.relationType = 'spouse'
            ) AS hasReverseSpouse
         FROM tblCharacterTie t
         JOIN tblCharacter c ON c.id = t.idCharacterTarget
         WHERE t.idCharacter = :idCharacter
         ORDER BY c.firstName, c.lastName"
    );
    $stmt->execute([':idCharacter' => $characterId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function aetherFetchCharacterTieOptionRows(PDO $pdo, bool $includeAllCharacters): array
{
    $sql = 'SELECT id, firstName, lastName, title, class FROM tblCharacter';
    if (!$includeAllCharacters) {
        $sql .= " WHERE type = 'player' AND state = 'active'";
    }
    $sql .= ' ORDER BY firstName, lastName';

    return dbAll($pdo, $sql);
}

/** @return array<string, mixed>|null */
function aetherFetchCharacterTieTarget(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, `class`, type, state
           FROM tblCharacter
          WHERE id = :id'
    );
    $stmt->execute([':id' => $characterId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    return $character ?: null;
}

/** @return array{id: mixed}|null */
function aetherFetchOwnedCharacterTie(PDO $pdo, int $tieId, int $characterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id
           FROM tblCharacterTie
          WHERE id = :idTie
            AND idCharacter = :idCharacter'
    );
    $stmt->execute([':idTie' => $tieId, ':idCharacter' => $characterId]);
    $tie = $stmt->fetch(PDO::FETCH_ASSOC);

    return $tie ?: null;
}

function aetherInsertCharacterTie(
    PDO $pdo,
    int $characterId,
    int $otherCharacterId,
    string $relationType,
    string $description,
    int $userId
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacterTie
            (idCharacter, idCharacterTarget, relationType, description, updatedAt, updatedBy, createdAt, createdBy)
         VALUES
            (:idCharacter, :idOtherCharacter, :relationType, :description, NOW(), :updatedBy, NOW(), :createdBy)'
    );
    $stmt->execute([
        ':idCharacter' => $characterId,
        ':idOtherCharacter' => $otherCharacterId,
        ':relationType' => $relationType,
        ':description' => $description,
        ':updatedBy' => $userId,
        ':createdBy' => $userId,
    ]);

    return (int) $pdo->lastInsertId();
}

function aetherUpdateCharacterTie(
    PDO $pdo,
    int $tieId,
    int $characterId,
    int $otherCharacterId,
    string $relationType,
    string $description,
    int $userId
): void {
    $stmt = $pdo->prepare(
        'UPDATE tblCharacterTie
            SET idCharacterTarget = :idOtherCharacter,
                relationType = :relationType,
                description = :description,
                updatedAt = NOW(),
                updatedBy = :updatedBy
          WHERE id = :idTie
            AND idCharacter = :idCharacter'
    );
    $stmt->execute([
        ':idOtherCharacter' => $otherCharacterId,
        ':relationType' => $relationType,
        ':description' => $description,
        ':updatedBy' => $userId,
        ':idTie' => $tieId,
        ':idCharacter' => $characterId,
    ]);
}

function aetherDeleteCharacterTieRecord(PDO $pdo, int $tieId, int $characterId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM tblCharacterTie
          WHERE id = :idTie
            AND idCharacter = :idCharacter'
    );
    $stmt->execute([':idTie' => $tieId, ':idCharacter' => $characterId]);
}

function aetherCharacterTieExists(
    PDO $pdo,
    int $characterId,
    int $targetCharacterId,
    string $relationType
): bool {
    $stmt = $pdo->prepare(
        'SELECT 1
           FROM tblCharacterTie
          WHERE idCharacter = :idCharacter
            AND idCharacterTarget = :idCharacterTarget
            AND relationType = :relationType
          LIMIT 1'
    );
    $stmt->execute([
        ':idCharacter' => $characterId,
        ':idCharacterTarget' => $targetCharacterId,
        ':relationType' => $relationType,
    ]);

    return (bool) $stmt->fetchColumn();
}

/** @return array{street: string, houseNumber: string, postalCode: string, municipality: string}|null */
function aetherFetchCharacterTieAddress(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT street, houseNumber, postalCode, municipality
           FROM tblCharacter
          WHERE id = :idCharacter'
    );
    $stmt->execute([':idCharacter' => $characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'street' => (string) ($row['street'] ?? ''),
        'houseNumber' => (string) ($row['houseNumber'] ?? ''),
        'postalCode' => (string) ($row['postalCode'] ?? ''),
        'municipality' => (string) ($row['municipality'] ?? ''),
    ];
}

/** @param array{street: string, houseNumber: string, postalCode: string, municipality: string} $address */
function aetherUpdateCharacterTieAddress(PDO $pdo, int $characterId, array $address): void
{
    $stmt = $pdo->prepare(
        'UPDATE tblCharacter
            SET street = :street,
                houseNumber = :houseNumber,
                postalCode = :postalCode,
                municipality = :municipality
          WHERE id = :idCharacter'
    );
    $stmt->execute([
        ':street' => $address['street'],
        ':houseNumber' => $address['houseNumber'],
        ':postalCode' => $address['postalCode'],
        ':municipality' => $address['municipality'],
        ':idCharacter' => $characterId,
    ]);
}
