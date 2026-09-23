# Refactor actieve eventmodule

Datum: 22 september 2026

## Resultaat en afbakening

De werkelijk actieve eventketen is naar de gedeelde PHP-architectuur gebracht. De vier kernroutes gebruiken nu expliciete JSON-validatie, een centrale event-accesslaag, vaste repositoryqueries en gedeelde responses. Eventgebonden characteractions en beheerwrites gebruiken de bestaande idempotentievoorziening. De gossipattempt- en unlockstate worden onder row locks en in één transactie gewijzigd.

Er bestaat in het actuele schema en de frontend geen eventdetailroute, eventdelete, eventstatus, conceptstatus, geslotenstatus of eventvisibility. Deze batch verzint die functies en regels niet. `tblEvent` bevat uitsluitend `id`, `title`, `type`, `description`, `venue`, `dateStart`, `dateEnd` en `ep`. Alle bestaande events blijven daarom zichtbaar via het bestaande lijstcontract aan iedere aangemelde gebruiker. Een toekomstig verborgen/statusmodel vereist een afzonderlijke functionele beslissing en migratie.

Er is geen eventdelete toegevoegd. De huidige foreign keys zouden diary-, visibility-, gossip- en actionhistorie kunnen verwijderen, terwijl snapshots en financiële historie apart moeten worden beoordeeld. Zonder expliciete bedrijfsregel zou delete verliesgevaarlijk zijn.

## Actieve routes en contracten

Alle hieronder genoemde requests gebruiken de bestaande WordPress/sessie-identiteit. Browserrollen worden niet vertrouwd.

| Route | Frontend | Methode en input | Succesresponse | Toegang en writes |
|---|---|---|---|---|
| `api/events/getEventList.php` | `js/eventFunctions.js` | POST JSON `{idUser}`; `0` betekent huidige gebruiker | ongewijzigde vlakke array met `id,type,title,description,dateStart,dateEnd,venue,ep,participation` | iedere aangemelde gebruiker voor eigen deelname; director/admin ook voor een andere bestaande user; read |
| `api/events/newEvent.php` | `js/eventFunctions.js` | POST JSON met `type,title,description,dateStart,dateEnd,venue,ep` | numeriek nieuw event-ID | director/admin; één insert |
| `api/events/updateEvent.php` | `js/eventFunctions.js` | POST JSON met `id` en een of meer eventvelden; actieve UI stuurt alle velden | numerieke `rowCount` | director/admin; één update met vaste veld/kolommapping |
| `api/events/updateParticipation.php` | `js/eventFunctions.js` | POST JSON `{idEvent,idUser,participation}` | numeriek link-ID bij toevoegen, `rowCount` bij verwijderen | director/admin; één upsert of delete |
| `api/characters/getCharacterDiary.php` | character diary-tab | POST JSON `{idCharacter}` | bestaand diary-readmodel | character-viewrecht; leest diary en events |
| `api/characters/getCharacterActionEvents.php` | `js/actionsCharacter.js` | POST JSON `{idCharacter}` | bestaand actioncatalogusobject | participant eigen toegestaan character; director/admin volgens characterbeleid |
| `api/characters/getCharacterActionKnowledgeTargets.php` | `js/actionsCharacter.js` | POST JSON `{idCharacter,idEvent}` | bestaand knowledge-targetobject | hetzelfde characterbeleid; zichtbaarheid en skillniveau blijven toegepast |
| `api/characters/revealCharacterActionKnowledge.php` | `js/actionsCharacter.js` | POST JSON `{idCharacter,idEvent,idSourceCharacter}` plus CSRF en `Idempotency-Key` | bestaande revealresponse | eigen toegestaan character of privileged; attempt plus unlock plus idempotentieresponse in één transactie |
| `api/characters/useCharacterSkillAction.php` | `js/actionsCharacter.js` | POST JSON met character-, event-, skill- en actionvelden plus CSRF en key | bestaande skillactionresponse | bestaand character/skill/actionbeleid; state, use en response in één transactie |
| `api/admin/getKnowledgeEvents.php` | `js/adminFunctions.js` | bestaand readrequest | `{events:[...]}` | director/admin |
| `api/admin/getKnowledgeEventGossip.php` | `js/adminFunctions.js` | POST JSON `{idEvent}` | `{groups:[...]}` | director/admin; event moet bestaan |
| `api/admin/saveKnowledgeVisibility.php` | `js/adminFunctions.js` | POST JSON `{idEvent,idCharacter,isVisible}` plus CSRF en key | `{ok,idEvent,idCharacter,isVisible}` | director/admin; diarytarget moet bestaan; upsert en response samen |
| `api/admin/deleteKnowledgeUnlock.php` | `js/adminFunctions.js` | POST JSON `{idEvent,idSourceCharacter,idViewerCharacter}` plus CSRF en key | `{ok,idEvent,idSourceCharacter,idViewerCharacter,attemptCount}` | director/admin; unlockdelete, countercorrectie en response samen |
| `api/admin/getActionEvents.php` | `js/adminActions.js` | bestaand readrequest | `{events:[...]}` | director/admin |
| `api/admin/getActionEventUses.php` | `js/adminActions.js` | POST JSON `{idEvent}` | `{items:[...]}` | director/admin; event moet bestaan |
| `api/admin/updateActionUse.php` | `js/adminActions.js` | POST JSON met de bestaande action-use editvelden plus CSRF en key | `{ok:true,item:...}` | director/admin; één action-use-update en response samen |
| `api/admin/deleteActionUse.php` | `js/adminActions.js` | POST JSON `{idActionUse}` plus CSRF en key | `{ok:true,idActionUse}` | director/admin; delete en response samen; replay blijft mogelijk nadat de rij weg is |

