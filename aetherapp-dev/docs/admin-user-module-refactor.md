# Admin- en gebruikersmodule: gerichte refactor (2026-09-23)

## Actief oppervlak en contract

De actieve adminpagina (`js/adminFunctions.js`) gebruikt vijf skillroutes. `js/characterFunctions.js`, `js/createCharacter.js`, `js/eventFunctions.js` en `js/navCharacter.js` gebruiken de gebruikerslijst. De acht eventgebonden routes voor knowledge, visibility en action uses in `api/admin` zijn al tijdens de eventrefactor aangepast en zijn in deze batch niet gewijzigd. Er bestaat geen actieve API-route onder `api/users` voor details, rollen wijzigen, accounts koppelen, deactiveren of verwijderen. Traitbeheer loopt via character-/andere bestaande routes, niet via deze adminbatch.

| Route | Request | Succesresponse | Toegang |
| --- | --- | --- | --- |
| `getSkillList.php` | GET, geen velden | `{skills, skillTypes}` | director, administrator |
| `getSkill.php` | POST JSON `{idSkill}` | skillobject inclusief categories, specialisations en holders | director, administrator |
| `newSkill.php` | POST JSON `{name}` | `{skill, skills}` | director, administrator + CSRF |
| `saveSkill.php` | POST JSON `{idSkill,name,description,beginner,professional,master,isSecret,categoryIds,specialisations}`, `Idempotency-Key`-header | `{skill, skills}` | director, administrator + CSRF |
| `saveSkillType.php` | POST JSON `{action,idSkillType,name}` | `{skillTypes}` | administrator + CSRF |
| `api/users/getUserList.php` | GET, geen velden | array van `{id,username,firstName,lastName,role,displayName}` | director, administrator |

De frontend stuurt JSON via `apiFetchJson`; alleen `saveSkill.php` gebruikt nu `apiFetchIdempotentJson` met dezelfde JSON-body en een extra request-ID-header. CSRF komt uit de bestaande sessiegebonden header. Alle rollen worden voor iedere aanvraag opnieuw uit `tblUser` geladen. De browserrol, een sessierol en meegestuurde autoriteitsvelden worden niet vertrouwd. Onbekende velden geven 422. Lege, ongeldige of niet-object-JSON geeft 400. Onbekende skills/categorieën geven 404; naamconflicten of een gekoppelde specialisatie die niet kan verdwijnen geven 409. Technische fouten geven 500 zonder interne details.

## Implementatie en regels

- `adminAccess.php`: actuele gebruiker en director-/administratorbeleid; bestaande eventhelpers in `adminUtils.php` blijven voor de eventroutes bestaan.
- `adminSchemas.php`: expliciete velden en grenzen. Skillnaam 1–30 tekens; beschrijving en niveauteksten maximaal 1200; categorienaam maximaal 50; specialisatienaam 1–100. IDs zijn gehele getallen. `isSecret` is een echte boolean. `categoryIds` en `specialisations` zijn lijsten met expliciet gecontroleerde elementen. Skill- en categorietekst blijft gewone tekst; de adminfrontend toont DB-tekst via `textContent` of formuliervelden.
- `adminSkillRepository.php`: vaste prepared lookups en vergrendelingen; geen browserwaarden als SQL-identifiers.
- `adminSkillService.php`: skillcreatie, skillupdate en categoriebeheer; gekoppelde writes en het opbouwen van de succesresponse vallen binnen één PDO-transactie. Een exception rolt terug. Voor `saveSkill.php` beheert de gedeelde idempotentielaag die transactie, zodat requestregistratie, gekoppelde writes en opgeslagen response samen committen. Een herhaling met dezelfde sleutel en payload geeft de eerder opgeslagen response; dezelfde sleutel met andere payload geeft 409. Auth, CSRF en objectbestaan worden opnieuw gecontroleerd. De eerste poging krijgt vóór de idempotentieclaim een alleen-lezen beleidscontrole; onder de transactionele locks wordt die herhaald. Bestaande responsevelden worden door de bestaande readhelpers samengesteld. `adminUtils.php` geeft een fout bij een mislukte categorielezing nu door, zodat een databasefout geen lege 200-lijst wordt.
- `userRepository.php` en `userService.php`: vaste gebruikersquery en dezelfde `displayName`-afleiding als eerder. `getUserList.php` heeft geen sessie- of rolherlezing buiten de centrale authlaag.
- De vijf skillendpoints en de gebruikerslijstroute lezen nu alleen auth, input en service-uitkomst en gebruiken gedeelde JSON-responses.

