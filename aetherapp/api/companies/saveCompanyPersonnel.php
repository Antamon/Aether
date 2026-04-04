<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';

function resolveCompanyPersonnelSpecialisationId(PDO $pdo, int $idSkill, array $input): int
{
    if ($idSkill <= 0) {
        throw new RuntimeException('Kies eerst een geldige vaardigheid.');
    }

    $idSkillSpecialisation = (int) ($input['idSkillSpecialisation'] ?? 0);
    if ($idSkillSpecialisation > 0) {
        $row = dbOne(
            $pdo,
            'SELECT id
               FROM tblSkillSpecialisation
              WHERE id = :idSkillSpecialisation
                AND idSkill = :idSkill',
            [
                'idSkillSpecialisation' => $idSkillSpecialisation,
                'idSkill' => $idSkill,
            ]
        );

        if ($row === null) {
            throw new RuntimeException('De gekozen specialisatie hoort niet bij deze vaardigheid.');
        }

        return $idSkillSpecialisation;
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Geef een naam op voor de nieuwe specialisatie.');
    }

    $existing = dbOne(
        $pdo,
        'SELECT id
           FROM tblSkillSpecialisation
          WHERE idSkill = :idSkill
            AND LOWER(name) = LOWER(:name)
          LIMIT 1',
        [
            'idSkill' => $idSkill,
            'name' => $name,
        ]
    );

    if ($existing !== null) {
        return (int) $existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblSkillSpecialisation (idSkill, name, kind)
         VALUES (:idSkill, :name, :kind)'
    );
    $stmt->execute([
        'idSkill' => $idSkill,
        'name' => $name,
        'kind' => 'specialisation',
    ]);

    return (int) $pdo->lastInsertId();
}

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$idCompany = (int) ($postData['idCompany'] ?? 0);
$personnel = $postData['personnel'] ?? [];

if ($idCompany <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig bedrijf ID.']);
    exit;
}

