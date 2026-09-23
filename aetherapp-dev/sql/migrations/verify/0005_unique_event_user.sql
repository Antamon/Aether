SELECT migrationCode, description, appliedAt
FROM tblSchemaMigration
WHERE migrationCode = '0005_unique_event_user';

SHOW INDEX FROM tblLinkEventUser;

SELECT idEvent, idUser, COUNT(*) AS duplicateCount
FROM tblLinkEventUser
GROUP BY idEvent, idUser
HAVING COUNT(*) > 1;

SELECT leu.id, leu.idEvent, leu.idUser
FROM tblLinkEventUser AS leu
LEFT JOIN tblEvent AS e ON e.id = leu.idEvent
LEFT JOIN tblUser AS u ON u.id = leu.idUser
WHERE e.id IS NULL OR u.id IS NULL;

