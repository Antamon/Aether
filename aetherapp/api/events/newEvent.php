<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

if (empty($postData) || !is_array($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldige event data ontvangen.']);
    exit;
}

try {
    $columns = array_keys($postData);
    $columnList = implode(', ', $columns);
    $placeholders = ':' . implode(', :', $columns);

    $sql = "INSERT INTO tblEvent ($columnList) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($postData);

    echo $pdo->lastInsertId();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon event niet aanmaken.']);
}
