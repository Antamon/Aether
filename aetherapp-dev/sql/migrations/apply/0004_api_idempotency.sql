-- MariaDB 10.11. DDL kan impliciet committen.
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

START TRANSACTION;
DELETE FROM tblApiIdempotency
WHERE operation IN ('__migration_0004_probe__', '__migration_0004_probe_other__')
  AND requestKey = '0004-structure-probe-only';
INSERT INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, responseStatus, responseJson, expiresAt)
VALUES
  (0, '__migration_0004_probe__', '0004-structure-probe-only', REPEAT('0', 64),
   'completed', 200, '{"probe":true}', DATE_ADD(NOW(), INTERVAL 1 DAY));
INSERT INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
VALUES
  (1, '__migration_0004_probe__', '0004-structure-probe-only', REPEAT('1', 64),
   'processing', DATE_ADD(NOW(), INTERVAL 1 DAY)),
  (0, '__migration_0004_probe_other__', '0004-structure-probe-only', REPEAT('2', 64),
   'processing', DATE_ADD(NOW(), INTERVAL 1 DAY));
INSERT IGNORE INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
VALUES
  (0, '__migration_0004_probe__', '0004-structure-probe-only', REPEAT('1', 64),
   'processing', DATE_ADD(NOW(), INTERVAL 1 DAY));
SET @aether_0004_probe_count := (
  SELECT COUNT(*) FROM tblApiIdempotency
  WHERE operation IN ('__migration_0004_probe__', '__migration_0004_probe_other__')
    AND requestKey = '0004-structure-probe-only'
);
DELETE FROM tblApiIdempotency
WHERE operation IN ('__migration_0004_probe__', '__migration_0004_probe_other__')
  AND requestKey = '0004-structure-probe-only';
COMMIT;
SET @aether_0004_guard_sql := IF(
  @aether_0004_probe_count = 3,
  'SELECT 1',
  'SELECT * FROM __AETHER_BLOCK_0004_WRONG_UNIQUE_DEFINITION__'
);
PREPARE aether_0004_guard FROM @aether_0004_guard_sql;
EXECUTE aether_0004_guard;
DEALLOCATE PREPARE aether_0004_guard;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0004_create_api_idempotency', 'Create transactional API idempotency registry')
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);
