-- Fase 7: uitsluitend SELECT op de geselecteerde database; geen mutatie.
-- Verwacht na fase 5: 0006-0011 aanwezig. Een ontbrekende registratie eerst oplossen.
SELECT DATABASE() AS databaseName,
       GROUP_CONCAT(migrationCode ORDER BY migrationCode SEPARATOR ', ') AS shareMigrations,
       COUNT(*) AS migrationCount
FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill',
                        '0008_share_domain_codes', '0009_share_status_initialization',
                        '0010_holder_sale_offer', '0011_company_leadership');

-- Elke rij moet een niet-negatieve vrije voorraad hebben. Het originele aantal
-- hoeft na een geautoriseerde correctie of bedrijfsinkoop niet meer 100 te zijn.
SELECT c.id AS idCompany, c.companyName, c.initialShareCount, c.issuedShareCount,
       COALESCE(h.heldShares, 0) AS heldShares,
       COALESCE(a.shareCount, 0) AS anonymousShares,
       c.issuedShareCount - COALESCE(h.heldShares, 0) - COALESCE(a.shareCount, 0) AS freeShares,
       CASE WHEN c.initialShareCount IS NULL OR c.initialShareCount = 0
                      OR c.issuedShareCount IS NULL OR c.issuedShareCount = 0
                      OR c.issuedShareCount < COALESCE(h.heldShares, 0) + COALESCE(a.shareCount, 0)
            THEN 'INVALID' ELSE 'ok' END AS stockCheck
FROM tblCompany c
LEFT JOIN (SELECT idCompany, SUM(shareCount) AS heldShares
           FROM tblCompanyShareHolding GROUP BY idCompany) h ON h.idCompany = c.id
LEFT JOIN tblCompanyAnonymousShares a ON a.idCompany = c.id
ORDER BY c.id;

-- Verwacht: 0 rijen. Ontbrekende of verouderde statuswaarde blokkeert vrijgave.
SELECT h.idCharacter, s.intrinsicPortfolioValue AS storedValue,
       h.computedValue, s.shareLevel, s.usedStatusPoints, s.personnelRequirement
FROM (SELECT x.idCharacter,
             ROUND(SUM(x.shareCount * c.companyValue / c.issuedShareCount), 2) AS computedValue
      FROM tblCompanyShareHolding x
      JOIN tblCompany c ON c.id = x.idCompany
      GROUP BY x.idCharacter) h
LEFT JOIN tblCharacterShareStatus s ON s.idCharacter = h.idCharacter
WHERE s.idCharacter IS NULL
   OR ABS(s.intrinsicPortfolioValue - h.computedValue) >= 0.01
   OR s.shareLevel IS NULL OR s.usedStatusPoints IS NULL
   OR s.personnelRequirement IS NULL
   OR s.shareLevel <> s.usedStatusPoints
   OR s.personnelRequirement <> CASE WHEN s.shareLevel <= 2 THEN 0 ELSE s.shareLevel - 2 END
   OR (h.computedValue < 20000000.00 AND s.shareLevel <> CASE
       WHEN h.computedValue >= 10000000.00 THEN 5
       WHEN h.computedValue >= 5000000.00 THEN 4
       WHEN h.computedValue >= 2500000.00 THEN 3
       WHEN h.computedValue >= 1200000.00 THEN 2
       WHEN h.computedValue >= 600000.00 THEN 1
       ELSE 0 END)
   OR (h.computedValue >= 20000000.00 AND s.shareLevel < 6)
ORDER BY h.idCharacter;

-- Verwacht: 0 rijen. Na verkoop van het laatste aandeel wordt status nul.
SELECT s.idCharacter, s.intrinsicPortfolioValue, s.shareLevel,
       s.usedStatusPoints, s.personnelRequirement
FROM tblCharacterShareStatus s
WHERE NOT EXISTS (SELECT 1 FROM tblCompanyShareHolding h
                  WHERE h.idCharacter = s.idCharacter)
  AND (s.intrinsicPortfolioValue <> 0.00 OR s.shareLevel <> 0
       OR s.usedStatusPoints <> 0 OR s.personnelRequirement <> 0
       OR s.shareLevel IS NULL OR s.usedStatusPoints IS NULL
       OR s.personnelRequirement IS NULL);

-- Verwacht: 0 rijen. Geen verweesde migratiebronnen blijven als actief trait bestaan.
SELECT a.idLegacyTraitLink AS orphanStillActive
FROM tblLegacyShareOrphanArchive a
JOIN tblLinkCharacterTrait lct ON lct.id = a.idLegacyTraitLink;

-- Verwacht: 0 rijen. Iedere bedrijfsleider blijft bestuurslid en heeft het beroep.
SELECT cp.idCompany, COUNT(*) AS leaderCount,
       SUM(CASE WHEN cp.isBoardMember <> 1 OR NOT EXISTS (
                 SELECT 1 FROM tblLinkCharacterTrait AS link
                 JOIN tblTrait AS trait ON trait.id = link.idTrait
                 WHERE link.idCharacter = cp.idCharacter
                   AND trait.`class` = 'middle class' AND trait.`type` = 'profession'
                   AND trait.name IN ('Bedrijfsleider', 'Bedrijfsleider klein', 'Bedrijfsleider groot'))
                THEN 1 ELSE 0 END) AS invalidLeaderRows
FROM tblCompanyPersonnel cp
WHERE cp.isCompanyLeader = 1
GROUP BY cp.idCompany
HAVING invalidLeaderRows > 0;

-- Migratiehistoriek blijft herleidbaar; dit zijn rapportcijfers, geen vaste doelen.
SELECT (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) AS linkedLegacyRows,
       (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) AS archivedOrphans,
       (SELECT COUNT(*) FROM tblCompanyShareHolding) AS holdingRows,
       (SELECT COUNT(*) FROM tblCompanyShareTransaction) AS shareTransactions,
       (SELECT COUNT(*) FROM tblCompanyValueHistory) AS valueHistoryRows,
       (SELECT COUNT(*) FROM tblShareSearchAssessment) AS searchAssessments;
