-- Functiebeschrijving voor bedrijfspersoneel. Voer uit vóór de nieuwe PHP-code.
-- Bruikbaar op de dev- en productiedatabase nadat migratie 0012 is geregistreerd.

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

SET @aether_0013_column_exists := (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'tblCompanyPersonnel'
    AND column_name = 'functionDescription');
SET @aether_0013_alter_sql := IF(@aether_0013_column_exists = 0,
  'ALTER TABLE tblCompanyPersonnel ADD COLUMN functionDescription TEXT NULL AFTER importance',
  'SELECT ''Kolom functionDescription bestaat al'' AS result');
PREPARE aether_0013_alter FROM @aether_0013_alter_sql;
EXECUTE aether_0013_alter;
DEALLOCATE PREPARE aether_0013_alter;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0013_company_personnel_function_description',
        'Store each company employee function description');

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0013_company_personnel_function_description') AS migrationPresent,
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'tblCompanyPersonnel'
           AND column_name = 'functionDescription') AS columnPresent;
