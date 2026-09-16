# API-architectuur en hergebruik

Datum: 16 september 2026

## Samenvatting

De nieuwe toegangscontrole en character-validatie vormen een bruikbare en veilige tussenstap. De belangrijkste beveiligingskeuzes zijn goed: de server haalt identiteit en rol uit de sessie en database, characterrechten worden ook op objectniveau gecontroleerd, schrijfroutes controleren CSRF en character-input wordt met expliciete veldlijsten gevalideerd. De vaste SQL-kolommapping in `updateCharacter.php` voorkomt bovendien dat browserinput rechtstreeks kolomnamen bepaalt.

De huidige indeling is nog geen goede gedeelde basis voor events, companies en admin. Twee bestanden hebben meer verantwoordelijkheden dan hun naam aangeeft:

- `api/auth/accessControl.php` bevat naast authenticatie en CSRF ook JSON-foutafhandeling, characterbeleid, characteropzoekingen en skillbeleid.
- `api/characters/characterRequestValidation.php` bevat characterschema's, maar ook een generieke validatie-engine, HTTP-requestparsing en het versturen van foutantwoorden.

De eenvoudige routes zijn dicht bij het gewenste model. `saveCharacterSection.php` is daar het beste voorbeeld van, al herhaalt die nog validatie die al in het schema staat. `updateCharacter.php`, `getCharacter.php`, `saveBankTransfer.php` en `updateTrait.php` combineren nog te veel lagen. Vooral `updateCharacter.php` heeft een groot risico op verdere spaghetticode doordat autorisatie, veldtransformaties, spelregels, transacties en gekoppelde updates in één script staan.

Het advies is een kleine procedurele basis in gewone PHP: drie gedeelde bestanden voor requestinvoer, JSON-antwoorden en schemavalidatie; een zuiver generiek auth-bestand; en characterspecifieke schema's, beleidsfuncties en complexe use-cases binnen `api/characters`. Een framework, ORM, algemene controllerklasse of repositorybasisklasse is hiervoor niet nodig.

## Huidige verantwoordelijkheden

### `api/auth/accessControl.php`

Terecht generiek zijn:

- de rolconstanten `participant`, `director` en `administrator`;
- sessiestart en het herstellen van de sessie-identiteit;
- `aetherLoadAuthenticatedUser()`, dat de actuele rol uit `tblUser` haalt en daarmee een vervalste sessierol niet vertrouwt;
- `aetherRequireAuthenticatedUser()` en `aetherRequirePrivilegedUser()`;
- de rolpredicaten;
- genereren, ophalen en controleren van het CSRF-token.

Niet auth-specifiek zijn:

- `aetherJsonError()`: dit is algemene HTTP/API-presentatie;
- `aetherCanViewCharacter()`, `aetherCanEditCharacter()` en de overige characterbeslissingen;
- `aetherFetchCharacterAccessRecord()` en `aetherRequireCharacterAccess()`: deze combineren characterbeleid met een PDO-query;
- `aetherCanManageSkill()` en de bijbehorende require-helper: dit is skillbeleid.

De policyfuncties zelf zijn waardevol en moeten behouden blijven. Hun locatie veroorzaakt het probleem: iedere volgende module zou anders zijn eigen objectregels in het centrale auth-bestand toevoegen. Dan wordt `accessControl.php` een verzameling van alle bedrijfsregels.

### `api/characters/characterRequestValidation.php`

Terecht characterspecifiek zijn:

- de toegestane velden per characterroute;
- veldlengtes en grenzen die bij een character horen;
- toegestane characterwaarden en de dynamische schema's per actie;
- `aetherCompanyShareSchema()`, `aetherCharacterSecuritiesSchema()`, `aetherNewCharacterSchema()` en `aetherUpdateCharacterSchema()` zolang deze uitsluitend character-use-cases beschrijven.

Generiek en herbruikbaar zijn:

