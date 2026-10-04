-- Read-only; alle afwijkingsqueries moeten nul rijen teruggeven.
SELECT migrationCode, appliedAt FROM tblSchemaMigration
WHERE migrationCode = '0007_share_reform_backfill';
SELECT COUNT(*) AS companies, MIN(initialShareCount) AS minInitialShares,
       MAX(initialShareCount) AS maxInitialShares,
       MIN(issuedShareCount) AS minIssuedShares,
       MAX(issuedShareCount) AS maxIssuedShares,
       SUM(initialCompanyValue <> companyValue) AS changedSinceFoundingCopy
FROM tblCompany;
SELECT COUNT(*) AS linkedSourceRows FROM tblLegacyShareMigrationSource;
SELECT COUNT(*) AS archivedOrphanRows FROM tblLegacyShareOrphanArchive;
SELECT COUNT(*) AS ownershipRows, COUNT(DISTINCT idCharacter) AS shareholders,
       SUM(shareCount) AS totalHeldShares
FROM tblCompanyShareHolding;
SELECT CASE WHEN (SELECT COUNT(*) FROM tblCompany) = 13
                  AND (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) = 10
                  AND (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) = 12
                  AND (SELECT COUNT(*) FROM tblCompanyShareHolding) = 10
                  AND (SELECT COUNT(DISTINCT idCharacter) FROM tblCompanyShareHolding) = 9
                  AND (SELECT SUM(shareCount) FROM tblCompanyShareHolding) = 150
                  AND (SELECT COUNT(*) FROM tblCharacterShareStatus) = 9
                  AND (SELECT GROUP_CONCAT(idLegacyTraitLink ORDER BY idLegacyTraitLink)
                       FROM tblLegacyShareOrphanArchive)
                      = '11,95,235,236,246,247,248,266,336,429,430,453'
            THEN 'ok' ELSE 'investigate' END AS inventoryCheck;
SELECT co.id, co.companyName, co.issuedShareCount,
       COALESCE(SUM(h.shareCount), 0) AS personShares,
       COALESCE(MAX(a.shareCount), 0) AS anonymousShares,
       co.issuedShareCount - COALESCE(SUM(h.shareCount), 0)
         - COALESCE(MAX(a.shareCount), 0) AS freeShares
FROM tblCompany AS co
LEFT JOIN tblCompanyShareHolding AS h ON h.idCompany = co.id
LEFT JOIN tblCompanyAnonymousShares AS a ON a.idCompany = co.id
GROUP BY co.id, co.companyName, co.issuedShareCount ORDER BY co.id;
SELECT co.id AS invalidCompanyId
FROM tblCompany AS co
LEFT JOIN tblCompanyShareHolding AS h ON h.idCompany = co.id
LEFT JOIN tblCompanyAnonymousShares AS a ON a.idCompany = co.id
GROUP BY co.id, co.issuedShareCount
HAVING co.issuedShareCount IS NULL OR co.issuedShareCount = 0
    OR co.issuedShareCount < COALESCE(SUM(h.shareCount), 0) + COALESCE(MAX(a.shareCount), 0);
SELECT h.idCompany, h.idCharacter, h.shareCount,
       SUM(src.migratedShareCount) AS sourceShares
FROM tblCompanyShareHolding AS h
LEFT JOIN tblLegacyShareMigrationSource AS src
  ON src.idCompany = h.idCompany AND src.idCharacter = h.idCharacter
GROUP BY h.idCompany, h.idCharacter, h.shareCount
HAVING h.shareCount <> COALESCE(sourceShares, 0);
SELECT src.idCompany, src.idCharacter, SUM(src.migratedShareCount) AS sourceShares,
       MAX(h.shareCount) AS heldShares
FROM tblLegacyShareMigrationSource AS src
LEFT JOIN tblCompanyShareHolding AS h
  ON h.idCompany = src.idCompany AND h.idCharacter = src.idCharacter
GROUP BY src.idCompany, src.idCharacter
HAVING heldShares IS NULL OR sourceShares <> heldShares;
SELECT a.idLegacyTraitLink AS orphanStillPresent
FROM tblLegacyShareOrphanArchive AS a
JOIN tblLinkCharacterTrait AS lct ON lct.id = a.idLegacyTraitLink;
SELECT s.idCharacter, s.intrinsicPortfolioValue,
       ROUND(SUM(co.companyValue * h.shareCount / co.issuedShareCount), 2) AS recalculatedValue
FROM tblCharacterShareStatus AS s
JOIN tblCompanyShareHolding AS h ON h.idCharacter = s.idCharacter
JOIN tblCompany AS co ON co.id = h.idCompany
GROUP BY s.idCharacter, s.intrinsicPortfolioValue
HAVING s.intrinsicPortfolioValue <> recalculatedValue;
