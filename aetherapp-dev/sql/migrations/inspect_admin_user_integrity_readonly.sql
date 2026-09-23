-- Alleen lezen op de afzonderlijke aetherapp-dev-database; geen migratie.
-- Controleer ook handmatig of de WordPress-ID's bij de bedoelde tblUser-records horen.
SELECT role, COUNT(*) AS userCount FROM tblUser GROUP BY role ORDER BY role;
SELECT LOWER(name) AS normalizedName, COUNT(*) AS duplicateCount
  FROM tblSkill GROUP BY LOWER(name) HAVING COUNT(*) > 1;
SELECT LOWER(name) AS normalizedName, COUNT(*) AS duplicateCount
  FROM tblSkillType GROUP BY LOWER(name) HAVING COUNT(*) > 1;
SELECT idSkill, LOWER(name) AS normalizedName, COUNT(*) AS duplicateCount
  FROM tblSkillSpecialisation GROUP BY idSkill, LOWER(name) HAVING COUNT(*) > 1;
SELECT ss.id, ss.idSkill FROM tblSkillSpecialisation AS ss
  LEFT JOIN tblSkill AS s ON s.id = ss.idSkill WHERE s.id IS NULL;
SELECT lst.idSkill, lst.idSkillType FROM tblLinkSkillType AS lst
  LEFT JOIN tblSkill AS s ON s.id = lst.idSkill
  LEFT JOIN tblSkillType AS st ON st.id = lst.idSkillType
  WHERE s.id IS NULL OR st.id IS NULL;
SELECT cs.id, cs.idSkill, cs.idSkillSpecialisation FROM tblCharacterSpecialisation AS cs
  LEFT JOIN tblSkillSpecialisation AS ss ON ss.id = cs.idSkillSpecialisation
  WHERE ss.id IS NULL OR ss.idSkill <> cs.idSkill;
SELECT cpss.id, cpss.idSkillSpecialisation
  FROM tblCompanyPersonnelSkillSpecialisation AS cpss
  LEFT JOIN tblSkillSpecialisation AS ss ON ss.id = cpss.idSkillSpecialisation
  WHERE ss.id IS NULL;
