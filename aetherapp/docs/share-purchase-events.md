# Aandelenaankopen per kwartaal

Update 21 oktober 2026.

Aankopen worden gedateerd met een speljaar en kwartaal, naast het event dat
de voorwaarden bepaalt. Bij een historische kwartaalaankoop of correctie
geldt vanaf de eerste dag van dat kwartaal het lopende of eerstvolgende
event **met een snapshot van dat bedrijf**. Events zonder bedrijfssnapshot
worden overgeslagen, ook als andere bedrijven daar wel een snapshot hebben.
Zonder toekomstige bedrijfssnapshot wordt het kwartaal geweigerd en verschijnt
het niet in de jaar-/kwartaalkeuze. Eventdatums liggen exact
100 jaar na de speldatums. Een aankoop kan niet vóór het oprichtingskwartaal
van het bedrijf liggen. Deelnemers kunnen bij hun huidige/volgende event
een kwartaal kiezen vanaf het huidige spelkwartaal; historische kwartalen
zijn voor admins en directors. Een Draft-aankoop behoudt de gekozen
snapshotvoorwaarden en het aandelenstartkapitaal.

De kwartaalkeuze staat bij de aankoop in de bedrijfsmodal. Admins/directors
kunnen via het formulier onder de portefeuille ook een aankoop toevoegen;
de server gebruikt dan de marktprijs van de bedrijfssnapshot bij het event
dat bij het gekozen kwartaal hoort. Ontbreekt die prijs, dan kan die aankoop
nog niet worden bewaard.

Klik in de portefeuille op een bedrijf: beheerders krijgen de tabs
**Overzicht** en **Aankopen**. De tweede tab toont alle afzonderlijke
aankopen. De pen opent per aankoop een formulier voor aantal en kwartaal.
Bij verplaatsing naar een ander event wordt de prijs opnieuw bepaald door
de bedrijfssnapshot van dat event; binnen hetzelfde event blijft de
bewaarde aankoopprijs gelden. De interface toont de nieuwe prijs en het
bedragverschil vóór het opslaan.

Onder de tab Aankopen staan geen knoppen Sluiten of Annuleren; het kruisje
van de modal blijft beschikbaar. De overzichtstab behoudt zijn sluitknop.
Een oud aankoopkwartaal zonder beschikbare bedrijfssnapshot blijft als
bestaande datum zichtbaar. Het kan behouden blijven bij een aantalscorrectie
van een historische registratie zonder event; het wordt niet als nieuwe
kwartaalkeuze aangeboden.

De aandeelhouders op de bedrijfpagina staan in een tabel met naam, aantal,
percentage van alle uitgegeven aandelen en een personageknop die rechtstreeks
naar de economietab van dat personage verwijst.

De schakelaar **Bankrekening aanpassen** staat standaard uit. Indien aan
wordt alleen het verschil met de vorige versie afgeboekt of teruggestort.
Bij een aankoop van een andere aandeelhouder worden beide banken en beide
aandelenbezitten samen gecorrigeerd. Correcties van vrije aandelen behouden
de bedrijfswaardering uit de laatste vastgelegde snapshot. Alleen zonder
vastgelegde waardering blijft de oude kapitaalberekening beschikbaar. Draft-correcties werken
het bestede aandelenstartkapitaal bij, ook als het budget al gesloten is.
Bestaande dividend- en bedrijfssnapshots worden niet herschreven.

Er is geen SQL-migratie nodig. Het kwartaal staat in `detailsJson` van de
bestaande transactietabel. Elke correctie is een aparte
`purchase_correction` met de oorspronkelijke aankoop-ID, voor/na-gegevens,
bankverschil en uitvoerende gebruiker. Het originele aankooprecord blijft
intact; de API toont de laatst gecorrigeerde versie. Voor oude aankopen
zonder kwartaal wordt het kwartaal uit het bekende event afgeleid; zonder
event blijft de datering onbekend. De portefeuille telt werkelijk bezit op
per personage en bedrijf; correctielogregels worden geen extra aankopen.

De correctieroute controleert beheerdersrechten, eigendom van de aankoop,
vrije voorraad, nog aanwezig bezit, banksaldo, aanbodlimiet en Draft-budget.
Een revisienummer voorkomt overschrijven vanuit een verouderde modal.
Geld, aandelen en audit worden in één idempotente transactie bewaard.

