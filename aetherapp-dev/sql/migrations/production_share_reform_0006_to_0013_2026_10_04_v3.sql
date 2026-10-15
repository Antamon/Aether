-- Productieomzetting aandelen 0006-0013 voor oneiros_be_aether.
-- Gebaseerd op productie-export van 4 oktober 2026; maak vlak voor uitvoering een verse back-up.
-- Voer dit volledige bestand eenmaal uit op oneiros_be_aether voor het uploaden van nieuwe applicatiecode.
-- Alle read-only controles, apply- en verifyqueries staan in dit ene bestand.
-- De meldingen 'empty result set' bij SET/DDL zijn geen fout.
SELECT DATABASE() AS selectedDatabase,
       'Aether fase 2: controleer de eindmelding onderaan' AS instruction;
SET @aether_phase2_orphan_ids := (
  SELECT GROUP_CONCAT(x.id ORDER BY x.id SEPARATOR ',') FROM (
    SELECT lct.id
    FROM tblLinkCharacterTrait AS lct
    JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
    LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
    GROUP BY lct.id
    HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
  ) AS x
);
SET @aether_phase2_orphan_count := (
  SELECT COUNT(*) FROM (
    SELECT lct.id
    FROM tblLinkCharacterTrait AS lct
    JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
    LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
    GROUP BY lct.id
    HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
  ) AS x
);
SET @aether_phase2_orphan_list_count := IF(
  @aether_phase2_orphan_ids IS NULL, 0,
  1 + LENGTH(@aether_phase2_orphan_ids)
    - LENGTH(REPLACE(@aether_phase2_orphan_ids, ',', ''))
);
SET @aether_phase2_orphan_join_count := (
  SELECT COUNT(*)
  FROM tblLinkCharacterTrait AS lct
  JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
  LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
  WHERE FIND_IN_SET(lct.id, @aether_phase2_orphan_ids) > 0
);
-- Broncontrole voor de eerste wijziging; losse traits worden dynamisch gearchiveerd.
SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCompany) AS companyCount,
       (SELECT COUNT(*) FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
        WHERE lctc.idCompany IS NOT NULL) AS linkedShareRows,
       @aether_phase2_orphan_ids AS orphanTraitIds,
       @aether_phase2_orphan_count AS orphanTraitCount,
       @aether_phase2_orphan_list_count AS orphanListCount,
       @aether_phase2_orphan_join_count AS orphanArchiveSourceRows,
       (SELECT COUNT(DISTINCT lct.idCharacter)
          FROM tblLinkCharacterTraitCompany AS lctc
          JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
          JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL) AS shareholderCount,
       (SELECT COUNT(*) FROM (
         SELECT lctc.idCompany, lct.idCharacter
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL
         GROUP BY lctc.idCompany, lct.idCharacter
       ) AS holders) AS holdingRows,
       (SELECT COALESCE(SUM(lct.rankValue + lctc.extraPercentage), 0)
          FROM tblLinkCharacterTraitCompany AS lctc
          JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
          JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL) AS legacyHeldShares,
       (SELECT COUNT(*) FROM (
         SELECT lct.idCharacter
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         JOIN tblCompany AS co ON co.id = lctc.idCompany
         WHERE lctc.idCompany IS NOT NULL
         GROUP BY lct.idCharacter
         HAVING ROUND(SUM(co.companyValue * (lct.rankValue + lctc.extraPercentage) / 100), 2) >= 600000
       ) AS highValueHolders) AS highValueHolders;
