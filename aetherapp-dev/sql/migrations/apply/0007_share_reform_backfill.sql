-- Fase 2 / 0007: gecontroleerde omzetting op een GEISOLEERDE TESTKOPIE.
-- Eerst 0006, verse backup, oude schrijfprocessen uit en preflight/0007.
-- Niet in een live omgeving met actieve oude procentuele share-endpoints uitvoeren.
-- Bij een SQL-fout voor COMMIT: ROLLBACK in dezelfde sessie en kopie herstellen.

-- Geen wijziging voordat de exacte huidige inventaris is bevestigd.
SET @aether_0007_ready := (
  SELECT COUNT(*) = 1 FROM tblSchemaMigration
  WHERE migrationCode = '0006_share_reform_schema'
);
SET @aether_0007_applied := (
  SELECT COUNT(*) FROM tblSchemaMigration
  WHERE migrationCode = '0007_share_reform_backfill'
);
SET @aether_0007_orphan_ids := (
  SELECT GROUP_CONCAT(x.id ORDER BY x.id SEPARATOR ',') FROM (
    SELECT lct.id
    FROM tblLinkCharacterTrait AS lct
    JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
    LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
    GROUP BY lct.id
    HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
  ) AS x
);
SET @aether_0007_company_count := (SELECT COUNT(*) FROM tblCompany);
SET @aether_0007_linked_count := (
  SELECT COUNT(*) FROM tblLinkCharacterTraitCompany AS lctc
  JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
  JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
  WHERE lctc.idCompany IS NOT NULL
);
SET @aether_0007_empty := (
  (SELECT COUNT(*) FROM tblCompanyShareHolding)
  + (SELECT COUNT(*) FROM tblLegacyShareMigrationSource)
  + (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive)
);
SET @aether_0007_unset := (
  SELECT COUNT(*) FROM tblCompany
  WHERE initialCompanyValue IS NULL AND initialShareCount IS NULL AND issuedShareCount IS NULL
);
SET @aether_0007_invalid := (
  SELECT COUNT(*) FROM tblLinkCharacterTraitCompany AS lctc
  LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
  LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
  LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
  LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
  WHERE lctc.idCompany IS NOT NULL
    AND (lct.id IS NULL OR c.id IS NULL OR co.id IS NULL OR tsd.idTrait IS NULL
         OR lct.rankValue + lctc.extraPercentage <= 0
         OR lct.rankValue + lctc.extraPercentage > 100)
);
SET @aether_0007_overallocated := (
  SELECT COUNT(*) FROM (
    SELECT lctc.idCompany
    FROM tblLinkCharacterTraitCompany AS lctc
    JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
    JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
    WHERE lctc.idCompany IS NOT NULL
    GROUP BY lctc.idCompany
    HAVING SUM(lct.rankValue + lctc.extraPercentage) > 100
  ) AS x
);
SET @aether_0007_guard_sql := IF(
  @aether_0007_ready = 1 AND @aether_0007_applied = 0
  AND @aether_0007_company_count = 13 AND @aether_0007_linked_count = 10
  AND @aether_0007_empty = 0 AND @aether_0007_unset = 13
  AND @aether_0007_invalid = 0 AND @aether_0007_overallocated = 0
  AND @aether_0007_orphan_ids = '11,95,235,236,246,247,248,266,336,429,430,453',
  'SELECT ''0007 preflight passed'' AS result',
  'SELECT * FROM __AETHER_BLOCK_0007_SOURCE_CHANGED_OR_NOT_READY__'
);
PREPARE aether_0007_guard FROM @aether_0007_guard_sql;
EXECUTE aether_0007_guard;
DEALLOCATE PREPARE aether_0007_guard;

START TRANSACTION;

-- Herleidbare kopie van alle gekoppelde oude A/B-bronrijen.
INSERT INTO tblLegacyShareMigrationSource
  (idLegacyCompanyLink, idLegacyTraitLink, idCharacter, idTrait, shareClass,
   idCompany, rankValue, extraPercentage, migratedShareCount,
   legacyTraitCreatedAt, legacyTraitUpdatedAt,
   legacyCompanyLinkCreatedAt, legacyCompanyLinkUpdatedAt)
SELECT lctc.id, lct.id, lct.idCharacter, lct.idTrait, tsd.shareClass,
       lctc.idCompany, lct.rankValue, lctc.extraPercentage,
       lct.rankValue + lctc.extraPercentage,
       lct.createdAt, lct.updatedAt, lctc.createdAt, lctc.updatedAt
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL;

-- Bewaar de exact gerapporteerde losse rijen voordat zij verwijderd worden.
INSERT INTO tblLegacyShareOrphanArchive
  (idLegacyTraitLink, idLegacyCompanyLink, idCharacter, idTrait, rankValue, extraPercentage,
   legacyTraitCreatedAt, legacyTraitUpdatedAt,
   legacyCompanyLinkCreatedAt, legacyCompanyLinkUpdatedAt)
SELECT lct.id, lctc.id, lct.idCharacter, lct.idTrait,
       lct.rankValue, lctc.extraPercentage,
       lct.createdAt, lct.updatedAt, lctc.createdAt, lctc.updatedAt
FROM tblLinkCharacterTrait AS lct
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
WHERE lct.id IN (11,95,235,236,246,247,248,266,336,429,430,453);

UPDATE tblCompany
SET initialCompanyValue = companyValue,
    initialShareCount = 100,
    issuedShareCount = 100;

INSERT INTO tblCompanyAnonymousShares (idCompany, shareCount)
SELECT id, 0 FROM tblCompany;

INSERT INTO tblCompanyShareHolding (idCompany, idCharacter, shareCount)
SELECT idCompany, idCharacter, SUM(migratedShareCount)
FROM tblLegacyShareMigrationSource
GROUP BY idCompany, idCharacter;

INSERT INTO tblCharacterShareStatus
  (idCharacter, intrinsicPortfolioValue)
SELECT h.idCharacter,
       ROUND(SUM(co.companyValue * h.shareCount / co.issuedShareCount), 2)
FROM tblCompanyShareHolding AS h
JOIN tblCompany AS co ON co.id = h.idCompany
GROUP BY h.idCharacter;

DELETE lctc FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLegacyShareOrphanArchive AS a ON a.idLegacyCompanyLink = lctc.id;
DELETE lct FROM tblLinkCharacterTrait AS lct
JOIN tblLegacyShareOrphanArchive AS a ON a.idLegacyTraitLink = lct.id;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0007_share_reform_backfill', 'Backfill 100 issued shares, ownership and archived orphan traits');

COMMIT;
