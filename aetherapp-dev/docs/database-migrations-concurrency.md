# Database-migraties en concurrency

Datum: 21 september 2026

Update: 22 september 2026

## Eventmodule-update (0005)

Volgens de actuele opdrachtcontext zijn migraties 0001–0004 op `aetherapp-dev` uitgevoerd. De lokale export van 21 september blijft het recentste bestand dat in deze werkmap beschikbaar is en bewijst alleen 0001–0003; hij wordt daarom niet voorgesteld als bewijs voor de online toestand van 0004.

Deze eventbatch voegt migratie `0005_unique_event_user` toe. De unieke index op `(idEvent, idUser)` sluit dubbele eventdeelnames onder concurrency. De omgekeerde index `(idUser, idEvent)` ondersteunt de bestaande eventlijstlookup. De zelfstandige phpMyAdminbundel staat in `sql/migrations/run_0005_event_integrity_phpmyadmin.sql`; deze taak heeft hem niet op een database uitgevoerd.

Characteractionwrites gebruiken nu dezelfde `tblApiIdempotency`-voorziening als finance. De idempotentiesleutel is gebonden aan de actuele gebruiker, operatie en gevalideerde payload. `useCharacterSkillAction.php`, `revealCharacterActionKnowledge.php` en de actieve beheerwrites voor knowledge/action-use slaan mutatie en response in één transactie op. Een replay controleert de actuele rechten opnieuw.

De gossipunlockrace is gesloten met deze lockvolgorde:

1. idempotentierij voor gebruiker, operatie en request-ID;
2. viewer/event-attemptcounter via `SELECT ... FOR UPDATE`;
3. concrete viewer/event/source-unlockrij via `SELECT ... FOR UPDATE`;
4. atomaire tellerincrement;
5. monotone vlagmerge met `GREATEST`, zodat een eenmaal ontgrendelde vlag niet naar nul terugkeert;
6. opgeslagen idempotentieresponse en commit.

Een fout rolt claim, attemptcounter en unlockstate samen terug. De lokale stateful tests bewijzen volgorde, replay en rollback. `tests/event_gossip_mariadb_concurrency_test.php` levert daarnaast een echte overlaptest met twee onafhankelijke MariaDB-connecties; die wordt alleen uitgevoerd met een expliciet toegestane wegwerpdatabase.

## Samenvatting

Deze batch introduceert een eenvoudige migratiestructuur voor handmatige uitvoering via phpMyAdmin of een gecontroleerde MariaDB/MySQL-CLI. Er is geen publiek PHP-endpoint toegevoegd en er is geen SQL uitgevoerd tegen de geconfigureerde ontwikkel- of productiedatabase.

De batch voegt twee unieke relaties toe:

- één rij per `(idCharacter, idSkill)` in `tblLinkCharacterSkill`;
- één rij per `(idCharacter, idSkill, idSkillSpecialisation)` in `tblCharacterSpecialisation`.

De applicatie-inserts gebruiken na de migratie `ON DUPLICATE KEY UPDATE id = id`. Twee gelijktijdige identieke toevoegingen leveren daardoor één koppeling en dezelfde bestaande succesresponse op. De gossipattemptcounter gebruikt voortaan een atomaire database-increment, zodat gelijktijdige reveals geen increment meer kunnen overschrijven.

Financiële idempotentie is geïmplementeerd via migratie 0004 en `tblApiIdempotency`. De browser bewaart per gebruikershandeling een cryptografisch willekeurige request-ID en hergebruikt die bij een netwerkfout of HTTP 5xx. De server bindt de sleutel aan de actuele gebruiker uit `tblUser`, een vaste operatiecode en een SHA-256-hash van de gevalideerde payload. De claim, domeinmutatie en opgeslagen succesresponse staan in één transactie. De eventbatch gebruikt dezelfde voorziening nu ook voor characteractions en eventgebonden beheerwrites.

## Online status `oneiros_beaetherdev`

