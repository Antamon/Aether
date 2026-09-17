<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterRichText.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('saveCharacterSection', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idCharacter = $input['idCharacter'];
$section = $input['section'];
$content = $input['content'] ?? '';

try {
    aetherRequireCharacterAccess($pdo, $currentUser, $idCharacter, 'edit');
    $userId = (int) $currentUser['id'];
    $content = aetherSanitizeCharacterRichText((string) $content);

    $sql = "
        INSERT INTO tblCharacterSection (idCharacter, section, content, updatedAt, updatedBy)
        VALUES (:idCharacter, :section, :content, NOW(), :updatedBy)
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            updatedAt = VALUES(updatedAt),
            updatedBy = VALUES(updatedBy)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':idCharacter' => $idCharacter,
        ':section' => $section,
        ':content' => $content,
        ':updatedBy' => $userId
    ]);

    aetherJsonResponse(['success' => true, 'content' => $content]);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon sectie niet opslaan.');
}
