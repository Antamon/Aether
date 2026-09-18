# Refactor van character-ties

Datum: 18 september 2026

## Vastgesteld frontend- en API-contract

De actieve frontend staat in `js/backgroundCharacter.js` en gebruikt `apiFetchJson()`.

| Route | Methode en input | Succesresponse |
|---|---|---|
| `getCharacterTies.php` | `POST`, `application/json`, `{ "idCharacter": int }` | kale JSON-lijst van ties |
| `getCharacterTieOptions.php` | `GET`, geen body | kale JSON-lijst van mogelijke doelcharacters |
| `saveCharacterTie.php` | `POST`, `application/json`, CSRF-header; `idCharacter`, `idTie`, `idOtherCharacter`, `relationType`, `description` | `{ "success": true, "ties": [...], "syncedAddress": object|null }` |
| `deleteCharacterTie.php` | `POST`, `application/json`, CSRF-header; `idCharacter`, `idTie` | `{ "success": true, "ties": [...] }` |

Bij toevoegen verstuurt de frontend bewust `idTie: null`; bij wijzigen bevat dit veld het bestaande tie-ID. Na toevoegen of wijzigen vervangt de frontend de zichtbare lijst door `result.ties`, verwerkt zij een eventueel `syncedAddress` in het huidige character en sluit zij de editor. Na bevestigde verwijdering gebruikt zij eveneens `result.ties` en sluit zij zo nodig de editor. De bestaande fallback die de lijst nogmaals ophaalt blijft ongewijzigd.

De tielijst behoudt exact deze velden en volgorde:

`id`, `idOtherCharacter`, `relationType`, `relationTypeLabel`, `description`, `otherName`, `firstName`, `lastName`, `otherClass`, `otherRecurringIncomeTotal`, `otherMiddleClassLivingStandardIncome`, `otherUpperClassLivingStandardTier`, `portraitUrl`, `hasReverseSuperior`, `hasReverseLandlord`, `hasReverseHouseholdStaff`, `hasReverseSpouse`.

De optielijst behoudt `id`, `displayName`, `class`, `recurringIncomeTotal` en `canBeLandlord`.

De foutcontracten gebruiken het bestaande `error`-veld:

- HTTP 400 voor ongeldige JSON en characterspecifieke regels zoals zelfkoppeling of een ongeschikte landlord;
- HTTP 401 wanneer geen actuele gebruiker uit `tblUser` kan worden geladen;
- HTTP 403 voor CSRF, verkeerd eigenaarschap of een participant die een niet-actief/non-player doel probeert te koppelen;
- HTTP 404 voor een onbekend broncharacter, doelcharacter of tie;
- HTTP 422 voor ontbrekende, verkeerd getypeerde, ongeldige of onverwachte velden;
- HTTP 500 met uitsluitend de bestaande generieke routemelding en zonder PDO-, SQL- of tabeldetails.

## Rechten en tie-opties

Identiteit en rol komen uitsluitend uit `aetherRequireAuthenticatedUser()` en dus uit de actuele `tblUser`-rij. Dezelfde `$currentUser` wordt aan policies en servicefuncties doorgegeven.

| Handeling | participant | director | administrator |
|---|---|---|---|
| Ties bekijken | alleen van een eigen character | ieder character | ieder character |
| Tie toevoegen/wijzigen/verwijderen | alleen op een eigen character van type `player` | ieder character | ieder character |
| Doelcharacter kiezen | alleen `player` en `active` | alle characters, inclusief extra/inactive | alle characters, inclusief extra/inactive |

De optieroute zelf retourneert voor een participant alle actieve player-characters, inclusief het huidige character. De frontend filtert het huidige character uit de keuzelijst. De server blijft zelfkoppeling afzonderlijk weigeren, zodat een rechtstreeks API-request die frontendfilter niet kan omzeilen.

## Behouden bedrijfsregels

