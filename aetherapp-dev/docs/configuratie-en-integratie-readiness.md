# Configuratie en integratie: gereedheidscontrole

Datum: 23 september 2026. Dit document beschrijft de ontwikkelwerkboom. Er is niets geüpload, geen database gewijzigd en `VERSION` is niet aangepast.

## Uitkomst en grenzen

De actieve `db.php` is nu een kleine, voor Git bedoelde bootstrap: één centrale configuratielader, één PDO-factory en een generieke foutrespons. De oude lokale configuratie is zonder wijziging van haar waarden apart bewaard als genegeerde `db.legacy.local.php`; de primaire verbindingsgegevens staan nu in genegeerde `config.local.php`. De fallbackverbinding met een tweede hardcoded set waarden is verwijderd uit de actieve code. De loader is lokaal met het overgezette configuratiebestand getest, **zonder een databaseverbinding te openen**.

De applicatie is lokaal met testdoubles gecontroleerd. Echte WordPress-, Apache-, upload-, browser-, MySQL/MariaDB- en concurrency-integratie is op deze machine niet bewezen. De gebruikte lokale PHP 8.4.25 heeft geen `pdo_mysql`, `pdo_sqlite`, `mbstring`, `gd` of `fileinfo`. De ingestelde online PHP 8.5 en de extensies moeten op de host bevestigd worden.

Tijdens de analyse verscheen een regel met een oude databasefallback in diagnostische tooluitvoer. **Roteer het betreffende databasewachtwoord vóór een productie-uitrol** en controleer of die fallback ooit online gebruikt werd. Dit document bevat geen waarde daarvan.

## Inventaris van configuratiebronnen

| Soort | Bron en huidige betekenis | Repository/host |
|---|---|---|
| Geheimen | Databasegebruiker en wachtwoord, eventueel `AETHER_DB_*` | Uitsluitend omgevingsvariabelen of echte, niet-getrackte configuratie |
| Omgevingswaarden | Database-DSN/host/naam, `AETHER_APP_ENV`, `AETHER_CONFIG_FILE` | Per dev/productie apart; niet in een gedeeld voorbeeld invullen |
| Omgevingswaarden | WordPress `wp-load.php` wordt via `DOCUMENT_ROOT` en bovenliggende mappen gezocht | Bestaande bootstrap behouden; WordPress staat boven Aether |
| Omgevingswaarden | PHP-sessiecookiepad en HTTPS-status | Afgeleid van serverroute en `HTTPS`/serverpoort, niet van browserparameters |
| Omgevingswaarden | `img/portret`, `img/portret/.quarantine`, `img/bedrijfslogo`, PHP uploadtemp en sessietemp | Schrijfbaarheid door PHP, geen uitvoerbare uploads |
| Gewone instellingen | `VERSION`, `asset.php`, `version.php`, `js/updateManager.js`, assetresolver en cacheheaders | Getrackt; `VERSION` pas bewust bij release wijzigen |
| Gewone instellingen | HTML Purifier uit `vendor/`; `Cache.DefinitionImpl = null` | Geen schrijfbare Purifier-cachemap vereist |
| Runtime | PHP >= 8.1; PDO, PDO MySQL, JSON, session, mbstring, DOM/libxml | Centrale controle bij databasebootstrap |
| Runtime voor uploads | GD en fileinfo voor portretten; fileinfo voor logo's; schrijfbare doel- en tijdelijke mappen | Controleer voor release en bij online uploadtest |
| Testwaarden | `AETHER_TEST_MYSQL_*` plus expliciete toestemming voor wegwerpdatabase | Alleen lokale testomgeving; nooit de gewone dev- of productiedatabase |
| Uitgeschakeld | OIDC `tokenhandler.php` retourneert 410 met `no-store` | Niet opnieuw activeren zonder geverifieerde providerconfiguratie |
| Webserver | Root- en API-`.htaccess`; uploadmap-`.htaccess` | Alleen gecontroleerd samenvoegen in de Aethermap; WordPress-regels bewaren |

De actieve applicatie gebruikt relatieve app- en API-paden; de updatechecker gebruikt de paginaroot, ook bij `/aether/aetherapp-dev/`. Er is geen nieuw domein of absolute publieke URL toegevoegd. Geen module leest database-environmentvariabelen meer buiten `api/shared/config.php`; `db.php` maakt één PDO-verbinding met exceptionmodus, associatieve fetch en uitgeschakelde emulated prepares, met `utf8mb4` in de gevalideerde MySQL-DSN. `dbAll`, `dbOne` en `getPDO` houden hun bestaande signaturen en gedrag.

## Configuratiestructuur en lokale migratie

