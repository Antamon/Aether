<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';

// Check of er een ingelogde gebruiker is
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'redirect']);
    exit;
}

$userId = (int) $_SESSION['user']['id'];

try {
    // Haal de gebruiker op uit de Aether-databank
    $user = dbOne(
        $pdo,
        'SELECT id, firstName, lastName, role
           FROM tblUser
          WHERE id = :id',
        ['id' => $userId]
    );

    if ($user === null) {
        // Safety fallback: als de user (nog) niet in tblUser staat
        $user = [
            'id' => $userId,
            'firstName' => $_SESSION['user']['firstName'] ?? '',
            'lastName' => $_SESSION['user']['lastName'] ?? '',
            'role' => $_SESSION['user']['role'] ?? 'participant', // veilig default
        ];
    }

    echo json_encode($user);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading current user.']);
}
