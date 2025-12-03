<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';

try {
    // Eenvoudige read-only query via PDO
    $users = dbAll(
        $pdo,
        'SELECT id, username, firstName, lastName, role
           FROM tblUser
       ORDER BY firstName ASC, lastName ASC'
    );

    echo json_encode($users);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error while loading user list.']);
}
