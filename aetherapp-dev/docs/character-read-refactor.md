# Refactor van de centrale character-leesroutes

Datum: 18 september 2026

## Vastgestelde externe contracten

De actieve frontend gebruikt voor alle drie routes `apiFetchJson()` met `POST`, `application/json` en een JSON-object. De routes zijn sessiebeveiligde leesacties en vereisen daarom geen CSRF-token.

| Route | Requestvelden | Succesresponse |
|---|---|---|
| `getCharacter.php` | verplicht positief geheel getal `id` | het bestaande, vlakke volledige characterobject met onder meer basisvelden, skills, traits, punten, talen, portretrechten en voor bevoegde gebruikers economiegegevens |
| `getCharacterDiary.php` | verplicht positief geheel getal `idCharacter` | object met exact `entries` en `availableEvents`; diary- en eventvelden blijven op dezelfde plaats |
| `getCharacterSections.php` | verplicht positief geheel getal `idCharacter` | object met, in deze volgorde, `personal_background`, `knowledge`, `nature` en `demeanour`; ontbrekende records blijven lege strings |

`js/apiCharacter.js`, `js/diaryCharacter.js` en `js/backgroundCharacter.js` bevestigen deze contracten. Er is geen formulier- of querystringfallback nodig. Ongeldige of niet-object-JSON blijft HTTP 400; ontbrekende, ongeldige en onverwachte velden blijven HTTP 422. Een onbekend character geeft HTTP 404.

De foutresponses blijven een JSON-object met `error`. Een onverwachte database- of applicatiefout geeft nu voor alle drie routes een generieke HTTP 500 zonder exception-, PDO-, SQL- of tabeldetails. De technische fout wordt uitsluitend server-side gelogd.

## Gegevensbronnen en joins

### `getCharacter.php`

De route leest eerst `tblCharacter`. De leesservice bouwt daarna hetzelfde samengestelde model op uit:

- `tblLinkCharacterSkill` met `tblSkill`, waarbij geheime skills alleen voor director en administrator worden geselecteerd;
- `tblCharacterSpecialisation` met `tblSkillSpecialisation`;
- `tblLinkSkillType` met `tblSkillType`;
- `tblUser` voor de bestaande `nameParticipant`-waarde;
- de bestaande trait-, punten-, talen-, portret- en economyhelpers.

De SQL en veldvolgorde van deze gegevens zijn behouden. De bestaande helpers blijven hun huidige tabellen voor traits, events, talen, banktransacties, effecten, snapshots en companyshares gebruiken. Deze batch heeft hun queries en bedrijfsregels niet gewijzigd.

### `getCharacterDiary.php`

- entries: `tblCharacterDiary` met een inner join naar `tblEvent`, geordend op startdatum en event-ID aflopend;
- beschikbare events: `tblEvent`, behalve events die al in `tblCharacterDiary` voor het character staan.

Geen diaryrecords levert `entries: []`; geen beschikbare events levert `availableEvents: []`.

### `getCharacterSections.php`

De route leest `section` en `content` uit `tblCharacterSection`. Alleen de vier vastgelegde character-rich-textsections komen in de response. Ontbrekende rijen blijven een lege string en onbekende sectionnamen worden niet teruggegeven.

## Toegang en gegevensbescherming

Alle routes laden eerst de actuele gebruiker via `aetherRequireAuthenticatedUser()`. Daardoor komen identiteit en rol uit `tblUser`; een rol uit de browser of een vervalste sessierol wordt niet vertrouwd. Dezelfde `$currentUser` wordt daarna aan de characterpolicy en leesservice doorgegeven.

| Rol | Eigen character | Character van een ander | Geheime skills |
|---|---|---|---|
| participant | lezen toegestaan | HTTP 403 | niet opgenomen |
| director | lezen toegestaan | lezen toegestaan | opgenomen |
| administrator | lezen toegestaan | lezen toegestaan | opgenomen |

Voor `getCharacter.php` wordt het volledige basisrecord tevens als toegangsrecord gebruikt, zodat geen dubbele characterquery nodig is. Diary en sections gebruiken de bestaande `aetherRequireCharacterAccess(..., 'view')`; deze routes moeten ook zonder resultaatrijen onderscheid kunnen maken tussen een onbekend character, geweigerde toegang en lege moduledata.

Het huidige responsebeleid is behouden: gewone charactertekst zoals `firstName` en gossip blijft gewone tekst in de JSON-response en wordt door de frontend met tekstvelden of `textContent` verwerkt. Alleen de vier sections en `goals`/`achievements` zijn rich text. De leesservice saniteert deze velden met de bestaande characterallowlist voordat ze de browser bereiken. Onbekende beheer-sections worden niet opgenomen. Er is geen sanitizer, editor of frontendcode gewijzigd.

## Nieuwe verantwoordelijkheden

### Dunne endpoints

`getCharacter.php`, `getCharacterDiary.php` en `getCharacterSections.php` doen alleen nog het volgende:

1. dependencies en PDO laden;
2. de vertrouwde gebruiker laden;
3. expliciet een JSON-object lezen;
4. het bestaande routeschema uitvoeren;
5. toegang tot het concrete character controleren;
6. de passende leesservice aanroepen;
7. via de gedeelde responsehelper antwoorden;
8. onverwachte fouten generiek afhandelen.

De routes gebruiken de tijdelijke `characterRequestValidation.php`-facade niet meer.

### `characterReadRepository.php`

Deze gerichte repository bevat alleen de PDO-query's voor de drie leesmodellen: basischaracter, skills, specialisaties, skilltypes, participantnaam, diaryentries, beschikbare diaryevents en sectionrijen. De updategerichte `characterRepository.php` is niet uitgebreid tot een allesomvattende repository.

