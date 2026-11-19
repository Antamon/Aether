-- Na 0027; op beide databases uitvoeren. De bronteksten blijven ongewijzigd.
-- Beide websites kunnen dezelfde gebruiksdatabank gebruiken (zie config.example.php).
CREATE TABLE IF NOT EXISTS tblTranslationUsage (
    budgetKey VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    monthKey CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sentCharacters BIGINT UNSIGNED NOT NULL DEFAULT 0,
    successfulCharacters BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (budgetKey,monthKey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tblTranslationRequest (
    budgetKey VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sourceHash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    targetLanguage CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state VARCHAR(16) NOT NULL DEFAULT 'idle',
    attemptToken CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    retryAfter DATETIME NULL,
    sourceLanguage CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    translatedText MEDIUMTEXT NULL,
    format VARCHAR(5) NOT NULL DEFAULT 'plain',
    PRIMARY KEY (budgetKey,sourceHash,targetLanguage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

INSERT IGNORE INTO tblSchemaMigration (migrationCode,description)
VALUES ('0029_google_translation','Google translation cache, atomic monthly budget and request deduplication');
