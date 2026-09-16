<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/accessControl.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

// Fallback: form-encoded POST (apiFetchJson stuurt dit standaard)
if (empty($postData) || !is_array($postData)) {
    $postData = $_POST ?? [];
}

if (empty($postData) || !is_array($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldige character data ontvangen.']);
    exit;
}

try {
    // 2. Bepaal creator
    $creatorId = (int) $currentUser['id'];

    // 3. Standaardwaarden afdwingen

    // Audit- en autoriteitsvelden worden uitsluitend server-side bepaald.
    $postData['createdBy'] = $creatorId;

    // createdAt: huidige timestamp als er niets wordt meegestuurd
    $postData['createdAt'] = date('Y-m-d H:i:s');

    // state: bij creatie altijd 'draft', tenzij je bewust een andere state toelaat
    $postData['state'] = 'draft';

    if (!aetherIsPrivilegedRole($currentUser['role'])) {
        $postData['idUser'] = $creatorId;
        $postData['type'] = 'player';
        $postData['physicalHealthFree'] = 0;
        $postData['mentalHealthFree'] = 0;
    }

    // birthDate placeholder (vermijd '0000-00-00' in strict mode)
    if (empty($postData['birthDate'])) {
        $postData['birthDate'] = '1900-01-01';
    }

    // (optioneel) minimale sanity check op class / firstName / lastName
    // Dit is vooral server-side safety; frontend valideert al.
    if (
        empty($postData['class']) ||
        empty($postData['firstName']) ||
        empty($postData['lastName'])
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Klasse, voornaam en familienaam zijn verplicht.']);
        exit;
    }

    // 4. Dynamische INSERT op basis van de keys in $postData
    $columns = array_keys($postData);
    if (empty($columns)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geen velden om op te slaan.']);
        exit;
    }

    $columnList = implode(', ', array_map(static function ($col) {
        return '`' . str_replace('`', '', $col) . '`';
    }, $columns));
    $placeholders = ':' . implode(', :', $columns);

    $sql = "INSERT INTO tblCharacter ($columnList) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($postData);

    // Zelfde gedrag als vroeger: enkel het nieuwe ID teruggeven
    echo $pdo->lastInsertId();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet aanmaken.',
        // 'details' => $e->getMessage(), // eventueel tijdelijk aanzetten voor debugging
    ]);
}
