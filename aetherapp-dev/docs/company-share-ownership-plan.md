# Plan: bedrijven als aandeelhouder

Voorstel van 4 november 2026. De eerste stap is uitgevoerd in versie 2026.11.04.1: een beheerder kan vanuit de aandeelhouders een moederbedrijf vrije aandelen toewijzen. Zie `company-share-ownership.md` voor de opgeleverde werking en migratie. Overdrachten, correctiemodals en de afzonderlijke bedrijfsportefeuille uit dit bredere plan zijn nog niet geïmplementeerd. De online databank is niet gewijzigd.

1. **Werking en afbakening**

   Een bedrijf kan aandelen van een ander bedrijf bezitten. Alleen een admin of director kan een aankoop, overdracht of correctie uitvoeren. Een deelnemer krijgt dit recht ook niet via werknemerschap, aandeelhouderschap of een bestuursmandaat.

   De eerste versie ondersteunt aankopen uit vrije aandelen en overdrachten tussen twee bedrijven die aandelen van hetzelfde derde bedrijf bezitten of willen bezitten. Overdrachten tussen een personage en een bedrijf vallen buiten deze eerste versie.

   Bij aankoop uit vrije aandelen neemt de vrije voorraad af en het bedrijfsbezit toe. Bij overdracht tussen bedrijven neemt het bezit van de verkoper af en dat van de koper met hetzelfde aantal toe. Het aantal uitgegeven aandelen verandert niet.

   De bedrijfswaarden van koper, verkoper en uitgevende onderneming blijven exact gelijk, ook als er geen snapshot bestaat. Er wordt geen bankrekening van een personage aangesproken. Aantal, prijs en totaalbedrag worden administratief geregistreerd; er komt hiervoor geen bedrijfsbankrekening bij. Ook snapshots, kwartaalwaarden, marktprijzen, stabiliteit en rendabiliteit worden niet gewijzigd.

   Een bedrijf kan geen eigen aandelen kopen via deze functie; de bestaande aandeleninkoop blijft een afzonderlijke actie. Wederzijdse deelnemingen zijn wel mogelijk. Er komt geen recursieve herwaardering: het aandelenbezit van een onderneming wordt niet automatisch opgeteld bij haar bedrijfswaarde.

2. **Bedrijfpagina en modal**

   In het tweede Aether panel komt een blok **Aandelenportefeuille** met de ondernemingen waarin dit bedrijf aandelen bezit. Het toont onderneming, aantal en percentage. Meerdere aankopen worden hierin samengeteld; de afzonderlijke transacties staan in een modal.

   Alleen admins en directors zien **Aandelen toevoegen**. In de modal kiezen ze het uitgevende bedrijf, aankoopjaar en kwartaal, en aantal. De beschikbare voorraad en prijs per aandeel staan bij de invoer, met het totaalbedrag voor het opslaan.

   Voor de aankoopvoorwaarden gebruiken we dezelfde periodekeuze als bij personages: het lopende of eerstvolgende event met een snapshot van het gekozen uitgevende bedrijf. Events zonder snapshot van dat bedrijf worden overgeslagen. Kwartalen zonder passende toekomstige snapshot worden niet aangeboden. Het aankoopkwartaal mag niet vóór de oprichting van het kopende of uitgevende bedrijf liggen. De prijs komt uit die snapshot; ontbreekt een bruikbare prijs, dan kan de aankoop niet worden opgeslagen.

   Bij bestaand bedrijfsbezit kan een beheerder de aankoopgeschiedenis openen en een correctie uitvoeren, of **Overdragen aan bedrijf** kiezen. Bij een overdracht kiest die het ontvangende bedrijf, kwartaal, aantal en overeengekomen prijs. De verkoper kan maximaal zijn werkelijk aanwezige bezit overdragen. Dit is een directe beheeractie, zonder aanbod- of acceptatiestap voor deelnemers.

   Alleen het bestaande kruisje in de rode cirkel sluit de modal. De bestaande tabel-, formulier- en button-group-opmaak wordt hergebruikt.

