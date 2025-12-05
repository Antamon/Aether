<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
$idCharacter = isset($input['idCharacter']) ? (int)$input['idCharacter'] : 0;

if ($idCharacter <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'idCharacter ontbreekt.']);
    exit;
}

$tieLabels = [
    'superior' => 'Superior',
    'dependent' => 'Dependent',
    'spouse' => 'Spouse',
    'ally' => 'Ally',
    'adversary' => 'Adversary',
    'person_of_interest' => 'Person of interest',
];

try {
    $sql = "
        SELECT
            t.id,
            t.idCharacterTarget,
            t.relationType,
            t.description,
            c.firstName,
            c.lastName,
            c.title,
            c.class
        FROM tblCharacterTie t
        JOIN tblCharacter c ON c.id = t.idCharacterTarget
        WHERE t.idCharacter = :idCharacter
        ORDER BY c.firstName, c.lastName
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idCharacter' => $idCharacter]);

    $ties = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $displayName = '';
        if ($row['class'] === 'upper class' && !empty($row['title'])) {
            $displayName = trim($row['title'] . ' ' . $row['firstName'] . ' ' . $row['lastName']);
        } else {
            $displayName = trim($row['firstName'] . ' ' . $row['lastName']);
        }

        $ties[] = [
            'id' => (int)$row['id'],
            'idOtherCharacter' => (int)$row['idCharacterTarget'],
            'relationType' => $row['relationType'],
            'relationTypeLabel' => $tieLabels[$row['relationType']] ?? $row['relationType'],
            'description' => $row['description'],
            'otherName' => $displayName,
        ];
    }

    echo json_encode($ties);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon ties niet ophalen.']);
}
