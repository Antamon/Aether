<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/accessControl.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $currentUser = aetherRequireAuthenticatedUser($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaseverbinding mislukt.']);
    exit;
}

// JSON body uitlezen
$inputJson = file_get_contents('php://input');
$input = json_decode($inputJson, true) ?? [];

$requestedUserId = isset($input['idUser']) ? (int) $input['idUser'] : 0;
$idUser = (int) $currentUser['id'];

// Alleen beheerrollen mogen de deelname van een andere gebruiker bekijken.
if ($requestedUserId > 0 && $requestedUserId !== $idUser) {
    if (!aetherIsPrivilegedRole($currentUser['role'])) {
        aetherJsonError(403, 'Je hebt geen rechten om deelnames van deze gebruiker te bekijken.');
    }
    $idUser = $requestedUserId;
}

// Eén query die events + deelname ophaalt
$sql = "
    SELECT
        e.id,
        e.type,
        e.title,
        e.description,
        e.dateStart,
        e.dateEnd,
        e.venue,
        e.ep,
        CASE WHEN leu.idUser IS NULL THEN 0 ELSE 1 END AS participation
    FROM tblEvent e
    LEFT JOIN tblLinkEventUser leu
        ON leu.idEvent = e.id
       AND leu.idUser = :idUser
    ORDER BY e.dateStart
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':idUser' => $idUser]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// participation naar echte boolean, EP netjes casten
foreach ($events as &$ev) {
    $ev['participation'] = (bool) $ev['participation'];
    if ($ev['ep'] !== null) {
        $ev['ep'] = (int) $ev['ep'];
    }
}
unset($ev);

// Frontend verwacht een platte array
echo json_encode($events);
