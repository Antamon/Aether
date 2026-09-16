# Veilige rich text in de personagemodule

Datum: 2026-09-16

## Resultaat

De oorspronkelijke rich-texteditor is hersteld voor precies zes velden:

| Scherm | API-/databaseveld |
|---|---|
| Background – persoonlijke achtergrond | `tblCharacterSection.content` met section `personal_background` |
| Background – knowledge | `tblCharacterSection.content` met section `knowledge` |
| Personality – nature | `tblCharacterSection.content` met section `nature` |
| Personality – demeanor | `tblCharacterSection.content` met section `demeanour` |
| Diary – goals | `tblCharacterDiary.goals` |
| Diary – achievements | `tblCharacterDiary.achievements` |

Namen, talen, skills, traits, relaties, gossip en andere korte charactervelden blijven gewone tekst. Ze worden via `textContent` of tekstnodes weergegeven.

## Historische implementatie en regressie

De initiële implementatie in commit `a702499` gebruikte een eigen `contenteditable`-editor met `document.execCommand`. De diarytoolbar bevatte vet, cursief, ongenummerde lijst, genummerde lijst, alinea, H4 en opmaak verwijderen. De backgroundeditor had daarnaast H2, H3 en H5. Opslaan verstuurde `editor.innerHTML`; lezen en de directe weergave na opslag gebruikten `innerHTML` zonder server-side sanitization.

Commit `6b5c0a9` verving de editor tijdens het beveiligen van de tekstweergave door een `textarea`, verborg de toolbar en toonde alle inhoud met `textContent`. Dat voorkwam uitvoering van opgeslagen HTML, maar maakte bestaande opmaak letterlijk zichtbaar. De herstelde gedeelde editor behoudt de gemeenschappelijke, gevraagde toolbar met zeven functies. H2, H3 en H5 zijn bewust niet teruggebracht omdat ze buiten de vastgelegde allowlist vallen.

## Sanitization en weergave

`api/shared/richText.php` bevat de generieke server-side sanitizer. Deze bestaat uit twee stappen:

1. `DOMDocument` normaliseert historische markup: `div` wordt een alinea, betekenisloze `span` en links worden uitgepakt, en `b`/`i` worden `strong`/`em`. Elementen die uitvoerbare of actieve inhoud kunnen bevatten worden met hun inhoud verwijderd.
2. HTML Purifier voert de definitieve allowlistcontrole uit.

De characterallowlist in `api/characters/characterRichText.php` bevat uitsluitend:

- `p`
- `br`
- `strong`
- `em`
- `ul`
- `ol`
- `li`
- `h4`

Geen enkel attribuut is toegestaan. Daardoor verdwijnen onder meer eventhandlers, `style`, `class`, `id`, URL-attributen en alle markup voor scripts, afbeeldingen, SVG, iframes, objecten, embeds en formulieren.

De twee schrijfroutes saniteren vóór de PDO-write. De serverresponse bevat daarna de daadwerkelijk gesaniteerde inhoud, zodat de frontend nooit het onbehandelde request terug in de pagina plaatst. De twee leesroutes saniteren ook iedere databasewaarde. Daardoor is bestaande data veilig zonder een massale databasemigratie. Wanneer een gebruiker een rich-textveld opnieuw bewaart, wordt die waarde genormaliseerd opgeslagen.

Voor gebruikers die volgens de bestaande toegangscontrole uitsluitend diary-achievements mogen bewerken, wordt alleen het gewijzigde achievementveld gesanitized en opgeslagen als nieuwe invoer. Bestaande goals worden niet door de normalisatie herschreven als neveneffect.

`js/richTextCharacter.js` gebruikt `innerHTML` alleen voor HTML uit deze gesaniteerde API-responses. Lege waarden en gewone tekst gebruiken `textContent`. De generieke validatie-engine transformeert geen HTML; het bestaande schema-attribuut `html` documenteert alleen welke velden rich text zijn.

## Dependency en deployment

