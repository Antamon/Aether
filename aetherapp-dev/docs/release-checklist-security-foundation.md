# Release-readiness en productiechecklist: security foundation

> **Update 23 september 2026:** de oudere uitsluiting van `db.php` hieronder is achterhaald door [configuratie-en-integratie-readiness.md](configuratie-en-integratie-readiness.md). `db.php` is nu een voor Git bedoelde bootstrap zonder secrets. Gebruik de nieuwere configuratie- en uploadinstructies voor deze batch.

Datum beoordeling: 17 september 2026  
Beoordeelde branch/commit: `main` op `26f0c7d` (`origin/main` wees tijdens de controle naar dezelfde commit)  
Status: **nog niet vrijgegeven voor productie**

## 1. Go/no-go-samenvatting

Er is geen applicatiefout gevonden die vóór deze voorbereiding nog een codewijziging vereiste. De geautomatiseerde tests, browsertest, syntaxcontrole en padcontrole zijn geslaagd, met één gedocumenteerde skip wegens ontbrekende PDO SQLite.

Een productie-uitrol mag pas starten nadat de volgende operationele blokkades zijn gesloten:

1. **De huidige productiecommit is onbekend.** De repository bevat geen productietag, deploymentmanifest of door productie gepubliceerd commit-ID. Een exacte vergelijking met productie is daardoor niet mogelijk. Download vóór de uitrol een volledige productieback-up en leg minimaal hashes en wijzigingstijden van de huidige bestanden vast.
2. **De werkelijke productie-PHP-runtime is niet gemeten.** De nieuwe code vereist minimaal PHP 8.1 door onder meer `never` en `array_is_list()`. Controleer bij voorkeur PHP 8.3 of nieuwer en bevestig de vereiste extensies: PDO MySQL, DOM, libxml, JSON, session, mbstring en GD. De exportmetadata uit het eerdere architectuuronderzoek noemde PHP 8.3.6, maar dat is geen actuele hostmeting.
3. **Apache/cPanel is niet lokaal geïntegreerd getest.** Bevestig `AllowOverride` en `mod_headers` op de echte host en controleer dat de nieuwe headerregels met de bestaande WordPress-regels zijn samengevoegd.
4. **WordPress en MySQL zijn niet end-to-end getest.** Voer de productie-smoketest met testaccounts uit voordat de release voor gewone gebruikers wordt vrijgegeven.
5. **De productie-basismap is niet bevestigd.** Stel vast of Aether een eigen submap met eigen `.htaccess` heeft. Overschrijf nooit de WordPress-root-`.htaccess`; voeg de Aether-headerregels samen wanneer beide applicaties dezelfde map gebruiken.

De praktische releasevergelijking hieronder gebruikt commit `a702499` (`Aetherapp-dev inti`) als werkbasis. Dat is de commit vóór de beveiligingsreeks, maar is niet bewezen als de huidige productiecommit.

## 2. Beoordeelde commits

| Commit | Onderwerp | Hoofdwijziging |
|---|---|---|
| `6b5c0a9` | Toegangscontrole hersteld | Server-side identiteit/rollen, objecttoegang, bevoegdheidsvelden, CSRF en uitgeschakelde OIDC-callback |
| `70ad111` | Gedeelde API-responseafhandeling | Responsecontracten, character-validatie en veilige tekstweergave |
| `0ac4f55` | Gedeelde request- en validatiebasis | Expliciete JSON/form-readers, generieke validator en characterschema's |
| `f57e1bd` | Veilige rich-textbewerking | HTML Purifier, zes rich-textvelden, editor en veilige rendering |
| `92d5fc2` | Dun `saveCharacterSection`-endpoint | Characterbeleid naar de module en route-orkestratie via gedeelde helpers |
| `26f0c7d` | Assetversioning en updateherkenning | `filemtime`-assets, `VERSION`, updatechecker en cacheheaders |

Er zijn geen tags die een productieversie aanduiden.

## 3. Bestandsinventaris vanaf de praktische basis

Vergelijking: `a702499..26f0c7d`.

- 517 bestanden in totaal;
- 39 nieuwe applicatiebestanden;
- 81 gewijzigde applicatiebestanden;
- 397 nieuwe bestanden onder `vendor/` voor Composer en HTML Purifier;
- 0 verwijderde bestanden;
- 0 SQL-migraties of gewijzigde databaseschemabestanden.

