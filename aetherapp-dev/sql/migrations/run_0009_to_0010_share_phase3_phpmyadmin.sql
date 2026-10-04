-- Fase 3, alleen op oneiros_beaetherdev na een back-up en succesvolle 0006-0008.
-- Historische negen portefeuilles zijn volgens de gecontroleerde preflight allemaal < Fr 600.000.
-- Voer als volledig bestand in phpMyAdmin uit; eindresultaat toont aantallen en migratiestatus.

SET @aether_phase3_ready := (
  SELECT COUNT(*) = 3 FROM tblSchemaMigration
  WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill', '0008_share_domain_codes')
);
SET @aether_phase3_safe := @aether_phase3_ready
  AND DATABASE() = 'oneiros_beaetherdev'
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
SET @aether_phase3_sale_safe := DATABASE() = 'oneiros_beaetherdev'
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
