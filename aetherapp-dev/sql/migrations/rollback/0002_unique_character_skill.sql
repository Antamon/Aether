-- DDL kan impliciet committen. Voer dit bestand alleen bewust en afzonderlijk uit.
ALTER TABLE `tblLinkCharacterSkill`
  DROP INDEX `uq_tblLinkCharacterSkill_character_skill`;

DELETE FROM `tblSchemaMigration`
WHERE `migrationCode` = '0002_unique_character_skill'
  AND NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblLinkCharacterSkill'
      AND INDEX_NAME = 'uq_tblLinkCharacterSkill_character_skill'
  );
