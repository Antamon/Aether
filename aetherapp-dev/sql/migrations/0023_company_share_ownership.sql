-- Uitvoeren op beide databases vóór de nieuwe bedrijfsbezitfunctie wordt gebruikt.
-- Bestaand aandelenbezit en bedrijfswaarden worden niet gewijzigd.
CREATE TABLE IF NOT EXISTS tblCompanyShareCompanyHolding (
  idCompany INT UNSIGNED NOT NULL,
  idHoldingCompany INT UNSIGNED NOT NULL,
  shareCount INT UNSIGNED NOT NULL,
  PRIMARY KEY (idCompany, idHoldingCompany),
  KEY idx_company_holding_owner (idHoldingCompany),
  CONSTRAINT fk_company_holding_issuer FOREIGN KEY (idCompany) REFERENCES tblCompany(id),
  CONSTRAINT fk_company_holding_owner FOREIGN KEY (idHoldingCompany) REFERENCES tblCompany(id),
  CONSTRAINT chk_company_holding_count CHECK (shareCount > 0),
  CONSTRAINT chk_company_holding_not_self CHECK (idCompany <> idHoldingCompany)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCompanyShareCompanyTransaction (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idCompany INT UNSIGNED NOT NULL,
  idHoldingCompany INT UNSIGNED NOT NULL,
  actorUserId INT NOT NULL,
  shareCount INT UNSIGNED NOT NULL,
  purchaseYear SMALLINT UNSIGNED NOT NULL,
  purchaseQuarter TINYINT UNSIGNED NOT NULL,
  issuerValueBefore DECIMAL(18,2) NOT NULL,
  issuerValueAfter DECIMAL(18,2) NOT NULL,
  holderValueBefore DECIMAL(18,2) NOT NULL,
  holderValueAfter DECIMAL(18,2) NOT NULL,
  requestKey VARCHAR(128) NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_share_request (actorUserId, requestKey),
  KEY idx_company_share_history (idCompany, idHoldingCompany, createdAt),
  CONSTRAINT fk_company_share_tx_issuer FOREIGN KEY (idCompany) REFERENCES tblCompany(id),
  CONSTRAINT fk_company_share_tx_owner FOREIGN KEY (idHoldingCompany) REFERENCES tblCompany(id),
  CONSTRAINT fk_company_share_tx_actor FOREIGN KEY (actorUserId) REFERENCES tblUser(id),
  CONSTRAINT chk_company_share_tx_count CHECK (shareCount > 0),
  CONSTRAINT chk_company_share_tx_quarter CHECK (purchaseQuarter BETWEEN 1 AND 4),
  CONSTRAINT chk_company_share_tx_values CHECK (issuerValueBefore = issuerValueAfter AND holderValueBefore = holderValueAfter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCompanySnapshotCompanyPayout (
  idCompanySnapshot INT UNSIGNED NOT NULL,
  idHoldingCompany INT UNSIGNED NOT NULL,
  shareCount INT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idCompanySnapshot, idHoldingCompany),
  CONSTRAINT fk_company_payout_snapshot FOREIGN KEY (idCompanySnapshot) REFERENCES tblCompanySnapshot(id),
  CONSTRAINT fk_company_payout_owner FOREIGN KEY (idHoldingCompany) REFERENCES tblCompany(id),
  CONSTRAINT chk_company_payout_amount CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0023_company_share_ownership', 'Company shareholders and value-neutral share assignments');
