<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim((string) ($input['name'] ?? ''));

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'De naam van de vaardigheid is verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);

    $existing = dbOne(
        $pdo,
        'SELECT id
           FROM tblSkill
          WHERE LOWER(name) = LOWER(:name)
          LIMIT 1',
        ['name' => $name]
    );

    if ($existing !== null) {
        http_response_code(400);
        echo json_encode(['error' => 'Er bestaat al een vaardigheid met deze naam.']);
        exit;
    }

    $visibilityMap = getSkillVisibilityStorageMap($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO tblSkill (name, description, beginner, professional, master, visibility)
         VALUES (:name, :description, :beginner, :professional, :master, :visibility)'
    );
    $stmt->execute([
        'name' => $name,
        'description' => '',
        'beginner' => '',
        'professional' => '',
        'master' => '',
        'visibility' => $visibilityMap['public'],
    ]);

    $idSkill = (int) $pdo->lastInsertId();
    $skill = fetchAdminSkillDetail($pdo, $idSkill);

    echo json_encode([
        'skill' => $skill,
        'skills' => fetchAdminSkillList($pdo),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de vaardigheid niet aanmaken.',
        'details' => $e->getMessage(),
    ]);
}
