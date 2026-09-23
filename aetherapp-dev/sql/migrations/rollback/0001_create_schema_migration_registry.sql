-- Geen automatische rollback: DROP TABLE zou de volledige migratiehistoriek verwijderen.
-- Behoud deze tabel. Een eventuele verwijdering vereist een afzonderlijke menselijke beoordeling.
SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
ORDER BY id;
