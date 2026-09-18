# Refactor van `updateCharacter.php`

Datum: 18 september 2026

## Vastgesteld API-contract

De actieve frontend roept `api/characters/updateCharacter.php` aan via `apiFetchJson()`:

- methode: `POST`;
- content-type: `application/json`;
- authenticatie: bestaande sessie/WordPress-koppeling;
- CSRF: bestaande `X-CSRF-Token`-header;
- body: een JSON-object met verplicht `id` en één of meer te wijzigen velden.

De huidige UI verstuurt meestal één wijziging per request. Autosave verstuurt `id` plus het gewijzigde formulierveld. Navigatie verstuurt afzonderlijk `idUser`, `type`, `state` of `experienceToTrait`; gezondheids- en economieschermen versturen eveneens één veld. De server blijft meerdere toegestane velden in één request ondersteunen.

De succesresponse blijft HTTP 200 met uitsluitend het JSON-getal dat gelijk is aan `PDOStatement::rowCount()`, bijvoorbeeld `1`. De frontend gebruikt die waarde niet inhoudelijk en wacht alleen op een succesvolle response.

Het foutcontract is:

- HTTP 400 voor ongeldige JSON en bestaande characterspelregels zoals een lege update, te lage totale gezondheid of onvoldoende ervaringspunten;
- HTTP 401 wanneer geen actuele gebruiker uit `tblUser` geladen kan worden;
- HTTP 403 voor een ongeldig CSRF-token, verkeerd eigenaarschap, onvoldoende rolrechten of verboden autoriteitsvelden;
- HTTP 404 voor een onbekend character;
- HTTP 422 voor ontbrekende velden, verkeerde types, waarden buiten schemagrenzen, ongeldige enums en onbekende velden;
- HTTP 500 met alleen `{"error":"Kon character niet bijwerken."}` bij een onverwachte fout.

Er is geen formulierfallback meer. Lege, ongeldige of niet-object-JSON gebruikt het bestaande gedeelde HTTP 400-contract.

## Requestvelden

De bestaande schema's zijn niet inhoudelijk gewijzigd. `updateCharacter` accepteert:

- verplicht: `id`;
- gewone charactertekst: `firstName`, `lastName`, `birthPlace`, `nationality`, `stateRegisterNumber`, `street`, `houseNumber`, `municipality`, `postalCode`, `title`;
- overige basisvelden: `class`, `birthDate`, `maritalStatus`, `experienceToTrait`;
- gezondheid: `physicalHealth`, `mentalHealth`, `physicalHealthFree`, `mentalHealthFree`;
- economie: `bankaccount`, `securitiesaccount`;
- autoriteitsvelden: `idUser`, `type`, `state`;
- auditvelden die uitsluitend worden herkend om ze expliciet te weigeren: `createdBy`, `createdAt`.

De precieze types, lengtes, numerieke grenzen en enums blijven vastgelegd in `characterSchemas.php` en `docs/invoervalidatie-characters.md`. Geen van deze velden is rich text; deze refactor verandert daarom geen rich-textsanitization.

## Behouden rechten en bedrijfsregels

Dezelfde uit `tblUser` geladen `$currentUser` wordt voor alle beslissingen gebruikt.

| Wijziging | Toegang |
|---|---|
| Gewone basisvelden | Director en administrator; participant alleen op een eigen character van type `player`. |
| `idUser`, `type`, `state` | Alleen director en administrator. |
| `createdBy`, `createdAt` | Nooit via deze API. |
| `class` | Director en administrator; participant alleen op een eigen `player`-character in `draft`. |
| `bankaccount`, `securitiesaccount` | Alleen director en administrator en alleen wanneer het character niet in `draft` staat. |
| `physicalHealth`, `mentalHealth` | Director en administrator; participant alleen op een eigen `player`-character in `draft`. |
| `physicalHealthFree`, `mentalHealthFree` | Alleen director en administrator. |

Verder blijven behouden:

