# Refactor van character-skills en -acties

Datum: 18 september 2026

## Vastgestelde API-contracten

De actieve frontend staat in `js/skillsCharacter.js` en `js/actionsCharacter.js`. Alle zes routes gebruiken `POST` met een JSON-object. `apiFetchJson()` voegt bij de vier schrijfroutes de bestaande `X-CSRF-Token`-header toe.

| Route | Requestvelden | Succesresponse |
|---|---|---|
| `updateSkill.php` | `action` (`up`, `down` of `delete`), `idSkill`, `idCharacter` | `skills`, `usedExperience`, `maxExperience`; bestaande spelregelweigeringen blijven HTTP 200 met `error` |
| `addSkillSpecialisation.php` | `idSkill`, `idCharacter`, optioneel `idSkillSpecialisation`, `name` en `kind` | `{ "success": true }` |
| `getCharacterActionEvents.php` | `idCharacter` | `events`, `worldKnowledgeLevel`, `psiBurn`, `actions` |
| `getCharacterActionKnowledgeTargets.php` | `idCharacter`, `idEvent` | `worldKnowledgeLevel`, `attemptCount`, `targets` |
| `revealCharacterActionKnowledge.php` | `idCharacter`, `idEvent`, `idSourceCharacter` | `attemptCount`, `newlyUnlockedLevels`, `unlockedGossips`, `unlockedGossipLevel`, `displayName`, `portraitUrl`, `type`, `isFullyUnlocked` |
| `useCharacterSkillAction.php` | `idCharacter`, `idEvent`, `idSkill`, `actionCode`, `actionSubtype`, optioneel `clearBurn` | `usageId`, `actionCode`, `categoryCode`, `categoryLabel`, `skill`, `event`, `roll`, `burn`, `result` |

De frontend gebruikt de skillresponses om het character opnieuw te laden en gebruikt de actionresponses rechtstreeks voor de eventkeuze, kennistargets, gossipmodal, psi-burn en resultaatmodal. Frontendcode is niet gewijzigd.

De gemigreerde routes gebruiken nu het bestaande gedeelde foutcontract:

- HTTP 400 voor ongeldige JSON en bestaande action-domeinfouten; action-domeinfouten behouden het bestaande `details: null`;
- HTTP 401 voor een niet-aangemelde gebruiker;
- HTTP 403 voor CSRF, eigenaarschap, rol, ontbrekende Wereldwijs-toegang of een geheime skill;
- HTTP 404 voor een onbekend character;
- HTTP 422 voor ontbrekende, fout getypeerde, te lange, ongeldige en onverwachte velden;
- HTTP 500 met alleen de routemelding bij een onverwachte fout en zonder PDO-, SQL- of tabeldetails.

`updateSkill.php` behoudt de bestaande HTTP 200-responses met een `error`-veld voor onvoldoende XP, maximum niveau, niveau nul en een ontbrekende character-skilllink. De bestaande foutbetekenis van `addSkillSpecialisation.php` voor een onbekende specialisatiedefinitie blijft eveneens behouden.

## Rechten en objecttoegang

Iedere route laadt eenmaal `$currentUser` via `aetherRequireAuthenticatedUser()`. Identiteit en rol komen daardoor uit de actuele `tblUser`-rij. Browserrollen, character-eigenaren en sessierollen worden niet als bevoegdheidsbron gebruikt.

| Handeling | participant | director | administrator |
|---|---|---|---|
| Skillniveau of skilllink wijzigen | alleen een eigen character van type `player`, en alleen een publieke skill | ieder character en iedere skill | ieder character en iedere skill |
| Specialisatie koppelen of maken | alleen een eigen `player`-character en een publieke skill | ieder character en iedere skill | ieder character en iedere skill |
| Actioncatalogus en kennistargets bekijken | alleen een eigen character; de bestaande action-uitzondering voor een eigen `extra` blijft behouden | ieder character | ieder character |
| Gossip onthullen of een psi-actie gebruiken | alleen voor een eigen character; geheime skills blijven uitgesloten | ieder character, inclusief geheime skills | ieder character, inclusief geheime skills |