- De acht bestaande relatietypes blijven ongewijzigd: `superior`, `dependent`, `landlord`, `household_staff`, `spouse`, `ally`, `adversary` en `person_of_interest`.
- `description` blijft gewone, getrimde tekst met maximaal 255 tekens en wordt in de frontend via `textContent` weergegeven.
- Een character kan geen tie met zichzelf krijgen.
- Een participant kan geen inactive of non-player doelcharacter koppelen.
- `household_staff` vereist dat het broncharacter volgens de bestaande economieregels landlord kan zijn.
- `landlord` vereist dat het doelcharacter landlord kan zijn.
- De applicatielaag voorkomt geen dubbele combinatie van bron, doel en relatietype. Een tweede insert blijft dus mogelijk voor zover de productiedatabase geen afzonderlijke constraint oplegt.
- Bij een bevestigde combinatie `household_staff`/omgekeerde `landlord`, of `landlord`/omgekeerde `household_staff`, wordt het bestaande adres in dezelfde richting gekopieerd als vóór de refactor.
- Een gewone insert of update doet één tie-write. Een bevestigde landlord/household-staffkoppeling doet daarnaast één adreswrite binnen dezelfde transactie.
- Verwijderen wist alleen de tie met het opgegeven ID die bij het opgegeven broncharacter hoort.

## Vastgestelde en herstelde concrete fouten

De frontend verstuurde bij toevoegen `idTie: null`, terwijl het schema alleen integers accepteerde. `saveCharacterTie` accepteert daarom nu expliciet `null` of een integer vanaf 0. Bestaande numerieke requests blijven geldig.

Een update met een onbekend tie-ID of een tie van een ander broncharacter voerde voorheen een UPDATE met nul getroffen rijen uit, maar kon daarna wel de adressynchronisatie uitvoeren en een succesresponse geven. De service controleert nu vóór de transactie of de tie bij het broncharacter hoort. Bij ontbreken volgt HTTP 404 zonder write of ander neveneffect. Dezelfde controle gebeurt vóór een DELETE, zodat een geweigerde verwijdering geen muterend statement uitvoert.

De oude schrijfroute kon exceptiondetails in het veld `detail` terugsturen. Onverwachte fouten geven nu alleen de generieke foutmelding; technische details worden uitsluitend server-side gelogd.

## Architectuur en verantwoordelijkheden

### Dunne endpoints

De vier endpoints doen alleen nog:

1. dependencies en PDO laden;
2. de vertrouwde gebruiker laden;
3. bij writes CSRF controleren;
4. de expliciete inputbron lezen;
5. het bestaande characterschema uitvoeren;
6. toegang tot het broncharacter controleren;
7. de tie-service aanroepen;
8. via de gedeelde responsehelper antwoorden.

De drie JSON-routes gebruiken rechtstreeks `aetherReadJsonObject()`, `aetherValidateInput()` en `aetherCharacterRequestSchema()`. De GET-optieroute combineert expliciet queryvelden met `aetherReadFormFields()` om het bestaande lege GET/form-contract te valideren. De tijdelijke `characterRequestValidation.php`-facade is voor deze routes niet meer nodig.

### `characterTieRepository.php`

Bevat alleen prepared PDO-query's voor:

- de tielijst met bestaande reverse-tie-indicatoren;
- beschikbare doelcharacters;
- doelcharacter en tie-eigenaarschap;
- insert, update en delete;
- reverse-tiecontrole;
- adres lezen en bijwerken.

Er is geen algemene repositorybasis of dynamische kolommapping toegevoegd.

### `characterTieService.php`

Bevat alleen samenhangende tieverantwoordelijkheden:

- vaste labels en de bestaande response-opbouw;
- opbouw van opties volgens de vertrouwde rol;
- zelfkoppeling, doeltoegang en landlordvoorwaarden;
- controle dat een gewijzigde of verwijderde tie bij het broncharacter hoort;
- transactie voor tie-write plus eventuele adreswrite;
- de bestaande richting van adressynchronisatie.

`AetherCharacterTieException` draagt alleen een verwachte HTTP-status en bestaande gebruikersmelding terug naar het endpoint.

## Gewijzigde bestanden

- `api/characters/getCharacterTies.php`: dun JSON-leesendpoint.
- `api/characters/getCharacterTieOptions.php`: dun GET-optie-endpoint.
- `api/characters/saveCharacterTie.php`: dun transactioneel schrijfendpoint.
- `api/characters/deleteCharacterTie.php`: dun delete-endpoint.
- `api/characters/characterTieRepository.php`: tie- en adresquery's.
- `api/characters/characterTieService.php`: tiebeleid, responses en transactie.
- `api/characters/characterSchemas.php`: `idTie: null` afgestemd op het actieve createcontract.
- `tests/character_tie_endpoints_test.php`: stateful gedragstests voor de echte routes.
- `tests/character_input_validation_test.php`: volgt de directe gedeelde parsing en validatie.

