-- Alleen-lezen preflight voor migratie 0005.
-- De duplicate- en NULL-resultaatsets moeten leeg zijn.
-- Verweesde event/userkoppelingen worden afzonderlijk gerapporteerd, maar blokkeren de unieke index niet.

SELECT idEvent, idUser, COUNT(*) AS duplicateCount, GROUP_CONCAT(id ORDER BY id) AS involvedIds
FROM tblLinkEventUser
GROUP BY idEvent, idUser
HAVING COUNT(*) > 1
ORDER BY idEvent, idUser;

SELECT id, idEvent, idUser
FROM tblLinkEventUser
WHERE idEvent IS NULL OR idUser IS NULL
ORDER BY id;

SELECT leu.id, leu.idEvent, leu.idUser,
       CASE WHEN e.id IS NULL THEN 'missing_event' ELSE 'missing_user' END AS orphanReason
FROM tblLinkEventUser AS leu
LEFT JOIN tblEvent AS e ON e.id = leu.idEvent
LEFT JOIN tblUser AS u ON u.id = leu.idUser
WHERE e.id IS NULL OR u.id IS NULL
ORDER BY leu.id;

SHOW INDEX FROM tblLinkEventUser;
