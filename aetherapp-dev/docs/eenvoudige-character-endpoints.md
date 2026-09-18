# Eenvoudige character-endpoints

## Doel en afbakening

Deze wijziging past het patroon van `saveCharacterSection.php` toe op routes met één duidelijke primaire lees- of schrijfbewerking. De actieve frontendcontracten zijn vóór de wijziging gecontroleerd. Alle vijf gemigreerde routes worden met `apiFetchJson()` als `POST` met een JSON-object aangeroepen. Uploads, rich text, transacties en samengestelde character-use-cases zijn niet gewijzigd.

## Inventaris

Onderstaande inventaris behandelt uitvoerbare routes in `api/characters`. Bestanden met alleen helpers, policies, schema's of repositories zijn geen endpoints en staan daarom niet in de tabel.

### 1. Eenvoudige leesroutes

| Route | Behandeling | Reden |
|---|---|---|
| `getCharacterList.php` | Gemigreerd | Eén lijstquery, met server-side rolfilter voor participants. |
| `getNewSkills.php` | Gemigreerd | Eén lijstquery na characteraccess; skillzichtbaarheid blijft door de vertrouwde databaserol bepaald. |
| `getDisciplineList.php` | Gemigreerd | Eén lijstquery na character- en skillaccess. |
| `getCharacterSections.php` | Uitgesteld | De route leest rich-textvelden en valt onder de expliciete rich-textuitsluiting van deze opdracht. |

### 2. Eenvoudige schrijfroutes met één primaire query

| Route | Behandeling | Reden |
|---|---|---|
| `newCharacter.php` | Gemigreerd | Eén prepared `INSERT`; eigenaar, type, status en auditvelden blijven server-side bepaald. |
| `deleteCharacterLanguage.php` | Gemigreerd | Eén prepared `DELETE` na auth, CSRF, schemavalidatie en eigenaarschapscontrole. De bestaande compatibiliteitscontrole voor een ontbrekend taalschema blijft behouden. |
| `saveCharacterSection.php` | Reeds eerder gemigreerde pilot | Het patroon en de bestaande test zijn behouden; deze rich-textroute is in deze opdracht niet gewijzigd. |

### 3. Upload-, multipart- en portretroutes

| Route | Reden voor uitstel |
|---|---|
| `uploadCharacterPortrait.php` | Multipartupload, bestandsvalidatie en bestandssysteemneveneffecten. |
| `deleteCharacterPortrait.php` | Portret- en bestandssysteemroute; expliciet uitgesloten. |

### 4. Complexe of samengestelde routes

| Routes | Reden voor uitstel |
|---|---|
| `getCharacter.php` | Samengesteld leesmodel met veel queries en domeinhelpers; expliciet uitgesloten. |
| `getCharacterActionEvents.php`, `getCharacterActionKnowledgeTargets.php` | Bouwen responses uit meerdere action-, skill-, burn- en knowledgequeries. |
| `getCharacterDiary.php`, `saveCharacterDiary.php` | Meerdere queries en rich-textvelden; expliciet buiten scope. |
| `getCharacterLanguageOptions.php`, `addCharacterLanguage.php` | Taal-, klasse-, trait-, skill- en puntenregels met meerdere queries. |
| `getCharacterTieOptions.php`, `getCharacterTies.php`, `saveCharacterTie.php`, `deleteCharacterTie.php` | Meerdere relatiequeries, wederkerige relaties en/of afhankelijke mutaties. |
| `getSkillSpecialisations.php` | Twee afhankelijke leesqueries en applicatiefiltering. |
| `AddNewSkill.php`, `addSkillSpecialisation.php`, `deleteSkillSpecialisation.php`, `updateSkill.php` | Combineren skillpolicy met meerdere reads/writes of geven na een mutatie een opnieuw opgebouwde lijst terug. |
| `revealCharacterActionKnowledge.php`, `useCharacterSkillAction.php` | Transactionele action- en knowledge-use-cases. |
| `buyCompanyShare.php`, `saveCompanyShare.php` | Companybeleid en meerdere afhankelijke queries/mutaties. |
| `deleteBankTransaction.php`, `saveBankTransfer.php` | Transactionele economiehandelingen; `saveBankTransfer.php` is expliciet uitgesloten. |
| `saveCharacterEconomySnapshot.php`, `deleteCharacterEconomySnapshot.php`, `saveCharacterSecuritiesPortfolio.php` | Snapshot-, effecten- en economieregels met transacties of meerdere afhankelijke mutaties. |
| `deleteCharacter.php` | Verwijdert meerdere gekoppelde records binnen een transactie. |
| `updateCharacter.php` | Meerdere velden, gezagsregels en gekoppelde mutaties; expliciet uitgesloten. |
| `updateTrait.php` | Meerdere actietakken, queries en spelregels; expliciet uitgesloten. |

Bij twijfel is de route in categorie 4 gebleven. Vooral een tweede query na een mutatie, een transactie of samengestelde domeinhelper was reden om niet alleen de controllerlaag te wijzigen.

## Behouden frontendcontracten

