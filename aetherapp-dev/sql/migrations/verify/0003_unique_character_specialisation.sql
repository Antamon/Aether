SELECT INDEX_NAME, NON_UNIQUE,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS indexedColumns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tblCharacterSpecialisation'
GROUP BY INDEX_NAME, NON_UNIQUE
ORDER BY INDEX_NAME;

SELECT idCharacter, idSkill, idSkillSpecialisation, COUNT(*) AS duplicateCount,
       GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM tblCharacterSpecialisation
GROUP BY idCharacter, idSkill, idSkillSpecialisation
HAVING COUNT(*) > 1;

SELECT cs.id, cs.idCharacter, cs.idSkill, cs.idSkillSpecialisation,
       ss.idSkill AS specialisationSkillId
FROM tblCharacterSpecialisation AS cs
LEFT JOIN tblSkillSpecialisation AS ss ON ss.id = cs.idSkillSpecialisation
WHERE ss.id IS NULL OR ss.idSkill <> cs.idSkill;

SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode = '0003_unique_character_specialisation';
