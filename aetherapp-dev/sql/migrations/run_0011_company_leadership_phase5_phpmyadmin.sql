-- Fase 5. Eenmalig uitvoeren op oneiros_beaetherdev na een back-up en 0006-0010.
-- Controleer eerst de gekozen databank met inspect_0011_phase5_preflight_readonly.sql.
-- Fout #1146 op __AETHER_BLOCK_PHASE5_PREFLIGHT_FAILED__ betekent dat de
-- veiligheidscontrole is mislukt; er zijn dan nog geen schemawijzigingen uitgevoerd.
-- De twee historische Bedrijfsleider-traitlinks blijven bestaan; de personeelsrij
-- wordt de expliciete bron voor leiding en bestuurslidbevoegdheid.

SET @aether_phase5_ready := DATABASE() = 'oneiros_beaetherdev'
  AND (SELECT COUNT(*) FROM tblSchemaMigration WHERE migrationCode IN
       ('0006_share_reform_schema', '0007_share_reform_backfill',
        '0008_share_domain_codes', '0009_share_status_initialization',
        '0010_holder_sale_offer')) = 5
  AND (SELECT COUNT(*) FROM tblSchemaMigration
       WHERE migrationCode = '0011_company_leadership') = 0;
SET @aether_phase5_guard := IF(@aether_phase5_ready,
  'SELECT ''FASE 5 PREFLIGHT GESLAAGD'' AS result',
  'SELECT * FROM __AETHER_BLOCK_PHASE5_PREFLIGHT_FAILED__');
PREPARE aether_phase5_check FROM @aether_phase5_guard;
EXECUTE aether_phase5_check;
DEALLOCATE PREPARE aether_phase5_check;

-- Koppel een oude Bedrijfsleider alleen automatisch als de persoon precies bij
-- één bedrijf werkt en dat bedrijf maar één dergelijke kandidaat heeft.
DROP TEMPORARY TABLE IF EXISTS aether_phase5_legacy_leaders;
CREATE TEMPORARY TABLE aether_phase5_legacy_leaders AS
SELECT cp.id AS idCompanyPersonnel
FROM tblCompanyPersonnel AS cp
JOIN (
  SELECT lct.idCharacter
    FROM tblLinkCharacterTrait AS lct
    JOIN tblTrait AS t ON t.id = lct.idTrait
   WHERE t.`class` = 'middle class' AND t.`type` = 'profession'
     AND t.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
   GROUP BY lct.idCharacter
) AS legacy ON legacy.idCharacter = cp.idCharacter
JOIN (
  SELECT idCharacter FROM tblCompanyPersonnel
   GROUP BY idCharacter HAVING COUNT(*) = 1
) AS oneJob ON oneJob.idCharacter = cp.idCharacter
JOIN (
  SELECT cp2.idCompany
    FROM tblCompanyPersonnel AS cp2
    JOIN tblLinkCharacterTrait AS lct2 ON lct2.idCharacter = cp2.idCharacter
    JOIN tblTrait AS t2 ON t2.id = lct2.idTrait
   WHERE t2.`class` = 'middle class' AND t2.`type` = 'profession'
     AND t2.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
   GROUP BY cp2.idCompany HAVING COUNT(DISTINCT cp2.idCharacter) = 1
) AS oneLeader ON oneLeader.idCompany = cp.idCompany;

ALTER TABLE tblCompanyPersonnel
  ADD COLUMN isCompanyLeader TINYINT(1) NOT NULL DEFAULT 0 AFTER isBoardMember,
  ADD KEY idx_company_personnel_leader (idCompany, isCompanyLeader, idCharacter);

INSERT INTO tblShareDomainCode (code, entityType, entityId)
SELECT CASE t.name WHEN 'Bedrijfsleider klein' THEN 'company_leader_legacy_small'
                   ELSE 'company_leader_legacy_large' END,
       'trait', t.id
  FROM tblTrait AS t
 WHERE t.`class` = 'middle class' AND t.`type` = 'profession'
   AND t.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
   AND NOT EXISTS (SELECT 1 FROM tblShareDomainCode AS dc
                    WHERE dc.entityType = 'trait' AND dc.entityId = t.id);

UPDATE tblCompanyPersonnel AS cp
JOIN aether_phase5_legacy_leaders AS candidate
  ON candidate.idCompanyPersonnel = cp.id
   SET cp.isCompanyLeader = 1, cp.isBoardMember = 1,
       cp.importance = 'Negligible';
DROP TEMPORARY TABLE aether_phase5_legacy_leaders;

DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation AS cpss
JOIN tblCompanyPersonnelSkill AS cps ON cps.id = cpss.idCompanyPersonnelSkill
JOIN tblCompanyPersonnel AS cp ON cp.id = cps.idCompanyPersonnel
WHERE cp.isCompanyLeader = 1;
DELETE cps FROM tblCompanyPersonnelSkill AS cps
JOIN tblCompanyPersonnel AS cp ON cp.id = cps.idCompanyPersonnel
WHERE cp.isCompanyLeader = 1;

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0011_company_leadership',
        'Explicit company leadership and safe legacy leader backfill');

SELECT DATABASE() AS databaseName,
       (SELECT COUNT(*) FROM tblCompanyPersonnel WHERE isCompanyLeader = 1) AS mappedLeaders,
       (SELECT COUNT(*) FROM tblCompanyPersonnel WHERE isCompanyLeader = 1
         AND (isBoardMember <> 1 OR importance <> 'Negligible')) AS invalidLeaders,
       (SELECT COUNT(*) FROM (
          SELECT idCompany FROM tblCompanyPersonnel WHERE isCompanyLeader = 1
          GROUP BY idCompany HAVING COUNT(*) > 1
       ) AS duplicates) AS companiesWithMultipleLeaders,
       (SELECT COUNT(DISTINCT lct.idCharacter)
          FROM tblLinkCharacterTrait AS lct
          JOIN tblTrait AS t ON t.id = lct.idTrait
         WHERE t.`class` = 'middle class' AND t.`type` = 'profession'
           AND t.name IN ('Bedrijfsleider klein', 'Bedrijfsleider groot')
           AND NOT EXISTS (SELECT 1 FROM tblCompanyPersonnel AS cp
                            WHERE cp.idCharacter = lct.idCharacter
                              AND cp.isCompanyLeader = 1)) AS unmappedLegacyLeaders,
       (SELECT COUNT(*) FROM tblSchemaMigration
         WHERE migrationCode = '0011_company_leadership') AS migrationPresent;
