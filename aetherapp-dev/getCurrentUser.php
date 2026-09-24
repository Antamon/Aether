<?php
declare(strict_types=1);

require_once __DIR__ . '/api/shared/session.php';
aetherStartSession();
header('Content-Type: application/json; charset=utf-8');

require 'db.php';
require_once 'sessionUserBootstrap.php';
require_once __DIR__ . '/api/auth/accessControl.php';

// Check of er een ingelogde gebruiker is
if (aetherEnsureSessionIdentity() <= 0) {
    aetherHydrateSessionUserFromWordPress();
}

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'redirect']);
    exit;
}

try {
    $user = aetherLoadAuthenticatedUser($pdo, true);
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['status' => 'redirect']);
        exit;
    }

    echo json_encode([
        'id' => $user['id'],
        'firstName' => $user['firstName'],
        'lastName' => $user['lastName'],
        'role' => $user['role'],
        'csrfToken' => aetherGetCsrfToken(),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading current user.']);
}