Een bestaande specialisatie zonder verwijzing mag nog worden verwijderd. **Bewuste veiligheidswijziging:** `saveSkill.php` weigert verwijdering met 409 wanneer die specialisatie nog gekoppeld is aan een character of companypersoneel. De oude route verwijderde die koppelingen stil mee. Er is geen projectregel die dat gegevensverlies rechtvaardigt. De beheerder moet de koppelingen eerst via hun eigen module afhandelen. Bij succes blijven naam, visibility, categorylinks en specialisaties volgens de bestaande regels bewaard. Het type `kind` van een bestaande specialisatie blijft behouden; nieuwe specialisaties erven de disciplinecategorie of krijgen `specialisation`.

## Database en afhankelijkheden

De beschikbare export toont `tblSkill`, `tblSkillType`, `tblLinkSkillType`, `tblSkillSpecialisation`, `tblCharacterSpecialisation` en `tblCompanyPersonnelSkillSpecialisation`, inclusief indexen op de koppelingen. Skills worden ook gebruikt door character- en companypersoneelroutes, action uses en readmodellen. In deze admin-UI bestaat geen skilldelete. Categorie verwijderen verwijdert de bestaande skill-categoriekoppelingen in dezelfde transactie; een categoriecode is verder geen identiteits- of autoriteitsbron. De beschikbare export kan achterlopen op de online testdatabase; deze batch veronderstelt geen niet-bevestigde migratiestatus.

Er is **geen migratie 0006** nodig: de aangepaste routes gebruiken bestaande kolommen en indexen, waaronder de reeds aanwezige `tblApiIdempotency` met unieke `(idUser, operation, requestKey)`-sleutel. De bestaande gedeelde helper bewaart idempotentieregistraties zeven jaar; opruiming volgt het bestaande modulebrede beleid en wordt in deze batch niet gewijzigd. `sql/migrations/inspect_admin_user_integrity_readonly.sql` bevat alleen-lezen controles op dubbele namen en verweesde of niet-passende koppelingen voor de afzonderlijke testdatabase. Het SQL-bestand is optioneel voor inspectie; voer geen schemawijziging uit voor deze batch. De testdatabase en WordPress-/MariaDB-integratie zijn lokaal niet benaderd.

`getCurrentUser.php` blijft het bestaande WordPress-bootstrap- en participant-provisioningpad. De admin- en gebruikerslijstroutes provisioneren nooit. Er is geen UI of endpoint voor rolwijziging, zelfpromotie, laatste-administratorregel of herkoppeling van een WordPress-ID. Daarvoor is geen nieuwe bedrijfsregel ingevoerd. `tokenhandler.php` blijft uitgeschakeld.

## Tests en grenzen

`tests/admin_user_module_endpoints_test.php` voert de echte zes routes in een geïsoleerde kopie uit met een stateful PDO-testdouble. Getest: director-/administrator-succes, lijst- en detailresponse, participant/anonymous/CSRF-weigering met nul writes, actuele rol na intrekking ondanks vervalste sessierol, JSON- en veldvalidatie, onbekende skill, gekoppelde versus vrije specialisatie, categoriewrites, prepared parameters, rollback na fout halverwege, generieke 500, ontbrekende request-ID, replay na verloren response (één specialisatie), payloadconflict en geweigerde replay na rolintrekking. De statische routecoveragetest is aangepast aan `aetherRequireAdminEditor` en controleert dat deze de actuele databasegebruiker laadt.

