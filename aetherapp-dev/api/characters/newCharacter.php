<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterRequestValidation.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

$postData = aetherReadCharacterJsonRequest('newCharacter');

try {
    // 2. Bepaal creator
    $creatorId = (int) $currentUser['id'];

    // 3. Standaardwaarden en uitsluitend vaste databasevelden afdwingen.
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

    // Audit- en autoriteitsvelden worden uitsluitend server-side bepaald.
    $postData['createdBy'] = $creatorId;

    // createdAt: huidige timestamp als er niets wordt meegestuurd
    $postData['createdAt'] = date('Y-m-d H:i:s');

    // state: bij creatie altijd 'draft', tenzij je bewust een andere state toelaat
    $postData['state'] = 'draft';

    if (!aetherIsPrivilegedRole($currentUser['role'])) {
        $postData['idUser'] = $creatorId;
        $postData['type'] = 'player';
        $postData['physicalHealthFree'] = 0;
        $postData['mentalHealthFree'] = 0;
    }

    $sql = 'INSERT INTO tblCharacter
        (idUser, createdAt, createdBy, type, state, firstName, lastName, `class`, birthDate,
         birthPlace, nationality, stateRegisterNumber, street, houseNumber, municipality,
         postalCode, title, maritalStatus, experienceToTrait, physicalHealth, mentalHealth,
         physicalHealthFree, mentalHealthFree)
        VALUES
        (:idUser, :createdAt, :createdBy, :type, :state, :firstName, :lastName, :class, :birthDate,
         :birthPlace, :nationality, :stateRegisterNumber, :street, :houseNumber, :municipality,
         :postalCode, :title, :maritalStatus, :experienceToTrait, :physicalHealth, :mentalHealth,
         :physicalHealthFree, :mentalHealthFree)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($postData);

    // Zelfde gedrag als vroeger: enkel het nieuwe ID teruggeven
    echo $pdo->lastInsertId();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet aanmaken.',
        // 'details' => $e->getMessage(), // eventueel tijdelijk aanzetten voor debugging
    ]);
}
