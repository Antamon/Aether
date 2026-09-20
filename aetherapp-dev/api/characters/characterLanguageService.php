<?php
declare(strict_types=1);

require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/characterLanguageUtils.php';
require_once __DIR__ . '/characterLanguageRepository.php';

final class AetherCharacterLanguageException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

/** @return array{success: true} */
function aetherAddCharacterLanguage(PDO $pdo, array $currentUser, int $characterId, int $languageId, string $name): array
{
    if ($languageId <= 0 && $name === '') {
        throw new AetherCharacterLanguageException(400, 'Ongeldige parameters.');
    }
    if (!characterLanguageSchemaReady($pdo)) {
        throw new AetherCharacterLanguageException(500, 'De taalmodule is nog niet geactiveerd in de databank.');
    }
    $character = aetherFetchLanguageCharacter($pdo, $characterId);
    if ($character === null) throw new AetherCharacterLanguageException(404, 'Personage niet gevonden.');
    if (!canCurrentUserManageCharacterLanguages($character, (string) $currentUser['role'], (int) $currentUser['id'])) {
        throw new AetherCharacterLanguageException(403, 'Geen rechten om talen te beheren.');
    }
    if (!canCharacterUseWrittenLanguages($pdo, $character)) {
        throw new AetherCharacterLanguageException(400, 'Dit personage kan geen extra schrijftalen kiezen.');
    }

    $points = getCharacterPointSummary($pdo, $character);
    $freeSlots = getCharacterFreeLanguageSlotCount($pdo, $character);
    $requiresExperience = count(getCharacterLanguages($pdo, $characterId)) >= $freeSlots;
    if ((string) ($character['type'] ?? '') === 'player' && $requiresExperience
        && (int) ($points['remainingExperience'] ?? 0) < 2) {
        throw new AetherCharacterLanguageException(400, 'Onvoldoende ervaringspunten om nog een extra taal te kiezen.');
    }

    $createDefinition = false;
    if ($languageId > 0) {
        if (aetherFetchLanguageDefinition($pdo, $languageId) === null) {
            throw new AetherCharacterLanguageException(404, 'Taal niet gevonden.');
        }
    } else {
        if (aetherFindLanguageDefinitionByName($pdo, $name) !== null) {
            throw new AetherCharacterLanguageException(400, 'Deze taal bestaat al. Kies ze uit de bestaande lijst.');
        }
        $createDefinition = true;
    }
    if (!$createDefinition && aetherCharacterLanguageIsLinked($pdo, $characterId, $languageId)) {
        throw new AetherCharacterLanguageException(400, 'Deze taal is al gekozen voor dit personage.');
    }

    $pdo->beginTransaction();
    try {
        if ($createDefinition) $languageId = aetherInsertLanguageDefinition($pdo, $name, (int) $currentUser['id']);
        aetherInsertCharacterLanguage($pdo, $characterId, $languageId, (int) $currentUser['id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return ['success' => true];
}