De door de gebruiker uitgevoerde one.com-inspectie van 21 september 2026 bevestigt dat migraties 0001–0003 zijn toegepast:

- geselecteerde database: `oneiros_beaetherdev`;
- server: MariaDB `10.11.18-MariaDB-ubu2404`;
- `FOREIGN_KEY_CHECKS = 1`;
- `tblSchemaMigration` bestaat;
- `tblLinkCharacterSkill` heeft een unieke index op exact `idCharacter, idSkill`;
- `tblCharacterSpecialisation` heeft een unieke index op exact `idCharacter, idSkill, idSkillSpecialisation`;
- de duplicatecontroles voor beide relaties zijn leeg;
- de controles op nullwaarden in beide relaties zijn leeg;
- de specialisatiecontrole voor ontbrekende referenties en een verkeerde skill is leeg;
- migraties 0001, 0002 en 0003 zijn geregistreerd met `appliedAt = 2026-09-21 07:53:56`.

De inspectie rapporteert vier niet-blokkerende, reeds bekende verweesde character-skilllinks: IDs 70, 71 en 72 verwijzen naar character 3; ID 508 verwijst naar character 81. Zij zijn niet gewijzigd.

De recentste beschikbare export is `sql/oneiros_beaetherdev.sql`, gegenereerd op `2026-09-21 09:01`. Deze bevat `tblSchemaMigration` en beide unieke indexes en is daarom de actuele post-migratiebron voor 0001–0003. De oudere export in de Downloads-map blijft uitsluitend de pre-migratieback-up.

Volgens de aangeleverde actuele context is migratie 0004 online uitgevoerd. Deze lokale taak heeft geen SQL tegen een geconfigureerde database uitgevoerd en heeft die online toestand daarom niet zelfstandig geverifieerd.

## Huidige schemasituatie

De actuele onderzochte export is `sql/oneiros_beaetherdev.sql`, gegenereerd op 21 september 2026 om 09:01 door phpMyAdmin 5.2.3. De export vermeldt MariaDB **10.11.18** en PHP 8.3.6. Hij bevat de bevestigde toestand na migraties 0001–0003. Vóór migratie 0004 blijft een back-up en controle van de geselecteerde `aetherapp-dev`-database vereist.

### `tblLinkCharacterSkill`

- Kolommen: `id`, `idCharacter`, `idSkill`, `level`; alle vier zijn `NOT NULL`.
- Bestaande index: alleen primary key `id`.
- De export bevat geen foreign keys vanuit deze tabel.
- De export bevat 431 rijen en geen dubbele combinatie `(idCharacter, idSkill)`.
- De export bevat vier rijen met een ontbrekend character: link-ID’s **70, 71, 72 en 508**, voor character-ID’s 3, 3, 3 en 81.
- In de export zijn geen skilllinks naar een ontbrekende skill gevonden.

De vier weesrecords blokkeren de unieke index niet en worden door deze migratie niet gewijzigd. Zij vereisen wel een inhoudelijke beslissing voordat later een character-foreign-key op deze tabel kan worden toegevoegd. De preflight rapporteert ze opnieuw tegen de actuele testdatabase.

### `tblCharacterSpecialisation`

- Kolommen: `id`, `idCharacter`, `idSkill`, `idSkillSpecialisation`; alle zijn `NOT NULL`.
- Bestaande indexen: primary key `id` plus losse indexen op `idCharacter`, `idSkill` en `idSkillSpecialisation`.
- Bestaande foreign keys verwijzen met `ON DELETE CASCADE` naar `tblCharacter`, `tblSkill` en `tblSkillSpecialisation`.
- De export bevat 52 rijen en geen dubbele combinatie `(idCharacter, idSkill, idSkillSpecialisation)`.
- In de export zijn geen weesrecords en geen links gevonden waarbij de specialisatiedefinitie bij een andere skill hoort.

De applicatie controleert de skillrelatie bovendien met `WHERE id = ? AND idSkill = ?` in `aetherFetchSkillSpecialisationForSkill()`. De nieuwe unique key voorkomt dubbele links; hij vervangt deze inhoudelijke controle niet.

