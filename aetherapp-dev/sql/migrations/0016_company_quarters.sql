-- Uitvoeren op zowel de ontwikkel- als productiedatabank na migratie 0012.
-- Alleen handmatige correcties worden opgeslagen; de tijdlijn vult ontbrekende
-- kwartalen voorspelbaar aan en geeft bestaande snapshots altijd voorrang.
CREATE TABLE IF NOT EXISTS tblCompanyQuarter (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idCompany INT UNSIGNED NOT NULL,
    quarterYear SMALLINT NOT NULL,
    quarterNumber TINYINT UNSIGNED NOT NULL,
    companyValue DECIMAL(18,2) NOT NULL,
    issuedShareCount INT UNSIGNED NOT NULL,
    shareMarketPrice DECIMAL(18,2) NULL,
    stability TINYINT NOT NULL,
    profitability TINYINT NOT NULL,
    isManual TINYINT(1) NOT NULL DEFAULT 0,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_company_quarter (idCompany, quarterYear, quarterNumber),
    CONSTRAINT fk_company_quarter_company FOREIGN KEY (idCompany) REFERENCES tblCompany (id) ON DELETE CASCADE,
    CONSTRAINT chk_company_quarter_number CHECK (quarterNumber BETWEEN 1 AND 4),
    CONSTRAINT chk_company_quarter_stability CHECK (stability BETWEEN -7 AND 7),
    CONSTRAINT chk_company_quarter_profitability CHECK (profitability BETWEEN -7 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tblCompanyQuarterEvent (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idCompanyQuarter BIGINT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    publicity TINYINT UNSIGNED NOT NULL,
    CONSTRAINT fk_company_quarter_event_quarter FOREIGN KEY (idCompanyQuarter) REFERENCES tblCompanyQuarter (id) ON DELETE CASCADE,
    CONSTRAINT chk_company_quarter_event_publicity CHECK (publicity BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0016_company_quarters', 'Quarterly company evolution and notable events');
