# Companymodule: gerichte refactor

Datum: 23 september 2026. Er is niets gepubliceerd of op een database uitgevoerd.

## Afbakening en bron van de beginsituatie

De actieve company-UI staat in `companies.html` en `js/companyFunctions.js`. De meest recente lokale export is `sql/oneiros_beaetherdev.sql`, gemaakt op **22 september 2026 om 08:00**. Die toont migratie 0004, maar nog niet 0005. De gebruiker bevestigt dat 0001â€“0005 nadien op `aetherapp-dev` zijn uitgevoerd; de export is dus geen bewijs van de actuele online toestand. Geen oudere export is als actuele database voorgesteld.

`api/characters/buyCompanyShare.php` en `saveCompanyShare.php` schrijven aandeel-/charactergegevens; `api/characters/characterLifecycleRepository.php` verwijdert personeelslinks bij characterverwijdering. `api/admin/saveSkill.php` kan companypersoneel-specialisatielinks verwijderen wanneer een globale skill wijzigt. De finance- en sharemutaties blijven bij de bestaande characterfinance- en shareservices. Er bestaat geen actieve company-delete- of archiveerroute, en er is geen nieuwe toegevoegd.

## Actieve contracten

Alle genoemde companyroutes zijn alleen voor de actuele `director` en `administrator` uit `tblUser`. Een participant krijgt ook met een aandelen- of personeelskoppeling geen beheer- of detailtoegang. De browserrol en meegestuurde presentatievelden bepalen nooit toegang.

| Route | UI-aanroep en invoer | Succesresponse | Gegevens en mutaties |
|---|---|---|---|
| `getCompanyList.php` | GET, geen body | Array van `{id,companyName}` | `tblCompany` lezen |
| `getCompany.php` | POST JSON `{id}` | Bestaand companyobject met basisvelden, afgeleid type, logo, aandeelhouders, vrije aandelen, eventopties, snapshots en personeelsopties/-rijen | Company, shares/traits, events, personeel en snapshots lezen |
| `newCompany.php` | POST JSON `{companyName}`, CSRF, `Idempotency-Key` | `{id,companyName,description,foundationDate,companyValue,stability,profitability}`; dezelfde JSON-typen als voordien | EÃ©n companyrij met bestaande standaardwaarden |
| `updateCompany.php` | POST JSON `{id}` plus toegestane basisvelden, CSRF en key | `{status:"ok",updatedShareTraitCount}` | Companywaarde en zo nodig gekoppelde sharetraittypen in Ã©Ã©n transactie |
| `saveCompanyPersonnel.php` | POST JSON `{idCompany,personnel:[...]}`, CSRF en key | `{success:true,personnelEntries:[...],snapshots:[...]}` | Personeel, skill- en specialisatielinks; niet-toegepaste snapshots worden herberekend |
| `saveCompanySnapshot.php` | POST JSON `action` (`create`, `update`, `recalculate`, `apply`, `delete`), `idCompany` en actiegebonden event-, snapshot- of slidervelden; CSRF en key | `{success:true,company,availableSharePercentage,snapshotEventOptions,snapshots}` | Bestaande snapshot-, companywaarde-, bank- en dividendflow |
| `uploadCompanyLogo.php` | POST multipart `id` en `logo`, CSRF | `{status:"ok",logoUrl}` | Vast pad `img/bedrijfslogo/<company-id>.png`, geen DB-kolom |
| `deleteCompanyLogo.php` | POST JSON `{id}`, CSRF | `{status:"ok"}` | Alleen het vaste PNG-pad van die company |

De UI roept `newCompany.php` automatisch aan nadat de naam is ingevuld, bewaart algemene gegevens en personeel automatisch, en gebruikt voor snapshots al `apiFetchFinancialJson()`. `newCompany.php` en `saveCompanyPersonnel.php` gebruiken nu eveneens de bestaande `apiFetchIdempotentJson()`; een onduidelijke netwerkuitkomst houdt dezelfde key. Een oude tab zonder key krijgt HTTP 400 met herlaadinstructie voordat deze twee writes beginnen. Bewuste nieuwe handelingen krijgen een nieuwe key. De bestaande snapshot-/updatefoutafhandeling en succesresponses zijn behouden. Ongeldige JSON blijft HTTP 400, niet aangemeld HTTP 401, geweigerde rol/CSRF HTTP 403, veldvalidatie HTTP 422, ontbrekende objecten HTTP 404, keyconflict HTTP 409 en onverwachte fouten een generieke HTTP 500.