### Reeds aanwezige actionconstraints

- `tblCharacterEventGossipAttempt` heeft al unique key `uq_tblCharacterEventGossipAttempt_viewer_event (idViewerCharacter, idEvent)`.
- `tblCharacterEventGossipUnlock` heeft unique key `uq_tblCharacterEventGossipUnlock_viewer_event_source`.
- `tblCharacterSkillActionState` heeft unique key `uq_tblCharacterSkillActionState_character_action_state`.
- `tblCharacterSkillActionUse` heeft een primary key en gewone indexen, maar geen idempotentiesleutel.

## Migratiestructuur

```text
sql/migrations/
  README.md
  run_0001_to_0003_phpmyadmin.sql
  inspect_0001_to_0003_onecom_readonly.sql
  run_0001_to_0003_requires_information_schema.sql.reference
  run_0004_finance_idempotency_phpmyadmin.sql
  run_0005_event_integrity_phpmyadmin.sql
  preflight/
    0001_create_schema_migration_registry.sql
    0002_unique_character_skill.sql
    0003_unique_character_specialisation.sql
    0004_api_idempotency.sql
    0005_unique_event_user.sql
  apply/
    0001_create_schema_migration_registry.sql
    0002_unique_character_skill.sql
    0003_unique_character_specialisation.sql
    0004_api_idempotency.sql
    0005_unique_event_user.sql
  verify/
    0001_create_schema_migration_registry.sql
    0002_unique_character_skill.sql
    0003_unique_character_specialisation.sql
    0004_api_idempotency.sql
    0005_unique_event_user.sql
  rollback/
    0001_create_schema_migration_registry.sql
    0002_unique_character_skill.sql
    0003_unique_character_specialisation.sql
    0004_api_idempotency.sql
    0005_unique_event_user.sql
```

### Hostingblokkade voor de gebundelde uitvoering

De one.com/dbadmin-uitvoering bewees dat het databaseaccount geen betrouwbare toegang heeft tot `information_schema`: de server retourneerde `#1044 - Access denied ... to database 'information_schema'`. De tientallen meldingen over `BEGIN NOT ATOMIC`, `DECLARE` en `IF` kwamen uit de statische phpMyAdmin-parser; de beslissende serverfout is de ontbrekende metadata-toegang.

Zonder deze metadata kan een automatisch bestand niet veilig vaststellen of een equivalente unieke index al onder een andere naam bestaat en evenmin blokkeren wanneer de bedoelde indexnaam een verkeerde definitie heeft. Een script dat desondanks `ALTER TABLE ... IF NOT EXISTS` uitvoert zou niet aan de afgesproken veiligheidsvoorwaarden voldoen.

`run_0001_to_0003_phpmyadmin.sql` is daarom onschadelijk gemaakt en toont alleen een blokkademelding. De eerdere implementatie is uitsluitend als niet-uitvoerbare referentie bewaard in `run_0001_to_0003_requires_information_schema.sql.reference`.

`inspect_0001_to_0003_onecom_readonly.sql` gebruikt alleen directe `SELECT`- en `SHOW`-statements. Het leest geen `information_schema` en wijzigt niets. De resultaten daarvan zijn nodig om te bepalen welke migratiestappen door eerdere pogingen al zijn uitgevoerd.

`tblSchemaMigration` bevat:

- auto-increment primary key `id`;
- unieke `migrationCode`;
- `description`;
- `appliedAt` met de uitvoeringstijd.

Een indexmigratie schrijft haar registerrij pas na `ALTER TABLE` en alleen wanneer `information_schema.STATISTICS` de verwachte unieke index met de exacte kolomvolgorde toont. Wanneer de `ALTER TABLE` door duplicaten faalt en phpMyAdmin toch volgende statements probeert, kan de migratie daardoor niet ten onrechte geregistreerd worden.

## Preflight en menselijke beslissingen

Alle bestanden onder `preflight/` bevatten uitsluitend `SELECT`-queries. Zij controleren:

- of de registermigratie al bestaat;
- dubbele samengestelde sleutels met alle betrokken rij-ID’s;
- onverwachte nullwaarden;
- ontbrekende characters, skills of specialisatiedefinities;
- specialisaties die volgens hun definitie bij een andere skill horen;
- bestaande indexen en constraints.

Wanneer een duplicate-query een rij geeft, voer het bijbehorende applybestand niet uit. De betrokken records kunnen verschillende levels of andere betekenis hebben. Een director/administrator moet bepalen welke rij inhoudelijk correct is; deze migraties kiezen, verwijderen of combineren niets automatisch.

Wanneer de specialisatie-integriteitsquery een rij geeft, stop eveneens. Eerst moet worden bepaald of `idSkill` of `idSkillSpecialisation` fout is. De applymigratie mag geen willekeurige kant kiezen.

Weesrecords in `tblLinkCharacterSkill` blokkeren de unique key niet. Bewaar het preflightresultaat en neem afzonderlijk een menselijke beslissing over herstel of verwijdering voordat ooit foreign keys op deze tabel worden ingevoerd.

## Applicatiecorrecties

### Characterskills

`aetherInsertCharacterSkillLink()` voert de bestaande insert nu uit als:

```sql
INSERT INTO tblLinkCharacterSkill (idCharacter, idSkill, level)
VALUES (:idCharacter, :idSkill, :level)
ON DUPLICATE KEY UPDATE id = id
```

De eerste aanvraag bewaart het bestaande level. Een gelijktijdige identieke aanvraag verandert die rij niet en `AddNewSkill.php` retourneert daarna, zoals voorheen, het skillobject. Een unique conflict wordt dus geen HTTP 500 en lekt geen databasedetails.

### Characterspecialisaties

De insert in `aetherInsertCharacterSkillSpecialisation()` gebruikt dezelfde no-op-conflictafhandeling. De bestaande voorafgaande check blijft nuttig voor normaal gedrag en XP-berekening; de unique key is de beslissende bescherming wanneer twee requests tussen die check en de insert racen. De definitie wordt nog altijd met zowel specialisatie-ID als skill-ID opgehaald.

Gelijktijdig aanmaken van twee nieuwe globale specialisatiedefinities met dezelfde naam blijft een afzonderlijk risico: `tblSkillSpecialisation` heeft geen unique key op `(idSkill, name)`. Deze batch voegt die regel niet toe, omdat naamnormalisatie en de betekenis van gelijknamige definities eerst functioneel beslist moeten worden.

### Gossipattempts

De vroegere code deed:

1. `SELECT attemptCount`;
2. in PHP `+ 1`;
3. upsert van de berekende waarde.

Twee transacties konden dezelfde beginwaarde lezen en beide dezelfde volgende waarde opslaan. De nieuwe query doet één atomaire upsert:

```sql
INSERT INTO tblCharacterEventGossipAttempt (idViewerCharacter, idEvent, attemptCount, updatedAt)
VALUES (:idViewerCharacter, :idEvent, 1, NOW())
ON DUPLICATE KEY UPDATE
    attemptCount = attemptCount + 1,
    updatedAt = NOW()
```

Daarna leest dezelfde lopende revealtransactie de toegewezen waarde. InnoDB serialiseert concurrerende updates op de bestaande unieke viewer/event-rij. De daaropvolgende gossip-unlockwrite blijft in dezelfde service-transactie; een fout rolt counter en unlock samen terug.

De lokale test simuleert dat een andere transactie de waarde verhoogt vlak vóór de geteste write. De eindwaarde en response worden 2; de oude read-modify-writequery zou 1 terugschrijven.

De teller is hiermee beschermd tegen verloren increments. De eventbatch vergrendelt daarnaast de concrete viewer/event/source-unlockrij en schrijft vlaggen monotoon met `GREATEST`. Twee gelijktijdige reveals kunnen daardoor geen eerder ontgrendelde vlag terug op nul zetten. Attemptcounter en unlockresultaat blijven onderdeel van dezelfde transactie.

