<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterRequestValidation.php';
require_once __DIR__ . '/characterRichText.php';

$input = aetherReadCharacterJsonRequest('getCharacterSections');

$idCharacter = isset($input['idCharacter']) ? (int)$input['idCharacter'] : 0;
if ($idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'idCharacter ontbreekt.']);
    exit;
}

try {
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCharacterAccess($pdo, $currentUser, $idCharacter, 'view');

    $stmt = $pdo->prepare("
        SELECT section, content
        FROM tblCharacterSection
        WHERE idCharacter = :idCharacter
    ");
    $stmt->execute([':idCharacter' => $idCharacter]);

    $sections = [
        'personal_background' => '',
        'knowledge' => '',
        'nature' => '',
        'demeanour' => ''
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $section = (string) ($row['section'] ?? '');
        if (in_array($section, AETHER_CHARACTER_RICH_TEXT_SECTIONS, true)) {
            $sections[$section] = aetherSanitizeCharacterRichText((string) ($row['content'] ?? ''));
        }
    }

    echo json_encode($sections);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon character sections niet ophalen.']);
}
