# Aandelenhervorming — uitgevoerde fase 1

## Bereik en bewijswaarde

Deze fase inventariseert de actieve routes, hun huidige contracten en de omzetting van procenten naar aantallen. De runtimecode staat op dit moment in `../aetherapp`, de documentatie en SQL in `aetherapp-dev`. Er is **geen** applicatiecode, bedrijf of financiële rij gewijzigd.

De recentste lokaal beschikbare ontwikkel-export is `C:\Users\jan_m\Downloads\oneiros_beaetherdev.sql` van **24 september 2026 09:08**, SHA-256 `E08EEA9F90EDE118E23082BC34F278CD37F61298FF2A593475EBAEAB0921DD2F`. De productie-export is `oneiros_be_aether.sql` van **24 september 2026 09:09**, SHA-256 `D013CCF8939AC8A527912A9C7EE007AC32D094B5B32675A8A468278F4C2F6C6C`. De aandelencijfers hieronder zijn uit de SQL-INSERT-rijen van deze exports afgeleid; zij zijn **geen actuele online database-uitkomst**. Lokaal is geen werkende PHP/MariaDB-CLI of MariaDB-service beschikbaar en de browserinventaris bevat geen ingelogde databasetab. Daarom kon ik de volledige live read-only preflight niet zelf uitvoeren. Het daarvoor gereedstaande bestand is [`inspect_share_reform_phase1_readonly.sql`](../sql/migrations/inspect_share_reform_phase1_readonly.sql); het bevat uitsluitend `SELECT`- en `SHOW`-statements.

**Online bevestigd door de gebruiker op 30 september 2026:** `SELECT DATABASE()` gaf `oneiros_beaetherdev`. Een apart, volledig gekwalificeerd `SELECT` op `oneiros_beaetherdev.tblSchemaMigration` gaf precies de registraties `0001_create_schema_migration_registry`, `0002_unique_character_skill`, `0003_unique_character_specialisation`, `0004_create_api_idempotency` en `0005_unique_event_user`. Dit bevestigt de huidige dev-migratieregistratie, niet de huidige aandelenvoorraad of productieomgeving. Voor de resterende actuele aandeelcontrole is ook een [compacte read-only querybundel](../sql/migrations/inspect_share_reform_phase1_dev_summary_readonly.sql) met vier resultaatsets voorbereid.

**Online aandelencijfers eveneens bevestigd op 30 september 2026:** alle 13 bedrijven staan in de actuele dev-database. De vijf bedrijven met gekoppelde belangen hebben dezelfde verkochte/vrije aantallen als de historische export: 10/90, 32/68, 12/88, 95/5 en 1/99. De controle op blokkerende fouten gaf nul rijen; de negen portefeuillewaarden zijn eveneens ongewijzigd. De losse aandeeltraits zijn nu **12** in plaats van 13: `11, 95, 235, 236, 246, 247, 248, 266, 336, 429, 430, 453`. Traitlink `475` uit de export ontbreekt online. De huidige waarde van bedrijf 2 (Rheinmetall) is `119423698.89` en van bedrijf 6 (Bekaert) `25783300.00`; deze waarden moeten bij uitvoering uit de database worden gelezen, niet uit de export worden gekopieerd. De vier compacte SELECT-resultaten tonen niet alle indexen, kolommen, andere datumrecords of de toestand van een afzonderlijke testkopie.

## Actieve routes en huidige contracten

