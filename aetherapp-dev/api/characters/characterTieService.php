<?php
declare(strict_types=1);

require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterTieRepository.php';

final class AetherCharacterTieException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

/** @return array<string, string> */
function aetherCharacterTieLabels(): array
{
    return [
        'superior' => 'Superior',
        'dependent' => 'Dependent',
        'landlord' => 'Landlord',
        'household_staff' => 'Household staff',
        'spouse' => 'Spouse',
        'ally' => 'Ally',
        'adversary' => 'Adversary',
        'person_of_interest' => 'Person of interest',
    ];
}

function aetherCharacterTieDisplayName(array $character): string
{
    if (($character['class'] ?? '') === 'upper class' && !empty($character['title'])) {
        return trim($character['title'] . ' ' . $character['firstName'] . ' ' . $character['lastName']);
    }

    return trim($character['firstName'] . ' ' . $character['lastName']);
}

/** @return list<array<string, mixed>> */
function aetherBuildCharacterTieList(PDO $pdo, int $characterId): array
{
    $labels = aetherCharacterTieLabels();

    return array_map(static function (array $row) use ($pdo, $labels): array {
        $otherCharacter = [
            'id' => (int) $row['idCharacterTarget'],
            'class' => (string) ($row['class'] ?? ''),
        ];

        return [
            'id' => (int) $row['id'],
            'idOtherCharacter' => (int) $row['idCharacterTarget'],
            'relationType' => $row['relationType'],
            'relationTypeLabel' => $labels[$row['relationType']] ?? $row['relationType'],
            'description' => $row['description'],
            'otherName' => aetherCharacterTieDisplayName($row),
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'otherClass' => (string) ($row['class'] ?? ''),
            'otherRecurringIncomeTotal' => getCharacterRecurringIncomeTotal($pdo, $otherCharacter),
            'otherMiddleClassLivingStandardIncome' => getCharacterMiddleClassLivingStandardIncome($pdo, $otherCharacter),
            'otherUpperClassLivingStandardTier' => getCharacterUpperClassLivingStandardTier($pdo, $otherCharacter),
            'portraitUrl' => getCharacterPortraitUrl((int) $row['idCharacterTarget']),
            'hasReverseSuperior' => (bool) $row['hasReverseSuperior'],
            'hasReverseLandlord' => (bool) $row['hasReverseLandlord'],
            'hasReverseHouseholdStaff' => (bool) $row['hasReverseHouseholdStaff'],
            'hasReverseSpouse' => (bool) $row['hasReverseSpouse'],
        ];
    }, aetherFetchCharacterTieRows($pdo, $characterId));
}

/** @param array<string, mixed> $currentUser
 *  @return list<array<string, mixed>>
 */
function aetherBuildCharacterTieOptions(PDO $pdo, array $currentUser): array
{
    $rows = aetherFetchCharacterTieOptionRows(
        $pdo,
        aetherIsPrivilegedRole((string) $currentUser['role'])
    );

    return array_map(static function (array $row) use ($pdo): array {
        $character = ['id' => (int) $row['id'], 'class' => (string) ($row['class'] ?? '')];
        return [
            'id' => (int) $row['id'],
            'displayName' => aetherCharacterTieDisplayName($row),
            'class' => (string) ($row['class'] ?? ''),
            'recurringIncomeTotal' => getCharacterRecurringIncomeTotal($pdo, $character),
            'canBeLandlord' => canCharacterBeLandlord($pdo, $character),
        ];
    }, $rows);
}

/**
 * @param array<string, mixed> $currentUser
 * @param array<string, mixed> $ownerCharacter
 * @param array<string, mixed> $input
 * @return array{idTie: int, syncedAddress: array<string, mixed>|null}
 */
