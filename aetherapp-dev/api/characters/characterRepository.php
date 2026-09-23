<?php
declare(strict_types=1);

/** @return array<string, string> */
function aetherCharacterUpdateColumnMap(): array
{
    return [
        'idUser' => '`idUser`',
        'type' => '`type`',
        'state' => '`state`',
        'firstName' => '`firstName`',
        'lastName' => '`lastName`',
        'class' => '`class`',
        'birthDate' => '`birthDate`',
        'birthPlace' => '`birthPlace`',
        'nationality' => '`nationality`',
        'stateRegisterNumber' => '`stateRegisterNumber`',
        'street' => '`street`',
        'houseNumber' => '`houseNumber`',
        'municipality' => '`municipality`',
        'postalCode' => '`postalCode`',
        'title' => '`title`',
        'maritalStatus' => '`maritalStatus`',
        'experienceToTrait' => '`experienceToTrait`',
        'physicalHealth' => '`physicalHealth`',
        'mentalHealth' => '`mentalHealth`',
        'physicalHealthFree' => '`physicalHealthFree`',
        'mentalHealthFree' => '`mentalHealthFree`',
        'bankaccount' => '`bankaccount`',
        'securitiesaccount' => '`securitiesaccount`',
    ];
}

function aetherFetchCharacterForUpdate(PDO $pdo, int $characterId, bool $lock = false): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, `class`, type, state, idUser, experienceToTrait,
                physicalHealth, mentalHealth, physicalHealthFree, mentalHealthFree,
                bankaccount, securitiesaccount
           FROM tblCharacter
          WHERE id = :id' . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute(['id' => $characterId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    return $character ?: null;
}

/** @param array<string, mixed> $fields */
function aetherUpdateCharacterRecord(PDO $pdo, int $characterId, array $fields): int
{
    $columnMap = aetherCharacterUpdateColumnMap();
    $setParts = [];
    foreach (array_keys($fields) as $field) {
        if (!isset($columnMap[$field])) {
            throw new LogicException("Geen vaste kolommapping voor {$field}.");
        }
        $setParts[] = $columnMap[$field] . " = :{$field}";
    }

    $stmt = $pdo->prepare(
        'UPDATE tblCharacter SET ' . implode(', ', $setParts) . ' WHERE id = :id'
    );
    $parameters = $fields;
    $parameters['id'] = $characterId;
    $stmt->execute($parameters);

    return $stmt->rowCount();
}

function aetherFetchCharacterAddressForSync(PDO $pdo, int $characterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT street, houseNumber, postalCode, municipality
           FROM tblCharacter
          WHERE id = :idCharacter'
    );
    $stmt->execute(['idCharacter' => $characterId]);
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

function aetherLandlordHasConfirmedHouseholdStaff(PDO $pdo, int $characterId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
           FROM tblCharacterTie AS landlordTie
           JOIN tblCharacterTie AS staffTie
             ON staffTie.idCharacter = landlordTie.idCharacterTarget
            AND staffTie.idCharacterTarget = landlordTie.idCharacter
            AND staffTie.relationType = 'household_staff'
          WHERE landlordTie.idCharacter = :idLandlordCharacter
            AND landlordTie.relationType = 'landlord'
          LIMIT 1"
    );
    $stmt->execute(['idLandlordCharacter' => $characterId]);

    return (bool) $stmt->fetchColumn();
}

function aetherSyncConfirmedHouseholdStaffAddresses(PDO $pdo, int $landlordCharacterId): void
{
    $address = aetherFetchCharacterAddressForSync($pdo, $landlordCharacterId);
    if ($address === null) {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE tblCharacter AS c
           JOIN tblCharacterTie AS landlordTie
             ON landlordTie.idCharacterTarget = c.id
            AND landlordTie.idCharacter = :idLandlordCharacter
            AND landlordTie.relationType = 'landlord'
           JOIN tblCharacterTie AS staffTie
             ON staffTie.idCharacter = c.id
            AND staffTie.idCharacterTarget = :idLandlordCharacter
            AND staffTie.relationType = 'household_staff'
            SET c.street = :street,
                c.houseNumber = :houseNumber,
                c.postalCode = :postalCode,
                c.municipality = :municipality"
    );
    $stmt->execute([
        'idLandlordCharacter' => $landlordCharacterId,
        'street' => $address['street'],
        'houseNumber' => $address['houseNumber'],
        'postalCode' => $address['postalCode'],
        'municipality' => $address['municipality'],
    ]);
}

function aetherPruneCharacterClassTraits(PDO $pdo, int $characterId): void
{
    $stmt = $pdo->prepare(
        "DELETE lct
           FROM tblLinkCharacterTrait AS lct
           JOIN tblTrait AS t
             ON t.id = lct.idTrait
          WHERE lct.idCharacter = :idCharacter
            AND t.`class` <> 'all'"
    );
    $stmt->execute(['idCharacter' => $characterId]);
}
