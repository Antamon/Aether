<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

// 1. Basisvalidatie: ID verplicht
if (empty($postData['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig character ID.']);
    exit;
}

$id = (int) $postData['id'];
unset($postData['id']); // rest = kolommen die geüpdatet moeten worden

// 2. Datumvelden: lege string NIET updaten (vermijdt "Invalid date")
$dateFields = ['birthDate']; // voeg hier eventueel extra date/datetime kolommen toe

foreach ($dateFields as $field) {
    if (array_key_exists($field, $postData)) {
        $value = trim((string) $postData[$field]);
        if ($value === '') {
            // Kolom uit de UPDATE-query halen als er geen geldige datum is opgegeven
            unset($postData[$field]);
        }
    }
}

// 3. Na filtering moeten er nog velden overblijven
if (empty($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen velden om bij te werken.']);
    exit;
}

try {
    // 4. Dynamische SET-lijst opbouwen
    $columns = array_keys($postData);
    $setParts = [];

    foreach ($columns as $col) {
        $setParts[] = "$col = :$col";
    }

    $setSql = implode(', ', $setParts);

    $sql = "UPDATE tblCharacter SET $setSql WHERE id = :id";

    // 5. Parameters samenstellen
    $params = $postData;
    $params['id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 6. Zelfde gedrag als vroeger: aantal gewijzigde rijen teruggeven
    echo $stmt->rowCount();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet bijwerken.'
        // eventueel voor debug:
        // 'details' => $e->getMessage()
    ]);
}