3. **Aandeelhouderstabel**

   De bestaande tabel bevat voortaan personages én bedrijven, op naam gesorteerd. De hoofding blijft **Naam**, **Aandelen** boven aantal en percentage, en een smalle ongelabelde navigatiekolom voor beheerders.

   Bij een personage blijft het losse klikbare persoonicoon verwijzen naar diens economietab. Bij een bedrijf staat op dezelfde plaats een los klikbaar **fabrieksicoon** (`fa-industry`) dat naar de bedrijfpagina van de aandeelhouder leidt.

   Deelnemers blijven een tabel met drie kolommen zien, zonder navigatieiconen of beheerknoppen. Hun toegang tot bedrijven blijft gebaseerd op hun eigen directe aandelenbezit of werknemerschap. Werken voor bedrijf A dat aandelen in B bezit, geeft dus geen automatische toegang tot B en geen transactierechten.

4. **Databank**

   Een nieuwe migratie, voorlopig **0023_company_share_ownership**, voegt het volgende toe. Het nummer wordt bij uitvoering gecontroleerd op beschikbaarheid.

   - `tblCompanyShareCompanyHolding`: het uitgevende bedrijf, het bezittende bedrijf en een positief aantal aandelen. Een unieke combinatie van beide bedrijfs-ID's voorkomt dubbele totalen. Beide verwijzen met foreign keys naar `tblCompany`; zelfbezit wordt geweigerd. Historisch gebruikte bedrijven worden niet met cascadeverwijdering gewist.
   - Bedrijfsvelden in `tblCompanyShareTransaction`: een kopend/bezittend bedrijfs-ID en een tegenpartij-bedrijfs-ID. Het bestaande `idCompany` blijft altijd het uitgevende bedrijf. Personage-ID's blijven voor deze transacties leeg. Nieuwe operation codes onderscheiden aankoop, overdracht en correctie.
   - Correcties verwijzen naar de oorspronkelijke transactie, bewaren voor/na-gegevens en gebruiken een revisienummer. Kwartaal en gekozen snapshot worden vastgelegd volgens de bestaande aankoopgeschiedenis. Originele transacties blijven bewaard.
   - Een afzonderlijk dividendregister voor bedrijfsontvangers legt snapshot, ontvangend bedrijf, aantal aandelen en toegekend bedrag vast. Een eventuele terugname wordt eveneens geregistreerd.

   Bestaande personagebezitten en aankoopgeschiedenissen blijven intact. Er wordt geen bedrijfsbezit afgeleid uit oude gegevens. De migratie wordt herhaalbaar gemaakt en afzonderlijk op beide databases uitgevoerd voordat de nieuwe routes beschikbaar worden.

5. **Voorraad en waarderingen**

   Overal geldt voortaan:

   `vrij = uitgegeven - bezit personages - bezit bedrijven - aandelen in naamloze handen`

   `verkocht = bezit personages + bezit bedrijven + aandelen in naamloze handen`

   Het percentage van elke aandeelhouder blijft diens aantal gedeeld door alle uitgegeven aandelen. Bezit van bedrijven krijgt geen personagestatus, statuspunten of personeelsvoorwaarde.

   De berekening wordt op één gedeelde voorraadbron gebaseerd. Niet alleen de aandeelhouderstabel wordt aangepast, maar ook aanbiedingen, zoekresultaten, Draft-aankopen, beheeraankopen, inkoop van vrije aandelen en controles bij snapshotwijzigingen. Een daling van het uitgegeven aantal of verhoging van naamloze aandelen mag bedrijfsbezit niet overlappen.

   De huidige functie `aetherShareValueAfterPurchase()` heeft een terugval die zonder vastgelegde waardering de bedrijfswaarde kan veranderen. Bedrijfstransacties mogen die terugval niet gebruiken. Hun audit bewaart voor alle betrokken bedrijven gelijke waarden vóór en na de transactie.

6. **Dividend zonder onbedoelde herverdeling**

   De huidige dividendverdeling kent alleen personages en naamloze aandelen. Bedrijfsbezit moet als afzonderlijke ontvanger meetellen volgens dezelfde verdeelregels en afronding. Bedrijfs- en personage-ID's krijgen daarbij verschillende ontvangerssleutels, zodat gelijke numerieke ID's niet samenvallen.

   Voorstel voor deze eerste versie: het dividenddeel van een bedrijf wordt administratief geregistreerd en zichtbaar in zijn portefeuillehistoriek. Het wordt niet automatisch aan een personage of aan de bedrijfswaarde toegevoegd. De bestaande verwerking voor personages blijft behouden; het bedrijfsdeel telt als afzonderlijk toegewezen bedrag, zodat het niet daarnaast als onverdeeld restant wordt verwerkt.

   Reeds verwerkte historische dividenden worden niet opnieuw verdeeld door een aankoop of correctie. Verwijderen of terugnemen van een snapshot verwerkt ook de bijbehorende bedrijfsdividendregistratie. Een toekomstige bedrijfsbankrekening of automatische verwerking in de bedrijfswaarde is een afzonderlijke uitbreiding.

