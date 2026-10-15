-- Uitvoeren op oneiros_be_aether en oneiros_beaetherdev, vóór het uploaden van de nieuwe event-API.
-- Bestaande diaryvermeldingen vormen de eerste event/personage-koppelingen.
-- De registratie voorkomt dat bewust ontkoppelde personages bij herhaling terugkeren.
SET @aether_event_links_seeded := (SELECT COUNT(*) FROM tblSchemaMigration
    WHERE migrationCode = '0014_event_character_links');
CREATE TABLE IF NOT EXISTS tblLinkEventCharacter (
    idEvent INT NOT NULL,
    idCharacter INT NOT NULL,
    PRIMARY KEY (idEvent, idCharacter),
    KEY ix_event_character_character (idCharacter),
    CONSTRAINT fk_event_character_event FOREIGN KEY (idEvent) REFERENCES tblEvent(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_character_character FOREIGN KEY (idCharacter) REFERENCES tblCharacter(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO tblLinkEventCharacter (idEvent, idCharacter)
SELECT DISTINCT d.idEvent, d.idCharacter
FROM tblCharacterDiary d
JOIN tblEvent e ON e.id = d.idEvent
JOIN tblCharacter c ON c.id = d.idCharacter
WHERE @aether_event_links_seeded = 0;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0014_event_character_links', 'Link characters to events independently from their diaries');

SELECT DATABASE() AS databaseName, COUNT(*) AS eventCharacterLinks FROM tblLinkEventCharacter;