## Financiële idempotentie

Migratie 0004 voegt `tblApiIdempotency` toe. De financiële characterwrites, company-snapshotwrites, directe company-value-update en directe character-saldowijzigingen gebruiken het volgende contract:

- de browser maakt per gebruikershandeling een cryptografisch willekeurige UUID en stuurt die als `Idempotency-Key`;
- een retry van dezelfde handeling hergebruikt exact dezelfde key;
- de server berekent na validatie een SHA-256-hash over de canonieke gevalideerde payload en bindt die via de unieke sleutel aan vertrouwde user-ID en operatie;
- dezelfde key met een andere hash geeft HTTP 409;
- dezelfde voltooide key met dezelfde hash retourneert de opgeslagen HTTP-status en response;
- een gelijktijdige identieke aanvraag wacht op of leest dezelfde transactionele rij en voert de domeinwrite niet opnieuw uit.

De geïmplementeerde tabel bevat:

```text
tblApiIdempotency
  id                  bigint primary key auto_increment
  idUser              int not null
  operation           varchar(100) not null
  requestKey          varchar(128) not null
  payloadHash         char(64) not null
  status              enum('processing','completed') not null
  responseStatus      smallint null
  responseJson        mediumtext null
  createdAt           datetime not null
  updatedAt           datetime not null
  expiresAt           datetime not null
  unique (idUser, operation, requestKey)
```

De idempotentierij, financiële mutatie en opgeslagen response staan in dezelfde database-transactie. Een rollback verwijdert dus ook de niet-voltooide claim. `expiresAt` wordt op zeven jaar gezet: financiële audit- en retrybescherming vraagt een veel langere termijn dan de eerder als voorbeeld genoemde 72 uur. De runtime negeert verlopen regels niet stilzwijgend. Opruiming mag later alleen via een gecontroleerde archiefprocedure gebeuren; na het verwijderen van een sleutel kan een zeer oude retry opnieuw als nieuwe handeling gelden.

Characteracties zijn sinds de eventbatch aangesloten. Een bewust nieuwe actie krijgt een nieuwe browserkey; alleen een retry van dezelfde gebruikershandeling hergebruikt de bestaande key. De gossipunlockrij wordt vóór wijziging vergrendeld en met `GREATEST` samengevoegd, zodat gelijktijdige reveals geen ontgrendelde vlag verliezen.

## Handmatige uitvoering via phpMyAdmin op `aetherapp-dev`

Migraties 0001–0003 zijn volgens de aangeleverde inspectieresultaten al toegepast. Voer ze niet opnieuw uit. Voor deze financiële batch is `run_0004_finance_idempotency_phpmyadmin.sql` het zelfstandige bestand: het vereist geen `SOURCE`, stored procedure of `information_schema`, controleert de drie prerequisites, verifieert de unieke idempotentiescope met tijdelijke probegegevens en registreert 0004 pas daarna.

### Uitvoering migratie 0004

1. Activeer onderhoudsmodus voor financiële writes en maak een volledige database-export.
2. Selecteer expliciet de `aetherapp-dev`-testdatabase.
3. Importeer `sql/migrations/run_0004_finance_idempotency_phpmyadmin.sql` één keer.
4. Controleer de succesmelding, de ene registratie `0004_create_api_idempotency`, `SHOW CREATE TABLE` en de unieke index op `(idUser, operation, requestKey)`.
5. Volg daarna de bestands- en VERSION-volgorde in `docs/character-economy-finance-refactor.md`.

### Historische uitvoering en inspectie van 0001–0003

#### Voorbereiding

1. Plan een kort onderhoudsvenster waarin geen skills of specialisaties worden toegevoegd.
2. Controleer in phpMyAdmin dat de geselecteerde database werkelijk de afzonderlijke `aetherapp-dev`-testdatabase is.
3. Exporteer een volledige back-up met structuur en data, inclusief triggers, routines en events indien aanwezig.
4. Bewaar daarnaast het resultaat van alle preflightqueries als bewijs van de beginsituatie.
5. Upload nog geen aangepaste PHP-bestanden; de databaseconstraints komen eerst.

