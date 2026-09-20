# Character-lifecycle en portretten

Datum: 19 september 2026

## Onderzochte actieve contracten

De actieve frontend gebruikt precies drie routes voor deze batch.

| Route | Methode en invoer | Bestaande succesresponse |
|---|---|---|
| `api/characters/uploadCharacterPortrait.php` | `POST` multipart/form-data met formulierveld `id`, uploadveld `portrait` en `X-CSRF-Token` | `{ "status": "ok", "portraitUrl": "img/portret/...png?v=..." }` |
| `api/characters/deleteCharacterPortrait.php` | `POST` met JSON-object `{ "id": ... }` via `apiFetchJson()` | `{ "status": "ok" }` |
| `api/characters/deleteCharacter.php` | `POST` met JSON-object `{ "id": ... }` via `apiFetchJson()` | `{ "success": true, "id": ..., "name": "..." }` |

`js/characterFunctions.js` maakt de multipartupload, verwerkt `portraitUrl` en roept de portretverwijdering aan. `js/apiCharacter.js` roept de characterverwijdering aan. Deze frontendcode en de drie normale succesresponses zijn niet gewijzigd.

De JSON-routes accepteren uitsluitend een JSON-object. De upload accepteert uitsluitend het formulierveld `id` en het uploadveld `portrait`. Onbekende velden worden met HTTP 422 geweigerd. Een ontbrekende upload of een PHP-uploadfout blijft HTTP 400. Authenticatie, CSRF en objecttoegang worden vóór een mutatie gecontroleerd. Onverwachte fouten geven een generieke HTTP 500; technische details gaan alleen naar `error_log`.

## Toegangsregels

De endpoints laden de gebruiker eenmaal met `aetherRequireAuthenticatedUser()`. ID en rol komen daardoor uit de actuele rij in `tblUser`; de meegestuurde of in de sessie opgeslagen rol geeft geen extra rechten.

| Handeling | participant | director | administrator |
|---|---|---|---|
| Portret uploaden/vervangen | eigen character, inclusief de bestaande `extra`-uitzondering | ieder character | ieder character |
| Portret verwijderen | eigen character, inclusief de bestaande `extra`-uitzondering | ieder character | ieder character |
| Character verwijderen | eigen character van type `player`; de bestaande state-onafhankelijke regel blijft gelden | ieder character | ieder character |

Deze regels zijn uit de vroegere routes en het bestaande `aetherCanEditCharacter()` afgeleid. De portraitregel staat nu expliciet als `aetherCanManageCharacterPortrait()` in `characterAccess.php`. Een onbekend character geeft HTTP 404. Verkeerd eigenaarschap, verkeerde rol en een ongeldige CSRF-token geven HTTP 403 voordat een bestand of databaserij wordt gewijzigd.

## Portretopslag en validatie

Portretten hebben geen databasekolom. De opslaglocatie is de bestaande map `img/portret` (exact deze hoofdletters). Historische bestanden met naam `<character-id>.png` blijven leesbaar. Nieuwe bestanden krijgen uitsluitend server-side een naam volgens `<character-id>-<32 hextekens>.png`. Browserbestandsnamen en paden worden nooit gebruikt.

De upload behoudt de bestaande limiet van 10 MB en de afbeeldingssoorten die de oude GD-verwerking functioneel kon lezen: JPEG, PNG, GIF, WebP, BMP, WBMP en, wanneer de PHP-versie dit kent, AVIF. De controle gebruikt de werkelijke bestandsgrootte en `getimagesize()`. Wanneer Fileinfo beschikbaar is, moet ook de door `finfo` gedetecteerde MIME overeenkomen. De door de browser opgegeven extensie, naam, MIME en grootte verlenen geen vertrouwen. De bestaande 35:45-uitsnede, maximale hoogte van 1024 pixels en opslag als PNG blijven behouden.

Bestandspaden worden vóór rename of verwijdering aan de echte portraitmap en het strikte naamformaat getoetst. Symlinks, dubbele extensies, `default.png`, bestanden van andere characters en bestanden buiten de map worden niet geraakt. Het actieve portret wordt bij lezen bepaald uit de bestaande legacynaam of de nieuwste geldige gegenereerde naam.

### Veilige vervanging en verwijdering

Een upload wordt eerst naar een willekeurig tijdelijk bestand verwerkt en daarna atomisch naar zijn definitieve willekeurige naam hernoemd. Pas wanneer het nieuwe bestand bestaat, gaan oude, aantoonbaar bij het character horende portretten naar `img/portret/.quarantine`. Als het verplaatsen van een oud bestand mislukt, worden reeds verplaatste bestanden teruggezet en wordt het nieuwe bestand verwijderd. Na succes worden quarantainestukken best-effort opgeruimd.

