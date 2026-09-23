# Character-economie en financiële transacties

Datum: 21 september 2026

## Resultaat

De actieve financiële writes gebruiken nu dunne endpoints, expliciete JSON-schema’s, vertrouwde rollen uit `tblUser`, centrale CSRF-controle, vaste prepared queries en één transactionele idempotentielaag. Saldo-, effecten-, aandelen- en payoutmutaties worden pas na het vergrendelen van de actuele records beoordeeld en committen samen met hun audit-/historierijen en opgeslagen succesresponse.

Er is niets gedeployed en migratie 0004 is niet op een database uitgevoerd.

## Onderzochte keten en contracten

De frontend gebruikt voor alle onderstaande writes `POST` met `application/json`. `apiFetchJson()` voegt het bestaande `X-CSRF-Token` toe. `apiFetchFinancialJson()` voegt voor deze routes ook `Idempotency-Key` toe.

| Route | Actieve requestvelden | Bestaande succesresponse |
|---|---|---|
| `api/characters/saveBankTransfer.php` | `idSourceCharacter`, `idTargetCharacter`, `amount`, `description`, `transactionDate` | `{success:true,idTransaction}` |
| `api/characters/deleteBankTransaction.php` | `idTransaction` | `{success:true}` |
| `api/characters/buyCompanyShare.php` | `idCharacter`, `idCompany`, `shareClass` (`A`/`B`) | `{success:true,idLinkCharacterTrait,unitPrice}` |
| `api/characters/saveCompanyShare.php` | `action`, `idLinkCharacterTrait`, afhankelijk van actie `idCompany` | `{success:true}`, bij kopen/verkopen aangevuld met de bestaande prijsvelden |
| `api/characters/saveCharacterEconomySnapshot.php` | `idCharacter`, `idEvent` | `{success:true,amount}` |
| `api/characters/deleteCharacterEconomySnapshot.php` | `idSnapshot` | `{success:true,revertedAmount}` |
| `api/characters/saveCharacterSecuritiesPortfolio.php` | `action`, `idCharacter` en actiegebonden settings, `idSnapshot` of `amount` | bestaande `{success:true}`; snapshotopname behoudt `bankAmount` en `hasLoss` |
| `api/characters/updateCharacter.php` | bestaand characterschema; financiële velden zijn `bankaccount`, `securitiesaccount`; een statusovergang kan het startsaldo instellen | bestaande numerieke row-countresponse |
| `api/companies/saveCompanySnapshot.php` | `action`, `idCompany` en actiegebonden event/snapshot/slidervelden | bestaand object met `success`, `company`, `availableSharePercentage`, `snapshotEventOptions`, `snapshots` |
| `api/companies/updateCompany.php` | `id` en toegestane companyvelden, waaronder `companyValue` | `{status:"ok",updatedShareTraitCount}` |

De leesketen blijft lopen via het bestaande `getCharacter.php`/character-readmodel en de bestaande company-detailroutes. Deze batch verandert hun responsevelden niet.

Er bestaat geen actieve afzonderlijke route om aandelen rechtstreeks van character A naar character B over te dragen. `saveCompanyShare.php` koppelt of ontkoppelt een bestaand aandelen-trait en verhoogt of verlaagt het percentage; `buyCompanyShare.php` koopt een nieuw 1%-aandeel. Er is daarom geen niet-bestaande transferregel toegevoegd.

## Rollen en bedrijfsregels