- `CharacterRequestValidationException`;
- de verwerking van `required`, `default`, `trim`, lengte, minimum en maximum;
- validators voor string, integer, getal, boolean, datum en enum;
- de controle op onbekende velden;
- het lezen en decoderen van JSON;
- de omzetting van validatiefouten naar een HTTP 422-antwoord.

De route-dispatch in `aetherCharacterRequestSchema()` is nu praktisch, maar wordt onhandelbaar als events, companies en admin eraan worden toegevoegd. Iedere module hoort haar eigen schema's te registreren of rechtstreeks aan haar endpoints te leveren. De generieke engine hoeft geen routenamen te kennen.

Het schema-attribuut `html` is momenteel documentatie; de validator gebruikt het niet om te saneren of te transformeren. Dat is aanvaardbaar zolang dit expliciet zo blijft. HTML-outputcodering hoort bij de presentatie en opgemaakte tekst hoort bij een bewuste, afzonderlijke sanitization-keuze. De validator moet geen zelfgemaakt HTML-filter krijgen.

### `api/characters/newCharacter.php`

Positief:

- authenticatie, CSRF en validatie gebeuren voor de insert;
- gezagsvelden zoals maker, aanmaakdatum, eigenaar en deelnemerstype worden server-side bepaald;
- de insert gebruikt een vaste kolommenlijst en prepared parameters.

Nog vermengd:

- HTTP-afhandeling en JSON-respons;
- standaardwaarden en characterregels;
- de mapping naar databasekolommen;
- uitvoeren van de insert en afhandelen van databasefouten.

Dit endpoint is nog overzichtelijk genoeg om niet onmiddellijk in veel bestanden te splitsen. Bij de eerstvolgende inhoudelijke uitbreiding is één characterspecifieke create-functie zinvol, zodat de route alleen request, beveiliging en response orkestreert.

### `api/characters/updateCharacter.php`

Dit is het duidelijkste refactordoel. Het bestand bevat tegelijk:

- requestparsing en opnieuw uitgevoerde controles die al in het schema staan;
- objectautorisatie en aparte checks voor gezagsvelden, klasse, bank, effecten en gezondheid;
- normalisatie en auditvelden;
- XP-, gezondheids- en andere spelregels;
- een vaste maar lokale kolommapping;
- transactionele PDO-updates;
- het verwijderen van niet meer geldige class traits;
- adressynchronisatie met gekoppelde gegevens;
- HTTP-fouten en het succesantwoord.

De vaste kolommapping is een sterke beveiligingsmaatregel en moet blijven. Zij is characterspecifieke persistentielogica, geen kandidaat voor een generieke dynamische updatefunctie.

De adressynchronisatie vangt binnen de hoofdtransactie zelf een fout af en logt die, waarna de overige update kan worden gecommit. Daardoor kan een gekoppelde invariant gedeeltelijk worden bijgewerkt. De module moet expliciet kiezen: de synchronisatie is onderdeel van dezelfde use-case en een fout rolt alles terug, of ze is aantoonbaar best-effort en gebeurt na de commit. Die keuze is characterspecifieke bedrijfslogica.

De route vraagt daarnaast op meerdere plaatsen opnieuw naar de huidige rol. Na `aetherRequireAuthenticatedUser()` moet de reeds vertrouwde `$currentUser` doorgegeven worden aan policy- en servicefuncties. Zo ontstaat geen tweede identiteitspad via sessiehelpers.

### Representatieve lees- en schrijfroutes

#### `getCharacter.php`

De objectcontrole vóór het samenstellen van het antwoord is goed. Het script bouwt daarna echter een volledig character-leesmodel met veel queries en helpers: basisgegevens, eigenschappen, vaardigheden, talen, portretten, economie, transacties, snapshots, aandelen en UI-capabilities. Daardoor is de route zowel controller als query-/presentatieservice.

Een characterspecifieke `aetherBuildCharacterDetail(PDO $pdo, array $user, int $characterId)` is hier passend. Die functie kan de benodigde data ophalen en de bestaande pure policyfuncties gebruiken. Een generieke repositorybasis biedt hier geen voordeel. Expliciete selectkolommen verdienen bij aanpassing de voorkeur boven `SELECT *`, zodat een databaseschemaverandering niet ongemerkt de API-response uitbreidt.

