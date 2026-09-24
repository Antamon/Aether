# Productieschema gelijkzetten met de dev-structuur

## Vergelijkingsbasis en reikwijdte

Vergeleken zijn de door de gebruiker aangeleverde phpMyAdmin-exports `oneiros_be_aether.sql` (productie, 24 september 2026 09:09) en `oneiros_beaetherdev.sql` (dev, 24 september 2026 09:08). De comparator `tests/schema_export_comparison.php` leest alleen schema-DDL en toont geen applicatierijen.

Productie bevat 67 tabellen, dev 39. De 37 gedeelde tabellen hebben dezelfde kolomdefinities en tabelopties. Dev heeft twee extra tabellen: `tblSchemaMigration` en `tblApiIdempotency`. In de gedeelde tabellen ontbreken op productie alleen de volgende dev-indexen:

| Tabel | Ontbrekende index |
| --- | --- |
| `tblLinkCharacterSkill` | uniek `(idCharacter, idSkill)` |
| `tblCharacterSpecialisation` | uniek `(idCharacter, idSkill, idSkillSpecialisation)` |
| `tblLinkEventUser` | uniek `(idEvent, idUser)` en lookup `(idUser, idEvent)` |

De 30 extra productietabellen met `oneiros_*`/`economie_*`-namen blijven bestaan. Het script importeert geen dev-rijen, wijzigt geen bestaande kolommen en verwijdert geen oude tabellen of applicatiegegevens. Het legt de structuur van migraties 0001–0005 aan; op dev zijn die migraties al geregistreerd. De afzonderlijke migratiebestanden blijven de referentie voor inspectie en rollback.

## Bestand en werking

Gebruik [production_align_to_dev_2026_09_24.sql](../sql/migrations/production_align_to_dev_2026_09_24.sql) als **één** importbestand in phpMyAdmin. Het bestand gebruikt directe MariaDB 10.11-statements en `PREPARE`, geen `SOURCE`, stored procedure of `information_schema`.

Het controleert vóór de eerste DDL de geselecteerde databasenaam, dubbele en nullsleutels in character-skill, character-specialisatie en event-gebruiker, en verweesde of aan de verkeerde skill gekoppelde specialisaties. Verweesde character-skill- en event-gebruikerlinks worden als waarschuwing geteld; ze worden niet aangepast. Bij een blokkerende uitkomst verwijst de guard bewust naar een niet-bestaande `__AETHER_BLOCK_*__`-tabel zodat MariaDB stopt met een herkenbare fout. **Schakel “fouten negeren” in phpMyAdmin niet in.**

Daarna maakt het bestand de twee tabellen waar nodig, voegt de indexen conditioneel toe en registreert 0001–0005 na een structuurcontrole. Tijdelijke testinserts voor unieke sleutels staan in datatransacties die `ROLLBACK` uitvoeren. Deze probes wijzigen geen blijvende applicatierijen; een auto-incrementteller kan door een probe wel vooruitgaan. MariaDB-DDL voert impliciete commits uit: een fout kan eerdere DDL laten staan. Na correctie van de oorzaak is het bestand hervatbaar; inspecteer wel eerst de werkelijk aangelegde tabellen en indexen. De SQL-bundel verifieert de unieke indexwerking via probes, maar controleert de exacte definitie van een reeds bestaande **niet-unieke** index met dezelfde naam niet automatisch. Vergelijk daarvoor de laatste `SHOW INDEX`-resultaten met bovenstaande tabel. Gebruik het script niet wanneer iemand tussentijds handmatig afwijkende gelijknamige schema-objecten heeft gemaakt.

## Uitvoering op productie, later

1. Plan een kort schrijfvenster met onderhoudsmodus. Maak en bewaar een volledige productie-databaseback-up en bestandsback-up. Controleer herstelbaarheid van de back-up buiten productie.
2. Controleer de productiedatabase op wijzigingen sinds de export. Bij schemawijzigingen of nieuwe migraties: vergelijk opnieuw en gebruik dit bestand niet blind.
3. Selecteer in phpMyAdmin expliciet **`oneiros_be_aether`**. Importeer alleen `sql/migrations/production_align_to_dev_2026_09_24.sql` via het tabblad *Import*. Zet geen optie aan die na SQL-fouten doorloopt.
4. Lees de eerste preflightuitkomst. Bij een `__AETHER_BLOCK_*__`-fout: stop, onderzoek de gemelde duplicaten/nulls/specialisaties, corrigeer data alleen via een afzonderlijk gecontroleerd besluit en probeer daarna opnieuw. Dit script ruimt niets op.
5. Controleer de slotmelding, vijf registraties, `SHOW CREATE TABLE tblApiIdempotency` en drie `SHOW INDEX`-resultaten. Vergelijk de kolomvolgorde en `Non_unique` met bovenstaande tabel. Open de applicatie pas weer voor writes wanneer de schema- en runtimeversies op elkaar zijn afgestemd.

Benodigde databasebevoegdheden: `SELECT`, `CREATE`, `ALTER` en `INSERT` op de geselecteerde database. `PREPARE` vereist geen apart objectprivilege maar moet door de phpMyAdmin-verbinding toegelaten zijn. Voor de verificatie-inserts en `ROLLBACK` moeten de betrokken tabellen InnoDB zijn, zoals in de exports. Geen `DROP`, `DELETE`, `CREATE ROUTINE` of `information_schema`-toegang is nodig voor dit bestand.

De gebruikte `ADD ... INDEX IF NOT EXISTS`-vorm en de impliciete commit bij `ALTER TABLE` zijn beschreven in de officiële [MariaDB ALTER TABLE-documentatie](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table) en [transactiedocumentatie](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit). De [PREPARE-documentatie](https://mariadb.com/docs/server/reference/sql-statements/prepared-statements/prepare-statement) bevestigt dat een sessievariabele één statement kan bevatten; phpMyAdmin-gedrag op dit concrete hostingaccount blijft een uitvoeringscontrole.

Rollback is niet één transactie. Zet bij een mislukte of ongewenste uitrol eerst alle writes stil; herstel zo nodig de volledige database uit de vooraf gecontroleerde back-up. Verwijder `tblApiIdempotency` niet los zolang financiële routes deze tabel gebruiken: oude retries kunnen anders opnieuw een betaling uitvoeren. De afzonderlijke rollbackbestanden beschrijven gerichte opties, maar zijn geen vervanging voor een volledige back-up.

## Verificatie tot nu toe

De statische export- en scriptcontrole in `tests/production_schema_alignment_test.php` is geslaagd op de twee aangeleverde exports. De SQL is **niet** op productie, dev of een lokale MariaDB uitgevoerd. Live preflightresultaten, privileges, phpMyAdmin-importgedrag en daadwerkelijke index-DDL zijn dus nog niet getest. Het bestand is voorbereid voor een latere gecontroleerde productiestap, niet reeds toegepast.
