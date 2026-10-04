<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/shares/shareService.php';

function shareAccessCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function shareAccessReject(int $expectedStatus, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (AetherShareException $e) {
        shareAccessCheck($e->getHttpStatus() === $expectedStatus, $message . ': verkeerde status');
        return;
    }
    throw new RuntimeException($message . ': toegang ten onrechte toegestaan');
}

$owner = ['id' => 7, 'role' => 'participant'];
$other = ['id' => 8, 'role' => 'participant'];
$director = ['id' => 9, 'role' => 'director'];
$admin = ['id' => 10, 'role' => 'administrator'];
$player = ['id' => 21, 'idUser' => 7, 'type' => 'player', 'state' => 'active'];
$extra = ['id' => 22, 'idUser' => 7, 'type' => 'extra', 'state' => 'active'];
$draft = ['id' => 23, 'idUser' => 7, 'type' => 'player', 'state' => 'draft'];

shareAccessCheck(aetherCanTradeForCharacter($owner, $player), 'Eigen actief personage');
shareAccessCheck(!aetherCanTradeForCharacter($other, $player), 'Andermans personage');
shareAccessCheck(!aetherCanTradeForCharacter($owner, $extra), 'Extra kan niet handelen');
shareAccessCheck(aetherCanTradeForCharacter($director, $player), 'Director mag handelen');
shareAccessCheck(aetherCanTradeForCharacter($admin, $player), 'Administrator mag handelen');
shareAccessCheck(!aetherCanManageShareCompany($owner), 'Participant mag geen bedrijfsparameters wijzigen');
shareAccessCheck(aetherCanManageShareCompany($director), 'Director mag bedrijfsparameters wijzigen');
shareAccessCheck(aetherCanManageShareCompany($admin), 'Administrator mag bedrijfsparameters wijzigen');
shareAccessReject(403, static fn() => aetherShareRequireManager($owner), 'Beheerrecht participant');
shareAccessReject(403, static fn() => aetherShareRequireCharacter([21 => $player], 21, $other), 'Eigendom');
shareAccessReject(404, static fn() => aetherShareRequireCharacter([], 21, $owner), 'Ontbrekend personage');
shareAccessReject(409, static fn() => aetherShareRequireCharacter([23 => $draft], 23, $owner), 'Draft mag niet handelen');
shareAccessCheck(aetherShareRequireCharacter([21 => $player], 21, $owner) === $player, 'Eigen actieve handel');

$company = ['id' => 4, 'initialShareCount' => 100, 'issuedShareCount' => 100];
shareAccessCheck(aetherShareRequireCompany([4 => $company], 4) === $company, 'Geinitialiseerd bedrijf');
shareAccessReject(409, static fn() => aetherShareRequireCompany([4 => ['initialShareCount' => null,
    'issuedShareCount' => 0]], 4), 'Ongenitialiseerd bedrijf');

shareAccessCheck(aetherIdempotencyPayloadHash(['shareCount' => 1, 'idCompany' => 4])
    === aetherIdempotencyPayloadHash(['idCompany' => 4, 'shareCount' => 1]),
    'Veldvolgorde verandert de idempotentiesleutel niet');
shareAccessCheck(aetherIdempotencyPayloadHash(['shareCount' => 1, 'idCompany' => 4])
    !== aetherIdempotencyPayloadHash(['shareCount' => 2, 'idCompany' => 4]),
    'Gewijzigd aantal geeft andere payloadhash');

echo "Share access and idempotency rules passed.\n";
