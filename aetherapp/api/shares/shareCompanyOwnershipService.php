<?php
declare(strict_types=1);

/** Administrative assignment from free stock; no money or company valuation moves. */
function aetherShareAddCompanyHolding(PDO $pdo, array $user, array $input, string $key): array
{
    aetherShareRequireManager($user);
    if (!aetherCompanyShareOwnershipAvailable($pdo)) {
        throw new AetherShareException(503, 'Voer eerst migratie 0023_company_share_ownership uit.');
    }
    $issuerId = (int) $input['idCompany'];
    $ownerId = (int) $input['idHoldingCompany'];
    $quantity = (int) $input['shareCount'];
    if ($issuerId === $ownerId) throw new AetherShareException(422, 'Een bedrijf kan hier geen eigen aandelen bezitten.');
    if ($quantity < 1 || $quantity > 1000000) throw new AetherShareException(422, 'Kies een geldig aantal aandelen.');
    return aetherShareMutation($pdo, $user, 'company_holding_add', $key, $input,
        static function (array $companies) use ($pdo, $user, $issuerId, $ownerId, $quantity, $key): array {
            aetherShareRequireManager($user);
            $issuer = aetherShareRequireCompany($companies, $issuerId);
            $owner = $companies[$ownerId] ?? null;
            if (!$owner) throw new AetherShareException(404, 'Moederbedrijf niet gevonden.');
            $stock = aetherShareStock($pdo, $issuerId, (int) $issuer['issuedShareCount']);
            if ($quantity > $stock['free']) throw new AetherShareException(409, 'Onvoldoende vrije aandelen.');
            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels'));
            $gameDate = $now->modify('-100 years');
            foreach ([$issuer, $owner] as $company) {
                if (!empty($company['foundationDate']) && substr((string) $company['foundationDate'], 0, 10) > $gameDate->format('Y-m-d')) {
                    throw new AetherShareException(409, 'Het bedrijf is op de huidige speldatum nog niet opgericht.');
                }
            }
            $stmt = $pdo->prepare('INSERT INTO tblCompanyShareCompanyHolding (idCompany, idHoldingCompany, shareCount)
                VALUES (:issuer, :owner, :quantity) ON DUPLICATE KEY UPDATE shareCount = shareCount + VALUES(shareCount)');
            $stmt->execute(['issuer' => $issuerId, 'owner' => $ownerId, 'quantity' => $quantity]);
            $stmt = $pdo->prepare('INSERT INTO tblCompanyShareCompanyTransaction
                (idCompany, idHoldingCompany, actorUserId, shareCount, purchaseYear, purchaseQuarter,
                 issuerValueBefore, issuerValueAfter, holderValueBefore, holderValueAfter, requestKey)
                VALUES (:issuer, :owner, :actor, :quantity, :year, :quarter, :issuerBefore, :issuerAfter, :ownerBefore, :ownerAfter, :requestKey)');
            $stmt->execute(['issuer' => $issuerId, 'owner' => $ownerId, 'actor' => (int) $user['id'], 'quantity' => $quantity,
                'year' => (int) $gameDate->format('Y'), 'quarter' => (int) ceil((int) $gameDate->format('n') / 3),
                'issuerBefore' => $issuer['companyValue'], 'issuerAfter' => $issuer['companyValue'],
                'ownerBefore' => $owner['companyValue'], 'ownerAfter' => $owner['companyValue'], 'requestKey' => $key]);
            return ['success' => true, 'idTransaction' => (int) $pdo->lastInsertId(), 'shareCount' => $quantity];
        }, static function (array $companies) use ($user, $issuerId, $ownerId): void {
            aetherShareRequireManager($user);
            aetherShareRequireCompany($companies, $issuerId);
            if (!isset($companies[$ownerId])) throw new AetherShareException(404, 'Moederbedrijf niet gevonden.');
        });
}
