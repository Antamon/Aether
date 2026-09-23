-- MariaDB 10.11-compatible. Voer alleen uit nadat de preflight geen bestaande afwijkende tabel toont.
CREATE TABLE `tblSchemaMigration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migrationCode` varchar(120) NOT NULL,
  `description` varchar(255) NOT NULL,
  `appliedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tblSchemaMigration_code` (`migrationCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblSchemaMigration` (`migrationCode`, `description`)
VALUES ('0001_create_schema_migration_registry', 'Create the Aether schema migration registry');
