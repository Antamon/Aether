<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';

// 1. Is er een Oneiros-sessie?
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['status' => 'redirect']);
    exit;
}

// 2. Basisgegevens uit de sessie ophalen
$id = (int) ($_SESSION['user']['id'] ?? 0);
$username = $_SESSION['user']['username'] ?? '';
$firstName = $_SESSION['user']['firstName'] ?? '';
$lastName = $_SESSION['user']['lastName'] ?? '';
$role = 'participant'; // default als er nog geen record is in tblUser

if ($id <= 0) {
    echo json_encode(['status' => 'redirect']);
    exit;
}

try {
    // 3. Bestaat de gebruiker al in tblUser?
    $existing = dbOne(
        $pdo,
        'SELECT role
           FROM tblUser
          WHERE id = :id',
        ['id' => $id]
    );

    if ($existing === null) {
        // 4a. Nieuwe gebruiker aanmaken
        $sql = 'INSERT INTO tblUser (id, username, firstName, lastName, role)
                VALUES (:id, :username, :firstName, :lastName, :role)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'username' => $username,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'role' => $role, // blijft "participant" tot je het aanpast in de DB
        ]);
    } else {
        // 4b. Bestaande gebruiker bijwerken, maar rol behouden
        $role = $existing['role'];

        $sql = 'UPDATE tblUser
                   SET username  = :username,
                       firstName = :firstName,
                       lastName  = :lastName
                 WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'username' => $username,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ]);
    }

    // 5. Beperkte data teruggeven aan de frontend
    echo json_encode([
        'status' => 'ok',
        'user' => [
            'displayName' => trim($firstName . ' ' . $lastName),
            'role' => $role,
        ],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error during login check.',
    ]);
}