#### `saveCharacterSection.php`

Deze route komt het dichtst bij een dun endpoint: beveiliging, één schema, objectcontrole en één upsert. De lokale lijst met toegestane secties herhaalt de enum uit het schema en kan gaan afwijken. Na invoering van de gedeelde validator kan de route rechtstreeks op de gevalideerde waarde vertrouwen.

#### `saveBankTransfer.php`

De route herhaalt type-, grens-, datum- en lengtecontroles uit het schema. De omschrijving wordt bovendien nog afgekapt terwijl het schema een te lange waarde al afwijst. De transactie zelf is een duidelijke character-economie-use-case met bron- en doelcontrole, bevoegdheden en meerdere mutaties. Die hoort als geheel in een characterspecifieke servicefunctie, zodat rollback en invarianten op één plaats staan.

#### `updateTrait.php`

`canCurrentUserManageTrait()` staat lokaal in het endpoint en de route bevat veel operationele branches met eigen queries en foutantwoorden. Het beleid hoort bij character/trait access en de bewerkingen horen in een character-traitservice. Verschillende branches geven momenteel een JSON-fout zonder consequent dezelfde HTTP-status; een gedeelde responsehelper voorkomt dit.

## Scheiding van lagen

| Onderdeel | Huidige toestand | Gewenste grens |
|---|---|---|
| Request parsing | JSON lezen zit in de character-validator; andere modules doen dit handmatig | Generieke, expliciete JSON- en formulierlezers |
| Validatie | Goede expliciete schemas, maar engine en characterregels staan samen | Generieke engine; schema's per module |
| API-fouten | `aetherJsonError()` staat in auth; routes bouwen ook eigen antwoorden | Eén gedeelde responsefunctie en één foutcontract |
| Authenticatie | Centrale databasegestuurde identiteit is goed | In `auth/accessControl.php` houden |
| Rollen | Canonieke rolconstanten bestaan, maar oude wrappers dupliceren checks | Canonieke predicates gebruiken en usercontext doorgeven |
| CSRF | Centrale tokencontrole is goed; endpoints starten soms ook zelf sessies | Centrale helper behouden; geen extra `session_start()` in routes |
| Objectautorisatie | Characterpredicaten zijn sterk, maar staan in auth en sommige policies lokaal | Pure policies in de betreffende module; afdwinghelper vóór neveneffecten |
| Bedrijfslogica | Eenvoudige routes zijn redelijk dun; complexe routes mengen regels en PDO | Complexe use-case per module in een servicefunctie |
| PDO | Prepared statements en vaste mapping zijn goed; grote routes bevatten veel queries | Characterspecifieke queryfuncties waar hergebruik of transacties dat rechtvaardigen |

Een endpoint hoeft niet volledig zonder SQL te zijn. Een enkele duidelijke query in een korte route is minder complex dan een kunstmatige repositorylaag. Extractie is zinvol bij meerdere queries, een transactie, hergebruik, of wanneer bedrijfsregels anders niet afzonderlijk testbaar zijn.

## Duplicatie die bij volgende modules dreigt

De dreiging is al zichtbaar:

- admin en companies hebben eigen wrappers voor privileged access en optionele CSRF-controle;
- characterhelpers `getCurrentUserRole()`, `isPrivilegedUserRole()` en `getCurrentUserId()` overlappen met de centrale authenticatie en laten code opnieuw naar sessiestaat kijken;
- admin-, company- en eventroutes lezen en decoderen JSON zelf en formatteren hun eigen fouten;
- eventroutes bouwen insert/updatekolommen op uit keys van requestdata; een expliciet schema plus een vaste modulemapping is daar nodig zodra die module wordt aangepakt;
- companyroutes hebben lokale allowlists en validatie, waarbij onbekende velden niet overal op dezelfde manier worden behandeld;
- transactieroutes herhalen `beginTransaction()`, rollback en foutantwoorden;
- routes herhalen validatie na een al geslaagde schemavalidatie, waardoor grenzen en enumlijsten kunnen afwijken.

