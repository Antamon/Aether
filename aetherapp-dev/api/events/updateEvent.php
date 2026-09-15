<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

if (empty($postData['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig event ID.']);
    exit;
}

$id = (int) $postData['id'];
unset($postData['id']);

if (empty($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen velden om bij te werken.']);
    exit;
}

try {
    $columns = array_keys($postData);
    $setParts = [];
    foreach ($columns as $col) {
        $setParts[] = "$col = :$col";
    }
    $setSql = implode(', ', $setParts);

    $sql = "UPDATE tblEvent SET $setSql WHERE id = :id";

    $params = $postData;
    $params['id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo $stmt->rowCount();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon event niet bijwerken.']);
}