#### Veilige one.com-inspectie

1. Selecteer in phpMyAdmin expliciet `oneiros_beaetherdev`.
2. Importeer uitsluitend `sql/migrations/inspect_0001_to_0003_onecom_readonly.sql`.
3. Bewaar alle resultaten van `SELECT`, `SHOW TABLES` en `SHOW INDEX`.
4. Fout 1146 bij de laatste registratiequery betekent dat `tblSchemaMigration` nog niet bestaat; de voorgaande inspectieresultaten blijven bruikbaar.
5. Voer voorlopig geen bestand uit `apply/` of `rollback/` uit en probeer het geblokkeerde bundelbestand niet verder aan te passen in phpMyAdmin.

Pas nadat de inspectieresultaten zijn beoordeeld kan een gerichte uitvoering worden opgesteld voor de werkelijk aanwezige indexen en registraties. Daarmee wordt voorkomen dat een eerdere gedeeltelijke uitvoering wordt overschreven of dat een fout gedefinieerde index stilzwijgend wordt geaccepteerd.

#### PHP-upload na succesvolle SQL

Upload vervolgens samen:

1. `api/characters/characterSkillRepository.php`;
2. `api/characters/AddNewSkill.php`;
3. `api/characters/gossipKnowledgeUtils.php`.

Deze volgorde als één korte onderhoudsbatch voorkomt dat de no-op-inserts tijdelijk zonder de benodigde unique keys draaien. Frontendbestanden hoeven voor deze batch niet gewijzigd of geüpload te worden.

## Verificatie en online teststappen

Voer op `aetherapp-dev` met uitsluitend testdata uit:

1. Voeg één nieuwe skill aan een testcharacter toe en herlaad het character.
2. Verstuur vanuit twee browsertabs vrijwel gelijktijdig exact hetzelfde `AddNewSkill.php`-request. Beide responses moeten functioneel slagen en de database moet één link bevatten.
3. Voeg één bestaande specialisatie toe en herhaal hetzelfde request vrijwel gelijktijdig. Beide responses blijven `{ "success": true }`; er blijft één link.
4. Probeer een specialisatie-ID die bij een andere skill hoort. Het request moet worden geweigerd en mag niets schrijven.
5. Lees de huidige gossipattemptwaarde voor één testcharacter/event.
6. Verstuur twee vrijwel gelijktijdige geldige revealrequests waarvoor nog gossip pending is. De teller moet exact met 2 stijgen.
7. Forceer in een veilige testopstelling een fout bij de unlockwrite. Counter en unlock mogen beide niet wijzigen en de response mag geen SQL/PDO-details bevatten.
8. Herhaal toegangstests als participant op eigen en vreemd character en als director/administrator.
9. Controleer ontbrekende en foutieve CSRF-tokens; geen betrokken tabel mag wijzigen.
10. Controleer de normale skill-, specialisatie-, reveal- en psi-responsevelden tegen de bestaande frontend.

## Rollback en beperkingen

MariaDB-DDL zoals `ALTER TABLE` veroorzaakt impliciete commits. De applybestanden zijn daarom niet verpakt in een transactie en er wordt geen transactionele rollback geclaimd.

- De registryrollback verwijdert de tabel bewust niet, omdat dit migratiehistoriek zou vernietigen.
- De rollbackbestanden voor 0002 en 0003 verwijderen uitsluitend de toegevoegde index en daarna hun registerrij wanneer de index aantoonbaar weg is.
- De rollback van 0004 verwijdert de idempotentietabel en daarmee retry-/auditgegevens. Zet daarom eerst alle afhankelijke runtimecode terug en voer deze rollback alleen na een expliciete auditbeslissing uit.
- Een rollback verwijdert of reconstrueert geen gebruikersdata.
- Het verwijderen van de indexes maakt toekomstige duplicaten opnieuw mogelijk. Laat de indexes bij voorkeur staan, ook wanneer alleen de PHP-code wordt teruggedraaid.
- Als een index toch moet worden verwijderd: activeer onderhoudsmodus, herstel eerst een compatibele PHP-versie en voer daarna uitsluitend het betreffende rollbackbestand uit.

