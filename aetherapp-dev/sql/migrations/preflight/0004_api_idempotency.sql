-- Alleen-lezen preflight voor MariaDB 10.11.
SELECT migrationCode, appliedAt
FROM tblSchemaMigration
WHERE migrationCode IN (
  '0001_create_schema_migration_registry',
  '0002_unique_character_skill',
  '0003_unique_character_specialisation',
  '0004_create_api_idempotency'
)
ORDER BY migrationCode;

SHOW TABLES LIKE 'tblApiIdempotency';