| Handeling | Participant | Director / administrator |
|---|---|---|
| Banksaldo rechtstreeks wijzigen | niet toegestaan | niet-draft character |
| Bankoverschrijving uitvoeren | niet toegestaan | van niet-draft character naar actief character |
| Bankverrichting verwijderen | niet toegestaan | toegestaan volgens bestaand beheerbeleid |
| Economiesnapshot maken/verwijderen | niet toegestaan | niet-draft character |
| Effecteninstellingen, storting en opname | uitsluitend eigen toegestane niet-draft character | niet-draft character |
| Effectensnapshot herrollen/goedkeuren | niet toegestaan | niet-draft character |
| Aandeel kopen/verhogen/verlagen | eigen niet-draft player-character | niet-draft player of extra |
| Aandelenbedrijf koppelen/ontkoppelen | eigen player-character | toegestaan |
| Companywaarde en companysnapshots | niet toegestaan | toegestaan |

De server gebruikt steeds de actuele user-ID en rol uit `tblUser`. Character-ID’s, company-ID’s, prijzen, saldi en bevoegdheden uit de browser gelden alleen als invoerreferentie en nooit als autoriteitsbron.

Bestaande regels zijn behouden:

- geen bankoverschrijving naar hetzelfde character;
- doel van een bankoverschrijving moet actief zijn;
- het bronsaldo mag niet worden overschreden;
- een company kan maximaal 100% toegewezen aandelen hebben;
- aankoop en verkoop van 1% gebruiken 1% van de actuele, vergrendelde companywaarde;
- de bestaande share-class-, character-class- en company-typekoppeling blijft gelden;
- normale effectenopname buiten een snapshot boekt 75% naar de bank;
- snapshotopnames behouden de bestaande grens van 30.000 en event-einddatumregel;
- goedkeuring en herrol van effectenresultaten blijven beheerhandelingen;
- draftbeperkingen en bestaande player/extra-uitzonderingen blijven gelden.

Bewuste beveiligingscorrecties:

- bedragen met meer dan twee decimalen worden met HTTP 422 geweigerd in plaats van stil afgerond;
- onbekende requestvelden in de aangepaste character- en gekoppelde company-writes worden geweigerd;
- financiële writes zonder geldige `Idempotency-Key` worden met HTTP 400 geweigerd;
- dezelfde sleutel met een andere gevalideerde payload geeft HTTP 409;
- onverwachte fouten geven alleen de generieke bestaande foutboodschap; technische details gaan naar `error_log`.

## Architectuur

### Gedeeld

- `api/shared/decimal.php`: normalisatie, vergelijking, optellen/aftrekken en ratiovermenigvuldiging in gehele centen.
- `api/shared/idempotency.php`: request-ID valideren, payload canoniseren, claim/replay/conflict en response opslaan.
- `api/shared/validation.php`: het herbruikbare type `decimal` met schaal en grenzen.

### Character-economie

- `characterFinanceRepository.php`: vaste queries, saldomutaties, historiewrites en `FOR UPDATE`-lezingen.
- `characterFinanceService.php`: banktransfer-, economiesnapshot- en effectenregels.
- `characterShareRepository.php`: company-, sharelink- en allocatiequeries.
- `characterShareService.php`: aankoop, verkoop, rang en companykoppeling.
- `characterSchemas.php`: expliciete actiegebonden requestvelden en exacte bedragregels.

De zeven financiële characterendpoints bevatten alleen authenticatie, CSRF, JSON parsing, één schema, idempotentie, één serviceaanroep en de gedeelde response. `updateCharacter.php` gebruikt dezelfde voorziening wanneer een request saldo’s of `state` bevat; een overgang uit `draft` kan immers automatisch `bankaccount` instellen.

### Gekoppelde company-writes

`companyFinanceSchemas.php` bevat uitsluitend de schema’s voor `updateCompany.php` en `saveCompanySnapshot.php`. Deze routes wijzigen dezelfde companywaarde of characterbankrekeningen en zijn daarom in de batch opgenomen. Hun bredere company-presentatielogica is niet geherstructureerd.

## Transacties en vergrendeling

`aetherRunIdempotentMutation()` beheert de buitenste transactie. Services starten geen geneste transactie. Companysnapshots gebruiken dezelfde lage claim-/completefuncties binnen hun bestaande samengestelde transactie.

