-- Fase 1 aandelenhervorming: uitsluitend SELECT/SHOW. Geen DDL, DML of sessiewijziging.
-- Voer op de bedoelde database uit. phpMyAdmin: selecteer de database links,
-- controleer de DATABASE()-uitkomst en voer de migratieregistratie apart uit.
-- De historische export van 2026-09-24 is geen bewijs van de huidige online toestand.
-- Stop als een vereiste tabel ontbreekt; vul geen ontbrekende tabellen automatisch aan.

-- 01. Omgeving en vereiste tabellen.
SELECT DATABASE() AS databaseName, CURRENT_TIMESTAMP AS inspectedAt;
SHOW TABLES;
-- De volgende query staat bewust NIET in deze bundel. Als tblSchemaMigration
-- in SHOW TABLES staat, voer haar apart uit op de geselecteerde appdatabase:
-- SELECT migrationCode, description, appliedAt
-- FROM tblSchemaMigration ORDER BY migrationCode;

-- 02. Bestaande bedrijfskapitalen. Deze companyValue wordt bij migratie initialCompanyValue;
-- het voorgestelde initiele en uitgegeven aantal is 100. Niets wordt hier opgeslagen.
SELECT id AS idCompany, companyName, companyValue AS proposedInitialCompanyValue,
       100 AS proposedInitialShareCount, 100 AS proposedIssuedShareCount,
       CASE WHEN companyValue <= 0 THEN 'review_zero_or_negative_value' ELSE 'ok' END AS valueCheck
FROM tblCompany ORDER BY id;

-- 03. Alle oude aandeeltraitlinks, inclusief ontbrekende of ongeldige bedrijfsrelaties.
-- oldPercentage is alleen een geldige nieuwe hoeveelheid als de koppeling exact eenmaal bestaat.
SELECT lct.id AS idLinkCharacterTrait, lct.idCharacter, c.state AS characterState,
       lct.idTrait, tsd.shareClass, lct.rankValue,
       lctc.id AS idLinkCharacterTraitCompany, lctc.idCompany,
       lctc.extraPercentage,
       lct.rankValue + COALESCE(lctc.extraPercentage, 0) AS oldPercentage,
       CASE
         WHEN c.id IS NULL THEN 'blocking_missing_character'
         WHEN lctc.id IS NULL THEN 'remove_unlinked_share_trait'
         WHEN lctc.idCompany IS NULL THEN 'remove_unlinked_share_trait'
         WHEN co.id IS NULL THEN 'blocking_missing_company'
         WHEN lct.rankValue + lctc.extraPercentage <= 0 THEN 'blocking_nonpositive_percentage'
         ELSE 'linked_candidate'
       END AS inspectionResult
FROM tblLinkCharacterTrait AS lct
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
ORDER BY lct.id, lctc.id;

-- 04. Exacte te verwijderen kandidaat-TRAITLINKS: geen bedrijfskoppeling of uitsluitend NULL.
-- Een niet-NULL idCompany die naar een ontbrekend bedrijf wijst is hier bewust GEEN
-- automatische verwijderkandidaat: eerst als blokkerende inconsistentie beoordelen.
SELECT lct.id AS idLinkCharacterTrait, lct.idCharacter, lct.idTrait, lct.rankValue,
       GROUP_CONCAT(lctc.id ORDER BY lctc.id) AS oldCompanyLinkIds
FROM tblLinkCharacterTrait AS lct
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
GROUP BY lct.id, lct.idCharacter, lct.idTrait, lct.rankValue
HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
ORDER BY lct.id;

-- 05. Blokkerende relaties: ontbrekend bedrijf/personage, meerdere bedrijfslinks,
-- bedrijfskoppeling aan een niet-aandeeltrait, en niet-positieve hoeveelheden.
SELECT lctc.id, lctc.idLinkCharacterTrait, lctc.idCompany
FROM tblLinkCharacterTraitCompany AS lctc
LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lct.id IS NULL OR c.id IS NULL
   OR (lctc.idCompany IS NOT NULL AND co.id IS NULL)
   OR tsd.idTrait IS NULL
