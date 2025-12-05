<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

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
    // Character info ophalen
    $stmtChar = $pdo->prepare("SELECT idUser, type FROM tblCharacter WHERE id = :id");
    $stmtChar->execute([':id' => $idCharacter]);
    $character = $stmtChar->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Character niet gevonden.']);
        exit;
    }

    $userId = (int) $_SESSION['user']['id'];
    $role   = $_SESSION['user']['role'] ?? '';

    $isAdmin = ($role === 'administrator' || $role === 'director');
    $isOwnerPlayer = (
        $role === 'participant' &&
        $character['type'] === 'player' &&
        (int)$character['idUser'] === $userId
    );

    if (!$isAdmin && !$isOwnerPlayer) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om deze sectie te wijzigen.']);
        exit;
    }

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

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon sectie niet opslaan.']);
}
