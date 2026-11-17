-- Onderzoek: uitvoeren op beide databases vóór de nieuwe onderzoekscode.
-- Vereist de bestaande event/personage-koppelingen (0014) en idempotentielaag (0004).
CREATE TABLE IF NOT EXISTS tblResearchLock (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB;
INSERT IGNORE INTO tblResearchLock VALUES (1);
CREATE TABLE IF NOT EXISTS tblResearchEvent (
 idEvent INT NOT NULL PRIMARY KEY, timeZone VARCHAR(64) NOT NULL DEFAULT 'Europe/Brussels',
 FOREIGN KEY (idEvent) REFERENCES tblEvent(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO tblResearchEvent(idEvent) SELECT id FROM tblEvent;
CREATE TABLE IF NOT EXISTS tblResearchChallenge (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL,
 description MEDIUMTEXT NOT NULL, active TINYINT NOT NULL DEFAULT 1, revision INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchChallengeEvent (
 idChallenge BIGINT UNSIGNED NOT NULL, idEvent INT NOT NULL, PRIMARY KEY(idChallenge,idEvent),
 FOREIGN KEY(idChallenge) REFERENCES tblResearchChallenge(id) ON DELETE CASCADE,
 FOREIGN KEY(idEvent) REFERENCES tblEvent(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchDiscovery (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, idChallenge BIGINT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL, description MEDIUMTEXT NOT NULL, idSkill INT NOT NULL,
 qrToken CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 active TINYINT NOT NULL DEFAULT 0, revision INT NOT NULL DEFAULT 1,
 FOREIGN KEY(idChallenge) REFERENCES tblResearchChallenge(id), FOREIGN KEY(idSkill) REFERENCES tblSkill(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchBonus (
 idDiscovery BIGINT UNSIGNED NOT NULL, idSpecialisation INT NOT NULL, PRIMARY KEY(idDiscovery,idSpecialisation),
 FOREIGN KEY(idDiscovery) REFERENCES tblResearchDiscovery(id) ON DELETE CASCADE,
 FOREIGN KEY(idSpecialisation) REFERENCES tblSkillSpecialisation(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchRoute (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, idDiscovery BIGINT UNSIGNED NOT NULL,
 level TINYINT NOT NULL, kind VARCHAR(24) NOT NULL DEFAULT 'general',
 idRequiredSpecialisation INT NULL, priority INT NOT NULL DEFAULT 0, revision INT NOT NULL DEFAULT 1,
 FOREIGN KEY(idDiscovery) REFERENCES tblResearchDiscovery(id),
 FOREIGN KEY(idRequiredSpecialisation) REFERENCES tblSkillSpecialisation(id),
 CHECK(level BETWEEN 0 AND 4), CHECK(kind IN ('general','specialisation','character'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchRouteCharacter (
 idRoute BIGINT UNSIGNED NOT NULL, idCharacter INT NOT NULL, PRIMARY KEY(idRoute,idCharacter),
 FOREIGN KEY(idRoute) REFERENCES tblResearchRoute(id) ON DELETE CASCADE,
 FOREIGN KEY(idCharacter) REFERENCES tblCharacter(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchStep (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, idRoute BIGINT UNSIGNED NOT NULL,
 position INT NOT NULL, content MEDIUMTEXT NOT NULL, confirmationRequired TINYINT NOT NULL DEFAULT 0,
 conditionsJson MEDIUMTEXT NOT NULL, revision INT NOT NULL DEFAULT 1,
 UNIQUE KEY uq_research_step_position(idRoute,position), FOREIGN KEY(idRoute) REFERENCES tblResearchRoute(id), CHECK(position > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchMedia (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, idStep BIGINT UNSIGNED NOT NULL,
 filename VARCHAR(200) NOT NULL, mime VARCHAR(80) NOT NULL, byteSize INT NOT NULL,
 sha256 CHAR(64) NOT NULL, bytes MEDIUMBLOB NOT NULL,
 UNIQUE KEY uq_research_media_content(idStep,sha256), FOREIGN KEY(idStep) REFERENCES tblResearchStep(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchFound (
 idEvent INT NOT NULL, idCharacter INT NOT NULL, idDiscovery BIGINT UNSIGNED NOT NULL,
 idRoute BIGINT UNSIGNED NOT NULL, effectiveLevel TINYINT NOT NULL, foundAt DATETIME(6) NOT NULL,
 completedAt DATETIME(6) NULL, PRIMARY KEY(idEvent,idCharacter,idDiscovery),
 FOREIGN KEY(idEvent) REFERENCES tblEvent(id), FOREIGN KEY(idCharacter) REFERENCES tblCharacter(id),
 FOREIGN KEY(idDiscovery) REFERENCES tblResearchDiscovery(id), FOREIGN KEY(idRoute) REFERENCES tblResearchRoute(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchStepProgress (
 idEvent INT NOT NULL, idCharacter INT NOT NULL, idStep BIGINT UNSIGNED NOT NULL,
 shownAt DATETIME(6) NULL, completedAt DATETIME(6) NULL, confirmedAt DATETIME(6) NULL,
 PRIMARY KEY(idEvent,idCharacter,idStep), FOREIGN KEY(idEvent) REFERENCES tblEvent(id),
 FOREIGN KEY(idCharacter) REFERENCES tblCharacter(id), FOREIGN KEY(idStep) REFERENCES tblResearchStep(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchAction (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, idEvent INT NOT NULL, idCharacter INT NOT NULL,
 idDiscovery BIGINT UNSIGNED NOT NULL, idStep BIGINT UNSIGNED NOT NULL DEFAULT 0,
 actionCode VARCHAR(32) NOT NULL, occurredAt DATETIME(6) NOT NULL, actorUserId INT NOT NULL,
 UNIQUE KEY uq_research_action(idEvent,idCharacter,idDiscovery,idStep,actionCode),
 KEY ix_research_action_trigger(idEvent,idDiscovery,idStep,actionCode,occurredAt),
 FOREIGN KEY(idEvent) REFERENCES tblEvent(id), FOREIGN KEY(idCharacter) REFERENCES tblCharacter(id),
 FOREIGN KEY(idDiscovery) REFERENCES tblResearchDiscovery(id), FOREIGN KEY(actorUserId) REFERENCES tblUser(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchTimer (
 idEvent INT NOT NULL, idStep BIGINT UNSIGNED NOT NULL,
 scopeCharacter INT NOT NULL DEFAULT 0, idStartedByCharacter INT NOT NULL, idStartAction BIGINT UNSIGNED NOT NULL,
 startedAt DATETIME(6) NOT NULL, endsAt DATETIME(6) NOT NULL,
 PRIMARY KEY(idEvent,idStep,scopeCharacter), FOREIGN KEY(idEvent) REFERENCES tblEvent(id),
 FOREIGN KEY(idStep) REFERENCES tblResearchStep(id), FOREIGN KEY(idStartedByCharacter) REFERENCES tblCharacter(id),
 FOREIGN KEY(idStartAction) REFERENCES tblResearchAction(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblResearchAudit (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, actorUserId INT NOT NULL,
 actionCode VARCHAR(50) NOT NULL, detailsJson MEDIUMTEXT NOT NULL, createdAt DATETIME(6) NOT NULL,
 FOREIGN KEY(actorUserId) REFERENCES tblUser(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO tblSchemaMigration(migrationCode,description)
VALUES('0024_research','Event-scoped discovery routes, feedback, timers, QR codes and protected media');
