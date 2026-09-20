# Refactor character-traits, diary en talen

Datum: 18 september 2026

## Afbakening en frontendcontracten

De drie actieve routes worden vanuit de browser met `POST` en een JSON-object aangeroepen via `apiFetchJson`. De centrale fetchhelper voegt `Content-Type: application/json` en het CSRF-token toe.

| Route | Requestvelden | Bestaande succesresponse |
|---|---|---|
| `updateTrait.php` | `action`, `idCharacter`, `idTrait`, `idCurrentTrait` | `{"status":"ok"}` of een spelregelfout in `error` met HTTP 200 |
| `saveCharacterDiary.php` | `idCharacter`, optioneel `idDiary`, `idEvent`, `goals`, `achievements`, `gossip1`, `gossip2`, `gossip3` | `success`, de volledige `entries`-lijst en `availableEvents` |
| `addCharacterLanguage.php` | `idCharacter` en ofwel `idLanguage` of `name` | `{"success":true}` |

`deleteCharacterLanguage.php` was al gemigreerd. De route is niet gewijzigd en is alleen als contract- en regressiecontrole meegenomen.

## Vastgestelde werking en rechten

### Traits

- Participants mogen uitsluitend traits van hun eigen player-character in status `draft` beheren.
- Directors en administrators mogen traits van ieder character beheren.
- Geheime traits zijn voor participants afgeschermd.
- Klasse, unieke beroepen, gegroepeerde keuzes, statuspunten, rangstappen en de bestaande company-share-controle zijn behouden.
- `add`, `change`, `remove`, `rank_up` en `rank_down` voeren ieder maximaal één mutatie uit. Daarvoor is geen transactie toegevoegd.

### Diary

- Participants mogen alle diaryvelden van hun eigen player-character bewerken.
- Voor een eigen extra mag een participant uitsluitend `achievements` bewerken. Bestaande goals en gossip worden server-side behouden.
- Directors en administrators mogen alle diaryvelden bewerken.
- `goals` en `achievements` worden vóór opslag met de bestaande character-rich-textallowlist gesanitized. `gossip1` tot en met `gossip3` blijven gewone tekst; de bestaande frontend toont die via `textContent`.
- Een event heeft maximaal één diary-entry per character door de bestaande unieke databasesleutel.
- Na een write levert de route dezelfde volledige diaryresponse als de leesroute.

### Talen

- Participants mogen talen uitsluitend beheren voor hun eigen player-character. Directors en administrators behouden toegang tot ieder character.
- De bestaande regels voor characterklasse, gratis taalslots, Academicus, betaalde talen en twee ervaringspunten zijn behouden.
- Een bestaande taal kan niet dubbel worden gekoppeld. Een nieuwe naam die al bestaat moet via de bestaande lijst worden gekozen.
- De unieke sleutels op taalnaam en character/taal blijven de definitieve bescherming tegen twee gelijktijdige verzoeken.

## Herstelde concrete fouten

1. Een update met een onbekende of bij een ander character horende `idDiary` kon eerder nul rijen aanpassen en toch succes retourneren. De service controleert nu diary, character en event vóór de eerste write.
2. Een onbekend event kon bij een nieuwe diary pas via een databasefout zichtbaar worden. Het event wordt nu vóór de write gecontroleerd.
3. Bij een nieuwe taal werden de globale taaldefinitie en de characterkoppeling zonder transactie geschreven. Een mislukte koppeling kon daardoor een weesrecord achterlaten. Beide writes committen of rollen nu samen terug.
4. `updateTrait.php` en `saveCharacterDiary.php` gaven technische exceptiondetails terug. Onverwachte fouten leveren nu alleen de bestaande generieke foutmelding; details gaan naar de serverlog.

## Nieuwe verantwoordelijkheden

- `updateTrait.php`, `saveCharacterDiary.php` en `addCharacterLanguage.php`: dunne orkestratie van dependencies, vertrouwde gebruiker, CSRF, JSON parsing, schema, service en gedeelde JSON-response.
- `characterTraitService.php`: traitrechten en bestaande keuze-, punten- en rangregels.
- `characterTraitRepository.php`: vaste prepared queries voor character-traitlinks en de bestaande share-capaciteitslezing.
- `characterDiaryService.php`: diaryrechten, objectconsistentie, veldbeperking, sanitization, transactie en terugleesmodel.
- `characterDiaryRepository.php`: vaste diary- en eventqueries en diarywrites.
- `characterLanguageService.php`: taalrechten, punten- en duplicaatregels en de atomaire write.
- `characterLanguageRepository.php`: vaste queries voor taaldefinities en character-taalkoppelingen.

De bestaande `characterSchemas.php`, shared request/response/validationhelpers, `characterAccess.php`, rich-texthelper en `characterReadService.php` zijn hergebruikt. De generieke validator voert geen HTML-sanitisatie uit.

## Atomiciteit en concurrency

