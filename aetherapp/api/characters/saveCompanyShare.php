<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/companyShareUtils.php';
require_once __DIR__ . '/../companies/companyUtils.php';

function getCompanyShareLinkRecord(PDO $pdo, int $idLinkCharacterTrait): ?array
{
    return dbOne(
        $pdo,
        'SELECT
            lct.id,
            lct.idCharacter,
            lct.rankValue,
            t.id AS idTrait,
            t.name,
            c.id AS characterId,
            c.type AS characterType,
            c.state AS characterState,
            c.idUser,
            c.bankaccount,
            lctc.idCompany,
            COALESCE(lctc.extraPercentage, 0) AS extraPercentage
         FROM tblLinkCharacterTrait AS lct
         JOIN tblTrait AS t
           ON t.id = lct.idTrait
         JOIN tblCharacter AS c
           ON c.id = lct.idCharacter
         LEFT JOIN tblLinkCharacterTraitCompany AS lctc
           ON lctc.idLinkCharacterTrait = lct.id
         WHERE lct.id = :idLinkCharacterTrait',
        ['idLinkCharacterTrait' => $idLinkCharacterTrait]
    );
}

function getAllocatedCompanySharePercentage(PDO $pdo, int $idCompany, int $excludeLinkCharacterTraitId = 0): int
{
    if ($idCompany <= 0) {
        return 0;
    }

    $rows = dbAll(
        $pdo,
        'SELECT
            lct.id,
            lct.rankValue,
            COALESCE(lctc.extraPercentage, 0) AS extraPercentage,
            t.name
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct
           ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTrait AS t
           ON t.id = lct.idTrait
         WHERE lctc.idCompany = :idCompany',
        ['idCompany' => $idCompany]
    );

    $allocatedPercentage = 0;
    foreach ($rows as $row) {
        if (!isCompanyShareTrait(['name' => (string) ($row['name'] ?? '')])) {
            continue;
        }

        if ($excludeLinkCharacterTraitId > 0 && (int) ($row['id'] ?? 0) === $excludeLinkCharacterTraitId) {
            continue;
        }

        $allocatedPercentage += max(
            0,
            (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0)
        );
    }

    return $allocatedPercentage;
}

