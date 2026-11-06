-- Uitvoeren in beide databases nadat de aangepaste shareService.php is geplaatst.
-- Gericht gegevensherstel voor bedrijf 13 uit oneiros_be_aether.sql van 22-10-2026.
-- Geen schemawijziging. De oorspronkelijke export blijft als back-up behouden.
-- Alleen de bekende combinatie 81 actueel / 1 bij oprichting / 100 bij Holiday
-- wordt hersteld. Een geregistreerde aandeleninkoop sluit automatisch herstel uit.
-- Herhaald uitvoeren wijzigt de gegevens niet opnieuw.

START TRANSACTION;

SELECT id, companyName, initialShareCount, issuedShareCount
FROM tblCompany WHERE id = 13 FOR UPDATE;

UPDATE tblCompany AS c
SET c.issuedShareCount = 100
WHERE c.id = 13
  AND c.companyName = 'Van Lerius Transport & Spoorwegen'
  AND c.initialShareCount = 1
  AND c.issuedShareCount = 81
  AND EXISTS (
      SELECT 1 FROM tblCompanySnapshot AS s
      JOIN tblEvent AS e ON e.id = s.idEvent
      WHERE s.idCompany = c.id AND s.issuedShareCount = 100
        AND e.title = 'Holiday' AND e.dateStart = '2027-05-15'
        AND NOT EXISTS (
            SELECT 1 FROM tblCompanySnapshot AS later
            JOIN tblEvent AS laterEvent ON laterEvent.id = later.idEvent
            WHERE later.idCompany = s.idCompany
              AND (laterEvent.dateStart > e.dateStart
                OR (laterEvent.dateStart = e.dateStart AND later.id > s.id))
        )
  )
  AND NOT EXISTS (
      SELECT 1 FROM tblCompanyShareTransaction AS t
      WHERE t.idCompany = c.id AND t.operationCode = 'buyback_free'
  )
  AND COALESCE((SELECT SUM(h.shareCount) FROM tblCompanyShareHolding AS h
                WHERE h.idCompany = c.id), 0)
      + COALESCE((SELECT a.shareCount FROM tblCompanyAnonymousShares AS a
                  WHERE a.idCompany = c.id), 0) <= 100;

SET @aether_company13_share_repaired = ROW_COUNT();

-- Zelfde lockvolgorde als de aandelenservice: bedrijf, daarna personages.
SELECT p.id FROM tblCharacter AS p
JOIN tblCompanyShareHolding AS h ON h.idCharacter = p.id
WHERE h.idCompany = 13 AND @aether_company13_share_repaired = 1
ORDER BY p.id FOR UPDATE;

INSERT INTO tblCompanyValueHistory
    (idCompany, actorUserId, reasonCode, valueBefore, valueDelta, valueAfter, detailsJson)
SELECT c.id, NULL, 'share_count_repair', c.companyValue, 0.00, c.companyValue,
       JSON_OBJECT('migrationCode', '0019_company_foundation_share_count',
                   'issuedBefore', 81, 'issuedAfter', 100,
                   'reason', 'Founding count correction crossed explicit event snapshot')
FROM tblCompany AS c
WHERE c.id = 13 AND @aether_company13_share_repaired = 1;

-- Herwaardeer alle bezittingen van de betrokken personages met de bestaande
-- niveaudrempels; bezit, aankoopgeschiedenis, banken en bedrijfswaarde blijven gelijk.
INSERT INTO tblCharacterShareStatus
    (idCharacter, shareLevel, usedStatusPoints, personnelRequirement,
     intrinsicPortfolioValue, valuationVersion)
SELECT ranked.idCharacter, ranked.shareLevel, ranked.shareLevel,
       GREATEST(0, ranked.shareLevel - 2), ranked.portfolioValue, 1
FROM (
    SELECT valuations.*,
           CASE
             WHEN portfolioValue < 600000 THEN 0
             WHEN portfolioValue < 1200000 THEN 1
             WHEN portfolioValue < 2500000 THEN 2
             WHEN portfolioValue < 5000000 THEN 3
             WHEN portfolioValue < 10000000 THEN 4
             WHEN portfolioValue < 20000000 THEN 5
             ELSE 6 + FLOOR(LOG2(portfolioValue / 20000000))
           END AS shareLevel
    FROM (
        SELECT h.idCharacter,
               ROUND(SUM(h.shareCount * c.companyValue / c.issuedShareCount), 2) AS portfolioValue
        FROM tblCompanyShareHolding AS h
        JOIN tblCompany AS c ON c.id = h.idCompany
        WHERE @aether_company13_share_repaired = 1
          AND h.idCharacter IN (SELECT affected.idCharacter FROM tblCompanyShareHolding AS affected
                                WHERE affected.idCompany = 13)
        GROUP BY h.idCharacter
    ) AS valuations
) AS ranked
ON DUPLICATE KEY UPDATE
    shareLevel = VALUES(shareLevel), usedStatusPoints = VALUES(usedStatusPoints),
    personnelRequirement = VALUES(personnelRequirement),
    intrinsicPortfolioValue = VALUES(intrinsicPortfolioValue),
    valuationVersion = valuationVersion + 1;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
SELECT '0019_company_foundation_share_count',
       'Bound founding share count corrections by explicit snapshots; repair company 13'
WHERE @aether_company13_share_repaired = 1;

COMMIT;

-- Verwacht voor de aangeleverde export: 100 uitgegeven, 5 verkocht, 95 vrij.
SELECT c.id, c.companyName, c.issuedShareCount AS uitgegeven,
       COALESCE(h.held, 0) + COALESCE(a.shareCount, 0) AS verkocht,
       c.issuedShareCount - COALESCE(h.held, 0) - COALESCE(a.shareCount, 0) AS vrij,
       @aether_company13_share_repaired AS hersteld_in_deze_uitvoering
FROM tblCompany AS c
LEFT JOIN (SELECT idCompany, SUM(shareCount) AS held FROM tblCompanyShareHolding
           WHERE idCompany = 13 GROUP BY idCompany) AS h ON h.idCompany = c.id
LEFT JOIN tblCompanyAnonymousShares AS a ON a.idCompany = c.id
WHERE c.id = 13;
