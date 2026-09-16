<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';

// JSON-body inlezen (optioneel)
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

try {
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    $userRole = $currentUser['role'];
    $idUser = (int) $currentUser['id'];

    // Director / administrator ziet alle personages
    if ($userRole === 'director' || $userRole === 'administrator') {
        $characters = dbAll(
            $pdo,
            'SELECT id, idUser, firstName, lastName, type, state, class
               FROM tblCharacter
           ORDER BY firstName, lastName'
        );
    } else {
        // Gewone participant: enkel eigen personages
        $characters = dbAll(
            $pdo,
            'SELECT id, idUser, firstName, lastName, type, state, class
               FROM tblCharacter
              WHERE idUser = :uid
           ORDER BY firstName, lastName',
            ['uid' => $idUser]
        );
    }

    foreach ($characters as &$character) {
        $character['portraitUrl'] = getCharacterPortraitUrl((int) ($character['id'] ?? 0));
    }
    unset($character);

    echo json_encode($characters);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading character list.']);
}
