-- Aether migratie 0004 voor MariaDB 10.11 / phpMyAdmin.
-- Geen information_schema, SOURCE, stored procedure of automatische applicatiedata-opruiming.
-- DDL kan impliciet committen; dit script claimt geen transactionele DDL-rollback.

SET @aether_prerequisite_count := (
  SELECT COUNT(*)
  FROM tblSchemaMigration
  WHERE migrationCode IN (
    '0001_create_schema_migration_registry',
    '0002_unique_character_skill',
    '0003_unique_character_specialisation'
  )
);
SET @aether_guard_sql := IF(
  @aether_prerequisite_count = 3,
  'SELECT ''Preflight: migraties 0001-0003 zijn geregistreerd.'' AS preflightResult',
  'SELECT * FROM __AETHER_BLOCK_0004_MISSING_PREREQUISITES__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

CREATE TABLE IF NOT EXISTS tblApiIdempotency (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  idUser int NOT NULL,
  operation varchar(100) NOT NULL,
  requestKey varchar(128) NOT NULL,
  payloadHash char(64) NOT NULL,
  status enum('processing','completed') NOT NULL DEFAULT 'processing',
  responseStatus smallint unsigned DEFAULT NULL,
  responseJson mediumtext DEFAULT NULL,
  createdAt datetime NOT NULL DEFAULT current_timestamp(),
  updatedAt datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  expiresAt datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tblApiIdempotency_user_operation_key (idUser, operation, requestKey),
  KEY idx_tblApiIdempotency_expiresAt (expiresAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uitvoerbare structuurcontrole zonder information_schema. De probe wordt binnen
-- dezelfde transactie verwijderd en raakt geen bestaande applicatierijen.
START TRANSACTION;
DELETE FROM tblApiIdempotency
WHERE operation IN ('__migration_0004_probe__', '__migration_0004_probe_other__')
  AND requestKey = '0004-structure-probe-only';
INSERT INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, responseStatus, responseJson, expiresAt)
VALUES
  (0, '__migration_0004_probe__', '0004-structure-probe-only',
   REPEAT('0', 64), 'completed', 200, '{"probe":true}', DATE_ADD(NOW(), INTERVAL 1 DAY));
INSERT INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
VALUES
  (1, '__migration_0004_probe__', '0004-structure-probe-only',
   REPEAT('1', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY)),
  (0, '__migration_0004_probe_other__', '0004-structure-probe-only',
   REPEAT('2', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY));
INSERT IGNORE INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
VALUES
  (0, '__migration_0004_probe__', '0004-structure-probe-only',
   REPEAT('1', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY));
SET @aether_probe_count := (
  SELECT COUNT(*) FROM tblApiIdempotency
  WHERE operation IN ('__migration_0004_probe__', '__migration_0004_probe_other__')
    AND requestKey = '0004-structure-probe-only'
);
DELETE FROM tblApiIdempotency
WHERE operation IN ('__migration_0004_probe__', '__migration_0004_probe_other__')
  AND requestKey = '0004-structure-probe-only';
COMMIT;

SET @aether_guard_sql := IF(
  @aether_probe_count = 3,
  'SELECT ''Structuurcontrole: unieke idempotentiesleutel werkt.'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0004_WRONG_UNIQUE_DEFINITION__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0004_create_api_idempotency', 'Create transactional API idempotency registry')
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SELECT 'Migratie 0004 is geslaagd.' AS migrationResult;
SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode = '0004_create_api_idempotency';
SHOW CREATE TABLE tblApiIdempotency;
SHOW INDEX FROM tblApiIdempotency;
