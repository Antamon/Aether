-- Vereist lege duplicates- en integrityProblem-resultsets uit de preflight.
ALTER TABLE `tblCharacterSpecialisation`
  ADD UNIQUE KEY `uq_tblCharacterSpecialisation_character_skill_specialisation`
    (`idCharacter`, `idSkill`, `idSkillSpecialisation`);

-- Registratie gebeurt uitsluitend wanneer de nieuwe unieke index aantoonbaar bestaat.
INSERT INTO `tblSchemaMigration` (`migrationCode`, `description`)
SELECT '0003_unique_character_specialisation', 'Enforce one character link per skill specialisation'
FROM DUAL
WHERE EXISTS (
  SELECT 1
  FROM (
    SELECT INDEX_NAME, NON_UNIQUE,
           GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS indexedColumns
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblCharacterSpecialisation'
    GROUP BY INDEX_NAME, NON_UNIQUE
  ) AS foundIndex
  WHERE foundIndex.INDEX_NAME = 'uq_tblCharacterSpecialisation_character_skill_specialisation'
    AND foundIndex.NON_UNIQUE = 0
    AND foundIndex.indexedColumns = 'idCharacter,idSkill,idSkillSpecialisation'
)
AND NOT EXISTS (
  SELECT 1 FROM `tblSchemaMigration`
  WHERE `migrationCode` = '0003_unique_character_specialisation'
);