De vaste lockvolgorde is:

1. idempotentierij voor vertrouwde user, operatie en request-ID;
2. betrokken companies in oplopende ID-volgorde;
3. betrokken characters in oplopende ID-volgorde;
4. concrete banktransactie, snapshot of sharelink;
5. mutaties, historie en opgeslagen response;
6. commit;
7. pas daarna de HTTP-succesresponse.

Bankoverschrijvingen vergrendelen bron en doel in één gesorteerde query. Aandelenaankopen vergrendelen eerst de company en daarna het character; de companyrij serialiseert de 100%-capaciteitscontrole. Companydividenden vergrendelen alle ontvangende characters in oplopende volgorde. Een sharelink die tussen de oriënterende read en de lock aan een andere company werd gekoppeld, geeft HTTP 409 en veroorzaakt geen write.

Een PDO-exception, deadlock of locktimeout rolt de volledige transactie terug en geeft een generieke HTTP 500. De frontend bewaart de request-ID bij netwerkfouten en 5xx, zodat opnieuw proberen dezelfde mutatie veilig hervat. Een 4xx beëindigt die gebruikershandeling en ruimt de lokale pending key op.

## Exacte bedragen en afronding

Gevalideerde bedragen worden canonieke decimale strings met twee cijfers na de komma. Vergelijkingen en procentberekeningen gebruiken gehele centen. SQL schrijft deze strings naar de bestaande `DECIMAL`-kolommen; browserwaarden bepalen nooit een actuele prijs of saldo.

De bestaande afrondingsregel blijft “naar de dichtstbijzijnde cent, halve cent omhoog in absolute waarde”. Voorbeeld: 75% van `10.01` is `7.51`. Companysnapshotbedragen, dividend per procent, companywaardedelta’s en personeelscorrecties volgen dezelfde exacte helper. JSON-succesvelden die historisch nummers waren blijven voor frontendcompatibiliteit nummers.

De oudere karakter-inkomensformules in `economyUtils.php` combineren veel spelmultipliers als floats en ronden hun eindresultaat al op twee decimalen. Deze batch normaliseert dat eindresultaat vóór iedere write en gebruikt daarna uitsluitend exacte centen voor saldo- en bevoegdheidsbeslissingen. De spelbalansformules zelf zijn bewust niet herschreven.

## Idempotentiecontract

De browser maakt een UUID per bewuste handeling. De opslagkey bestaat uit endpoint plus canonieke payload en leeft in `sessionStorage` met een geheugenfallback.

- Dubbelklik of retry vóór een duidelijke uitkomst gebruikt dezelfde request-ID.
- Na een succesvolle response wordt de pending key verwijderd; een nieuwe bewuste identieke actie krijgt een nieuwe UUID.
- Bij een verloren response na commit vindt de server de voltooide rij en retourneert hij de opgeslagen response zonder tweede domeinwrite.
- Dezelfde sleutel met andere gegevens geeft HTTP 409.
- Authenticatie en CSRF gebeuren vóór iedere replay. De idempotentiehelper voert daarna een routespecifieke actuele rol- en objectcontrole uit voordat hij een opgeslagen response teruggeeft. Een gebruiker die intussen zijn rol of eigenaarschap verloor krijgt dus geen replayresponse.

`expiresAt` staat op zeven jaar. De runtime blijft ook na die datum dezelfde rij respecteren. Er is geen automatische cleanup toegevoegd. Een toekomstige beheerprocedure moet eerst archiveren en mag alleen aantoonbaar afgesloten keys verwijderen; na verwijdering kan een zeer oude retry opnieuw uitvoerbaar worden.

De helper is technisch herbruikbaar voor characteracties, maar die routes zijn niet gemigreerd. De bestaande gossip-unlockrace blijft als afzonderlijk open punt in `docs/database-migrations-concurrency.md` staan.

## Migratie 0004

