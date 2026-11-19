# Nederlandse en Engelse interface

Alle zeven applicatiepagina's laden dezelfde catalogus en taalmodule:
`js/interface-copy.js`, `js/i18n.js` en `css/i18n.css`.
Navigatie, vaste labels, knoppen, formulieren, uitleg, toegankelijkheidslabels,
modals en bekende fout-/validatiemeldingen gebruiken deze voorziening.
De bestaande afzonderlijke aandelenwoordenboeken zijn hierin ondergebracht.

`checkLogin.php` en `getCurrentUser.php` geven `interfaceLanguage` terug.
`api/shared/interfaceLanguage.php` leest daarvoor de taal van het ingelogde
WordPress-profiel via `get_user_locale($userId)`. Nederlands en Engels worden
ondersteund; andere talen of een ontbrekende voorkeur vallen terug op Nederlands.
De taal krijgt geen invloed op rollen, toegang of opgeslagen codewaarden.

De taalkeuze staat na de verwelkoming en naam: `(nl▾)` of `(en▾)`.
De keuzelijst biedt English en Nederlands. Een handmatige keuze wordt per
gebruikers-ID in localStorage bewaard en krijgt op dit toestel voorrang op de
profielvoorkeur. Ze wijzigt het WordPress-profiel niet. Het wisselen van taal
herlaadt de pagina op dezelfde URL, zodat ook bij aanvang samengestelde schermen
de juiste taal gebruiken. Bewaar lopende bewerkingen daarom vóór het wisselen.

De catalogus bevat alleen interfacecopy. Namen, evenementtitels, vaardigheden,
eigenschappen, personageachtergronden, bedrijfsbeschrijvingen, onderzoeksteksten
en andere ingevoerde spelinhoud worden niet automatisch vertaald. Dropdowns
behouden hun oorspronkelijke `value`; alleen de vaste zichtbare labels worden
vertaald. Er is geen vertaaldienst, databankmigratie of Node.js-runtime op de
hosting nodig. Onbekende teksten vallen terug op hun oorspronkelijke tekst.

## Onderhoud

- Voeg vaste copy toe aan `tools/interface-translations.tsv` (Nederlands, tab,
  Engels). Bestaande oorspronkelijk Engelse copy staat ook in de omgekeerde
  mapping in `tools/build-interface-copy.cjs`.
- JavaScript: `aetherT('Opslaan')` of
  `aetherT('Welkom {0}', [naam])`. Vertaal nooit een API-code of opgeslagen waarde.
- HTML: gebruik `data-i18n` op een tekst-only element of
  `data-i18n-title`, `data-i18n-placeholder`, `data-i18n-alt`,
  `data-i18n-aria-label` voor een attribuut. Markeer geen gebruikersinhoud.
- Bouw lokaal met `node tools/build-interface-copy.cjs`, daarna
  `node tools/version-assets.cjs`. Upload ook de gewijzigde HTML-instappagina's.
  De build controleert ontbrekende vertalingen en afwijkende parameters.

## Controle

`tests/interface_language_test.php` controleert locale-normalisatie en de
WordPress-profielbron. `tests/interface_language_browser_test.cjs` controleert
beide talen, de eerste profielkeuze, handmatige taalwisseling, herladen,
gebruikersisolatie, zeven pagina's, later gekloonde templates, de dynamische
eigenschappeneditor en een onderzoeksmodal. Verder controleert die test behoud
van spelinhoud en API-waarden, vertaalde validatiefouten en de mobiele en
toetsenbordbediening van de taalkeuze. De bestaande editor-, aandelen-,
bedrijven-, event-, sidebar-, modal- en onderzoekscontroles zijn opnieuw uitgevoerd.
