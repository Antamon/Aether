-- Uitvoeren na 0024, vóór gebruik van universele onderzoeksvoortgang.
-- Bestaande discoveries behouden persoonlijke voortgang.
ALTER TABLE tblResearchDiscovery
 ADD COLUMN IF NOT EXISTS progressionMode VARCHAR(16) NOT NULL DEFAULT 'personal';
CREATE TABLE IF NOT EXISTS tblResearchSharedProgress (
 idEvent INT NOT NULL, idDiscovery BIGINT UNSIGNED NOT NULL, position INT NOT NULL,
 idStep BIGINT UNSIGNED NOT NULL, idCharacter INT NOT NULL, completedAt DATETIME(6) NOT NULL,
 PRIMARY KEY(idEvent,idDiscovery,position),
 FOREIGN KEY(idEvent) REFERENCES tblEvent(id),
 FOREIGN KEY(idDiscovery) REFERENCES tblResearchDiscovery(id),
 FOREIGN KEY(idStep) REFERENCES tblResearchStep(id),
 FOREIGN KEY(idCharacter) REFERENCES tblCharacter(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO tblSchemaMigration(migrationCode,description)
VALUES ('0025_research_progression','Personal and event-scoped universal research stages');
