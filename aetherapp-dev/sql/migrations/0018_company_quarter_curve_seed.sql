-- Uitvoeren op beide databases na 0016, vóór de aangepaste kwartaalcode.
-- Een bewaard kwartaal krijgt een nieuwe willekeurige sleutel voor de
-- gegenereerde curve tot de volgende eventsnapshot. Bestaande curves blijven
-- bij sleutel 0 behouden totdat een kwartaal opnieuw wordt bewaard.
ALTER TABLE tblCompanyQuarter
  ADD COLUMN IF NOT EXISTS curveSeed INT UNSIGNED NOT NULL DEFAULT 0 AFTER isManual;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0018_company_quarter_curve_seed', 'Stable random curve after each saved company quarter');
