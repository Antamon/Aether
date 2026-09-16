<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/gossipKnowledgeUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idCharacter = (int) ($input['idCharacter'] ?? 0);
$idEvent = (int) ($input['idEvent'] ?? 0);

if ($idCharacter <= 0 || $idEvent <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Personage en event zijn verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    $currentUserRole = $currentUser['role'];
    $currentUserId = (int) $currentUser['id'];

    $character = dbOne($pdo, 'SELECT * FROM tblCharacter WHERE id = :idCharacter', ['idCharacter' => $idCharacter]);
    if ($character === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    if (!canViewCharacterActions($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om deze acties te bekijken.']);
        exit;
    }

    $worldKnowledgeLevel = getCharacterSkillLevelByIdForGossip($pdo, $idCharacter, AETHER_WORLD_KNOWLEDGE_SKILL_ID);

    echo json_encode([
        'worldKnowledgeLevel' => $worldKnowledgeLevel,
        'attemptCount' => getCharacterEventGossipAttemptCount($pdo, $idCharacter, $idEvent),
        'targets' => fetchVisibleKnowledgeTargetsForViewer($pdo, $idCharacter, $idEvent, $worldKnowledgeLevel),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de wereldwijsdoelen niet laden.',
        'details' => $e->getMessage(),
    ]);
}