Bij het openen van **Aankopen** door een admin/director vult
`initializeSharePurchaseHistory.php` ontbrekende historische aankoopgegevens
aan. Als aankoopkwartaal geldt `tblCharacter.createdAt` min 100 jaar.
Bestaande bekende datums blijven behouden. Oud bezit zonder aankooplog
krijgt een `legacy_purchase`-registratie; al geregistreerde aankopen en
verkopen (inclusief correcties) worden meegenomen om uitsluitend het
ontbrekende historische bezit te reconstrueren. Het openen van Overzicht
of het verversen van de portefeuille schrijft niets weg.

Deze aanvulling wijzigt geen bezit, bank, bedrijfswaarde of Draft-budget.
Een onbekende historische prijs wordt niet ingevuld. De registratie blijft
bewerkbaar, maar een bankcorrectie is niet beschikbaar zolang het vorige
aankoopbedrag onbekend is. Een ontbrekende aanmaakdatum geeft een melding;
er wordt dan geen andere datum geraden. Bedrijf- en personagelocks voorkomen
dubbele aanvullingen bij gelijktijdig openen van de tab. Er is geen nieuwe
SQL-migratie nodig.

De bovenste navbar toont alleen Admin (voor admins/directors) en de begroeting.
Personages, events en bedrijven blijven bereikbaar via de sidebars.

Elke nieuwe aandelenaankoop krijgt een `idEvent` in het bestaande
`tblCompanyShareTransaction`. Dit geldt voor markt-, privé- en
aandeelhouderaanbiedingen en aankopen die een beheerder toevoegt.
De portefeuille toont de aankoopgeschiedenis met het event, bedrijf, aantal
en totaalbedrag. Oude transacties zonder event blijven herkenbaar als
“Event onbekend”; er wordt geen historisch event geraden.

De server kiest voor deelnemers een lopend event, of anders het eerstvolgende
event. De einddatum is inclusief; datums worden volgens Europe/Brussels
beoordeeld. Wanneer meerdere events lopen, geldt het laatst begonnen event.
Een ontbrekende einddatum valt terug op de begindatum. Als alleen afgelopen
events bestaan, kunnen deelnemers geen aandelen kopen of zoeken. Handmatig
ingestuurde historische of verder toekomstige event-ID's worden geweigerd.

Admins/directors kunnen in Economie bij de aandelenportefeuille een aankoop
toevoegen, met bedrijf, kwartaal en aantal. Afgelopen events
en inmiddels inactieve personages zijn toegestaan. Dit is een gewone betaalde
aankoop: voldoende vrije aandelen en banksaldo zijn vereist; de bank wordt
gedebiteerd, het bezit wordt verhoogd en de status wordt op basis van de
vastgelegde bedrijfswaardering
herberekend. Bestaande snapshots worden niet herschreven. De actie en het
transactielog worden in één transactie opgeslagen; dezelfde request-ID boekt
de aankoop niet nogmaals.

## Upload

Werk beide omgevingen bij met:

- `api/shares/shareMarketEvent.php`
- `api/shares/sharePurchaseHistory.php` (nieuw)
- `api/shares/updateSharePurchase.php` (nieuw)
- `api/shares/initializeSharePurchaseHistory.php` (nieuw)
- `api/shares/shareRepository.php`
- `api/shares/shareDraftService.php`
- `api/shares/shareDraftBudget.php`
- `api/shares/shareService.php`
- `api/shares/shareSearchService.php`
- `api/shares/shareWriteHttp.php`
- `api/shares/addSharePurchase.php` (nieuw)
- `js/mainFunctions.js`
- `js/shareInterface.js`
- `js/shareMarketClient.js`
- `css/style.css`
- `index.html`, `eventParticipation.html`, `companies.html`, `admin.html`,
  `static.html` en `leaderPersonnel.html`
- `VERSION`

De HTML-pagina's bevatten nieuwe inhoudshashes voor de gewijzigde scripts.
Er is geen nieuwe SQL-migratie nodig naast het bestaande aandelensysteem.

## Controles

`tests/share_purchase_events_test.php` test eventselectie, alle drie
aanbodtypes, historische aankopen door beide beheerdersrollen, afscherming
van andere personages, afboeking, onvoldoende voorraad/saldo, rollback en
idempotentie. De test gebruikt de echte services en idempotentielaag met
geïsoleerde opslag; voor de andere appversie kan het applicatiepad als eerste
argument worden doorgegeven.

`tests/share_purchase_ui_test.cjs` controleert de aankoop-eventpayload bij alle
aanbodtypes, de beheerderskeuze, het historische aankoopformulier, de
aankoopgeschiedenis en de navbar op alle zes pagina's. De bestaande
portfolio- en eventnavigatietests blijven slagen. PHP-syntaxcontrole en de pure
aandelenregels slagen. Er is geen live database gebruikt; de bestaande
`share_phase7_access_test.php` kon de databasebootstrap hier niet laden.