1. Maak per omgeving een afzonderlijk bestand volgens [config.example.php](../config.example.php), bij voorkeur **buiten de publieke webroot**. Stel `AETHER_CONFIG_FILE` in op het absolute pad. Als one.com geen bruikbare environmentvariabele toestaat, mag een omgevingsspecifieke `config.local.php` in de Aethermap staan, mits de Aether-`.htaccess` actief is en direct HTTP-toegang tot dat bestand 403 geeft.
2. Vul in het echte bestand de eigen `database.host`, `name`, `user`, `password` en `application.environment` in. Alternatief: geef `AETHER_DB_DSN` met `mysql:...;dbname=...;charset=utf8mb4` en de overige `AETHER_DB_*`-waarden via de serveromgeving. De loader valideert types, verplichte waarden en DSN. Dev en productie krijgen verschillende databasegegevens en een afzonderlijk configuratiebestand.
3. Lokaal is de vorige, genegeerde `db.php` bewaard als `db.legacy.local.php` en de primaire waarden zijn zonder uitvoer naar `config.local.php` verplaatst. De loader heeft het nieuwe lokale bestand succesvol gelezen. **De PDO-verbinding is niet geverifieerd**, omdat lokale PDO MySQL ontbreekt. Bewaar de backup totdat een echte verbindings- en logincontrole op een veilige omgeving is geslaagd; verwijder hem daarna van de publieke host als hij daar niet nodig is.
4. Bij ontbrekende of ongeldige configuratie, extensies of verbinding geeft de bootstrap alleen HTTP 500 `{"error":"Server error."}`. De serverlog krijgt een vaste, algemene melding zonder DSN, wachtwoord of pad. PHP-waarschuwingen bij het lezen van het configuratiebestand worden onderdrukt.

`config.local.php` en `db.legacy.local.php` staan in `.gitignore`. `db.php`, de loader en het placeholderbestand horen in Git. Upload het lokale dev-configuratiebestand, de legacybackup en `config.example.php` **niet** naar de host. Een productieconfiguratie wordt daar afzonderlijk en privaat aangemaakt. De Apache-blokkade geldt ook voor directe requests naar `db.php` en het voorbeeldbestand; test die blokkade expliciet online.

## WordPress, sessie en cookies

`sessionUserBootstrap.php` zoekt `wp-load.php` zoals voorheen, vult een sessie alleen na een geldige WordPress-login en vernieuwt bij login het PHP-sessie-ID. Een oude CSRF-token wordt dan gewist. De gedeelde `api/shared/session.php` start sessies met strict mode, cookies only, `HttpOnly`, `SameSite=Lax`, sessieduur tot browsersluiting en een cookiepad voor de Aether-app (bijvoorbeeld `/aether/aetherapp-dev/`). `Secure` wordt aangezet als de server HTTPS of poort 443 meldt. Een onbevestigde `X-Forwarded-Proto`-header wordt bewust niet vertrouwd; verifieer op one.com dat `HTTPS` op de online HTTPS-aanvraag correct staat.

Een Aether-sessie wordt waar WordPress beschikbaar is opnieuw aan de actuele WordPress-login gekoppeld, ook als een oudere sessie geen bronmarkering heeft. Uitloggen of wisselen van WordPress-account wist de oude Aether-identiteit en CSRF-token; pas daarna mag een nieuwe login hydrateren. Een als WordPress gemarkeerde sessie faalt gesloten als WordPress niet geladen kan worden. De rol blijft uit `tblUser` komen. De bestaande `checkLogin.php`-/`getCurrentUser.php`-responsevormen en participantprovisioning na geslaagde authenticatie blijven gelijk. Beëindig bestaande sessies bij uitrol (uitloggen of cookies wissen) en test logout online. `tokenhandler.php` blijft 410.

## Runtime en mappen

`api/shared/runtime.php` controleert de kernextensies en kan aanvullende uploadvereisten en schrijfbaarheid van opgegeven mappen controleren. De kerncontrole loopt vóór een echte PDO-verbinding. Controleer vóór online gebruik onder PHP 8.5: `pdo_mysql`, `mbstring`, `dom`, `libxml`, `json`, `session`, PDO, GD en fileinfo. Portretverwerking gebruikt GD; beide uploadstromen gebruiken beeld-/MIME-inspectie. De bestaande services geven bij falende verwerking hun generieke API-fout; echte featuregedrag bij ontbrekende extensies is niet op de host getest.

