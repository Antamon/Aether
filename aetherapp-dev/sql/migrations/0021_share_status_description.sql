-- Wijzig de zichtbare uitleg van de statuseigenschap Aandelen.
-- Veilig herhaalbaar op beide databases.
UPDATE tblTrait
SET description = 'Je bezit aandelen in 1 of meerdere bedrijven of andere ondernemingen. De totale waarde van je aandelenportefeuille bepaalt de kost en personeelsvoorwaarde van deze statuseigenschap. Vlak voor of tijdens een event kan de aandeelhoudersvergadering besluiten om de eventuele winsten van een bedrijf te herinvesteren of uit te betalen als dividend aan de aandeelhouders.'
WHERE name = 'Aandelen' AND `type` = 'status' AND `class` = 'all';

INSERT IGNORE INTO tblSchemaMigration (migrationCode, description)
VALUES ('0021_share_status_description', 'Update the visible Aandelen status description');
