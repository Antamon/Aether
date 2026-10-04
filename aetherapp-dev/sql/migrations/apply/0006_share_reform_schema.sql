-- Fase 2 / 0006: uitsluitend additieve structuur voor het nieuwe aandelendomein.
-- Eerst preflight/0006 uitvoeren. DDL commit impliciet in MariaDB.
-- Niet op productie uitvoeren; oude runtime schrijft nog procentuele aandelen.

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
