-- Uitvoeren op beide databases vóór de tweetalige inhoud wordt gebruikt.
-- Bestaande spelgegevens blijven de oorspronkelijke, bewerkbare bron.
CREATE TABLE IF NOT EXISTS tblContentTranslation (
    sourceHash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    targetLanguage CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sourceLanguage CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sourceText MEDIUMTEXT NOT NULL,
    translatedText MEDIUMTEXT NOT NULL,
    format ENUM('plain','html') NOT NULL DEFAULT 'plain',
    status ENUM('ready','pending','review') NOT NULL DEFAULT 'ready',
    provider VARCHAR(80) NOT NULL DEFAULT 'manual',
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sourceHash, targetLanguage),
    KEY content_translation_status (targetLanguage, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0027_content_translations', 'Versioned Dutch and English translations without changing source game content');
