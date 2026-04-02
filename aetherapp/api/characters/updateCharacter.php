<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/economyUtils.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

// 1. Basisvalidatie: ID verplicht
if (empty($postData['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig character ID.']);
    exit;
}

$id = (int) $postData['id'];
unset($postData['id']); // rest = kolommen die geüpdatet moeten worden

// 2. Datumvelden: lege string NIET updaten (vermijdt "Invalid date")
$dateFields = ['birthDate']; // voeg hier eventueel extra date/datetime kolommen toe

foreach ($dateFields as $field) {
    if (array_key_exists($field, $postData)) {
        $value = trim((string) $postData[$field]);
        if ($value === '') {
            // Kolom uit de UPDATE-query halen als er geen geldige datum is opgegeven
            unset($postData[$field]);
        }
    }
}

if (array_key_exists('experienceToTrait', $postData)) {
    $postData['experienceToTrait'] = max(0, min(6, (int) $postData['experienceToTrait']));
}

$paidHealthFields = ['physicalHealth', 'mentalHealth'];
$freeHealthFields = ['physicalHealthFree', 'mentalHealthFree'];
$allHealthFields = array_merge($paidHealthFields, $freeHealthFields);
$healthFieldPairs = [
    'physicalHealth' => 'physicalHealthFree',
    'mentalHealth' => 'mentalHealthFree',
];

