<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/gossipKnowledgeUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idCharacter = (int) ($input['idCharacter'] ?? 0);
$idEvent = (int) ($input['idEvent'] ?? 0);
$idSourceCharacter = (int) ($input['idSourceCharacter'] ?? 0);

if ($idCharacter <= 0 || $idEvent <= 0 || $idSourceCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Personage, event en bronpersonage zijn verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCsrfToken();
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
        echo json_encode(['error' => 'Geen rechten om deze actie uit te voeren.']);
        exit;
    }

    $worldKnowledgeLevel = getCharacterSkillLevelByIdForGossip($pdo, $idCharacter, AETHER_WORLD_KNOWLEDGE_SKILL_ID);
    if ($worldKnowledgeLevel <= 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Dit personage bezit Wereldwijs niet.']);
        exit;
    }

    $pdo->beginTransaction();
    $result = revealKnowledgeGossip($pdo, $idCharacter, $idEvent, $idSourceCharacter, $worldKnowledgeLevel);
    $pdo->commit();

    echo json_encode($result);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Kon de wereldwijsroddels niet vrijspelen.';

    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode([
        'error' => $message,
        'details' => $e instanceof RuntimeException ? null : $e->getMessage(),
    ]);
}
