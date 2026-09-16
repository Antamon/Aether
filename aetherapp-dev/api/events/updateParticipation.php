<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/accessControl.php';

$currentUser = aetherRequirePrivilegedUser($pdo);
aetherRequireCsrfToken();

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$idEvent = isset($postData['idEvent']) ? (int) $postData['idEvent'] : 0;
$idUser = isset($postData['idUser']) ? (int) $postData['idUser'] : 0;
$participation = $postData['participation'] ?? null;

// Zonder expliciete keuze bewerkt een beheerder de eigen deelname.
if ($idUser <= 0) {
    $idUser = (int) $currentUser['id'];
}

if ($idEvent <= 0 || $idUser <= 0 || !is_bool($participation)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige deelname-data.']);
    exit;
}

try {
    if ($participation === true) {
        // Deelname toevoegen – voorkom dubbele rijen (optioneel: eerst deleten)
        $sql = 'INSERT INTO tblLinkEventUser (idEvent, idUser)
                VALUES (:idEvent, :idUser)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'idEvent' => $idEvent,
            'idUser' => $idUser,
        ]);

        echo $pdo->lastInsertId();

    } else {
        // Deelname verwijderen
        $sql = 'DELETE FROM tblLinkEventUser
                 WHERE idEvent = :idEvent
                   AND idUser  = :idUser';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'idEvent' => $idEvent,
            'idUser' => $idUser,
        ]);

        echo $stmt->rowCount();
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon deelname niet bijwerken.'
        // 'details' => $e->getMessage() // eventueel tijdelijk voor debug
    ]);
}