Skillbeheer gebruikt `aetherRequireCharacterAccess(..., 'edit')` en `aetherRequireSkillAccess()`. Characteracties gebruiken één nieuwe action-policygrens die de bestaande viewbeslissing via `aetherCanViewCharacter()` toepast. Dezelfde vertrouwde usercontext wordt daarna aan de service doorgegeven.

## Behouden spelregels

### Skills en specialisaties

- Skillniveaus blijven begrensd op 0 tot en met 3.
- De bestaande incrementele XP-kosten blijven 1, 2 en 3 punten voor de overgangen naar niveau 1, 2 en 3.
- Alleen player-characters zijn aan het XP-budget gebonden; de bestaande extra-uitzondering in de puntberekening blijft behouden.
- Een niet-discipline-specialisatie kost 2 XP; een discipline kost geen extra XP.
- Alleen director en administrator kunnen via deze route een nieuwe definitie met `kind: discipline` maken. Dezelfde browserwaarde van een participant wordt als gewone `specialisation` opgeslagen.
- Een bestaande link wordt niet nogmaals toegevoegd.
- Bij skillniveau 0 worden gekoppelde discipline-specialisaties verwijderd.
- Bij verwijderen van een skill worden eerst alle characterspecialisaties voor die character-skillcombinatie en daarna de skilllink verwijderd.

### Events, kennis en skillacties

- De eventlijst blijft alle records uit `tblEvent` bevatten, aflopend op startdatum en ID.
- Wereldwijs blijft de bestaande skill met ID 32 en het opgeslagen skillniveau bepaalt welke gossipniveaus bereikbaar zijn.
- Kennistargets blijven beperkt tot andere characters met niet-lege gossip voor het gekozen event en de bestaande visibilityregel. Player-gossip is standaard zichtbaar; extra-gossip standaard verborgen tenzij beheer de visibility expliciet aanpast.
- De bestaande attemptteller en kansen per Wereldwijsniveau blijven ongewijzigd.
- Psi-categorieën, rolls, burn, clear-burnmodifier, uitkomsten en resulterende tekst blijven in de bestaande helpers staan en zijn inhoudelijk niet gewijzigd.
- Eén geslaagde psi-actie werkt burn bij en schrijft één auditregel naar `tblCharacterSkillActionUse`.
- De huidige applicatie heeft geen maximum aantal psi-action uses per event of skill en verbruikt geen aparte voorraad. De adminconfiguratie kan action-use-auditregels bekijken, wijzigen en verwijderen, maar definieert geen use-limiet. Deze refactor voegt geen limiet of spelbalans toe.

## Concrete herstelde fouten

### Specialisatie hoorde niet aantoonbaar bij de skill

De oude dropdownroute zocht een specialisatie alleen op `id`. Daardoor kon een rechtstreeks request een definitie van skill A opslaan in een characterlink voor skill B. De repository zoekt nu op zowel `id` als `idSkill`. Een verkeerde combinatie wordt vóór de eerste write geweigerd.

### Orphan-specialisatie na XP-weigering

Bij een nieuwe naam schreef de oude route eerst de globale definitie en controleerde daarna pas het XP-budget. Een afwijzing liet daardoor een ongekoppelde definitie achter. De service bepaalt nu eerst kind, kosten en bevoegdheden. Definitie en characterlink committen vervolgens in één transactie of rollen samen terug.

### Gekoppelde skillwrites waren niet atomisch

Een skill verwijderen deed twee losse deletes. Niveauverlaging naar nul kon een disciplinekoppeling verwijderen en daarna falen op de levelupdate. Deze gekoppelde writes staan nu in één transactie. De responsegegevens worden vóór commit opnieuw gelezen, zodat een fout daarbij de write eveneens terugrolt.

### Geheime action-skills lekten via de catalogus

De actionquery filterde `tblSkill.visibility` niet. Een participant kon daardoor naam en niveau van een gekoppelde geheime psi-skill ontvangen. De actionservice filtert de catalogus nu met hetzelfde centrale skillbeleid als de overige character-API en controleert dit beleid opnieuw vóór een skillactie. Director en administrator behouden toegang.