7. **Serververwerking en gelijktijdigheid**

   Nieuwe routes voor bedrijfsportefeuille, aankoop, overdracht en correctie krijgen een eigen service boven de bestaande aandeleninfrastructuur. Iedere schrijfroute controleert expliciet admin/director, ook bij herhaling van een al verwerkte aanvraag. Bestuursrechten zijn hier nooit voldoende.

   Alle betrokken bedrijven worden in oplopende ID-volgorde vergrendeld, daarna de betrokken bezitten. De bestaande mutatiehulp vergrendelt nu één bedrijf; die wordt uitgebreid met expliciete ondersteuning voor meerdere bedrijven. Alle relevante mutatieroutes moeten dezelfde lockvolgorde behouden.

   Voorraad, bezit en audit worden in één idempotente database-transactie opgeslagen. Er komen controles op voldoende vrije voorraad of verkopersbezit, positieve gehele aantallen, geldige bedragen, bestaande bedrijven, oprichtingskwartalen en verouderde revisies. Dubbel klikken mag geen dubbele aankoop opleveren. Een mislukte aanvraag laat geen gedeeltelijke overdracht achter.

   Een correctie die meer aandelen terugneemt dan nog bij de oorspronkelijke koper aanwezig zijn, wordt geweigerd. Latere overdrachten worden niet stilzwijgend herschreven. Een aantalscorrectie uit vrije voorraad past dezelfde voorraadcontroles toe als een nieuwe aankoop.

8. **Uitvoeringsvolgorde**

   Eerst de migratie en gedeelde voorraadberekening, daarna de beheertransacties en leesroutes. Vervolgens de gemengde aandeelhouderstabel en bedrijfsportefeuille met modals. Dividendverdeling en terugname worden aangepast voordat bedrijfsbezit in gebruik wordt genomen. Tot slot volgen rechtencontroles, regressietests en de bestaande assetversies.

   Belangrijkste bestanden: `api/shares/shareRepository.php`, `shareService.php`, `sharePurchaseHistory.php`, `shareDividendRules.php`, de nieuwe bedrijfsbezitservice/routes, `api/companies/companySnapshotService.php`, overige voorraadqueries in `shareSearchService.php` en `api/characters/economyUtils.php`, plus `js/shareInterface.js`, `js/companyFunctions.js` en `companies.html`.

9. **Acceptatie en controles**

   - Bij 100 uitgegeven aandelen, 24 bij een personage, 10 bij een bedrijf en 50 in naamloze handen toont de app 84 verkocht en 16 vrij. Het bedrijf staat voor 10% in de tabel, met fabrieksicoon voor beheerders.
   - Koopt het bedrijf nog 5 vrije aandelen, dan wordt dat bezit 15 en de vrije voorraad 11. Alle betrokken bedrijfswaarden, snapshots en kwartaalpunten blijven exact gelijk, ook zonder vastgelegde bedrijfswaardering.
   - Draagt het bedrijf 4 aandelen over aan een ander bedrijf, dan staan er 11 en 4 bij de bedrijven. De vrije voorraad en het totale verkochte aantal blijven gelijk.
   - Twee gelijktijdige aankopen kunnen samen de vrije voorraad niet overschrijden. Herhaling met dezelfde aanvraag-ID maakt geen extra bezit of audittransactie.
   - Een deelnemer, ook een bestuurslid, krijgt bij een rechtstreeks API-verzoek een weigering en ziet geen knoppen voor bedrijfstransacties.
   - Fabrieks- en persooniconen verwijzen naar het juiste type pagina; deelnemers krijgen geen extra indirecte toegang tot bedrijven.
   - Dividend voor bedrijfsbezit wordt afzonderlijk toegewezen, zonder verhoging van personagedividend door een ontbrekende bedrijfsontvanger. Gelijke bedrijfs- en personage-ID's blijven gescheiden. Een snapshotterugname verwerkt beide soorten ontvangers correct.
   - Bestaande personageaankopen, correcties, zoekacties, snapshots en vrije-aandelenaanbiedingen blijven werken. Tests controleren expliciet dat de nieuwe bedrijfsacties geen bedrijfswaarde of personagebank wijzigen.
