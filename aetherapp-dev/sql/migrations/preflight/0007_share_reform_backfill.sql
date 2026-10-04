-- Read-only; run on isolated test copy after 0006 and immediately before 0007.
SELECT DATABASE() AS selectedDatabase;
SELECT migrationCode FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill');
SELECT COUNT(*) AS companyCount,
       SUM(initialCompanyValue IS NULL AND initialShareCount IS NULL AND issuedShareCount IS NULL)
         AS notYetConverted
FROM tblCompany;
SELECT (SELECT COUNT(*) FROM tblCompanyShareHolding) AS existingHoldings,
       (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) AS existingSourceArchive,
       (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) AS existingOrphanArchive;
SELECT lct.id AS orphanTraitLinkId, lct.idCharacter, lct.idTrait, lct.rankValue,
       lctc.id AS oldCompanyLinkId, lctc.idCompany
FROM tblLinkCharacterTrait AS lct
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
WHERE lct.id IN (11,95,235,236,246,247,248,266,336,429,430,453)
ORDER BY lct.id;
SELECT lctc.idCompany, COUNT(*) AS linkedRows,
       SUM(lct.rankValue + lctc.extraPercentage) AS soldShares
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
GROUP BY lctc.idCompany ORDER BY lctc.idCompany;
-- Verwacht voor de gecontroleerde kopie: 13/13 bedrijven, 0 bestaande rijen,
-- 12 bovenstaande orphan IDs, 10 gekoppelde rijen en geen bedrijf boven 100.
