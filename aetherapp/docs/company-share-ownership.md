# Aandelen moederbedrijf

Versie 2026.11.04.1 — 4 november 2026.

Op de bedrijfpagina heet de bestaande knop voortaan **Privéaanbod**. In dezelfde button group staat voor admins en directors **Aandelen moederbedrijf**. Bestuursleden met een deelnemersaccount behouden hun bestaande privéaanbodfunctie, maar krijgen geen toegang tot bedrijfsbezitmutaties.

De nieuwe modal gebruikt het bestaande modalkader met uitsluitend het kruisje in de rode cirkel. Een beheerder selecteert één bedrijf via zijn logo en naam, vult een aantal aandelen in en bewaart de toewijzing. Het huidige bedrijf wordt uitgesloten van de selectie. Het geselecteerde moederbedrijf krijgt een zichtbare selectiestaat. De server controleert beide bedrijven, de huidige speldatum en voldoende vrije aandelen.

De aandelen komen uit de vrije voorraad van het geopende dochterbedrijf. Aandelen in naamloze handen zijn geen vrije aandelen; die kunnen niet via deze knop worden toegewezen. Bij onvoldoende vrije aandelen is de knop uitgeschakeld. Een ongeïnitialiseerd aandeelhouderschap of een nog niet opgericht bedrijf wordt door de server geweigerd.

Het bedrijf verschijnt in de aandeelhouderstabel, met aantal en percentage. Voor beheerders staat in de navigatiekolom een los klikbaar fabrieksicoon dat zijn bedrijfpagina opent. Personages behouden hun persoonicoon naar de economietab. Deelnemers behouden drie kolommen zonder navigatieiconen; indirect bedrijfsbezit verleent hun geen extra toegang of rechten.

Dit is een administratieve toewijzing: er wordt geen prijs, betaling of aanbodacceptatie gevraagd. Bedrijfswaarden, uitgegeven aandelen, snapshots, kwartaalwaarden en bankrekeningen blijven ongewijzigd. De huidige speldatum bepaalt het kwartaal in de auditregistratie. Meerdere toewijzingen aan hetzelfde moederbedrijf worden bij het bezit opgeteld en blijven als afzonderlijke audittransacties bewaard.

Alle voorraadberekeningen tellen het bedrijfsbezit mee: aandeelhouders, aanbiedingen, aandelenonderzoek, Draft-quotes, inkoop, snapshotcontroles en de dividendbasis voor personages. Bij een nieuwe dividendverwerking wordt het bedrijfsdeel afzonderlijk geregistreerd in `tblCompanySnapshotCompanyPayout`. Het verhoogt geen bedrijfswaarde of personagebank. Het wordt wel als toegewezen bedrag meegerekend, zodat het niet nogmaals als restant wordt verwerkt. Bestaande verwerkte dividenden worden niet herschreven. Terugnemen van een dividend verwijdert ook deze bedrijfsregistraties.

## Installatie

Voer `sql/migrations/0023_company_share_ownership.sql` uit op beide databases. De bestaande aandelenmigraties en snapshotgeschiedenis moeten aanwezig zijn. De migratie maakt drie tabellen en registreert 0023; bestaande waarden worden niet gewijzigd. De migratie is herhaalbaar en is niet door de codewijziging uitgevoerd.

Upload vervolgens de gewijzigde runtimebestanden naar de betreffende website:

- `api/shares/shareOwnershipRepository.php` (nieuw)
- `api/shares/shareCompanyOwnershipService.php` (nieuw)
- `api/shares/addCompanyShareHolding.php` (nieuw)
- `api/shares/shareRepository.php`
- `api/shares/shareService.php`
- `api/shares/shareWriteHttp.php`
- `api/shares/shareSearchService.php`
- `api/shares/shareDraftBudget.php`
- `api/shares/shareDividendRules.php`
- `api/characters/economyUtils.php`
- `api/companies/companySnapshotService.php`
- `js/shareInterface.js`
- `css/style.css`
- `VERSION` en de zes HTML-entrypagina's met vernieuwde assetlinks.

Zonder migratie blijven de bestaande aandelenfuncties werken. De nieuwe knop is dan uitgeschakeld en de nieuwe schrijfroute geeft een gerichte migratiemelding.

## Controle

`tests/company_share_ownership_test.php` voert de echte service, voorraadqueries, idempotentielaag en dividendfuncties uit op geïsoleerde SQLite-opslag met vertaling van MySQL-lock- en upsert-syntaxis. De test controleert rechten, eigen-aandelenweigering, oprichting, voorraadtekort, optellen van bezit, dubbele aanvragen, audit, ongewijzigde bedrijfsrecords, gelijke numerieke bedrijfs-/personage-ID's en dividendterugname. Dit vervangt geen uitvoering van de migratie of gelijktijdigheidstest op de online MariaDB-database.

`tests/share_purchase_ui_test.cjs` controleert de gezamenlijke button group, rolverdeling inclusief deelnemers in de raad van bestuur, fabriekslinks, logoselectie, aantalvalidatie en het opslaan van de modal. Bestaande aankoop-, waarderings-, onderzoeks- en snapshottests blijven beschikbaar.