Economiesnapshots en companysnapshots gebruiken events als selectiedoel, maar blijven eigendom van de finance/companyservices uit `docs/character-economy-finance-refactor.md`. De eventmodule schrijft geen saldo, aandeel, payout of companywaarde rechtstreeks.

## Rechtenmatrix

| Handeling | Participant | Director | Administrator |
|---|---|---|---|
| eventlijst en eigen deelname lezen | ja, aangemeld | ja | ja |
| deelname van een andere user lezen | nee | ja | ja |
| event aanmaken of wijzigen | nee | ja | ja |
| user aan event koppelen/ontkoppelen | nee | ja | ja |
| actioncatalogus/targets | alleen volgens bestaand characteraccess, normaal eigen player-character | ja volgens characteraccess | ja volgens characteraccess |
| skillaction of gossipreveal | alleen eigen toegestaan player-character en bestaande skill/zichtbaarheidsregels | ja volgens bestaand privileged beleid | ja volgens bestaand privileged beleid |
| knowledgevisibility en unlockbeheer | nee | ja | ja |
| action-use beheer | nee | ja | ja |

Een aangeleverde `idUser`, character-ID, event-ID, rol of andere browserwaarde verleent op zichzelf geen recht. De gebruiker en rol komen steeds opnieuw uit `tblUser`. Ook een idempotente replay voert de actuele rol- en objectcontrole uit.

## Validatie en tekst

`api/events/eventSchemas.php` bevat de eventvelden en schema's per actieve handeling. Onbekende velden geven HTTP 422. Kernregels:

- type: `weekend` of `mini`;
- titel: getrimde gewone tekst, 1–50 tekens;
- beschrijving: getrimde gewone tekst, maximaal 255 tekens;
- venue: getrimde gewone tekst, maximaal 50 tekens;
- begin- en einddatum: geldige datums; einddatum mag niet vóór begindatum liggen;
- EP: integer van 0 tot 2147483647;
- IDs: positieve integers, behalve het bestaande `idUser=0`-aliascontract;
- visibility: boolean;
- action-use editvelden: expliciet schema, inclusief integergrenzen en tekstlengtes.

De eventkern heeft geen rich-texteditor. Titel, beschrijving, venue, action-resultaattekst en gossip zijn gewone tekst. `js/eventFunctions.js` schrijft database-inhoud via `textContent`; de resterende `innerHTML = ""`-regels legen alleen containers. Diary `goals` en `achievements` behouden hun bestaande characterspecifieke rich-textsanitisatie; die is niet naar de eventvalidator verplaatst.

## Architectuur

- `eventAccess.php`: eventrollen en toegang tot de deelname van een specifieke user.
- `eventSchemas.php`: expliciete event-, knowledge- en action-use-requestschema's.
- `eventRepository.php`: vaste prepared event-, participation- en diarytargetqueries.
- `eventService.php`: datumvolgorde, toegangsbeslissingen en kernuse-cases.
- `eventKnowledgeService.php`: visibility en veilige unlockverwijdering.
- kernendpoints: alleen dependencies, trusted user, CSRF waar nodig, request/schema, service en response.

Characteractiebeleid blijft in `characterAccess.php` en `characterActionService.php`. Finance blijft in de finance/companyservices. De generieke idempotentielaag staat in `api/shared/idempotency.php`; er is geen tweede implementatie gemaakt.

## Transacties, locks en idempotentie

