-- Voer eerst preflight/0005_unique_event_user.sql uit.
-- MariaDB-DDL kan impliciet committen.

ALTER TABLE tblLinkEventUser
  ADD UNIQUE INDEX IF NOT EXISTS uq_tblLinkEventUser_event_user (idEvent, idUser),
  ADD INDEX IF NOT EXISTS idx_tblLinkEventUser_user_event (idUser, idEvent);

INSERT INTO tblSchemaMigration (migrationCode, description)
VALUES ('0005_unique_event_user', 'Enforce one event participation per user')
ON DUPLICATE KEY UPDATE migrationCode = VALUES(migrationCode);

