-- Aether production -> dev structure, based on the two exports of 24 September 2026.
-- MariaDB 10.11 / phpMyAdmin. Select the production database explicitly.
-- BACK UP THE DATABASE AND STOP WRITES before running this file.
-- No DROP, DELETE, UPDATE of application rows, or import of development data.
-- DDL commits implicitly. A failed run may leave verified earlier steps in place;
-- this file may then be rerun after the blocking condition is resolved.
-- Do not enable phpMyAdmin's "ignore errors" option.

SET @aether_target_ok := (SELECT DATABASE() = 'oneiros_be_aether');
SET @aether_duplicate_skill := (
  SELECT COUNT(*) FROM (
    SELECT idCharacter, idSkill FROM tblLinkCharacterSkill
    GROUP BY idCharacter, idSkill HAVING COUNT(*) > 1
  ) AS duplicates
);
SET @aether_null_skill := (
  SELECT COUNT(*) FROM tblLinkCharacterSkill
  WHERE idCharacter IS NULL OR idSkill IS NULL
);
SET @aether_duplicate_spec := (
  SELECT COUNT(*) FROM (
    SELECT idCharacter, idSkill, idSkillSpecialisation
    FROM tblCharacterSpecialisation
    GROUP BY idCharacter, idSkill, idSkillSpecialisation HAVING COUNT(*) > 1
  ) AS duplicates
);
SET @aether_null_spec := (
  SELECT COUNT(*) FROM tblCharacterSpecialisation
  WHERE idCharacter IS NULL OR idSkill IS NULL OR idSkillSpecialisation IS NULL
);
SET @aether_invalid_spec := (
  SELECT COUNT(*)
  FROM tblCharacterSpecialisation AS cs
  LEFT JOIN tblCharacter AS c ON c.id = cs.idCharacter
  LEFT JOIN tblSkill AS s ON s.id = cs.idSkill
  LEFT JOIN tblSkillSpecialisation AS ss ON ss.id = cs.idSkillSpecialisation
  WHERE c.id IS NULL OR s.id IS NULL OR ss.id IS NULL OR ss.idSkill <> cs.idSkill
);
SET @aether_duplicate_event_user := (
  SELECT COUNT(*) FROM (
    SELECT idEvent, idUser FROM tblLinkEventUser
    GROUP BY idEvent, idUser HAVING COUNT(*) > 1
  ) AS duplicates
);
SET @aether_null_event_user := (
  SELECT COUNT(*) FROM tblLinkEventUser
  WHERE idEvent IS NULL OR idUser IS NULL
);
SET @aether_orphan_skill := (
  SELECT COUNT(*) FROM tblLinkCharacterSkill AS lcs
  LEFT JOIN tblCharacter AS c ON c.id = lcs.idCharacter
  LEFT JOIN tblSkill AS s ON s.id = lcs.idSkill
  WHERE c.id IS NULL OR s.id IS NULL
);
SET @aether_orphan_event_user := (
  SELECT COUNT(*) FROM tblLinkEventUser AS leu
  LEFT JOIN tblEvent AS e ON e.id = leu.idEvent
  LEFT JOIN tblUser AS u ON u.id = leu.idUser
  WHERE e.id IS NULL OR u.id IS NULL
);
SET @aether_blockers := IF(@aether_target_ok, 0, 1)
  + @aether_duplicate_skill + @aether_null_skill
  + @aether_duplicate_spec + @aether_null_spec + @aether_invalid_spec
  + @aether_duplicate_event_user + @aether_null_event_user;

SELECT DATABASE() AS selectedDatabase,
       @aether_duplicate_skill AS duplicateCharacterSkills,
       @aether_null_skill AS nullCharacterSkillKeys,
       @aether_duplicate_spec AS duplicateCharacterSpecialisations,
       @aether_null_spec AS nullSpecialisationKeys,
       @aether_invalid_spec AS invalidSpecialisationLinks,
       @aether_duplicate_event_user AS duplicateEventUsers,
       @aether_null_event_user AS nullEventUserKeys,
       @aether_orphan_skill AS warningOrphanCharacterSkills,
       @aether_orphan_event_user AS warningOrphanEventUsers;