`aetherRunIdempotentMutation()` beheert de buitenste transactie. Services starten binnen deze routes geen geneste PDO-transactie.

Voor een gossipreveal is de volgorde: idempotentieclaim, actuele character/event/skill/visibilitycontrole, attemptrij `FOR UPDATE`, concrete unlockrij `FOR UPDATE`, atomaire increment, `GREATEST`-merge van unlockvlaggen, action/history indien van toepassing, opgeslagen response, commit. Een rollback herstelt alles. Dezelfde key en payload retourneert de opgeslagen response zonder tweede poging; dezelfde key met een andere payload geeft HTTP 409.

Voor psi/actionstate wordt de concrete character/action/state-rij gemaakt indien nodig en daarna `FOR UPDATE` gelezen. Daardoor kan een gelijktijdige statewijziging niet op een verouderde waarde verder rekenen.

De frontendfunctie `apiFetchIdempotentJson()` bewaart de key per concrete gebruikershandeling in `sessionStorage`. Netwerkfouten en HTTP 5xx behouden de key. Een afgeronde request of definitieve 4xx verwijdert hem. Een bewuste nieuwe klik krijgt een nieuwe key. De oude naam `apiFetchFinancialJson()` blijft als compatibiliteitswrapper voor bestaande financieroutes.

## Database-integriteit en migratie 0005

De onderzochte export bevestigde al unieke sleutels voor diary per character/event, diaryvisibility, gossipattempt, gossipunlock en actionstate. `tblLinkEventUser` miste een unieke event/userregel.

Migratie 0005 levert:

- `sql/migrations/preflight/0005_unique_event_user.sql`;
- `sql/migrations/apply/0005_unique_event_user.sql`;
- `sql/migrations/verify/0005_unique_event_user.sql`;
- `sql/migrations/rollback/0005_unique_event_user.sql`;
- `sql/migrations/run_0005_event_integrity_phpmyadmin.sql`.

De bundel vereist 0001–0004, blokkeert op dubbele of nullsleutels en rapporteert verweesde event/userlinks als waarschuwing zonder ze te wijzigen. Hij voegt de indexen hervatbaar toe, bewijst de unieke regel met tijdelijke probe-inserts en registreert 0005 pas daarna. Hij ruimt geen applicatiedata op en claimt geen transactionele rollback van `ALTER TABLE`. Vereist zijn `SELECT`, `INSERT` en `ALTER`; `PREPARE` moet door de verbinding worden toegelaten. De test gebruikt een bestaande event- en user-ID voor de teruggedraaide probe; een volledig lege database blokkeert met een duidelijke probe-fout.

## Tests

Werkelijk lokaal uitgevoerd op 22 september 2026:

- `event_module_endpoints_test.php`: geslaagd;
- `event_knowledge_admin_endpoints_test.php`: geslaagd;
- `event_admin_action_endpoints_test.php`: geslaagd;
- `character_skills_actions_endpoints_test.php`: geslaagd;
- `database_migrations_concurrency_test.php`: geslaagd;
- `event_gossip_mariadb_concurrency_test.php`: overgeslagen; geen expliciet toegestane wegwerp-MariaDB geconfigureerd.

De volledige set van 26 testsuites is uitgevoerd: 23 suites zijn geslaagd en 3 zijn gecontroleerd overgeslagen. `authenticated_user_test.php` kon niet draaien omdat PDO SQLite ontbreekt. De twee echte MariaDB-concurrencysuites zijn overgeslagen omdat geen expliciet toegestane wegwerpdatabase is ingesteld. De overige regressies voor toegang, request/responsecontracten, characters, rich text, OIDC, finance, migraties en cache/updatebeheer zijn geslaagd.

PHP-syntaxcontrole is geslaagd voor alle 150 gevonden PHP-bestanden buiten `vendor`, `legacy` en `node_modules`. `git diff --check` is geslaagd. Er is lokaal geen Node-, Deno-, Bun- of qjs-runtime beschikbaar; JavaScript is daarom via de gerichte statische contractcontroles en PHP-gedragstests gecontroleerd, niet met een afzonderlijke JS-parser of browserruntime.

Niet lokaal bewezen: echte InnoDB-lockoverlap, WordPress-sessie-integratie, phpMyAdmin-uitvoering van 0005 en browsergedrag op `aetherapp-dev`.

## Exacte runtime-uploadlijst

Gedeeld:

- `api/shared/idempotency.php`

Eventkern:

- `api/events/eventAccess.php`
- `api/events/eventSchemas.php`
- `api/events/eventRepository.php`
- `api/events/eventService.php`
- `api/events/eventKnowledgeService.php`
- `api/events/getEventList.php`
- `api/events/newEvent.php`
- `api/events/updateEvent.php`
- `api/events/updateParticipation.php`

