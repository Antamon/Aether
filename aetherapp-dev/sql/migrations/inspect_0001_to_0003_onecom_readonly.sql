-- Alleen-lezen inspectie voor de beperkte one.com/phpMyAdmin-omgeving.
-- Dit bestand gebruikt geen information_schema en wijzigt niets.

SELECT DATABASE() AS selectedDatabase;
SELECT VERSION() AS databaseVersion;
SELECT @@SESSION.foreign_key_checks AS foreignKeyChecks;

SHOW TABLES LIKE 'tblSchemaMigration';
SHOW TABLES LIKE 'tblLinkCharacterSkill';
SHOW TABLES LIKE 'tblCharacterSpecialisation';

SHOW INDEX FROM `tblLinkCharacterSkill`;
SHOW INDEX FROM `tblCharacterSpecialisation`;

SELECT idCharacter, idSkill, COUNT(*) AS duplicateCount,
       GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM `tblLinkCharacterSkill`
GROUP BY idCharacter, idSkill
HAVING COUNT(*) > 1
ORDER BY idCharacter, idSkill;

SELECT id, idCharacter, idSkill, level
FROM `tblLinkCharacterSkill`
WHERE idCharacter IS NULL OR idSkill IS NULL
ORDER BY id;

SELECT lcs.id, lcs.idCharacter, lcs.idSkill, lcs.level,
       CASE
         WHEN c.id IS NULL THEN 'missing_character'
         WHEN s.id IS NULL THEN 'missing_skill'
       END AS orphanReason
FROM `tblLinkCharacterSkill` AS lcs
LEFT JOIN `tblCharacter` AS c ON c.id = lcs.idCharacter
LEFT JOIN `tblSkill` AS s ON s.id = lcs.idSkill
WHERE c.id IS NULL OR s.id IS NULL
ORDER BY lcs.id;

SELECT idCharacter, idSkill, idSkillSpecialisation, COUNT(*) AS duplicateCount,
       GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM `tblCharacterSpecialisation`
GROUP BY idCharacter, idSkill, idSkillSpecialisation
HAVING COUNT(*) > 1
ORDER BY idCharacter, idSkill, idSkillSpecialisation;

SELECT id, idCharacter, idSkill, idSkillSpecialisation
FROM `tblCharacterSpecialisation`
WHERE idCharacter IS NULL OR idSkill IS NULL OR idSkillSpecialisation IS NULL
ORDER BY id;

SELECT cs.id, cs.idCharacter, cs.idSkill, cs.idSkillSpecialisation,
       CASE
         WHEN c.id IS NULL THEN 'missing_character'
         WHEN s.id IS NULL THEN 'missing_skill'
         WHEN ss.id IS NULL THEN 'missing_specialisation'
         WHEN ss.idSkill <> cs.idSkill THEN 'specialisation_belongs_to_other_skill'
       END AS integrityProblem,
       ss.idSkill AS specialisationSkillId
FROM `tblCharacterSpecialisation` AS cs
LEFT JOIN `tblCharacter` AS c ON c.id = cs.idCharacter
LEFT JOIN `tblSkill` AS s ON s.id = cs.idSkill
LEFT JOIN `tblSkillSpecialisation` AS ss ON ss.id = cs.idSkillSpecialisation
WHERE c.id IS NULL
   OR s.id IS NULL
   OR ss.id IS NULL
   OR ss.idSkill <> cs.idSkill
ORDER BY cs.id;

-- Deze query lukt alleen wanneer tblSchemaMigration tijdens een eerdere poging
-- al is aangemaakt. Fout 1146 betekent uitsluitend dat de tabel nog ontbreekt.
SELECT migrationCode, description, appliedAt
FROM `tblSchemaMigration`
WHERE migrationCode IN (
  '0001_create_schema_migration_registry',
  '0002_unique_character_skill',
  '0003_unique_character_specialisation'
)
ORDER BY migrationCode;
