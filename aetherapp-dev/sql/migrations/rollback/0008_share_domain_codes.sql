-- Alleen op een geisoleerde testkopie, voordat nieuwe aandelenfunctionaliteit
-- de codes gebruikt. Laat een eventueel al bestaand of nieuw aangemaakt
-- Aandelenmarkt-specialisatierecord ongemoeid; herstel de volledige backup
-- wanneer exact de oude toestand nodig is.
START TRANSACTION;
DELETE FROM tblShareDomainCode
WHERE code IN ('skill.fiscality', 'skill_specialisation.stock_market');
DELETE FROM tblSchemaMigration
WHERE migrationCode = '0008_share_domain_codes';
COMMIT;
