-- Aether migratie 0005 voor MariaDB 10.11 / phpMyAdmin.
-- Geen SOURCE, stored procedure, information_schema of automatische data-opruiming.
-- DDL kan impliciet committen; dit bestand claimt geen transactionele DDL-rollback.

SET @aether_prerequisite_count := (
  SELECT COUNT(*)
  FROM tblSchemaMigration
  WHERE migrationCode IN (
    '0001_create_schema_migration_registry',
    '0002_unique_character_skill',
    '0003_unique_character_specialisation',
    '0004_create_api_idempotency'
  )
);
SET @aether_guard_sql := IF(
  @aether_prerequisite_count = 4,
  'SELECT ''Preflight: migraties 0001-0004 zijn geregistreerd.'' AS preflightResult',
  'SELECT * FROM __AETHER_BLOCK_0005_MISSING_PREREQUISITES__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

SET @aether_duplicate_count := (
  SELECT COUNT(*) FROM (
    SELECT idEvent, idUser
    FROM tblLinkEventUser
    GROUP BY idEvent, idUser
    HAVING COUNT(*) > 1
  ) AS duplicate_event_users
);
SET @aether_guard_sql := IF(
  @aether_duplicate_count = 0,
  'SELECT ''Preflight: geen dubbele eventdeelnames.'' AS preflightResult',
  'SELECT * FROM __AETHER_BLOCK_0005_DUPLICATE_EVENT_USERS__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

SET @aether_null_count := (
  SELECT COUNT(*) FROM tblLinkEventUser WHERE idEvent IS NULL OR idUser IS NULL
);
SET @aether_guard_sql := IF(
  @aether_null_count = 0,
  'SELECT ''Preflight: geen NULL-sleutels in eventdeelnames.'' AS preflightResult',
  'SELECT * FROM __AETHER_BLOCK_0005_NULL_EVENT_USER_KEYS__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

SET @aether_orphan_count := (
  SELECT COUNT(*)
  FROM tblLinkEventUser AS leu
  LEFT JOIN tblEvent AS e ON e.id = leu.idEvent
  LEFT JOIN tblUser AS u ON u.id = leu.idUser
  WHERE e.id IS NULL OR u.id IS NULL
);
-- Verweesde koppelingen verhinderen de unieke event/userindex niet.
-- Bewaar en rapporteer ze; herstel van historische deelnames vereist een afzonderlijke beslissing.
SELECT CASE
         WHEN @aether_orphan_count = 0 THEN 'Preflight: geen verweesde eventdeelnames.'
         ELSE CONCAT('WAARSCHUWING: ', @aether_orphan_count,
                     ' verweesde eventdeelnames; ongewijzigd gelaten.')
       END AS orphanWarning;

ALTER TABLE tblLinkEventUser
  ADD UNIQUE INDEX IF NOT EXISTS uq_tblLinkEventUser_event_user (idEvent, idUser),
  ADD INDEX IF NOT EXISTS idx_tblLinkEventUser_user_event (idUser, idEvent);

-- Bewijs de unieke definitie met twee identieke inserts op bestaande geldige sleutels.
-- ROLLBACK herstelt exact de beginsituatie, ook wanneer de eerste probe-insert nodig was.
SET @aether_probe_event := (SELECT MIN(id) FROM tblEvent);
SET @aether_probe_user := (SELECT MIN(id) FROM tblUser);
SET @aether_guard_sql := IF(
  @aether_probe_event IS NOT NULL AND @aether_probe_user IS NOT NULL,
  'SELECT ''Structuurcontrole: geldige event- en gebruikerssleutel gevonden.'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0005_NO_PROBE_KEYS__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

START TRANSACTION;
INSERT IGNORE INTO tblLinkEventUser (idEvent, idUser)
VALUES (@aether_probe_event, @aether_probe_user);
SET @aether_probe_count_after_first := (
  SELECT COUNT(*) FROM tblLinkEventUser
  WHERE idEvent = @aether_probe_event AND idUser = @aether_probe_user
);
INSERT IGNORE INTO tblLinkEventUser (idEvent, idUser)
VALUES (@aether_probe_event, @aether_probe_user);
SET @aether_probe_count_after_second := (
  SELECT COUNT(*) FROM tblLinkEventUser
  WHERE idEvent = @aether_probe_event AND idUser = @aether_probe_user
);
ROLLBACK;

SET @aether_guard_sql := IF(
  @aether_probe_count_after_first = 1 AND @aether_probe_count_after_second = 1,
  'SELECT ''Structuurcontrole: unieke event-userindex werkt.'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0005_WRONG_UNIQUE_DEFINITION__'
);
PREPARE aether_guard FROM @aether_guard_sql;
EXECUTE aether_guard;
DEALLOCATE PREPARE aether_guard;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0005_unique_event_user', 'Enforce one event participation per user')
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SELECT 'Migratie 0005 is geslaagd.' AS migrationResult;
SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode = '0005_unique_event_user';
SHOW INDEX FROM tblLinkEventUser;

SELECT idEvent, idUser, COUNT(*) AS duplicateCount
FROM tblLinkEventUser
GROUP BY idEvent, idUser
HAVING COUNT(*) > 1;

SELECT leu.id, leu.idEvent, leu.idUser
FROM tblLinkEventUser AS leu
LEFT JOIN tblEvent AS e ON e.id = leu.idEvent
LEFT JOIN tblUser AS u ON u.id = leu.idUser
WHERE e.id IS NULL OR u.id IS NULL;