SET @aether_phase2_target_ok := DATABASE() = 'oneiros_be_aether'
  AND (SELECT COUNT(*) FROM tblCompany) = 12
  AND (SELECT COUNT(*) FROM tblLinkCharacterTraitCompany AS lctc
       JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
       JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
       WHERE lctc.idCompany IS NOT NULL) = 10
  AND (SELECT COUNT(DISTINCT lct.idCharacter)
       FROM tblLinkCharacterTraitCompany AS lctc
       JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
       JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
       WHERE lctc.idCompany IS NOT NULL) = 9
  AND (SELECT COALESCE(SUM(lct.rankValue + lctc.extraPercentage), 0)
       FROM tblLinkCharacterTraitCompany AS lctc
       JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
       JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
       WHERE lctc.idCompany IS NOT NULL) = 148
  AND (SELECT COUNT(*) FROM (
         SELECT lctc.idCompany, lct.idCharacter
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL
         GROUP BY lctc.idCompany, lct.idCharacter
       ) AS holders) = 10
  AND (SELECT COUNT(*) FROM (
         SELECT lct.idCharacter
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         JOIN tblCompany AS co ON co.id = lctc.idCompany
         WHERE lctc.idCompany IS NOT NULL
         GROUP BY lct.idCharacter
         HAVING ROUND(SUM(co.companyValue * (lct.rankValue + lctc.extraPercentage) / 100), 2) >= 600000
       ) AS highValueHolders) = 0
  AND @aether_phase2_orphan_list_count = @aether_phase2_orphan_count
  AND @aether_phase2_orphan_join_count = @aether_phase2_orphan_count
  AND (SELECT COUNT(*) FROM tblLinkCharacterTraitCompany AS lctc
       LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
       LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
       LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
       LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
       WHERE lctc.idCompany IS NOT NULL
         AND (lct.id IS NULL OR c.id IS NULL OR co.id IS NULL OR tsd.idTrait IS NULL
              OR lct.rankValue + lctc.extraPercentage <= 0
              OR lct.rankValue + lctc.extraPercentage > 100)) = 0
  AND (SELECT COUNT(*) FROM (
         SELECT lctc.idCompany
         FROM tblLinkCharacterTraitCompany AS lctc
         JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
         JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL
         GROUP BY lctc.idCompany
         HAVING SUM(lct.rankValue + lctc.extraPercentage) > 100
       ) AS overallocated) = 0
  AND (SELECT COUNT(*) FROM tblSkill
       WHERE id = 6 AND LOWER(name) = 'fiscaliteit') = 1
  AND (SELECT COUNT(*) FROM tblSkillSpecialisation
       WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation') <= 1
  AND (SELECT COUNT(*) FROM tblSchemaMigration
       WHERE migrationCode IN (
         '0001_create_schema_migration_registry',
         '0002_unique_character_skill',
         '0003_unique_character_specialisation',
         '0004_create_api_idempotency',
         '0005_unique_event_user')) = 5
  AND (SELECT COUNT(*) FROM tblSchemaMigration
       WHERE migrationCode IN ('0006_share_reform_schema',
                               '0007_share_reform_backfill',
                               '0008_share_domain_codes')) = 0;
SET @aether_phase2_guard_sql := IF(
  @aether_phase2_target_ok,
  'SELECT ''Database en fase-2-bron bevestigd'' AS result',
  'SELECT * FROM __AETHER_BLOCK_PHASE2_WRONG_TARGET_OR_SOURCE__'
);
PREPARE aether_phase2_guard FROM @aether_phase2_guard_sql;
EXECUTE aether_phase2_guard;
DEALLOCATE PREPARE aether_phase2_guard;

-- ===== Voorcontrole 0006 =====
-- Read-only preflight voor de additieve fase-2-structuur.
-- De productie-export bevat 12 bedrijven, 15 orphan links en geen
-- gekoppelde fouten op oneiros_be_aether; controleer de bron opnieuw.
SELECT DATABASE() AS selectedDatabase;
SELECT migrationCode FROM tblSchemaMigration ORDER BY migrationCode;
SELECT id, companyName, companyValue FROM tblCompany ORDER BY id;
SELECT lct.id AS orphanTraitLinkId, lct.idCharacter, lct.idTrait,
       GROUP_CONCAT(lctc.id ORDER BY lctc.id) AS companyLinkIds
FROM tblLinkCharacterTrait AS lct
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
GROUP BY lct.id, lct.idCharacter, lct.idTrait
HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
ORDER BY lct.id;
SELECT lctc.idCompany, SUM(lct.rankValue + lctc.extraPercentage) AS soldShares,
       100 - SUM(lct.rankValue + lctc.extraPercentage) AS freeShares
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
GROUP BY lctc.idCompany ORDER BY lctc.idCompany;
SELECT lctc.id AS invalidCompanyLinkId
FROM tblLinkCharacterTraitCompany AS lctc
LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
  AND (lct.id IS NULL OR c.id IS NULL OR co.id IS NULL OR tsd.idTrait IS NULL
       OR lct.rankValue + lctc.extraPercentage <= 0
       OR lct.rankValue + lctc.extraPercentage > 100);
SHOW INDEX FROM tblCompanySnapshot;
SHOW INDEX FROM tblCompanyPersonnel;
SHOW INDEX FROM tblLinkCharacterTraitCompany;

-- ===== Structuur 0006 =====
-- Fase 2 / 0006: uitsluitend additieve structuur voor het nieuwe aandelendomein.
-- Eerst preflight/0006 uitvoeren. DDL commit impliciet in MariaDB.
-- Zet de oude runtime stil tijdens de omzetting; DDL commit impliciet.

ALTER TABLE tblCompany
  ADD COLUMN IF NOT EXISTS initialCompanyValue DECIMAL(18,2) NULL,
  ADD COLUMN IF NOT EXISTS initialShareCount INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS issuedShareCount INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS isShareListed TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE tblCompanySnapshot
  ADD COLUMN IF NOT EXISTS shareMarketPrice DECIMAL(18,2) NULL;

ALTER TABLE tblCompanyPersonnel
  ADD COLUMN IF NOT EXISTS isBoardMember TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS tblShareDomainCode (
  code VARCHAR(80) NOT NULL,
  entityType ENUM('skill','skill_specialisation','trait') NOT NULL,
  entityId INT NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (code),
  UNIQUE KEY uq_share_domain_entity (entityType, entityId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCompanyShareHolding (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCompany INT UNSIGNED NOT NULL,
  idCharacter INT NOT NULL,
  shareCount INT UNSIGNED NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share_holding_company_character (idCompany, idCharacter),
  KEY idx_share_holding_character (idCharacter),
  CONSTRAINT fk_share_holding_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id),
  CONSTRAINT fk_share_holding_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id),
  CONSTRAINT chk_share_holding_positive CHECK (shareCount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCompanyAnonymousShares (
  idCompany INT UNSIGNED NOT NULL,
  shareCount INT UNSIGNED NOT NULL DEFAULT 0,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (idCompany),
  CONSTRAINT fk_share_anonymous_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Momentopname van de oude bron. Geen FK: deze rijen blijven bestaan na het
-- gecontroleerd verwijderen van aandeeltraits zonder bedrijf.
CREATE TABLE IF NOT EXISTS tblLegacyShareMigrationSource (
  idLegacyCompanyLink INT UNSIGNED NOT NULL,
  idLegacyTraitLink INT NOT NULL,
  idCharacter INT NOT NULL,
  idTrait INT NOT NULL,
  shareClass CHAR(1) NOT NULL,
  idCompany INT UNSIGNED NOT NULL,
  rankValue INT NOT NULL,
  extraPercentage INT NOT NULL,
  migratedShareCount INT UNSIGNED NOT NULL,
  legacyTraitCreatedAt DATETIME NOT NULL,
  legacyTraitUpdatedAt DATETIME NOT NULL,
  legacyCompanyLinkCreatedAt DATETIME NOT NULL,
  legacyCompanyLinkUpdatedAt DATETIME NOT NULL,
  capturedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idLegacyCompanyLink),
  KEY idx_legacy_share_source_company (idCompany),
  KEY idx_legacy_share_source_character (idCharacter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblLegacyShareOrphanArchive (
  idLegacyTraitLink INT NOT NULL,
  idLegacyCompanyLink INT UNSIGNED NULL,
  idCharacter INT NOT NULL,
  idTrait INT NOT NULL,
  rankValue INT NOT NULL,
  extraPercentage INT NULL,
  legacyTraitCreatedAt DATETIME NOT NULL,
  legacyTraitUpdatedAt DATETIME NOT NULL,
  legacyCompanyLinkCreatedAt DATETIME NULL,
  legacyCompanyLinkUpdatedAt DATETIME NULL,
  capturedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idLegacyTraitLink),
  KEY idx_legacy_share_orphan_character (idCharacter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- level blijft NULL zolang de ondergrensregel voor historische bezitters niet vastligt.
CREATE TABLE IF NOT EXISTS tblCharacterShareStatus (
  idCharacter INT NOT NULL,
  shareLevel INT UNSIGNED NULL,
  usedStatusPoints INT UNSIGNED NULL,
  personnelRequirement INT UNSIGNED NULL,
  intrinsicPortfolioValue DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  valuationVersion BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (idCharacter),
  CONSTRAINT fk_share_status_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCharacterShareDraftBudget (
  idCharacter INT NOT NULL,
  purchasedLevel INT UNSIGNED NOT NULL,
  budgetAmount DECIMAL(18,2) NOT NULL,
  spentAmount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  closedAt DATETIME NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (idCharacter),
  CONSTRAINT fk_share_budget_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id),
  CONSTRAINT chk_share_budget_amount CHECK (budgetAmount >= 0 AND spentAmount >= 0 AND spentAmount <= budgetAmount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblShareSearch (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCharacter INT NOT NULL,
  idEvent INT NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastCheckedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share_search_character_event (idCharacter, idEvent),
  KEY idx_share_search_event (idEvent),
  CONSTRAINT fk_share_search_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id),
  CONSTRAINT fk_share_search_event FOREIGN KEY (idEvent) REFERENCES tblEvent (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblShareSearchAssessment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idSearch BIGINT UNSIGNED NOT NULL,
  idCompany INT UNSIGNED NOT NULL,
  idSnapshot INT UNSIGNED NOT NULL,
  effectiveSkillLevel TINYINT UNSIGNED NOT NULL,
  discoveryRoll SMALLINT UNSIGNED NOT NULL,
  quantityRoll SMALLINT UNSIGNED NULL,
  priceRoll SMALLINT UNSIGNED NULL,
  wasDiscovered TINYINT(1) NOT NULL,
  assessedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share_assessment_search_company_snapshot (idSearch, idCompany, idSnapshot),
  KEY idx_share_assessment_snapshot (idSnapshot),
  CONSTRAINT fk_share_assessment_search FOREIGN KEY (idSearch) REFERENCES tblShareSearch (id),
  CONSTRAINT fk_share_assessment_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id),
  CONSTRAINT fk_share_assessment_snapshot FOREIGN KEY (idSnapshot) REFERENCES tblCompanySnapshot (id),
  CONSTRAINT chk_share_assessment_level CHECK (effectiveSkillLevel BETWEEN 1 AND 4),
  CONSTRAINT chk_share_assessment_roll CHECK (discoveryRoll BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblShareOffer (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCompany INT UNSIGNED NOT NULL,
  idEvent INT NULL,
  idSnapshot INT UNSIGNED NULL,
  idAssessment BIGINT UNSIGNED NULL,
  idOfferingCharacter INT NULL,
  sourceCode ENUM('search','shared_search','private_board','director_grant') NOT NULL,
  statusCode ENUM('open','filled','cancelled','expired') NOT NULL DEFAULT 'open',
  offeredShareCount INT UNSIGNED NOT NULL,
  purchasedShareCount INT UNSIGNED NOT NULL DEFAULT 0,
  unitPrice DECIMAL(18,2) NOT NULL,
  expiresAt DATETIME NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_share_offer_company_status (idCompany, statusCode),
  KEY idx_share_offer_event_status (idEvent, statusCode),
  KEY idx_share_offer_assessment (idAssessment),
  CONSTRAINT fk_share_offer_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id),
  CONSTRAINT fk_share_offer_event FOREIGN KEY (idEvent) REFERENCES tblEvent (id),
  CONSTRAINT fk_share_offer_snapshot FOREIGN KEY (idSnapshot) REFERENCES tblCompanySnapshot (id),
  CONSTRAINT fk_share_offer_assessment FOREIGN KEY (idAssessment) REFERENCES tblShareSearchAssessment (id),
  CONSTRAINT fk_share_offer_offering_character FOREIGN KEY (idOfferingCharacter) REFERENCES tblCharacter (id),
  CONSTRAINT chk_share_offer_count CHECK (offeredShareCount > 0 AND purchasedShareCount <= offeredShareCount),
  CONSTRAINT chk_share_offer_price CHECK (unitPrice >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblShareOfferRecipient (
  idOffer BIGINT UNSIGNED NOT NULL,
  idCharacter INT NOT NULL,
  recipientKind ENUM('discoverer','shared','private') NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idOffer, idCharacter),
  KEY idx_share_offer_recipient_character (idCharacter, idOffer),
  CONSTRAINT fk_share_recipient_offer FOREIGN KEY (idOffer) REFERENCES tblShareOffer (id),
  CONSTRAINT fk_share_recipient_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCompanyShareTransaction (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCompany INT UNSIGNED NOT NULL,
  idCharacter INT NULL,
  idCounterpartyCharacter INT NULL,
  idOffer BIGINT UNSIGNED NULL,
  idEvent INT NULL,
  actorUserId INT NULL,
  operationCode VARCHAR(64) NOT NULL,
  shareCount INT UNSIGNED NOT NULL,
  unitPrice DECIMAL(18,2) NULL,
  totalAmount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  companyValueBefore DECIMAL(18,2) NOT NULL,
  companyValueAfter DECIMAL(18,2) NOT NULL,
  requestKey VARCHAR(128) NULL,
  detailsJson MEDIUMTEXT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_share_tx_company_time (idCompany, createdAt),
  KEY idx_share_tx_character_time (idCharacter, createdAt),
  KEY idx_share_tx_offer (idOffer),
  UNIQUE KEY uq_share_tx_actor_operation_request (actorUserId, operationCode, requestKey),
  CONSTRAINT fk_share_tx_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id),
  CONSTRAINT fk_share_tx_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id),
  CONSTRAINT fk_share_tx_counterparty FOREIGN KEY (idCounterpartyCharacter) REFERENCES tblCharacter (id),
  CONSTRAINT fk_share_tx_offer FOREIGN KEY (idOffer) REFERENCES tblShareOffer (id),
  CONSTRAINT fk_share_tx_event FOREIGN KEY (idEvent) REFERENCES tblEvent (id),
  CONSTRAINT fk_share_tx_actor FOREIGN KEY (actorUserId) REFERENCES tblUser (id),
  CONSTRAINT chk_share_tx_count CHECK (shareCount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCompanyValueHistory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCompany INT UNSIGNED NOT NULL,
  idShareTransaction BIGINT UNSIGNED NULL,
  idSnapshot INT UNSIGNED NULL,
  actorUserId INT NULL,
  reasonCode VARCHAR(64) NOT NULL,
  valueBefore DECIMAL(18,2) NOT NULL,
  valueDelta DECIMAL(18,2) NOT NULL,
  valueAfter DECIMAL(18,2) NOT NULL,
  detailsJson MEDIUMTEXT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_value_history_company_time (idCompany, createdAt),
  UNIQUE KEY uq_value_history_share_transaction (idShareTransaction),
  CONSTRAINT fk_value_history_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id),
  CONSTRAINT fk_value_history_share_transaction FOREIGN KEY (idShareTransaction) REFERENCES tblCompanyShareTransaction (id),
  CONSTRAINT fk_value_history_snapshot FOREIGN KEY (idSnapshot) REFERENCES tblCompanySnapshot (id),
  CONSTRAINT fk_value_history_actor FOREIGN KEY (actorUserId) REFERENCES tblUser (id),
  CONSTRAINT chk_value_history_delta CHECK (valueAfter = valueBefore + valueDelta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0006_share_reform_schema', 'Add share reform schema without converting historical ownership')
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

-- ===== Structuurcontrole 0006 =====
-- Alleen-lezen structuurcontrole. Controleer ook de kolomtypes en sleutels.
SHOW COLUMNS FROM tblCompany;
SHOW COLUMNS FROM tblCompanySnapshot;
SHOW COLUMNS FROM tblCompanyPersonnel;
SHOW CREATE TABLE tblCompanyShareHolding;
SHOW CREATE TABLE tblCompanyAnonymousShares;
SHOW CREATE TABLE tblLegacyShareMigrationSource;
SHOW CREATE TABLE tblLegacyShareOrphanArchive;
SHOW CREATE TABLE tblCharacterShareStatus;
SHOW CREATE TABLE tblCharacterShareDraftBudget;
SHOW CREATE TABLE tblShareSearch;
SHOW CREATE TABLE tblShareSearchAssessment;
SHOW CREATE TABLE tblShareOffer;
SHOW CREATE TABLE tblShareOfferRecipient;
SHOW CREATE TABLE tblCompanyShareTransaction;
SHOW CREATE TABLE tblCompanyValueHistory;
SHOW CREATE TABLE tblShareDomainCode;
SELECT migrationCode, appliedAt FROM tblSchemaMigration
WHERE migrationCode = '0006_share_reform_schema';

-- ===== Voorcontrole 0007 =====
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
WHERE FIND_IN_SET(lct.id, @aether_phase2_orphan_ids) > 0
ORDER BY lct.id;
SELECT lctc.idCompany, COUNT(*) AS linkedRows,
       SUM(lct.rankValue + lctc.extraPercentage) AS soldShares
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
GROUP BY lctc.idCompany ORDER BY lctc.idCompany;
-- Verwacht: 12/12 bedrijven, 0 bestaande rijen, 10 gekoppelde rijen,
-- en geen bedrijf boven 100 aandelen. Het aantal losse traits kan veranderen.

-- ===== Omzetting 0007 =====
-- Fase 2 / 0007: gecontroleerde omzetting op de geselecteerde database.
-- Eerst 0006, back-up, oude aandeelwrites uit en preflight/0007.
-- Laat tijdens uitvoering niemand de oude procentuele share-endpoints gebruiken.
-- Bij een SQL-fout voor COMMIT: ROLLBACK in dezelfde sessie en back-up bewaren.

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
  AND @aether_0007_company_count = 12 AND @aether_0007_linked_count = 10
  AND @aether_0007_empty = 0 AND @aether_0007_unset = 12
  AND @aether_0007_invalid = 0 AND @aether_0007_overallocated = 0
  AND (@aether_0007_orphan_ids <=> @aether_phase2_orphan_ids),
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
WHERE FIND_IN_SET(lct.id, @aether_phase2_orphan_ids) > 0;

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

-- ===== Omzettingscontrole 0007 =====
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
SELECT CASE WHEN (SELECT COUNT(*) FROM tblCompany) = 12
                  AND (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) = 10
                  AND (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) = @aether_phase2_orphan_count
                  AND (SELECT COUNT(*) FROM tblCompanyShareHolding) = 10
                  AND (SELECT COUNT(DISTINCT idCharacter) FROM tblCompanyShareHolding) = 9
                  AND (SELECT SUM(shareCount) FROM tblCompanyShareHolding) = 148
                  AND (SELECT COUNT(*) FROM tblCharacterShareStatus) = 9
                  AND ((SELECT GROUP_CONCAT(idLegacyTraitLink ORDER BY idLegacyTraitLink)
                        FROM tblLegacyShareOrphanArchive) <=> @aether_phase2_orphan_ids)
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

-- ===== Voorcontrole 0008 =====
-- Read-only; controleer actuele ID's op de geselecteerde database.
SELECT migrationCode FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill',
                        '0008_share_domain_codes');
SELECT id, name FROM tblSkill WHERE id = 6 OR LOWER(name) LIKE '%fiscal%';
SELECT id, idSkill, name, kind FROM tblSkillSpecialisation
WHERE idSkill = 6 OR LOWER(name) LIKE '%aandelenmarkt%'
ORDER BY idSkill, id;
SELECT code, entityType, entityId FROM tblShareDomainCode ORDER BY code;

-- ===== Domeincodes 0008 =====
-- Maak een taalneutrale mapping voor Fiscaliteit/Aandelenmarkt.
-- De naamcontrole gebeurt alleen bij de eenmalige binding; runtime zoekt op code.
-- Stop als skill 6 niet de bedoelde vaardigheid is; pas niet blind het ID aan.
SET @aether_0008_ready := (
  (SELECT COUNT(*) FROM tblSchemaMigration
   WHERE migrationCode = '0007_share_reform_backfill') = 1
  AND (SELECT COUNT(*) FROM tblSchemaMigration
       WHERE migrationCode = '0008_share_domain_codes') = 0
  AND (SELECT COUNT(*) FROM tblSkill
       WHERE id = 6 AND LOWER(name) = 'fiscaliteit') = 1
  AND (SELECT COUNT(*) FROM tblSkillSpecialisation
       WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation') <= 1
  AND (SELECT COUNT(*) FROM tblShareDomainCode
       WHERE code IN ('skill.fiscality', 'skill_specialisation.stock_market')) = 0
);
SET @aether_0008_guard_sql := IF(
  @aether_0008_ready,
  'SELECT ''0008 preflight passed'' AS result',
  'SELECT * FROM __AETHER_BLOCK_0008_WRONG_SKILL_OR_MAPPING__'
);
PREPARE aether_0008_guard FROM @aether_0008_guard_sql;
EXECUTE aether_0008_guard;
DEALLOCATE PREPARE aether_0008_guard;

START TRANSACTION;
INSERT INTO tblSkillSpecialisation (idSkill, name, kind)
SELECT 6, 'Aandelenmarkt', 'specialisation'
WHERE NOT EXISTS (
  SELECT 1 FROM tblSkillSpecialisation
  WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation'
);
INSERT INTO tblShareDomainCode (code, entityType, entityId)
VALUES ('skill.fiscality', 'skill', 6);
INSERT INTO tblShareDomainCode (code, entityType, entityId)
SELECT 'skill_specialisation.stock_market', 'skill_specialisation', id
FROM tblSkillSpecialisation
WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation';
INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0008_share_domain_codes', 'Bind fiscality and stock-market specialisation to stable codes');
COMMIT;

-- ===== Domeincodecontrole 0008 =====
SELECT migrationCode, appliedAt FROM tblSchemaMigration
WHERE migrationCode = '0008_share_domain_codes';
SELECT dc.code, dc.entityType, dc.entityId,
       s.name AS skillName, sp.name AS specialisationName, sp.idSkill AS parentSkillId
FROM tblShareDomainCode AS dc
LEFT JOIN tblSkill AS s ON dc.entityType = 'skill' AND s.id = dc.entityId
LEFT JOIN tblSkillSpecialisation AS sp
  ON dc.entityType = 'skill_specialisation' AND sp.id = dc.entityId
WHERE dc.code IN ('skill.fiscality', 'skill_specialisation.stock_market')
ORDER BY dc.code;

-- ===== Eindcontrole: alleen deze melding betekent dat de bundel geslaagd is =====
SET @aether_phase2_verified :=
  (SELECT COUNT(*) FROM tblSchemaMigration
   WHERE migrationCode IN ('0006_share_reform_schema',
                           '0007_share_reform_backfill',
                           '0008_share_domain_codes')) = 3
  AND (SELECT COUNT(*) FROM tblCompany
       WHERE initialShareCount = 100 AND issuedShareCount = 100
         AND initialCompanyValue = companyValue) = 12
  AND (SELECT COUNT(*) FROM tblLegacyShareMigrationSource) = 10
  AND (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive) = @aether_phase2_orphan_count
  AND (SELECT COUNT(*) FROM tblCompanyShareHolding) = 10
  AND (SELECT COUNT(DISTINCT idCharacter) FROM tblCompanyShareHolding) = 9
  AND (SELECT SUM(shareCount) FROM tblCompanyShareHolding) = 148
  AND (SELECT COUNT(*) FROM tblCharacterShareStatus
       WHERE shareLevel IS NULL AND usedStatusPoints IS NULL) = 9
  AND (SELECT COUNT(*) FROM tblShareDomainCode
       WHERE code IN ('skill.fiscality', 'skill_specialisation.stock_market')) = 2
  AND (SELECT COUNT(*) FROM tblLegacyShareOrphanArchive AS a
       JOIN tblLinkCharacterTrait AS lct ON lct.id = a.idLegacyTraitLink) = 0
  AND (SELECT COUNT(*) FROM (
         SELECT co.id
         FROM tblCompany AS co
         LEFT JOIN tblCompanyShareHolding AS h ON h.idCompany = co.id
         LEFT JOIN tblCompanyAnonymousShares AS a ON a.idCompany = co.id
         GROUP BY co.id, co.issuedShareCount
         HAVING co.issuedShareCount IS NULL OR co.issuedShareCount = 0
             OR co.issuedShareCount < COALESCE(SUM(h.shareCount), 0)
                                      + COALESCE(MAX(a.shareCount), 0)
       ) AS invalidStock) = 0;
SET @aether_phase2_verify_sql := IF(
  @aether_phase2_verified,
  'SELECT ''FASE 2 GESLAAGD'' AS result',
  'SELECT * FROM __AETHER_BLOCK_PHASE2_VERIFICATION_FAILED__'
);
PREPARE aether_phase2_verify FROM @aether_phase2_verify_sql;
EXECUTE aether_phase2_verify;
DEALLOCATE PREPARE aether_phase2_verify;
SELECT CASE WHEN @aether_phase2_verified THEN 'FASE 2 GESLAAGD'
            ELSE 'FASE 2 NIET GESLAAGD' END AS result,
       DATABASE() AS databaseName;

-- 0009-0010: initialiseer bezitters en voeg verkoopaanbiedingen toe.
-- Historische negen portefeuilles zijn volgens de gecontroleerde preflight allemaal < Fr 600.000.
-- Voer als volledig bestand in phpMyAdmin uit; eindresultaat toont aantallen en migratiestatus.

SET @aether_phase3_ready := (
  SELECT COUNT(*) = 3 FROM tblSchemaMigration
  WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill', '0008_share_domain_codes')
);
SET @aether_phase3_safe := @aether_phase3_ready
  AND DATABASE() = 'oneiros_be_aether'
  AND (SELECT COUNT(*) FROM tblCharacterShareStatus) = 9
  AND (SELECT COUNT(*) FROM tblCharacterShareStatus WHERE intrinsicPortfolioValue >= 600000.00) = 0
  AND (SELECT COUNT(*) FROM tblCompanyShareHolding) = 10;
SET @aether_phase3_guard := IF(@aether_phase3_safe,
  'SELECT ''FASE 3 STATUS PREFLIGHT GESLAAGD'' AS result',
  'SELECT * FROM __AETHER_BLOCK_PHASE3_STATUS_PREFLIGHT_FAILED__');
PREPARE aether_phase3_check FROM @aether_phase3_guard;
EXECUTE aether_phase3_check;
DEALLOCATE PREPARE aether_phase3_check;

START TRANSACTION;
UPDATE tblCharacterShareStatus
   SET shareLevel = 0, usedStatusPoints = 0, personnelRequirement = 0,
       valuationVersion = valuationVersion + 1
 WHERE @aether_phase3_safe = 1
   AND (shareLevel IS NULL OR usedStatusPoints IS NULL OR personnelRequirement IS NULL)
   AND intrinsicPortfolioValue < 600000.00;
SET @aether_phase3_status_ok := (
  SELECT COUNT(*) = 9 FROM tblCharacterShareStatus
  WHERE shareLevel = 0 AND usedStatusPoints = 0 AND personnelRequirement = 0
);
INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0009_share_status_initialization', 'Set legacy holders below Fr 600000 to level zero'
 WHERE @aether_phase3_safe = 1 AND @aether_phase3_status_ok = 1
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);
COMMIT;

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCharacterShareStatus WHERE shareLevel = 0
          AND usedStatusPoints = 0 AND personnelRequirement = 0) AS initializedHolders,
       (SELECT COUNT(*) FROM tblCharacterShareStatus WHERE shareLevel IS NULL
          OR usedStatusPoints IS NULL OR personnelRequirement IS NULL) AS unresolvedHolders,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0009_share_status_initialization') AS migrationPresent;

-- 0010: een gerichte verkoop uit reeds bezeten aandelen is een ander type
-- aanbod dan een bestuursaanbod uit vrije bedrijfsaandelen.
SET @aether_phase3_sale_safe := DATABASE() = 'oneiros_be_aether'
  AND (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0009_share_status_initialization') = 1
  AND (SELECT COUNT(*) FROM tblCharacterShareStatus
         WHERE shareLevel IS NULL OR usedStatusPoints IS NULL OR personnelRequirement IS NULL) = 0;
SET @aether_phase3_sale_sql := IF(@aether_phase3_sale_safe,
  'ALTER TABLE tblShareOffer MODIFY COLUMN sourceCode ENUM(''search'',''shared_search'',''private_board'',''director_grant'',''holder_sale'') NOT NULL',
  'SELECT * FROM __AETHER_BLOCK_PHASE3_SALE_PREFLIGHT_FAILED__');
PREPARE aether_phase3_sale FROM @aether_phase3_sale_sql;
EXECUTE aether_phase3_sale;
DEALLOCATE PREPARE aether_phase3_sale;

SET @aether_holder_sale_supported := 0;
START TRANSACTION;
INSERT INTO tblShareOffer (idCompany, sourceCode, offeredShareCount, unitPrice)
SELECT MIN(id), 'holder_sale', 1, 1.00 FROM tblCompany
HAVING @aether_phase3_sale_safe = 1 AND MIN(id) IS NOT NULL;
SET @aether_holder_sale_probe_id := LAST_INSERT_ID();
SET @aether_holder_sale_supported := (
  SELECT COUNT(*) = 1 FROM tblShareOffer
  WHERE id = @aether_holder_sale_probe_id AND sourceCode = 'holder_sale'
);
ROLLBACK;

INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0010_holder_sale_offer', 'Allow a targeted sale of owned shares'
 WHERE @aether_phase3_sale_safe = 1 AND @aether_holder_sale_supported = 1
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCharacterShareStatus WHERE shareLevel = 0
          AND usedStatusPoints = 0 AND personnelRequirement = 0) AS initializedHolders,
       (SELECT COUNT(*) FROM tblCharacterShareStatus WHERE shareLevel IS NULL
          OR usedStatusPoints IS NULL OR personnelRequirement IS NULL) AS unresolvedHolders,
       (SELECT COUNT(*) FROM tblSchemaMigration WHERE migrationCode IN
         ('0006_share_reform_schema', '0007_share_reform_backfill', '0008_share_domain_codes',
          '0009_share_status_initialization', '0010_holder_sale_offer')) AS phaseMigrations;

-- Fase 5. Eenmalig uitvoeren op oneiros_be_aether na een back-up en 0006-0010.
-- Controleer eerst de gekozen databank met inspect_0011_phase5_preflight_readonly.sql.
-- Fout #1146 op __AETHER_BLOCK_PHASE5_PREFLIGHT_FAILED__ betekent dat de
-- veiligheidscontrole is mislukt; er zijn dan nog geen schemawijzigingen uitgevoerd.
-- Historische Bedrijfsleider-traits blijven bestaan; gekoppelde personeelsrijen krijgen de leidersrol.

SET @aether_phase5_ready := DATABASE() = 'oneiros_be_aether'
  AND (SELECT COUNT(*) FROM tblSchemaMigration WHERE migrationCode IN
       ('0006_share_reform_schema', '0007_share_reform_backfill',
        '0008_share_domain_codes', '0009_share_status_initialization',
        '0010_holder_sale_offer')) = 5
  AND (SELECT COUNT(*) FROM tblSchemaMigration
       WHERE migrationCode = '0011_company_leadership') = 0;
SET @aether_phase5_guard := IF(@aether_phase5_ready,
  'SELECT ''FASE 5 PREFLIGHT GESLAAGD'' AS result',
  'SELECT * FROM __AETHER_BLOCK_PHASE5_PREFLIGHT_FAILED__');
PREPARE aether_phase5_check FROM @aether_phase5_guard;
EXECUTE aether_phase5_check;
DEALLOCATE PREPARE aether_phase5_check;

-- Koppel een oude Bedrijfsleider alleen automatisch als de persoon precies bij
-- één bedrijf werkt en dat bedrijf maar één dergelijke kandidaat heeft.
DROP TEMPORARY TABLE IF EXISTS aether_phase5_legacy_leaders;
CREATE TEMPORARY TABLE aether_phase5_legacy_leaders AS
SELECT cp.id AS idCompanyPersonnel
FROM tblCompanyPersonnel AS cp
JOIN (
  SELECT lct.idCharacter
    FROM tblLinkCharacterTrait AS lct
    JOIN tblTrait AS t ON t.id = lct.idTrait
   WHERE t.`class` = 'middle class' AND t.`type` = 'profession'
     AND t.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
   GROUP BY lct.idCharacter
) AS legacy ON legacy.idCharacter = cp.idCharacter
;

ALTER TABLE tblCompanyPersonnel
  ADD COLUMN isCompanyLeader TINYINT(1) NOT NULL DEFAULT 0 AFTER isBoardMember,
  ADD KEY idx_company_personnel_leader (idCompany, isCompanyLeader, idCharacter);

INSERT INTO tblShareDomainCode (code, entityType, entityId)
SELECT CASE t.name WHEN 'Bedrijfsleider klein' THEN 'company_leader_legacy_small'
                   ELSE 'company_leader_legacy_large' END,
       'trait', t.id
  FROM tblTrait AS t
 WHERE t.`class` = 'middle class' AND t.`type` = 'profession'
   AND t.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
   AND NOT EXISTS (SELECT 1 FROM tblShareDomainCode AS dc
                    WHERE dc.entityType = 'trait' AND dc.entityId = t.id);

UPDATE tblCompanyPersonnel AS cp
JOIN aether_phase5_legacy_leaders AS candidate
  ON candidate.idCompanyPersonnel = cp.id
   SET cp.isCompanyLeader = 1, cp.isBoardMember = 1,
       cp.importance = 'Negligible';
DROP TEMPORARY TABLE aether_phase5_legacy_leaders;

-- Bewaar vaardigheden en specialisaties van bedrijfsleiders: zij bepalen het bedrijfsresultaat.

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0011_company_leadership',
        'Backfill linked company leaders, allowing multiple leaders and preserving skills');

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCompanyPersonnel WHERE isCompanyLeader = 1) AS mappedLeaders,
       (SELECT COUNT(*) FROM tblCompanyPersonnel WHERE isCompanyLeader = 1
         AND (isBoardMember <> 1 OR importance <> 'Negligible')) AS invalidLeaders,
       (SELECT COUNT(*) FROM (
          SELECT idCompany FROM tblCompanyPersonnel WHERE isCompanyLeader = 1
          GROUP BY idCompany HAVING COUNT(*) > 1
       ) AS duplicates) AS companiesWithMultipleLeaders,
       (SELECT COUNT(DISTINCT lct.idCharacter)
          FROM tblLinkCharacterTrait AS lct
          JOIN tblTrait AS t ON t.id = lct.idTrait
         WHERE t.`class` = 'middle class' AND t.`type` = 'profession'
           AND t.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
           AND NOT EXISTS (SELECT 1 FROM tblCompanyPersonnel AS cp
                            WHERE cp.idCharacter = lct.idCharacter
                              AND cp.isCompanyLeader = 1)) AS unmappedLegacyLeaders,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0011_company_leadership') AS migrationPresent;

-- Fase 8: oprichting en volledige aandelenstand per bedrijfssnapshot.
-- Voer eenmaal uit op oneiros_be_aether, na een back-up en na migratie 0011.
-- Oude event-snapshots krijgen NULL voor destijds niet bewaarde aantallen.
-- Een NULL betekent 'historisch onbekend', niet nul.

SET @aether_0012_ready := DATABASE() = 'oneiros_be_aether'
  AND (SELECT COUNT(*) FROM tblSchemaMigration WHERE migrationCode = '0011_company_leadership') = 1
  AND (SELECT COUNT(*) FROM tblSchemaMigration WHERE migrationCode = '0012_company_snapshot_history') = 0;
SET @aether_0012_guard := IF(@aether_0012_ready,
  'SELECT ''FASE 8 PREFLIGHT GESLAAGD'' AS result',
  'SELECT * FROM __AETHER_BLOCK_0012_PREFLIGHT_FAILED__');
PREPARE aether_0012_check FROM @aether_0012_guard;
EXECUTE aether_0012_check;
DEALLOCATE PREPARE aether_0012_check;

CREATE TABLE IF NOT EXISTS tblCompanyFoundationSnapshot (
  idCompany INT UNSIGNED NOT NULL PRIMARY KEY,
  foundationDate DATE NULL,
  initialCompanyValue DECIMAL(18,2) NULL,
  initialShareCount INT UNSIGNED NULL,
  shareMarketPrice DECIMAL(18,2) NULL,
  anonymousShareCount INT UNSIGNED NULL,
  stability TINYINT NULL,
  profitability TINYINT NULL,
  sourceCode VARCHAR(20) NOT NULL DEFAULT 'recorded',
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_company_foundation_company FOREIGN KEY (idCompany)
    REFERENCES tblCompany(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tblCompanySnapshot
  ADD COLUMN IF NOT EXISTS initialCompanyValue DECIMAL(18,2) NULL AFTER shareMarketPrice,
  ADD COLUMN IF NOT EXISTS initialShareCount INT UNSIGNED NULL AFTER initialCompanyValue,
  ADD COLUMN IF NOT EXISTS issuedShareCount INT UNSIGNED NULL AFTER initialShareCount,
  ADD COLUMN IF NOT EXISTS anonymousShareCount INT UNSIGNED NULL AFTER issuedShareCount;

INSERT IGNORE INTO tblCompanyFoundationSnapshot
  (idCompany, foundationDate, initialCompanyValue, initialShareCount, sourceCode)
SELECT id, foundationDate, initialCompanyValue, initialShareCount, 'reconstructed'
FROM tblCompany;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0012_company_snapshot_history',
        'Record foundation and per-event company/share snapshot parameters');

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCompany) AS companies,
       (SELECT COUNT(*) FROM tblCompanyFoundationSnapshot) AS foundationSnapshots,
       (SELECT COUNT(*) FROM tblCompanySnapshot) AS eventSnapshots,
       (SELECT COUNT(*) FROM tblCompanySnapshot
         WHERE issuedShareCount IS NULL OR anonymousShareCount IS NULL) AS historicalSnapshotsWithUnknownStock,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0012_company_snapshot_history') AS migrationPresent;

-- Functiebeschrijving voor bedrijfspersoneel. Voer uit vóór de nieuwe PHP-code.
-- Productie: voer uit na migratie 0012.

SET @aether_0013_ready := (SELECT COUNT(*) FROM tblSchemaMigration
  WHERE migrationCode = '0012_company_snapshot_history') = 1
  AND (SELECT COUNT(*) FROM tblSchemaMigration
    WHERE migrationCode = '0013_company_personnel_function_description') = 0;
SET @aether_0013_guard := IF(@aether_0013_ready,
  'SELECT ''MIGRATIE 0013 PREFLIGHT GESLAAGD'' AS result',
  'SELECT * FROM __AETHER_BLOCK_0013_PREFLIGHT_FAILED__');
PREPARE aether_0013_check FROM @aether_0013_guard;
EXECUTE aether_0013_check;
DEALLOCATE PREPARE aether_0013_check;

ALTER TABLE tblCompanyPersonnel
  ADD COLUMN IF NOT EXISTS functionDescription TEXT NULL AFTER importance;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0013_company_personnel_function_description',
        'Store each company employee function description');

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0013_company_personnel_function_description') AS migrationPresent,
       'Controleer functionDescription met SHOW COLUMNS' AS columnCheck;
SHOW COLUMNS FROM tblCompanyPersonnel LIKE 'functionDescription';

-- Eindcontrole: alle migraties, voorraad en statussen moeten kloppen.
SELECT DATABASE() AS databaseName,
       COUNT(*) AS completedMigrations,
       GROUP_CONCAT(migrationCode ORDER BY migrationCode SEPARATOR ', ') AS migrationCodes
FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill',
  '0008_share_domain_codes', '0009_share_status_initialization',
  '0010_holder_sale_offer', '0011_company_leadership',
  '0012_company_snapshot_history', '0013_company_personnel_function_description');
SELECT c.id, c.companyName, c.initialShareCount, c.issuedShareCount,
       COALESCE(h.heldShares, 0) AS heldShares,
       COALESCE(a.shareCount, 0) AS anonymousShares,
       c.issuedShareCount - COALESCE(h.heldShares, 0) - COALESCE(a.shareCount, 0) AS freeShares
FROM tblCompany AS c
LEFT JOIN (SELECT idCompany, SUM(shareCount) AS heldShares
           FROM tblCompanyShareHolding GROUP BY idCompany) AS h ON h.idCompany = c.id
LEFT JOIN tblCompanyAnonymousShares AS a ON a.idCompany = c.id
ORDER BY c.id;
SELECT COUNT(*) AS unresolvedShareStatuses FROM tblCharacterShareStatus
WHERE shareLevel IS NULL OR usedStatusPoints IS NULL OR personnelRequirement IS NULL;
SELECT COUNT(*) AS foundationRows FROM tblCompanyFoundationSnapshot;