Niet elke herhaling vraagt nu een helper. Een generieke transactiehelper kan bijvoorbeeld pas worden toegevoegd wanneer duidelijk is hoe geneste transacties en bekende domeinfouten moeten werken. De concrete, onmiddellijke hergebruikskansen zijn request parsing, responsevorm, scalair validatieschema en auth/CSRF-aanroepen.

## Voorgestelde kleine gedeelde basis

```text
api/
  shared/
    request.php                 JSON- en formulierinput lezen
    response.php                JSON-succes, JSON-fout en validatiefout
    validation.php              exception, schema-engine en veldregelhelpers
  auth/
    accessControl.php           sessie, vertrouwde user, rollen en CSRF
  characters/
    characterRequestValidation.php  tijdelijke compatibiliteitslaag
    characterSchemas.php        charactervelden, grenzen, enums en actieschema's
    characterAccess.php         character- en skillbeleid
    characterRepository.php     herbruikbare characterqueries en vaste mappings
    characterService.php        create/update en transactionele character-use-cases
    characterReadService.php    samengesteld detail-leesmodel
    ...                         bestaande characterspecifieke domeinhelpers
```

De compatibiliteitslaag voorkomt een grote wijziging ineens: bestaande endpoints kunnen hun huidige functienamen tijdelijk behouden terwijl de implementatie naar `shared` en de nieuwe characterbestanden verhuist. Zodra alle aanroepen zijn gemigreerd, kan die laag verdwijnen.

### Gedeelde requestfuncties

Houd de bron expliciet:

- `aetherReadJsonObject(): array` leest alleen een JSON-object en meldt lege, ongeldige of niet-objectinput uniform;
- `aetherReadFormFields(): array` leest gewone formulierdata;
- multipart/uploadroutes blijven een aparte codepad gebruiken.

De huidige stille terugval van JSON naar `$_POST` maakt minder duidelijk welk contract een endpoint heeft. Een endpoint kiest daarom zelf de passende lezer. De lezer valideert geen charactervelden.

### Gedeelde responses

Verplaats `aetherJsonError()` uit `accessControl.php` naar `api/shared/response.php` en behoud de functienaam tijdens de migratie. Voeg hoogstens toe:

- `aetherJsonResponse(mixed $data, int $status = 200): never`;
- `aetherJsonValidationError(array $errors): never`.

Behoud voor frontendcompatibiliteit voorlopig het bestaande leesbare `error`-veld. Voeg een stabiele machinecode en gestructureerde veldfouten toe, bijvoorbeeld:

```json
{
  "error": "Ongeldige invoer.",
  "code": "validation_failed",
  "validationErrors": [
    {"field": "name", "code": "too_long", "message": "..."}
  ]
}
```

Gebruik vervolgens vaste statussen: 400 voor een ongeldig requestformaat, 401 voor niet aangemeld, 403 voor onvoldoende rechten of CSRF, 404 voor een niet zichtbaar object, 409 voor een aantoonbaar conflict, 422 voor veldvalidatie en 500 voor een onverwachte serverfout.

### Gedeelde validatie

Verplaats de huidige generieke engine vrijwel ongewijzigd naar `validation.php`:

- hernoem `CharacterRequestValidationException` naar `AetherValidationException`;
- hernoem de engine naar bijvoorbeeld `aetherValidateInput(array $input, array $schema): array`;
- laat de engine onbekende velden blijven weigeren;
- bied kleine veldregelhelpers voor tekst, integer, getal, boolean, datum en enum;
- laat een endpoint of module het concrete schema aanleveren.

Voeg geen algemene validatietaal, reflectie of objectmodel toe. Geneste lijsten en objecten zijn nog niet nodig voor characterdata. Breid de engine pas uit wanneer een concrete admin-, event- of companyroute wordt gemigreerd en daar een test voor bestaat.

### Authenticatie en CSRF

De huidige expliciete volgorde is al goed leesbaar en heeft geen nieuwe wrapper nodig:

```php
$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();
$input = aetherReadJsonObject();
$input = aetherValidateInput($input, aetherUpdateCharacterSchema());
```

Voor privileged routes wordt alleen de eerste regel `aetherRequirePrivilegedUser($pdo)`. Houd deze aanroepen zichtbaar bovenaan iedere beschermde route en vóór alle mutaties. Een configureerbare `requireApiAccess($options)` zou de beveiligingskeuze juist verbergen en is nu niet wenselijk.

Een algemene `aetherRequireAnyRole()` is pas zinvol als een volgende module een andere concrete rolcombinatie nodig heeft. De bestaande privileged helper dekt director plus administrator al duidelijk af.

## Characterspecifieke verantwoordelijkheden

Binnen de personagemodule blijven:

- alle characterschema's, veldnamen, lengtes, grenzen en enums;
- regels voor eigenaar, gekoppelde gebruiker, participanttype en gezagsvelden;
- wie een character, dagboek, economie, trait, skill of gekoppeld object mag zien of wijzigen;
- XP-, bank-, effecten-, gezondheids-, klasse- en traitregels;
- vaste mappings tussen characterinput en databasekolommen;
- samengestelde characterqueries en transactievolgorde;
- bepalen van UI-capabilities in een characterresponse.

Nieuwe policyfuncties gebruiken bij voorkeur consequent `(array $user, array $resource)`. Dat sluit aan op `aetherCanViewCharacter()` en voorkomt de huidige mix van losse rol- en user-ID-argumenten. De browser aangeleverde eigenaar of rol mag nooit deel uitmaken van die usercontext.

## Bestaande helpers: behouden, verplaatsen en uitfaseren

| Helper of groep | Advies | Reden |
|---|---|---|
| `aetherLoadAuthenticatedUser()` | Behouden | Eén vertrouwde bron voor identiteit en actuele databaserol |
| `aetherRequireAuthenticatedUser()` | Behouden | Heldere routegrens |
| `aetherRequirePrivilegedUser()` | Behouden | Concrete, reeds gebruikte rolcombinatie |
| rolconstanten en `aetherIs*Role()` | Behouden als canoniek | Voorkomt losse rolstrings en afwijkende checks |
| CSRF-tokenhelpers | Behouden | Klein, generiek en correct gecentraliseerd |
| `aetherJsonError()` | Verplaatsen, naam behouden | Generieke response, geen authenticatielogica |
| `CharacterRequestValidationException` | Generaliseren en hernoemen | De uitzondering bevat geen characterlogica |
| `aetherValidateCharacterRequest()` | Splitsen | Engine generiek; schema characterspecifiek |
| `aetherReadCharacterJsonRequest()` | Vervangen door generieke lezer plus validator | HTTP-bron en domeinschema worden nu vermengd |
| `aetherCharacterValidationFailure()` | Vervangen door generieke validatieresponse | Zelfde foutcontract voor alle modules |
| `aetherCanView/Edit...Character()` | Behouden, verplaatsen | Goede characterspecifieke pure policies |
| `aetherRequireCharacterAccess()` | Behouden als facade, verplaatsen | Nuttige afdwinggrens; fetch en policy later intern scheiden |
| `aetherCanManageSkill()` | Hernoemen naar `aetherCanAccessSkill()` bij migratie | “Manage” beschrijft publieke/deelnemertoegang onvoldoende precies |
| `getCurrentUserRole()` | Uitfaseren | Gebruik de al geladen `$currentUser['role']` |
| `isPrivilegedUserRole()` | Samenvoegen met `aetherIsPrivilegedRole()` | Dubbele rolwaarheid |
| `getCurrentUserId()` | Uitfaseren | Geef het ID uit de vertrouwde usercontext door |
| admin/company privileged wrappers | Tijdelijk behouden als compatibiliteitswrapper | Laat ze later de centrale auth- en CSRF-helpers aanroepen |
| `dbOne()` en `dbAll()` | Behouden | Kleine bruikbare PDO-helpers; geen ORM nodig |

