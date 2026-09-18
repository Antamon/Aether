<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterLanguageUtils.php';
require_once __DIR__ . '/characterSchemas.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('getCharacterLanguageOptions', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

$idCharacter = $input['idCharacter'];

try {
    if (!characterLanguageSchemaReady($pdo)) {
        aetherJsonResponse(['options' => []]);
    }

    $character = aetherFetchCharacterAccessRecord($pdo, $idCharacter);
    if ($character === null) {
        aetherJsonError(404, 'Personage niet gevonden.');
    }
    if (!canCurrentUserManageCharacterLanguages($character, $currentUser['role'], (int) $currentUser['id'])) {
        aetherJsonError(403, 'Geen rechten om talen te beheren.');
    }
    if (!canCharacterUseWrittenLanguages($pdo, $character)) {
        aetherJsonResponse(['options' => []]);
    }

    $options = dbAll(
        $pdo,
        'SELECT l.id, l.name
           FROM tblLanguage AS l
          WHERE NOT EXISTS (
                SELECT 1
                  FROM tblCharacterLanguage AS cl
                 WHERE cl.idCharacter = :idCharacter
                   AND cl.idLanguage = l.id
           )
       ORDER BY LOWER(l.name), l.name, l.id',
        ['idCharacter' => $idCharacter]
    );

    aetherJsonResponse([
        'options' => array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
            ],
            $options
        ),
    ]);
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon taalopties niet ophalen.');
}