Maak en controleer `img/portret/`, `img/portret/.quarantine/` en `img/bedrijfslogo/` als schrijfbaar voor de PHP-gebruiker. Controleer ook `upload_tmp_dir` (of PHP's systeemtempmap) en `session.save_path`. Geef geen wereldschrijfbare permissies als de host met de juiste eigenaar/groep kan werken. De nieuwe `img/portret/.htaccess` en bestaande logo-`.htaccess` blokkeren scriptuitvoering; bevestig dat Apache die regels werkelijk toepast. Bewaar bestaande gebruikersbestanden bij uploads en rollback. Purifier heeft geen schrijfcache, want die staat uit.

## Integratiecontrole

De statische integratietest heeft 583 letterlijke `__DIR__`-includes op aanwezigheid en exacte schrijfwijze gecontroleerd, 70 verwijzingen vanuit JavaScript naar API-routes gecontroleerd en geen top-level includecyclus gevonden. Een bestaande cirkel via `companyShareUtils.php`/`companyUtils.php` is zonder wijziging van berekeningen door een dependency pas bij aanroep te laden; redundante lazy includes zijn verwijderd. De modulegedragstests zijn daarna opnieuw gedraaid. Dynamisch samengestelde includes, Apache-rewrites en echte browserrequestvelden kan een statische test niet volledig bewijzen.

De bestaande module-, routecoverage-, response-, validatie-, rich-text-, idempotentie-, OIDC-, cache- en endpointtests dekken character, finance, events, companies, admin en users. Er zijn geen actieve character-endpoints die de oude `characterRequestValidation.php`-facade laden. De componenttests controleren onder meer vertrouwde rollen/eigenaarschap, CSRF, idempotentie en foutresponses; een volledige WordPress-naar-MariaDB-end-to-end-run is nog nodig op `aetherapp-dev`.

`api/.htaccess` geeft gebruikersgebonden API-antwoorden `private, no-store`; `version.php` gebruikt `no-store`; versiegebonden assets kunnen lang worden gecachet. Root-`.htaccess` behoudt bestaande cacheregels en voegt gerichte bescherming voor configuratie, bronbestanden en per ongeluk geüploade `tests/`, `docs/`, `sql/` en `legacy/` toe. Die laatste padregel is met `<IfModule mod_rewrite.c>` afgeschermd; zonder `mod_rewrite` blijft **niet uploaden** de harde maatregel. De uploadmappen verbieden uitvoerbare bestandstypen. Test online dat deze regels WordPress, API en assets niet raken. Tests, documentatie, SQL-exports, backups en lokale configuratie mogen niet publiek uitvoerbaar worden: upload ze niet.

## Wegwerp-MariaDB voor concurrencytests

De drie bestaande tests zijn [character_finance_mariadb_concurrency_test.php](../tests/character_finance_mariadb_concurrency_test.php), [event_gossip_mariadb_concurrency_test.php](../tests/event_gossip_mariadb_concurrency_test.php) en [company_mariadb_concurrency_test.php](../tests/company_mariadb_concurrency_test.php). Ze draaien uitsluitend na alle volgende expliciete stappen:

1. Maak zelf een **aparte, lege wegwerpdatabase** met een naam die exact begint met `aether_disposable_`; bijvoorbeeld `aether_disposable_local_ci`. Geef een testgebruiker uitsluitend daarop `CREATE`, `DROP`, `SELECT`, `INSERT`, `UPDATE` en `DELETE`. Gebruik geen online dev- of productiedatabase, ook niet tijdelijk.
2. Stel lokaal `AETHER_TEST_MYSQL_DSN`, `AETHER_TEST_MYSQL_DATABASE` (exact dezelfde databasenaam), `AETHER_TEST_MYSQL_USER` en `AETHER_TEST_MYSQL_PASSWORD` in. Stel ook `AETHER_TEST_DB_DISPOSABLE=YES` en `AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS=YES` in; voor de companytest bovendien `AETHER_ALLOW_COMPANY_MARIADB_TESTS=YES`.
3. De guard vergelijkt vóór het verbinden de DSN-naam exact met de toestemming en na het verbinden `SELECT DATABASE()` opnieuw. Workers erven hun configuratie via de procesomgeving; credentials staan niet meer in commandoregelargumenten.
4. De financetest maakt `tmp_aether_finance_payment_*` en `tmp_aether_finance_share_*`; de eventtest `tmp_aether_event_gossip_*`; de companytest `tmp_aether_company_*` en `tmp_aether_personnel_*`. Ze vullen alleen die tijdelijke tabellen, voeren overlappende locks/writes uit en ruimen ze normaal weer op. Bij een abrupte processtop kunnen tijdelijke tabellen achterblijven: controleer en verwijder die uitsluitend in de wegwerpdatabase.

Zonder alle vlaggen melden deze tests `SKIP`. De huidige machine heeft bovendien geen PDO MySQL. Er is geen externe database aangemaakt of benaderd. De guardtest gebruikt alleen synthetische waarden.

## Tests op 23 september 2026

| Controle | Resultaat |
|---|---|
| Configuratie, lokale bestandloader, environment override, types, PDO-opties, runtime, geheimvrije fouten | PASS (`tests/config_runtime_test.php`) |
| Cookies, WordPress-hydratie, logout/accountwissel en CSRF-wissen met WordPress-stubs | PASS (`tests/session_config_test.php`) |
| Exacte wegwerpdatabasenaam en toestemmingsvlaggen | PASS (`tests/mariadb_disposable_guard_test.php`) |
| Statische includes, hoofdletters, top-level cycli, frontend-API-paden en beschermende regels | PASS (`tests/application_integration_readiness_test.php`) |
| Bestaande PHP-module- en contracttests | PASS voor alle lokaal uitvoerbare tests |
| Echte MariaDB-overlap, WordPress, Apache, browser, multipartuploads en PDO-verbinding | SKIP/niet uitgevoerd door ontbrekende lokale runtime of externe omgeving |

Eindrun: **29 PASS, 0 FAIL, 4 SKIP** op 33 PHP-testbestanden. De skips zijn `authenticated_user_test.php` (PDO SQLite ontbreekt) en de drie MariaDB-concurrencytests (geen expliciet toegestane wegwerpdatabase; PDO MySQL ontbreekt lokaal). Een tussentijdse run raakte een kortstondig vergrendeld Windows-`.tmp`-bestand bij het kopiëren van een endpointfixture; de fixturekopie negeert zulke niet-PHP-swapbestanden nu en de volledige herhaling slaagde. PHP-syntaxcontrole: **176 niet-legacy PHP-bestanden, 0 fouten**. `git diff --check`: **PASS**; Git meldde uitsluitend LF/CRLF-conversiewaarschuwingen. De HTML-browsertest `tests/update_manager_browser_test.html` is niet uitgevoerd; er is geen lokale browser-/WordPress-/Apache-integratie geclaimd.

## Exacte runtimebestanden voor deze batch

Upload of synchroniseer naar dezelfde relatieve paden **na een eigen back-up**:

```text
.htaccess                         (alleen gecontroleerd samenvoegen in Aether-root)
db.php
sessionUserBootstrap.php
checkLogin.php
getCurrentUser.php
api/shared/config.php
api/shared/database.php
api/shared/runtime.php
api/shared/session.php
api/auth/accessControl.php
api/characters/companyShareUtils.php
api/companies/companyUtils.php
img/portret/.htaccess
```

Een **eigen productie- of dev-configuratie** buiten de webroot of een door `.htaccess` beschermd `config.local.php` is aanvullend noodzakelijk, maar wordt niet vanuit de ontwikkelkopie geüpload. Voor deze batch is geen SQL-bestand of migratie nodig. `VERSION` is niet gewijzigd. Bestaande `vendor/` en de overige modulebestanden blijven nodig voor een volledige nieuwe installatie, maar zijn in deze batch niet veranderd.

Niet uploaden: `config.local.php` uit deze werkmap, `db.legacy.local.php`, `config.example.php`, `.git/`, `.github/`, `.vscode/`, `tests/`, `docs/`, `sql/`, database-exports, `.env`, logs, tijdelijke bestanden en lokale gebruikersuploads. Overschrijf of wis bestaande `img/portret`- en `img/bedrijfslogo`-inhoud niet. Overschrijf WordPress-core, `wp-config.php` of de WordPress-root-`.htaccess` niet.

## Veilige uitrol naar dev; daarna productie

1. **Vooraf:** identificeer de exacte huidige online versie/bestandsset; maak een volledige bestands- en databasebackup en bewaar de bestaande online `db.php`, Aether-`.htaccess` en uploads apart. Controleer PHP 8.5/extensies, maprechten, bestaande WordPress-locatie en de `aetherapp-dev`-basismap. Controleer of de oude databasefallbackcredential is geroteerd. Gebruik dev en productie met verschillende configuratie en database.
2. **Onderhoudsvenster:** voorkom nieuwe writes tijdens gemengde FTP-versies. Bij voorkeur upload een complete Aether-kopie naar een tijdelijke siblingmap en schakel de Aethermap atomair om met de filemanager; laat WordPress buiten die omschakeling. Als mapomschakeling niet kan, gebruik korte onderhoudsmodus en upload niet rechtstreeks terwijl gebruikers schrijven. Al geopende tabs moeten na vrijgave vernieuwen; verhoog `VERSION` pas bewust wanneer het pakket samenhangend actief is.
3. **Voorbereiding:** plaats eerst de Aether-`.htaccess` met de config-denyregel en beide uploadmapregels. Plaats daarna de **hostspecifieke** configuratie buiten de webroot of via de beschermde fallback. Bevestig via HTTP dat `config.local.php` en `db.php` 403 geven; bevestig ook dat de normale WordPress- en assetroutes nog werken. Als die 403 niet zeker is, gebruik de webroot-fallback niet.
4. **Code:** upload de vier nieuwe `api/shared`-helpers, vervolgens de twee gewijzigde company/characterhelpers en `sessionUserBootstrap.php`/`api/auth/accessControl.php`, daarna `checkLogin.php` en `getCurrentUser.php`; activeer de nieuwe `db.php` **als laatste**. Bij een atomair staged pakket mogen deze bestanden samen omschakelen. Bewaar het oude online `db.php` uitsluitend buiten publieke toegang voor rollback.
5. **Vrijgave:** controleer na omschakeling een ongeldige config alleen in een geïsoleerde stagingkopie, niet op de live site. Controleer de echte PDO-verbinding via login en toegestane reads, daarna uploads met testgegevens. Zet onderhoud pas uit na de online devchecklist. Wijzig `VERSION` handmatig naar een unieke releasewaarde **nadat** alle runtimebestanden coherent zijn; wacht op de updatemelding in een oude tab en herlaad.
6. **Productie:** herhaal dezelfde procedure met productie-eigen configuratie en een nieuwe backup. Geen online dev-config, testdatabasevlaggen of oude uploads overnemen. Voer geen SQL-migratie uit voor deze batch.

### Online devchecklist

- [ ] `config.local.php` (indien gebruikt), `db.php` en `img/portret/test.php` zijn niet publiek uitvoerbaar; normale CSS/JS en WordPress-login werken.
- [ ] PHP 8.5 heeft de genoemde extensies; upload-, quarantine-, temp- en sessiemappen zijn schrijfbaar zonder brede permissies.
- [ ] WordPress-login maakt een Aether-sessie met cookiepad `/aether/aetherapp-dev/`, `Secure`, `HttpOnly` en `SameSite=Lax`; gebruiker en rol komen uit `tblUser`.
- [ ] WordPress-logout en accountwissel beëindigen de oude Aether-toegang; oude CSRF-token werkt niet meer; anonieme API krijgt 401.
- [ ] Participant leest en wijzigt eigen character, niet andermans; director en administrator behouden hun rechten; geweigerde writes doen niets.
- [ ] Character, finance (inclusief idempotente retry), events, companylogo, portret, admin en users doen een kleine toegestane en een geweigerde UI-actie met testgegevens.
- [ ] Rich text blijft gesaniteerd; uploads blijven in de bedoelde mappen en zijn als bestand leesbaar maar niet uitvoerbaar.
- [ ] `tokenhandler.php` geeft 410; `version.php` geeft `no-store`; API-antwoorden met gebruikersdata zijn `private, no-store`; gewijzigde assets hebben een nieuwe `?v=`-waarde.
- [ ] Een al geopende tab krijgt na een handmatig gewijzigde `VERSION` één updatemelding en vernieuwt pas na gebruikersactie.

### Rollback

Zet bij mislukte controle de onderhoudsmodus terug aan. Herstel het volledige vorige Aether-codepakket inclusief bijbehorende `.htaccess`, `db.php`, WordPress-integratiebestanden en de vorige `VERSION` als één samenhangende set; herstel de vorige hostspecifieke configuratie apart. Behoud tijdens rollback de actuele gebruikersuploads. Een databasebackup is beschikbaar voor een afzonderlijk bewezen dataprobleem, maar deze batch heeft geen schema- of datamigratie en vraagt normaal geen database-restore. Controleer daarna login, anonieme toegang en een toegestane read opnieuw voordat onderhoud uitgaat.

## Resterende productieblokkades

De effectieve one.com-extensies en Apache-denyregels, productieconfiguratie en rechten, echte PDO/WordPress-samenwerking, oude sessiecookies en online uploadverwerking zijn nog niet geverifieerd. De historische credential uit de diagnostische uitvoer moet worden geroteerd. De productie-basismap en huidige releaseversie moeten vóór een exacte delta-uitrol bevestigd worden. Echte MariaDB-concurrency blijft ongetest totdat een expliciet wegwerpbare lokale database bestaat. Voer de bovenstaande online devchecks uit en gebruik pas daarna dezelfde werkwijze voor productie.
