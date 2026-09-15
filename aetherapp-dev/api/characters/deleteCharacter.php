<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

function tableExists(PDO $pdo, string $tableName): bool
{
    $row = dbOne(
        $pdo,
        'SELECT 1
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
          LIMIT 1',
        ['table' => $tableName]
    );

    return $row !== null;
}

function deleteCompanyPersonnelLinks(PDO $pdo, int $idCharacter): void
{
    if (!tableExists($pdo, 'tblCompanyPersonnel')) {
        return;
    }

    if (tableExists($pdo, 'tblCompanyPersonnelSkillSpecialisation') && tableExists($pdo, 'tblCompanyPersonnelSkill')) {
        $stmt = $pdo->prepare(
            'DELETE cpss
               FROM tblCompanyPersonnelSkillSpecialisation cpss
               INNER JOIN tblCompanyPersonnelSkill cps
                       ON cps.id = cpss.idCompanyPersonnelSkill
               INNER JOIN tblCompanyPersonnel cp
                       ON cp.id = cps.idCompanyPersonnel
              WHERE cp.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $idCharacter]);
    }

    if (tableExists($pdo, 'tblCompanyPersonnelSkill')) {
        $stmt = $pdo->prepare(
            'DELETE cps
               FROM tblCompanyPersonnelSkill cps
               INNER JOIN tblCompanyPersonnel cp
                       ON cp.id = cps.idCompanyPersonnel
              WHERE cp.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $idCharacter]);
    }

    $stmt = $pdo->prepare('DELETE FROM tblCompanyPersonnel WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $idCharacter]);
}

function deleteCharacterShareLinks(PDO $pdo, int $idCharacter): void
{
    if (!tableExists($pdo, 'tblLinkCharacterTrait') || !tableExists($pdo, 'tblLinkCharacterTraitCompany')) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE lctc
           FROM tblLinkCharacterTraitCompany lctc
           INNER JOIN tblLinkCharacterTrait lct
                   ON lct.id = lctc.idLinkCharacterTrait
          WHERE lct.idCharacter = :idCharacter'
    );
    $stmt->execute(['idCharacter' => $idCharacter]);
}

function deleteCharacterLanguageLinks(PDO $pdo, int $idCharacter): void
{
    if (!tableExists($pdo, 'tblCharacterLanguage')) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM tblCharacterLanguage WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $idCharacter]);
}

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd.']);
    exit;
}

$idCharacter = isset($postData['id']) ? (int) $postData['id'] : 0;
if ($idCharacter <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Ongeldig personage.']);
    exit;
}

$sessionUserId = (int) $_SESSION['user']['id'];
$stmtUser = $pdo->prepare('SELECT role FROM tblUser WHERE id = :id');
$stmtUser->execute(['id' => $sessionUserId]);
$userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
$sessionRole = (string) ($userRow['role'] ?? ($_SESSION['user']['role'] ?? 'participant'));
$isAdmin = $sessionRole === 'administrator' || $sessionRole === 'director';

try {
    $character = dbOne(
        $pdo,
        'SELECT id, idUser, firstName, lastName
           FROM tblCharacter
          WHERE id = :id',
        ['id' => $idCharacter]
    );

    if ($character === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    $isOwnerPlayer = $sessionRole === 'participant'
        && $character['type'] === 'player'
        && (int) $character['idUser'] === $sessionUserId;

    if (!$isAdmin && !$isOwnerPlayer) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen toestemming om dit personage te verwijderen.']);
        exit;
    }

    $pdo->beginTransaction();

    if (tableExists($pdo, 'tblCompanySnapshotPayout')) {
        $stmt = $pdo->prepare('DELETE FROM tblCompanySnapshotPayout WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $idCharacter]);
    }

    if (tableExists($pdo, 'tblCharacterBankTransaction')) {
        $stmt = $pdo->prepare(
            'DELETE FROM tblCharacterBankTransaction
              WHERE idSourceCharacter = :idSourceCharacter
                 OR idTargetCharacter = :idTargetCharacter'
        );
        $stmt->execute([
            'idSourceCharacter' => $idCharacter,
            'idTargetCharacter' => $idCharacter
        ]);
    }

    deleteCompanyPersonnelLinks($pdo, $idCharacter);
    deleteCharacterShareLinks($pdo, $idCharacter);
    deleteCharacterLanguageLinks($pdo, $idCharacter);

    $stmt = $pdo->prepare('DELETE FROM tblCharacter WHERE id = :idCharacter');
    $stmt->execute(['idCharacter' => $idCharacter]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Het personage kon niet verwijderd worden.');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $idCharacter,
        'name' => trim((string) ($character['firstName'] ?? '') . ' ' . (string) ($character['lastName'] ?? ''))
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Het verwijderen van het personage is mislukt.',
        'details' => $e->getMessage()
    ]);
}
