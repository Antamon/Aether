<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/characterLanguageUtils.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
$idCharacter = isset($input['idCharacter']) ? (int) $input['idCharacter'] : 0;
$idLanguage = isset($input['idLanguage']) ? (int) $input['idLanguage'] : 0;
$name = trim((string) ($input['name'] ?? ''));

if ($idCharacter <= 0 || ($idLanguage <= 0 && $name === '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige parameters.']);
    exit;
}

try {
    $pdo = getPDO();

    if (!characterLanguageSchemaReady($pdo)) {
        http_response_code(500);
        echo json_encode(['error' => 'De taalmodule is nog niet geactiveerd in de databank.']);
        exit;
    }

    $character = dbOne(
        $pdo,
        'SELECT id, idUser, type, `class`, experienceToTrait, physicalHealth, mentalHealth
           FROM tblCharacter
          WHERE id = :id',
        ['id' => $idCharacter]
    );

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
    $currentUserRole = getCurrentUserRole($pdo);
    if (!canCurrentUserManageCharacterLanguages($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen rechten om talen te beheren.']);
        exit;
    }

    if (!canCharacterUseWrittenLanguages($pdo, $character)) {
        http_response_code(400);
        echo json_encode(['error' => 'Dit personage kan geen extra schrijftalen kiezen.']);
        exit;
    }

    $pointSummary = getCharacterPointSummary($pdo, $character);
    $freeSlots = getCharacterFreeLanguageSlotCount($pdo, $character);
    $currentLanguageCount = count(getCharacterLanguages($pdo, $idCharacter));
    $requiresExperience = $currentLanguageCount >= $freeSlots;

    if ((string) ($character['type'] ?? '') === 'player' && $requiresExperience && (int) ($pointSummary['remainingExperience'] ?? 0) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Onvoldoende ervaringspunten om nog een extra taal te kiezen.']);
        exit;
    }

    if ($idLanguage > 0) {
        $language = dbOne(
            $pdo,
            'SELECT id, name
               FROM tblLanguage
              WHERE id = :id',
            ['id' => $idLanguage]
        );
        if (!$language) {
            http_response_code(404);
            echo json_encode(['error' => 'Taal niet gevonden.']);
            exit;
        }
    } else {
        $language = dbOne(
            $pdo,
            'SELECT id, name
               FROM tblLanguage
              WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
              LIMIT 1',
            ['name' => $name]
        );

        if ($language) {
            http_response_code(400);
            echo json_encode(['error' => 'Deze taal bestaat al. Kies ze uit de bestaande lijst.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tblLanguage (name, createdAt, createdBy)
             VALUES (:name, NOW(), :createdBy)'
        );
        $stmt->execute([
            'name' => $name,
            'createdBy' => $currentUserId > 0 ? $currentUserId : null,
        ]);

        $language = [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
        ];
    }

    $alreadyLinked = dbOne(
        $pdo,
        'SELECT id
           FROM tblCharacterLanguage
          WHERE idCharacter = :idCharacter
            AND idLanguage = :idLanguage',
        [
            'idCharacter' => $idCharacter,
            'idLanguage' => (int) ($language['id'] ?? 0),
        ]
    );

    if ($alreadyLinked) {
        http_response_code(400);
        echo json_encode(['error' => 'Deze taal is al gekozen voor dit personage.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacterLanguage (idCharacter, idLanguage, createdAt, createdBy)
         VALUES (:idCharacter, :idLanguage, NOW(), :createdBy)'
    );
    $stmt->execute([
        'idCharacter' => $idCharacter,
        'idLanguage' => (int) ($language['id'] ?? 0),
        'createdBy' => $currentUserId > 0 ? $currentUserId : null,
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon taal niet toevoegen.']);
}
