-- Voer uit op oneiros_be_aether en oneiros_beaetherdev, na de aandelenmigratie.
-- Voegt de gewone Aandelen-statuseigenschap toe; bestaand aandelenbezit blijft behouden.
START TRANSACTION;
INSERT INTO tblTrait (name, `class`, `type`, isUnique, rankType, traitGroup, grouped, cost, income, evolution, staffRequirements, description)
SELECT 'Aandelen', 'all', 'status', 1, 'range_positive', 'Economie', 0, 1, NULL, NULL, 0,
    'Startkapitaal in Draft: rang 1 = 600.000 Fr; 2 = 1.200.000 Fr; 3 = 2.500.000 Fr; 4 = 5.000.000 Fr; 5 = 10.000.000 Fr; 6 = 20.000.000 Fr. Daarna verdubbelt het kapitaal per rang. Ongebruikt kapitaal vervalt na Draft. Daarna volgt de rang de actuele intrinsieke portefeuillewaarde.'
WHERE NOT EXISTS (SELECT 1 FROM tblTrait WHERE name = 'Aandelen' AND `type` = 'status' AND `class` = 'all');
INSERT IGNORE INTO tblTraitFlag (idTrait, flagKey)
SELECT MIN(id), 'share_capital' FROM tblTrait WHERE name = 'Aandelen' AND `type` = 'status' AND `class` = 'all' HAVING COUNT(*) > 0;
INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0015_draft_share_capital', 'Enable share capital trait and draft share purchases');
COMMIT;
