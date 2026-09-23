SHOW CREATE TABLE tblApiIdempotency;
SHOW INDEX FROM tblApiIdempotency;
SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode = '0004_create_api_idempotency';

SELECT status, COUNT(*) AS rowCount
FROM tblApiIdempotency
GROUP BY status
ORDER BY status;
