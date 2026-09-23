-- Alleen-lezen preflight. Iedere resultaatrij bij duplicates of orphans moet worden beoordeeld.
SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode IN (
  '0001_create_schema_migration_registry',
  '0002_unique_character_skill'
)
ORDER BY migrationCode;

SELECT idCharacter, idSkill, COUNT(*) AS duplicateCount,
       GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM tblLinkCharacterSkill
GROUP BY idCharacter, idSkill
HAVING COUNT(*) > 1
ORDER BY idCharacter, idSkill;

SELECT id, idCharacter, idSkill, level
FROM tblLinkCharacterSkill
WHERE idCharacter IS NULL OR idSkill IS NULL;

SELECT lcs.id, lcs.idCharacter, lcs.idSkill, lcs.level,
       CASE
         WHEN c.id IS NULL THEN 'missing_character'
         WHEN s.id IS NULL THEN 'missing_skill'
       END AS orphanReason
FROM tblLinkCharacterSkill AS lcs
LEFT JOIN tblCharacter AS c ON c.id = lcs.idCharacter
LEFT JOIN tblSkill AS s ON s.id = lcs.idSkill
WHERE c.id IS NULL OR s.id IS NULL
ORDER BY lcs.id;

SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tblLinkCharacterSkill'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tblLinkCharacterSkill'
ORDER BY CONSTRAINT_NAME;
