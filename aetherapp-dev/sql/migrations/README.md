# Aether-databasemigraties

Deze bestanden zijn bedoeld voor handmatige uitvoering via phpMyAdmin of een gecontroleerde MariaDB-CLI. Er bestaat geen publiek migratie-endpoint.

## Productieschema versus dev (exportvergelijking 24 september 2026)

Voor de afzonderlijk aangeleverde productie- en dev-exports van 24 september is `production_align_to_dev_2026_09_24.sql` voorbereid. Het is één phpMyAdmin-bestand dat alleen de ontbrekende structuur van 0001–0005 toevoegt en alle bestaande productie-applicatierijen en oude productietabellen behoudt. Het is **niet uitgevoerd**. Volg eerst de back-up-, onderhouds-, preflight- en verificatiestappen in `docs/productie-schema-gelijktrekken.md`; dit bestand is uitsluitend voor de geselecteerde productiedatabase `oneiros_be_aether` en vervangt de afzonderlijke inspectie- en rollbackbestanden niet.

## Actuele beginsituatie

De recentste beschikbare export is `sql/oneiros_beaetherdev.sql`, gegenereerd op 21 september 2026 om 09:01 door phpMyAdmin 5.2.3 op MariaDB 10.11.18. Deze export bevestigt:

- `tblSchemaMigration` met registraties 0001, 0002 en 0003;
- de unieke index `(idCharacter, idSkill)`;
- de unieke index `(idCharacter, idSkill, idSkillSpecialisation)`;
- vier bekende verweesde character-skilllinks (70, 71, 72 en 508), die door deze migraties niet zijn gewijzigd.

De oudere export `oneiros_be_mysql_service_one_com.sql` uit de Downloads-map was een pre-migratieback-up. Gebruik die niet als beschrijving van de actuele testsituatie.

## Migraties 0001–0003

Deze migraties zijn al uitgevoerd en online geïnspecteerd. `run_0001_to_0003_phpmyadmin.sql` is daarom bewust onschadelijk en voert geen DDL of datamutaties uit. `inspect_0001_to_0003_onecom_readonly.sql` blijft beschikbaar voor hercontrole met directe `SELECT`- en `SHOW`-statements.

De oorspronkelijke preflight-, apply-, verify- en rollbackbestanden blijven behouden voor inspectie en gericht herstel. De referentie-implementatie `run_0001_to_0003_requires_information_schema.sql.reference` mag niet op one.com worden uitgevoerd: het hostingaccount heeft geen betrouwbare toegang tot `information_schema`.

## Migratie 0004: financiële idempotentie

`0004_create_api_idempotency` maakt `tblApiIdempotency`. De unieke sleutel op `(idUser, operation, requestKey)` koppelt een browser-request-ID aan de vertrouwde gebruiker, de concrete operatie en de gevalideerde payloadhash.

Bestanden:

- `preflight/0004_api_idempotency.sql`: alleen-lezen controle van registraties en tabelaanwezigheid;
- `apply/0004_api_idempotency.sql`: afzonderlijke applystap;
- `verify/0004_api_idempotency.sql`: tabel-, index- en registratieweergave;
- `rollback/0004_api_idempotency.sql`: verwijdert de tabel en registratie, met het gedocumenteerde retryrisico;
- `run_0004_finance_idempotency_phpmyadmin.sql`: aanbevolen zelfstandige uitvoering voor `aetherapp-dev`.

De phpMyAdminbundel gebruikt geen `SOURCE`, stored procedure of `information_schema`. Hij:

1. controleert dat 0001–0003 geregistreerd zijn;
2. maakt de tabel met `CREATE TABLE IF NOT EXISTS`;
3. voert tijdelijke probe-inserts uit die zowel duplicaatblokkering als de scope per gebruiker en operatie controleren;
4. verwijdert de probes binnen dezelfde datatransactie;
5. registreert 0004 pas na een geslaagde structuurprobe;
6. toont de registratie, tabeldefinitie en indexen.