function upsertCompanyShareLink(PDO $pdo, int $idLinkCharacterTrait, ?int $idCompany, ?int $extraPercentage = null): void
{
    $params = [
        'idLinkCharacterTrait' => $idLinkCharacterTrait,
        'idCompany' => $idCompany,
    ];

    if ($extraPercentage === null) {
        $sql = 'INSERT INTO tblLinkCharacterTraitCompany (idLinkCharacterTrait, idCompany)
                VALUES (:idLinkCharacterTrait, :idCompany)
                ON DUPLICATE KEY UPDATE idCompany = VALUES(idCompany)';
    } else {
        $params['extraPercentage'] = max(0, $extraPercentage);
        $sql = 'INSERT INTO tblLinkCharacterTraitCompany (idLinkCharacterTrait, idCompany, extraPercentage)
                VALUES (:idLinkCharacterTrait, :idCompany, :extraPercentage)
                ON DUPLICATE KEY UPDATE
                    idCompany = VALUES(idCompany),
                    extraPercentage = VALUES(extraPercentage)';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$action = trim((string) ($input['action'] ?? ''));
$idLinkCharacterTrait = isset($input['idLinkCharacterTrait']) ? (int) $input['idLinkCharacterTrait'] : 0;
$idCompany = array_key_exists('idCompany', $input) && $input['idCompany'] !== null && $input['idCompany'] !== ''
    ? (int) $input['idCompany']
    : null;

if ($action === '' || $idLinkCharacterTrait <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUserRole = getCurrentUserRole($pdo);
    $currentUserId = getCurrentUserId();

    if ($currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $shareLink = getCompanyShareLinkRecord($pdo, $idLinkCharacterTrait);
    if (!$shareLink) {
        http_response_code(404);
        echo json_encode(['error' => 'Aandelen-trait niet gevonden.']);
        exit;
    }

    $trait = [
        'name' => (string) ($shareLink['name'] ?? ''),
        'rank' => (int) ($shareLink['rankValue'] ?? 0),
        'baseRank' => (int) ($shareLink['rankValue'] ?? 0),
        'shareExtraRank' => (int) ($shareLink['extraPercentage'] ?? 0),
    ];

    if (!isCompanyShareTrait($trait)) {
        http_response_code(400);
        echo json_encode(['error' => 'Deze trait is geen aandelen-trait.']);
        exit;
    }

    $character = [
        'id' => (int) ($shareLink['characterId'] ?? 0),
        'type' => (string) ($shareLink['characterType'] ?? ''),
        'state' => (string) ($shareLink['characterState'] ?? ''),
        'idUser' => (int) ($shareLink['idUser'] ?? 0),
        'bankaccount' => round((float) ($shareLink['bankaccount'] ?? 0), 2),
    ];

    if ($action === 'assign_company' || $action === 'clear_company') {
        if (!canManageCompanyShareAssignments($character, $currentUserRole, $currentUserId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Je hebt geen rechten om aandelenbedrijven te koppelen.']);
            exit;
        }

        if ($action === 'clear_company') {
            upsertCompanyShareLink(
                $pdo,
                $idLinkCharacterTrait,
                null,
                (int) ($shareLink['extraPercentage'] ?? 0)
            );

            echo json_encode(['success' => true]);
            exit;
        }

        if ($idCompany === null || $idCompany <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Kies een bedrijf om te koppelen.']);
            exit;
        }

        $company = dbOne(
            $pdo,
            'SELECT id, companyName, companyValue
             FROM tblCompany
             WHERE id = :id',
            ['id' => $idCompany]
        );

        if (!$company) {
            http_response_code(404);
            echo json_encode(['error' => 'Bedrijf niet gevonden.']);
            exit;
        }

        $company = enrichCompanyWithType($company);
        $shareMetadata = getCompanyShareTraitMetadata($trait);
        if (($shareMetadata['companyTypeKey'] ?? '') !== ($company['companyTypeKey'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => 'Dit bedrijf past niet bij het gekozen aandeeltype.']);
            exit;
        }

        $currentPercentage = getCompanyShareTotalRank($trait);
        $allocatedElsewhere = getAllocatedCompanySharePercentage($pdo, $idCompany, $idLinkCharacterTrait);
        if ($allocatedElsewhere + $currentPercentage > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Dit bedrijf heeft niet genoeg vrije aandelen voor deze koppeling.']);
            exit;
        }

        upsertCompanyShareLink(
            $pdo,
            $idLinkCharacterTrait,
            $idCompany,
            (int) ($shareLink['extraPercentage'] ?? 0)
        );

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'increase_rank') {
        if (!canIncreaseCompanyShareRank($character, $currentUserRole, $currentUserId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Je hebt geen rechten om dit aandeel te verhogen.']);
            exit;
        }

        $currentCompanyId = (int) ($shareLink['idCompany'] ?? 0);
        if ($currentCompanyId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Koppel eerst een bedrijf aan dit aandeel.']);
            exit;
        }

        $company = dbOne(
            $pdo,
            'SELECT id, companyName, companyValue
             FROM tblCompany
             WHERE id = :id',
            ['id' => $currentCompanyId]
        );

        if (!$company) {
            http_response_code(404);
            echo json_encode(['error' => 'Het gekoppelde bedrijf bestaat niet meer.']);
            exit;
        }

        $company = enrichCompanyWithType($company);
        $shareMetadata = getCompanyShareTraitMetadata($trait);
        if (($shareMetadata['companyTypeKey'] ?? '') !== ($company['companyTypeKey'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => 'Het gekoppelde bedrijf past niet langer bij dit aandeeltype.']);
            exit;
        }

        $currentPercentage = getCompanyShareTotalRank($trait);
        $allocatedElsewhere = getAllocatedCompanySharePercentage($pdo, $currentCompanyId, $idLinkCharacterTrait);
        if ($allocatedElsewhere + $currentPercentage + 1 > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Dit bedrijf heeft geen vrij aandeel meer voor +1%.']);
            exit;
        }

        $nextPercentageCost = round(normalizeCompanyValue($company['companyValue'] ?? 0) / 100, 2);
        if ($nextPercentageCost <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'De bedrijfswaarde is ongeldig voor een aandelenverhoging.']);
            exit;
        }

        if ($nextPercentageCost > round((float) ($character['bankaccount'] ?? 0), 2)) {
            http_response_code(400);
            echo json_encode(['error' => 'Onvoldoende saldo op de bankrekening.']);
            exit;
        }

        $pdo->beginTransaction();

        upsertCompanyShareLink(
            $pdo,
            $idLinkCharacterTrait,
            $currentCompanyId,
            (int) ($shareLink['extraPercentage'] ?? 0) + 1
        );

        $stmt = $pdo->prepare(
            'UPDATE tblCharacter
             SET bankaccount = ROUND(COALESCE(bankaccount, 0) - :amount, 2)
             WHERE id = :idCharacter
               AND COALESCE(bankaccount, 0) >= :amount'
        );
        $stmt->execute([
            'amount' => $nextPercentageCost,
            'idCharacter' => (int) $character['id'],
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Onvoldoende saldo op rekening.');
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'nextPercentageCost' => $nextPercentageCost,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Onbekende actie.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon aandelen niet bewaren.',
        'details' => $e->getMessage(),
    ]);
}
