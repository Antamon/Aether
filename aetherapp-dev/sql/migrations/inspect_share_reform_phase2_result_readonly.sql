-- Controle van fase 2 op de geselecteerde ontwikkel-databank.
-- Alleen SELECT; veilig opnieuw uit te voeren in phpMyAdmin.
SELECT DATABASE() AS databaseName,
       GROUP_CONCAT(migrationCode ORDER BY migrationCode) AS phase2Migrations
FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema',
                        '0007_share_reform_backfill',
                        '0008_share_domain_codes');

SELECT (SELECT COUNT(*) FROM tblCompany WHERE initialShareCount = 100
                                    AND issuedShareCount = 100) AS convertedCompanies,
       (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) AS linkedSourceRows,
       (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) AS archivedOrphanRows,
       (SELECT COUNT(*) FROM tblCompanyShareHolding) AS holdingRows,
       (SELECT COUNT(DISTINCT idCharacter) FROM tblCompanyShareHolding) AS shareholders,
       (SELECT SUM(shareCount) FROM tblCompanyShareHolding) AS heldShares,
       (SELECT COUNT(*) FROM tblShareDomainCode
        WHERE code IN ('skill.fiscality', 'skill_specialisation.stock_market')) AS domainCodes;

SELECT co.id AS idCompany, co.issuedShareCount,
       COALESCE(SUM(h.shareCount), 0) AS heldShares,
       COALESCE(MAX(a.shareCount), 0) AS anonymousShares,
       co.issuedShareCount - COALESCE(SUM(h.shareCount), 0)
         - COALESCE(MAX(a.shareCount), 0) AS freeShares
FROM tblCompany AS co
LEFT JOIN tblCompanyShareHolding AS h ON h.idCompany = co.id
LEFT JOIN tblCompanyAnonymousShares AS a ON a.idCompany = co.id
GROUP BY co.id, co.issuedShareCount
ORDER BY co.id;
