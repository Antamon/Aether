<?php
declare(strict_types=1);

require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterRepository.php';

final class AetherCharacterUpdateException extends RuntimeException
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

/**
 * @param array<string, mixed> $currentUser
 * @param array<string, mixed> $currentCharacter
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function aetherPrepareCharacterUpdate(
    PDO $pdo,
    array $currentUser,
    array $currentCharacter,
    array $fields
): array {
    if (array_key_exists('birthDate', $fields) && trim((string) $fields['birthDate']) === '') {
        unset($fields['birthDate']);
    }
    if (array_key_exists('experienceToTrait', $fields)) {
        $fields['experienceToTrait'] = max(0, min(6, (int) $fields['experienceToTrait']));
    }
    if ($fields === []) {
        throw new AetherCharacterUpdateException(400, 'Geen velden om bij te werken.');
    }

    foreach (['idUser', 'type', 'state'] as $authorityField) {
        if (array_key_exists($authorityField, $fields)
            && !aetherCanChangeCharacterAuthorityField($currentUser)) {
            throw new AetherCharacterUpdateException(
                403,
                'Je hebt geen rechten om eigenaar, type of status van dit personage te wijzigen.'
            );
        }
    }
    foreach (['createdBy', 'createdAt'] as $auditField) {
        if (array_key_exists($auditField, $fields)) {
            throw new AetherCharacterUpdateException(403, 'Auditvelden kunnen niet via deze API worden gewijzigd.');
        }
    }

    if (array_key_exists('class', $fields) && !aetherCanEditDraftCharacter($currentUser, $currentCharacter)) {
        throw new AetherCharacterUpdateException(
            403,
            'Je hebt geen rechten om de klasse van dit personage aan te passen.'
        );
    }

    $role = (string) $currentUser['role'];
    if (array_key_exists('bankaccount', $fields)) {
        if (!canEditCharacterBankAccount($currentCharacter, $role)) {
            throw new AetherCharacterUpdateException(
                403,
                'Je hebt geen rechten om de bankrekening van dit personage aan te passen.'
            );
        }
        $fields['bankaccount'] = round((float) $fields['bankaccount'], 2);
    }
    if (array_key_exists('securitiesaccount', $fields)) {
        if (!canEditCharacterSecuritiesAccount($currentCharacter, $role)) {
            throw new AetherCharacterUpdateException(
                403,
                'Je hebt geen rechten om de effectenportefeuille van dit personage aan te passen.'
            );
        }
        $fields['securitiesaccount'] = max(0, round((float) $fields['securitiesaccount'], 2));
    }

    if (array_key_exists('state', $fields)
        && (string) ($currentCharacter['state'] ?? '') === 'draft'
        && (string) $fields['state'] !== 'draft'
        && !array_key_exists('bankaccount', $fields)) {
        $fields['bankaccount'] = getDraftBankAccountAmountForCharacter($pdo, $currentCharacter);
    }

    $paidHealthFields = ['physicalHealth', 'mentalHealth'];
    $freeHealthFields = ['physicalHealthFree', 'mentalHealthFree'];
    $containsHealthUpdate = array_intersect(
        array_keys($fields),
        array_merge($paidHealthFields, $freeHealthFields)
    ) !== [];

    if ($containsHealthUpdate) {
        foreach ($paidHealthFields as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }
            if (!aetherCanEditDraftCharacter($currentUser, $currentCharacter)) {
                throw new AetherCharacterUpdateException(
                    403,
                    'Je hebt geen rechten om gezondheid van dit personage aan te passen.'
                );
            }
            $fields[$field] = max(-3, (int) $fields[$field]);
        }
        foreach ($freeHealthFields as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }
            if (!aetherIsPrivilegedRole($role)) {
                throw new AetherCharacterUpdateException(
                    403,
                    'Je hebt geen rechten om gratis gezondheid van dit personage aan te passen.'
                );
            }
            $fields[$field] = (int) $fields[$field];
        }

        foreach (['physicalHealth' => 'physicalHealthFree', 'mentalHealth' => 'mentalHealthFree'] as $paidField => $freeField) {
            $nextPaid = array_key_exists($paidField, $fields)
                ? (int) $fields[$paidField]
                : (int) ($currentCharacter[$paidField] ?? 0);
            $nextFree = array_key_exists($freeField, $fields)
                ? (int) $fields[$freeField]
                : (int) ($currentCharacter[$freeField] ?? 0);
            if (4 + $nextPaid + $nextFree < 1) {
                throw new AetherCharacterUpdateException(400, 'Gezondheid kan niet lager dan 1 worden.');
            }
        }
    }

    $affectsExperienceBudget = array_key_exists('experienceToTrait', $fields)
        || array_intersect(array_keys($fields), $paidHealthFields) !== [];
    if ($affectsExperienceBudget) {
        $nextCharacter = $currentCharacter;
        foreach (['experienceToTrait', 'physicalHealth', 'mentalHealth'] as $field) {
            if (array_key_exists($field, $fields)) {
                $nextCharacter[$field] = (int) $fields[$field];
            }
        }

        $pointSummary = getCharacterPointSummary($pdo, $nextCharacter);
        $usedSkillExperience = getCharacterSkillExperienceCost(
            $pdo,
            (int) $currentCharacter['id']
        );
        if ((bool) ($pointSummary['isPlayer'] ?? false)
            && $usedSkillExperience > (int) ($pointSummary['experienceBudget'] ?? 0)) {
            throw new AetherCharacterUpdateException(
                400,
                'Onvoldoende ervaringspunten voor deze wijziging.'
            );
        }
    }

    return $fields;
}

/**
 * @param array<string, mixed> $currentCharacter
 * @param array<string, mixed> $fields
 */
function aetherApplyCharacterUpdate(
    PDO $pdo,
    array $currentCharacter,
    array $fields
): int {
    $characterId = (int) $currentCharacter['id'];
    $shouldPruneClassTraits = array_key_exists('class', $fields)
        && (string) ($currentCharacter['class'] ?? '') !== (string) $fields['class'];
    $shouldSyncAddresses = array_intersect(
        array_keys($fields),
        ['street', 'houseNumber', 'postalCode', 'municipality']
    ) !== [];

    $pdo->beginTransaction();
    try {
        $rowCount = aetherUpdateCharacterRecord($pdo, $characterId, $fields);

        if ($shouldSyncAddresses && aetherLandlordHasConfirmedHouseholdStaff($pdo, $characterId)) {
            try {
                aetherSyncConfirmedHouseholdStaffAddresses($pdo, $characterId);
            } catch (Throwable $syncException) {
                error_log(sprintf(
                    'Address sync failed for character %d in updateCharacter.php: %s',
                    $characterId,
                    $syncException->getMessage()
                ));
            }
        }
        if ($shouldPruneClassTraits) {
            aetherPruneCharacterClassTraits($pdo, $characterId);
        }

        $pdo->commit();
        return $rowCount;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
