<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/characterSkillActionUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterRequestValidation.php';

$input = aetherReadCharacterJsonRequest('getCharacterActionEvents');
$idCharacter = (int) ($input['idCharacter'] ?? 0);

if ($idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Personage ontbreekt.']);
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

    echo json_encode([
        'events' => fetchCharacterActionEvents($pdo),
        'worldKnowledgeLevel' => getCharacterSkillLevelByIdForGossip($pdo, $idCharacter, AETHER_WORLD_KNOWLEDGE_SKILL_ID),
        'psiBurn' => getCharacterPsiBurn($pdo, $idCharacter),
        'actions' => buildCharacterPsiActionGroups($pdo, $idCharacter),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de actiedata niet laden.',
        'details' => $e->getMessage(),
    ]);
}
