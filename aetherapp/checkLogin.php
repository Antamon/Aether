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
    echo json_encode(['status' => 'redirect']);
    exit;
}

require __DIR__ . '/db.php';

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
            'id' => $user['id'],
            'firstName' => $firstName,
            'lastName' => $lastName,
            'displayName' => trim($firstName . ' ' . $lastName),
            'role' => $user['role'],
            'interfaceLanguage' => aetherProfileInterfaceLanguage((int) $user['id']),
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
