<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idSkill = (int) ($input['idSkill'] ?? 0);

if ($idSkill <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldige vaardigheid geselecteerd.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);

    $skill = fetchAdminSkillDetail($pdo, $idSkill);
    if ($skill === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Vaardigheid niet gevonden.']);
        exit;
    }

    echo json_encode($skill);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de vaardigheid niet laden.',
        'details' => $e->getMessage(),
    ]);
}
