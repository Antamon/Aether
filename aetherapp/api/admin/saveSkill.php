<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/adminUtils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$idSkill = (int) ($input['idSkill'] ?? 0);
$name = trim((string) ($input['name'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$beginner = trim((string) ($input['beginner'] ?? ''));
$professional = trim((string) ($input['professional'] ?? ''));
$master = trim((string) ($input['master'] ?? ''));
$isSecret = (bool) ($input['isSecret'] ?? false);
$categoryIds = is_array($input['categoryIds'] ?? null) ? $input['categoryIds'] : [];
$specialisations = is_array($input['specialisations'] ?? null) ? $input['specialisations'] : [];

if ($idSkill <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldige vaardigheid geselecteerd.']);
    exit;
}

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'De naam van de vaardigheid is verplicht.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedAdminAccess($pdo);

    $existingSkill = dbOne(
        $pdo,
        'SELECT id
           FROM tblSkill
          WHERE id = :idSkill',
        ['idSkill' => $idSkill]
    );

    if ($existingSkill === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Vaardigheid niet gevonden.']);
        exit;
    }

    $duplicateSkill = dbOne(
        $pdo,
        'SELECT id
           FROM tblSkill
          WHERE LOWER(name) = LOWER(:name)
            AND id <> :idSkill
          LIMIT 1',
        [
            'name' => $name,
            'idSkill' => $idSkill,
        ]
    );

    if ($duplicateSkill !== null) {
        http_response_code(400);
        echo json_encode(['error' => 'Er bestaat al een andere vaardigheid met deze naam.']);
        exit;
    }

    $skillTypeOptions = fetchAdminSkillTypeOptions($pdo);
    $skillTypeMap = [];
    foreach ($skillTypeOptions as $option) {
        $skillTypeMap[(int) ($option['idSkillType'] ?? 0)] = $option;
    }

    $normalizedCategoryIds = [];
    foreach ($categoryIds as $categoryId) {
        $normalizedCategoryId = (int) $categoryId;
        if ($normalizedCategoryId <= 0 || !isset($skillTypeMap[$normalizedCategoryId])) {
            continue;
        }

        if (!in_array($normalizedCategoryId, $normalizedCategoryIds, true)) {
            $normalizedCategoryIds[] = $normalizedCategoryId;
        }
    }

    $existingSpecialisationRows = dbAll(
        $pdo,
        'SELECT id, name, kind
           FROM tblSkillSpecialisation
          WHERE idSkill = :idSkill',
        ['idSkill' => $idSkill]
    );
    $existingSpecialisationsById = [];
    foreach ($existingSpecialisationRows as $row) {
        $existingSpecialisationsById[(int) ($row['id'] ?? 0)] = $row;
    }

    $defaultKind = 'specialisation';
    foreach ($normalizedCategoryIds as $categoryId) {
        $category = $skillTypeMap[$categoryId] ?? null;
        if ($category !== null && normalizeAdminTextKey((string) ($category['code'] ?? '')) === 'discipline') {
            $defaultKind = 'discipline';
            break;
        }
    }

    $normalizedSpecialisations = [];
    $seenSpecialisationNames = [];

    foreach ($specialisations as $specialisation) {
        if (!is_array($specialisation)) {
            continue;
        }

        $specialisationId = (int) ($specialisation['idSkillSpecialisation'] ?? 0);
        $specialisationName = trim((string) ($specialisation['name'] ?? ''));

        if ($specialisationName === '') {
            throw new RuntimeException('Elke specialisatie moet een naam hebben.');
        }

        $specialisationNameKey = normalizeAdminTextKey($specialisationName);
        if ($specialisationNameKey === '') {
            throw new RuntimeException('Elke specialisatie moet een geldige naam hebben.');
        }

        if (isset($seenSpecialisationNames[$specialisationNameKey])) {
            throw new RuntimeException('Specialisatienamen moeten uniek zijn per vaardigheid.');
        }
        $seenSpecialisationNames[$specialisationNameKey] = true;

        if ($specialisationId > 0 && !isset($existingSpecialisationsById[$specialisationId])) {
            throw new RuntimeException('Een bestaande specialisatie hoort niet bij deze vaardigheid.');
        }

        $existingKind = $specialisationId > 0
            ? (string) ($existingSpecialisationsById[$specialisationId]['kind'] ?? '')
            : '';

        $normalizedSpecialisations[] = [
            'idSkillSpecialisation' => $specialisationId,
            'name' => $specialisationName,
            'kind' => $existingKind !== '' ? $existingKind : $defaultKind,
        ];
    }

    $visibilityMap = getSkillVisibilityStorageMap($pdo);
    $visibility = $isSecret ? $visibilityMap['secret'] : $visibilityMap['public'];

    $pdo->beginTransaction();

    $updateSkillStmt = $pdo->prepare(
        'UPDATE tblSkill
            SET name = :name,
                description = :description,
                beginner = :beginner,
                professional = :professional,
                master = :master,
                visibility = :visibility
          WHERE id = :idSkill'
    );
    $updateSkillStmt->execute([
        'idSkill' => $idSkill,
        'name' => $name,
        'description' => $description,
        'beginner' => $beginner,
        'professional' => $professional,
        'master' => $master,
        'visibility' => $visibility,
    ]);

    $deleteSkillTypeLinksStmt = $pdo->prepare(
        'DELETE FROM tblLinkSkillType
          WHERE idSkill = :idSkill'
    );
    $deleteSkillTypeLinksStmt->execute(['idSkill' => $idSkill]);

    if (count($normalizedCategoryIds) > 0) {
        $insertSkillTypeLinkStmt = $pdo->prepare(
            'INSERT INTO tblLinkSkillType (idSkill, idSkillType)
             VALUES (:idSkill, :idSkillType)'
        );

        foreach ($normalizedCategoryIds as $categoryId) {
            $insertSkillTypeLinkStmt->execute([
                'idSkill' => $idSkill,
                'idSkillType' => $categoryId,
            ]);
        }
    }

    $requestedSpecialisationIds = array_values(array_filter(array_map(
        static fn(array $specialisation): int => (int) ($specialisation['idSkillSpecialisation'] ?? 0),
        $normalizedSpecialisations
    )));

    $deleteCharacterLinksStmt = $pdo->prepare(
        'DELETE FROM tblCharacterSpecialisation
          WHERE idSkillSpecialisation = :idSkillSpecialisation'
    );
    $deleteCompanyLinksStmt = $pdo->prepare(
        'DELETE FROM tblCompanyPersonnelSkillSpecialisation
          WHERE idSkillSpecialisation = :idSkillSpecialisation'
    );
    $deleteSkillSpecialisationStmt = $pdo->prepare(
        'DELETE FROM tblSkillSpecialisation
          WHERE id = :idSkillSpecialisation
            AND idSkill = :idSkill'
    );
    $updateSkillSpecialisationStmt = $pdo->prepare(
        'UPDATE tblSkillSpecialisation
            SET name = :name,
                kind = :kind
          WHERE id = :idSkillSpecialisation
            AND idSkill = :idSkill'
    );
    $insertSkillSpecialisationStmt = $pdo->prepare(
        'INSERT INTO tblSkillSpecialisation (idSkill, name, kind)
         VALUES (:idSkill, :name, :kind)'
    );

    foreach (array_keys($existingSpecialisationsById) as $existingSpecialisationId) {
        if (in_array((int) $existingSpecialisationId, $requestedSpecialisationIds, true)) {
            continue;
        }

        $deleteCharacterLinksStmt->execute(['idSkillSpecialisation' => (int) $existingSpecialisationId]);
        $deleteCompanyLinksStmt->execute(['idSkillSpecialisation' => (int) $existingSpecialisationId]);
        $deleteSkillSpecialisationStmt->execute([
            'idSkillSpecialisation' => (int) $existingSpecialisationId,
            'idSkill' => $idSkill,
        ]);
    }

    foreach ($normalizedSpecialisations as $specialisation) {
        $specialisationId = (int) ($specialisation['idSkillSpecialisation'] ?? 0);
        if ($specialisationId > 0) {
            $updateSkillSpecialisationStmt->execute([
                'idSkillSpecialisation' => $specialisationId,
                'idSkill' => $idSkill,
                'name' => (string) ($specialisation['name'] ?? ''),
                'kind' => (string) ($specialisation['kind'] ?? 'specialisation'),
            ]);
            continue;
        }

        $insertSkillSpecialisationStmt->execute([
            'idSkill' => $idSkill,
            'name' => (string) ($specialisation['name'] ?? ''),
            'kind' => (string) ($specialisation['kind'] ?? 'specialisation'),
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'skill' => fetchAdminSkillDetail($pdo, $idSkill),
        'skills' => fetchAdminSkillList($pdo),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $httpCode = http_response_code();
    if ($httpCode < 400) {
        http_response_code($e instanceof RuntimeException ? 400 : 500);
    }

    echo json_encode([
        'error' => 'Kon de vaardigheid niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