De exacte inventaris kan opnieuw worden gegenereerd met:

```sh
git diff --name-status a702499..26f0c7d
```

### Nieuwe applicatiebestanden

**Root en configuratie**

- `.htaccess`
- `VERSION`
- `asset.php`
- `version.php`
- `composer.json`
- `composer.lock`

**API**

- `api/.htaccess`
- `api/auth/accessControl.php`
- `api/characters/characterAccess.php`
- `api/characters/characterRequestValidation.php`
- `api/characters/characterRichText.php`
- `api/characters/characterSchemas.php`
- `api/shared/appVersion.php`
- `api/shared/assets.php`
- `api/shared/cache.php`
- `api/shared/request.php`
- `api/shared/response.php`
- `api/shared/richText.php`
- `api/shared/validation.php`

**Frontend**

- `js/richTextCharacter.js`
- `js/updateManager.js`

**Documentatie**

- `docs/api-architectuur-en-hergebruik.md`
- `docs/cache-en-updatebeheer.md`
- `docs/character-access-refactor.md`
- `docs/invoervalidatie-characters.md`
- `docs/rich-text-characters.md`
- `docs/save-character-section-pilot.md`
- `docs/toegangscontrole-herstel.md`

**Tests**

- `tests/access_control_route_coverage_test.php`
- `tests/access_control_test.php`
- `tests/api_response_contract_test.php`
- `tests/authenticated_user_test.php`
- `tests/cache_update_management_test.php`
- `tests/character_input_validation_test.php`
- `tests/character_rich_text_test.php`
- `tests/oidc_callback_test.php`
- `tests/request_validation_test.php`
- `tests/save_character_section_endpoint_test.php`
- `tests/update_manager_browser_test.html`

**Dependencytree**

- `vendor/autoload.php` en de volledige `vendor/composer/`-tree;
- de volledige `vendor/ezyang/htmlpurifier/`-tree, versie 4.19.0;
- in totaal 397 getrackte bestanden. Upload deze tree als één ondeelbare dependencyset. Selecteer geen losse bestanden uit `vendor/`.

### Gewijzigde applicatiebestanden

**Rootpagina's en authenticatie**

- `admin.html`
- `checkLogin.php`
- `companies.html`
- `eventParticipation.html`
- `getCurrentUser.php`
- `index.html`
- `sessionUserBootstrap.php`
- `static.html`
- `tokenhandler.php`

**Admin-API**

- `api/admin/adminUtils.php`
- `api/admin/deleteActionUse.php`
- `api/admin/deleteKnowledgeUnlock.php`
- `api/admin/newSkill.php`
- `api/admin/saveKnowledgeVisibility.php`
- `api/admin/saveSkill.php`
- `api/admin/saveSkillType.php`
- `api/admin/updateActionUse.php`

**Character-API**

- `api/characters/AddNewSkill.php`
- `api/characters/addCharacterLanguage.php`
- `api/characters/addSkillSpecialisation.php`
- `api/characters/buyCompanyShare.php`
- `api/characters/characterPointUtils.php`
- `api/characters/deleteBankTransaction.php`
- `api/characters/deleteCharacter.php`
- `api/characters/deleteCharacterEconomySnapshot.php`
- `api/characters/deleteCharacterLanguage.php`
- `api/characters/deleteCharacterPortrait.php`
- `api/characters/deleteCharacterTie.php`
- `api/characters/deleteSkillSpecialisation.php`
- `api/characters/economyUtils.php`
- `api/characters/getCharacter.php`
- `api/characters/getCharacterActionEvents.php`
- `api/characters/getCharacterActionKnowledgeTargets.php`
- `api/characters/getCharacterDiary.php`
- `api/characters/getCharacterLanguageOptions.php`
- `api/characters/getCharacterList.php`
- `api/characters/getCharacterSections.php`
- `api/characters/getCharacterTieOptions.php`
- `api/characters/getCharacterTies.php`
- `api/characters/getDisciplineList.php`
- `api/characters/getNewSkills.php`
- `api/characters/getSkillSpecialisations.php`
- `api/characters/newCharacter.php`
- `api/characters/revealCharacterActionKnowledge.php`
- `api/characters/saveBankTransfer.php`
- `api/characters/saveCharacterDiary.php`
- `api/characters/saveCharacterEconomySnapshot.php`
- `api/characters/saveCharacterSection.php`
- `api/characters/saveCharacterSecuritiesPortfolio.php`
- `api/characters/saveCharacterTie.php`
- `api/characters/saveCompanyShare.php`
- `api/characters/updateCharacter.php`
- `api/characters/updateSkill.php`
- `api/characters/updateTrait.php`
- `api/characters/uploadCharacterPortrait.php`
- `api/characters/useCharacterSkillAction.php`