`aetherFetchCharacterAccessRecord()` kan intern naar `characterRepository.php`, terwijl `aetherRequireCharacterAccess()` de herkenbare facade blijft. Zo hoeven endpoints niet dubbel te fetchen wanneer ze het volledige character al hebben: in dat geval roepen ze direct de pure policy aan.

## Gewenst patroon voor endpoints

Een nieuwe of aangepaste route moet hoofdzakelijk orkestreren:

1. dependencies laden en PDO verkrijgen;
2. de aangemelde gebruiker server-side laden;
3. bij een schrijfactie CSRF controleren;
4. de juiste inputbron lezen en tegen één moduleschema valideren;
5. objecttoegang controleren;
6. één eenvoudige query of één module-use-case uitvoeren;
7. een gestandaardiseerd antwoord versturen;
8. onverwachte fouten uniform afhandelen en server-side loggen.

Er mogen vóór stap 2 tot en met 5 geen databasemutaties of andere neveneffecten plaatsvinden. Publieke routes en login-/callbackroutes kiezen expliciet een ander beveiligingspad; zij mogen niet per ongeluk onder een algemene endpointbootstrap vallen.

## Beoordeling van de tests

De testcode is voor deze beoordeling gelezen. Een heruitvoering was in de huidige shell niet mogelijk: `php`, `where.exe php` en de gecontroleerde gebruikelijke lokale installatiepaden leverden geen PHP-binary op. Daarom worden hieronder alleen de aangetroffen dekking en beperkingen beoordeeld; er wordt geen nieuw testresultaat als geslaagd gemeld.

### Sterke dekking

- `tests/access_control_test.php` test de pure rol-, eigenaarschap- en CSRF-beslissingen snel en zonder database.
- `tests/authenticated_user_test.php` test met SQLite dat de databaserol leidend is, een vervalste sessierol geen extra recht geeft, provisioning niet stilzwijgend gebeurt, objecteigenaarschap wordt toegepast en een geweigerde mutatie geen gegevens wijzigt.
- `tests/character_input_validation_test.php` dekt geldige input, ontbrekende velden, types, grenzen, onbekende velden, gemanipuleerde kolomnamen en opgeslagen XSS-payloads.

### Grenzen van de huidige tests

- `tests/access_control_route_coverage_test.php` zoekt vooral functienamen en patronen in bronbestanden. Dat is een nuttige tijdelijke inventariscontrole, maar bewijst niet dat de check vóór een mutatie loopt, het juiste object controleert of bereikbaar is.
- Een deel van `character_input_validation_test.php` controleert eveneens broncodepatronen en exacte helpernamen. Zulke checks worden snel broos bij een veilige refactor en kunnen de oude bestandsindeling onbedoeld vastzetten.
- De tests controleren nog geen volledig uniform foutcontract met HTTP-status en foutcode.
- De complexe use-cases uit `updateCharacter.php`, banktransfer en traitupdates zijn niet als afzonderlijke service met succes-, weigering- en rollbackscenario's testbaar.
- De XSS-test bewijst veilige opslag en controleert het gebruik van veilige DOM-methoden statisch; hij voert geen browser-DOM-test uit. Die beperking moet bij de testresultaten zichtbaar blijven.

Na de extractie horen de tests in drie niveaus te worden verdeeld:

1. generieke contracttests voor requestparsing, schema-engine en responsevorm;
2. characterschematests en pure policytests;
3. SQLite-integratietests voor character-use-cases, inclusief rollback en ongewijzigde data na weigering.

De route-coverage-test kan tijdens de migratie blijven bestaan, maar mag niet als primaire beveiligingstest gelden. Pas hem aan op gedrag waar een endpointtest praktisch is en laat hem alleen statisch bewaken wat niet uitvoerbaar is zonder de volledige WordPress-/webserveromgeving.

## Kleine refactorvolgorde

