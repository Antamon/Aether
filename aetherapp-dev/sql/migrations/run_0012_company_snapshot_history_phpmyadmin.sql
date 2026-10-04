-- Fase 8: oprichting en volledige aandelenstand per bedrijfssnapshot.
-- Voer eenmaal uit op oneiros_beaetherdev, na een back-up en na migratie 0011.
-- Oude event-snapshots krijgen NULL voor destijds niet bewaarde aantallen.
-- Een NULL betekent 'historisch onbekend', niet nul.

SET @aether_0012_ready := DATABASE() = 'oneiros_beaetherdev'
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
