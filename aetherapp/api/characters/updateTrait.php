<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/traitUtils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $action = (string) ($input['action'] ?? '');
    $idCharacter = (int) ($input['idCharacter'] ?? 0);
    $idTrait = (int) ($input['idTrait'] ?? 0);
    $idCurrentTrait = (int) ($input['idCurrentTrait'] ?? 0);

    if ($idCharacter <= 0 || $idTrait <= 0 || $action === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige parameters.']);
        exit;
    }

    $character = dbOne(
        $pdo,
        'SELECT id, type, class, idUser, experienceToTrait FROM tblCharacter WHERE id = :id',
        ['id' => $idCharacter]
    );

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $trait = dbOne(
        $pdo,
        "SELECT
            id,
            name,
            `class`,
            `type`,
            isUnique,
            rankType,
            traitGroup,
            cost,
            income,
            evolution,
            staffRequirements,
            description
         FROM tblTrait
         WHERE id = :id",
        ['id' => $idTrait]
    );

    if (!$trait) {
        http_response_code(404);
        echo json_encode(['error' => 'Trait niet gevonden.']);
        exit;
    }

    $traitClass = (string) $trait['class'];
    $characterClass = (string) $character['class'];
    if ($traitClass !== 'all' && $traitClass !== $characterClass) {
        http_response_code(400);
        echo json_encode(['error' => 'Deze trait is niet beschikbaar voor deze klasse.']);
        exit;
    }

    $currentLinkedTrait = null;
    $currentTrait = null;
    if ($idCurrentTrait > 0) {
        $currentLinkedTrait = dbOne(
            $pdo,
            'SELECT id, rankValue FROM tblLinkCharacterTrait WHERE idCharacter = :idCharacter AND idTrait = :idTrait',
            [
                'idCharacter' => $idCharacter,
                'idTrait' => $idCurrentTrait,
            ]
        );

        if ($currentLinkedTrait) {
            $currentTrait = dbOne(
                $pdo,
                "SELECT
                    id,
                    name,
                    `class`,
                    `type`,
                    isUnique,
                    rankType,
                    traitGroup,
                    cost,
                    income,
                    evolution,
                    staffRequirements,
                    description
                 FROM tblTrait
                 WHERE id = :id",
                ['id' => $idCurrentTrait]
            );
        }
    }

    $linkedTrait = dbOne(
        $pdo,
        'SELECT id, rankValue FROM tblLinkCharacterTrait WHERE idCharacter = :idCharacter AND idTrait = :idTrait',
        [
            'idCharacter' => $idCharacter,
            'idTrait' => $idTrait,
        ]
    );

    $rankType = (string) $trait['rankType'];
    $currentRank = $linkedTrait ? (int) $linkedTrait['rankValue'] : 0;
    $pointSummary = getCharacterPointSummary($pdo, [
        'id' => $character['id'],
        'type' => $character['type'],
        'idUser' => $character['idUser'],
        'experienceToTrait' => $character['experienceToTrait'],
    ]);

    $currentCost = $linkedTrait ? calculateTraitPointCost($trait, $currentRank) : 0;
    $newRank = $currentRank;

    if ($action === 'add') {
        if ($linkedTrait) {
            echo json_encode(['error' => 'Trait is al gekoppeld.']);
            exit;
        }

        if ((int) $trait['isUnique'] === 1) {
            if ((string) $trait['type'] === 'profession') {
                $existingProfession = dbOne(
                    $pdo,
                    "SELECT lct.id
                     FROM tblLinkCharacterTrait AS lct
                     JOIN tblTrait AS t
                           ON t.id = lct.idTrait
                     WHERE lct.idCharacter = :idCharacter
                       AND t.`type` = 'profession'",
                    ['idCharacter' => $idCharacter]
                );

                if ($existingProfession) {
                    echo json_encode(['error' => 'Er is al een beroep gekoppeld aan dit personage.']);
                    exit;
                }
            }
        }

        $newRank = ($rankType === 'singular') ? 1 : 1;
        $deltaCost = calculateTraitPointCost($trait, $newRank);
        if ($deltaCost > $pointSummary['availableStatusPoints']) {
            echo json_encode(['error' => 'Onvoldoende statuspunten.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tblLinkCharacterTrait (idCharacter, idTrait, rankValue) VALUES (:idCharacter, :idTrait, :rankValue)'
        );
        $stmt->execute([
            'idCharacter' => $idCharacter,
            'idTrait' => $idTrait,
            'rankValue' => $newRank,
        ]);
    } elseif ($action === 'change') {
        if (!$currentLinkedTrait || !$currentTrait) {
            echo json_encode(['error' => 'Bestaande trait-link niet gevonden.']);
            exit;
        }

        if ($linkedTrait && $idCurrentTrait !== $idTrait) {
            echo json_encode(['error' => 'Deze trait is al gekoppeld.']);
            exit;
        }

        if ((int) $currentTrait['isUnique'] !== 1 || (int) $trait['isUnique'] !== 1) {
            echo json_encode(['error' => 'Alleen unieke traits kunnen gewisseld worden.']);
            exit;
        }

        if ((string) $currentTrait['traitGroup'] !== (string) $trait['traitGroup']) {
            echo json_encode(['error' => 'Je kan alleen wisselen binnen dezelfde traitgroep.']);
            exit;
        }

        if ($idCurrentTrait === $idTrait) {
            echo json_encode(['status' => 'ok']);
            exit;
        }

        $currentRank = (int) $currentLinkedTrait['rankValue'];
        $currentCost = calculateTraitPointCost($currentTrait, $currentRank);
        $newCost = calculateTraitPointCost($trait, $currentRank);
        $deltaCost = $newCost - $currentCost;

        if ($deltaCost > $pointSummary['availableStatusPoints']) {
            echo json_encode(['error' => 'Onvoldoende statuspunten voor deze wijziging.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE tblLinkCharacterTrait SET idTrait = :idTrait WHERE id = :id'
        );
        $stmt->execute([
            'idTrait' => $idTrait,
            'id' => $currentLinkedTrait['id'],
        ]);
    } elseif ($action === 'remove') {
        if (!$linkedTrait) {
            echo json_encode(['error' => 'Trait-link niet gevonden.']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tblLinkCharacterTrait WHERE id = :id');
        $stmt->execute(['id' => $linkedTrait['id']]);
    } elseif ($action === 'rank_up' || $action === 'rank_down') {
        if (!$linkedTrait) {
            echo json_encode(['error' => 'Trait-link niet gevonden.']);
            exit;
        }

        if ($rankType === 'singular') {
            echo json_encode(['error' => 'Deze trait heeft geen rang.']);
            exit;
        }

        if ($action === 'rank_up') {
            $newRank = $currentRank + 1;
        } else {
            if ($rankType === 'range_positive' && $currentRank <= 1) {
                echo json_encode(['error' => 'De rang kan niet lager dan 1.']);
                exit;
            }
            $newRank = $currentRank - 1;
        }

        $newCost = calculateTraitPointCost($trait, $newRank);
        $deltaCost = $newCost - $currentCost;

        if ($deltaCost > $pointSummary['availableStatusPoints']) {
            echo json_encode(['error' => 'Onvoldoende statuspunten.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE tblLinkCharacterTrait SET rankValue = :rankValue WHERE id = :id'
        );
        $stmt->execute([
            'rankValue' => $newRank,
            'id' => $linkedTrait['id'],
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Onbekende actie.']);
        exit;
    }

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon trait niet bijwerken.',
        'details' => $e->getMessage(),
    ]);
}
