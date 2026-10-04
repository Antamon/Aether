# Fase 7: test- en vrijgavecontrole voor aandelen

Fase 7 voegt geen databasemigratie toe. De code gebruikt migraties 0006–0011. De gebruiker heeft gemeld dat de fase-5-SQL op de dev-database is uitgevoerd; de fase-6-bestanden en de volledige aandelenstroom zijn nog niet online bevestigd. Beschouw de productie-uitrol daarom pas als vrijgegeven wanneer de onderstaande controles werkelijk slagen.

## Wat nu in de repository staat

- `tests/share_rules_test.php`: grenswaarden van aandelenstatus en Fiscaliteit, centafronding, anonieme voorraad, inkoop en correctie van 100 naar 1.000 aandelen zonder vermenigvuldiging van oud bezit.
- `tests/share_phase5_rules.php`: bedrijfstype, dividendnoemer en deterministische verdeling van restcenten.
- `tests/share_phase7_access_test.php`: participant/director/administrator, eigenaarschap, draftblokkade, bedrijfsbeheer en idempotentie-payloadhash.
- `sql/migrations/inspect_share_reform_phase7_readonly.sql`: alleen-lezen controle van migraties, voorraad, portefeuillewaarde, status, verweesde oude traits, bedrijfsleiders en auditaantallen. De zes migratiecodes moeten aanwezig zijn; iedere `stockCheck` moet `ok` zijn; de vier afwijkingsqueries moeten nul rijen opleveren. De historische auditaantallen zijn informatief en hoeven niet gelijk te blijven na echte transacties.

De bestaande algemene endpointtests met PDO-testdoubles en de drie MariaDB-locktests blijven deel van de regressiecontrole. Zij testen nog niet alle combinaties van de nieuwe aandelenendpoints. Dat is een afzonderlijke vrijgavevoorwaarde, geen bewijs dat de nieuwe endpoints al werken.

## Lokale testomgeving met PHP

Voer vanaf de projectroot uit met PHP 8.2+ en de benodigde extensies. De huidige werkplek heeft geen `php`, `mysql` of `mariadb` op het pad, dus deze opdrachten zijn hier niet uitgevoerd.

```powershell
$phpFiles = Get-ChildItem -Recurse -File -Filter *.php | Where-Object { $_.FullName -notmatch '[\\/](vendor|legacy)[\\/]' }
foreach ($file in $phpFiles) { php -l $file.FullName; if ($LASTEXITCODE -ne 0) { throw "PHP-syntax: $($file.FullName)" } }

$testFiles = @(
  'tests/share_rules_test.php', 'tests/share_phase5_rules.php',
  'tests/share_phase7_access_test.php',
  'tests/access_control_test.php', 'tests/access_control_route_coverage_test.php',
  'tests/api_response_contract_test.php', 'tests/character_finance_services_test.php',
  'tests/character_finance_endpoints_test.php', 'tests/company_module_endpoints_test.php',
  'tests/event_module_endpoints_test.php', 'tests/event_admin_action_endpoints_test.php',
  'tests/admin_user_module_endpoints_test.php',
  'tests/application_integration_readiness_test.php'
)
foreach ($test in $testFiles) { php $test; if ($LASTEXITCODE -ne 0) { throw "Test mislukt: $test" } }

git -c core.whitespace=cr-at-eol diff --check -- .
```

`production_schema_alignment_test.php` vergelijkt de oude exports van september en hoort niet bij deze nieuwe schemaacceptatie. Vergelijk voor de echte productie-uitrol een **verse** productie-export met de beoogde migratiebasis.

## Isolatie en overlappende transacties

Gebruik uitsluitend een aparte kopie met een databasenaam volgens `aether_disposable_*`. De bestaande concurrencytests blokkeren een gewone dev- of productiedatabase via `tests/mariadb_disposable_guard.php`. Zet de vlaggen alleen voor die kopie en test eerst de verbinding met `SELECT DATABASE()`.

```powershell
$env:AETHER_TEST_MYSQL_DATABASE = 'aether_disposable_shares'
$env:AETHER_TEST_MYSQL_DSN = 'mysql:host=127.0.0.1;dbname=aether_disposable_shares;charset=utf8mb4'
$env:AETHER_TEST_MYSQL_USER = '<testgebruiker>'
$env:AETHER_TEST_MYSQL_PASSWORD = '<testwachtwoord>'
$env:AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS = 'YES'
$env:AETHER_ALLOW_COMPANY_MARIADB_TESTS = 'YES'
$env:AETHER_TEST_DB_DISPOSABLE = 'YES'
php tests/mariadb_disposable_guard_test.php
php tests/character_finance_mariadb_concurrency_test.php
php tests/company_mariadb_concurrency_test.php
php tests/event_gossip_mariadb_concurrency_test.php
```

