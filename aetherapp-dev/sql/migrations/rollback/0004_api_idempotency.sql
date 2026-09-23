-- Beperking: voer dit alleen uit nadat alle runtimebestanden die Idempotency-Key
-- vereisen zijn teruggezet en nadat eventueel benodigde auditresponses zijn gearchiveerd.
-- DROP TABLE verwijdert idempotentiestatus en kan oude retries opnieuw uitvoerbaar maken.
DROP TABLE IF EXISTS tblApiIdempotency;
DELETE FROM tblSchemaMigration WHERE migrationCode = '0004_create_api_idempotency';