ORDER BY lctc.id;
-- De huidige PHP-code kent ook een naamfallback voor oude aandeeltraits.
-- Laat aandeelachtig genoemde traits zonder expliciete definitie afzonderlijk zien.
SELECT t.id AS idTrait, t.name, COUNT(lct.id) AS characterLinks
FROM tblTrait AS t
LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = t.id
LEFT JOIN tblLinkCharacterTrait AS lct ON lct.idTrait = t.id
WHERE LOWER(t.name) LIKE '%aandeel%' AND tsd.idTrait IS NULL
GROUP BY t.id, t.name
ORDER BY t.id;
SELECT idLinkCharacterTrait, COUNT(*) AS companyLinkCount,
       GROUP_CONCAT(idCompany ORDER BY idCompany) AS companyIds
FROM tblLinkCharacterTraitCompany
GROUP BY idLinkCharacterTrait HAVING COUNT(*) > 1
ORDER BY idLinkCharacterTrait;
SELECT lct.id, lct.idCharacter, lct.idTrait, lct.rankValue,
       lctc.idCompany, lctc.extraPercentage,
       lct.rankValue + lctc.extraPercentage AS oldPercentage
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
  AND (lct.rankValue + lctc.extraPercentage <= 0
       OR lct.rankValue + lctc.extraPercentage > 100)
ORDER BY lctc.idCompany, lct.id;

-- 06. Uitgegeven 100 per bedrijf, bestaand verkocht percentage -> aantal,
-- en vrije voorraad. allocated > 100 blokkeert de omzetting.
SELECT co.id AS idCompany, co.companyName, co.companyValue,
       COUNT(DISTINCT CASE WHEN tsd.idTrait IS NOT NULL THEN lct.id END) AS shareTraitLinks,
       COALESCE(SUM(CASE WHEN tsd.idTrait IS NOT NULL
                         THEN lct.rankValue + lctc.extraPercentage ELSE 0 END), 0)
           AS proposedSoldShares,
       100 - COALESCE(SUM(CASE WHEN tsd.idTrait IS NOT NULL
                               THEN lct.rankValue + lctc.extraPercentage ELSE 0 END), 0)
           AS proposedFreeShares
FROM tblCompany AS co
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idCompany = co.id
LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
GROUP BY co.id, co.companyName, co.companyValue
ORDER BY co.id;

-- 07. Bezit per personage en bedrijf; de historische A/B-klassen worden alleen
-- voor controle getoond. De nieuwe waardering gebruikt hele aandelen.
SELECT lct.idCharacter, c.state AS characterState, lctc.idCompany,
       co.companyName, tsd.shareClass,
       SUM(lct.rankValue + lctc.extraPercentage) AS proposedShareCount,
       co.companyValue,
       ROUND(co.companyValue * SUM(lct.rankValue + lctc.extraPercentage) / 100, 2)
           AS indicativeIntrinsicValue
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
JOIN tblCompany AS co ON co.id = lctc.idCompany
LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
GROUP BY lct.idCharacter, c.state, lctc.idCompany, co.companyName,
         tsd.shareClass, co.companyValue
ORDER BY lct.idCharacter, lctc.idCompany, tsd.shareClass;

-- 08. Totale indicatieve intrinsieke bezitswaarde per personage.
-- De uiteindelijke centafronding is een spelregel voor fase 2;
-- deze query markeert alvast bezit onder de eerste grens van Fr 600.000.
SELECT lct.idCharacter, c.state AS characterState,
       COUNT(DISTINCT lctc.idCompany) AS companyCount,
       SUM(lct.rankValue + lctc.extraPercentage) AS proposedShareCount,
       ROUND(SUM(co.companyValue * (lct.rankValue + lctc.extraPercentage) / 100), 2)
           AS indicativeIntrinsicPortfolioValue,
       CASE WHEN SUM(co.companyValue * (lct.rankValue + lctc.extraPercentage) / 100) < 600000
            THEN 'below_level_1' ELSE 'level_1_or_higher' END AS thresholdCheck
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
JOIN tblCompany AS co ON co.id = lctc.idCompany
LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
GROUP BY lct.idCharacter, c.state
ORDER BY lct.idCharacter;