### Databasefout kon als domeinfout uitlekken

`PDOException` is in PHP ook een `RuntimeException`. De actionservice onderscheidt daarom verwachte domeinfouten expliciet van PDO-fouten. Alleen domeinfouten behouden HTTP 400 en `details: null`; databasefouten bereiken de generieke HTTP 500-afhandeling zonder technische details.

## Architectuur en verantwoordelijkheden

### Dunne endpoints

De zes endpoints doen alleen nog:

1. PDO en dependencies laden;
2. de vertrouwde gebruiker laden;
3. bij writes CSRF controleren;
4. met `aetherReadJsonObject()` één expliciete JSON-bron lezen;
5. met `aetherValidateInput()` het bestaande characterschema uitvoeren;
6. character- en skilltoegang controleren;
7. één gerichte service aanroepen;
8. via `aetherJsonResponse()` of `aetherJsonError()` antwoorden.

De routes gebruiken `characterRequestValidation.php` niet meer. Er is geen stille JSON-naar-formulierfallback en er zijn geen lokale JSON-decoders, rolstrings of handmatig samengestelde succesresponses meer.

### `characterSkillRepository.php`

Bevat uitsluitend vaste prepared PDO-query's voor character-skilllinks, levels, feedbackregels, specialisatiedefinities en characterspecialisatielinks. Requestkeys bepalen nooit een SQL-kolom.

### `characterSkillService.php`

Bevat de bestaande XP-, level-, discipline- en specialisatieregels en beheert de transacties voor gekoppelde skillwrites. `AetherCharacterSkillException` draagt alleen een verwachte bestaande status en melding naar het endpoint.

### `characterActionService.php`

Vormt de routegrens rond de bestaande `gossipKnowledgeUtils.php` en `characterSkillActionUtils.php`. De service controleert character- en skilltoegang, bouwt de bestaande responses en beheert commit/rollback voor onthullen en psi-gebruik. De omvangrijke kans-, gossip- en psi-tabellen zijn bewust niet herschreven of naar een algemene repository verplaatst.

## Transactiegrenzen

De volgende gekoppelde mutaties zijn atomisch:

- discipline-specialisatie verwijderen plus levelupdate;
- alle specialisaties verwijderen plus character-skilllink verwijderen;
- nieuwe specialisatiedefinitie plus characterlink;
- gossip-attempt ophogen plus unlockstate opslaan;
- psi-burn bijwerken plus action-use-auditregel opslaan.

Alle rol-, eigenaarschaps-, schema-, skillvisibility-, XP- en objectcontroles vinden vóór de eerste write plaats. Event-, target- en psi-skillcombinaties worden in de bestaande domeinhelpers binnen de transactie maar vóór hun eerste write gevalideerd.

## Resterend concurrencyrisico

De huidige databasestructuur bevat geen samengestelde unieke sleutel voor `tblCharacterSpecialisation (idCharacter, idSkill, idSkillSpecialisation)` of `tblLinkCharacterSkill (idCharacter, idSkill)`. Daarnaast hebben action requests geen idempotentiesleutel en verhoogt de gossipattemptteller via read-then-write. Twee werkelijk gelijktijdige identieke requests kunnen daardoor nog een dubbele link/action use maken of een attemptincrement verliezen.

Een correcte oplossing vraagt een afzonderlijke MySQL-migratie met voorafgaande duplicaatcontrole en/of een nieuw idempotentiecontract. Dat valt buiten deze contractbehoudende refactor en is daarom niet stilzwijgend gewijzigd.

## Gewijzigde bestanden

- `api/characters/updateSkill.php`: dun skilllevelendpoint.
- `api/characters/addSkillSpecialisation.php`: dun specialisatie-endpoint.
- `api/characters/getCharacterActionEvents.php`: dun actioncatalogusendpoint.
- `api/characters/getCharacterActionKnowledgeTargets.php`: dun kennistargetendpoint.
- `api/characters/revealCharacterActionKnowledge.php`: dun transactioneel reveal-endpoint.
- `api/characters/useCharacterSkillAction.php`: dun transactioneel psi-actionendpoint.
- `api/characters/characterSkillRepository.php`: gerichte skill- en specialisatiequery's.
- `api/characters/characterSkillService.php`: skillregels en transacties.
- `api/characters/characterActionService.php`: actionbeleid, responses en transacties rond de bestaande helpers.
- `tests/character_skills_actions_endpoints_test.php`: stateful gedragstests voor de echte zes routes.
- `tests/access_control_route_coverage_test.php`: volgt de nieuwe action-policygrens.
- `tests/character_input_validation_test.php`: bewaakt het directe gedeelde parsing- en schemapatroon.

