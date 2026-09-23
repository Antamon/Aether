-- Beperking: rollback maakt dubbele deelnames opnieuw mogelijk.
-- Controleer eerst dat geen actieve runtime op de unieke index vertrouwt.

ALTER TABLE tblLinkEventUser
  DROP INDEX IF EXISTS uq_tblLinkEventUser_event_user,
  DROP INDEX IF EXISTS idx_tblLinkEventUser_user_event;

DELETE FROM tblSchemaMigration
WHERE migrationCode = '0005_unique_event_user';

