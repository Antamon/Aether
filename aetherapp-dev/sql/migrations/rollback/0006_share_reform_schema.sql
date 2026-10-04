-- Beperkte schema-terugweg: alleen na succesvolle rollback van 0007 op een
-- wegwerpbare testkopie en als geen nieuwe rijen of externe afhankelijkheden
-- zijn ontstaan. DDL is niet transactioneel. Dit script is bewust NIET
-- uitvoerbaar als bundel: beoordeel SHOW CREATE TABLE en backups handmatig.
-- De primaire terugweg voor een testkopie is herstel van de vooraf gemaakte
-- volledige database-export. In productie hoort een aparte releasebeslissing.

SELECT migrationCode, appliedAt FROM tblSchemaMigration
WHERE migrationCode IN ('0006_share_reform_schema', '0007_share_reform_backfill');
SELECT (SELECT COUNT(*) FROM tblCompanyShareHolding) AS holdings,
       (SELECT COUNT(*) FROM tblCompanyShareTransaction) AS transactions,
       (SELECT COUNT(*) FROM tblCompanyValueHistory) AS valueHistory,
       (SELECT COUNT(*) FROM tblShareOffer) AS offers,
       (SELECT COUNT(*) FROM tblShareSearch) AS searches;
