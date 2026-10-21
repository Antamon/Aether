-- Uitvoeren op beide databases na migratie 0012 en voor de aangepaste
-- snapshotcode wordt gebruikt. Een oude herinvestering verhoogde alleen
-- tblCompany.companyValue; deze migratie brengt de snapshotwaarde in lijn.
ALTER TABLE tblCompanySnapshot
  ADD COLUMN IF NOT EXISTS preReinvestmentCompanyValue DECIMAL(18,2) NULL AFTER companyValue;

UPDATE tblCompanySnapshot
   SET preReinvestmentCompanyValue = companyValue,
       companyValue = companyValue + COALESCE(companyValueDelta, 0)
 WHERE appliedAction = 'reinvest'
   AND preReinvestmentCompanyValue IS NULL;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0017_reinvested_snapshot_value', 'Keep reinvested profit in snapshot company value');