-- Every DDL statement below is itself gated, even if a phpMyAdmin client
-- incorrectly continues after a previous PREPARE error.
SET @aether_sql := IF(@aether_blockers = 0,
  'CREATE TABLE IF NOT EXISTS tblSchemaMigration (id int NOT NULL AUTO_INCREMENT, migrationCode varchar(120) NOT NULL, description varchar(255) NOT NULL, appliedAt datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (id), UNIQUE KEY uq_tblSchemaMigration_code (migrationCode)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT * FROM __AETHER_BLOCK_PRODUCTION_SCHEMA_PREFLIGHT__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;

-- Production export has no registry. On rerun this insert is idempotent.
INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0001_create_schema_migration_registry', 'Create the Aether schema migration registry'
WHERE @aether_blockers = 0
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SET @aether_sql := IF(@aether_blockers = 0,
  'ALTER TABLE tblLinkCharacterSkill ADD UNIQUE INDEX IF NOT EXISTS uq_tblLinkCharacterSkill_character_skill (idCharacter, idSkill)',
  'SELECT * FROM __AETHER_BLOCK_PRODUCTION_SCHEMA_PREFLIGHT__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;

-- Prove the pair is unique and each constituent key can vary independently.
-- The negative test IDs are checked for absence; all inserts are rolled back.
SET @aether_probe_char := -1000000000 - FLOOR(RAND() * 1000000);
SET @aether_probe_skill := -1000000000 - FLOOR(RAND() * 1000000);
SET @aether_probe_free := (
  SELECT COUNT(*) FROM tblLinkCharacterSkill
  WHERE idCharacter IN (@aether_probe_char, @aether_probe_char - 1)
     OR idSkill IN (@aether_probe_skill, @aether_probe_skill - 1)
);
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_free = 0,
  'SELECT ''Character-skill probe keys are free'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0002_PROBE_KEYS__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
SET @aether_blockers := @aether_blockers + IF(@aether_probe_free = 0, 0, 1);
START TRANSACTION;
INSERT INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
SELECT @aether_probe_char, @aether_probe_skill, 1 WHERE @aether_blockers = 0 AND @aether_probe_free = 0;
INSERT IGNORE INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
SELECT @aether_probe_char, @aether_probe_skill, 1 WHERE @aether_blockers = 0 AND @aether_probe_free = 0;
INSERT IGNORE INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
SELECT @aether_probe_char, @aether_probe_skill - 1, 1 WHERE @aether_blockers = 0 AND @aether_probe_free = 0;
INSERT IGNORE INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
SELECT @aether_probe_char - 1, @aether_probe_skill, 1 WHERE @aether_blockers = 0 AND @aether_probe_free = 0;
SET @aether_probe_0002 := (
  SELECT COUNT(*) FROM tblLinkCharacterSkill
  WHERE idCharacter IN (@aether_probe_char, @aether_probe_char - 1)
    AND idSkill IN (@aether_probe_skill, @aether_probe_skill - 1)
);
ROLLBACK;
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0002 = 3,
  'SELECT ''Character-skill uniqueness verified'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0002_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0002_unique_character_skill', 'Enforce one link per character and skill'
WHERE @aether_blockers = 0 AND @aether_probe_0002 = 3
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0002 = 3,
  'ALTER TABLE tblCharacterSpecialisation ADD UNIQUE INDEX IF NOT EXISTS uq_tblCharacterSpecialisation_character_skill_specialisation (idCharacter, idSkill, idSkillSpecialisation)',
  'SELECT * FROM __AETHER_BLOCK_0002_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;

-- Use an existing valid tuple or a valid character + specialisation pair.
SET @aether_spec_character := COALESCE(
  (SELECT idCharacter FROM tblCharacterSpecialisation LIMIT 1),
  (SELECT MIN(id) FROM tblCharacter)
);
SET @aether_spec_id := COALESCE(
  (SELECT idSkillSpecialisation FROM tblCharacterSpecialisation LIMIT 1),
  (SELECT MIN(id) FROM tblSkillSpecialisation)
);
SET @aether_spec_skill := (SELECT idSkill FROM tblSkillSpecialisation WHERE id = @aether_spec_id);
SET @aether_spec_before := (
  SELECT COUNT(*) FROM tblCharacterSpecialisation
  WHERE idCharacter = @aether_spec_character AND idSkill = @aether_spec_skill
    AND idSkillSpecialisation = @aether_spec_id
);
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0002 = 3
  AND @aether_spec_character IS NOT NULL AND @aether_spec_skill IS NOT NULL
  AND @aether_spec_before IN (0, 1),
  'SELECT ''Specialisation probe keys are valid'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0003_NO_PROBE_KEYS__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
START TRANSACTION;
INSERT IGNORE INTO tblCharacterSpecialisation (idCharacter, idSkill, idSkillSpecialisation)
SELECT @aether_spec_character, @aether_spec_skill, @aether_spec_id
WHERE @aether_blockers = 0 AND @aether_probe_0002 = 3 AND @aether_spec_skill IS NOT NULL;
INSERT IGNORE INTO tblCharacterSpecialisation (idCharacter, idSkill, idSkillSpecialisation)
SELECT @aether_spec_character, @aether_spec_skill, @aether_spec_id
WHERE @aether_blockers = 0 AND @aether_probe_0002 = 3 AND @aether_spec_skill IS NOT NULL;
SET @aether_probe_0003 := (
  SELECT COUNT(*) FROM tblCharacterSpecialisation
  WHERE idCharacter = @aether_spec_character AND idSkill = @aether_spec_skill
    AND idSkillSpecialisation = @aether_spec_id
);
ROLLBACK;
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0002 = 3 AND @aether_probe_0003 = 1,
  'SELECT ''Character-specialisation uniqueness verified'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0003_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0003_unique_character_specialisation', 'Enforce one character link per skill specialisation'
WHERE @aether_blockers = 0 AND @aether_probe_0002 = 3 AND @aether_probe_0003 = 1
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0002 = 3 AND @aether_probe_0003 = 1,
  'CREATE TABLE IF NOT EXISTS tblApiIdempotency (id bigint unsigned NOT NULL AUTO_INCREMENT, idUser int NOT NULL, operation varchar(100) NOT NULL, requestKey varchar(128) NOT NULL, payloadHash char(64) NOT NULL, status enum(''processing'',''completed'') NOT NULL DEFAULT ''processing'', responseStatus smallint unsigned DEFAULT NULL, responseJson mediumtext DEFAULT NULL, createdAt datetime NOT NULL DEFAULT current_timestamp(), updatedAt datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), expiresAt datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY uq_tblApiIdempotency_user_operation_key (idUser, operation, requestKey), KEY idx_tblApiIdempotency_expiresAt (expiresAt)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT * FROM __AETHER_BLOCK_0003_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;

-- Verify duplicate rejection and independent user/operation scopes, then roll back.
SET @aether_probe_key := UUID();
START TRANSACTION;
INSERT INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
SELECT 0, '__aether_schema_probe__', @aether_probe_key, REPEAT('0', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE @aether_blockers = 0 AND @aether_probe_0003 = 1;
INSERT IGNORE INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
SELECT 0, '__aether_schema_probe__', @aether_probe_key, REPEAT('0', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE @aether_blockers = 0 AND @aether_probe_0003 = 1;
INSERT IGNORE INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
SELECT 1, '__aether_schema_probe__', @aether_probe_key, REPEAT('0', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE @aether_blockers = 0 AND @aether_probe_0003 = 1;
INSERT IGNORE INTO tblApiIdempotency
  (idUser, operation, requestKey, payloadHash, status, expiresAt)
SELECT 0, '__aether_schema_probe_other__', @aether_probe_key, REPEAT('0', 64), 'processing', DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE @aether_blockers = 0 AND @aether_probe_0003 = 1;
SET @aether_probe_0004 := (
  SELECT COUNT(*) FROM tblApiIdempotency
  WHERE requestKey = @aether_probe_key
    AND operation IN ('__aether_schema_probe__', '__aether_schema_probe_other__')
);
ROLLBACK;
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0004 = 3,
  'SELECT ''Idempotency uniqueness verified'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0004_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0004_create_api_idempotency', 'Create transactional API idempotency registry'
WHERE @aether_blockers = 0 AND @aether_probe_0004 = 3
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0004 = 3,
  'ALTER TABLE tblLinkEventUser ADD UNIQUE INDEX IF NOT EXISTS uq_tblLinkEventUser_event_user (idEvent, idUser), ADD INDEX IF NOT EXISTS idx_tblLinkEventUser_user_event (idUser, idEvent)',
  'SELECT * FROM __AETHER_BLOCK_0004_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;

-- tblLinkEventUser has no foreign keys in either supplied export. Use free
-- negative IDs so an existing pair cannot make a missing unique index pass.
SET @aether_probe_event := -1100000000 - FLOOR(RAND() * 1000000);
SET @aether_probe_user := -1100000000 - FLOOR(RAND() * 1000000);
SET @aether_probe_event_free := (
  SELECT COUNT(*) FROM tblLinkEventUser
  WHERE idEvent IN (@aether_probe_event, @aether_probe_event - 1)
     OR idUser IN (@aether_probe_user, @aether_probe_user - 1)
);
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0004 = 3
  AND @aether_probe_event_free = 0,
  'SELECT ''Event-user probe keys are free'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0005_PROBE_KEYS__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
SET @aether_blockers := @aether_blockers + IF(@aether_probe_event_free = 0, 0, 1);
START TRANSACTION;
INSERT IGNORE INTO tblLinkEventUser (idEvent, idUser)
SELECT @aether_probe_event, @aether_probe_user
WHERE @aether_blockers = 0 AND @aether_probe_event_free = 0;
INSERT IGNORE INTO tblLinkEventUser (idEvent, idUser)
SELECT @aether_probe_event, @aether_probe_user
WHERE @aether_blockers = 0 AND @aether_probe_event_free = 0;
INSERT IGNORE INTO tblLinkEventUser (idEvent, idUser)
SELECT @aether_probe_event, @aether_probe_user - 1
WHERE @aether_blockers = 0 AND @aether_probe_event_free = 0;
INSERT IGNORE INTO tblLinkEventUser (idEvent, idUser)
SELECT @aether_probe_event - 1, @aether_probe_user
WHERE @aether_blockers = 0 AND @aether_probe_event_free = 0;
SET @aether_probe_0005 := (
  SELECT COUNT(*) FROM tblLinkEventUser
  WHERE idEvent IN (@aether_probe_event, @aether_probe_event - 1)
    AND idUser IN (@aether_probe_user, @aether_probe_user - 1)
);
ROLLBACK;
SET @aether_sql := IF(@aether_blockers = 0 AND @aether_probe_0005 = 3,
  'SELECT ''Event-user uniqueness verified'' AS verificationResult',
  'SELECT * FROM __AETHER_BLOCK_0005_WRONG_INDEX__');
PREPARE aether_step FROM @aether_sql; EXECUTE aether_step; DEALLOCATE PREPARE aether_step;
INSERT INTO tblSchemaMigration (migrationCode, description)
SELECT '0005_unique_event_user', 'Enforce one event participation per user'
WHERE @aether_blockers = 0 AND @aether_probe_0005 = 3
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

SELECT CASE
  WHEN @aether_blockers = 0 AND @aether_probe_0002 = 3
   AND @aether_probe_0003 = 1 AND @aether_probe_0004 = 3
   AND @aether_probe_0005 = 3
  THEN 'Migraties 0001-0005: structuur bijgewerkt; controleer onderstaande definities.'
  ELSE 'NIET GESLAAGD: een preflight of structuurprobe heeft de migratie geblokkeerd.'
END AS migrationResult;
SELECT migrationCode, description, appliedAt FROM tblSchemaMigration
WHERE migrationCode IN (
  '0001_create_schema_migration_registry', '0002_unique_character_skill',
  '0003_unique_character_specialisation', '0004_create_api_idempotency',
  '0005_unique_event_user'
) ORDER BY migrationCode;
SHOW INDEX FROM tblLinkCharacterSkill;
SHOW INDEX FROM tblCharacterSpecialisation;
SHOW CREATE TABLE tblApiIdempotency;
SHOW INDEX FROM tblLinkEventUser;
