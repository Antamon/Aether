-- Maak een taalneutrale mapping voor Fiscaliteit/Aandelenmarkt op de testkopie.
-- De naamcontrole gebeurt alleen bij de eenmalige binding; runtime zoekt op code.
-- Stop als skill 6 niet de bedoelde vaardigheid is; pas niet blind het ID aan.
SET @aether_0008_ready := (
  (SELECT COUNT(*) FROM tblSchemaMigration
   WHERE migrationCode = '0007_share_reform_backfill') = 1
  AND (SELECT COUNT(*) FROM tblSchemaMigration
       WHERE migrationCode = '0008_share_domain_codes') = 0
  AND (SELECT COUNT(*) FROM tblSkill
       WHERE id = 6 AND LOWER(name) = 'fiscaliteit') = 1
  AND (SELECT COUNT(*) FROM tblSkillSpecialisation
       WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation') <= 1
  AND (SELECT COUNT(*) FROM tblShareDomainCode
       WHERE code IN ('skill.fiscality', 'skill_specialisation.stock_market')) = 0
);
SET @aether_0008_guard_sql := IF(
  @aether_0008_ready,
  'SELECT ''0008 preflight passed'' AS result',
  'SELECT * FROM __AETHER_BLOCK_0008_WRONG_SKILL_OR_MAPPING__'
);
PREPARE aether_0008_guard FROM @aether_0008_guard_sql;
EXECUTE aether_0008_guard;
DEALLOCATE PREPARE aether_0008_guard;

START TRANSACTION;
INSERT INTO tblSkillSpecialisation (idSkill, name, kind)
SELECT 6, 'Aandelenmarkt', 'specialisation'
WHERE NOT EXISTS (
  SELECT 1 FROM tblSkillSpecialisation
  WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation'
);
INSERT INTO tblShareDomainCode (code, entityType, entityId)
VALUES ('skill.fiscality', 'skill', 6);
INSERT INTO tblShareDomainCode (code, entityType, entityId)
SELECT 'skill_specialisation.stock_market', 'skill_specialisation', id
FROM tblSkillSpecialisation
WHERE idSkill = 6 AND name = 'Aandelenmarkt' AND kind = 'specialisation';
INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0008_share_domain_codes', 'Bind fiscality and stock-market specialisation to stable codes');
COMMIT;