function aetherSaveCharacterTie(
    PDO $pdo,
    array $currentUser,
    array $ownerCharacter,
    array $input
): array {
    $characterId = (int) $input['idCharacter'];
    $tieId = (int) ($input['idTie'] ?? 0);
    $otherCharacterId = (int) $input['idOtherCharacter'];
    $relationType = (string) $input['relationType'];

    if ($characterId === $otherCharacterId) {
        throw new AetherCharacterTieException(400, 'Een tie met hetzelfde personage is niet toegelaten.');
    }

    $targetCharacter = aetherFetchCharacterTieTarget($pdo, $otherCharacterId);
    if ($targetCharacter === null) {
        throw new AetherCharacterTieException(404, 'Doelpersonage niet gevonden.');
    }
    if (!aetherIsPrivilegedRole((string) $currentUser['role'])
        && ((string) ($targetCharacter['type'] ?? '') !== 'player'
            || (string) ($targetCharacter['state'] ?? '') !== 'active')) {
        throw new AetherCharacterTieException(403, 'Je hebt geen rechten om dit doelpersonage te koppelen.');
    }
    if ($tieId > 0 && aetherFetchOwnedCharacterTie($pdo, $tieId, $characterId) === null) {
        throw new AetherCharacterTieException(404, 'Tie niet gevonden.');
    }

    $ownerData = ['id' => $characterId, 'class' => (string) ($ownerCharacter['class'] ?? '')];
    $targetData = ['id' => $otherCharacterId, 'class' => (string) ($targetCharacter['class'] ?? '')];
    if ($relationType === 'household_staff' && !canCharacterBeLandlord($pdo, $ownerData)) {
        throw new AetherCharacterTieException(400, 'Dit personage kan geen household staff kiezen.');
    }
    if ($relationType === 'landlord' && !canCharacterBeLandlord($pdo, $targetData)) {
        throw new AetherCharacterTieException(400, 'Het gekozen personage kan niet als landlord aangeduid worden.');
    }

    $pdo->beginTransaction();
    try {
        if ($tieId > 0) {
            aetherUpdateCharacterTie(
                $pdo,
                $tieId,
                $characterId,
                $otherCharacterId,
                $relationType,
                (string) ($input['description'] ?? ''),
                (int) $currentUser['id']
            );
        } else {
            $tieId = aetherInsertCharacterTie(
                $pdo,
                $characterId,
                $otherCharacterId,
                $relationType,
                (string) ($input['description'] ?? ''),
                (int) $currentUser['id']
            );
        }

        $syncedAddress = aetherSyncConfirmedCharacterTieAddress(
            $pdo,
            $characterId,
            $otherCharacterId,
            $relationType
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['idTie' => $tieId, 'syncedAddress' => $syncedAddress];
}

/** @return array<string, mixed>|null */
function aetherSyncConfirmedCharacterTieAddress(
    PDO $pdo,
    int $characterId,
    int $otherCharacterId,
    string $relationType
): ?array {
    $sourceId = 0;
    $targetId = 0;
    if ($relationType === 'household_staff'
        && aetherCharacterTieExists($pdo, $otherCharacterId, $characterId, 'landlord')) {
        $sourceId = $otherCharacterId;
        $targetId = $characterId;
    } elseif ($relationType === 'landlord'
        && aetherCharacterTieExists($pdo, $otherCharacterId, $characterId, 'household_staff')) {
        $sourceId = $characterId;
        $targetId = $otherCharacterId;
    }
    if ($sourceId <= 0 || $targetId <= 0) {
        return null;
    }

    $address = aetherFetchCharacterTieAddress($pdo, $sourceId);
    if ($address === null) {
        return null;
    }
    aetherUpdateCharacterTieAddress($pdo, $targetId, $address);

    return ['idCharacter' => $targetId] + $address;
}

function aetherDeleteCharacterTie(PDO $pdo, int $characterId, int $tieId): void
{
    if (aetherFetchOwnedCharacterTie($pdo, $tieId, $characterId) === null) {
        throw new AetherCharacterTieException(404, 'Tie niet gevonden.');
    }

    aetherDeleteCharacterTieRecord($pdo, $tieId, $characterId);
}
