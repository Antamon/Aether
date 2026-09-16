<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';
require_once 'sessionUserBootstrap.php';
require_once __DIR__ . '/api/auth/accessControl.php';

// 1. Is er een Aether-sessie? Zo niet, probeer rechtstreeks uit WordPress te hydrateren.
if (!isset($_SESSION['user']['id'])) {
    aetherHydrateSessionUserFromWordPress();
}

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['status' => 'redirect']);
    exit;
}

try {
    $user = aetherLoadAuthenticatedUser($pdo, true);
    if ($user === null) {
        echo json_encode(['status' => 'redirect']);
        exit;
    }

    // Houd profielgegevens gelijk met de vertrouwde WordPress-sessie; de databankrol blijft behouden.
    $profile = [
        'id' => $user['id'],
        'username' => (string) ($_SESSION['user']['username'] ?? $user['username'] ?? ''),
        'firstName' => (string) ($_SESSION['user']['firstName'] ?? $user['firstName'] ?? ''),
        'lastName' => (string) ($_SESSION['user']['lastName'] ?? $user['lastName'] ?? ''),
    ];
    $stmt = $pdo->prepare(
        'UPDATE tblUser
            SET username = :username,
                firstName = :firstName,
                lastName = :lastName
          WHERE id = :id'
    );
    $stmt->execute($profile);

    $firstName = $profile['firstName'];
    $lastName = $profile['lastName'];

    // 5. Beperkte data teruggeven aan de frontend
    echo json_encode([
        'status' => 'ok',
        'user' => [
            'displayName' => trim($firstName . ' ' . $lastName),
            'role' => $user['role'],
        ],
        'csrfToken' => aetherGetCsrfToken(),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error during login check.',
    ]);
}