**Companies, events en users**

- `api/companies/companyUtils.php`
- `api/companies/deleteCompanyLogo.php`
- `api/companies/newCompany.php`
- `api/companies/saveCompanyPersonnel.php`
- `api/companies/saveCompanySnapshot.php`
- `api/companies/updateCompany.php`
- `api/companies/uploadCompanyLogo.php`
- `api/events/getEventList.php`
- `api/events/newEvent.php`
- `api/events/updateEvent.php`
- `api/events/updateParticipation.php`
- `api/users/getUserList.php`

**JavaScript**

- `js/apiCharacter.js`
- `js/backgroundCharacter.js`
- `js/characterFunctions.js`
- `js/companyFunctions.js`
- `js/createCharacter.js`
- `js/diaryCharacter.js`
- `js/formCharacter.js`
- `js/languageCharacter.js`
- `js/mainFunctions.js`
- `js/navCharacter.js`
- `js/passportCharacter.js`
- `js/skillsCharacter.js`
- `js/traitsCharacter.js`

### Niet gewijzigde of te behouden omgevingsdata

- `db.php` is door `.gitignore` uitgesloten en bevat de lokale databaseconfiguratie. Dit bestand is geen onderdeel van de release en mag de productieversie nooit overschrijven.
- `sql/oneiros_be_aether.sql` en alle `*.sql` zijn genegeerd. Er is geen migratie voor deze release.
- `.env`, logs, `.vscode/`, `.git/`, `.github/`, `legacy/`, `docs/` en `tests/` horen niet in de publieke productie-upload.
- `img/portret/` en `img/bedrijfslogo/` bevatten runtimeuploads. Bewaar en synchroniseer de productie-inhoud; vervang of verwijder deze mappen niet vanuit de ontwikkelkopie.
- De productie-WordPressbestanden, waaronder `wp-config.php`, `wp-load.php`, plugins en de WordPress-root-`.htaccess`, vallen buiten deze release.

## 4. Functionele en beveiligingswijzigingen

### Authenticatie, rollen, eigenaarschap en CSRF

- De vertrouwde identiteit komt uit de WordPress/PHP-sessie; de actuele rol wordt uit `tblUser` geladen.
- De canonieke rollen zijn `participant`, `director` en `administrator`. Een browserrol of vervalste sessierol verleent geen rechten.
- Beschermde reads en writes controleren zowel rol als toegang tot het concrete character.
- Participants kunnen geen character van een ander beheren door een ID te wijzigen.
- Eigenaar, type, status, maker en aanmaaktijd worden server-side bepaald of alleen voor bevoegde rollen toegestaan.
- Schrijfroutes controleren een sessiegebonden `X-CSRF-Token` vóór database- of bestandswijzigingen.
- De WordPress-bootstrap vernieuwt het PHP-sessie-ID na authenticatie.

### OIDC-callback

`tokenhandler.php` is bewust fail-closed. De route doet geen tokenuitwisseling en maakt geen sessie. Verwacht HTTP 410, `Cache-Control: no-store` en `X-Content-Type-Options: nosniff`. De actieve WordPress-cookie-login blijft behouden.

Een rollback mag deze callback bij voorkeur niet opnieuw activeren. Het bestand is zelfstandig en kan als beveiligingshotfix op HTTP 410 blijven staan, ook wanneer andere onderdelen worden teruggedraaid.

### Character-validatie en tekstweergave

- Iedere characterroute heeft een expliciet schema met toegestane velden, types, grenzen en enums.
- Onbekende velden worden met HTTP 422 geweigerd.
- `newCharacter.php` en `updateCharacter.php` gebruiken vaste SQL-mappings; browserinput bepaalt geen kolomnaam.
- Gewone tekst wordt via veilige DOM-tekstweergave getoond.

### Rich text

De zes rich-textvelden zijn:

