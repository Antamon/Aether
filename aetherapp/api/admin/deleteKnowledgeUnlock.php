<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idEvent = (int) ($input['idEvent'] ?? 0);
$idSourceCharacter = (int) ($input['idSourceCharacter'] ?? 0);
$idViewerCharacter = (int) ($input['idViewerCharacter'] ?? 0);

if ($idEvent <= 0 || $idSourceCharacter <= 0 || $idViewerCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Event, bronpersonage en ontdekker zijn verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);

    $pdo->beginTransaction();
    deleteGossipUnlockState($pdo, $idViewerCharacter, $idEvent, $idSourceCharacter);
    $attemptCount = decrementCharacterEventGossipAttemptCount($pdo, $idViewerCharacter, $idEvent);
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'idEvent' => $idEvent,
        'idSourceCharacter' => $idSourceCharacter,
        'idViewerCharacter' => $idViewerCharacter,
        'attemptCount' => $attemptCount,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon deze wereldwijsontdekking niet verwijderen.',
        'details' => $e->getMessage(),
    ]);
}