1. **Leg het externe API-contract vast.** Voeg tests toe voor de bestaande succesvormen, validatiefout, 401, 403 en 422 voordat responsevelden veranderen.
2. **Extraheer `response.php`.** Verplaats `aetherJsonError()` zonder gedrag te wijzigen en laat auth en bestaande routes dit bestand includen.
3. **Extraheer de generieke validator en expliciete requestlezers.** Laat `characterRequestValidation.php` tijdelijk als facade bestaan en splits de tests in generiek en character-specifiek.
4. **Verplaats objectbeleid uit auth.** Zet character- en skillpolicy in `characterAccess.php`; behoud tijdelijke wrappers zodat dit geen brede endpointwijziging wordt.
5. **Maak de eenvoudige routes consistent.** Verwijder alleen aantoonbaar dubbele validatie, dubbele sessiestarts en handgemaakte foutresponses in routes zoals `saveCharacterSection.php`.
6. **Splits `updateCharacter.php` als eerste complexe use-case.** Houd de vaste veldmapping characterspecifiek, plaats beslisregels in een testbare functie en laat één service de transactie en gekoppelde mutaties beheren.
7. **Pak complexe subdomeinen één voor één aan.** Daarna banktransfer, traits en het samengestelde leesmodel van `getCharacter.php`; vermijd één allesomvattende `CharacterService`.
8. **Gebruik de basis bij de volgende module.** Start met events omdat daar dynamische kolomopbouw uit requestkeys voorkomt. Migreer daarna companies en admin. Voeg pas nieuwe generieke validatietypes toe zodra een concrete route ze nodig heeft.

Elke stap moet klein genoeg blijven om de bestaande toegangs- en validatietests uit te voeren en om via `git diff` te controleren dat er geen bedrijfsregel is verplaatst of veranderd zonder bijbehorende test.

## Criteria voor toekomstige event-, company- en adminroutes

Een route voldoet aan de voorgestelde structuur wanneer:

- de inputbron expliciet JSON, formulier of multipart is;
- ieder endpoint één expliciet schema/allowlist gebruikt en onbekende velden afwijst;
- veldtypen, normalisatie, grenzen, enums en nullability in het moduleschema staan;
- SQL-kolommen alleen uit een vaste server-side mapping komen;
- identiteit en rol uitsluitend uit `aetherLoadAuthenticatedUser()` of een require-helper komen;
- dezelfde `$currentUser` aan policies en services wordt doorgegeven;
- roltoegang én toegang tot het concrete object vóór een read of write worden gecontroleerd;
- iedere sessiegebaseerde schrijfactie vóór het eerste neveneffect CSRF controleert;
- gezagsvelden alleen server-side worden gezet of via een expliciete policy worden toegestaan;
- weigering geen databasewijziging, upload, synchronisatie of ander neveneffect veroorzaakt;
- eenvoudige PDO-statements prepared parameters gebruiken;
- een use-case met meerdere afhankelijke mutaties één duidelijke transactiegrens heeft;
- verwachte fouten een consistente HTTP-status, leesbare boodschap en stabiele foutcode geven;
- onverwachte fouten geen interne details naar de browser lekken;
- bedrijfsregels, veldschema's en objectpolicies in de eigen module blijven;
- het endpoint vooral orkestreert en geen lange branchstructuur met queries en responslogica bevat;
- ten minste toegestaan gedrag, niet aangemeld, verkeerde rol, verkeerd object, gemanipuleerde IDs/gezagsvelden en ongewijzigde data na weigering worden getest;
- statische broncodechecks alleen aanvullend zijn op uitvoerbare gedrags- en integratietests;
- publieke routes expliciet als publiek herkenbaar blijven en niet afhankelijk zijn van een impliciete uitzondering.

## Afbakening

Dit advies vraagt geen technologiemigratie. De voorgestelde functies, bestanden en tests werken met de bestaande PHP-, PDO-, sessie- en WordPress-koppeling en vereisen geen Node.js. Het advies verandert ook geen toegangsregels of characterspelregels. Het ordent de reeds aanwezige verantwoordelijkheden zodat events, companies en admin dezelfde veilige basis kunnen gebruiken zonder characterspecifieke regels naar een algemene laag te verplaatsen.