`tblApiIdempotency` bevat de vertrouwde user-ID, operatie, request-ID, payloadhash, status, opgeslagen HTTP-status/JSON, timestamps en een unieke sleutel op `(idUser, operation, requestKey)`.

Bestanden:

- `sql/migrations/preflight/0004_api_idempotency.sql`;
- `sql/migrations/apply/0004_api_idempotency.sql`;
- `sql/migrations/verify/0004_api_idempotency.sql`;
- `sql/migrations/rollback/0004_api_idempotency.sql`;
- `sql/migrations/run_0004_finance_idempotency_phpmyadmin.sql`.

De zelfstandige bundel is bedoeld voor MariaDB 10.11 via phpMyAdmin en gebruikt geen `SOURCE`, stored procedure of `information_schema`. Hij controleert eerst de registraties van 0001–0003, maakt de tabel herhaalbaar, bewijst met tijdelijke rijen dat duplicaten per user/operatie/key worden geblokkeerd en registreert 0004 pas daarna. De tijdelijke rijen worden verwijderd. Het script wijzigt geen bestaande applicatiedata.

Vereiste migratieprivileges: `CREATE`, `SELECT`, `INSERT` en `DELETE` binnen de geselecteerde testdatabase. `PREPARE` heeft geen afzonderlijk MariaDB-objectprivilege, maar moet door de hostingverbinding worden toegelaten. De runtime heeft ook `UPDATE` nodig. Voor de normale bundel zijn geen routine-, `information_schema`-, `ALTER`- of `DROP`-privileges nodig. De rollback vereist wel `DROP` en mag pas na runtime-rollback en auditbeslissing worden gebruikt.

## Tests

Gerichte lokale tests bewijzen:

- betaling, twee saldi en historiewrite committen samen;
- onvoldoende saldo, verkeerde rol/eigenaar, authenticatie, CSRF, ontbrekende key en ongeldige precisie geven nul commits;
- fout tussen af- en bijschrijving rolt historie en beide saldi terug;
- replay met dezelfde key/payload voert nul nieuwe writes uit;
- een replay controleert de actuele rol en objecttoegang opnieuw; ingetrokken rechten geven HTTP 403 zonder writes;
- keyconflict geeft HTTP 409;
- effectenstorting en de 75%-opname verwerken exacte bedragen;
- participant kan geen vreemd effectenaccount wijzigen;
- aandeel kopen en verkopen past saldo en allocatie samen aan;
- het laatste beschikbare aandeel kan eenmaal worden gekocht;
- director-/administratorbeleid blijft beschikbaar;
- companyschema’s weigeren onbekende velden;
- generieke 500-responses lekken geen SQLSTATE of tabeldetails;
- frontendroutes gebruiken de financiële fetchhelper;
- migratie 0004 is hervatbaar, conditioneel, registreert pas na verificatie en gebruikt geen `information_schema`.

`tests/character_finance_mariadb_concurrency_test.php` is uitvoerbaar met twee onafhankelijke MariaDB-processen. Hij vereist een expliciet toegestane wegwerpdatabase via `AETHER_TEST_MYSQL_DSN`, `AETHER_TEST_MYSQL_USER`, `AETHER_TEST_MYSQL_PASSWORD` en `AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS=YES`. Zonder die configuratie meldt de test `SKIP`. Daardoor zijn echt lockgedrag, deadlocks en overlap via de volledige online routes lokaal nog niet bewezen.

De exacte eindresultaten van de regressieronde staan onderaan dit document in **Uitgevoerde controles**.

## Resterende risico’s

- Echte MariaDB-concurrency en WordPress-/browserintegratie moeten nog op een geïsoleerde testomgeving worden uitgevoerd.
- De huidige idempotentietabel heeft bewust geen automatische cleanup; dit vraagt later een audit-/archiefbeleid.
- PHP-sessielocking kan browserverzoeken van dezelfde sessie serialiseren. De afzonderlijke MariaDB-test gebruikt daarom twee processen en geen PHP-sessie.
- De character-inkomensformules behouden hun historische float-gebaseerde spelberekening tot aan de exacte opslaggrens.
- De gossip-unlockrace valt buiten deze batch.

