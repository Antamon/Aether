# Character- en skillbeleid uit de authlaag

Datum: 2026-09-16

## Resultaat

Stap 4 van `docs/api-architectuur-en-hergebruik.md` is uitgevoerd zonder toegangsbeslissingen te wijzigen. `api/auth/accessControl.php` bevat nu alleen:

- sessiestart en het herstellen van de server-side identiteit;
- laden en eventueel veilig provisionen van de actuele gebruiker uit `tblUser`;
- de canonieke rollen `participant`, `director` en `administrator` en hun predicaten;
- `aetherRequireAuthenticatedUser()` en `aetherRequirePrivilegedUser()`;
- CSRF-generatie en -controle.

Het bestand heeft geen include of andere afhankelijkheid naar de personagemodule. `api/characters/characterAccess.php` laadt de generieke authlaag en gebruikt de reeds vertrouwde `$currentUser` die endpoints na authenticatie doorgeven. Het bestand leest geen `$_POST` of `$_SESSION` en introduceert geen tweede identiteitspad.

## Verplaatste functies

De volgende functies zijn met ongewijzigde naam, signature en beslislogica naar `api/characters/characterAccess.php` verplaatst:

| Functie | Verantwoordelijkheid |
|---|---|
| `aetherCanViewCharacter()` | privileged toegang of participant-eigenaarschap voor lezen |
| `aetherCanEditCharacter()` | privileged toegang of eigen character van type `player` voor wijzigen |
| `aetherCanEditDraftCharacter()` | privileged toegang of eigen bewerkbaar player-character in status `draft` |
| `aetherCanEditCharacterDiaryAchievements()` | volledige characterbewerking of achievements van een eigen `extra` |
| `aetherFetchCharacterAccessRecord()` | minimaal accessrecord uit `tblCharacter` ophalen |
| `aetherRequireCharacterAccess()` | bestaand accessrecord ophalen en `view`, `edit`, `edit_draft` of `diary_achievements` afdwingen |
| `aetherCanChangeCharacterAuthorityField()` | eigenaar, type en status beperken tot director/administrator |
| `aetherCanManageSkill()` | director/administrator of een skill met visibility `public` |
| `aetherRequireSkillAccess()` | skillbeleid afdwingen met het bestaande HTTP 403-contract |

Lokale policies voor bijvoorbeeld character-economie, talen, portretten, company shares en traits stonden al binnen de personagemodule. Ze zijn in deze stap niet herschreven of samengevoegd, omdat dat de uitgesloten complexe endpoints en bedrijfsregels zou raken.

## Includes en compatibiliteit

De achttien characterroutes die een van de verplaatste functies rechtstreeks gebruiken laden nu `characterAccess.php`. Omdat dit bestand zelf `accessControl.php` met `require_once` laadt, blijven de generieke authenticatie-, rol- en CSRF-functies in die routes beschikbaar zonder dubbele definities of een circulaire include.

Er zijn geen tijdelijke wrappers toegevoegd. Er bestaat ook geen tijdelijke terugwaartse include vanuit `accessControl.php` naar `characterAccess.php`. Daardoor hoeft voor deze stap later geen compatibiliteitslaag verwijderd te worden. Routes die uitsluitend generieke authenticatie of CSRF gebruiken, blijven rechtstreeks `accessControl.php` laden.

De responsehelper blijft ongewijzigd in `api/shared/response.php` en wordt door de generieke authlaag geladen.

## Gedragsbehoud

De bestaande beslissingen zijn behouden:

- een participant kan eigen characters bekijken;
- alleen een eigen character van type `player` kan algemeen worden gewijzigd;
- draftbewerkingen blijven beperkt tot een eigen bewerkbaar player-character met status `draft`;
- een participant kan alleen achievements van een eigen `extra` wijzigen;
- characters van andere deelnemers blijven ontoegankelijk;
- directors en administrators behouden volledige characterrechten en toegang tot geheime skills;
- een participant kan alleen skills met visibility `public` gebruiken;
- gezagsvelden blijven beperkt tot director en administrator;
- onbekende of afwijkend gespelde rollen krijgen geen rechten;
- de actuele databaserol blijft leidend boven een rolwaarde in de sessie.

## Tests

| Test | Resultaat |
|---|---|
| `tests/access_control_test.php` | geslaagd; eigen/andermans character, draft, extra-achievements, director, administrator, gezagsvelden, publieke/geheime skills, vervalste sessierol, CSRF en geen mutatie na weigering |
| `tests/access_control_route_coverage_test.php` | geslaagd; moved-policy-locatie, eenrichtingsdependency en alle rechtstreekse route-includes worden bewaakt |
| `tests/authenticated_user_test.php` | gestart maar overgeslagen: lokale PHP meldt dat PDO SQLite niet beschikbaar is |
| `tests/character_input_validation_test.php` | geslaagd |
| `tests/request_validation_test.php` | geslaagd |
| `tests/api_response_contract_test.php` | geslaagd |
| `tests/character_rich_text_test.php` | geslaagd |
| `tests/oidc_callback_test.php` | geslaagd |
| PHP-syntaxcontrole | geslaagd voor alle gewijzigde PHP-bestanden |
| `git diff --check` | geslaagd; alleen informatieve LF/CRLF-waarschuwingen |

De test voor een vervalste sessierol en het skillvisibilitybeleid gebruikt een PDO-testdouble en is daardoor werkelijk uitgevoerd zonder SQLite. De uitgebreidere database-integratietest blijft afzonderlijk niet uitgevoerd. Er zijn geen productiegegevens benaderd of gewijzigd.

Er is niets gepubliceerd, gepusht of gedeployed.
