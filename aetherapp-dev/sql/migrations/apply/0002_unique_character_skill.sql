-- Vereist een lege duplicates-resultset uit de preflight. Er wordt geen gebruikersdata opgeschoond.
ALTER TABLE `tblLinkCharacterSkill`
  ADD UNIQUE KEY `uq_tblLinkCharacterSkill_character_skill` (`idCharacter`, `idSkill`);

-- Registratie gebeurt uitsluitend wanneer de nieuwe unieke index aantoonbaar bestaat.
INSERT INTO `tblSchemaMigration` (`migrationCode`, `description`)
SELECT '0002_unique_character_skill', 'Enforce one link per character and skill'
FROM DUAL
WHERE EXISTS (
  SELECT 1
  FROM (
    SELECT INDEX_NAME, NON_UNIQUE,
           GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS indexedColumns
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblLinkCharacterSkill'
    GROUP BY INDEX_NAME, NON_UNIQUE
  ) AS foundIndex
  WHERE foundIndex.INDEX_NAME = 'uq_tblLinkCharacterSkill_character_skill'
    AND foundIndex.NON_UNIQUE = 0
    AND foundIndex.indexedColumns = 'idCharacter,idSkill'
)
AND NOT EXISTS (
  SELECT 1 FROM `tblSchemaMigration`
  WHERE `migrationCode` = '0002_unique_character_skill'
);
