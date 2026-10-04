# Aandelenhervorming — fase 3 in de ontwikkelmap

## Databasestap en activering

De gebruiker heeft met een read-only query bevestigd dat migraties 0006–0008 en de verwachte 13 bedrijven, tien bezitrijen, negen aandeelhouders, 150 bezeten aandelen, twaalf gearchiveerde losse traits en twee domeincodes aanwezig zijn op `oneiros_beaetherdev`.

Voer vóór de nieuwe endpoints worden gebruikt [run_0009_to_0010_share_phase3_phpmyadmin.sql](../sql/migrations/run_0009_to_0010_share_phase3_phpmyadmin.sql) op **alleen de dev-database** uit. Het bestand stopt bij afwijkende broncijfers, zet de negen historische houders onder Fr 600.000 op niveau 0, nul statuspunten en nul personeel, en voegt het afzonderlijke type `holder_sale` toe aan aanbiedingen. Het laatste SELECT-resultaat moet `initializedHolders = 9`, `unresolvedHolders = 0` en `phaseMigrations = 5` tonen. De nieuwe routes eisen registraties 0006–0010. Bestaande procentuele aandelenwrites zijn sinds 0007 afgesloten.

## Transacties en rechten

`api/shares` bevat gerichte endpoints voor de bedrijfsvoorraad, individuele portefeuille en aanbiedingen. Een bestuurslid kan vanuit de vrije bedrijfsvoorraad een privéaanbod voor één ontvanger maken; de betaling verhoogt de bedrijfswaarde. Een bestaande aandeelhouder kan zijn eigen aandelen gericht aan één ontvanger aanbieden; bij aanvaarding gaat de betaling naar de bankrekening van de verkoper en blijft de bedrijfswaarde gelijk. Alleen de ontvanger kan de aankoop bevestigen. Beide soorten aanbod reserveren geen voorraad: de GET-lijst en de kooptransactie begrenzen het aantal op respectievelijk de vrije bedrijfsvoorraad of het actuele bezit van de verkoper. Het aankoopendpoint controleert ontvanger, beschikbaarheid, saldo, bedrijfslimiet en rechten opnieuw in een transactie.

Het pad voor verkoop tussen bestaande aandeelhouders is voorlopig op deze expliciete verkoper/koper-geldstroom gebaseerd. De hierover gestelde verduidelijkingsvraag staat nog open; wijzig het schema of zet dit pad niet vrij als de gebruiker een andere verkoopregel kiest.

Directors en administrators krijgen aparte endpoints voor initiële waarde/aantal, actuele waarde, anonieme voorraad, inkoop van vrije aandelen, beursstatus, snapshotmarktprijs en bestuursvlag. Een correctie van 100 naar 1.000 initiële aandelen verandert een bestaande houder van één aandeel niet; de vrije voorraad groeit met 900. De actuele waarde verandert alleen via de waardeactie, aankoop of inkoop. Alleen een boardlid van het betrokken bedrijf en eigen personage kan een privéaanbod doen; de algemene companyroutes blijven voor directors/admins.

De mutaties gebruiken de bestaande CSRF- en idempotentiecontrole. Ze vergrendelen het betrokken bedrijf, daarna de relevante personages in ID-volgorde en daarna aanbod-/bezitrijen. `READ COMMITTED` voorkomt dat een herberekening na wachten op een personagelock een oudere portefeuille leest. Auditregels bewaren aandeeltransacties en waardeaanpassingen. Na waarde- of aantalswijziging worden alle aandeelhouders van het betrokken bedrijf opnieuw gewaardeerd, inclusief hun bezit bij andere bedrijven. Oude A/B-aandeeltraits blijven als migratiebron bestaan maar tellen niet dubbel mee voor statuspunten.

De PHP-sessielock wordt na authenticatie en CSRF-validatie vrijgegeven, vóór het wachten op databasevergrendelingen. Daarmee kunnen twee requests van dezelfde ingelogde gebruiker daadwerkelijk tegelijk de voorraadconcurrentie testen.

De bestaande routes voor procentuele aankoop en traitwijziging weigeren aandeelwrites; een oude company-update kan de actuele waarde niet meer aanpassen. Een ongewijzigde waarde in een oud formulier wordt genegeerd, zodat andere bedrijfsvelden bewerkbaar blijven. Snapshotafrekening (`apply` en `delete`) is tijdelijk afgesloten tot de nieuwe dividend- en waardeboeking in fase 5 gereed is. De oude personeelsroute bewaart de bestuursvlag voor leden die in de lijst blijven.

Nieuwe foutresponses bevatten naast een Nederlandse fallbacktekst een stabiele `errorCode`-sleutel (`shares.forbidden`, `shares.conflict`, enzovoort), zodat de meertalige UI de tekst later zelf kan vertalen. De API wisselt geld als decimale strings en aandelen als gehele aantallen uit.

## Nog niet vrijgegeven

Publieke Fiscaliteit-zoekresultaten, delen, eventselectie en live voorraadsignalering horen bij fase 4; de huidige koopservice accepteert daarom alleen gerichte aanbiedingen. Draftbudget en afsluiting bij statusovergang, de nieuwe dividendafrekening en de volledige UI worden in de volgende fasen geïntegreerd. Zet deze code niet op productie of open voor spelers voordat de ontbrekende paden en een MariaDB-concurrentietest zijn uitgevoerd.

## Lokale controle

`tests/share_rules_test.php` dekt drempels, niveau 0, de voorraadcorrectie, inkoop en centbedragen. In deze werkplek ontbreken PHP en MariaDB als uitvoerbare programma's, dus PHP-syntax, endpointtests en twee gelijktijdige MariaDB-connecties moeten in een omgeving met die programma's draaien. `git diff --check` is lokaal uitgevoerd.
