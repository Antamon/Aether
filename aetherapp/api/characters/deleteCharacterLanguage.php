<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/characterLanguageUtils.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;
$idCharacterLanguage = isset($input['idCharacterLanguage']) ? (int) $input['idCharacterLanguage'] : 0;

if ($idCharacter <= 0 || $idCharacterLanguage <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    $pdo = getPDO();

    if (!characterLanguageSchemaReady($pdo)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $character = dbOne(
        $pdo,
        'SELECT id, idUser, type, `class`
           FROM tblCharacter
          WHERE id = :id',
        ['id' => $idCharacter]
    );

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
    $currentUserRole = getCurrentUserRole($pdo);
    if (!canCurrentUserManageCharacterLanguages($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om talen te beheren.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM tblCharacterLanguage
          WHERE id = :id
            AND idCharacter = :idCharacter'
    );
    $stmt->execute([
        'id' => $idCharacterLanguage,
        'idCharacter' => $idCharacter,
    ]);

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'Taal-link niet gevonden.']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon taal niet verwijderen.']);
}