- `personal_background` en `knowledge`;
- `goals` en `achievements`;
- `nature` en `demeanour`.

HTML Purifier 4.19.0 saneert server-side. De characterallowlist beperkt inhoud tot `p`, `br`, `strong`, `em`, `ul`, `ol`, `li` en `h4`. Historische `div`/`span`-inhoud wordt leesbaar genormaliseerd; scripts, events, stijlen, SVG, iframes, objecten, embeds, formulieren en afbeeldingen worden verwijderd. De editor ondersteunt vet, cursief, lijsten, alinea, H4 en opmaak verwijderen.

### Gedeelde API-basis

- `api/shared/request.php`: expliciete JSON-object- en formulierlezers;
- `api/shared/response.php`: bestaande JSON-responsecontracten;
- `api/shared/validation.php`: generieke schema-engine en validators;
- `api/shared/richText.php`: generieke HTML-sanitisatie;
- `api/shared/cache.php`, `assets.php` en `appVersion.php`: cache- en releasehelpers.

Characterschema's en character-specifiek rich-text- en toegangsbeleid blijven in `api/characters/`.

### Characteraccess en `saveCharacterSection.php`

Character- en skillbeleid staat in `api/characters/characterAccess.php`; de algemene authlaag heeft geen terugwaartse dependency naar de personagemodule. `saveCharacterSection.php` is een dun JSON-endpoint met vaste volgorde: dependencies, gebruiker, CSRF, parsing, schema, objecttoegang, sanitisatie/upsert en gedeeld antwoord. De response blijft `{"success":true,"content":"..."}`.

### Asset- en updatebeheer

- Alle lokale CSS/JS-tags in de vijf ingangspagina's lopen via `asset.php`.
- De resolver stuurt zonder cache door naar een `?v=<filemtime>`-URL.
- Externe Bootstrap- en Font Awesome-URL's blijven ongewijzigd.
- `version.php` publiceert de waarde uit `VERSION` met `no-store`.
- `js/updateManager.js` controleert iedere 60 seconden en toont één handmatige vernieuwmelding. Mogelijk niet-opgeslagen invoer krijgt een bevestiging; netwerkfouten blijven stil.
- Er is geen service worker of webmanifest aangetroffen of toegevoegd.

## 5. Expliciete releaseblokkadecontrole

| Controle | Uitkomst | Actie vóór productie |
|---|---|---|
| Productiecommit bekend | **Blokkade** | Identificeer via bestaand deploymentrecord of maak een volledige productieback-up/hashinventaris en accepteer die als rollbackbasis. |
| Geheimen of lokale DB-config in release | Geslaagd voor Git-delta | Geen secretpatronen in de getrackte applicatiedelta gevonden. `db.php`, `.env`, SQL en logs blijven uitgesloten. Controleer ook het handmatig samengestelde FTP-pakket. |
| Lokale ontwikkelconfig niet uploaden | Voorwaardelijk | Sluit `db.php`, `sql/`, `.vscode/`, `.git/`, logs en lokale editorbestanden expliciet uit. |
| Tests/docs/tijdelijke bestanden niet publiek | Voorwaardelijk | Upload `tests/` en `docs/` niet. Tijdelijke Edge-output was na de test verwijderd. Plaats releasearchieven buiten de documentroot. |
| Nieuwe PHP-includes bestaan | Geslaagd | 251 letterlijke application-includes gecontroleerd; geen ontbrekende targets. |
| Linux-hoofdletters | Geslaagd | PHP-includes en 85 frontend/API/assetpaden komen exact overeen met de bestandsnamen. `AddNewSkill.php` wordt ook met hoofdletters aangeroepen. |
| `.htaccess` blokkeert routes | Codecontrole geslaagd | De regels zetten alleen headers en bevatten geen rewrite- of deny-regels. Merge met bestaande WordPressregels en voer de host-smoketest uit. |
| Ontbrekende `mod_headers` | Geslaagd in configuratie | Alle `Header`-regels staan binnen `<IfModule mod_headers.c>`; zonder module worden ze overgeslagen. Productiegedrag is niet lokaal met Apache getest. |
| `VERSION` uniek | Geslaagd binnen Git | `2026.09.17.1` komt slechts in commit `26f0c7d` voor. Bevestig of kies onmiddellijk vóór uitrol een definitieve unieke release-ID. |
| Alle lokale CSS/JS via resolver | Geslaagd | Geen directe `href="css/..."` of `src="js/..."` meer in de vijf ingangspagina's. |
| Rich-textdependency volledig | Geslaagd in repository | Autoload van HTML Purifier 4.19.0 werkt; 397 dependencybestanden staan in Git. Upload `vendor/` als volledige tree. |
| Productie-PHP compatibel | **Blokkade** | Meet CLI én web-SAPI. Vereist PHP >= 8.1, PDO MySQL, DOM/libxml, JSON, session, mbstring en GD. |
| Databasewijziging nodig | Geslaagd | Geen schemawijziging of migratie; maak toch een databaseback-up wegens smoketestwrites en rollback. |
| `private, no-store` te breed | Geslaagd met bewuste keuze | `api/.htaccess` maakt alle API-responses niet cachebaar. Dit omvat eventueel onschuldige API-responses, maar blokkeert ze niet en voorkomt alleen browser/proxycache. Publieke CSS/JS staat buiten `api/` en blijft langdurig cachebaar met `?v=`. `asset.php` en `version.php` hebben bewust `no-store`. |

