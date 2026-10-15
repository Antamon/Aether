# Aandelenaankopen per event en navbar

Update 13 oktober 2026.

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
toevoegen, met bedrijf, event, aantal en prijs per aandeel. Afgelopen events
en inmiddels inactieve personages zijn toegestaan. Dit is een gewone betaalde
aankoop: voldoende vrije aandelen en banksaldo zijn vereist; de bank wordt
gedebiteerd, het bezit en de bedrijfswaarde worden verhoogd en de status wordt
herberekend. Bestaande snapshots worden niet herschreven. De actie en het
transactielog worden in één transactie opgeslagen; dezelfde request-ID boekt
de aankoop niet nogmaals.

## Upload

Werk beide omgevingen bij met:

- `api/shares/shareMarketEvent.php`
- `api/shares/shareService.php`
- `api/shares/shareSearchService.php`
- `api/shares/shareWriteHttp.php`
- `api/shares/addSharePurchase.php` (nieuw)
- `js/mainFunctions.js`
- `js/shareInterface.js`
- `js/shareMarketClient.js`
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
