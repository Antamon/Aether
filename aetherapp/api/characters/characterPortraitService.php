<?php
declare(strict_types=1);

require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/characterPortraitImage.php';

final class AetherCharacterPortraitException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

/** @param array<string, mixed> $upload @return array{status: string, portraitUrl: ?string} */
function aetherUploadCharacterPortrait(PDO $pdo, array $currentUser, int $characterId, array $upload): array
{
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if ($character === null) {
        throw new AetherCharacterPortraitException(404, 'Personage niet gevonden.');
    }
    if (!aetherCanManageCharacterPortrait($currentUser, $character)) {
        throw new AetherCharacterPortraitException(403, 'Je hebt geen rechten om dit portret te beheren.');
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new AetherCharacterPortraitException(400, 'Het opladen van het portret is mislukt.');
    }

    $tmpPath = is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : '';
    if ($tmpPath === '' || !aetherPortraitUploadIsTrusted($tmpPath)) {
        throw new AetherCharacterPortraitException(400, 'Geen geldig uploadbestand ontvangen.');
    }
    try {
        aetherInspectCharacterPortrait($tmpPath);
    } catch (LengthException|InvalidArgumentException $e) {
        throw new AetherValidationException([$e->getMessage()]);
    } catch (UnexpectedValueException $e) {
        throw new AetherCharacterPortraitException(400, $e->getMessage());
    }

    aetherEnsurePortraitDirectories();
    $oldPaths = aetherGetCharacterPortraitPaths($characterId);
    $workingPath = getCharacterPortraitDirectory() . '/.incoming-' . bin2hex(random_bytes(16)) . '.tmp';
    $finalPath = null;
    try {
        try {
            aetherWriteCharacterPortraitPng($tmpPath, $workingPath);
        } catch (UnexpectedValueException $e) {
            throw new AetherCharacterPortraitException(400, $e->getMessage());
        }
        if (!is_file($workingPath) || is_link($workingPath)) {
            throw new RuntimeException('Portretverwerking leverde geen bestand op.');
        }

        $filename = aetherCreateCharacterPortraitFilename($characterId);
        $finalPath = aetherCharacterPortraitPathForFilename($filename, $characterId);
        if (!aetherPortraitRename($workingPath, $finalPath)) {
            throw new RuntimeException('Kon verwerkt portret niet activeren.');
        }

        try {
            $staged = aetherStageCharacterPortraitPaths($oldPaths, $characterId);
        } catch (Throwable $e) {
            if (is_file($finalPath) && !aetherPortraitUnlink($finalPath)) {
                error_log('Kon nieuw characterportret na mislukte activatie niet opruimen: ' . basename($finalPath));
            }
            throw $e;
        }
        aetherPurgeStagedCharacterPortraits($staged);
    } catch (Throwable $e) {
        if (is_file($workingPath) && !aetherPortraitUnlink($workingPath)) {
            error_log('Kon tijdelijk characterportret niet opruimen: ' . basename($workingPath));
        }
        throw $e;
    }

    return ['status' => 'ok', 'portraitUrl' => getCharacterPortraitUrl($characterId)];
}

/** @return array{status: string} */
function aetherDeleteCharacterPortrait(PDO $pdo, array $currentUser, int $characterId): array
{
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if ($character === null) {
        throw new AetherCharacterPortraitException(404, 'Personage niet gevonden.');
    }
    if (!aetherCanManageCharacterPortrait($currentUser, $character)) {
        throw new AetherCharacterPortraitException(403, 'Je hebt geen rechten om dit portret te beheren.');
    }

    $staged = aetherStageCharacterPortraitPaths(aetherGetCharacterPortraitPaths($characterId), $characterId);
    aetherPurgeStagedCharacterPortraits($staged);
    return ['status' => 'ok'];
}