Niet lokaal bewezen:

- uitvoering van de DDL op de actuele testdatabase;
- lockgedrag met twee echte MariaDB-connecties;
- phpMyAdmin-verwerking en time-outs bij `ALTER TABLE`;
- browserrequests via WordPress-sessies.

De eerste en derde punten zijn inmiddels door de gebruiker via one.com uitgevoerd. De lokale omgeving heeft die uitvoering niet zelf aangestuurd; bovenstaande online status is gebaseerd op de aangeleverde phpMyAdmin-resultaten. Echte parallelle databaseconnecties en browserrequests blijven niet getest.

De lokale PDO-testdouble bewijst de queryvorm, transactievolgorde, rollbackstate, responses en gesimuleerde interleaving. SQLite is niet gebruikt om MariaDB-DDL als compatibel voor te stellen.

## Uitgevoerde tests

Op 21 september 2026 zijn lokaal uitgevoerd:

- alle 22 aanwezige `tests/*_test.php`-bestanden;
- 20 testsuites geslaagd, waaronder de nieuwe financiële endpoint- en servicetests, skills/actions, traits/diary/languages, ties, character reads, `updateCharacter`, toegangscontrole, routecoverage, request-/validatie-/responsecontracten, rich text, OIDC, lifecycle/portraits en de migratietest;
- `authenticated_user_test.php` niet uitgevoerd: de suite meldt `SKIP` met exitcode 2 omdat PDO SQLite lokaal niet beschikbaar is;
- `character_finance_mariadb_concurrency_test.php` overgeslagen omdat geen expliciet toegestane wegwerp-MariaDB was ingesteld;
- PHP-syntaxcontrole geslaagd voor alle 141 gevonden PHP-bestanden buiten `vendor`, `legacy` en `node_modules`;
- `git diff --check` geslaagd;
- aanvullende controle op trailing whitespace in de nieuwe, nog niet door Git gevolgde documentatie-, SQL- en testbestanden geslaagd.

Niet uitgevoerd en daarom niet als geslaagd aangemerkt:

- de SQL-migraties tegen MariaDB/MySQL;
- een echte parallelle test met twee databaseconnecties;
- phpMyAdmin-uitvoering van migratie 0004;
- WordPress-sessie- en browsertests op `aetherapp-dev`.

## Gewijzigde bestanden

- `api/characters/characterSkillRepository.php`: racebestendige no-op-inserts voor skill- en specialisatielinks.
- `api/characters/AddNewSkill.php`: gebruikt de gerichte repositoryfunctie.
- `api/characters/gossipKnowledgeUtils.php`: atomaire gossipattemptincrement.
- `sql/migrations/run_0001_to_0003_phpmyadmin.sql`: onschadelijke blokkademelding; voert op one.com geen DDL of datamutatie meer uit.
- `sql/migrations/inspect_0001_to_0003_onecom_readonly.sql`: hostingcompatibele alleen-lezen inventarisatie met `SELECT` en `SHOW`.
- `sql/migrations/run_0001_to_0003_requires_information_schema.sql.reference`: niet-uitvoerbare referentie van de eerdere bundelimplementatie.
- `sql/migrations/README.md`: one.com-blokkade en veilige inspectiestappen.
- `sql/migrations/**`: register, preflight-, apply-, verify- en rollbackbestanden voor migraties 0001–0003.
- `tests/database_migrations_concurrency_test.php`: migratie- en exportcontroles.
- `tests/simple_character_endpoints_batch2_test.php`: duplicate-skillrace.
- `tests/character_skills_actions_endpoints_test.php`: duplicate-specialisatierace, atomaire gossipinterleaving en bestaande rollbacktests.
