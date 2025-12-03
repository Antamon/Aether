<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

if (empty($postData) || !is_array($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldige character data ontvangen.']);
    exit;
}

try {
    // Dynamische INSERT op basis van de keys in $postData
    $columns = array_keys($postData);
    $columnList = implode(', ', $columns);
    $placeholders = ':' . implode(', :', $columns);

    $sql = "INSERT INTO tblCharacter ($columnList) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($postData);

    // Zelfde gedrag als vroeger: gewoon het nieuwe ID teruggeven (nummer = geldige JSON)
    echo $pdo->lastInsertId();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon character niet aanmaken.']);
}