Portretverwijdering verplaatst uitsluitend de door de server gevonden characterbestanden naar dezelfde quarantaine. De map bevat een `.htaccess` die toegang voor Apache 2.4 en oudere Apache-configuraties weigert. Een opruimfout laat daardoor een niet-publiek weesbestand achter en maakt de geslaagde verwijdering niet opnieuw zichtbaar. Herstel- en opruimfouten worden zonder volledig serverpad gelogd.

De productiehost moet de reeds eerder benodigde PHP-GD-ondersteuning hebben voor de beeldtransformatie. Fileinfo is aanbevolen en wordt automatisch aanvullend gebruikt. Er is geen nieuwe Composer- of Node.js-dependency toegevoegd.

## Characterverwijdering en databasevolgorde

De SQL-dump en de bestaande route zijn vergeleken. De volgende relaties hebben `ON DELETE CASCADE` vanuit `tblCharacter` en worden door MySQL met het character verwijderd:

- diary en diaryvisibility;
- gossip attempts en unlocks als viewer of bron;
- sections;
- action state en action-use-audit;
- characterspecialisaties;
- ties als eigenaar of doel;
- traitlinks; de gekoppelde companysharelink cascadeert vervolgens via de traitlink.

De huidige dump heeft geen bruikbare character-cascade voor meerdere andere tabellen. Binnen één transactie voert de lifecycle-repository daarom in deze volgorde vaste prepared statements uit:

1. `tblCompanySnapshotPayout` voor het character;
2. `tblCharacterBankTransaction` met het character als bron of doel;
3. `tblCharacterSecuritiesTransaction` van het character;
4. `tblCharacterEconomySnapshot` van het character;
5. company-personnel-specialisaties en -skills via de personeelslink;
6. `tblCompanyPersonnel` van het character;
7. `tblLinkCharacterTraitCompany` vóór de traitcascade;
8. `tblLinkCharacterSkill` van het character;
9. `tblCharacterLanguage` (expliciet en tevens veilig bij de aanwezige cascade);
10. live `tblCharacter`-verwijzingen naar dit character als effectenbeheerder worden teruggezet naar `self` met een lege manager-ID;
11. de geselecteerde rij uit `tblCharacter`.

Economiesnapshots van andere characters worden niet herschreven wanneer hun historische `securitiesManagerCharacterId` naar het verwijderde character wees. Die kolom beschrijft de historische snapshot en heeft in het huidige schema bewust geen foreign key. Het actuele beheer op levende characters wordt wel consistent gemaakt.

## Database- en bestandsconsistentie

Vóór de databasetransactie wordt alleen het eigen portret naar quarantaine verplaatst. Als dit mislukt, start geen databasewrite. Daarna worden alle gekoppelde deletes, de managerupdate en de characterdelete in één PDO-transactie uitgevoerd.

- Bij een SQL- of commitfout wordt de transactie teruggedraaid en wordt het gequarantaineerde portret teruggezet.
- Na een geslaagde commit wordt het gequarantaineerde portret best-effort verwijderd.
- Als die laatste unlink mislukt, blijft de databaseverwijdering geldig en blijft alleen een door `.htaccess` afgeschermd weesbestand achter. Dit kan later handmatig uit `.quarantine` worden verwijderd.
- Een procescrash tussen bestandsstaging en rollback kan eveneens een quarantaineweesbestand achterlaten. De database blijft dan leidend; de bestandsnaam bevat geen browserinput en is niet uitvoerbaar.

Deze volgorde voorkomt dat een databasefout een zichtbaar portret definitief verwijdert en voorkomt dat een geslaagde characterdelete een publiek portret laat staan. Volledige atomiciteit tussen MySQL en het bestandssysteem is zonder een externe transactielaag niet mogelijk; de quarantaine beperkt dat risico.

## Architectuur en gewijzigde bestanden

- `api/characters/uploadCharacterPortrait.php`: dun multipartendpoint; auth, CSRF, formulierparser, schema, service en gedeelde response.
- `api/characters/deleteCharacterPortrait.php`: dun JSON-endpoint voor portretverwijdering.
- `api/characters/deleteCharacter.php`: dun JSON-endpoint voor transactionele lifecycleverwijdering.
- `api/characters/characterAccess.php`: expliciet portraitbeleid met vertrouwde usercontext.
- `api/characters/characterMediaUtils.php`: veilige bestandsnamen, padcontrole, legacy-resolutie en quarantine-operaties.
- `api/characters/characterPortraitImage.php`: inhouds-, MIME-, grootte- en dimensiecontrole plus de bestaande uitsnede naar PNG.
- `api/characters/characterPortraitService.php`: upload-, vervangings- en verwijdervolgorde en verwachte domeinfouten.
- `api/characters/characterLifecycleRepository.php`: vaste characterdeletequeries en gerelateerde records.
- `api/characters/characterLifecycleService.php`: toegangscontrole, PDO-transactie en coördinatie met de portraitquarantaine.
- `img/portret/.quarantine/.htaccess`: blokkeert publieke toegang tot gestagede bestanden.
- `tests/character_lifecycle_portrait_endpoints_test.php`: stateful routes, PDO-testdouble en tijdelijke bestanden.
- `tests/access_control_route_coverage_test.php`: volgt het verplaatste portrait- en lifecyclebeleid.
- `tests/character_input_validation_test.php`: bewaakt de directe gedeelde parsers en schema's.