| Route | Request | Succesresponse |
|---|---|---|
| `getCharacterList.php` | `POST` JSON-object `{}` | Ongewijzigde JSON-lijst van toegankelijke characters, inclusief `portraitUrl`. |
| `getNewSkills.php` | `POST` JSON met `id` | Ongewijzigde JSON-lijst van beschikbare skills. |
| `getDisciplineList.php` | `POST` JSON met `idSkill` en `idCharacter` | Ongewijzigd object `{"options": [...]}`. |
| `newCharacter.php` | `POST` JSON volgens het bestaande `newCharacter`-schema | Ongewijzigd kaal numeriek character-ID. |
| `deleteCharacterLanguage.php` | `POST` JSON met `idCharacter` en `idCharacterLanguage` | Ongewijzigd `{"success": true}`. |

De routes gebruiken nu expliciet `aetherReadJsonObject()`. De actieve frontend verstuurt JSON en heeft de oude stille terugval naar `$_POST` niet nodig. Ongeldige of niet-object-JSON volgt daardoor het gedeelde HTTP 400-contract; veldvalidatie blijft HTTP 422 met `error` en `validationErrors`.

## Verwijderde duplicatie

In de vijf routes zijn waar van toepassing verwijderd:

- extra `session_start()`-aanroepen;
- de tijdelijke `aetherReadCharacterJsonRequest()`-facade;
- lokale casts en controles die al in `characterSchemas.php` staan;
- handmatig opgebouwde `http_response_code()`- en `json_encode()`-responses;
- losse vergelijkingen met `director` en `administrator` ten gunste van `aetherIsPrivilegedRole()`;
- het opnieuw ophalen van de PDO-verbinding terwijl `db.php` die al heeft geladen;
- een lokale characterquery in `deleteCharacterLanguage.php`, vervangen door de bestaande vaste access-recordfunctie.

Prepared statements en vaste server-side kolommen zijn behouden. Geen SQL-kolomnaam komt uit een requestkey.

## Resterend gebruik van de compatibiliteitsfacade

`characterRequestValidation.php` blijft nodig voor de nog niet gemigreerde routes. Dat betreft momenteel:

- skills en specialisaties;
- character detail, actions, diary, ties en language options;
- economie, companies, traits en complexe updates;
- portretupload en -verwijdering.

De facade kan per route worden verwijderd zodra het requestcontract is bevestigd en de route als afzonderlijke, beperkte migratie gedragsmatig wordt getest. Multipart blijft een expliciete aparte invoerroute houden.

## Tests

### Nieuwe gedragsdekking

`tests/simple_character_endpoints_test.php` voert de vijf routes in afzonderlijke PHP-processen uit met PDO-testdoubles. De test controleert:

- participant krijgt alleen de eigen characterlijst;
- director krijgt de volledige characterlijst;
- een vervalste sessierol verandert de databasegedreven rol niet;
- eigen en andermans characteraccess voor skill- en disciplinelijsten;
- geheime skills blijven voor participants afgeschermd;
- participant- en directorregels bij charactercreatie, inclusief server-side eigenaar, type, status en `createdBy`;
- geldige taalverwijdering door eigenaar en director;
- HTTP 401 zonder geldige gebruiker;
- HTTP 403 bij ongeldige CSRF en verkeerd eigenaarschap;
- HTTP 422 bij onbekende of ongeldige velden;
- frontendcompatibele succesresponses;
- geen databasewijziging bij geweigerde schrijfverzoeken.

`tests/character_input_validation_test.php` herkent nu naast de tijdelijke facade ook het directe patroon van requestlezer, moduleschema en generieke validator.

### Uitgevoerde eindcontroles

Uitgevoerd met PHP 8.4.25:

| Controle | Resultaat |
|---|---|
| `tests/simple_character_endpoints_test.php` | Geslaagd |
| `tests/save_character_section_endpoint_test.php` | Geslaagd |
| `tests/access_control_test.php` | Geslaagd |
| `tests/access_control_route_coverage_test.php` | Geslaagd |
| `tests/request_validation_test.php` | Geslaagd |
| `tests/character_input_validation_test.php` | Geslaagd |
| `tests/api_response_contract_test.php` | Geslaagd |
| `tests/character_rich_text_test.php` | Geslaagd |
| `tests/oidc_callback_test.php` | Geslaagd |
| PHP-syntaxcontrole van alle in deze opdracht gewijzigde PHP-bestanden | Geslaagd |
| `git diff --check` | Geslaagd; alleen informatieve LF/CRLF-waarschuwingen van Git |
| `tests/authenticated_user_test.php` | Niet uitgevoerd: de test meldt `SKIP` omdat PDO SQLite ontbreekt |

Na de eerste routebatch slaagden auth, routecoverage, requestvalidatie, responsecontract, de bestaande pilot-endpointtest en alle vijf syntaxcontroles. De statische character-validatietest herkende aanvankelijk alleen de oude facade-aanroep; na de gerichte aanpassing voor het directe schema-enginepatroon is die test opnieuw uitgevoerd en geslaagd.

De nieuwe endpointtest gebruikt PDO-testdoubles: prepared parameters en mutatievolgorde worden gecontroleerd, maar er is daarmee geen echte MySQL-integratie bewezen. Er is in deze opdracht geen browser-, WordPress-, Apache- of productie-integratietest uitgevoerd.