## Online checklist voor aetherapp-dev

- [ ] Selecteer expliciet de testdatabase en voer migratie 0004 uit; controleer registratie en unieke index.
- [ ] Log in als participant en controleer eigen economie; een vreemd character blijft geweigerd.
- [ ] Stort in effecten, neem op en controleer bank-, effecten- en historieregels na herladen.
- [ ] Koop het laatste vrije testpercentage en controleer dat een tweede gelijktijdige poging wordt geweigerd.
- [ ] Verhoog en verlaag een aandeel; saldo en beschikbaar percentage blijven gelijk na herladen.
- [ ] Log in als director en administrator en controleer banktransfer, snapshot, goedkeuring en companywaarde.
- [ ] Dubbelklik één financiële actie: één mutatie en één historisch resultaat.
- [ ] Simuleer een verloren response en herhaal met dezelfde request-ID: dezelfde response, geen tweede mutatie.
- [ ] Stuur dezelfde key met gewijzigde payload: HTTP 409 en geen write.
- [ ] Controleer ontbrekende/foute CSRF en ontbrekende request-ID: weigering zonder mutatie.
- [ ] Controleer een foutpad: generieke melding zonder SQL-, PDO- of tabelnaam.
- [ ] Controleer vanuit een reeds vóór deployment geopend tabblad dat VERSION een herlaadmelding geeft; zonder nieuwe JS weigert de backend een financiële write begrijpelijk wegens ontbrekende request-ID.

## Runtime-uploadlijst

Nieuwe bestanden:

- `api/shared/decimal.php`
- `api/shared/idempotency.php`
- `api/characters/characterFinanceRepository.php`
- `api/characters/characterFinanceService.php`
- `api/characters/characterShareRepository.php`
- `api/characters/characterShareService.php`
- `api/companies/companyFinanceSchemas.php`

Gewijzigde PHP-runtimebestanden:

- `api/shared/validation.php`
- `api/characters/characterSchemas.php`
- `api/characters/characterRepository.php`
- `api/characters/characterService.php`
- `api/characters/updateCharacter.php`
- `api/characters/saveBankTransfer.php`
- `api/characters/deleteBankTransaction.php`
- `api/characters/buyCompanyShare.php`
- `api/characters/saveCompanyShare.php`
- `api/characters/saveCharacterEconomySnapshot.php`
- `api/characters/deleteCharacterEconomySnapshot.php`
- `api/characters/saveCharacterSecuritiesPortfolio.php`
- `api/companies/companyUtils.php`
- `api/companies/updateCompany.php`
- `api/companies/saveCompanySnapshot.php`

Gewijzigde browserbestanden:

- `js/mainFunctions.js`
- `js/apiCharacter.js`
- `js/economyCharacter.js`
- `js/companyFunctions.js`
- `js/navCharacter.js`

SQL voor handmatige uitvoering:

- `sql/migrations/run_0004_finance_idempotency_phpmyadmin.sql`

De losse preflight/apply/verify/rollbackbestanden en documentatie horen bij de releaseback-up, maar hoeven niet publiek uitvoerbaar te zijn. `.gitignore` laat voortaan alleen SQL onder `sql/migrations/` toe in versiebeheer; database-exports en andere SQL-bestanden blijven genegeerd.

Test- en documentatiebestanden:

- `tests/character_finance_endpoints_test.php`
- `tests/character_finance_services_test.php`
- `tests/character_finance_mariadb_concurrency_test.php`
- bijgewerkte route-, validatie-, updateCharacter- en migratieregressietests
- `docs/character-economy-finance-refactor.md`
- `docs/database-migrations-concurrency.md`
- `sql/migrations/README.md`

