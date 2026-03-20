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

if ($idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'idCharacter ontbreekt.']);
    exit;
}

try {
    // Entries
    $sql = "
        SELECT d.*, e.title AS eventTitle, e.dateStart, e.dateEnd
          FROM tblCharacterDiary d
          JOIN tblEvent e ON e.id = d.idEvent
         WHERE d.idCharacter = :idCharacter
         ORDER BY e.dateStart DESC, e.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idCharacter' => $idCharacter]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Available events without entry
    $sqlAvail = "
        SELECT e.id, e.title, e.dateStart
          FROM tblEvent e
         WHERE e.id NOT IN (
             SELECT idEvent FROM tblCharacterDiary WHERE idCharacter = :idCharacter
         )
         ORDER BY e.dateStart DESC, e.id DESC
    ";
    $stmtAvail = $pdo->prepare($sqlAvail);
    $stmtAvail->execute([':idCharacter' => $idCharacter]);
    $available = $stmtAvail->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'entries' => array_map(function ($row) {
            $row['id'] = (int)$row['id'];
            $row['idCharacter'] = (int)$row['idCharacter'];
            $row['idEvent'] = (int)$row['idEvent'];
            return $row;
        }, $entries),
        'availableEvents' => array_map(function ($row) {
            $row['id'] = (int)$row['id'];
            return $row;
        }, $available)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon diary niet ophalen.']);
}