## Rechten en veldregels

| Handeling | Participant zonder/met company- of characterlink | Director | Administrator |
|---|---|---|---|
| Companylijst en volledig companydetail | Geen toegang | Alle bedrijven | Alle bedrijven |
| Company aanmaken, basisgegevens, personeel, snapshot, logo | Geen toegang | Alle bedrijven, bestaand object waar van toepassing, CSRF bij writes | Idem |
| Character-aandeel kopen of verkopen | Alleen volgens het bestaande character- en sharebeleid op eigen toegestaan character; verleent geen companybeheer | Bestaande character-/sharebevoegdheid | Idem |

`companySchemas.php` bevat de naamgrens (1â€“255 tekens), het positieve company-ID en de geneste personeelsschema's. De bestaande `companyFinanceSchemas.php` blijft eigenaar van exacte companywaarde- en snapshotvalidatie; de gewone beschrijving wordt bovendien op de bestaande `TEXT`-grens van 65.535 bytes gecontroleerd. Personeel gebruikt alleen bestaande actieve characters; per bedrijf mag een character Ã©Ã©n keer voorkomen, met maximaal Ã©Ã©n skill volgens de bestaande UI-regel. Een specialisatie moet bij die skill horen. `importance` gebruikt de bestaande vijf enumwaarden. Salarisverhoging gebruikt de bestaande `DECIMAL(8,2)`-precisie; skillniveau blijft 1â€“3. Servergeretourneerde `idCompanyPersonnel`, labels, skillnamen en `kind` worden voor UI-compatibiliteit geaccepteerd maar niet voor rechten of writes gebruikt. Onbekende velden, ook op geneste niveaus, worden geweigerd.

Een lege character- of skillkeuze (`0`) blijft de bestaande tijdelijke UI-placeholder en wordt niet opgeslagen. De validator weigert nu ongeldige types, extra velden en meer dan twee salarisdecimalen met 422 in plaats van stil te casten of technische 500-details terug te geven. Dat is de bewuste validatiecorrectie. `companyTypeKey` en aandeelpercentages blijven afgeleid van de actuele databasewaarde, nooit van browservelden.

Companynamen, beschrijving, character-/personeelsnamen, functies en eventtitels zijn **gewone tekst**. Er is geen company-rich-texteditor of rich-textveld in de actieve UI. Deze waarden gaan naar input-`value` of DOM-`textContent`. Ook de snapshotresultaatblokken worden nu via DOM en `textContent` opgebouwd. Er is geen nieuwe HTML-sanitizer of editor toegevoegd.

## Verantwoordelijkheden en consistentie

- `companyAccess.php`: de bestaande director-/administratorbeslissing op basis van de actuele gebruiker en CSRF. `companyUtils.php` laadt dit bestand tijdelijk voor bestaande finance- en presentatiehulpen.
- `companySchemas.php`: niet-financiÃ«le company- en personeelsrequestvelden, inclusief toegestane presentatievelden zonder autoriteitsbetekenis.
- `companyRepository.php`: vaste prepared queries voor lijst, bestaan, aanmaak en algemene update met server-side kolomallowlist.
- `companyPersonnelRepository.php`: vaste referentie-, delete- en insertqueries.
- `companyService.php`: aanmaak, algemene update en complete personeelsvervanging met bestaande snapshotherberekening.
- `companySnapshotService.php`: de bestaande financiÃ«le snapshotregels en queries, verplaatst uit het endpoint zonder spelregel- of responsewijziging. De service houdt Ã©Ã©n transactie; de route blijft alleen orkestratie en rollback/foutresponse.
- `companyLogoService.php`: inhoudscontrole en atomische vervanging van een logo op het vaste pad.

`aetherRunIdempotentMutation()` beheert de buitenste transactie voor company-aanmaak, algemene update en personeel. De idempotentieregistratie, domeinwrites en opgeslagen response committen samen. De snapshotservice gebruikt zijn bestaande claim/complete binnen Ã©Ã©n eigen transactie. Herhaling controleert opnieuw de actuele rol; personeel controleert ook het bestaande companyobject. Na rolverlies geeft replay 403.