## 6. Werkelijk uitgevoerde tests

Lokale runtime: PHP 8.4.25. Deze build heeft DOM/libxml maar geen PDO-driver; daarom waren geen SQLite- of MySQL-integratietests mogelijk.

| Test | Resultaat |
|---|---|
| `tests/access_control_test.php` | Geslaagd |
| `tests/authenticated_user_test.php` | **Niet uitgevoerd**: test meldt `SKIP: PDO SQLite is niet beschikbaar.` |
| `tests/access_control_route_coverage_test.php` | Geslaagd |
| `tests/request_validation_test.php` | Geslaagd |
| `tests/api_response_contract_test.php` | Geslaagd |
| `tests/character_input_validation_test.php` | Geslaagd |
| `tests/character_rich_text_test.php` | Geslaagd, inclusief XSS/allowlistgevallen |
| `tests/oidc_callback_test.php` | Geslaagd |
| `tests/save_character_section_endpoint_test.php` | Geslaagd met PDO-testdouble; geen echte MySQL-query uitgevoerd |
| `tests/cache_update_management_test.php` | Geslaagd |
| `tests/update_manager_browser_test.html` | Geslaagd in geïnstalleerde Microsoft Edge headless; resultaat `PASS` |
| PHP-syntax, alle PHP buiten `legacy/` | Geslaagd: 356 bestanden |
| PHP include-pad/hoofdlettercontrole | Geslaagd: 251 letterlijke includes |
| Frontend/API/asset-padcontrole | Geslaagd: 85 paden |
| HTML Purifier autoload | Geslaagd: `v4.19.0` geladen |
| Lokale HTTP `tokenhandler.php` | HTTP 410 met `no-store` |
| Lokale HTTP `version.php` | HTTP 200, juiste versie en `no-store` |
| Lokale HTTP `asset.php` | HTTP 302, `no-store`, actuele `?v=`-Location |
| `git diff --check` vóór dit rapport | Geslaagd |

Niet geclaimd of niet uitgevoerd:

- geen productie- of cPaneltest;
- geen Apache-interpretatie van `.htaccess`;
- geen echte WordPress-login/cookie/sessie-integratietest;
- geen MySQL/MariaDB-integratietest en geen controle van productiedata;
- geen echte upload van portretten of bedrijfslogo's;
- geen productiecache-, proxy- of CDN-test.

## 7. Releasepakket voor cPanel/FTP

### Wel opnemen

1. Alle hierboven vermelde nieuwe en gewijzigde runtimebestanden onder root, `api/` en `js/`.
2. De volledige `vendor/`-tree uit deze commit.
3. `.htaccess` en `api/.htaccess`, na samenvoeging met bestaande productieregels.
4. `VERSION`, met de definitieve unieke release-ID.
5. `composer.json` en `composer.lock` in het bewaarde release-artefact. Ze zijn niet nodig tijdens requests; wanneer beleid dit toestaat mogen ze mee naar de applicatiemap, maar plaats het release-artefact zelf buiten de documentroot.

### Niet uploaden of overschrijven