// 3. Na filtering moeten er nog velden overblijven
if (empty($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen velden om bij te werken.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentCharacter = dbOne(
        $pdo,
        'SELECT id, `class`, type, state, idUser, experienceToTrait, physicalHealth, mentalHealth, physicalHealthFree, mentalHealthFree
         FROM tblCharacter
         WHERE id = :id',
        ['id' => $id]
    );

    if (!$currentCharacter) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $shouldPruneClassTraits = false;
    if (array_key_exists('class', $postData)) {
        $shouldPruneClassTraits = (string) ($currentCharacter['class'] ?? '') !== (string) $postData['class'];
    }

    if (array_key_exists('class', $postData)) {
        $currentUserRole = getCurrentUserRole($pdo);
        $currentUserId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;

        $canEditClass = isPrivilegedUserRole($currentUserRole) || (
            $currentUserRole === 'participant'
            && (string) ($currentCharacter['type'] ?? '') === 'player'
            && (string) ($currentCharacter['state'] ?? '') === 'draft'
            && (int) ($currentCharacter['idUser'] ?? 0) === $currentUserId
        );

        if (!$canEditClass) {
            http_response_code(403);
            echo json_encode(['error' => 'Je hebt geen rechten om de klasse van dit personage aan te passen.']);
            exit;
        }
    }

    if (array_key_exists('bankaccount', $postData)) {
        $currentUserRole = getCurrentUserRole($pdo);

        if (!canEditCharacterBankAccount($currentCharacter, $currentUserRole)) {
            http_response_code(403);
            echo json_encode(['error' => 'Je hebt geen rechten om de bankrekening van dit personage aan te passen.']);
            exit;
        }

        $postData['bankaccount'] = round((float) $postData['bankaccount'], 2);
    }

    if (
        array_key_exists('state', $postData)
        && (string) ($currentCharacter['state'] ?? '') === 'draft'
        && (string) $postData['state'] !== 'draft'
        && !array_key_exists('bankaccount', $postData)
    ) {
        $postData['bankaccount'] = getDraftBankAccountAmountForCharacter($pdo, $currentCharacter);
    }

    $containsHealthUpdate = false;
    foreach ($allHealthFields as $field) {
        if (array_key_exists($field, $postData)) {
            $containsHealthUpdate = true;
            break;
        }
    }

    if ($containsHealthUpdate) {
        $currentUserRole = getCurrentUserRole($pdo);
        $currentUserId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;

        $canEditPaidHealth = isPrivilegedUserRole($currentUserRole) || (
            $currentUserRole === 'participant'
            && (string) ($currentCharacter['type'] ?? '') === 'player'
            && (string) ($currentCharacter['state'] ?? '') === 'draft'
            && (int) ($currentCharacter['idUser'] ?? 0) === $currentUserId
        );
        $canEditFreeHealth = isPrivilegedUserRole($currentUserRole);

        foreach ($paidHealthFields as $field) {
            if (!array_key_exists($field, $postData)) {
                continue;
            }

            if (!$canEditPaidHealth) {
                http_response_code(403);
                echo json_encode(['error' => 'Je hebt geen rechten om gezondheid van dit personage aan te passen.']);
                exit;
            }

            $postData[$field] = max(-3, (int) $postData[$field]);
        }

        foreach ($freeHealthFields as $field) {
            if (!array_key_exists($field, $postData)) {
                continue;
            }

            if (!$canEditFreeHealth) {
                http_response_code(403);
                echo json_encode(['error' => 'Je hebt geen rechten om gratis gezondheid van dit personage aan te passen.']);
                exit;
            }

            $postData[$field] = (int) $postData[$field];
        }

        foreach ($healthFieldPairs as $paidField => $freeField) {
            $nextPaid = array_key_exists($paidField, $postData)
                ? (int) $postData[$paidField]
                : (int) ($currentCharacter[$paidField] ?? 0);
            $nextFree = array_key_exists($freeField, $postData)
                ? (int) $postData[$freeField]
                : (int) ($currentCharacter[$freeField] ?? 0);
            $nextTotal = 4 + $nextPaid + $nextFree;

            if ($nextTotal < 1) {
                http_response_code(400);
                echo json_encode(['error' => 'Gezondheid kan niet lager dan 1 worden.']);
                exit;
            }
        }
    }

    $affectsExperienceBudget = array_key_exists('experienceToTrait', $postData);
    foreach ($paidHealthFields as $field) {
        if (array_key_exists($field, $postData)) {
            $affectsExperienceBudget = true;
            break;
        }
    }

    if ($affectsExperienceBudget) {
        $nextCharacter = $currentCharacter;
        foreach (['experienceToTrait', 'physicalHealth', 'mentalHealth'] as $field) {
            if (array_key_exists($field, $postData)) {
                $nextCharacter[$field] = (int) $postData[$field];
            }
        }

        $pointSummary = getCharacterPointSummary($pdo, $nextCharacter);
        $usedSkillExperience = getCharacterSkillExperienceCost($pdo, $id);

        if ((bool) ($pointSummary['isPlayer'] ?? false) && $usedSkillExperience > (int) ($pointSummary['experienceBudget'] ?? 0)) {
            http_response_code(400);
            echo json_encode(['error' => 'Onvoldoende ervaringspunten voor deze wijziging.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    // 4. Dynamische SET-lijst opbouwen
    $columns = array_keys($postData);
    $setParts = [];

    foreach ($columns as $col) {
        $setParts[] = "$col = :$col";
    }

    $setSql = implode(', ', $setParts);

    $sql = "UPDATE tblCharacter SET $setSql WHERE id = :id";

    // 5. Parameters samenstellen
    $params = $postData;
    $params['id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($shouldPruneClassTraits) {
        $deleteTraitLinksStmt = $pdo->prepare(
            "DELETE lct
             FROM tblLinkCharacterTrait AS lct
             JOIN tblTrait AS t
               ON t.id = lct.idTrait
             WHERE lct.idCharacter = :idCharacter
               AND t.`class` <> 'all'"
        );
        $deleteTraitLinksStmt->execute(['idCharacter' => $id]);
    }

    $pdo->commit();

    // 6. Zelfde gedrag als vroeger: aantal gewijzigde rijen teruggeven
    echo $stmt->rowCount();

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet bijwerken.'
        // eventueel voor debug:
        // 'details' => $e->getMessage()
    ]);
}