Geen van die drie bestaande MariaDB-tests test de nieuwe `aetherShareBuyOffer`-route zelf. Voor vrijgave zijn op dezelfde wegwerpkopie nog daadwerkelijke twee-sessie proeven nodig: twee kopers voor het laatste vrije aandeel; twee aanbiedingen uit dezelfde vrije voorraad; gelijktijdige bankmutatie; bedrijfsinkoop versus koop; en snapshot/prijswijziging versus zoekactie of koop. Controleer telkens de committed eindtoestand van bank, bezit, vrije voorraad, `companyValue`, waarde-audit, status en idempotentieregel. De sessies moeten echt overlappen; PHP-sessielocking mag ze niet achter elkaar zetten.

## Dev-acceptatie

1. Upload het complete compatibele PHP/JS/HTML/CSS-pakket van fase 3–6, inclusief `api/shares/getCreatedShareOffers.php`, en vernieuw de browser op de nieuwe `VERSION`. Voer voor fase 7 zelf geen SQL-migratie uit.
2. Voer `sql/migrations/inspect_share_reform_phase7_readonly.sql` uit op de geselecteerde **dev**-database en bewaar alle resultaten. De migratieteller moet 6 zijn. Controleer `databaseName` expliciet.
3. Test als participant en director via de echte API: eigenaarschap, CSRF, ingetrokken aanbod, verlopen event, herhaalde idempotentiesleutel met gelijke én andere payload, en herstel na een verloren response. Een fout na bankafschrijving maar vóór bezit/audit moet volledig terugrollen.
4. Controleer dat de Economie-navigatietab voor alle personages zichtbaar is en bank, portefeuille en aankoop toont. De Economie-knop onder Acties verschijnt alleen bij Fiscaliteit; zoekmarktrequests starten pas na een klik en tonen gevonden en gedeelde aandelen met prijs. Test daarna nieuwe snapshots, koopacties en actuele vrije voorraad in twee browsers.
5. Controleer waardestijging én -daling over een statusgrens voor alle aandeelhouders, ook wanneer zij aandelen in meerdere bedrijven bezitten. Controleer statuspunten, personeelsvoorwaarde, winst/dividend en bedrijfsinkoop met centafronding.
6. Draai de alleen-lezen SQL-controle nogmaals na de transacties. Een afwijking blokkeert de vrijgave totdat de oorzaak bekend en hersteld is.

## Productie-uitrol

Inspecteer eerst het actuele productieschema en de migratieregistratie zonder mutatie. Maak daarna een volledige, herstelbaar geteste backup, activeer onderhoudsmodus en pas de benodigde migraties in volgorde toe op basis van de **productiegegevens**; de dev-bundel met hardgecodeerde inventarisaantallen is geen blind uitvoerbaar productiescript. Controleer de backfill per bedrijf en personage. Plaats vervolgens het volledige compatibele runtimepakket atomair, inclusief dezelfde `VERSION`, en laat oude tabs vernieuwen. Draai het read-only rapport op productie, voer een beperkte online smoketest uit en geef writes pas daarna vrij.

Bij een mislukte vrijgave: houd writes uit, herstel code en database samen volgens de vooraf geteste backup. Bewaar de idempotentiehistorie, oude migratiebronnen en aandeeltransacties. Een gedeeltelijke terugzetting van alleen PHP of alleen tabellen is geen veilige rollback.

**Dev-databasecontrole (door gebruiker teruggekoppeld):** `oneiros_beaetherdev` toont zes registraties voor 0006–0011. Alle dertien bedrijven hebben `stockCheck = ok`; de vier afwijkingsqueries leverden nul rijen op. Het rapport toont tien historische migratiebronnen, twaalf gearchiveerde losse traits en tien huidige bezitrijen. `shareTransactions`, `valueHistoryRows` en `searchAssessments` zijn elk nul: er is dus nog geen nieuwe koop-, waardemutatie- of zoekactie via de nieuwe routes aangetoond.

**Huidige vrijgavestatus:** de read-only dev-integriteitscontrole is geslaagd. PHP-tests, echte MariaDB-overlap, de functionele dev-acceptatie en productie-inspectie zijn hier nog niet uitgevoerd. Productievrijgave blijft geblokkeerd totdat die resultaten zijn vastgelegd.