| Frontend en route | Huidige handeling, rechten en response | Te vervangen of te bewaken gedrag |
| --- | --- | --- |
| `js/traitsCharacter.js` → `api/characters/updateTrait.php` | In draft aandeeltrait toevoegen, wijzigen, rang verhogen/verlagen of verwijderen. Eigen player-draft of `director`/`administrator`; authenticatie en CSRF. Response `{"status":"ok"}` of `{"error":...}`. | A/B-statuskosten en rangstappen van 10%; deze route kan aandelenlinks wijzigen en moet bij omschakeling geen oude procenten meer schrijven. Geen financiële idempotentiesleutel op deze traitroute. |
| `js/economyCharacter.js` → `api/characters/buyCompanyShare.php` | `idCharacter`, `idCompany`, `shareClass` (`A`/`B`). Eigen player buiten draft of bevoegde `director`/`administrator`; authenticatie, CSRF en idempotentie. Response `success`, `idLinkCharacterTrait`, numerieke `unitPrice`. | Koopt precies 1% tegen `companyValue / 100` uit bankaccount; bedrijf krijgt in deze route geen waardeverhoging of aparte koopgeschiedenis. |
| `js/economyCharacter.js` → `api/characters/saveCompanyShare.php` | `assign_company`, `clear_company`, `increase_rank`, `decrease_rank`; traitlink-ID en eventueel bedrijfs-ID. Eigen player of bevoegde beheerder, buiten draft voor verhogen/verlagen; CSRF en idempotentie. Responses `success`, bij verhoging numerieke `nextPercentageCost`, bij verlaging numerieke `saleValue`. | Koppelt A/B-trait aan bedrijf, verhoogt/verlaagt 1%; verlaging crediteert bank zonder geregistreerde tegenpartij of corresponderende `companyValue`-afschrijving. |
| `js/apiCharacter.js` → `api/characters/getCharacter.php` | Leest na objecttoegang het character-readmodel, inclusief `companyShares`, `companySharePurchaseOptions`, statuspunten en economie. | `economyUtils.php` berekent en toont procenten, prijs `companyValue / 100` en oude A/B-kosten. |
| `js/companyFunctions.js` → `api/companies/getCompany.php` | Alleen `director`/`administrator`; readmodel met `shareholderGroups`, `availableSharePercentage` en snapshots. | A/B-groepen en procenten zijn de huidige bedrijfspresentatie. |
| `js/companyFunctions.js` → `api/companies/updateCompany.php` | Alleen `director`/`administrator`, CSRF en idempotentie; kan `companyValue` direct zetten, response `status: ok`, `updatedShareTraitCount`. | Een waardeverandering remapt nu eventueel A/B-traits; later moet deze route alle betrokken portefeuilles/statusrechten herwaarderen en de wijziging loggen. |
| `js/companyFunctions.js` → `api/companies/saveCompanySnapshot.php` | Alleen `director`/`administrator`, CSRF en idempotentie. `create`, `update`, `recalculate`, `apply` of `delete`; response met company, voorraadpercentage en snapshots. | Afrekeningen kunnen `companyValue` wijzigen en dividend op oude percentages betalen. De nieuwe waarderings- en dividendregels moeten hier worden aangesloten. |
| `js/companyFunctions.js` → `api/companies/saveCompanyPersonnel.php` | Alleen `director`/`administrator`, CSRF en idempotentie; response met personeel en snapshots. | Vervangt de volledige personeelslijst; raakt bedrijfssnapshotberekeningen. Nieuwe bestuursrechten mogen niet op deze brede write leunen. |

