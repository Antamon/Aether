-- Beperkte terugweg voor 0007 op DEZELFDE GEISOLEERDE TESTKOPIE,
-- uitsluitend voordat nieuwe aandeel-, aanbod-, zoek- of waardemutaties starten.
-- Maak ook hiervan eerst een volledige backup. DDL van 0006 blijft bestaan.
-- Bij een fout voor COMMIT: ROLLBACK in dezelfde sessie.

SET @aether_0007_rollback_ok := (
  (SELECT COUNT(*) FROM tblSchemaMigration WHERE migrationCode = '0007_share_reform_backfill') = 1
  AND (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) = 12
  AND (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) = 10
  AND (SELECT COUNT(*) FROM tblCompanyShareHolding) = 10
  AND (SELECT COUNT(*) FROM tblCompanyAnonymousShares WHERE shareCount = 0) = 13
  AND (SELECT COUNT(*) FROM tblCharacterShareStatus
       WHERE shareLevel IS NULL AND usedStatusPoints IS NULL
         AND personnelRequirement IS NULL) = 9
  AND (SELECT COUNT(*) FROM tblCharacterShareStatus) = 9
  AND (SELECT COUNT(*) FROM tblCompanyShareTransaction) = 0
  AND (SELECT COUNT(*) FROM tblCompanyValueHistory) = 0
  AND (SELECT COUNT(*) FROM tblShareOffer) = 0
  AND (SELECT COUNT(*) FROM tblShareSearch) = 0
  AND (SELECT COUNT(*) FROM tblCharacterShareDraftBudget) = 0
  AND (SELECT COUNT(*) FROM tblShareDomainCode) = 0
  AND (SELECT COUNT(*) FROM tblCompany WHERE initialCompanyValue = companyValue
        AND initialShareCount = 100 AND issuedShareCount = 100 AND isShareListed = 0) = 13
  AND (SELECT COUNT(*) FROM tblCompanySnapshot WHERE shareMarketPrice IS NOT NULL) = 0
  AND (SELECT COUNT(*) FROM tblCompanyPersonnel WHERE isBoardMember <> 0) = 0
  AND (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive AS a
       JOIN tblLinkCharacterTrait AS lct ON lct.id = a.idLegacyTraitLink) = 0
);
SET @aether_0007_rollback_guard_sql := IF(
  @aether_0007_rollback_ok,
  'SELECT ''0007 rollback preflight passed'' AS result',
  'SELECT * FROM __AETHER_BLOCK_0007_ROLLBACK_HAS_SUBSEQUENT_CHANGES__'
);
PREPARE aether_0007_rollback_guard FROM @aether_0007_rollback_guard_sql;
EXECUTE aether_0007_rollback_guard;
DEALLOCATE PREPARE aether_0007_rollback_guard;

START TRANSACTION;

INSERT INTO tblLinkCharacterTrait
  (id, idCharacter, idTrait, rankValue, createdAt, updatedAt)
SELECT idLegacyTraitLink, idCharacter, idTrait, rankValue,
       legacyTraitCreatedAt, legacyTraitUpdatedAt
FROM tblLegacyShareOrphanArchive;

INSERT INTO tblLinkCharacterTraitCompany
  (id, idLinkCharacterTrait, idCompany, extraPercentage, createdAt, updatedAt)
SELECT idLegacyCompanyLink, idLegacyTraitLink, NULL, extraPercentage,
       legacyCompanyLinkCreatedAt, legacyCompanyLinkUpdatedAt
FROM tblLegacyShareOrphanArchive
WHERE idLegacyCompanyLink IS NOT NULL;

DELETE FROM tblCharacterShareStatus;
DELETE FROM tblCompanyShareHolding;
DELETE FROM tblCompanyAnonymousShares;
DELETE FROM tblLegacyShareMigrationSource;
DELETE FROM tblLegacyShareOrphanArchive;

UPDATE tblCompany
SET initialCompanyValue = NULL, initialShareCount = NULL, issuedShareCount = NULL;

DELETE FROM tblSchemaMigration WHERE migrationCode = '0007_share_reform_backfill';

COMMIT;
