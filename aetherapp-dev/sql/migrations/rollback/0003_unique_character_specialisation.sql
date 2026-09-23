-- DDL kan impliciet committen. Voer dit bestand alleen bewust en afzonderlijk uit.
ALTER TABLE `tblCharacterSpecialisation`
  DROP INDEX `uq_tblCharacterSpecialisation_character_skill_specialisation`;

DELETE FROM `tblSchemaMigration`
WHERE `migrationCode` = '0003_unique_character_specialisation'
  AND NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblCharacterSpecialisation'
      AND INDEX_NAME = 'uq_tblCharacterSpecialisation_character_skill_specialisation'
  );
