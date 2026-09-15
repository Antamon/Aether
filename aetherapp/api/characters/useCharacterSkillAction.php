<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/characterSkillActionUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idCharacter = (int) ($input['idCharacter'] ?? 0);
$idEvent = (int) ($input['idEvent'] ?? 0);
$idSkill = (int) ($input['idSkill'] ?? 0);
$actionCode = trim((string) ($input['actionCode'] ?? ''));
$actionSubtype = trim((string) ($input['actionSubtype'] ?? ''));
$clearBurn = !empty($input['clearBurn']);

if ($idCharacter <= 0 || $idEvent <= 0 || $idSkill <= 0 || $actionCode === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Personage, event, vaardigheid en actiecode zijn verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUserRole = getCurrentUserRole($pdo);
    $currentUserId = getCurrentUserId();

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

    if ($actionCode !== AETHER_SKILL_ACTION_CODE_PSI) {
        http_response_code(400);
        echo json_encode(['error' => 'Deze actiecode wordt nog niet ondersteund.']);
        exit;
    }

    if ($actionSubtype === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Voor psi-acties is een gave-type verplicht.']);
        exit;
    }

    $pdo->beginTransaction();
    $result = executeCharacterPsiSkillUse(
        $pdo,
        $character,
        $idEvent,
        $idSkill,
        $actionSubtype,
        $clearBurn,
        $currentUserId
    );
    $pdo->commit();

    echo json_encode($result);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Kon deze vaardigheidsactie niet registreren.';

    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode([
        'error' => $message,
        'details' => $e instanceof RuntimeException ? null : $e->getMessage(),
    ]);
}
