<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/traitUtils.php';

header('Content-Type: application/json; charset=utf-8');

function canCurrentUserManageTrait(array $trait, string $currentUserRole): bool
{
    if (isPrivilegedUserRole($currentUserRole)) {
        return true;
    }

    return !traitHasFlag($trait, 'secret');
}

try {
    $pdo = getPDO();
    $currentUserRole = getCurrentUserRole($pdo);
    $canOverspendStatusPoints = isPrivilegedUserRole($currentUserRole);
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
        'SELECT id, type, class, state, idUser, experienceToTrait FROM tblCharacter WHERE id = :id',
        ['id' => $idCharacter]
    );

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $currentUserId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
    $canEditTraits = $canOverspendStatusPoints || (
        $currentUserRole === 'participant' &&
        (string) ($character['type'] ?? '') === 'player' &&
        (string) ($character['state'] ?? '') === 'draft' &&
        (int) ($character['idUser'] ?? 0) === $currentUserId
    );

    if (!$canEditTraits) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om traits van dit personage aan te passen.']);
        exit;
    }

    $trait = getTraitDefinition($pdo, $idTrait);

    if (!$trait) {
        http_response_code(404);
        echo json_encode(['error' => 'Trait niet gevonden.']);
        exit;
    }

    if (!canCurrentUserManageTrait($trait, $currentUserRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om deze trait aan te passen.']);
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
            $currentTrait = getTraitDefinition($pdo, $idCurrentTrait);
            if ($currentTrait && !canCurrentUserManageTrait($currentTrait, $currentUserRole)) {
                http_response_code(403);
                echo json_encode(['error' => 'Je hebt geen rechten om deze trait aan te passen.']);
                exit;
            }
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
    if ($linkedTrait && isCompanyShareTrait($trait)) {
        $currentRank = getCompanyShareBaseRank([
            'name' => (string) ($trait['name'] ?? ''),
            'baseRank' => $currentRank,
            'isCompanyShare' => true,
            'shareDraftStep' => $trait['shareDraftStep'] ?? null,
        ]);
    }
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

        if (isGroupedTrait($trait)) {
            $existingGroupTrait = null;
            foreach (getCharacterTraitLinks($pdo, $idCharacter, [(string) ($trait['type'] ?? '')]) as $linkedGroupTrait) {
                if (areTraitsInSameSelectionGroup($linkedGroupTrait, $trait)) {
                    $existingGroupTrait = $linkedGroupTrait;
                    break;
                }
            }

            if ($existingGroupTrait) {
                echo json_encode(['error' => 'Je kan maar 1 trait kiezen binnen deze categorie.']);
                exit;
            }
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

        $newRank = isCompanyShareTrait($trait)
            ? getCompanyShareDraftStep($trait)
            : 1;
        $deltaCost = calculateTraitPointCost($trait, $newRank);
        if (!$canOverspendStatusPoints && $deltaCost > $pointSummary['availableStatusPoints']) {
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

        if (!isGroupedTrait($currentTrait) || !isGroupedTrait($trait)) {
            echo json_encode(['error' => 'Alleen gegroepeerde traits kunnen gewisseld worden.']);
            exit;
        }

        if (!areTraitsInSameSelectionGroup($currentTrait, $trait)) {
            echo json_encode(['error' => 'Je kan alleen wisselen binnen dezelfde traitgroep.']);
            exit;
        }

        if ($idCurrentTrait === $idTrait) {
            echo json_encode(['status' => 'ok']);
            exit;
        }

        $currentRank = (int) $currentLinkedTrait['rankValue'];
        if (isCompanyShareTrait($currentTrait)) {
            $currentRank = getCompanyShareBaseRank([
                'name' => (string) ($currentTrait['name'] ?? ''),
                'baseRank' => $currentRank,
                'isCompanyShare' => true,
                'shareDraftStep' => $currentTrait['shareDraftStep'] ?? null,
            ]);
        }
        $currentCost = calculateTraitPointCost($currentTrait, $currentRank);
        $newCost = calculateTraitPointCost($trait, $currentRank);
        $deltaCost = $newCost - $currentCost;

        if (!$canOverspendStatusPoints && $deltaCost > $pointSummary['availableStatusPoints']) {
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

        if (isCompanyShareTrait($trait) && (string) ($character['state'] ?? '') !== 'draft') {
            echo json_encode(['error' => 'Aandelen verhoog je na draft via de economie-tab.']);
            exit;
        }

        $rankStep = isCompanyShareTrait($trait) ? getCompanyShareDraftStep($trait) : 1;

        if ($action === 'rank_up') {
            $newRank = $currentRank + $rankStep;
        } else {
            $minimumRank = isCompanyShareTrait($trait) ? getCompanyShareDraftStep($trait) : 1;
            if ($rankType === 'range_positive' && $currentRank <= $minimumRank) {
                $minimumRankLabel = isCompanyShareTrait($trait)
                    ? (string) $minimumRank . '%.'
                    : '1.';
                echo json_encode(['error' => 'De rang kan niet lager dan ' . $minimumRankLabel]);
                exit;
            }
            $newRank = $currentRank - $rankStep;
        }

        $newCost = calculateTraitPointCost($trait, $newRank);
        $deltaCost = $newCost - $currentCost;

        if (!$canOverspendStatusPoints && $deltaCost > $pointSummary['availableStatusPoints']) {
            echo json_encode(['error' => 'Onvoldoende statuspunten.']);
            exit;
        }

        if (isCompanyShareTrait($trait) && $action === 'rank_up') {
            try {
                $shareLink = dbOne(
                    $pdo,
                    'SELECT idCompany, COALESCE(extraPercentage, 0) AS extraPercentage
                     FROM tblLinkCharacterTraitCompany
                     WHERE idLinkCharacterTrait = :idLinkCharacterTrait',
                    ['idLinkCharacterTrait' => (int) $linkedTrait['id']]
                );

                $idCompany = (int) ($shareLink['idCompany'] ?? 0);
                if ($idCompany > 0) {
                    $rows = dbAll(
                        $pdo,
                        'SELECT
                            lct.id,
                            lct.idTrait,
                            lct.rankValue,
                            COALESCE(lctc.extraPercentage, 0) AS extraPercentage
                         FROM tblLinkCharacterTraitCompany AS lctc
                         JOIN tblLinkCharacterTrait AS lct
                           ON lct.id = lctc.idLinkCharacterTrait
                         WHERE lctc.idCompany = :idCompany',
                        ['idCompany' => $idCompany]
                    );

                    $allocatedOtherPercentage = 0;
                    foreach ($rows as $row) {
                        $linkedCompanyTrait = getTraitDefinition($pdo, (int) ($row['idTrait'] ?? 0));
                        if (!$linkedCompanyTrait || !isCompanyShareTrait($linkedCompanyTrait)) {
                            continue;
                        }

                        if ((int) ($row['id'] ?? 0) === (int) $linkedTrait['id']) {
                            continue;
                        }

                        $allocatedOtherPercentage += max(
                            0,
                            (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0)
                        );
                    }

                    $nextPercentage = $newRank + (int) ($shareLink['extraPercentage'] ?? 0);
                    if ($allocatedOtherPercentage + $nextPercentage > 100) {
                        echo json_encode(['error' => 'Dit bedrijf heeft niet genoeg vrije aandelen voor deze verhoging.']);
                        exit;
                    }
                }
            } catch (Throwable $shareCapacityException) {
                // Ignore missing share-link storage until the migration is applied.
            }
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
