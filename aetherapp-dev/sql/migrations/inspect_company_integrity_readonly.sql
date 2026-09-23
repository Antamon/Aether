-- Alleen-lezen inspectie voor de bestaande companytabellen op de actuele testdatabase.
-- Geen migratie: de aangeleverde export bevat al unieke sleutels voor deze combinaties.
SELECT idCompany, idCharacter, COUNT(*) AS duplicateCount
FROM tblCompanyPersonnel GROUP BY idCompany, idCharacter HAVING COUNT(*) > 1;
SELECT idCompanyPersonnel, idSkill, COUNT(*) AS duplicateCount
FROM tblCompanyPersonnelSkill GROUP BY idCompanyPersonnel, idSkill HAVING COUNT(*) > 1;
SELECT idCompanyPersonnelSkill, idSkillSpecialisation, COUNT(*) AS duplicateCount
FROM tblCompanyPersonnelSkillSpecialisation
GROUP BY idCompanyPersonnelSkill, idSkillSpecialisation HAVING COUNT(*) > 1;
SELECT idCompany, idEvent, COUNT(*) AS duplicateCount
FROM tblCompanySnapshot GROUP BY idCompany, idEvent HAVING COUNT(*) > 1;
SELECT cp.id, cp.idCompany, cp.idCharacter
FROM tblCompanyPersonnel cp
LEFT JOIN tblCompany co ON co.id = cp.idCompany
LEFT JOIN tblCharacter c ON c.id = cp.idCharacter
WHERE co.id IS NULL OR c.id IS NULL;
SELECT cps.id, cps.idCompanyPersonnel, cps.idSkill
FROM tblCompanyPersonnelSkill cps
LEFT JOIN tblCompanyPersonnel cp ON cp.id = cps.idCompanyPersonnel
LEFT JOIN tblSkill s ON s.id = cps.idSkill
WHERE cp.id IS NULL OR s.id IS NULL;
SELECT cpss.id, cpss.idCompanyPersonnelSkill, cpss.idSkillSpecialisation
FROM tblCompanyPersonnelSkillSpecialisation cpss
LEFT JOIN tblCompanyPersonnelSkill cps ON cps.id = cpss.idCompanyPersonnelSkill
LEFT JOIN tblSkillSpecialisation ss ON ss.id = cpss.idSkillSpecialisation
WHERE cps.id IS NULL OR ss.id IS NULL OR ss.idSkill <> cps.idSkill;
SELECT cs.id, cs.idCompany, cs.idEvent
FROM tblCompanySnapshot cs
LEFT JOIN tblCompany co ON co.id = cs.idCompany
LEFT JOIN tblEvent e ON e.id = cs.idEvent
WHERE co.id IS NULL OR e.id IS NULL;
SELECT lctc.id, lctc.idCompany, lctc.idLinkCharacterTrait
FROM tblLinkCharacterTraitCompany lctc
LEFT JOIN tblCompany co ON co.id = lctc.idCompany
LEFT JOIN tblLinkCharacterTrait lct ON lct.id = lctc.idLinkCharacterTrait
WHERE (lctc.idCompany IS NOT NULL AND co.id IS NULL) OR lct.id IS NULL;
SELECT lctc.idCompany, SUM(GREATEST(0, lct.rankValue + lctc.extraPercentage)) AS allocatedPercentage
FROM tblLinkCharacterTraitCompany lctc
JOIN tblLinkCharacterTrait lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
GROUP BY lctc.idCompany
HAVING SUM(GREATEST(0, lct.rankValue + lctc.extraPercentage)) > 100;
SHOW INDEX FROM tblCompanyPersonnel;
SHOW INDEX FROM tblCompanyPersonnelSkill;
SHOW INDEX FROM tblCompanyPersonnelSkillSpecialisation;
SHOW INDEX FROM tblCompanySnapshot;
SHOW INDEX FROM tblLinkCharacterTraitCompany;
