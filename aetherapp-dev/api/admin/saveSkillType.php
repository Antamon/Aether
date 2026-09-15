<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string) ($input['action'] ?? ''));
$idSkillType = (int) ($input['idSkillType'] ?? 0);
$name = trim((string) ($input['name'] ?? ''));

if (!in_array($action, ['create', 'update', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige actie voor categoriebeheer.']);
    exit;
}

try {
    $pdo = getPDO();
    requireAdministratorAccess($pdo);

    if ($action === 'create') {
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'De categorienaam is verplicht.']);
            exit;
        }

        $existing = dbOne(
            $pdo,
            'SELECT id
               FROM tblSkillType
              WHERE LOWER(name) = LOWER(:name)
              LIMIT 1',
            ['name' => $name]
        );

        if ($existing !== null) {
            http_response_code(400);
            echo json_encode(['error' => 'Er bestaat al een categorie met deze naam.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tblSkillType (code, name, description)
             VALUES (:code, :name, :description)'
        );
        $stmt->execute([
            'code' => buildUniqueAdminSkillTypeCode($pdo, $name),
            'name' => $name,
            'description' => '',
        ]);

        echo json_encode([
            'skillTypes' => fetchAdminSkillTypeOptions($pdo),
        ]);
        exit;
    }

    if ($idSkillType <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Geen geldige categorie geselecteerd.']);
        exit;
    }

    $existingType = dbOne(
        $pdo,
        'SELECT id
           FROM tblSkillType
          WHERE id = :idSkillType',
        ['idSkillType' => $idSkillType]
    );

    if ($existingType === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Categorie niet gevonden.']);
        exit;
    }

    if ($action === 'update') {
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'De categorienaam is verplicht.']);
            exit;
        }

        $duplicate = dbOne(
            $pdo,
            'SELECT id
               FROM tblSkillType
              WHERE LOWER(name) = LOWER(:name)
                AND id <> :idSkillType
              LIMIT 1',
            [
                'name' => $name,
                'idSkillType' => $idSkillType,
            ]
        );

        if ($duplicate !== null) {
            http_response_code(400);
            echo json_encode(['error' => 'Er bestaat al een andere categorie met deze naam.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE tblSkillType
                SET name = :name
              WHERE id = :idSkillType'
        );
        $stmt->execute([
            'name' => $name,
            'idSkillType' => $idSkillType,
        ]);

        echo json_encode([
            'skillTypes' => fetchAdminSkillTypeOptions($pdo),
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'DELETE FROM tblLinkSkillType
          WHERE idSkillType = :idSkillType'
    );
    $stmt->execute(['idSkillType' => $idSkillType]);

    $stmt = $pdo->prepare(
        'DELETE FROM tblSkillType
          WHERE id = :idSkillType'
    );
    $stmt->execute(['idSkillType' => $idSkillType]);

    $pdo->commit();

    echo json_encode([
        'skillTypes' => fetchAdminSkillTypeOptions($pdo),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon de categorie niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
