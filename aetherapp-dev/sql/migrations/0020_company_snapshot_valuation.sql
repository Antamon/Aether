-- Plaats eerst de aangepaste PHP-code op beide websites. Uitvoeren na 0019.
-- Geen schemawijziging: herstel de bekende losse bedrijfswaarde van bedrijf 13
-- en herbereken alle opgeslagen aandelenstatussen vanuit dezelfde snapshots
-- als de bedrijfskaart. Banken, aandelenbezit en snapshots blijven behouden.
-- Herhaald uitvoeren wijzigt niets zodra deze migratie geregistreerd is.

START TRANSACTION;

SET @aether_0020_apply = NOT EXISTS (
    SELECT 1 FROM tblSchemaMigration WHERE migrationCode = '0020_company_snapshot_valuation'
);

SET @aether_0020_old_value = NULL;
SELECT companyValue INTO @aether_0020_old_value
FROM tblCompany WHERE id = 13 FOR UPDATE;

SET @aether_0020_repair_company13 = @aether_0020_apply AND EXISTS (
    SELECT 1 FROM tblCompany AS c
    JOIN tblCompanySnapshot AS s ON s.id = (
        SELECT latest.id FROM tblCompanySnapshot AS latest
        JOIN tblEvent AS e ON e.id = latest.idEvent
        WHERE latest.idCompany = c.id ORDER BY e.dateStart DESC, latest.id DESC LIMIT 1
    )
    JOIN tblEvent AS snapshotEvent ON snapshotEvent.id = s.idEvent
    WHERE c.id = 13 AND c.companyName = 'Van Lerius Transport & Spoorwegen'
      AND c.companyValue IN (2829164.00, 2774164.00, 2656664.00, 2664164.00, 2686664.00)
      AND s.companyValue = 2500000.00 AND s.issuedShareCount = 100
      AND snapshotEvent.title = 'Holiday' AND snapshotEvent.dateStart = '2027-05-15'
      AND NOT EXISTS (SELECT 1 FROM tblCompanyShareTransaction AS t
                      WHERE t.idCompany = c.id AND t.operationCode = 'buyback_free')
);

-- Bedrijf eerst, dan personages: dezelfde lockvolgorde als de applicatie.
SELECT id FROM tblCharacter
WHERE @aether_0020_apply AND id IN (SELECT idCharacter FROM tblCompanyShareHolding)
ORDER BY id FOR UPDATE;

INSERT INTO tblCompanyValueHistory
    (idCompany, actorUserId, reasonCode, valueBefore, valueDelta, valueAfter, detailsJson)
SELECT 13, NULL, 'snapshot_valuation_repair', @aether_0020_old_value,
       2500000.00 - @aether_0020_old_value, 2500000.00,
       JSON_OBJECT('migrationCode', '0020_company_snapshot_valuation',
                   'reason', 'Replace unrecorded purchase corrections by Holiday valuation')
WHERE @aether_0020_repair_company13;

UPDATE tblCompany SET companyValue = 2500000.00
WHERE id = 13 AND @aether_0020_repair_company13;

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
-- BEGIN canonical portfolio SELECT (also exercised by the isolated query test)
        SELECT h.idCharacter,
               COALESCE(ROUND(SUM(h.shareCount * COALESCE(valuationSnapshot.companyValue,
                   valuationFoundation.initialCompanyValue, c.companyValue)
                   / NULLIF(CASE WHEN valuationSnapshot.id IS NOT NULL
                       THEN COALESCE(valuationSnapshot.issuedShareCount, c.issuedShareCount)
                       ELSE COALESCE(valuationFoundation.initialShareCount, c.issuedShareCount) END, 0)), 2), 0.00) AS portfolioValue
        FROM tblCompanyShareHolding AS h
        JOIN tblCompany AS c ON c.id = h.idCompany
        LEFT JOIN tblCompanySnapshot AS valuationSnapshot ON valuationSnapshot.id = (
            SELECT s.id FROM tblCompanySnapshot AS s JOIN tblEvent AS e ON e.id = s.idEvent
            WHERE s.idCompany = c.id ORDER BY e.dateStart DESC, s.id DESC LIMIT 1)
        LEFT JOIN tblCompanyFoundationSnapshot AS valuationFoundation ON valuationFoundation.idCompany = c.id
        WHERE @aether_0020_apply
        GROUP BY h.idCharacter
-- END canonical portfolio SELECT
    ) AS valuations
) AS ranked
ON DUPLICATE KEY UPDATE
    shareLevel = VALUES(shareLevel), usedStatusPoints = VALUES(usedStatusPoints),
    personnelRequirement = VALUES(personnelRequirement),
    intrinsicPortfolioValue = VALUES(intrinsicPortfolioValue),
    valuationVersion = valuationVersion + 1;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
SELECT '0020_company_snapshot_valuation', 'Value shares from the recorded company snapshots'
WHERE @aether_0020_apply;

COMMIT;

SELECT id, companyName, companyValue, issuedShareCount FROM tblCompany WHERE id = 13;
SELECT @aether_0020_repair_company13 AS bedrijfswaarde_hersteld,
       @aether_0020_apply AS aandelenstatussen_herberekend;