Bij personeelsbewaring wordt eerst de companyrij met `FOR UPDATE` vergrendeld, daarna worden de betrokken character-rijen in oplopende ID-volgorde vergrendeld en alle skills/specialisaties gecontroleerd. Vervolgens worden oude personeels-, skill- en specialisatielinks vervangen en niet-toegepaste snapshots herberekend. Fout halverwege of bij het samenstellen van de succesresponse rolt ook nieuwe globale specialisatiedefinities en de idempotentieclaim terug. Voor de schrijfresponse lezen de bestaande presentatiehelpers daarom in een strikte modus: een queryfout mag niet stilzwijgend als een lege personeels- of snapshotlijst worden opgeslagen. De bestaande unieke sleutels beschermen tegen dubbele `(idCompany,idCharacter)`, `(idCompanyPersonnel,idSkill)` en `(idCompanyPersonnelSkill,idSkillSpecialisation)`.

De gerichte correctie in `characterLifecycleService.php`/`characterLifecycleRepository.php` laat characterverwijdering de character-rij binnen de transactie vergrendelen **vÃ³Ã³r** de personeelsopschoning. Zo kan een companysave niet na die opschoning een verwijderde characterkoppeling terugplaatsen. Portraitstaging gebeurt nu na de toegangscontrole en binnen de rollbackbehandeling. De bestaande lifecycle-gedragstest is opnieuw geslaagd.

Bij dividend of terugdraaien vergrendelt de snapshotservice na de idempotentieclaim eerst de company, daarna de betrokken characters en vervolgens de concrete snapshot. De eerdere snapshot-voor-character volgorde is gecorrigeerd; het zichtbare financiÃ«le resultaat is niet veranderd. Bedragen blijven via `api/shared/decimal.php` exacte centen en de bestaande financiÃ«le services berekend. Geen geneste PDO-transacties zijn toegevoegd.

Logo's hebben geen DB-pad: de server bepaalt uitsluitend het pad op basis van het company-ID. De UI ondersteunt PNG, JPEG, WebP en GIF als invoer. De server controleert formaat en werkelijke afbeelding, houdt een grens van 10 MB en 20 miljoen pixels aan, en schrijft eerst naar een tijdelijk bestand in dezelfde map. Met GD wordt iedere toegestane invoer zoals voorheen genormaliseerd naar PNG en maximaal 800 pixels. Zonder GD blijft de al gecontroleerde rasterafbeelding in haar gedetecteerde veilige extensie (`.png`, `.jpg`, `.gif` of `.webp`) behouden; zo blokkeert een ontbrekende GD-extensie niet langer alle logo-uploads op shared hosting. SVG en andere formaten blijven uitgesloten. Een geslaagde rename activeert pas daarna het nieuwe bestand; opruimproblemen met een oud logo worden alleen gelogd en maken de geslaagde nieuwe upload niet ongedaan. Een browser kan geen doelpad aanwijzen. `img/bedrijfslogo/.htaccess` schakelt CGI-uitvoering uit en blokkeert bekende script-extensies als aanvullende bescherming.

Online nacontrole op 23 september: `img/bedrijfslogo/13.png` bestond maar gaf HTTP 403, terwijl `1.png` in dezelfde map HTTP 200 gaf. De nieuwe upload gebruikte `tempnam()`, dat op Unix gewoonlijk modus `0600` maakt; `rename()` nam die modus over. De service zet nu vóór activatie modus `0644` op het gevalideerde afbeeldingsbestand. Een mislukte rechtenwijziging levert een generieke 500 op en laat het vorige logo staan. Een reeds opgeslagen, onleesbaar logo wordt hierdoor niet achteraf gewijzigd: upload het opnieuw na het plaatsen van de correctie, of pas voor precies dat bestand via bestandsbeheer de rechten aan naar `0644`.

## Database en migratie

De export van 22 september toont reeds de vier relevante unieke sleutels op company-personnel, personnel-skill, personnel-specialisation en company/event-snapshot, plus een unieke sharetraitlink. De company-personnel- en snapshottabellen hebben in die export geen eigen foreign keys. Daarom is **geen schemawijziging of migratie 0006** ontworpen op basis van verouderde gegevens. `sql/migrations/inspect_company_integrity_readonly.sql` bevat uitsluitend SELECT/SHOW voor duplicaten, verweesde company-, character-, skill-, snapshot- en sharelinks, overallocatie en indexdefinities. Dit bestand is niet uitgevoerd. Controleer de actuele testdatabase ermee vÃ³Ã³r uitrol; corrigeer geen historische gegevens automatisch. Een latere FK- of globale specialisatienaamconstraint vereist afzonderlijke databeoordeling.

## Tests en beperkingen

