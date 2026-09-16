<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterRequestValidation.php';

$postData = aetherReadCharacterJsonRequest('deleteCharacterPortrait');
$id = (int) ($postData['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig personage ID.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCsrfToken();
    $currentUserRole = $currentUser['role'];
    $currentUserId = (int) $currentUser['id'];

    $character = dbOne(
        $pdo,
        'SELECT id, idUser
           FROM tblCharacter
          WHERE id = :id',
        ['id' => $id]
    );

    if ($character === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    if (!canManageCharacterPortrait($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om dit portret te beheren.']);
        exit;
    }

    $portraitPath = getCharacterPortraitAbsolutePath($id);
    if (is_file($portraitPath) && !unlink($portraitPath)) {
        throw new RuntimeException('Kon portret niet verwijderen.');
    }

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon portret niet verwijderen.']);
}