if (!is_array($personnel)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige personeelsdata.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $company = dbOne(
        $pdo,
        'SELECT id
           FROM tblCompany
          WHERE id = :idCompany',
        ['idCompany' => $idCompany]
    );

    if ($company === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
        exit;
    }

    $seenCharacters = [];
    $normalizedPersonnel = [];

    foreach ($personnel as $personnelEntry) {
        if (!is_array($personnelEntry)) {
            continue;
        }

        $idCharacter = (int) ($personnelEntry['idCharacter'] ?? 0);
        if ($idCharacter <= 0) {
            continue;
        }

        if (isset($seenCharacters[$idCharacter])) {
            throw new RuntimeException('Een personage kan maar één keer aan hetzelfde bedrijf gekoppeld worden.');
        }
        $seenCharacters[$idCharacter] = true;

        $character = dbOne(
            $pdo,
            "SELECT id
               FROM tblCharacter
              WHERE id = :idCharacter
                AND `state` <> 'draft'",
            ['idCharacter' => $idCharacter]
        );

        if ($character === null) {
            throw new RuntimeException('Een gekozen personage bestaat niet of staat nog in draft.');
        }

        $importance = normalizeCompanyPersonnelImportance($personnelEntry['importance'] ?? 'Moderate');
        $salaryIncreasePercentage = normalizeCompanyPersonnelSalaryIncreasePercentage(
            $personnelEntry['salaryIncreasePercentage'] ?? 0
        );

        $skills = is_array($personnelEntry['skills'] ?? null) ? $personnelEntry['skills'] : [];
        $normalizedSkills = [];
        $seenSkills = [];
        $validSkillCount = 0;

        foreach ($skills as $skillEntry) {
            if (!is_array($skillEntry)) {
                continue;
            }

            $idSkill = (int) ($skillEntry['idSkill'] ?? 0);
            if ($idSkill <= 0) {
                continue;
            }

            $validSkillCount++;
            if ($validSkillCount > 1) {
                throw new RuntimeException('Per personeelslid kan maar één vaardigheid gekoppeld worden.');
            }

            if (isset($seenSkills[$idSkill])) {
                throw new RuntimeException('Een vaardigheid kan maar één keer per personeelslid gekoppeld worden.');
            }
            $seenSkills[$idSkill] = true;

            $skill = dbOne(
                $pdo,
                'SELECT id
                   FROM tblSkill
                  WHERE id = :idSkill',
                ['idSkill' => $idSkill]
            );

            if ($skill === null) {
                throw new RuntimeException('Een gekozen vaardigheid bestaat niet.');
            }

            $level = normalizeCompanyPersonnelSkillLevel($skillEntry['level'] ?? 1);
            $specialisations = is_array($skillEntry['specialisations'] ?? null) ? $skillEntry['specialisations'] : [];
            $resolvedSpecialisations = [];
            $seenSpecialisations = [];

            foreach ($specialisations as $specialisationEntry) {
                if (!is_array($specialisationEntry)) {
                    continue;
                }

                $resolvedId = resolveCompanyPersonnelSpecialisationId($pdo, $idSkill, $specialisationEntry);
                if (isset($seenSpecialisations[$resolvedId])) {
                    continue;
                }

                $seenSpecialisations[$resolvedId] = true;
                $resolvedSpecialisations[] = $resolvedId;
            }

            $normalizedSkills[] = [
                'idSkill' => $idSkill,
                'level' => $level,
                'specialisations' => $resolvedSpecialisations,
            ];
        }

        $normalizedPersonnel[] = [
            'idCharacter' => $idCharacter,
            'importance' => $importance,
            'salaryIncreasePercentage' => $salaryIncreasePercentage,
            'skills' => $normalizedSkills,
        ];
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'DELETE cpss
           FROM tblCompanyPersonnelSkillSpecialisation AS cpss
           JOIN tblCompanyPersonnelSkill AS cps
             ON cps.id = cpss.idCompanyPersonnelSkill
           JOIN tblCompanyPersonnel AS cp
             ON cp.id = cps.idCompanyPersonnel
          WHERE cp.idCompany = :idCompany'
    );
    $stmt->execute(['idCompany' => $idCompany]);

    $stmt = $pdo->prepare(
        'DELETE cps
           FROM tblCompanyPersonnelSkill AS cps
           JOIN tblCompanyPersonnel AS cp
             ON cp.id = cps.idCompanyPersonnel
          WHERE cp.idCompany = :idCompany'
    );
    $stmt->execute(['idCompany' => $idCompany]);

    $stmt = $pdo->prepare(
        'DELETE FROM tblCompanyPersonnel
          WHERE idCompany = :idCompany'
    );
    $stmt->execute(['idCompany' => $idCompany]);

    $insertPersonnelStmt = $pdo->prepare(
        'INSERT INTO tblCompanyPersonnel (idCompany, idCharacter, importance, salaryIncreasePercentage)
         VALUES (:idCompany, :idCharacter, :importance, :salaryIncreasePercentage)'
    );
    $insertSkillStmt = $pdo->prepare(
        'INSERT INTO tblCompanyPersonnelSkill (idCompanyPersonnel, idSkill, level)
         VALUES (:idCompanyPersonnel, :idSkill, :level)'
    );
    $insertSpecialisationStmt = $pdo->prepare(
        'INSERT INTO tblCompanyPersonnelSkillSpecialisation (idCompanyPersonnelSkill, idSkillSpecialisation)
         VALUES (:idCompanyPersonnelSkill, :idSkillSpecialisation)'
    );

    foreach ($normalizedPersonnel as $personnelEntry) {
        $insertPersonnelStmt->execute([
            'idCompany' => $idCompany,
            'idCharacter' => $personnelEntry['idCharacter'],
            'importance' => $personnelEntry['importance'],
            'salaryIncreasePercentage' => $personnelEntry['salaryIncreasePercentage'],
        ]);

        $idCompanyPersonnel = (int) $pdo->lastInsertId();

        foreach ($personnelEntry['skills'] as $skillEntry) {
            $insertSkillStmt->execute([
                'idCompanyPersonnel' => $idCompanyPersonnel,
                'idSkill' => $skillEntry['idSkill'],
                'level' => $skillEntry['level'],
            ]);

            $idCompanyPersonnelSkill = (int) $pdo->lastInsertId();

            foreach ($skillEntry['specialisations'] as $idSkillSpecialisation) {
                $insertSpecialisationStmt->execute([
                    'idCompanyPersonnelSkill' => $idCompanyPersonnelSkill,
                    'idSkillSpecialisation' => $idSkillSpecialisation,
                ]);
            }
        }
    }

    refreshCompanySnapshotsForCurrentPersonnel($pdo, $idCompany);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'personnelEntries' => getCompanyPersonnelEntries($pdo, $idCompany),
        'snapshots' => getCompanySnapshots($pdo, $idCompany),
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon het bedrijfspersoneel niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