Andere betrokken schrijfroutes: `api/characters/updateCharacter.php` kan `bankaccount`, `securitiesaccount` en status wijzigen (directe saldo's alleen door `director`/`administrator` buiten draft; bij vertrek uit draft wordt bankaccount uit inkomen berekend). `api/characters/saveBankTransfer.php` en de overige financiële routes wijzigen hetzelfde banksaldo; `companySnapshotService.php` schrijft dividend naar `tblCharacter.bankaccount`. De nieuwe aandeelkoop moet met hun lock- en transactievolgorde samenwerken. Characterverwijdering ruimt oude trait-/bedrijfslinks op. `tblApiIdempotency` beschermt de huidige financiële writes, maar is geen volwaardig aandelen- of bedrijfswaardelog.

De centrale auth leest de actuele rol uit `tblUser`: `participant`, `director`, `administrator`. Volgens de verduidelijking is *storyteller* de bestaande rol `director`. Er is nu geen bestuurslidvlag in `tblCompanyPersonnel` en de companyroutes zijn niet toegankelijk door enkel aandeelhouder of personeelslid te zijn. De huidige sharekosten/voorraad zijn gehele percentages tot samen 100; geldkolommen zijn `DECIMAL(12,2)`, terwijl enkele presentatie- en inkomensberekeningen floats gebruiken. Fiscaliteit is in de export `tblSkill.id=6`; specialisatie `55` heet **Investering**, niet Aandelenmarkt. De nieuwe Aandelenmarkt-specialisatie mag ID 55 dus niet stilzwijgend overnemen.

## Historische ontwikkel-export: omzettingsinventaris

De export bevat 13 bedrijven, 82 personages, 20 A/B-aandeeltraitdefinities, 23 gekoppelde aandeeltraits op personages en 14 rijen in `tblLinkCharacterTraitCompany`. Tien aandeeltraits hebben een geldige bedrijfslink, verdeeld over negen personages en vijf bedrijven. Bij de afgesproken migratiestart (`initialCompanyValue = companyValue`, 100 uitgegeven aandelen) wordt elk procentpunt één aandeel.

| Bedrijf-ID | Bedrijf | `companyValue` in export | Gekoppelde regels | Verkocht → aandelen | Vrij bij start |
| ---: | --- | ---: | ---: | ---: | ---: |
| 1 | Janssens Chemicaliën | Fr 812.500,00 | 1 | 10 | 90 |
| 3 | Black Steel | Fr 120.000,00 | 3 | 32 | 68 |
| 4 | Seraphim | Fr 1.051.000,00 | 1 | 12 | 88 |
| 5 | Abeir Produkties | Fr 421.000,00 | 4 | 95 | 5 |
| 7 | FN Herstal | Fr 2.500.000,00 | 1 | 1 | 99 |

De overige acht bedrijven hebben in deze export geen gekoppelde aandeelhouders en starten volgens deze afspraak elk met 100 vrije aandelen. Bedrijf 13 heeft `companyValue = 0,00`: dat is geen reden om het te schrappen, maar een eventuele koop vereist later een expliciete geldige marktprijs. Geen van de vijf bezette bedrijven is boven 100% toegewezen. De gekoppelde regels hebben allemaal een positief integer percentage en een bestaand personage/bedrijf. Eén aandeelhouder (personage 52) bezit belangen in twee bedrijven; personage 62 heeft 55 aandelen van bedrijf 5 en staat `inactive`. De tien regels leveren de volgende portefeuilles op:

| Personage-ID | Totaal aandelen | Indicatieve intrinsieke bezitswaarde bij 100 aandelen/bedrijf |
| ---: | ---: | ---: |
| 15 | 12 | Fr 126.120,00 |
| 30 | 1 | Fr 1.200,00 |
| 32 | 1 | Fr 25.000,00 |
| 33 | 30 | Fr 36.000,00 |
| 40 | 1 | Fr 1.200,00 |
| 51 | 10 | Fr 42.100,00 |
| 52 | 20 | Fr 123.350,00 |
| 62 | 55 | Fr 231.550,00 |
| 63 | 20 | Fr 84.200,00 |

Alle negen waarden liggen **onder Fr 600.000**, de eerste niveaugrens van de nieuwe Aandelen-status. De aandelen blijven bezit; welke status/hoeveel statuspunten onder die eerste grens gelden, moet vóór de omzetting expliciet vaststaan. De waarden hierboven zijn inventariswaarden, geen historische transacties of nieuw banksaldo.

### Aandeeltraits zonder bedrijf

Dertien aandeeltraitlinks hebben geen bruikbare bedrijfskoppeling. Negen hebben helemaal geen rij in `tblLinkCharacterTraitCompany`: `11, 95, 235, 236, 246, 247, 248, 266, 475`. Vier hebben daar wel een rij met `idCompany = NULL`: traitlinks `336, 429, 430, 453`, bijbehorende linkrijen `149, 187, 191, 194`. Volgens de gebruikersbeslissing worden deze aandeeltraits **tijdens de latere migratie** verwijderd, na een verse telling en een back-up van de exacte rijen. Ze zijn nu niet verwijderd. De huidige export toont geen bedrijfslink naar een ontbrekend bedrijf.

De export toont vier bedrijfssnapshots voor drie bedrijven (2, 4, 5), alle gekoppeld aan events. Geen bestaande tabel bevat een snapshotmarktprijs, nieuw aandeelvoorraadmodel of bestuurslidvlag. De ontwikkel-export registreert migraties 0001 t/m 0005. De **even oude productie-export** bevat 12 bedrijven, 24 aandeeltraitlinks, 10 geldig gekoppelde regels en 14 zonder bedrijf; daarin ontbreekt `tblSchemaMigration`. Die verschillen mogen niet naar de huidige productie worden geëxtrapoleerd: daarvoor is een nieuwe read-only preflight nodig.

## Concrete acceptatiegevallen uit de vastgelegde regels

1. **Correctie van het startaantal:** bedrijf 5 heeft bij start 100 uitgegeven, 95 verkocht en 5 vrij. Correctie van initieel aantal naar 1.000 laat de bestaande 95 aandelen bij hun houders, maakt 905 aandelen vrij en wijzigt de intrinsieke waarde per aandeel van Fr 4.210 naar Fr 421 bij gelijkblijvende `companyValue`. Alle betrokken statuswaarden worden herberekend. Correctie van de initiële oprichtingswaarde op zichzelf raakt de actuele `companyValue` niet.
2. **Vrije aandelen kopen:** bij aankoop van `n` vrije aandelen voor totaalbedrag `P` dalen het kopersbanksaldo en de vrije voorraad, stijgen diens aandeelbezit en `companyValue` met `P`, en wordt de wijziging geaudit. De marktprijs blijft de beheerde snapshotprijs. Geen voorraad wordt door een vondst gereserveerd.
3. **Bedrijfsinkoop:** bij bedrijf 5 en vijf vrije aandelen à de huidige intrinsieke Fr 4.210 daalt de bedrijfswaarde volgens de afgesproken regel van Fr 421.000 naar Fr 399.950 en het uitgegeven aantal van 100 naar 95. De intrinsieke waarde blijft Fr 4.210 per aandeel; de marktprijs blijft onaangeroerd. Een echte implementatie moet centafronding exact vastleggen.
4. **Dividend:** bij bedrijf 5 is de noemer in deze export 95 verkochte aandelen. Personage 62 bezit 55 aandelen en ontvangt dus `55/95` van het volgens de bestaande winstregel uit te keren dividendbedrag, met een gedocumenteerde centafronding. Vrije aandelen staan niet in die noemer.
5. **Nieuwe snapshot tijdens event:** de eerste zoekactie legt per op dat moment aanwezig bedrijf/snapshot één dobbeluitkomst vast. Een later toegevoegd bedrijf of snapshot wordt bij heropenen eenmaal beoordeeld; eerdere uitkomsten veranderen niet. Deelname aan het event is niet vereist. De open pagina haalt gewijzigde vrije voorraad automatisch opnieuw op; aankoop verifieert voorraad binnen de transactie.
6. **Drempelverandering:** handmatige waardeaanpassing, aankoop uit vrije voorraad, winst/verlies of correctie van uitgegeven aantal herwaardeert het bezit van alle aandeelhouders en bijgevolg hun personeelsvoorwaarde en gebruikte statuspunten, ook bij een daling. Een loutere marktprijsaanpassing doet dat niet.

## Resultaat en poort naar fase 2

De statische code- en exportinventaris is uitgevoerd en de actuele dev-migratieregistratie is door screenshots bevestigd. [`inspect_share_reform_phase1_readonly.sql`](../sql/migrations/inspect_share_reform_phase1_readonly.sql) is gereed voor de resterende **actuele** read-only controle; de 33 statements zijn statisch gecontroleerd: 20 `SELECT` en 13 `SHOW`. In de eerste online poging noemde phpMyAdmin bij een ongekwalificeerde registratiequery `information_schema` als context; de afzonderlijke volledig gekwalificeerde query bewees vervolgens dat de registratietabel in de dev-database bestaat. De herziening gebruikt geen `information_schema` meer en bevat geen onvoorwaardelijke registratiequery. Er is niets op een online database gewijzigd. Voor een betrouwbare migratie op de testomgeving zijn nog nodig: de actuele aandelenresultaten van de herziening of een verse export, vergelijking van de 13 historische verwijderkandidaten met de huidige rijen, en de spelregel voor bezit onder de eerste statusgrens. Daadwerkelijke dataconversie mag niet op de oude export worden gebaseerd.
