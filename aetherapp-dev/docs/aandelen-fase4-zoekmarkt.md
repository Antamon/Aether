# Fase 4: Fiscaliteit-zoekmarkt

De fase-3-controle op `oneiros_beaetherdev` gaf `initializedHolders = 9`, `unresolvedHolders = 0` en `phaseMigrations = 5`. Dat is de verwachte toestand voor het starten van fase 4. Fase 4 gebruikt de al aanwezige tabellen uit migraties 0006–0010; er is geen nieuw SQL-bestand nodig.

## Gedrag

- De aandelenzoekactie gebruikt het event dat op de actiepagina geselecteerd is. Zonder selectie geldt het nieuwste event als standaard; historische eventdatums beperken de fictieve spelmarkt niet.
- De zoekactie vereist Fiscaliteit. Het niveau komt via `tblShareDomainCode` uit de vaardigheid en specialisatie Aandelenmarkt, maximaal niveau 4. Er is geen deelname aan het evenement vereist.
- Eén POST doorloopt de beursgenoteerde bedrijven met een positieve snapshotmarktprijs en vrije aandelen. Ieder bedrijf/snapshot krijgt zijn eigen cryptografische worp. De unieke assessmentrij verhindert een tweede worp bij heropenen; later toegevoegde kandidaten worden dan alsnog beoordeeld.
- Een vondst legt de prijs en het aangeboden aantal vast, maar reserveert geen vrije aandelen. De prijsaanpassing en het aanbod gelden ook voor precies één persoon met wie de vondst wordt gedeeld. Beide zien dezelfde `idOffer` en putten hetzelfde aangeboden aantal uit.
- De kooproute controleert het geselecteerde evenement, beursstatus, snapshot, aanbod, saldo en actuele vrije voorraad in één transactie. Betaling uit vrije aandelen verhoogt meteen `companyValue`; de transactie en waardewijziging worden gelogd en alle houders van het bedrijf worden opnieuw gewaardeerd.
- `priceRoll` in `tblShareSearchAssessment` bewaart de getrokken procentuele prijsaanpassing met een offset van 100. Dus 85 betekent −15%, 100 betekent 0% en 140 betekent +40%. Zo past een negatieve aanpassing in de bestaande unsigned kolom.

## Routes

Alle POST-routes eisen sessieauthenticatie, CSRF en een `Idempotency-Key` volgens de bestaande financiële fetchhelper. De inputs blijven taalneutraal.

| Route | Input | Doel |
| --- | --- | --- |
| `POST api/shares/searchShareMarket.php` | `idCharacter`, `idEvent` | Start of heropen de zoekactie voor het geselecteerde event. Geeft `idEvent` en `assessmentsAdded`. |
| `POST api/shares/shareSearchDiscovery.php` | `idOffer`, `idRecipientCharacter` | Deel één gevonden aanbod met één ander actief personage. |
| `GET api/shares/getCharacterShareSearch.php?idCharacter=…` | — | Actueel evenement, historische zoekoverzichten, vondsten en koopbare aanbiedingen. |
| `GET api/shares/getShareRecipientOptions.php?idCharacter=…` | — | Beperkte lijst actieve ontvangers voor het delen. |
| `GET api/shares/getShareMarketVersion.php?idCharacter=…` | — | Kleine hash van huidige marktvoorraad en eigen aanbiedingen voor polling. |
| `POST api/shares/buyOffer.php` | `idOffer`, `idCharacter`, `shareCount` | Koopt ook gevonden marktaanbiedingen. |

Bij openen van de Economie-actie door een personage met Fiscaliteit worden nieuwe snapshots voor het geselecteerde event automatisch beoordeeld. De actie toont de vondsten en laat één vondst met één ander actief personage delen. De Economie-tab toont aanbiedingen en laat een positief geheel aantal aandelen kopen. Beide gebruiken de versiecontrole elke 15 seconden, bij focus en na mutaties.

Na fase 2 staan `isShareListed` standaard uit en `shareMarketPrice` standaard op `NULL`. Een lege zoekmarkt is dus te verwachten tot een director/administrator voor minstens één bedrijf de beursstatus en de snapshotmarktprijs invult via de fase-3-routes.

## Nog online controleren

De SQL-uitkomst bevestigt de migratie, maar is geen endpointtest. Controleer op de dev-site met twee actieve personages: zoeken met en zonder Aandelenmarkt, dezelfde zoekactie heropenen zonder nieuwe worpen, een nieuw bedrijf/snapshot toevoegen en opnieuw zoeken, een vondst delen, één aandeel kopen en bij de ander de verminderde voorraad zien. Controleer ook dat een uitverkocht aandeel, verlopen evenement of onvoldoende saldo een nette 409 geeft en geen geld/aandelen wijzigt. Voor een betrouwbare gelijktijdigheidstest blijven twee onafhankelijke MariaDB-connecties nodig.

Deze werkplek heeft geen PHP-, JavaScript- of MariaDB-runtime op het pad. De wijzigingen zijn lokaal statisch nagekeken; voer PHP-lint, `tests/share_rules_test.php` en de online dev-smoketest uit vóór vrijgave aan spelers.