## Onderhouds- en uploadvolgorde

1. Maak een volledige database-export en bestandsback-up. Noteer de huidige `VERSION`.
2. Activeer onderhoudsmodus voor alle financiële writes.
3. Upload eerst de nieuwe, nog niet aangeroepen helpers, services, repositories en companyschema’s.
4. Upload daarna `mainFunctions.js`, `apiCharacter.js`, `economyCharacter.js`, `companyFunctions.js` en `navCharacter.js`.
5. Zet een nieuwe unieke `VERSION` klaar zodat bestaande tabs via de al aanwezige updatecontrole een herlaadmelding krijgen. Wacht minstens één controle-interval van 60 seconden. De melding herlaadt nooit automatisch bij mogelijk onopgeslagen invoer.
6. Voer in phpMyAdmin op de expliciet geselecteerde `aetherapp-dev`-database `run_0004_finance_idempotency_phpmyadmin.sql` uit en controleer de resultaten.
7. Upload de gewijzigde shared validator, character/company helpers en tot slot de endpoints. Gebruik bij FTP bij voorkeur een tijdelijke map en een server-side hernoeming; anders blijft onderhoudsmodus actief tot alle bestanden compleet zijn.
8. Voer de online checklist uit met testdata en controleer serverlogs op generieke foutpaden.
9. Schakel onderhoudsmodus uit.

Deze volgorde voorkomt dat nieuwe endpoints actief worden voordat tabel en helpers bestaan. Een oude tab die de VERSION-melding negeert, kan geen onbeschermde financiële mutatie uitvoeren: de nieuwe server weigert een ontbrekende `Idempotency-Key` met een begrijpelijke HTTP 400-response.

Rollback: zet eerst onderhoudsmodus aan, herstel alle genoemde PHP- en JavaScriptbestanden plus de vorige `VERSION`, en laat `tblApiIdempotency` voorlopig staan. Het laten staan is compatibel met oude code en bewaart retry/auditgegevens. Verwijder de tabel alleen na een expliciete auditbeslissing met het rollbackbestand; anders kan een oude retry opnieuw uitvoerbaar worden.

## Uitgevoerde controles

Uitgevoerd op 21 september 2026 met PHP 8.4.25:

- alle 22 aanwezige `tests/*_test.php`-bestanden zijn afzonderlijk gestart;
- 20 suites zijn geslaagd;
- `authenticated_user_test.php` is overgeslagen met exitcode 2 omdat PDO SQLite lokaal ontbreekt;
- `character_finance_mariadb_concurrency_test.php` is overgeslagen omdat geen expliciet toegestane wegwerp-MariaDB via de vereiste omgevingsvariabelen was ingesteld;
- de gerichte financiële endpointtest bewijst betaling, historie, rollback, idempotente replay, payloadconflict, ingetrokken replayrechten, auth, CSRF, validatie, standaarddatum en generieke HTTP 500;
- de gerichte servicetest bewijst effectenstorting/-opname, eigenaarschap en aandelen kopen/verkopen inclusief het laatste beschikbare procent;
- de migratietest voor 0001–0004 is geslaagd;
- PHP-syntaxcontrole is geslaagd voor alle 141 gevonden PHP-bestanden buiten `vendor`, `legacy` en `node_modules`;
- `git diff --check` is geslaagd;
- een moderne JavaScript-runtime (`node`, `deno`, `bun` of `qjs`) was lokaal niet beschikbaar. De gewijzigde frontendcontracten zijn wel door de statische financiële regressietest gecontroleerd.

Niet als geslaagd geclaimd: echte MariaDB-locking/deadlocks, MySQL/MariaDB-schema-integratie van migratie 0004, Apache/WordPress-sessie-integratie en een browsertest. Er is geen SQL tegen de online test- of productiedatabase uitgevoerd.
