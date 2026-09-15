# Aether: architectuuradvies, meertaligheid en berichten

Datum: 15 september 2026. Scope: lokale map `aetherapp-dev`.

## Besluit

Behoud PHP, MariaDB/MySQL en JavaScript. Een migratie naar een andere programmeertaal is voor deze uitbreidingen niet nodig. Bouw verder als één applicatie met duidelijke functionele modules en gedeelde voorzieningen. Verbeter eerst toegangscontrole en datavalidatie; voeg daarna vertalingen en een berichtenmodule toe.

Node.js op de productieserver is hiervoor niet vereist. JavaScript draait nu in de browser. Eventuele toekomstige hulpmiddelen voor het bouwen van frontendbestanden kunnen lokaal of in CI draaien; de server ontvangt dan gewone statische bestanden.

Dit is een statische beoordeling van broncode en de beschikbare SQL-export. De live server, database, WordPress-configuratie en webserverregels zijn niet getest. PHP en Composer zijn in deze lokale sessie niet via PATH beschikbaar; er zijn geen runtime- of belastingtests uitgevoerd. Alleen dit rapport is toegevoegd; applicatiecode is niet aangepast.

## 1. Huidige technologie

| Onderdeel | Aangetroffen |
| --- | --- |
| Backend | PHP, vooral procedurele functies, `strict_types`, PDO en losse JSON-endpoints |
| Database | MySQL-driver; SQL-export vermeldt MariaDB 10.11.18 |
| Frontend | HTML, CSS, gewone JavaScript, Fetch API, Bootstrap 5.3.3 en Font Awesome |
| Authenticatie | PHP-sessies, WordPress-bootstrap en een OAuth/OIDC-tokenhandler |
| Structuur | `api/characters`, `api/events`, `api/companies`, `api/admin`, `api/users`; JavaScript per scherm/onderdeel |
| Tooling | Geen Composer-/npm-manifest, geautomatiseerde tests of CI-workflow aangetroffen in deze ontwikkelmap |
| Talen en berichten | Geen centrale UI-vertaallaag of algemene chat-/notificatiemodule gevonden |

De map bevat buiten `legacy` 79 PHP-bestanden, 20 JavaScript-bestanden, 6 HTML-bestanden en 1 CSS-bestand. De export van 22 augustus 2026 noemt PHP 8.3.6; dit is metadata van de exportomgeving, geen meting van de actuele app-runtime.