`tests/company_module_endpoints_test.php` roept de echte routes aan met een stateful PDO-testdouble. Geslaagd: rollen en vervalste sessierol, anoniem, lijst/detail, tekstresponse, validatie, CSRF, aanmaak, algemene update, personeelsvervanging, geneste referenties, onbekende velden, exacte bedragprecisie, idempotente replay/keyconflict/ingetrokken recht, nul blijvende writes bij weigering, rollback en generieke 500, logoverwijdering zonder ander bestand te raken, snapshotcreatie en dividend waarbij bank, companywaarde, payout en snapshot samen committen of terugrollen. De test controleert ook company â†’ character â†’ snapshot-lockvolgorde van de payoutflow en frontendgebruik van de gedeelde keyhelper.

`tests/company_mariadb_concurrency_test.php` is uitvoerbaar met twee onafhankelijke PDO-processen op een **uitdrukkelijk wegwerpbare** MariaDB-testdatabase. Hij vereist `AETHER_TEST_MYSQL_DSN`, `AETHER_TEST_MYSQL_USER`, `AETHER_TEST_MYSQL_PASSWORD`, `AETHER_ALLOW_COMPANY_MARIADB_TESTS=YES` en `AETHER_TEST_DB_DISPOSABLE=YES`. Hij bewijst echte overlap en company-rijvergrendeling met tijdelijke InnoDB-tabellen. Zonder die instellingen meldt hij `SKIP`; het is geen volledige online-routeconcurrencytest. De bestaande finance-MariaDB-test behandelt de aandeelcapaciteit apart. Lokale GD, fileinfo, PDO MySQL en PDO SQLite zijn niet beschikbaar in de gebruikte PHP-binary: echte multipartupload, echte databaseconstraints, WordPress-sessie en browserweergave zijn daarom **niet lokaal getest**. Deze afhankelijkheden moeten online met testdata worden gecontroleerd.

De definitieve uitslag van de volledige regressie-, syntax- en diffcontrole staat in de laatste sectie hieronder.

## Resterende risico's

- De werkelijke huidige indexen, weesrecords en eventuele overallocatie zijn pas bekend nadat de alleen-lezen inspectie op `aetherapp-dev` is uitgevoerd. De lokale export loopt achter op migratie 0005.
- Echt lockgedrag van de volledige PHP-routes, gelijktijdige wijzigingen vanuit de admin-skillroute en gelijktijdige nieuw aangemaakte globale specialisatienamen zijn niet met MariaDB bewezen. Voor die globale naamcombinatie bestaat in de export geen unieke sleutel; hier is geen nieuwe spelregel of datamigratie voor verzonnen.
- De bestaande company-presentatiehelpers gebruiken voor enkele optionele reads nog hun historische lege-lijstfallback. De personeels-schrijfresponse gebruikt wel strikte reads en rolt bij fouten terug. Een bredere compatibiliteitsbeslissing voor alle detailreads hoort niet bij deze batch.
- De GD-fallback is met een gesimuleerde upload getest. GD-normalisatie, echte `move_uploaded_file`, schrijfrechten en browserweergave moeten nog online met testdata worden gecontroleerd.

## Exacte runtime-uploadlijst en volgorde voor `aetherapp-dev`

Runtime-PHP, als Ã©Ã©n compatibele batch: `api/companies/companyAccess.php`, `companySchemas.php`, `companyRepository.php`, `companyPersonnelRepository.php`, `companyService.php`, `companyLogoService.php`, `companySnapshotService.php`, de bestaande `companyUtils.php` en alle acht actieve company-endpoints hierboven. Ook `api/characters/characterLifecycleService.php` en `characterLifecycleRepository.php` horen bij deze batch. Frontend: uitsluitend `js/companyFunctions.js`. Upload ook `img/bedrijfslogo/.htaccess`, maar behoud de bestaande inhoud van die uploadmap. De al aanwezige shared-, auth-, finance- en sharehelpers moeten op de doelserver aanwezig blijven. Tests, documenten, inspectie-SQL en database-export horen niet in een publieke webmap.

