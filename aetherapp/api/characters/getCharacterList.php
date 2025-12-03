<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

// JSON-body inlezen (optioneel)
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

// Rol bepalen:
// - voorkeursbron = sessie (veilig)
// - indien de frontend expliciet een rol doorstuurt, gebruiken we die als fallback
$userRole = $postData['role'] ?? null;
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== '') {
    $userRole = $_SESSION['user']['role'];
}

// Ingelogde gebruiker bepalen
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}
$idUser = (int) $_SESSION['user']['id'];

try {
    // Director / administrator ziet alle personages
    if ($userRole === 'director' || $userRole === 'administrator') {
        $characters = dbAll(
            $pdo,
            'SELECT id, firstName, lastName
               FROM tblCharacter
           ORDER BY firstName, lastName'
        );
    } else {
        // Gewone participant: enkel eigen personages
        $characters = dbAll(
            $pdo,
            'SELECT id, firstName, lastName
               FROM tblCharacter
              WHERE idUser = :uid
           ORDER BY firstName, lastName',
            ['uid' => $idUser]
        );
    }

    echo json_encode($characters);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading character list.']);
}