De volledige beschikbare PHP-testreeks is uitgevoerd: **25 PASS, 4 SKIP, 0 FAIL bij de laatste volledige run**. Een eerdere run had één tijdelijke fixturekopieerfout in `update_character_endpoint_test.php`; die test slaagde daarna afzonderlijk en in de laatste volledige run. `authenticated_user_test.php` meldt SKIP omdat PDO SQLite ontbreekt. `character_finance_mariadb_concurrency_test.php`, `company_mariadb_concurrency_test.php` en `event_gossip_mariadb_concurrency_test.php` melden SKIP wegens ontbrekende configuratie voor een wegwerpbare MariaDB-testdatabase. PHP-syntaxcontrole van alle 15 gewijzigde of nieuwe PHP-bestanden en `git diff --check` zijn geslaagd. Een lokale JavaScript-syntaxcheck kon niet draaien omdat Node.js lokaal niet beschikbaar is; de frontendwijziging is één functienaam en wordt in de PHP-endpointtest op aanwezigheid gecontroleerd. Er is geen WordPress-, Apache-, browser- of MySQL/MariaDB-integratietest uitgevoerd. Controleer de categorie- en specialisatiewijzigingen daarom online op de aparte devomgeving voordat ze breder worden uitgerold.

## Handmatige upload naar aetherapp-dev

Runtimebestanden:

- `api/admin/adminAccess.php`
- `api/admin/adminSchemas.php`
- `api/admin/adminSkillRepository.php`
- `api/admin/adminSkillService.php`
- `api/admin/adminUtils.php`
- `api/admin/getSkillList.php`
- `api/admin/getSkill.php`
- `api/admin/newSkill.php`
- `api/admin/saveSkill.php`
- `api/admin/saveSkillType.php`
- `api/users/userRepository.php`
- `api/users/userService.php`
- `api/users/getUserList.php`
- `js/adminFunctions.js`

De SQL-inspectie, tests en dit document hoeven niet in de publieke webmap. Geen `VERSION`-wijziging, geen database-apply. `saveSkill.php` weigert oude, reeds geopende tabs zonder request-ID met HTTP 400 en een herlaadmelding in het API-antwoord; gebruikers moeten de adminpagina na upload opnieuw openen. Verhoog `VERSION` pas als onderdeel van een aparte, gecontroleerde uitrol.

Maak eerst een bestands- en databaseback-up. Upload de nieuwe helpers/repositories/services, daarna `js/adminFunctions.js` en de zes routes binnen een kort onderhoudsvenster of via een tijdelijke map en omschakeling om gemengde versies te vermijden. Open daarna de adminpagina opnieuw in een nieuwe tab. Een oude open tab kan geen nieuwe skillupdate meer doen totdat zij herladen is.

Online UI-controle: [ ] director ziet skills en gebruikers waar bedoeld; [ ] director kan een skill toevoegen/bewerken maar geen categorie beheren; [ ] administrator kan categorie toevoegen, hernoemen en verwijderen; [ ] skilldetails, geheime indicator en gekoppelde characters blijven zichtbaar; [ ] een gekoppelde specialisatie verwijderen toont een duidelijke weigering en laat character-/companygegevens staan; [ ] participant en anonieme gebruiker krijgen geen admin- of gebruikerslijst; [ ] na rolintrekking werkt een oude adminsessie niet meer; [ ] ongeoorloofde of CSRF-loze schrijftests veranderen niets; [ ] geen SQL-details in netwerkresponses.

Resterend risico: gelijktijdige beheer- en companywrites rond een specialisatie zijn zonder echte MariaDB-integratietest niet bewezen. De route vergrendelt de skill, bestaande specialisaties en afhankelijke rijen vóór verwijderen; live controle blijft nodig. Regels voor toekomstig gebruikers-/rollenbeheer vereisen een afzonderlijke productbeslissing.
