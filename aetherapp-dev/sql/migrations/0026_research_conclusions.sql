-- Na 0024 en 0025 uitvoeren op beide databases, vóór de nieuwe code.
ALTER TABLE tblResearchDiscovery
 ADD COLUMN IF NOT EXISTS isConclusion TINYINT NOT NULL DEFAULT 0;
ALTER TABLE tblResearchDiscovery
 MODIFY COLUMN qrToken CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL;
INSERT IGNORE INTO tblSchemaMigration(migrationCode,description)
VALUES ('0026_research_conclusions','Conclusions unlocked by individual challenge completion, without QR codes');