-- 09. Legacy trait/companytype mismatch. Beoordeel afwijkingen; verander geen
-- historische trait-ID op basis van de huidige companyValue.
SELECT lct.id, lct.idCharacter, lct.idTrait, lctc.idCompany,
       co.companyValue,
       CASE
         WHEN co.companyValue <= 30000 THEN 'micro'
         WHEN co.companyValue <= 450000 THEN 'family'
         WHEN co.companyValue <= 6750000 THEN 'national'
         WHEN co.companyValue <= 101250000 THEN 'small_international'
         ELSE 'large_international'
       END AS currentCompanyType
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
JOIN tblCompany AS co ON co.id = lctc.idCompany
LEFT JOIN tblTraitShareAllowedCompanyType AS allowed
  ON allowed.idTrait = lct.idTrait
 AND allowed.companyTypeKey = CASE
         WHEN co.companyValue <= 30000 THEN 'micro'
         WHEN co.companyValue <= 450000 THEN 'family'
         WHEN co.companyValue <= 6750000 THEN 'national'
         WHEN co.companyValue <= 101250000 THEN 'small_international'
         ELSE 'large_international' END
WHERE allowed.id IS NULL
ORDER BY lctc.idCompany, lct.id;

-- 10. Snapshots, uitbetalingen en idempotentiehistorie: geen aandeeltradeboek.
SELECT cs.id, cs.idCompany, cs.idEvent, cs.companyValue, cs.appliedAction,
       cs.companyValueDelta, cs.profitAmount,
       COUNT(p.id) AS payoutRows, COALESCE(SUM(p.amount), 0.00) AS payoutAmount
FROM tblCompanySnapshot AS cs
LEFT JOIN tblCompanySnapshotPayout AS p ON p.idCompanySnapshot = cs.id
GROUP BY cs.id, cs.idCompany, cs.idEvent, cs.companyValue,
         cs.appliedAction, cs.companyValueDelta, cs.profitAmount
ORDER BY cs.idCompany, cs.idEvent;
SELECT idCompany, idEvent, COUNT(*) AS snapshotCount
FROM tblCompanySnapshot
GROUP BY idCompany, idEvent HAVING COUNT(*) > 1;
SELECT operation, COUNT(*) AS requestCount, MIN(createdAt) AS firstAt, MAX(createdAt) AS lastAt
FROM tblApiIdempotency
WHERE operation LIKE 'character.company_share.%' OR operation LIKE 'company.snapshot.%'
GROUP BY operation ORDER BY operation;

-- 11. Fiscaliteit en specialisaties: ID's controleren, namen zijn geen toekomstige business keys.
SELECT id, name FROM tblSkill WHERE id = 6 OR LOWER(name) LIKE '%fiscal%';
SELECT id, idSkill, name, kind
FROM tblSkillSpecialisation
WHERE idSkill = 6 OR LOWER(name) LIKE '%aandelenmarkt%'
ORDER BY idSkill, id;

-- 12. Personeel en saldo's van bestaande aandeelhouders (geen persoonsnamen).
SELECT role, COUNT(*) AS userCount FROM tblUser GROUP BY role ORDER BY role;
SELECT cp.idCompany, COUNT(*) AS personnelCount,
       SUM(CASE WHEN c.id IS NULL THEN 1 ELSE 0 END) AS missingCharacters
FROM tblCompanyPersonnel AS cp
LEFT JOIN tblCharacter AS c ON c.id = cp.idCharacter
GROUP BY cp.idCompany ORDER BY cp.idCompany;
SELECT c.id, c.type, c.state, c.bankaccount, c.securitiesaccount,
       COUNT(DISTINCT lctc.idCompany) AS shareholderCompanyCount
FROM tblCharacter AS c
JOIN tblLinkCharacterTrait AS lct ON lct.idCharacter = c.id
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
JOIN tblCompany AS co ON co.id = lctc.idCompany
GROUP BY c.id, c.type, c.state, c.bankaccount, c.securitiesaccount
ORDER BY c.id;

-- 13. Relevante sleutels en kolommen voor een volgende, nog NIET uitgevoerde migratie.
SHOW COLUMNS FROM tblCompany;
SHOW COLUMNS FROM tblCompanySnapshot;
SHOW COLUMNS FROM tblCharacter;
SHOW COLUMNS FROM tblLinkCharacterTrait;
SHOW COLUMNS FROM tblLinkCharacterTraitCompany;
SHOW COLUMNS FROM tblTraitShareDefinition;
SHOW COLUMNS FROM tblTraitShareAllowedCompanyType;
SHOW INDEX FROM tblCompany;
SHOW INDEX FROM tblCompanySnapshot;
SHOW INDEX FROM tblCharacter;
SHOW INDEX FROM tblLinkCharacterTrait;
SHOW INDEX FROM tblLinkCharacterTraitCompany;
