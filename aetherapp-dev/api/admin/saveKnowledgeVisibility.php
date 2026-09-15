<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idEvent = (int) ($input['idEvent'] ?? 0);
$idCharacter = (int) ($input['idCharacter'] ?? 0);
$isVisible = isset($input['isVisible']) ? (bool) $input['isVisible'] : null;

if ($idEvent <= 0 || $idCharacter <= 0 || $isVisible === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Event, personage en zichtbaarheid zijn verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    $user = requirePrivilegedAdminAccess($pdo);

    setGossipVisibilityState($pdo, $idEvent, $idCharacter, $isVisible, (int) ($user['idUser'] ?? 0));

    echo json_encode([
        'ok' => true,
        'idEvent' => $idEvent,
        'idCharacter' => $idCharacter,
        'isVisible' => $isVisible,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de zichtbaarheid van deze wereldwijsgossip niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