Frontendcode, tabellen en andere charactermodules zijn niet gewijzigd.

## Testdekking en resultaten

`tests/character_tie_endpoints_test.php` kopieert de echte routes en PHP-dependencies naar een tijdelijke fixture. Alleen `db.php` wordt vervangen door een stateful PDO-testdouble. Daardoor kan een tie in één routecall worden toegevoegd of verwijderd en in een volgende echte routecall opnieuw worden gelezen.

De test dekt:

- eigen tielijst, veldvolgorde en gewone tekstweergave;
- participantweigering op andermans character;
- director- en administratorrechten;
- participant- en privileged-optielijsten;
- toevoegen met de echte frontendwaarde `idTie: null`;
- wijzigen, verwijderen en opnieuw lezen;
- exact prepared parameters voor insert, update en delete;
- bestaand duplicaatgedrag;
- onbekend broncharacter, doelcharacter en tie;
- zelfkoppeling en een niet-toegestaan participantdoel;
- ontbrekende, ongeldige en onverwachte velden en ongeldige JSON;
- ontbrekende en ongeldige CSRF;
- nul writes bij iedere weigering;
- commit van tie plus adres en rollback van beide bij een fout;
- exacte succesvormen en generieke HTTP 500-responses zonder databasedetails.

Uitgevoerd met PHP 8.4.25:

| Controle | Resultaat |
|---|---|
| `tests/character_tie_endpoints_test.php` | geslaagd |
| `tests/character_read_endpoints_test.php` | geslaagd |
| `tests/update_character_endpoint_test.php` | geslaagd |
| `tests/simple_character_endpoints_test.php` | geslaagd |
| `tests/simple_character_endpoints_batch2_test.php` | geslaagd |
| `tests/save_character_section_endpoint_test.php` | geslaagd |
| `tests/access_control_test.php` | geslaagd |
| `tests/access_control_route_coverage_test.php` | geslaagd |
| `tests/request_validation_test.php` | geslaagd |
| `tests/character_input_validation_test.php` | geslaagd |
| `tests/api_response_contract_test.php` | geslaagd |
| `tests/character_rich_text_test.php` | geslaagd |
| `tests/oidc_callback_test.php` | geslaagd |
| `tests/authenticated_user_test.php` | niet uitgevoerd: test meldt `SKIP` omdat PDO SQLite ontbreekt |
| PHP-syntaxcontrole van alle gewijzigde PHP-bestanden | geslaagd |
| `git diff --check` | geslaagd |

De PDO-testdouble controleert querykeuze, parameters, state, commit en rollback, maar bewijst geen echte MySQL-constraints, triggers of transacties. Er is lokaal geen WordPress- of interactieve browsertest uitgevoerd. Er zijn geen productie- of ontwikkelingsdatabasegegevens geraakt.

## Online checklist voor `aetherapp-dev`

- [ ] Open Background als participant en controleer ties van het eigen player-character.
- [ ] Manipuleer `idCharacter` naar een character van een ander en controleer HTTP 403.
- [ ] Controleer dat de participantopties alleen actieve player-characters tonen en het huidige character niet selecteerbaar is.
- [ ] Voeg een tie toe, controleer dat de editor sluit en herlaad de pagina.
- [ ] Wijzig doel, type en omschrijving en controleer de bijgewerkte lijst na herladen.
- [ ] Verwijder een tie na de bevestigingsdialoog en controleer dat zij na herladen wegblijft.
- [ ] Controleer dat HTML-achtige tekst in de omschrijving letterlijk verschijnt.
- [ ] Probeer een zelfkoppeling en een onbekend tie-ID via een rechtstreeks request; beide mogen geen data wijzigen.
- [ ] Meld aan als director en administrator en controleer ties op een character van een ander en opties met extra/inactive characters.
- [ ] Maak in testdata een wederzijdse landlord/household-staffcombinatie en controleer tie, adres en `syncedAddress` samen.
- [ ] Verstuur een schrijfrequest zonder of met een fout CSRF-token en controleer HTTP 403 zonder wijziging.
- [ ] Controleer in de networktab de exacte succesvelden van alle vier routes en generieke fouten zonder technisch `detail`-veld.