1. Maak een volledige bestands- en databaseback-up. Leg de huidige `VERSION` vast. Controleer dat de doelserver de bestaande finance-/idempotentiecode en migraties 0001â€“0005 heeft; voer ze niet opnieuw uit op basis van deze opdracht.
2. Zet een onderhoudsmodus aan voor company-, character- en financiÃ«le writes, ook voor reeds geopende tabs. Draai desgewenst de **alleen-lezen** companyinspectie in de expliciet geselecteerde `aetherapp-dev`-database en beoordeel elk resultaat. Er is **geen nieuwe SQL-applystap** voor deze batch.
3. Upload eerst de nieuwe PHP-access-, schema-, repository- en servicebestanden naar hun exacte hoofdlettergevoelige paden. Upload daarna de gewijzigde `companyUtils.php`, de twee character-lifecyclebestanden en de acht company-endpoints als Ã©Ã©n korte batch. Gebruik bij voorkeur een tijdelijke uploadmap met omschakeling; laat anders de onderhoudsmodus actief tot alles compleet is.
4. Upload `js/companyFunctions.js` en activeer pas daarna handmatig Ã©Ã©n nieuwe unieke `VERSION`. Wacht minstens Ã©Ã©n updatecontrole-interval (60 seconden) voor reeds geopende tabs. Oude create-/personnelclients zonder key krijgen vÃ³Ã³r een write HTTP 400 met herlaadinstructie; andere mixed-versionwrites blijven door de onderhoudsmodus geblokkeerd.
5. Voer de smoketest hieronder uit en schakel daarna pas de onderhoudsmodus uit. Bij rollback: onderhoudsmodus weer aan, alle hierboven genoemde PHP-/JS-bestanden en vorige `VERSION` herstellen; laat de bestaande idempotentietabel en historische data staan.

## Online smoketest met gewone UI-handelingen

- [ ] Participant zonder en met company-/characterkoppeling ziet geen companybeheer; een vervalste rol geeft geen toegang.
- [ ] Director en administrator openen bedrijvenlijst en detail; bestaande aandeelhouders, personeel, events en snapshots blijven zichtbaar.
- [ ] Maak een bedrijf aan, simuleer dubbele klik of retry, en controleer Ã©Ã©n bedrijf en dezelfde response.
- [ ] Wijzig naam, beschrijving, waarde en sliders; heropen het bedrijf en controleer type en eventuele sharetraitremap.
- [ ] Voeg personeel met Ã©Ã©n skill en bestaande of nieuwe specialisatie toe; verwijder en heropen. Een dubbele save maakt geen dubbele koppeling.
- [ ] Maak een snapshot, herbereken en betaal een testdividend; controleer companywaarde, characterbank en payout na herladen.
- [ ] Upload PNG/JPEG/WebP/GIF, vervang en verwijder het logo; controleer dat een vreemd logo blijft bestaan.
- [ ] HTML in companynaam, beschrijving en personeelsnaam verschijnt als gewone tekst en voert niets uit.
- [ ] Ontbrekende/foutieve CSRF, vreemd company-ID en onverwachte velden wijzigen niets; serverfouten tonen geen SQL-/paddetails.
- [ ] Een oude tab krijgt de updatemelding; een oude create-/personeelsrequest zonder key wordt vÃ³Ã³r write geweigerd.

## Uitgevoerde controles

Op 23 september 2026 lokaal met PHP 8.4.25:

- Alle 28 aanwezige `tests/*_test.php`-bestanden zijn afzonderlijk gestart: **24 geslaagd, 4 gecontroleerd overgeslagen, 0 mislukt**. Ook de nieuwe company-endpointtest, de bestaande character-lifecycletest, finance-, event-, access-, request-, response- en migratieregressies zijn geslaagd.
- `authenticated_user_test.php`: `SKIP` (PDO SQLite ontbreekt).
- `character_finance_mariadb_concurrency_test.php`, `company_mariadb_concurrency_test.php` en `event_gossip_mariadb_concurrency_test.php`: `SKIP` (geen expliciet toegestane wegwerp-MariaDB geconfigureerd). Echte lock- en overlapgaranties zijn dus niet lokaal bewezen.
- PHP-syntaxcontrole: **412 PHP-bestanden gecontroleerd, 0 fouten**.
- `git diff --check`: geslaagd. Ook de elf nieuwe, nog niet door Git gevolgde bestanden zijn afzonderlijk op trailing whitespace gecontroleerd: 0 fouten.
- Geen JavaScript-parser, GD, fileinfo, PDO MySQL of PDO SQLite in de lokale PHP/toolomgeving; JavaScript is via de statische frontendcontracttest gecontroleerd. De GD-fallback voor logo's is wel met een gesimuleerde upload getest, niet via browser of webserver.
- De company-inspectie-SQL en geen enkele migratie zijn tegen een database uitgevoerd; geen WordPress-, Apache-, phpMyAdmin- of echte MariaDB-integratie wordt als geslaagd aangemerkt.
