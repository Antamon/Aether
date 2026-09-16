<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterRequestValidation.php';
require_once __DIR__ . '/characterRichText.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

$input = aetherReadCharacterJsonRequest('saveCharacterSection');

$idCharacter = isset($input['idCharacter']) ? (int)$input['idCharacter'] : 0;
$section     = $input['section'] ?? '';
$content     = $input['content'] ?? '';

$allowedSections = [
    'personal_background',
    'knowledge',
    'nature',
    'demeanour'
];

if ($idCharacter <= 0 || !in_array($section, $allowedSections, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

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

    echo json_encode(['success' => true, 'content' => $content]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon sectie niet opslaan.']);
}