- Diary insert/update en het opbouwen van de bijbehorende succesresponse vallen in één transactie. Een fout tijdens teruglezen rolt de write terug.
- Een nieuwe taaldefinitie en de characterkoppeling vallen in één transactie. Een fout bij de tweede write rolt ook de definitie terug.
- De aanwezige unieke indexen `uq_diary_char_event`, `uq_tblLanguage_name` en `uq_tblCharacterLanguage_character_language` blijven nodig voor gelijktijdige requests. Deze batch voegt geen migratie of nieuw concurrencygedrag toe.
- Traitacties hebben één databasewrite. De bestaande read-before-write-spelregels en company-share-compatibiliteitsafhandeling zijn functioneel ongewijzigd.

## Gewijzigde bestanden

### Applicatie

- `api/characters/updateTrait.php`
- `api/characters/saveCharacterDiary.php`
- `api/characters/addCharacterLanguage.php`
- `api/characters/characterTraitService.php`
- `api/characters/characterTraitRepository.php`
- `api/characters/characterDiaryService.php`
- `api/characters/characterDiaryRepository.php`
- `api/characters/characterLanguageService.php`
- `api/characters/characterLanguageRepository.php`

### Tests en documentatie

- `tests/character_traits_diary_languages_endpoints_test.php`
- `tests/access_control_route_coverage_test.php`
- `tests/character_input_validation_test.php`
- `tests/character_rich_text_test.php`
- `docs/character-traits-diary-languages-refactor.md`

## Testdekking

De nieuwe routetest gebruikt de echte endpoints, services, repositories, auth-, request-, response- en validatiecode met een geïsoleerde stateful PDO-testdouble. Getest zijn onder meer:

- participant op eigen en andermans character, director en administrator;
- vervalste sessierol, niet aangemeld en ongeldige CSRF via de bestaande authsuite;
- geldige traitrang, minimumrang, puntenlimiet, geheime trait, draft/ownership en vaste prepared parameters;
- diary insert/update, duplicate event, onbekende diary/event, read-after-write en rollback bij een fout tijdens teruglezen;
- achievements-only voor een eigen extra;
- behoud en sanitisatie van goals/achievements en ongewijzigde gewone gossiptekst;
- bestaande/nieuwe taal, onbekende en dubbele taal, puntenlimiet, eigenaarschap en rollback van definitie plus koppeling;
- ontbrekende, ongeldige en onverwachte velden via route- en schemasuites;
- generieke HTTP 500 zonder SQL- of PDO-details;
- nul gecommitte writes bij geweigerde verzoeken.

Een echte MySQL-, WordPress- of browserintegratietest is lokaal niet uitgevoerd. PDO SQLite is niet beschikbaar; daarom gebruikt de nieuwe routetest een PDO-testdouble. De bestaande HTML Purifier dependency en DOM-extensie zijn wel werkelijk door de rich-texttests uitgevoerd.

### Werkelijk uitgevoerde resultaten

Geslaagd:

- `access_control_test.php`
- `access_control_route_coverage_test.php`
- `api_response_contract_test.php`
- `cache_update_management_test.php`
- `character_input_validation_test.php`
- `character_read_endpoints_test.php`
- `character_rich_text_test.php`
- `character_skills_actions_endpoints_test.php`
- `character_tie_endpoints_test.php`
- `character_traits_diary_languages_endpoints_test.php`
- `oidc_callback_test.php`
- `request_validation_test.php`
- `save_character_section_endpoint_test.php`
- `simple_character_endpoints_test.php`
- `simple_character_endpoints_batch2_test.php`
- `update_character_endpoint_test.php`
- PHP-syntaxcontrole van alle gewijzigde en nieuwe PHP-bestanden
- `git diff --check`

Niet uitgevoerd: `authenticated_user_test.php`; de test meldt zelf `SKIP` omdat de lokale PHP-binary geen PDO SQLite heeft. Er zijn geen mislukte uitvoerbare tests.

## Online checklist aetherapp-dev

- [ ] Log in als participant en verhoog/verlaag een trait van het eigen draft-character; heropen het character.
- [ ] Controleer dat een actief of vreemd character en een geheime trait worden geweigerd.
- [ ] Herhaal een toegestane traitactie als director en administrator.
- [ ] Maak en wijzig een diary-entry; controleer na heropenen vet/cursief/lijst/H4 in goals en achievements.
- [ ] Voer HTML in gossip in en controleer dat de tags letterlijk als tekst zichtbaar blijven.
- [ ] Controleer bij een eigen extra dat alleen achievements wijzigen.
- [ ] Voeg een bestaande en een nieuwe taal toe en heropen het character.
- [ ] Controleer dubbele taal, onvoldoende XP en een vreemd character.
- [ ] Herhaal diary en taal als director en administrator.
- [ ] Verstuur één schrijfrequest zonder of met een oud CSRF-token en controleer HTTP 403 zonder wijziging.
- [ ] Controleer in de netwerkweergave dat success- en foutresponses dezelfde velden houden en geen technische databasedetails bevatten.