Characteractions:

- `api/characters/characterActionService.php`
- `api/characters/characterSkillActionUtils.php`
- `api/characters/gossipKnowledgeUtils.php`
- `api/characters/revealCharacterActionKnowledge.php`
- `api/characters/useCharacterSkillAction.php`

Admin eventconsumenten:

- `api/admin/getKnowledgeEvents.php`
- `api/admin/getKnowledgeEventGossip.php`
- `api/admin/saveKnowledgeVisibility.php`
- `api/admin/deleteKnowledgeUnlock.php`
- `api/admin/getActionEvents.php`
- `api/admin/getActionEventUses.php`
- `api/admin/updateActionUse.php`
- `api/admin/deleteActionUse.php`

Frontend:

- `js/mainFunctions.js`
- `js/eventFunctions.js`
- `js/actionsCharacter.js`
- `js/adminFunctions.js`
- `js/adminActions.js`

`VERSION` is tijdens deze taak niet automatisch gewijzigd. Behoud de huidige lokale wijziging en kies pas bij de gecontroleerde uitrol één nieuwe unieke releasewaarde.

## Onderhouds- en uitvoervolgorde

1. Maak een volledige database- en bestandenback-up en noteer de huidige `VERSION`.
2. Activeer een onderhoudsmodus die ook reeds geopende financiële/actionwrites blokkeert.
3. Selecteer expliciet de `aetherapp-dev`-database en voer `run_0005_event_integrity_phpmyadmin.sql` uit.
4. Controleer succesmelding, registratie `0005_unique_event_user`, de unique index `(idEvent,idUser)` en lookupindex `(idUser,idEvent)`.
5. Upload eerst gedeelde helper, eventaccess/schema/repositories/services en gewijzigde characterhelpers.
6. Upload daarna alle gewijzigde PHP-endpoints als één korte batch.
7. Upload `js/mainFunctions.js` vóór de vier JS-consumenten en upload die consumenten direct erna.
8. Zet als laatste de gekozen nieuwe `VERSION` online. Hierdoor krijgen oude tabs de bestaande updatemelding. Een tab met oude JS die toch een nieuwe actionwrite probeert, krijgt HTTP 400 met de instructie te vernieuwen en kan geen onbeschermde mutatie uitvoeren.
9. Voer onderstaande smoketest uit en beëindig pas daarna de onderhoudsmodus.

## Online checklist `aetherapp-dev`

- [ ] Participant ziet de eventlijst en alleen de eigen deelnamekolom.
- [ ] Participant kan geen andere user-ID gebruiken en kan geen event/deelname beheren.
- [ ] Director en administrator kunnen event aanmaken, wijzigen en deelname aan/uit zetten.
- [ ] Een dubbele deelname blijft één databasekoppeling.
- [ ] HTML in eventtitel, beschrijving of venue verschijnt letterlijk en voert niets uit.
- [ ] Character diary opent en bestaande toegestane rich text blijft correct.
- [ ] Actioncatalogus en knowledge targets werken voor eigen character; vreemd character wordt geweigerd.
- [ ] Een dubbele klik op skillaction of reveal levert één use/poging op.
- [ ] Retry na een afgebroken response gebruikt dezelfde key en dezelfde opgeslagen response.
- [ ] Director/admin kan gossipvisibility wijzigen en een unlock verwijderen; counter en unlock blijven samen consistent.
- [ ] Director/admin kan action-use wijzigen en verwijderen; dubbele klik voert de write eenmaal uit.
- [ ] Ontbrekende/foutieve CSRF geeft 403 zonder writes.
- [ ] Onverwachte velden geven 422; serverfouten tonen geen SQL-, tabel- of paddetails.
- [ ] Economie- en companysnapshotselecties tonen dezelfde events en financiële writes blijven via de financeflow lopen.
- [ ] `version.php` toont de gekozen releaseversie met `no-store` en een oude tab toont de herlaadmelding.

## Resterende risico's

- De echte MariaDB-concurrencytest is geleverd maar lokaal niet uitgevoerd. Gebruik uitsluitend een expliciet toegestane wegwerpdatabase met twee onafhankelijke connecties.
- Migratie 0005 is niet door deze taak uitgevoerd.
- De lokale export bewijst 0004 niet; de status “online uitgevoerd” komt uit de opdrachtcontext.
- Eventstatus, verborgen events en veilig eventverwijderen hebben geen bestaand datamodel of bedrijfsregel en blijven buiten deze batch.
