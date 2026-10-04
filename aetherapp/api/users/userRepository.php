<?php
declare(strict_types=1);

function aetherUserListRows(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, username, firstName, lastName, role FROM tblUser ORDER BY firstName ASC, lastName ASC');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