De server-side allowlist gebruikt [`ezyang/htmlpurifier`](https://packagist.org/packages/ezyang/htmlpurifier) versie 4.19.0, vastgelegd in `composer.lock`; het [officiële releaseoverzicht](https://github.com/ezyang/htmlpurifier/releases) vermeldt deze versie als de actuele release. De Composer-autoloader en dependency zijn in `vendor/` meegeleverd, zodat de productiehost geen Node.js en ook geen Composer-installatie nodig heeft wanneer de volledige applicatiemap wordt gedeployed.

Bij een deployment die dependencies zelf opbouwt, moet vanuit de applicatiemap het volgende worden uitgevoerd:

```text
composer install --no-dev --optimize-autoloader
```

De PHP-runtime moet DOM/libxml ondersteunen. De sanitizer schakelt de schrijfbare HTML-Purifier-definitiecache uit, zodat geen extra cachemap of schrijfrecht nodig is.

## Gewijzigde bestanden

- `composer.json`, `composer.lock`, `vendor/`: HTML Purifier en reproduceerbare PHP-autoloading.
- `api/shared/richText.php`: generieke normalisatie en allowlistsanitization.
- `api/characters/characterRichText.php`: de zes rich-textvelden en de characterallowlist.
- `api/characters/characterSchemas.php`: `html: true` voor section-content, goals en achievements; gossip blijft `html: false`.
- `api/characters/saveCharacterSection.php`, `saveCharacterDiary.php`: sanitization vóór opslag en gesaniteerde responsegegevens.
- `api/characters/getCharacterSections.php`, `getCharacterDiary.php`: sanitization van bestaande gegevens bij iedere uitlezing.
- `js/richTextCharacter.js`: gedeelde toolbar, `contenteditable`-editor en rich-textweergave.
- `js/backgroundCharacter.js`, `js/diaryCharacter.js`, `index.html`: gebruik en laadvolgorde van de gedeelde frontendhelper.
- `tests/character_rich_text_test.php`: sanitizer-, veld-, route- en frontendcontracttests.
- `tests/character_input_validation_test.php`: gewone-tekstasserties bijgewerkt voor de zes bewuste uitzonderingen.
- `docs/invoervalidatie-characters.md`: de eerdere platte-tekstconclusie voor deze zes velden als achterhaald gemarkeerd.

## Uitgevoerde tests

| Test | Resultaat |
|---|---|
| `tests/character_rich_text_test.php` | geslaagd: toegestane opmaak, legacy `div`/`span`, `b`/`i`, accenten, `&nbsp;`, gevaarlijke elementen en attributen, sanitizer-/JSON-roundtrip, schema’s, routes, toolbar en gewone gossiptekst |
| `tests/character_input_validation_test.php` | geslaagd |
| `tests/request_validation_test.php` | geslaagd |
| `tests/api_response_contract_test.php` | geslaagd |
| `tests/access_control_test.php` | geslaagd |
| `tests/access_control_route_coverage_test.php` | geslaagd |
| `tests/oidc_callback_test.php` | geslaagd |
| `tests/authenticated_user_test.php` | niet uitgevoerd: test meldt `PDO SQLite is niet beschikbaar` en eindigt met status 1 |
| Composer-validatie | geslaagd (status 0); waarschuwingen over sandbox-repository-eigendom, de ontbrekende projectlicentie en de bewust exact vastgelegde dependencyversie |
| PHP-syntaxcontrole | geslaagd voor alle gewijzigde applicatie- en testbestanden |
| `git diff --check` | geslaagd |
| Interactieve browsertest | niet uitgevoerd: in deze ontwikkelomgeving was geen ingebouwde browser of Chrome beschikbaar |

Er was in deze sessie geen gekoppelde WordPress-sessie en testdatabase beschikbaar voor een volledige API-opslag- en herlaadtest. De browsertest en alle automatische controles hebben daarom geen databasegegevens veranderd.

## Handmatige browsertest

Gebruik uitsluitend een lokale ontwikkel- of testdatabase met een geldige WordPress-sessie:

1. Open een eigen testpersonage met bestaande opgemaakte HTML in Background, Diary en Personality. Controleer dat tags niet letterlijk zichtbaar zijn en dat `div`/`span`-inhoud leesbaar blijft.
2. Open elk van de zes velden in bewerkmodus. Controleer de knoppen voor vet, cursief, beide lijsttypen, P, H4 en opmaak verwijderen.
3. Sla een combinatie van deze opmaak op en herlaad de pagina. Dezelfde toegestane opmaak moet zichtbaar blijven.
4. Verstuur in testdata een payload met onder meer `<script>`, `onclick`, `style`, `<img onerror>`, `<svg>`, `<iframe>` en `<object>`. Na opslaan en herladen mogen deze elementen, attributen en scriptinhoud niet in de API-response of DOM aanwezig zijn.
5. Plaats dezelfde tekst in gossip of een ander gewoon tekstveld. De markup moet daar letterlijk als tekst verschijnen en er mag geen element uit ontstaan.
6. Controleer met een participant het eigen personage en een personage van een ander, en controleer met een director en administrator de eerder toegestane handelingen. Ongeldige CSRF en directe ongeautoriseerde API-aanroepen moeten geweigerd blijven.

Er is geen bulkupdate van bestaande databasewaarden uitgevoerd en er is niets gepubliceerd of gedeployed.
