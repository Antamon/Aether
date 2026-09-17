# Pilot dun endpoint: saveCharacterSection

Datum: 2026-09-17

## Request- en responsecontract

De actieve frontend roept `api/characters/saveCharacterSection.php` via `apiFetchJson()` aan met:

- methode `POST`;
- `Content-Type: application/json`;
- de bestaande `X-CSRF-Token`-header;
- exact de JSON-velden `idCharacter`, `section` en `content`.

Er is in de actieve frontend geen formulier- of multipartaanroep voor deze route. De oude stille terugval naar `$_POST` is daarom verwijderd. De route accepteert nu uitsluitend een JSON-object via `aetherReadJsonObject()`. Lege, ongeldige of niet-object-JSON is een requestformaatfout met HTTP 400. Dit sluit aan op het bestaande generieke requestcontract.

Het characterschema valideert:

- `idCharacter`: verplicht geheel getal, minimaal 1;
- `section`: verplicht en exact een van `personal_background`, `knowledge`, `nature`, `demeanour`;
- `content`: optionele getrimde rich-textstring, maximaal 16.000 tekens.

Veldvalidatie blijft HTTP 422 geven met `error: "Ongeldige invoer."` en `validationErrors`. De succesresponse blijft HTTP 200 met `{"success":true,"content":"..."}`. De frontend gebruikt de teruggegeven, server-side gesaniteerde `content` om de editor en weergave bij te werken.

## Route na de refactor

`saveCharacterSection.php` voert nu zichtbaar en in vaste volgorde uit:

1. PDO en de gedeelde/moduledependencies laden;
2. de vertrouwde gebruiker uit de server-side authenticatie laden;
3. CSRF controleren;
4. uitsluitend een JSON-object lezen;
5. rechtstreeks het schema `saveCharacterSection` met de generieke validator uitvoeren;
6. `aetherRequireCharacterAccess(..., 'edit')` met dezelfde `$currentUser` uitvoeren;
7. rich text met de bestaande helper saniteren en dezelfde MySQL-upsert met dezelfde prepared parameters uitvoeren;
8. het antwoord via de gedeelde responsehelper versturen.

Verwijderd zijn:

- de extra `session_start()`;
- de tijdelijke `characterRequestValidation.php`-facade voor deze route;
- de JSON-naar-`$_POST`-fallback;
- de lokale duplicaatlijst met secties;
- de tweede handmatige controle van character-ID en sectie;
- handmatige `http_response_code()`, `echo json_encode()` en `exit` voor route-responses.

`api/shared/response.php` heeft de kleine helper `aetherJsonValidationError()` gekregen. Die bewaart het bestaande HTTP 422-contract en de bestaande `JSON_UNESCAPED_UNICODE`-weergave. Andere characterroutes en de algemene compatibiliteitsfacade zijn niet gemigreerd.

## Gerichte endpointtest

`tests/save_character_section_endpoint_test.php` voert de echte routecode in afzonderlijke PHP-processen uit. Alleen de `db.php`-require wordt in een tijdelijke kopie vervangen door een PDO-testdouble. De tijdelijke route wordt na de test verwijderd.

Werkelijk gecontroleerd:

- toegestane update van het eigen player-character;
- exacte prepared parameters voor character-ID, sectie, gesaniteerde content en `updatedBy`;
- compatibele succesresponse;
- niet aangemeld: HTTP 401;
- ongeldige CSRF: HTTP 403;
- onbekend veld: HTTP 422;
- ongeldige sectie: HTTP 422;
- ontbrekend en numeriek ongeldig character-ID: HTTP 422;
- bestaand character van een andere participant: HTTP 403;
- onbekend object: HTTP 404;
- ongeldige JSON: HTTP 400;
- lege JSON met ingevulde `$_POST`: HTTP 400, dus geen stille formulierfallback;
- nul databasewrites voor ieder geweigerd scenario;
- de expliciete dependencies en het ontbreken van de verwijderde duplicatie;
- het JSON-contract van de actieve frontend.

## Uitgevoerde tests

| Controle | Resultaat |
|---|---|
| `tests/save_character_section_endpoint_test.php` | geslaagd |
| `tests/access_control_test.php` | geslaagd |
| `tests/authenticated_user_test.php` | gestart maar overgeslagen: PDO SQLite ontbreekt |
| `tests/access_control_route_coverage_test.php` | geslaagd |
| `tests/character_input_validation_test.php` | geslaagd |
| `tests/request_validation_test.php` | geslaagd |
| `tests/api_response_contract_test.php` | geslaagd |
| `tests/character_rich_text_test.php` | geslaagd |
| `tests/oidc_callback_test.php` | geslaagd |
| PHP-syntaxcontrole van gewijzigde PHP-bestanden | geslaagd |
| `git diff --check` | geslaagd; alleen informatieve LF/CRLF-waarschuwingen |

De PDO-testdouble bewijst de routevolgorde, toegangsbeslissingen, parameters, responses en het uitblijven van writes na weigering. Hij voert de MySQL-syntaxis, `ON DUPLICATE KEY UPDATE`, echte constraints en een echte WordPress-sessie niet tegen een database uit. Er is geen interactieve frontendtest uitgevoerd omdat in deze ontwikkelomgeving geen bestuurbare browser beschikbaar was. Er zijn geen productiegegevens gebruikt of gewijzigd.

Er is niets gepubliceerd, gepusht of gedeployed.