- productie-`db.php`;
- `.env`, `sql/`, database-exports, logs en foutlogs;
- `tests/`, `docs/`, `.git/`, `.github/`, `.vscode/` en lokale tijdelijke bestanden;
- WordPress core, `wp-config.php`, plugins en themes;
- de WordPress-root-`.htaccess` zonder gecontroleerde merge;
- productie-inhoud van `img/portret/` en `img/bedrijfslogo/`;
- andere gebruikersuploads of handmatig beheerde productieconfiguratie.

## 8. Gecontroleerd deploymentplan

### Fase A — verplichte preflight

- [ ] Leg de huidige productie-URL, applicatiemap en documentroot vast.
- [ ] Stel vast of de Aethermap een eigen `.htaccess` heeft of dezelfde map als WordPress gebruikt.
- [ ] Noteer de huidige productiecommit als die beschikbaar is. Anders archiveer de volledige huidige applicatiemap als de formele rollbackbasis.
- [ ] Controleer via cPanel **Select PHP Version** of een tijdelijke `phpinfo()` buiten publiek bereik: PHP >= 8.1 en PDO MySQL, DOM, libxml, JSON, session, mbstring en GD. Verwijder een tijdelijk infobestand onmiddellijk.
- [ ] Controleer voldoende vrije schijfruimte voor productie, staging en rollbackkopie.
- [ ] Bouw een schoon releasepakket vanaf commit `26f0c7d`; voeg dit rapport niet toe aan het publieke pakket.
- [ ] Scan het pakket opnieuw op `db.php`, `.env`, SQL, logs, tests, docs en editorbestanden.
- [ ] Controleer dat de volledige `vendor/`-tree en `vendor/autoload.php` aanwezig zijn.

### Fase B — back-ups

- [ ] Zet de applicatie kort in onderhoudsmodus of kondig een schrijfstilte aan.
- [ ] Maak via cPanel een volledige database-export met schema, data, triggers en correcte tekencodering.
- [ ] Download/archiveer de volledige huidige Aethermap, inclusief verborgen `.htaccess`, productie-`db.php` en runtimeuploads.
- [ ] Maak afzonderlijke kopieën van `db.php`, de bestaande `.htaccess`, `img/portret/` en `img/bedrijfslogo/`.
- [ ] Controleer dat de archieven geopend kunnen worden en noteer grootte, tijdstip en locatie.

### Fase C — voorkeursmethode: stagingmap en atomaire omschakeling

Gebruik bij voorkeur cPanel File Manager of SSH voor directoryrenames op hetzelfde filesystem. FTP-upload rechtstreeks over de actieve map is niet atomair.

1. Maak naast de actieve map een stagingmap, bijvoorbeeld `aetherapp-release-2026-09-17-1`.
2. Upload eerst de volledige `vendor/`-tree.
3. Upload daarna de gedeelde basis: `api/shared/`, `api/auth/accessControl.php`, `api/characters/characterAccess.php`, `characterSchemas.php`, `characterRequestValidation.php`, `characterRichText.php` en `sessionUserBootstrap.php`.
4. Upload vervolgens alle overige gewijzigde API-routes en PHP-bestanden.
5. Upload alle gewijzigde en nieuwe JavaScriptbestanden.
6. Upload `asset.php`, `version.php`, de HTML-pagina's en de samengevoegde `.htaccess`-bestanden.
7. Kopieer de productie-`db.php` naar de stagingmap zonder de inhoud via onveilige kanalen te tonen.
8. Kopieer/synchroniseer productie-`img/portret/` en `img/bedrijfslogo/` naar de stagingmap. Herhaal deze synchronisatie direct vóór de omschakeling als uploads nog mogelijk waren.
9. Plaats de definitieve `VERSION` als laatste bestand in staging.
10. Test syntax, autoload en de publieke niet-database-endpoints via een afgeschermde staging-URL. Een andere URL kan WordPress-cookiepaden beïnvloeden; beschouw dit niet als volledige loginvalidatie.
11. Activeer onderhoudsmodus, maak zo nodig een laatste databaseback-up en stop writes.
12. Hernoem de actieve map naar een tijdgestempelde rollbackmap en hernoem staging onmiddellijk naar de oorspronkelijke actieve mapnaam.
13. Controleer eigenaar, bestandsrechten en schrijfrechten van de twee uploadmappen.
14. Voer de productie-smoketest uit en beëindig onderhoudsmodus pas na de kritieke controles.

### Alternatief: in-place FTP

