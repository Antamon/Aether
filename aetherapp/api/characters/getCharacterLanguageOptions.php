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

if ($idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldig personage.']);
    exit;
}

try {
    $pdo = getPDO();

    if (!characterLanguageSchemaReady($pdo)) {
        echo json_encode(['options' => []]);
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

    if (!canCharacterUseWrittenLanguages($pdo, $character)) {
        echo json_encode(['options' => []]);
        exit;
    }

    $options = dbAll(
        $pdo,
        'SELECT l.id, l.name
           FROM tblLanguage AS l
          WHERE NOT EXISTS (
                SELECT 1
                  FROM tblCharacterLanguage AS cl
                 WHERE cl.idCharacter = :idCharacter
                   AND cl.idLanguage = l.id
           )
          ORDER BY LOWER(l.name), l.name, l.id',
        ['idCharacter' => $idCharacter]
    );

    echo json_encode([
        'options' => array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }, $options),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon taalopties niet ophalen.']);
}
