<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/../auth/accessControl.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
$role = $currentUser['role'];

try {
    if ($role === 'administrator' || $role === 'director') {
        // Alle personages (ook inactive/extra)
        $sql = "
            SELECT id, firstName, lastName, title, class
            FROM tblCharacter
            ORDER BY firstName, lastName
        ";
        $params = [];
    } else {
        // Participants: enkel actieve player characters
        $sql = "
            SELECT id, firstName, lastName, title, class
            FROM tblCharacter
            WHERE type = 'player' AND state = 'active'
            ORDER BY firstName, lastName
        ";
        $params = [];
    }

    $rows = dbAll($pdo, $sql, $params);

    $options = [];
    foreach ($rows as $row) {
        $display = '';
        if ($row['class'] === 'upper class' && !empty($row['title'])) {
            $display = trim($row['title'] . ' ' . $row['firstName'] . ' ' . $row['lastName']);
        } else {
            $display = trim($row['firstName'] . ' ' . $row['lastName']);
        }
        $options[] = [
            'id' => (int)$row['id'],
            'displayName' => $display,
            'class' => (string) ($row['class'] ?? ''),
            'recurringIncomeTotal' => getCharacterRecurringIncomeTotal($pdo, [
                'id' => (int) $row['id'],
                'class' => (string) ($row['class'] ?? ''),
            ]),
            'canBeLandlord' => canCharacterBeLandlord($pdo, [
                'id' => (int) $row['id'],
                'class' => (string) ($row['class'] ?? ''),
            ]),
        ];
    }

    echo json_encode($options);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon character lijst voor ties niet ophalen.']);
}