Gebruik dit alleen als een directorywissel onmogelijk is, en houd onderhoudsmodus actief gedurende de hele upload.

1. Upload `vendor/` volledig.
2. Upload nieuwe shared/auth/characterhelpers vóór routes die ze includen.
3. Upload daarna alle gewijzigde API-routes en root-PHP-bestanden.
4. Upload nieuwe JavaScriptbestanden vóór de HTML-pagina's die ernaar verwijzen.
5. Upload `asset.php`, `version.php` en de cachehelpers vóór de geversioneerde HTML-tags.
6. Upload de HTML-pagina's bijna als laatste.
7. Merge/upload `.htaccess` en `api/.htaccess`.
8. Upload/rename `VERSION` als laatste activatiestap.
9. Laat `db.php` en runtimeuploads ongemoeid.

Zonder onderhoudsmodus kunnen requests tijdens stappen 2–6 een endpoint treffen waarvan een vereiste helper nog ontbreekt, of HTML ontvangen dat al naar een nog niet geüploade asset verwijst.

## 9. Directe productiecontroles

Voer onmiddellijk na omschakeling uit:

- [ ] `version.php` geeft HTTP 200, de definitieve versie en `Cache-Control` met `no-store`.
- [ ] `tokenhandler.php` geeft HTTP 410 en `Cache-Control: no-store`.
- [ ] `asset.php?path=js/updateManager.js` geeft HTTP 302 en een bestaande `js/updateManager.js?v=...`-Location.
- [ ] De uiteindelijke CSS/JS-response met `?v=` heeft langdurige `public, max-age=31536000, immutable` caching.
- [ ] `index.html` herbevestigt via `no-cache, must-revalidate`.
- [ ] Een gebruikersgebonden API-response heeft `private, no-store` en nooit `public`.
- [ ] Browserconsole en PHP error log tonen geen include-, class-, header- of filesystemfouten.
- [ ] `vendor/autoload.php` en HTML Purifier laden zonder 500-respons.
- [ ] Portretten en bedrijfslogo's zijn zichtbaar en de mappen blijven schrijfbaar.

## 10. Productie-smoketest

Gebruik afgebakende testaccounts en testcharacters. Noteer per stap gebruiker, tijdstip, request, verwachte status en werkelijk resultaat.

### Authenticatie en anoniem

- [ ] WordPress-login opent Aether met dezelfde aangemelde gebruiker.
- [ ] Uitloggen uit WordPress maakt beschermde Aetherdata ontoegankelijk.
- [ ] Een anonieme rechtstreekse beschermde read geeft HTTP 401.
- [ ] Een anonieme rechtstreekse write geeft HTTP 401 en verandert geen data.
- [ ] `tokenhandler.php` geeft HTTP 410, `no-store` en maakt geen sessie.

### Participant

- [ ] Participant ziet uitsluitend eigen characters in de lijst.
- [ ] Participant kan het eigen toegestane player-character bekijken en wijzigen.
- [ ] Participant kan een eigen extra bekijken en alleen de bestaande toegestane diaryhandeling uitvoeren.
- [ ] Het ID van een character van een ander geeft HTTP 403 en geen datamutatie.
- [ ] Een meegestuurde `role=administrator`, andere `idUser`, eigenaar, `state`, `type`, `createdBy` of `createdAt` geeft geen extra rechten.
- [ ] Participant kan geen geheime skill zien of toevoegen.
- [ ] Een publieke skill blijft bruikbaar volgens de bestaande regels.

### Director en administrator

- [ ] Director kan volgens de rechtenmatrix alle characters bekijken en beheren.
- [ ] Administrator behoudt dezelfde characterrechten.
- [ ] Director en administrator kunnen geheime skills beheren.
- [ ] Alleen administrator kan skillcategorieën beheren.
- [ ] Beheer van events, companies en users werkt voor de bedoelde beheerrollen.

### CSRF en neveneffecten

- [ ] Geldige write met het door login uitgegeven CSRF-token slaagt.
- [ ] Dezelfde write zonder token geeft HTTP 403.
- [ ] Dezelfde write met aangepast token geeft HTTP 403.
- [ ] Vergelijk database/bestand vóór en na beide weigeringen: geen wijziging of upload.
- [ ] Portret- en logoupload sturen CSRF mee en blijven werken met geldige PNG/JPEG-testdata.

