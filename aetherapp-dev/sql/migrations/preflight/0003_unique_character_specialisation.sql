-- Alleen-lezen preflight. Verwijder of combineer geen gerapporteerde records zonder inhoudelijke beoordeling.
SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode IN (
  '0001_create_schema_migration_registry',
  '0002_unique_character_skill',
  '0003_unique_character_specialisation'
)
ORDER BY migrationCode;

SELECT idCharacter, idSkill, idSkillSpecialisation, COUNT(*) AS duplicateCount,
       GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM tblCharacterSpecialisation
GROUP BY idCharacter, idSkill, idSkillSpecialisation
HAVING COUNT(*) > 1
ORDER BY idCharacter, idSkill, idSkillSpecialisation;

SELECT id, idCharacter, idSkill, idSkillSpecialisation
FROM tblCharacterSpecialisation
WHERE idCharacter IS NULL OR idSkill IS NULL OR idSkillSpecialisation IS NULL;

SELECT cs.id, cs.idCharacter, cs.idSkill, cs.idSkillSpecialisation,
       CASE
         WHEN c.id IS NULL THEN 'missing_character'
         WHEN s.id IS NULL THEN 'missing_skill'
         WHEN ss.id IS NULL THEN 'missing_specialisation'
         WHEN ss.idSkill <> cs.idSkill THEN 'specialisation_belongs_to_other_skill'
       END AS integrityProblem,
       ss.idSkill AS specialisationSkillId
FROM tblCharacterSpecialisation AS cs
LEFT JOIN tblCharacter AS c ON c.id = cs.idCharacter
LEFT JOIN tblSkill AS s ON s.id = cs.idSkill
LEFT JOIN tblSkillSpecialisation AS ss ON ss.id = cs.idSkillSpecialisation
WHERE c.id IS NULL
   OR s.id IS NULL
   OR ss.id IS NULL
   OR ss.idSkill <> cs.idSkill
ORDER BY cs.id;

SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tblCharacterSpecialisation'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tblCharacterSpecialisation'
ORDER BY CONSTRAINT_NAME;
