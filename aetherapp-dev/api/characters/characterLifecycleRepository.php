<?php
declare(strict_types=1);

/** @return array<string, mixed>|null */
function aetherFetchCharacterForDeletion(PDO $pdo, int $characterId, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT id, idUser, type, state, firstName, lastName FROM tblCharacter WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute(['id' => $characterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aetherCharacterLifecycleTableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
          LIMIT 1'
    );
    $stmt->execute(['table' => $tableName]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function aetherDeleteCharacterCompanyPersonnel(PDO $pdo, int $characterId): void
{
    if (!aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnel')) return;
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnelSkillSpecialisation')
        && aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnelSkill')) {
        $stmt = $pdo->prepare(
            'DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation cpss
              INNER JOIN tblCompanyPersonnelSkill cps ON cps.id = cpss.idCompanyPersonnelSkill
              INNER JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel
             WHERE cp.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanyPersonnelSkill')) {
        $stmt = $pdo->prepare(
            'DELETE cps FROM tblCompanyPersonnelSkill cps
              INNER JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel
             WHERE cp.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $characterId]);
    }
    $stmt = $pdo->prepare('DELETE FROM tblCompanyPersonnel WHERE idCharacter = :idCharacter');
    $stmt->execute(['idCharacter' => $characterId]);
}

function aetherDeleteCharacterShareLinks(PDO $pdo, int $characterId): void
{
    if (!aetherCharacterLifecycleTableExists($pdo, 'tblLinkCharacterTrait')
        || !aetherCharacterLifecycleTableExists($pdo, 'tblLinkCharacterTraitCompany')) return;
    $stmt = $pdo->prepare(
        'DELETE lctc FROM tblLinkCharacterTraitCompany lctc
          INNER JOIN tblLinkCharacterTrait lct ON lct.id = lctc.idLinkCharacterTrait
         WHERE lct.idCharacter = :idCharacter'
    );
    $stmt->execute(['idCharacter' => $characterId]);
}

function aetherDeleteCharacterManualRelations(PDO $pdo, int $characterId): void
{
    aetherDeleteCharacterShareRelations($pdo, $characterId);
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanySnapshotPayout')) {
        $stmt = $pdo->prepare('DELETE FROM tblCompanySnapshotPayout WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterBankTransaction')) {
        $stmt = $pdo->prepare(
            'DELETE FROM tblCharacterBankTransaction
              WHERE idSourceCharacter = :idSourceCharacter OR idTargetCharacter = :idTargetCharacter'
        );
        $stmt->execute(['idSourceCharacter' => $characterId, 'idTargetCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterSecuritiesTransaction')) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterSecuritiesTransaction WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterEconomySnapshot')) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterEconomySnapshot WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    aetherDeleteCharacterCompanyPersonnel($pdo, $characterId);
    aetherDeleteCharacterShareLinks($pdo, $characterId);
    // Ook expliciet opruimen wanneer een oudere installatie cascade-FK's mist.
    foreach ([
        'tblCharacterDiary' => ['idCharacter'],
        'tblCharacterDiaryVisibility' => ['idCharacter'],
        'tblCharacterSection' => ['idCharacter'],
        'tblCharacterSpecialisation' => ['idCharacter'],
        'tblCharacterTie' => ['idCharacter', 'idCharacterTarget'],
        'tblCharacterEventGossipUnlock' => ['idViewerCharacter', 'idSourceCharacter'],
        'tblCharacterEventGossipAttempt' => ['idViewerCharacter'],
        'tblCharacterSkillActionState' => ['idCharacter'],
        'tblCharacterSkillActionUse' => ['idCharacter'],
        'tblLinkCharacterTrait' => ['idCharacter'],
    ] as $table => $columns) {
        aetherDeleteCharacterRows($pdo, $table, $columns, $characterId);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblLinkCharacterSkill')) {
        $stmt = $pdo->prepare('DELETE FROM tblLinkCharacterSkill WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterLanguage')) {
        $stmt = $pdo->prepare('DELETE FROM tblCharacterLanguage WHERE idCharacter = :idCharacter');
        $stmt->execute(['idCharacter' => $characterId]);
    }

    // Avoid a live character continuing to point at the character that is about to be removed.
    $stmt = $pdo->prepare(
        "UPDATE tblCharacter
            SET securitiesManagerType = 'self', securitiesManagerCharacterId = NULL
          WHERE securitiesManagerCharacterId = :idCharacter"
    );
    $stmt->execute(['idCharacter' => $characterId]);
    if (aetherCharacterLifecycleTableExists($pdo, 'tblCharacterEconomySnapshot')) {
        $stmt = $pdo->prepare(
            "UPDATE tblCharacterEconomySnapshot
                SET securitiesManagerType = 'self', securitiesManagerCharacterId = NULL
              WHERE securitiesManagerCharacterId = :idCharacter"
        );
        $stmt->execute(['idCharacter' => $characterId]);
    }
    aetherClearCharacterCachedResponses($pdo, $characterId);
}

function aetherCachedResponseReferencesCharacter(mixed $data, int $characterId): bool
{
    if (!is_array($data)) return false;
    $characterKeys = ['idCharacter', 'characterId', 'idSourceCharacter', 'idTargetCharacter',
        'idCounterpartyCharacter', 'idOfferingCharacter', 'sellerCharacterId', 'securitiesManagerCharacterId'];
    foreach ($data as $key => $value) {
        if (in_array($key, $characterKeys, true) && is_scalar($value) && (int) $value === $characterId) return true;
        if (aetherCachedResponseReferencesCharacter($value, $characterId)) return true;
    }
    return false;
}

function aetherClearCharacterCachedResponses(PDO $pdo, int $characterId): void
{
    if (!aetherCharacterLifecycleTableExists($pdo, 'tblApiIdempotency')) return;
    $stmt = $pdo->prepare("SELECT id, responseJson FROM tblApiIdempotency WHERE status = 'completed' AND responseJson IS NOT NULL FOR UPDATE");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as $record) {
        if (!aetherCachedResponseReferencesCharacter(json_decode((string) $record['responseJson'], true), $characterId)) continue;
        // Bewaar de aanvraag-ID: verwijderen daarvan kan een financiële actie
        // opnieuw uitvoerbaar maken. Alleen de opgeslagen persoonsgegevens weg.
        $update = $pdo->prepare('UPDATE tblApiIdempotency SET responseJson = :response, responseStatus = 410 WHERE id = :id');
        $update->execute(['response' => '{"error":"Dit personage is verwijderd."}', 'id' => $record['id']]);
    }
}

/** Identifiers komen uitsluitend uit de vaste tabel-/kolomlijst hieronder. */
function aetherDeleteCharacterRows(PDO $pdo, string $table, array $columns, int $characterId): void
{
    if (!aetherCharacterLifecycleTableExists($pdo, $table)) return;
    $conditions = [];
    $params = [];
    foreach ($columns as $index => $column) {
        $conditions[] = "`$column` = :character$index";
        $params["character$index"] = $characterId;
    }
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE " . implode(' OR ', $conditions));
    $stmt->execute($params);
}

function aetherDeleteCharacterShareRelations(PDO $pdo, int $characterId): void
{
    $hasOffers = aetherCharacterLifecycleTableExists($pdo, 'tblShareOffer');
    $hasSearches = aetherCharacterLifecycleTableExists($pdo, 'tblShareSearch')
        && aetherCharacterLifecycleTableExists($pdo, 'tblShareSearchAssessment');
    $hasTransactions = aetherCharacterLifecycleTableExists($pdo, 'tblCompanyShareTransaction');
    // Aanbiedingen uit de zoekacties van dit personage horen eveneens bij diens data.
    $offerCondition = 'o.idOfferingCharacter = :offeringCharacter';
    $offerParams = ['offeringCharacter' => $characterId];
    if ($hasSearches) {
        $offerCondition .= ' OR o.idAssessment IN
            (SELECT a.id FROM tblShareSearchAssessment a
             JOIN tblShareSearch s ON s.id = a.idSearch WHERE s.idCharacter = :searchCharacter)';
        $offerParams['searchCharacter'] = $characterId;
    }
    if ($hasTransactions) {
        // De bedrijfswaardehistoriek behoudt bedragen, zonder persoonsgegevens
        // of verwijzing naar de te verwijderen persoonlijke transactie.
        if (aetherCharacterLifecycleTableExists($pdo, 'tblCompanyValueHistory')) {
            $stmt = $pdo->prepare(
                'UPDATE tblCompanyValueHistory h JOIN tblCompanyShareTransaction t ON t.id = h.idShareTransaction
                 SET h.idShareTransaction = NULL, h.detailsJson = NULL
                 WHERE t.idCharacter = :owner OR t.idCounterpartyCharacter = :counterparty'
            );
            $stmt->execute(['owner' => $characterId, 'counterparty' => $characterId]);
        }
        aetherDeleteCharacterRows($pdo, 'tblCompanyShareTransaction', ['idCharacter', 'idCounterpartyCharacter'], $characterId);
        if ($hasOffers) {
            $stmt = $pdo->prepare(
                "UPDATE tblCompanyShareTransaction t JOIN tblShareOffer o ON o.id = t.idOffer
                 SET t.idOffer = NULL, t.detailsJson = NULL WHERE ($offerCondition)"
            );
            $stmt->execute($offerParams);
        }
    }
    if ($hasOffers) {
        if (aetherCharacterLifecycleTableExists($pdo, 'tblShareOfferRecipient')) {
            $stmt = $pdo->prepare(
                "DELETE r FROM tblShareOfferRecipient r JOIN tblShareOffer o ON o.id = r.idOffer
                 WHERE ($offerCondition) OR r.idCharacter = :recipientCharacter"
            );
            $stmt->execute($offerParams + ['recipientCharacter' => $characterId]);
        }
        $stmt = $pdo->prepare("DELETE o FROM tblShareOffer o WHERE ($offerCondition)");
        $stmt->execute($offerParams);
    }
    if ($hasSearches) {
        $stmt = $pdo->prepare(
            'DELETE a FROM tblShareSearchAssessment a JOIN tblShareSearch s ON s.id = a.idSearch
             WHERE s.idCharacter = :idCharacter'
        );
        $stmt->execute(['idCharacter' => $characterId]);
    }
    foreach (['tblShareOfferRecipient', 'tblShareSearch', 'tblCompanyShareHolding',
        'tblCharacterShareStatus', 'tblCharacterShareDraftBudget',
        'tblLegacyShareMigrationSource', 'tblLegacyShareOrphanArchive'] as $table) {
        aetherDeleteCharacterRows($pdo, $table, ['idCharacter'], $characterId);
    }
}

function aetherDeleteCharacterRecord(PDO $pdo, int $characterId): void
{
    $stmt = $pdo->prepare('DELETE FROM tblCharacter WHERE id = :idCharacter');
    $stmt->execute(['idCharacter' => $characterId]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Het personage kon niet verwijderd worden.');
    }
}
