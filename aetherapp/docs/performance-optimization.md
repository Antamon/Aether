# Optimalisatie 2026-10-05

De gebruikerslijst en personagerelaties delen lopende aanvragen en antwoorden in
het geheugen van de huidige pagina. De gebruikerslijst vervalt na vijf minuten,
relaties na één minuut. Schrijfacties maken deze cache ongeldig, zowel voor als
na de aanvraag. Fouten worden niet gecachet. Antwoorden worden gekopieerd zodat
een component het antwoord van een andere component niet kan wijzigen.

De navigatie en pagina-initialisatie delen één `checkLogin.php`-aanvraag via
`aetherGetCurrentUser()`. Deze levert ook id, voornaam, achternaam en CSRF-token.
De bestaande synchronisatie van het WordPress-profiel blijft actief.

Lokale JavaScript- en CSS-links staan rechtstreeks in de HTML met een hash van
de bestandsinhoud als versie. De PHP-omleiding is voor die pagina's niet meer
nodig. `asset.php` blijft beschikbaar voor bestaande verwijzingen.

## Uploaden

Upload de gewijzigde JS-bestanden, `checkLogin.php`, `VERSION` en alle gewijzigde
HTML-pagina's samen. Er is geen SQL-migratie nodig. Upload eerst de code en dan
de HTML. Doe een volledige paginaverversing na de upload.

Bestanden voor deze release (`2026.10.05.3`):

- `checkLogin.php` en `VERSION`
- `css/style.css` (de actuele productiestijl; de HTML-versie verwijst hiernaar)
- `index.html`, `companies.html`, `admin.html`, `eventParticipation.html`,
  `leaderPersonnel.html` en `static.html`
- `js/mainFunctions.js`, `js/apiCharacter.js`, `js/characterFunctions.js`,
  `js/companyFunctions.js`, `js/eventFunctions.js`, `js/adminFunctions.js`,
  `js/aetherSidebars.js` en `js/createCharacter.js`

Als je alle JS- en CSS-bestanden uploadt, blijven buiten die mappen dus alleen de zes
HTML-pagina's, `checkLogin.php` en `VERSION` over. `tools/`, `tests/` en `docs/`
zijn voor lokaal gebruik en hoeven niet naar de webserver.

Bij iedere volgende CSS/JS-wijziging: voer lokaal `node tools/version-assets.cjs`
uit en upload ook de HTML-pagina's die het script noemt. Het script behoudt de
laadvolgorde, externe CDN-links en relatieve applicatiepaden.

## Controle

Open personages en bedrijven met `?performance=1` en download na enkele
wisselingen een rapport. Verwacht één aanmeldcontrole per paginalaad, één
relatie-aanvraag per personage zolang die geldig blijft, een gedeelde
gebruikerslijst en geen asset.php-omleidingen voor CSS/JS.

De eerder gemeten wachttijd van 44,9 seconden vóór de HTML-respons is nog niet
verklaard. Deze wijzigingen bewijzen niet dat die server-/verbindingsvertraging
verdwenen is. Daarvoor is een nieuwe meting nodig.
