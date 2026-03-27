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

if (array_key_exists('experienceToTrait', $postData)) {
    $postData['experienceToTrait'] = max(0, min(6, (int) $postData['experienceToTrait']));
}

// 3. Na filtering moeten er nog velden overblijven
if (empty($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen velden om bij te werken.']);
    exit;
}

try {
    $pdo = getPDO();

    $shouldPruneClassTraits = false;
    if (array_key_exists('class', $postData)) {
        $currentCharacter = dbOne(
            $pdo,
            'SELECT `class` FROM tblCharacter WHERE id = :id',
            ['id' => $id]
        );

        if (!$currentCharacter) {
            http_response_code(404);
            echo json_encode(['error' => 'Personage niet gevonden.']);
            exit;
        }

        $shouldPruneClassTraits = (string) ($currentCharacter['class'] ?? '') !== (string) $postData['class'];
    }

    $pdo->beginTransaction();

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

    if ($shouldPruneClassTraits) {
        $deleteTraitLinksStmt = $pdo->prepare(
            "DELETE lct
             FROM tblLinkCharacterTrait AS lct
             JOIN tblTrait AS t
               ON t.id = lct.idTrait
             WHERE lct.idCharacter = :idCharacter
               AND t.`class` <> 'all'"
        );
        $deleteTraitLinksStmt->execute(['idCharacter' => $id]);
    }

    $pdo->commit();

    // 6. Zelfde gedrag als vroeger: aantal gewijzigde rijen teruggeven
    echo $stmt->rowCount();

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet bijwerken.'
        // eventueel voor debug:
        // 'details' => $e->getMessage()
    ]);
}