### Character-validatie en rich text

- [ ] Onbekend veld of gemanipuleerde kolomnaam geeft HTTP 422.
- [ ] Ongeldig datatype, te lange tekst en ongeldige enum geven HTTP 422.
- [ ] Gewone tekstvelden tonen `<script>` letterlijk en voeren niets uit.
- [ ] Background: `personal_background` en `knowledge` tonen de editor en bewaren toegestane opmaak.
- [ ] Diary: `goals` en `achievements` tonen de editor en bewaren toegestane opmaak.
- [ ] Personality: `nature` en `demeanour` tonen de editor en bewaren toegestane opmaak.
- [ ] Vet, cursief, lijsten, alinea en H4 blijven na opslaan en opnieuw openen aanwezig.
- [ ] Historische `div`/`span`-inhoud blijft leesbaar zonder inline stijlen.
- [ ] Scripts, eventhandlers, SVG, iframe, object, embed, style en afbeeldingen verdwijnen.
- [ ] `saveCharacterSection.php` retourneert de gesaniteerde `content` in de compatibele succesresponse.

### Cache en updateherkenning

- [ ] `version.php` geeft exact de geactiveerde waarde uit `VERSION` en `no-store`.
- [ ] Alle lokale CSS/JS-aanvragen lopen via `asset.php` en eindigen met `?v=`.
- [ ] Een gewijzigde asset heeft na upload een andere `?v=`-waarde; een ongewijzigde asset mag uit cache komen.
- [ ] Open vóór de definitieve `VERSION`-activatie een gecontroleerde testtab; wijzig/activeer daarna `VERSION` en wacht maximaal ongeveer 60 seconden.
- [ ] Precies één updatemelding verschijnt en de pagina herlaadt niet automatisch.
- [ ] Na invoer in een veld vraagt de vernieuwknop bevestiging.
- [ ] Een tijdelijk onbereikbaar `version.php` veroorzaakt geen herhaalde foutmeldingen voor de gebruiker.
- [ ] Een API-response met gebruikersdata heeft `private, no-store`; CSS/JS met `?v=` heeft juist publieke immutable caching.

## 11. Rollback

### Voorkeur: directoryrollback

1. Activeer onderhoudsmodus en stop writes.
2. Bewaar logs en foutdetails van de mislukte release.
3. Hernoem de nieuwe actieve map naar een quarantainenaam.
4. Hernoem de onaangeroerde rollbackmap terug naar de oorspronkelijke actieve mapnaam.
5. Behoud waar mogelijk de veilige HTTP-410-versie van `tokenhandler.php`; het terugzetten van de oude callback herintroduceert het vastgestelde beveiligingsprobleem.
6. Herstel de database alleen wanneer de release/smoketest ongewenste data heeft gewijzigd. Er is geen schemamigratie terug te draaien.
7. Controleer WordPress-login, anonieme blokkade en kritieke reads opnieuw voordat onderhoudsmodus wordt beëindigd.

### Bestandsrollback bij in-place upload

- herstel alle 81 gewijzigde applicatiebestanden uit de gecontroleerde productieback-up;
- herstel de oorspronkelijke productie-`.htaccess` en `api/.htaccess`-situatie;
- verwijder de 39 nieuwe applicatiebestanden alleen wanneer de back-up bevestigt dat ze vóór de release niet bestonden;
- verwijder de nieuwe `vendor/`-tree alleen wanneer productie die vóór de release niet gebruikte;
- herstel nooit `db.php` uit ontwikkeling;
- laat `img/portret/` en `img/bedrijfslogo/` intact;
- herstel zo nodig de database-export, rekening houdend met writes die na de back-up plaatsvonden;
- gebruik bij een herstel waarbij updatebeheer actief blijft een nieuwe unieke rollbackwaarde in `VERSION`, zodat open browsers de wissel herkennen.

Omdat de oorspronkelijke productiecommit onbekend is, is de gedownloade productieback-up de enige betrouwbare bron voor een exacte bestandsrollback.

## 12. Releasebesluit

**NO-GO totdat sectie 1 is afgehandeld.** Na bevestiging van productie-baseline, PHP/extensies, applicatiemap/`.htaccess` en een geldige back-up kan deze release volgens het staging- en smoketestplan worden uitgerold. Dit document heeft geen deployment, push of productieactie uitgevoerd.
