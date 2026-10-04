-- Read-only preflight voor de additieve fase-2-structuur op een TESTKOPIE.
-- De huidige screenshots bewezen 13 bedrijven, 12 orphan links en geen
-- gekoppelde fouten op oneiros_beaetherdev; controleer de kopie opnieuw.
SELECT DATABASE() AS selectedDatabase;
SELECT migrationCode FROM tblSchemaMigration ORDER BY migrationCode;
SELECT id, companyName, companyValue FROM tblCompany ORDER BY id;
SELECT lct.id AS orphanTraitLinkId, lct.idCharacter, lct.idTrait,
       GROUP_CONCAT(lctc.id ORDER BY lctc.id) AS companyLinkIds
FROM tblLinkCharacterTrait AS lct
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN tblLinkCharacterTraitCompany AS lctc ON lctc.idLinkCharacterTrait = lct.id
GROUP BY lct.id, lct.idCharacter, lct.idTrait
HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
ORDER BY lct.id;
SELECT lctc.idCompany, SUM(lct.rankValue + lctc.extraPercentage) AS soldShares,
       100 - SUM(lct.rankValue + lctc.extraPercentage) AS freeShares
FROM tblLinkCharacterTraitCompany AS lctc
JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
GROUP BY lctc.idCompany ORDER BY lctc.idCompany;
SELECT lctc.id AS invalidCompanyLinkId
FROM tblLinkCharacterTraitCompany AS lctc
LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
  AND (lct.id IS NULL OR c.id IS NULL OR co.id IS NULL OR tsd.idTrait IS NULL
       OR lct.rankValue + lctc.extraPercentage <= 0
       OR lct.rankValue + lctc.extraPercentage > 100);
SHOW INDEX FROM tblCompanySnapshot;
SHOW INDEX FROM tblCompanyPersonnel;
SHOW INDEX FROM tblLinkCharacterTraitCompany;
