# Fase 6: aandelenweergave en meertalige UI-basis

Deze fase voegt geen databasemigratie toe. Zij gebruikt de fase-3- tot en met fase-5-routes en de al uitgevoerde migraties 0006–0011 op de dev-databank.

## Personages

- Alle personages hebben de Economie-navigatietab tussen Personality en Acties. Daar staan inkomsten, bankverrichtingen, de effectenportefeuille, aankopen en de bestaande snapshots voor bevoegde gebruikers. De inhoud wordt bij openen van de tab geladen.
- Op de pagina Acties verschijnt daarnaast een Economie-actieknop voor personages met Fiscaliteit (vaardigheid-ID 6), in dezelfde tegelopmaak als Wereldwijs en Psi. Pas na een klik wordt de zoekmarkt geladen; zij toont gevonden en gedeelde aandelen met hun prijs. Aankopen gebeuren in de Economie-tab.
- De oude A/B-percentagekaarten en knoppen worden niet meer gerenderd. De portefeuille leest `getCharacterPortfolio.php` en toont het aantal aandelen per bedrijf, intrinsieke bezitswaarde, Aandelen-niveau, gebruikte statuspunten en personeelsvoorwaarde.
- Een actief personage met handelsrecht kan een gericht aanbod uit eigen bezit maken en een eigen open aanbod intrekken. Ontvangers komen uit `getShareRecipientOptions.php`; de server verifieert eigendom en rechten opnieuw.
- De optionele welvaartspagina van het paspoort haalt bij openen en bij PDF-export de actuele portefeuille op en vermeldt aantallen aandelen in plaats van oude percentages.
- De fase-4-zoekmarkt en aankoopweergave blijven de actuele aanbieding en vrije voorraad iedere 15 seconden, bij focus en na mutaties ophalen zolang de betreffende tab zichtbaar is. De portefeuillewaarde ververst periodiek wanneer de Economie-tab zichtbaar is.

## Bedrijven en leiding

- De bedrijfsweergave leest `getCompanyShares.php`: initiële, uitgegeven, verkochte, vrije en anonieme aandelen; nominale en intrinsieke waarde per aandeel; beursstatus; marktprijs per snapshot en bestuursleden.
- Alleen een `director` of `administrator` krijgt formulieren voor initiële parameters, actuele waarde, beursnotering, anonieme voorraad, intrekking van vrije aandelen, snapshotmarktprijs en bestuurslidstatus. Ieder formulier gebruikt een eigen klein endpoint en de gedeelde idempotente fetchhelper.
- Een aangemeld bestuurslid krijgt een privéaanbodvenster voor vrije aandelen, zowel op de bedrijfsbeheerpagina als op de bedrijfsleiderpagina. De koper betaalt bij aanvaarding aan het bedrijf; het formulier reserveert geen aandelen.
- Na een beheermutatie wordt de bedrijfsweergave opnieuw geladen, zodat het gewone bedrijfswaardeveld geen verouderde waarde terugschrijft.

## Taal en geld

De nieuwe aandelenlabels zijn sleutels met Nederlandse en Engelse tekst. De UI volgt de documenttaal (`lang`); bedragen worden pas bij het tonen geformatteerd. De browser verstuurt gehele aantallen en decimale strings met een punt. Komma-invoer wordt vóór verzending genormaliseerd. De server blijft leidend voor prijs, voorraad, rechten en status. Verwachte foutcodes krijgen een lokale melding; er wordt geen Nederlandse tekst als business key opgeslagen.

## Uitrol en controle

Upload het compatibele PHP-, HTML-, JS- en CSS-pakket samen met `api/shares/getCreatedShareOffers.php`. Voor deze correctie is geen SQL nodig. Controleer online als director en deelnemer: Economie-tab voor alle personages, zoekactie alleen bij Fiscaliteit, gevonden en gedeelde aandelen met prijs, bank en aankoop in de tab, en actuele vrije voorraad in een tweede sessie. De lokale werkplek heeft geen PHP-CLI of MariaDB-verbinding.
