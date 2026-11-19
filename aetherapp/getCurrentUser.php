<?php
declare(strict_types=1);

require_once __DIR__ . '/api/shared/session.php';
aetherStartSession();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/sessionUserBootstrap.php';
require_once __DIR__ . '/api/auth/accessControl.php';

// Validate the WordPress/Aether identity before opening the Aether database.
// Guests must return to Oneiros even when that database is unavailable.
if (aetherEnsureSessionIdentity() <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'redirect']);
    exit;
}

require __DIR__ . '/db.php';

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
        'interfaceLanguage' => aetherProfileInterfaceLanguage((int) $user['id']),
        'csrfToken' => aetherGetCsrfToken(),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading current user.']);
}
