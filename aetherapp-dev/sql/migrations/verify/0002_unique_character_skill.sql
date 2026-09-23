SELECT INDEX_NAME, NON_UNIQUE,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS indexedColumns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tblLinkCharacterSkill'
GROUP BY INDEX_NAME, NON_UNIQUE
ORDER BY INDEX_NAME;

SELECT idCharacter, idSkill, COUNT(*) AS duplicateCount,
       GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM tblLinkCharacterSkill
GROUP BY idCharacter, idSkill
HAVING COUNT(*) > 1;

SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode = '0002_unique_character_skill';
