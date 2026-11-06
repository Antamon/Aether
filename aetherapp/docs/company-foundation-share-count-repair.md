# Herstel aandelenstand bij oprichtingscorrecties

22 oktober 2026, versie 2026.10.22.1.

Het bewaren van de oprichtingssnapshot paste het verschil in aandelenaantal
altijd toe op `tblCompany.issuedShareCount`. Latere eventsnapshots bleven
ongewijzigd. In bedrijf 13 veroorzaakte de correctie 20 -> 10 -> 1 daardoor
een actuele stand van 81 terwijl Holiday expliciet 100 aandelen bevatte.

`api/shares/shareService.php` begrenst de doorwerking nu zodra een
eventsnapshot voor het bedrijf een expliciet aandelenaantal heeft.
Snapshots met een NULL-aantal erven de correctie nog. Zonder eventsnapshots
blijven de oprichtingswaarde en het oprichtingsaantal bepalend voor de
actuele stand. Een latere aandeleninkoop blijft behouden: de code zet het
actuele aantal niet zomaar gelijk aan de laatste snapshot.

## Uitvoeren

Plaats de aangepaste `api/shares/shareService.php` op beide websites.
Voer daarna `sql/migrations/0019_company_foundation_share_count.sql` uit in
de database(s) met de bekende afwijking. Het script repareert uitsluitend
bedrijf 13 met de naam Van Lerius Transport & Spoorwegen, de huidige stand
81, het oprichtingsaantal 1 en een nieuwste Holiday-snapshot van 15 mei 2027
met 100 aandelen. Een geregistreerde aandeleninkoop sluit herstel uit.

Het script registreert de correctie in de auditgeschiedenis en herberekent
de opgeslagen portefeuillewaarden en aandelenstatussen van de betrokken
personages. Banken, aandelenbezit, bedrijfswaarde en snapshots worden niet
gewijzigd. De controle onderaan hoort voor de aangeleverde export 100
uitgegeven, 5 verkocht en 95 vrije aandelen te tonen. Lucians 5 aandelen
vertegenwoordigen dan 5%. Bij opnieuw uitvoeren is het aantal herstelde
records 0. Als de voorwaarden niet overeenkomen, wordt niets gecorrigeerd.

De databankexport zelf blijft ongewijzigd als referentie/back-up.

## Controle

`tests/share_purchase_events_test.php` gebruikt de echte aandelenservice met
geisoleerde opslag. De regressies dekken de twee opeenvolgende correcties
20 -> 10 -> 1, beide beheerdersrollen, een lagere oprichtingsstand dan het
actuele bezit, snapshots met onbekend aantal, bedrijven zonder snapshots,
behoud van latere aandeleninkoop en idempotent opnieuw bewaren.
