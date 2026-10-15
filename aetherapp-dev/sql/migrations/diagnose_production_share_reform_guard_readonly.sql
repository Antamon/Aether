-- Alleen-lezen diagnose voor de eerste veiligheidscontrole van de productiemigratie.
-- Voer uit op oneiros_be_aether; dit bestand wijzigt geen gegevens of schema.
-- Geef de eerste resultaatrij door. De losse traits kunnen intussen veranderd zijn.
-- Verwacht volgens export 2026-10-04:
-- companyCount=12, linkedShareRows=10,
-- invalidLinkedRows=0, overallocatedCompanies=0, legacyHeldShares=148,
-- fiscalitySkills=1, stockMarketSpecialisations=1, baseMigrations=5, phaseMigrations=0.

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCompany) AS companyCount,
       (SELECT COUNT(*)
          FROM tblLinkCharacterTraitCompany AS lctc
          JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
          JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL) AS linkedShareRows,
       (SELECT GROUP_CONCAT(x.id ORDER BY x.id SEPARATOR ',')
          FROM (SELECT lct.id
                  FROM tblLinkCharacterTrait AS lct
                  JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
                  LEFT JOIN tblLinkCharacterTraitCompany AS lctc
                    ON lctc.idLinkCharacterTrait = lct.id
                 GROUP BY lct.id
                HAVING SUM(CASE WHEN lctc.idCompany IS NOT NULL THEN 1 ELSE 0 END) = 0) AS x
       ) AS orphanTraitIds,
       (SELECT COUNT(*)
          FROM tblLinkCharacterTraitCompany AS lctc
          LEFT JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
          LEFT JOIN tblCharacter AS c ON c.id = lct.idCharacter
          LEFT JOIN tblCompany AS co ON co.id = lctc.idCompany
          LEFT JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL
           AND (lct.id IS NULL OR c.id IS NULL OR co.id IS NULL OR tsd.idTrait IS NULL
                OR lct.rankValue + lctc.extraPercentage <= 0
                OR lct.rankValue + lctc.extraPercentage > 100)) AS invalidLinkedRows,
       (SELECT COUNT(*)
          FROM (SELECT lctc.idCompany
                  FROM tblLinkCharacterTraitCompany AS lctc
                  JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
                  JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
                 WHERE lctc.idCompany IS NOT NULL
                 GROUP BY lctc.idCompany
                HAVING SUM(lct.rankValue + lctc.extraPercentage) > 100) AS x
       ) AS overallocatedCompanies,
       (SELECT COALESCE(SUM(lct.rankValue + lctc.extraPercentage), 0)
          FROM tblLinkCharacterTraitCompany AS lctc
          JOIN tblLinkCharacterTrait AS lct ON lct.id = lctc.idLinkCharacterTrait
          JOIN tblTraitShareDefinition AS tsd ON tsd.idTrait = lct.idTrait
         WHERE lctc.idCompany IS NOT NULL) AS legacyHeldShares,
       (SELECT COUNT(*) FROM tblSkill
         WHERE id = 6 AND LOWER(name) = 'fiscaliteit') AS fiscalitySkills,
       (SELECT COUNT(*) FROM tblSkillSpecialisation
         WHERE idSkill = 6 AND name = 'Aandelenmarkt'
           AND kind = 'specialisation') AS stockMarketSpecialisations,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode IN ('0001_create_schema_migration_registry',
                                 '0002_unique_character_skill',
                                 '0003_unique_character_specialisation',
                                 '0004_create_api_idempotency',
                                 '0005_unique_event_user')) AS baseMigrations,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode IN ('0006_share_reform_schema',
                                 '0007_share_reform_backfill',
                                 '0008_share_domain_codes')) AS phaseMigrations;
