# Aether-databasemigraties

## Aandelenhervorming fase 2 (voorbereid op 30 september 2026)

De huidige dev-resultaten en de uitvoering staan in [`docs/aandelen-fase2-migratie-en-rekenregels.md`](../../docs/aandelen-fase2-migratie-en-rekenregels.md). De migraties `0006_share_reform_schema`, `0007_share_reform_backfill` en `0008_share_domain_codes` zijn voorbereid, maar in deze sessie **niet** tegen MariaDB uitgevoerd. De gebruiker heeft op 1 oktober 2026 uitvoering op `oneiros_beaetherdev` na back-up toegestaan. `run_0006_to_0008_share_reform_phpmyadmin.sql` bundelt de voorcontroles, migraties en verificaties in één uitvoerbaar bestand en blokkeert de productiedatabase `oneiros_be_aether`. Upload dit pakket niet als productie-uitrol zolang de oude procentuele aandeelroutes nog schrijven.

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

## Aandelenhervorming 0006–0009

`run_0006_to_0008_share_reform_phpmyadmin.sql` is op `oneiros_beaetherdev` uitgevoerd en daarna met `inspect_share_reform_phase2_result_readonly.sql` inhoudelijk gecontroleerd. De teruggekoppelde aantallen staan in `docs/aandelen-fase2-migratie-en-rekenregels.md`.

`run_0009_to_0010_share_phase3_phpmyadmin.sql` is de volgende, afzonderlijke dev-stap. Zij eist de gecontroleerde 13-bedrijven/10-bezitrijen/9-statusrijen-bron, zet de negen historische portefeuilles onder Fr 600.000 op niveau 0 en voegt het type gerichte aandeelhoudersverkoop toe. Pas na registraties 0009 én 0010 zijn de nieuwe endpoints onder `api/shares/` beschikbaar. Volg `docs/aandelen-fase3-transactiekern.md` voor de runtime- en validatievoorwaarden.

## Fase 5: bedrijfsleiding en personeelsrechten

Voer op `oneiros_beaetherdev` na een back-up precies eenmaal
`run_0011_company_leadership_phase5_phpmyadmin.sql` uit, vóór het uploaden van de
fase-5-PHP/JS-bestanden. De bundel vereist 0006–0010, voegt de expliciete
`isCompanyLeader`-markering toe en koppelt alleen ondubbelzinnige historische
Bedrijfsleider-traits automatisch. Historische traitlinks blijven staan.
De databasegebruiker heeft `CREATE TEMPORARY TABLES` en `ALTER` nodig; de
tijdelijke kandidaatlijst wordt vóór de kolomwijziging opgebouwd.
Controleer het eindresultaat: `databaseName=oneiros_beaetherdev`,
`migrationPresent=1`, `invalidLeaders=0`, `companiesWithMultipleLeaders=0`.
Die laatste nul was een controle van de toenmalige data, geen blijvende regel:
meerdere bedrijfsleiders bij hetzelfde bedrijf zijn toegestaan.
Beoordeel daarna eventuele oude
Bedrijfsleider-traits zonder eenduidige personeelskoppeling in het beheer.

De actuele applicatie bepaalt het bedrijfsleiderschap uit het gekoppelde beroep
`Bedrijfsleider` (ook de historische varianten klein/groot). Het personeelsformulier
heeft daarom geen apart leidersvinkje: een gekoppelde leider krijgt er wel een
vaardigheid en vereist niveau, die meewegen in de snapshotberekening. Zonder
personeelskoppeling levert het beroep geen bedrijfsleidersinkomen op.

Met PHP CLI kunnen de zuivere rekenregels worden nagekeken via
`php tests/share_phase5_rules.php`. De nieuwe gerichte personeelsroute is
`api/companies/saveCompanyPersonnelAction.php`; de bedrijfsleider ziet alleen
de toegestane velden op `leaderPersonnel.html`. De bestaande volledige
personeelsroute blijft uitsluitend voor `director` en `administrator`.

MariaDB kan `ALTER TABLE` impliciet committen. Herstel van een mislukte
gedeeltelijke uitvoering vereist daarom inspectie van kolom en
migratieregistratie voordat deze bundel opnieuw wordt gebruikt.

## Fase 6: frontend

Fase 6 gebruikt de bestaande migraties 0006–0011 en vereist geen nieuw SQL-bestand.
De schermen en de nieuwe leesroute staan beschreven in
`docs/aandelen-fase6-frontend.md`.

## Fase 7: vrijgavecontrole

`inspect_share_reform_phase7_readonly.sql` bevat uitsluitend SELECT-opdrachten voor
de gekozen database. Het is geen migratie. Interpretatie van de resultaten,
regressietests en de uitrolvolgorde staan in `docs/aandelen-fase7-vrijgave.md`.

## Migratie 0012: oprichting en snapshotgeschiedenis

Voer `run_0012_company_snapshot_history_phpmyadmin.sql` eenmaal uit op de
geselecteerde database `oneiros_beaetherdev` voordat de nieuwe bedrijfspagina
en PHP-bestanden online gaan. Maak eerst een databaseback-up. De eindrij moet
`migrationPresent = 1` tonen en `foundationSnapshots` moet gelijk zijn aan
`companies`. Oorspronkelijke bedrijfswaarde en het initiële aantal aandelen
worden uit de bestaande bedrijfsgegevens overgenomen. Een onbekende historische
marktprijs, stabiliteit, rendabiliteit of anonieme voorraad blijft `NULL`;
de bedrijfspagina toont dan ‘Onbekend’. Nieuwe event-snapshots bewaren de
bedrijfswaarde, uitgegeven en anonieme aandelen, marktprijs, stabiliteit en
rendabiliteit. De initiële waarden staan alleen bij de oprichting. Bewerk
oprichtingswaarden en event-snapshots via de
bijbehorende kaarten op de bedrijfspagina.

De bedrijfspagina toont onder de algemene gegevens twee kolommen: links
stabiliteit, aandeelhouders en snapshots; rechts rendabiliteit en personeel.
Directors en administrators kunnen elke event-snapshot en de oprichting later
nog corrigeren. Een correctie aan een toegepaste snapshot laat reeds geboekte
dividenden en winst/verlies ongewijzigd. Bij correctie van de recentste
snapshot wordt een verschil in bedrijfswaarde of aandelenvoorraad ook in de
actuele bedrijfsstand verwerkt; oudere snapshots wijzigen de actuele stand niet.
De bedrijfspagina laadt de eventkeuze via de bestaande event-API. Ongebruikte
events vóór de recentste bedrijfssnapshot blijven zichtbaar maar uitgeschakeld,
omdat snapshots momenteel in eventvolgorde worden aangemaakt.

## Migratie 0013: functiebeschrijving bedrijfspersoneel

Voer `run_0013_company_personnel_function_description_phpmyadmin.sql` uit
na migratie 0012 en vóór het uploaden van de bijbehorende PHP- en JS-code.
De laatste resultaatrij moet `migrationPresent = 1` en `columnPresent = 1`
tonen. De nieuwe kolom bewaart per personeelslid de beschrijving van diens
functie binnen het bedrijf. Bestaande beschrijvingen beginnen leeg.

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
