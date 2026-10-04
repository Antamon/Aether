-- Fase 1: compacte actuele controle op oneiros_beaetherdev.
-- Alleen SELECT; deze queries wijzigen geen gegevens.
-- Gebruik dit bestand in phpMyAdmin en bewaar de vier resultaatsets.

-- 1. Bedrijven, huidige waarde en omgerekende voorraad bij 100 aandelen.
SELECT co.id AS idCompany, co.companyName, co.companyValue,
       COUNT(DISTINCT CASE WHEN tsd.idTrait IS NOT NULL THEN lct.id END) AS linkedShareTraits,
       COALESCE(SUM(CASE WHEN tsd.idTrait IS NOT NULL
                         THEN lct.rankValue + lctc.extraPercentage ELSE 0 END), 0) AS soldShares,
       100 - COALESCE(SUM(CASE WHEN tsd.idTrait IS NOT NULL
                               THEN lct.rankValue + lctc.extraPercentage ELSE 0 END), 0) AS freeShares
FROM `oneiros_beaetherdev`.`tblCompany` AS co
LEFT JOIN `oneiros_beaetherdev`.`tblLinkCharacterTraitCompany` AS lctc ON lctc.idCompany = co.id
LEFT JOIN `oneiros_beaetherdev`.`tblLinkCharacterTrait` AS lct ON lct.id = lctc.idLinkCharacterTrait
LEFT JOIN `oneiros_beaetherdev`.`tblTraitShareDefinition` AS tsd ON tsd.idTrait = lct.idTrait
GROUP BY co.id, co.companyName, co.companyValue
ORDER BY co.id;

-- 2. Aandeeltraits zonder bruikbare bedrijfskoppeling: kandidaat voor latere verwijdering.
SELECT lct.id AS idLinkCharacterTrait, lct.idCharacter, lct.idTrait,
       lct.rankValue, GROUP_CONCAT(lctc.id ORDER BY lctc.id) AS companyLinkIds
FROM `oneiros_beaetherdev`.`tblLinkCharacterTrait` AS lct
JOIN `oneiros_beaetherdev`.`tblTraitShareDefinition` AS tsd ON tsd.idTrait = lct.idTrait
LEFT JOIN `oneiros_beaetherdev`.`tblLinkCharacterTraitCompany` AS lctc
  ON lctc.idLinkCharacterTrait = lct.id
GROUP BY lct.id, lct.idCharacter, lct.idTrait, lct.rankValue
HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0
ORDER BY lct.id;

-- 3. Blokkerende fouten in de gekoppelde aandeelregels.
SELECT lctc.id AS companyLinkId, lct.id AS shareTraitLinkId,
       lct.idCharacter, lctc.idCompany,
       lct.rankValue + lctc.extraPercentage AS proposedShareCount,
       CASE
         WHEN lct.id IS NULL THEN 'missing_trait_link'
         WHEN c.id IS NULL THEN 'missing_character'
         WHEN co.id IS NULL THEN 'missing_company'
         WHEN tsd.idTrait IS NULL THEN 'not_a_share_trait'
         WHEN lct.rankValue + lctc.extraPercentage <= 0 THEN 'nonpositive_shares'
         WHEN lct.rankValue + lctc.extraPercentage > 100 THEN 'more_than_100_shares'
         ELSE 'ok'
       END AS issue
FROM `oneiros_beaetherdev`.`tblLinkCharacterTraitCompany` AS lctc
LEFT JOIN `oneiros_beaetherdev`.`tblLinkCharacterTrait` AS lct ON lct.id = lctc.idLinkCharacterTrait
LEFT JOIN `oneiros_beaetherdev`.`tblCharacter` AS c ON c.id = lct.idCharacter
LEFT JOIN `oneiros_beaetherdev`.`tblCompany` AS co ON co.id = lctc.idCompany
LEFT JOIN `oneiros_beaetherdev`.`tblTraitShareDefinition` AS tsd ON tsd.idTrait = lct.idTrait
WHERE lctc.idCompany IS NOT NULL
  AND (lct.id IS NULL OR c.id IS NULL OR co.id IS NULL OR tsd.idTrait IS NULL
       OR lct.rankValue + lctc.extraPercentage <= 0
       OR lct.rankValue + lctc.extraPercentage > 100)
ORDER BY lctc.id;

-- 4. Om te beoordelen welke personages door intrinsieke waarde geraakt worden.
SELECT lct.idCharacter, SUM(lct.rankValue + lctc.extraPercentage) AS heldShares,
       ROUND(SUM(co.companyValue * (lct.rankValue + lctc.extraPercentage) / 100), 2)
         AS intrinsicPortfolioValue
FROM `oneiros_beaetherdev`.`tblLinkCharacterTraitCompany` AS lctc
JOIN `oneiros_beaetherdev`.`tblLinkCharacterTrait` AS lct ON lct.id = lctc.idLinkCharacterTrait
JOIN `oneiros_beaetherdev`.`tblTraitShareDefinition` AS tsd ON tsd.idTrait = lct.idTrait
JOIN `oneiros_beaetherdev`.`tblCompany` AS co ON co.id = lctc.idCompany
GROUP BY lct.idCharacter
ORDER BY lct.idCharacter;
