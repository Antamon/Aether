# Aandelenwaardering uit vastgelegde snapshots

22 oktober 2026, versie 2026.10.22.2.

De bedrijfskaart en grafiek gebruikten snapshots, terwijl de portefeuille
`tblCompany.companyValue` gebruikte. Aankoopcorrecties telden bedragen bij dat
losse veld op, zonder ze in een kwartaal of snapshot vast te leggen. Zo ontstond
voor 24 aandelen een intrinsieke waarde van 678.999,36 Fr naast een marktwaarde
van 600.000 Fr, terwijl Holiday 2.500.000 Fr en 100 aandelen bevatte.

`aetherShareValuationSql()` in `api/shares/shareRepository.php` bepaalt voortaan
de gezamenlijke bron voor bedrijfswaarde, aandelenaantal en marktprijs:

1. De laatste opgeslagen eventsnapshot op eventdatum en daarna snapshot-ID.
   Ook een reeds opgeslagen toekomstige snapshot telt mee, zoals op de bedrijfskaart.
2. Zonder eventsnapshot geldt de oprichtingssnapshot.
3. Alleen zonder vastgelegde waarde blijft de oude bedrijfsrecord beschikbaar
   als compatibiliteitsfallback.

De intrinsieke waarde per bezit en de totale portefeuillewaarde gebruiken deze
zelfde bron. De portefeuille-API berekent ook de status uit deze waarde, zodat
een oude cache geen afwijkende waarde of statuspunten terugbrengt. Een onbekende
marktprijs in de laatste snapshot blijft onbekend; een bedrijfswaarde van nul
blijft nul. Een oud snapshot zonder vastgelegd aandelenaantal gebruikt het
actuele aantal als fallback.

Het kopen van reeds uitgegeven aandelen en het aanpassen van een aankoop
wijzigen de vastgelegde bedrijfswaardering niet. Dit geldt voor markt- en
beheerdersaankopen, Draft-aankopen en aankoopcorrecties. Aantal, prijs, aankoopdatum,
startkapitaal en de gekozen bankboeking blijven verwerkt worden. Waar een losse
oude bedrijfswaarde aanwezig was, wordt bij een dergelijke aankoop/correctie
de vastgelegde waarde teruggezet. De wijziging blijft in de bestaande auditlog.
Bedrijven zonder enige vastgelegde waardering behouden hun oude werking.

Bij wijzigingen in de snapshotgeschiedenis worden de aandelenstatussen van de
aandeelhouders opnieuw berekend onder dezelfde company- en characterlocks.

## Uitrol en bestaande gegevens

Plaats de gewijzigde bestanden op beide websites:

- `api/shares/shareRepository.php`
- `api/shares/shareService.php`
- `api/shares/shareDraftService.php`
- `api/shares/sharePurchaseHistory.php`
- `api/companies/companySnapshotService.php`
- `VERSION`

Voer daarna `sql/migrations/0020_company_snapshot_valuation.sql` uit na 0019.
Het script herstelt uitsluitend bekende afwijkende bedrijfswaarden van Van
Lerius (bedrijf 13) wanneer Holiday nog 2.500.000 Fr en 100 aandelen bevat en
er geen aandeleninkoop geregistreerd is. Het bewaart daarvoor een auditregel.
Verder herberekent het alle opgeslagen aandelenstatussen op basis van de nieuwe
waarderingsbron. Banken, aandelenbezit, aankoopgeschiedenis, kwartaalpunten en
snapshots worden niet herschreven. Het script is herhaalbaar zonder dubbele
correctie. De bestaande export blijft een ongewijzigde back-up.

Na plaatsing van de PHP-code rekent de portefeuille al correct, ook voordat
de eenmalige herstelquery is uitgevoerd. Bij 24 van de 100 aandelen is de
intrinsieke waarde dan 600.000 Fr; bij 5 aandelen is dat 125.000 Fr.

## Tests

`tests/share_snapshot_valuation_test.php` draait de echte repository- en
portefeuillequeries tegen een geisoleerde SQLite-database. De test controleert
de 24-aandelenreproductie, chronologische snapshotselectie, een toekomstige
snapshot, oude waarden en aandelenaantallen, onbekende marktprijzen, nulwaarden,
cachecorrectie, de herstelquery en fallbackgedrag. `pdo_sqlite` is hiervoor nodig.

`tests/share_purchase_events_test.php` controleert de echte aankoopservices
met geisoleerde opslag: alle aankooproutes behouden de geregistreerde waarde,
terwijl bankboekingen, aantal, Draft-budget en correcties blijven werken.

De bestaande brede `company_module_endpoints_test.php` geeft in deze omgeving
46 dezelfde testafwijkingen bij de gewijzigde code en de ongewijzigde lokale
website. Een daarvan is de oude lijstverwachting zonder het bestaande
`logoUrl`-veld. Die suite is daarom niet volledig groen; de gerichte regressies,
kwartaaltests, UI-tests en PHP-syntaxcontroles slagen. De endpointtest accepteert
nu optioneel een applicatiepad om beide codeversies te kunnen vergelijken.
