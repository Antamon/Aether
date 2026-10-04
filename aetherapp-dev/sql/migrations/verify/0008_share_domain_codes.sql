SELECT migrationCode, appliedAt FROM tblSchemaMigration
WHERE migrationCode = '0008_share_domain_codes';
SELECT dc.code, dc.entityType, dc.entityId,
       s.name AS skillName, sp.name AS specialisationName, sp.idSkill AS parentSkillId
FROM tblShareDomainCode AS dc
LEFT JOIN tblSkill AS s ON dc.entityType = 'skill' AND s.id = dc.entityId
LEFT JOIN tblSkillSpecialisation AS sp
  ON dc.entityType = 'skill_specialisation' AND sp.id = dc.entityId
WHERE dc.code IN ('skill.fiscality', 'skill_specialisation.stock_market')
ORDER BY dc.code;