### `characterReadService.php`

De service bevat de bestaande response-opbouw:

- verrijking van skills met types en specialisaties;
- samenstelling van het volledige characterleesmodel via bestaande helpers;
- typecasts en rich-textsanitisatie van diarydata;
- vaste sectionresponse en rich-textsanitisatie.

De gebruikers-ID voor capabilitybeslissingen komt nu rechtstreeks uit de reeds vertrouwde `$currentUser`, in plaats van opnieuw uit de sessie te worden gelezen. Dit verandert de bedoelde rechten niet.

## Gewijzigde bestanden

- `api/characters/getCharacter.php`: dun endpoint voor het volledige characterleesmodel.
- `api/characters/getCharacterDiary.php`: dun diary-leesendpoint.
- `api/characters/getCharacterSections.php`: dun section-leesendpoint.
- `api/characters/characterReadRepository.php`: gerichte PDO-readquery's.
- `api/characters/characterReadService.php`: samenstelling en sanitization van de drie responsemodellen.
- `tests/character_read_endpoints_test.php`: uitvoerbare route-, policy-, contract- en sanitizationtests met PDO-testdouble.
- `tests/character_input_validation_test.php`: bewaakt de drie directe schema-aanroepen.
- `tests/character_rich_text_test.php`: volgt sanitization via de nieuwe leesservice.

Frontendcode, schemas, writes en databaseopbouw zijn niet gewijzigd.

## Testdekking en resultaat

`tests/character_read_endpoints_test.php` kopieert de echte routes en PHP-dependencies naar een tijdelijke fixture en vervangt uitsluitend `db.php` door een PDO-testdouble. De test voert ieder request in een apart PHP-proces uit en controleert:

- participant leest het eigen character en wordt bij een ander character geweigerd;
- director en administrator behouden toegang;
- rollen komen uit het gesimuleerde `tblUser`, ondanks een bewust vervalste sessierol;
- onbekende characters, ontbrekende en ongeldige ID's en onverwachte velden;
- geen writes vanuit een leesroute of geweigerd request;
- publieke en geheime skills per rol;
- de diary-nesting, veldvolgorde, integercasts en beschikbare events;
- de vaste sectionvelden, lege ontbrekende sections en het niet lekken van een onbekende beheer-section;
- toegestane rich text, verwijderde scripts/eventhandlers en ongewijzigde gewone gossiptekst;
- generieke HTTP 500 zonder technische details.

Uitgevoerd met PHP 8.4.25:

| Controle | Resultaat |
|---|---|
| `tests/character_read_endpoints_test.php` | geslaagd |
| `tests/update_character_endpoint_test.php` | geslaagd |
| `tests/simple_character_endpoints_test.php` | geslaagd |
| `tests/simple_character_endpoints_batch2_test.php` | geslaagd |
| `tests/save_character_section_endpoint_test.php` | geslaagd |
| `tests/character_input_validation_test.php` | geslaagd |
| `tests/access_control_test.php` | geslaagd |
| `tests/access_control_route_coverage_test.php` | geslaagd |
| `tests/request_validation_test.php` | geslaagd |
| `tests/api_response_contract_test.php` | geslaagd |
| `tests/character_rich_text_test.php` | geslaagd |
| `tests/oidc_callback_test.php` | geslaagd |
| `tests/authenticated_user_test.php` | niet uitgevoerd: test meldt `SKIP` omdat PDO SQLite ontbreekt |
| PHP-syntaxcontrole van alle gewijzigde PHP-bestanden | geslaagd |
| `git diff --check` | geslaagd |

De losse lokale PHP-binary heeft geen `mbstring`. `getCharacter` gebruikte `mb_strtolower()` al voor deze refactor; de geïsoleerde endpointfixture gebruikt daarom alleen tijdens de test een `strtolower()`-fallback. Dit bewijst niet de echte MySQL-queryuitvoering, databaseconstraints, WordPress-sessie-integratie of browserweergave. Er zijn geen productie- of ontwikkelingsdatabasegegevens geraakt.

## Online testchecklist voor `aetherapp-dev`

### Sheet

- [ ] Open als participant het eigen character en controleer basisvelden, publieke skills, traits, punten, talen en portret.
- [ ] Controleer met een gemanipuleerd character-ID dat een character van een ander HTTP 403 geeft.
- [ ] Open hetzelfde character als director en administrator en controleer dat geheime skills en bestaande beheerfuncties zichtbaar blijven.
- [ ] Controleer economygegevens voor de rollen die ze volgens de bestaande UI mogen zien.

### Diary

- [ ] Open een diary met entries en beschikbare events; titels, datums en volgorde moeten gelijk blijven.
- [ ] Controleer dat goals en achievements opgemaakt worden weergegeven en gossip met HTML-achtige tekst letterlijk blijft.
- [ ] Open een character zonder diarydata en controleer de lege toestand.

### Background

- [ ] Open personal background en knowledge met bestaande legacy `div`/`span`-inhoud; opmaak moet leesbaar en veilig zijn.
- [ ] Open een character zonder opgeslagen sections en controleer de lege velden.

### Personality

- [ ] Open nature en demeanour en controleer toegestane vet-, cursief-, lijst-, alinea- en H4-opmaak.
- [ ] Controleer dat scripts, inline eventhandlers en onbekende sections niet in de DOM verschijnen.

Voor alle tabs: herhaal minimaal één eigen-charactercontrole als participant en één ander-charactercontrole als director en administrator. Controleer in de browsernetworktab dat de drie routes dezelfde JSON-velden en HTTP-statussen blijven leveren.
