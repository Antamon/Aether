<?php
declare(strict_types=1);
require_once __DIR__ . '/userRepository.php';

function aetherUserList(PDO $pdo): array
{
    return array_map(static function (array $row): array {
        $fullName = trim(trim((string) ($row['firstName'] ?? '')) . ' ' . trim((string) ($row['lastName'] ?? '')));
        $row['displayName'] = $fullName !== '' ? $fullName : trim((string) ($row['username'] ?? ''));
        return $row;
    }, aetherUserListRows($pdo));
}
