<?php
declare(strict_types=1);

require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterRichText.php';
require_once __DIR__ . '/characterDiaryRepository.php';
require_once __DIR__ . '/characterReadService.php';

final class AetherCharacterDiaryException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

/** @param array<string, mixed> $input @return array<string, mixed> */
function aetherSaveCharacterDiary(PDO $pdo, array $currentUser, array $input): array
{
    $characterId = (int) $input['idCharacter'];
    $diaryId = (int) ($input['idDiary'] ?? 0);
    $eventId = (int) $input['idEvent'];
    $character = aetherFetchCharacterAccessRecord($pdo, $characterId);
    if ($character === null) throw new AetherCharacterDiaryException(404, 'Character niet gevonden.');

    $canEditAll = aetherCanEditCharacter($currentUser, $character);
    $canEditAchievementsOnly = !$canEditAll && aetherCanEditCharacterDiaryAchievements($currentUser, $character);
    if (!$canEditAll && !$canEditAchievementsOnly) {
        throw new AetherCharacterDiaryException(403, 'Geen rechten om deze diary te wijzigen.');
    }
    if (!aetherCharacterDiaryEventExists($pdo, $eventId)) {
        throw new AetherCharacterDiaryException(404, 'Event niet gevonden.');
    }

    $existing = null;
    if ($diaryId > 0) {
        $existing = aetherFetchCharacterDiaryEntry($pdo, $characterId, $diaryId);
        if ($existing === null) throw new AetherCharacterDiaryException(404, 'Diary entry niet gevonden.');
        if ((int) $existing['idEvent'] !== $eventId) {
            throw new AetherCharacterDiaryException(400, 'Diary entry hoort niet bij dit event.');
        }
    } elseif (aetherCharacterDiaryExistsForEvent($pdo, $characterId, $eventId)) {
        throw new AetherCharacterDiaryException(400, 'Dit event heeft al een diary entry.');
    }

    $values = [
        'goals' => (string) ($input['goals'] ?? ''),
        'achievements' => (string) ($input['achievements'] ?? ''),
        'gossip1' => (string) ($input['gossip1'] ?? ''),
        'gossip2' => (string) ($input['gossip2'] ?? ''),
        'gossip3' => (string) ($input['gossip3'] ?? ''),
    ];
    if ($canEditAchievementsOnly) {
        $values['goals'] = (string) ($existing['goals'] ?? '');
        $values['gossip1'] = (string) ($existing['gossip1'] ?? '');
        $values['gossip2'] = (string) ($existing['gossip2'] ?? '');
        $values['gossip3'] = (string) ($existing['gossip3'] ?? '');
    }
    if ($canEditAll) $values['goals'] = aetherSanitizeCharacterRichText($values['goals']);
    $values['achievements'] = aetherSanitizeCharacterRichText($values['achievements']);

    $pdo->beginTransaction();
    try {
        if ($diaryId > 0) {
            aetherUpdateCharacterDiaryEntry($pdo, $diaryId, $characterId, (int) $currentUser['id'], $values);
        } else {
            aetherInsertCharacterDiaryEntry($pdo, $characterId, $eventId, (int) $currentUser['id'], $values);
        }
        $readModel = aetherBuildCharacterDiaryReadModel($pdo, $characterId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return ['success' => true, 'entries' => $readModel['entries'], 'availableEvents' => $readModel['availableEvents']];
}
