-- Uitvoeren op beide databases vóór de bedrijfsinformatie in de economieactie wordt gebruikt.
-- Per personage, snapshot en bedrijfsgebeurtenis wordt één uitslag bewaard.
CREATE TABLE IF NOT EXISTS tblCharacterCompanyResearch (
    idCharacter INT NOT NULL,
    idSnapshot INT UNSIGNED NOT NULL,
    idCompanyQuarterEvent BIGINT UNSIGNED NOT NULL,
    effectiveSkillLevel TINYINT UNSIGNED NOT NULL,
    chancePercent TINYINT UNSIGNED NOT NULL,
    discoveryRoll TINYINT UNSIGNED NULL,
    isKnown TINYINT(1) NOT NULL DEFAULT 0,
    isManual TINYINT(1) NOT NULL DEFAULT 0,
    updatedAt TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idCharacter, idSnapshot, idCompanyQuarterEvent),
    CONSTRAINT fk_research_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter (id) ON DELETE CASCADE,
    CONSTRAINT fk_research_snapshot FOREIGN KEY (idSnapshot) REFERENCES tblCompanySnapshot (id) ON DELETE CASCADE,
    CONSTRAINT fk_research_quarter_event FOREIGN KEY (idCompanyQuarterEvent) REFERENCES tblCompanyQuarterEvent (id) ON DELETE CASCADE,
    CONSTRAINT chk_research_level CHECK (effectiveSkillLevel BETWEEN 1 AND 4),
    CONSTRAINT chk_research_chance CHECK (chancePercent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0022_company_research', 'Persistent company event knowledge by character and snapshot');