PHP 8.3 ontvangt volgens de officiële planning beveiligingsupdates tot eind 2027. Controleer de werkelijk geïnstalleerde patchversie. Voor verdere modernisering is een door de host ondersteunde, bijgewerkte PHP 8.4/8.5-versie een kandidaat na compatibiliteitstests met de app, WordPress en plugins. Zie [PHP supportbeleid](https://www.php.net/supported-versions.php).

## 2. Bevindingen met prioriteit

### P0: toegangscontrole en bescherming van gegevens

1. **Rol uit de browser wordt vertrouwd.** `api/characters/getCharacterList.php:17` neemt de rol uit de requestbody over wanneer de sessie geen rol bevat. De WordPress-bootstrap en tokenhandler slaan juist geen rol op. De API kan hierdoor een door de gebruiker aangeleverde administrator/director-rol gebruiken. Lees de rol uitsluitend server-side uit de gebruikersdatabase en weiger toegang wanneer identiteit of rechten niet vastgesteld kunnen worden.
2. **Algemene controle op identiteit/eigenaarschap ontbreekt bij meerdere endpoints.** `api/events/newEvent.php` en `updateEvent.php` schrijven zonder zichtbare login- of rolcontrole. `updateParticipation.php` accepteert een opgegeven gebruikers-ID zonder te controleren of de aanvrager die deelname mag wijzigen. `getCharacter.php` mist een algemene leesautorisatie; enkele economievelden worden gefilterd, maar het oorspronkelijke personagerecord en andere gegevens worden wel teruggestuurd. `updateCharacter.php` controleert enkele specifieke velden, maar heeft geen algemene schrijfautorisatie voor alle overige velden. Dit zijn bevindingen in de applicatiecode; eventuele externe webserverbeveiliging is niet onderzocht.
3. **Vrije requestvelden worden databasekolommen.** Onder andere `newCharacter.php`, `updateCharacter.php`, `newEvent.php` en `updateEvent.php` bouwen SQL met door de client aangeleverde veldnamen. Voeg per operatie een expliciete lijst toegestane velden, typen en rechten toe. Dwing eigenaar, maker en toegestane statusovergangen af op de server. Prepared statements beschermen waarden; ze maken dynamische kolomnamen niet vanzelf veilig. Zie [PDO-documentatie](https://www.php.net/manual/en/pdo.prepare.php).
4. **Onveilige HTML-weergave.** `js/eventFunctions.js:95` en volgende regels zetten titel, beschrijving en locatie direct in `innerHTML`. `js/mainFunctions.js:151` interpoleert dropdownteksten in HTML. Gebruik `textContent` voor gewone tekst; gebruik voor bewust toegestane rich text één gecontroleerde sanitizer. In combinatie met de onvoldoende beschermde event-API is dit extra relevant.
5. **Secrets en omgevingen.** In `db.php` staan databasecredentials en een fallback naar een andere database; `tokenhandler.php` bevat een client secret en een vaste redirect naar `aetherapp`. Verplaats configuratie naar omgevingsinstellingen of een bestand buiten de publieke map. Verwijder de fallback naar de andere omgeving. Beoordeel waar deze secrets gedeeld/gepubliceerd zijn en roteer blootgestelde credentials. `.gitignore` beschermt niet tegen downloaden via HTTP en wist geen eerdere Git-geschiedenis. Publiceer SQL-dumps en legacy-code niet mee.

### P1: betrouwbaarheid en onderhoudbaarheid

- **Authenticatie centraliseren.** De app kent zowel WordPress-sessiehydratie als een aparte tokenflow. De tokenhandler decodeert het ID-token zonder zichtbare validatie van issuer, audience en geldigheid; ook ontbreekt zichtbare `state`-controle en vernieuwing van het sessie-ID. Kies één duidelijk beheerde authenticatieroute of isoleer beide achter dezelfde adapter. Laat een geschikte OIDC-library providergebonden validatie uitvoeren. Base64-decoding is geen tokenvalidatie; het precieze validatiemodel hangt af van de gebruikte flow. Zie [OpenID Connect](https://openid.net/specs/openid-connect-core-1_0.html#IDTokenValidation).
- **Gedeelde HTTP-laag.** Voeg één bootstrap toe voor sessie, identiteit, autorisatie, HTTP-methoden, JSON-validatie, CSRF-bescherming en fouten. In de onderzochte appbestanden zijn geen expliciete CSRF-controles gevonden. Controleer daarnaast de daadwerkelijke cookie- en WordPress-instellingen. Uniformeer responses naar bijvoorbeeld `{data: ...}` en `{error: {code, params}}`; nu verschillen arrays, getallen, objecten en foutformaten.
- **Gelijktijdige wijzigingen.** `saveBankTransfer.php:107` controleert het saldo vóór de transactie en verlaagt daarna onvoorwaardelijk het saldo. Twee gelijktijdige requests kunnen dezelfde saldo-controle passeren. Controleer en wijzig onder rijvergrendeling in vaste volgorde, of gebruik een atomische voorwaardelijke update. Pas dezelfde beoordeling toe op punten, actiegebruik en aandelen. Voeg bescherming tegen dubbel versturen toe.
- **Databasewijzigingen reproduceerbaar maken.** Er is een SQL-dump, geen versiegebonden migratiereeks. De dump bevat wel foreign keys en unieke indexen, maar bijvoorbeeld `tblLinkEventUser` en `tblLinkCharacterSkill` hebben in de getoonde indexdefinities alleen een primaire sleutel. Verifieer de live structuur en bestaande duplicaten vóór het toevoegen van unieke combinaties `(idEvent, idUser)` en `(idCharacter, idSkill)`. Beoordeel ook een index op `tblCharacter.idUser` en gebruik querymetingen/EXPLAIN voor aanvullende indexen.
- **Grote bestanden splitsen.** `passportCharacter.js` telt 3.354 regels, `economyCharacter.js` 2.483 en `companyFunctions.js` 2.270. `economyUtils.php` telt 1.631 regels en combineert gebruikersidentiteit, autorisatie, spelregels en databasewerk. Splits op verantwoordelijkheid, met behoud van functionele samenhang.
- **Afhankelijkheden expliciet maken.** Frontendbestanden delen globals en hangen af van scriptvolgorde; gebruikersinformatie bestaat zowel in `currentUser` als `window.AETHER_CURRENT_USER`. Backend-helpers trekken via `require_once` andere domeinen mee. Gebruik frontend ES-modules en één gebruikerscontext; isoleer backend-services en repositories. ES-modules in de browser vereisen geen Node-server.
- **Gericht laden en meten.** `getCharacter.php` bouwt ook economie, talen, vaardigheden en opties op. Laad zware tabinhoud wanneer nodig en cache referentiedata. De omvang van dit endpoint is zichtbaar; daadwerkelijke traagheid is nog niet gemeten.
- **Veilige releases.** Voeg een gesanitiseerd databaseschema, testdata, deploymentinstructies en gerichte regressietests toe. De ontwikkelmap verschijnt momenteel als untracked map in de bovenliggende Git-repository; zorg voor een bewuste versiebeheer- en releaseprocedure met aparte dev/prod-configuratie.

## 3. Meertaligheid: Nederlands, Engels, later Frans

### Interface

Gebruik stabiele vertaalsleutels met aparte catalogi `nl`, `en`, `fr`, bijvoorbeeld `character.save`, `navigation.events` en `notifications.character_submitted`. i18next kan rechtstreeks in de browser geladen worden; een Node-runtime is niet vereist. Pin de gebruikte versie en beheer deze bewust. Zie [i18next-documentatie](https://www.i18next.com/overview/getting-started).

- Begin met NL en EN; gebruik NL als voorlopige brontaal/fallback en leg ontbrekende sleutels tijdens ontwikkeling vast.
- Bewaar de taalvoorkeur bij de gebruiker; gebruik een lokale voorkeur/browsertaal vóór het aanmelden als fallback.
- Vertaal statische HTML, dynamisch gemaakte schermen, meldingen, validatiefouten, placeholders, toegankelijkheidslabels en het paspoort/printscherm.
- Houd `<html lang>` en datums/getallen via `Intl` gelijk aan de gekozen taal. Nu komen onder meer vaste `nl-BE`- en `fr-BE`-formaten voor.
- Laat de API stabiele foutcodes en parameters teruggeven. Een foutcode kan in iedere taal dezelfde betekenis houden.
- Verander opgeslagen codes zoals `draft`, `participant` en `director` niet wanneer het zichtbare label verandert.

### Spelinhoud

`languageCharacter.js` en `tblLanguage` gaan over talen die personages kennen; dat is een ander gegeven dan de taal van de interface.

Vaardigheden, eigenschappen en spelbeschrijvingen komen uit de database. Voor meertalige beheerde inhoud zijn vertaalrecords per entiteit en locale nodig, bijvoorbeeld `tblSkillTranslation(idSkill, locale, name, description, beginner, professional, master)`, met een unieke sleutel op `(idSkill, locale)` en fallback naar de bestaande brontekst.

Maak spelregels onafhankelijk van zichtbare namen vóór het vertalen. `economyUtils.php:134` herkent bijvoorbeeld de tekst `familiehoofd`; verder bestaan er fallbackvergelijkingen op traitnamen. Gebruik vaste codes/flags. `gossipKnowledgeUtils.php:9` kent ook een vaste skill-ID; een stabiele functionele code maakt dit minder afhankelijk van een specifieke databasekopie.

Vrije berichten en dagboekteksten blijven in de taal waarin ze zijn geschreven. Automatische vertaling is een afzonderlijke eventuele functie. Systeemmeldingen worden opgeslagen met een type en parameters en verschijnen in de taal van de ontvanger.

## 4. Berichten en meldingen zonder Node.js

Maak twee onderdelen: gesprekken tussen gebruikers en systeemmeldingen naar expliciet bepaalde ontvangers. Bewaar beide duurzaam in de database; de gekozen transporttechniek bepaalt alleen hoe snel een open browser een wijziging ziet.

| Transport | Gebruik | Randvoorwaarden |
| --- | --- | --- |
| Korte polling | Voorlopige voorkeur voor gewone PHP-hosting als enkele seconden vertraging acceptabel zijn | Browser vraagt bijvoorbeeld elke 3–5 seconden wijzigingen op; geen permanent proces nodig |
| Server-Sent Events (SSE) | Directe server-naar-browsermeldingen, gecombineerd met normale POST-verzoeken voor verzenden | Streaming, timeouts, proxybuffering en beschikbare PHP-workers moeten dit toelaten |
| Beheerde WebSocket-dienst | Directe chat/push wanneer lokale permanente processen niet mogelijk zijn | Externe dienst, kosten, private kanaalauthenticatie en afhandeling van storingen |
| Eigen WebSocket-proces | Mogelijk bij geschikte hosting; ook met andere talen dan Node | Permanent proces, procesbeheer en reverse proxy vereist |

SSE ondersteunt eenrichtingsverkeer; berichten versturen kan gewoon via PHP-POST. Bij traditionele PHP-FPM-hosting bezet een open stream een worker. Sluit na authenticatie de PHP-sessieschrijflock en ontwerp herverbinding, gemiste meldingen en hercontrole van rechten. Zie [SSE met PHP](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events) en [PHP-sessielocks](https://www.php.net/manual/en/function.session-write-close.php).

Een externe dienst zoals Pusher kan events vanuit PHP ontvangen en aan browsers leveren. Dat vereist geen Node op deze server; zie de [officiële PHP-library](https://github.com/pusher/pusher-http-php). De dienst vervangt de eigen berichtenopslag en autorisatie niet.

### Voorgesteld gegevensmodel en gedrag

- `Conversation`: gesprek en eventueel context (event/personage).
- `ConversationMember`: gebruikers die het gesprek mogen lezen, met laatst gelezen bericht.
- `Message`: gesprek, afzender uit de sessie, inhoud, aanmaaktijd en client-request-ID tegen dubbele verzending.
- `Notification` plus `NotificationRecipient`: type, minimale context, ontvangers en gelezenstatus per ontvanger.
- Optioneel later `Outbox`: nog te publiceren externe events met pogingenteller en herprobeertijd.

Schrijf bij een deelnemersactie de wijziging en de bijhorende notificatie/ontvangers in dezelfde database-transactie. Publiceer externe events pas na commit. Wanneer betrouwbare externe aflevering nodig is, sla ook een outbox-record binnen de transactie op en verwerk dit met een worker of cronjob. Cron is niet nodig voor de eerste versie met databasepolling; cronfrequentie kan wel de vertraging van externe aflevering bepalen.

Gebruik bestaande actiepunten zoals `useCharacterSkillAction.php` en `revealCharacterActionKnowledge.php` als aansluiting, via een gedeelde service. Bestaande actiehistoriek blijft historiek; gelezenstatus hoort bij de ontvanger.

Elke read, write en subscription controleert server-side de gebruiker en gespreksdeelname/rechten. Een director krijgt relevante actiemeldingen volgens een expliciete regel; die rol geeft niet automatisch toegang tot alle privégesprekken. Beperk inhoudslengte en verzendsnelheid en geef op private pushkanalen alleen noodzakelijke gegevens door.

Voor polling: gebruik begrensde pagina's, een betrouwbaar hervatmechanisme en deduplicatie, plus lagere frequentie bij verborgen tabs en backoff na fouten. Test ook twee transacties die in een andere volgorde committen dan hun IDs zijn toegewezen; een naïeve `id > laatsteId`-cursor kan anders records missen. Indexeer gesprek/bericht en ontvanger/gelezenstatus. Rekenvoorbeeld: 100 actieve tabs met een interval van 5 seconden veroorzaken circa 20 polls per seconde, boven op ander verkeer; dit is geen gemeten servercapaciteit.

Deze eerste transportopties bedienen een geopende app. Meldingen wanneer de browser/app gesloten is vragen een aanvullende oplossing zoals Web Push of e-mail.

## 5. Gewenste architectuur en volgorde

Behoud één deploybare PHP-app met modules voor Identity, Characters, Events, Companies, Messaging en Notifications. Laat een HTTP-controller een service aanroepen voor spelregels en transacties; laat repositories databasewerk afhandelen. Gedeelde authenticatie, vertaling en foutafhandeling krijgen één plaats. Bewaar configuratie en interne code buiten de publieke webroot waar de hosting dat ondersteunt.

Laravel is een mogelijke toekomstige standaardisering binnen PHP als het aantal modules en ontwikkelaars sterk groeit. Het is geen voorwaarde voor deze twee functies. De huidige Laravel 13-documentatie noemt PHP 8.3+ en meerdere extensies; documentroot en deploymentmogelijkheden moeten ook passen. Zie [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment). Eerst modules afbakenen beperkt het risico, ongeacht of later een framework volgt.

Aanbevolen uitvoering:

1. Toegangscontrole, toegestane velden, veilige tekstweergave en configuratiescheiding herstellen. Test anonieme gebruiker, deelnemer A/B, director en administrator, inclusief vervalste rol/IDs.
2. Gedeelde API-bootstrap, consistente foutcodes, migraties en een testbare PHP-omgeving invoeren. Test ook gelijktijdige saldo-/puntenwijzigingen.
3. NL/EN-vertaallaag invoeren en scherm voor scherm omzetten. Maak naamafhankelijke spelregels stabiel. Bereid inhoudsvertalingen en FR voor.
4. Berichtenopslag, gespreksrechten, inbox, ongelezen teller en systeemmeldingen bouwen. Begin bij onbekende gewone webhosting met polling als seconden vertraging aanvaardbaar zijn.
5. Met werkelijke hostlimieten en gelijktijdige gebruikers meten of SSE of een beheerde pushdienst nodig is. Test herverbinding, gemiste/dubbele berichten, intrekken van rechten en rollback zonder onterechte melding.

Nog te bevestigen voor de implementatie: hostingpakket en actieve PHP-versie/extensies, cron/CLI/permanente processen, HTTP-streaming en workerlimieten, verwachte gelijktijdige gebruikers, gewenste berichtvertraging, welke acties welke directors/admins moeten bereiken, en de vertaalomvang van beheerde spelinhoud.