`characterSchemas.php`, frontendcode, tabellen, adminfunctionaliteit en overige modules zijn niet gewijzigd.

## Uitgevoerde tests

De nieuwe endpointtest kopieert de werkelijke routes en PHP-dependencies naar een tijdelijke fixture. Alleen `db.php` wordt vervangen door een stateful PDO-testdouble en `characterPointUtils.php` door een begrensde puntdouble, zodat de test niet van de ontbrekende lokale database-extensie afhangt. De echte schema-, auth-, access-, service-, repository-, gossip- en psi-code wordt uitgevoerd.

De test dekt onder meer:

- geldige eigen skillupdate en specialisatie, opnieuw lezen en duplicaatgedrag;
- director- en administratorrechten;
- participant op eigen player, eigen extra en andermans character volgens de bestaande afzonderlijke policies;
- publieke en geheime skillcatalogus en gebruikscontrole;
- XP-, minimum- en maximumniveaus;
- events, zichtbare en verborgen kennistargets;
- geldige gossip-reveal en psi-action;
- onbekende event-, target-, skill- en actioncombinaties;
- ontbrekende/ongeldige CSRF, niet aangemeld, ontbrekende, fout getypeerde, te lange en onverwachte velden;
- exacte prepared identificatieparameters;
- nul writes bij weigering;
- commit en rollback van iedere gekoppelde mutatie;
- exacte succesvelden en generieke 500-responses zonder technische details.

Uitgevoerd met PHP 8.4.25:

| Controle | Resultaat |
|---|---|
| `tests/character_skills_actions_endpoints_test.php` | geslaagd |
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
| PHP-syntaxcontrole van gewijzigde PHP-bestanden | geslaagd |
| `git diff --check` | geslaagd |

De PDO-testdouble bewijst routevolgorde, policies, parameters, state, commit en rollback. Zij bewijst geen echte MySQL-syntaxis, constraints, isolation levels, WordPress-sessie-integratie of browsergedrag. Er is geen ontwikkelings- of productiedatabase geraakt.

## Online checklist voor `aetherapp-dev`

- [ ] Verhoog en verlaag als participant een publieke skill van het eigen player-character; controleer level, XP en herladen.
- [ ] Voeg een bestaande en een nieuwe specialisatie toe en open het character opnieuw.
- [ ] Probeer via een rechtstreeks request een specialisatie-ID van een andere skill; er mag niets worden geschreven.
- [ ] Controleer XP-tekort, niveau 0 en niveau 3 zonder databasewijziging.
- [ ] Controleer als participant dat een geheime skill niet in de actioncatalogus verschijnt en niet gebruikt kan worden.
- [ ] Controleer dezelfde geheime skill als director en administrator.
- [ ] Open Actions voor het eigen character, een eigen extra en als participant een character van een ander; alleen de bestaande toegestane gevallen mogen werken.
- [ ] Controleer eventlijst, Wereldwijsniveau, zichtbare player-gossip en de visibilityinstelling voor extra-gossip.
- [ ] Onthul gossip en heropen hetzelfde event; attemptteller en vrijgespeelde niveaus moeten behouden blijven.
- [ ] Gebruik een publieke psi-skill met en zonder `clearBurn`; controleer burn en de resultaatmodal.
- [ ] Herhaal characteracties als director en administrator op een character van een ander.
- [ ] Verstuur ieder schrijfrequest zonder en met een fout CSRF-token; geen enkele tabel mag wijzigen.
- [ ] Controleer in de networktab de beschreven responsevelden en generieke 500-responses zonder `details` met SQL- of PDO-inhoud.