De blokkades gebruiken een bewust ongeldige tabelnaam in dynamische SQL. Daardoor stopt MariaDB duidelijk wanneer prerequisites of de unieke sleutel niet kloppen, zonder `CREATE ROUTINE`-privilege. Voor de migratie zijn `CREATE`, `SELECT`, `INSERT` en `DELETE` op de geselecteerde testdatabase nodig. `PREPARE` heeft in MariaDB geen afzonderlijk objectprivilege, maar moet door de hostingverbinding worden toegelaten. De runtime heeft daarnaast `UPDATE` nodig. Er is geen `ALTER`, `DROP`, routine- of `information_schema`-privilege nodig voor de normale 0004-bundel.

MariaDB-DDL kan impliciet committen. Geen van deze bestanden claimt transactionele rollback van `CREATE TABLE` of `ALTER TABLE`.

## Migratie 0005: unieke eventdeelname

`0005_unique_event_user` maakt `(idEvent, idUser)` uniek in `tblLinkEventUser` en voegt de omgekeerde lookupindex `(idUser, idEvent)` toe. Daardoor kan één gebruiker ook bij gelijktijdige requests maar eenmaal aan hetzelfde event gekoppeld worden.

Bestanden:

- `preflight/0005_unique_event_user.sql`: alleen-lezen controle op duplicaten, nullsleutels en verweesde event/userkoppelingen;
- `apply/0005_unique_event_user.sql`: conditionele index-DDL en registratie;
- `verify/0005_unique_event_user.sql`: registratie, indexen en integriteitsresultaten;
- `rollback/0005_unique_event_user.sql`: verwijdert alleen de twee indexen en de migratieregistratie;
- `run_0005_event_integrity_phpmyadmin.sql`: zelfstandige uitvoering voor `aetherapp-dev`.

De bundel vereist de registraties 0001–0004 en stopt vóór DDL bij een ontbrekende prerequisite, duplicaat of nullsleutel. Verweesde event/userkoppelingen worden met aantal en rij-ID's gerapporteerd, maar blokkeren de unieke index niet; de migratie wijzigt ze niet. Hij gebruikt geen `SOURCE`, routine of `information_schema`. De indexen worden met MariaDB 10.11 `IF NOT EXISTS` hervatbaar toegevoegd. Een tijdelijke dubbele insert wordt binnen een datatransactie teruggedraaid en bewijst vóór registratie dat de unieke event/userregel werkelijk werkt. De bundel heeft `SELECT`, `INSERT` en `ALTER` nodig; `PREPARE` moet door de hostingverbinding toegestaan zijn. Er is geen `CREATE ROUTINE`-privilege nodig.

Voer de migratie niet tegelijk met eventdeelnamewrites uit. Maak eerst een back-up, zet de applicatie kort in onderhoudsmodus, selecteer expliciet de testdatabase en importeer één keer `run_0005_event_integrity_phpmyadmin.sql`. Controleer de succesmelding, registratie en beide indexen voordat de nieuwe eventruntime wordt geactiveerd.

## Uitvoering van 0004 op aetherapp-dev

1. Activeer onderhoudsmodus en maak een volledige bestands- en databaseback-up.
2. Selecteer in phpMyAdmin expliciet `oneiros_beaetherdev`.
3. Importeer één keer `run_0004_finance_idempotency_phpmyadmin.sql`.
4. Controleer dat de melding “Migratie 0004 is geslaagd” verschijnt, dat exact één registratie voor 0004 wordt getoond en dat de samengestelde unieke index aanwezig is.
5. Upload daarna de bijbehorende runtimebestanden volgens `docs/character-economy-finance-refactor.md`.

Het bestand is hervatbaar na een gedeeltelijke uitvoering. Voer rollback alleen uit nadat alle PHP- en JavaScriptroutes die `Idempotency-Key` vereisen eerst zijn teruggezet. Het verwijderen van voltooide idempotentieregels kan een oude retry opnieuw uitvoerbaar maken.
