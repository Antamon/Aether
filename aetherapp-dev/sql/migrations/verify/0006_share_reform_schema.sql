-- Alleen-lezen structuurcontrole. Controleer ook de kolomtypes en sleutels.
SHOW COLUMNS FROM tblCompany;
SHOW COLUMNS FROM tblCompanySnapshot;
SHOW COLUMNS FROM tblCompanyPersonnel;
SHOW CREATE TABLE tblCompanyShareHolding;
SHOW CREATE TABLE tblCompanyAnonymousShares;
SHOW CREATE TABLE tblLegacyShareMigrationSource;
SHOW CREATE TABLE tblLegacyShareOrphanArchive;
SHOW CREATE TABLE tblCharacterShareStatus;
SHOW CREATE TABLE tblCharacterShareDraftBudget;
SHOW CREATE TABLE tblShareSearch;
SHOW CREATE TABLE tblShareSearchAssessment;
SHOW CREATE TABLE tblShareOffer;
SHOW CREATE TABLE tblShareOfferRecipient;
SHOW CREATE TABLE tblCompanyShareTransaction;
SHOW CREATE TABLE tblCompanyValueHistory;
SHOW CREATE TABLE tblShareDomainCode;
SELECT migrationCode, appliedAt FROM tblSchemaMigration
WHERE migrationCode = '0006_share_reform_schema';
