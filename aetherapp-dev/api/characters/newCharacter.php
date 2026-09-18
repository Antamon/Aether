<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterSchemas.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $postData = aetherValidateInput($requestData, aetherCharacterRequestSchema('newCharacter', $requestData));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    $creatorId = (int) $currentUser['id'];
    $postData = array_merge([
        'idUser' => 0,
        'birthPlace' => '',
        'nationality' => '',
        'stateRegisterNumber' => '',
        'street' => '',
        'houseNumber' => '',
        'municipality' => '',
        'postalCode' => '',
        'title' => '',
        'maritalStatus' => '',
        'experienceToTrait' => 0,
        'physicalHealth' => 0,
        'mentalHealth' => 0,
        'physicalHealthFree' => 0,
        'mentalHealthFree' => 0,
    ], $postData);

    $postData['createdBy'] = $creatorId;
    $postData['createdAt'] = date('Y-m-d H:i:s');
    $postData['state'] = 'draft';

    if (!aetherIsPrivilegedRole($currentUser['role'])) {
        $postData['idUser'] = $creatorId;
        $postData['type'] = 'player';
        $postData['physicalHealthFree'] = 0;
        $postData['mentalHealthFree'] = 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tblCharacter
        (idUser, createdAt, createdBy, type, state, firstName, lastName, `class`, birthDate,
         birthPlace, nationality, stateRegisterNumber, street, houseNumber, municipality,
         postalCode, title, maritalStatus, experienceToTrait, physicalHealth, mentalHealth,
         physicalHealthFree, mentalHealthFree)
        VALUES
        (:idUser, :createdAt, :createdBy, :type, :state, :firstName, :lastName, :class, :birthDate,
         :birthPlace, :nationality, :stateRegisterNumber, :street, :houseNumber, :municipality,
         :postalCode, :title, :maritalStatus, :experienceToTrait, :physicalHealth, :mentalHealth,
         :physicalHealthFree, :mentalHealthFree)'
    );
    $stmt->execute($postData);

    aetherJsonResponse((int) $pdo->lastInsertId());
} catch (Throwable $e) {
    aetherJsonError(500, 'Kon character niet aanmaken.');
}
