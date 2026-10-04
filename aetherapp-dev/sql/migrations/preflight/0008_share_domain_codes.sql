-- Read-only; controleer actuele ID's op de doel-testkopie.
SELECT migrationCode FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill',
                        '0008_share_domain_codes');
SELECT id, name FROM tblSkill WHERE id = 6 OR LOWER(name) LIKE '%fiscal%';
SELECT id, idSkill, name, kind FROM tblSkillSpecialisation
WHERE idSkill = 6 OR LOWER(name) LIKE '%aandelenmarkt%'
ORDER BY idSkill, id;
SELECT code, entityType, entityId FROM tblShareDomainCode ORDER BY code;