- een lege `birthDate` wordt niet naar de database geschreven;
- de totale fysieke en mentale gezondheid mag niet onder 1 komen;
- wijzigingen aan betaalde gezondheid of `experienceToTrait` mogen het ervaringsbudget niet overschrijden;
- een overgang van `draft` naar een andere status initialiseert het banksaldo wanneer geen expliciet banksaldo werd meegestuurd;
- een klassewijziging verwijdert gekoppelde niet-algemene class traits;
- gewijzigde adresvelden synchroniseren bevestigde household staff wanneer het character landlord is;
- de bestaande adres-synchronisatie blijft best-effort: een fout wordt gelogd en breekt de hoofdupdate niet af;
- de hoofdupdate en gekoppelde trait-/adresschrijfacties blijven binnen dezelfde transactie;
- alle updatekolommen komen uit een vaste server-side mapping.

## Verantwoordelijkheden vóór en na de refactor

Voorheen bevatte `updateCharacter.php` requestparsing, validatie, authenticatie, alle veldrechten en spelregels, SQL-opbouw, vier repositoryqueries, transactiebeheer, neveneffecten en responses.

Na de refactor orkestreert het endpoint alleen:

1. dependencies en PDO laden;
2. de vertrouwde gebruiker laden;
3. CSRF controleren;
4. een JSON-object lezen;
5. het bestaande `updateCharacter`-schema uitvoeren;
6. het character ophalen en algemene objecttoegang controleren;
7. voorbereiding en transactionele update aan de characterservice doorgeven;
8. het resultaat via de gedeelde JSON-responsehelper versturen.

### `characterService.php`

- `aetherPrepareCharacterUpdate()` voert characterspecifieke veldrechten, normalisatie, gezondheid en ervaringsregels uit en levert de definitieve velden voor opslag.
- `aetherApplyCharacterUpdate()` beheert de transactie en de bestaande gekoppelde class-trait- en adressynchronisatie.
- `AetherCharacterUpdateException` draagt uitsluitend een verwachte HTTP-status en een bestaande gebruikersmelding naar het endpoint. Onverwachte technische exceptions worden niet aan de browser doorgegeven.

### `characterRepository.php`

- `aetherFetchCharacterForUpdate()` leest het benodigde huidige record;
- `aetherCharacterUpdateColumnMap()` bevat de vaste requestveld-naar-databasekolommapping;
- `aetherUpdateCharacterRecord()` voert de prepared hoofdupdate uit;
- de overige functies bevatten uitsluitend SQL voor adresophaling/-synchronisatie, landlordcontrole en het verwijderen van niet-passende class traits.

Er is geen generieke repositorybasis, ORM, controllerklasse of nieuw framework toegevoegd.

## Gewijzigde bestanden

- `api/characters/updateCharacter.php`: dunne orkestratielaag met gedeelde helpers.
- `api/characters/characterService.php`: updatebeleid, normalisatie, spelregels en transactievolgorde.
- `api/characters/characterRepository.php`: vaste mapping en updategerelateerde PDO-query's.
- `tests/update_character_endpoint_test.php`: uitvoerbare route- en servicetest met geïsoleerde PDO-testdouble.
- `tests/character_input_validation_test.php`: bewaakt het directe schema en de verplaatste vaste mapping.
- `tests/access_control_route_coverage_test.php`: bewaakt autoriteitsvelden op hun nieuwe servicelocatie.
- `docs/update-character-refactor.md`: contract, verantwoordelijkheden, regels, tests en online checklist.

Er is geen frontendcode en geen characterschema gewijzigd.

## Gedragstests

`tests/update_character_endpoint_test.php` kopieert de werkelijke route en haar PHP-dependencies naar een tijdelijke fixture, vervangt uitsluitend `db.php` door een PDO-testdouble en voert elk scenario in een apart PHP-proces uit. Daardoor worden de echte request-, auth-, policy-, service-, repository- en responsecode uitgevoerd zonder een productie- of ontwikkelingsdatabase te raken.

De test dekt:

- geldige eigen wijziging met getrimde waarde en exacte prepared SQL-parameters;
- toegestane autoriteitswijzigingen door director en administrator;
- een vervalste sessierol en een ander participant-character;
- ontbrekende en ongeldige CSRF;
- ongeldige JSON;
- ontbrekend, ongeldig en onbekend character-ID;
- een request zonder te wijzigen velden;
- onbekende velden en een gemanipuleerde SQL-kolomnaam;
- fout datatype, maximale lengte en enum;
- alle drie autoriteitsvelden en beide auditvelden voor een participant;
- actieve versus draft-klassewijziging;
- verboden participantwijziging van het banksaldo;
- class-traitneveneffect binnen de transactie;
- rollback en een generieke HTTP 500 zonder SQL-, PDO- of tabeldetails;
- nul gecommitte writes bij ieder geweigerd verzoek;
- het actieve frontendcontract en de dunne endpointstructuur.

## Testresultaten en lokale beperkingen

Uitgevoerd met PHP 8.4.25:

| Controle | Resultaat |
|---|---|
| `tests/update_character_endpoint_test.php` | Geslaagd |
| `tests/simple_character_endpoints_test.php` | Geslaagd |
| `tests/simple_character_endpoints_batch2_test.php` | Geslaagd |
| `tests/save_character_section_endpoint_test.php` | Geslaagd |
| `tests/character_input_validation_test.php` | Geslaagd |
| `tests/access_control_test.php` | Geslaagd |
| `tests/access_control_route_coverage_test.php` | Geslaagd |
| `tests/request_validation_test.php` | Geslaagd |
| `tests/api_response_contract_test.php` | Geslaagd |
| `tests/character_rich_text_test.php` | Geslaagd |
| `tests/oidc_callback_test.php` | Geslaagd |
| PHP-syntaxcontrole van alle in deze refactor gewijzigde PHP-bestanden | Geslaagd |
| `git diff --check` | Geslaagd; alleen informatieve LF/CRLF-waarschuwingen van Git |
| `tests/authenticated_user_test.php` | Niet uitgevoerd: de test meldt `SKIP` omdat PDO SQLite ontbreekt |

De nieuwe testdouble controleert routevolgorde, policies, SQL-vorm, prepared parameters, transacties, rollback en responses. Hij bewijst geen echte MySQL-syntaxis, constraints, triggers of WordPress-sessie-integratie. Er is lokaal geen interactieve browsertest uitgevoerd.

## Online testchecklist voor `aetherapp-dev`

- [ ] Meld aan als participant en wijzig voornaam, adres en een ander gewoon veld van het eigen player-character.
- [ ] Controleer dat een participant geen character van een andere gebruiker kan wijzigen via een gemanipuleerd request.
- [ ] Controleer dat een participant `idUser`, `type`, `state`, `createdBy` en `createdAt` niet kan wijzigen.
- [ ] Wijzig als participant de klasse van een eigen draft-character en controleer dat niet-passende class traits verdwijnen.
- [ ] Controleer dat dezelfde klassewijziging op een actief character HTTP 403 geeft.
- [ ] Meld aan als director en administrator en wijzig eigenaar, type en status.
- [ ] Controleer bij activatie van een draft-character de bestaande automatische banksaldoberekening.
- [ ] Wijzig als bevoegde gebruiker bank- en effectensaldo van een niet-draft-character.
- [ ] Controleer betaalde en gratis gezondheidswijzigingen voor participant en beheerrollen.
- [ ] Wijzig het adres van een landlord met bevestigde household staff en controleer de bestaande synchronisatie.
- [ ] Verstuur een request zonder CSRF-token en controleer HTTP 403 zonder databasewijziging.
- [ ] Verstuur ongeldige JSON en controleer HTTP 400.
- [ ] Verstuur een onbekend veld en controleer HTTP 422.
- [ ] Controleer dat een geslaagde autosave dezelfde kale numerieke response teruggeeft en dat de UI normaal blijft werken.