`characterSchemas.php` bevatte de drie benodigde schema's al en hoefde niet gewijzigd te worden. Frontendcode en databaseopbouw zijn ongewijzigd.

## Uitgevoerde tests

De nieuwe endpointtest voert de echte endpoints, gedeelde helpers, schemas, policies, services en repositories uit in een tijdelijke kopie. Alleen `db.php`, PDO en de fysieke beeldtransformatie hebben testdoubles. Bestandsoperaties gebruiken echte tijdelijke mappen en bestanden.

Gedekt zijn onder meer:

- geldige PNG-upload, willekeurige servernaam en compatibele response;
- echte inhoud ondanks misleidende naam/MIME, niet-afbeelding, beschadigde afbeelding, te groot bestand en uploadfouten;
- eigen/vreemd character, eigen `extra`, director, administrator, niet aangemeld, vervalste sessierol en CSRF;
- onbekende, ontbrekende, ongeldige en onverwachte velden;
- veilige vervanging, processor-/rename-/unlinkfouten en bescherming van standaard-, vreemde en buiten-patroonbestanden;
- portretverwijdering met en zonder fysiek bestand;
- characterverwijdering met alle twaalf voorbereide mutaties en exacte ID-parameters;
- commit, rollback halverwege, portretherstel en nul gecommitte writes bij weigering of rollback;
- generieke HTTP 500 zonder SQL-, PDO- of padgegevens.

Uitgevoerd met PHP 8.4.25:

| Controle | Resultaat |
|---|---|
| `tests/character_lifecycle_portrait_endpoints_test.php` | geslaagd |
| alle overige `tests/*_test.php` regressietests | geslaagd, behalve onderstaande skip |
| `tests/authenticated_user_test.php` | niet uitgevoerd: test meldt `SKIP` omdat PDO SQLite ontbreekt |
| PHP-syntaxcontrole van 130 niet-legacy PHP-bestanden | geslaagd |
| `git diff --check` | geslaagd |

De lokale PHP-build heeft geen GD, Fileinfo of PDO SQLite. De tests gebruiken daarom een beeldprocessordouble en een PDO-testdouble. Werkelijke GD-transformatie, de aanvullende Fileinfo-tak, MySQL foreign keys/transacties, PHP-uploadintegratie, Apache `.htaccess`, WordPress-sessie-integratie en browsergedrag zijn lokaal niet als integratietest uitgevoerd. De echte `getimagesize()`-controle, bestanden, routevolgorde, policies, prepared parameters en transactiestate zijn wel uitgevoerd. Er zijn geen productiegegevens gebruikt.

## Online checklist voor `aetherapp-dev`

- [ ] Upload als participant op het eigen player-character en eigen extra een JPEG, PNG, GIF, WebP, BMP/WBMP en, indien PHP/GD dit ondersteunt, AVIF onder 10 MB.
- [ ] Controleer de 35:45-uitsnede, `portraitUrl`, herladen en vervanging van een bestaand legacyportret.
- [ ] Probeer een tekstbestand met afbeeldingsnaam, een beschadigd beeld, een bestand boven 10 MB en een extra uploadveld; de oude afbeelding moet blijven staan.
- [ ] Controleer als participant dat upload, portretverwijdering en characterverwijdering op andermans character vóór elke mutatie worden geweigerd.
- [ ] Controleer portraitbeheer en characterverwijdering als director en administrator.
- [ ] Verstuur iedere schrijfactie zonder CSRF-token en met een fout token; database en bestanden mogen niet wijzigen.
- [ ] Verwijder een portret tweemaal; het tweede verzoek moet gecontroleerd `{ "status": "ok" }` geven en geen ander bestand raken.
- [ ] Verwijder een testcharacter met diary, sections, ties, traits, skills, acties, taal, bank/economie/effecten, company personnel en een portret; controleer dat alleen de gekoppelde actuele gegevens en het eigen portret weg zijn.
- [ ] Controleer dat een ander character dat het verwijderde character als actuele effectenbeheerder had nu `self` gebruikt.
- [ ] Forceer in een veilige testdatabase een SQL-fout halverwege; character en portret moeten behouden blijven.
- [ ] Controleer dat `img/portret/.quarantine` via HTTP niet opvraagbaar is en verwijder eventuele oude `.tmp`-weesbestanden na een back-up.
- [ ] Controleer in de networktab dat succesresponses exact overeenkomen met de tabel hierboven en dat HTTP 500 geen SQL-, pad- of serverdetails bevat.
