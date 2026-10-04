<?php
declare(strict_types=1);

require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/characterLifecycleRepository.php';

final class AetherCharacterLifecycleException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

/** @return array{success: true, id: int, name: string} */
function aetherDeleteCharacter(PDO $pdo, array $currentUser, int $characterId): array
{
    $stagedPortraits = [];
    try {
        $pdo->beginTransaction();
        $character = aetherFetchCharacterForDeletion($pdo, $characterId, true);
        if ($character === null) {
            throw new AetherCharacterLifecycleException(404, 'Personage niet gevonden.');
        }
        if (!aetherCanEditCharacter($currentUser, $character)) {
            throw new AetherCharacterLifecycleException(403, 'Geen toestemming om dit personage te verwijderen.');
        }
        $stagedPortraits = aetherStageCharacterPortraitPaths(
            aetherGetCharacterPortraitPaths($characterId),
            $characterId
        );
        aetherDeleteCharacterManualRelations($pdo, $characterId);
        aetherDeleteCharacterRecord($pdo, $characterId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        aetherRestoreStagedCharacterPortraits($stagedPortraits);
        throw $e;
    }

    aetherPurgeStagedCharacterPortraits($stagedPortraits);
    return [
        'success' => true,
        'id' => $characterId,
        'name' => trim((string) ($character['firstName'] ?? '') . ' ' . (string) ($character['lastName'] ?? '')),
    ];
}
