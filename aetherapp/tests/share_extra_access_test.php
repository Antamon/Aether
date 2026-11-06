<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/shares/shareAccess.php';
$owner = ['id' => 94, 'role' => 'participant'];
$extra = ['id' => 14, 'idUser' => 94, 'type' => 'extra', 'state' => 'active'];
foreach ([
    [aetherCanTradeForCharacter($owner, $extra), true],
    [aetherCanTradeForCharacter(['id' => 1, 'role' => 'participant'], $extra), false],
    [aetherCanTradeForCharacter(['id' => 1, 'role' => 'director'], $extra), true],
    [aetherCanEditCharacter($owner, $extra), false],
] as [$actual, $expected]) {
    if ($actual !== $expected) throw new RuntimeException('Incorrect extra-character share permission.');
}
echo "Owned extra share access, unrelated users, director access and character edit restrictions passed.\n";
