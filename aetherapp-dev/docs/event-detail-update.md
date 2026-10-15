# Eventpagina: afbeeldingen en personages

## Database

Voer `sql/migrations/0014_event_character_links.sql` uit op `oneiros_be_aether`
en `oneiros_beaetherdev`, voordat de nieuwe eventdetail-API wordt gebruikt.
Het script maakt een aparte koppeling tussen events en personages en neemt
bestaande diaryvermeldingen eenmalig over. De migratieregistratie voorkomt
dat later bewust verwijderde koppelingen bij herhaling weer worden toegevoegd.
Verwijdering van een event of personage ruimt de koppeling via de foreign keys op.

Het script wijzigt geen deelnames, diaryteksten of bestaande eventgegevens.
Deelname van een gebruiker en koppeling van een personage blijven aparte gegevens.

## Bestanden voor upload

- `api/events/eventService.php`
- `api/events/eventMediaService.php` (nieuw)
- `api/events/eventDetailService.php` (nieuw)
- `api/events/eventDetails.php` (nieuw)
- `js/eventFunctions.js`
- `js/eventDetails.js` (nieuw)
- `js/aetherSidebars.js`
- `css/style.css`
- `eventParticipation.html`, `index.html`, `companies.html`, `admin.html`,
  `leaderPersonnel.html` en `static.html` (inclusief nieuwe cacheversies).

Afbeeldingen worden door de API in `img/events/` opgeslagen. Die map wordt bij
de eerste upload aangemaakt en moet schrijfbaar zijn voor PHP. De server moet
GD en Fileinfo ondersteunen. PNG, JPEG, GIF en WebP worden gecontroleerd en
opnieuw opgeslagen als een liggende PNG van 4:3, met maximaal 1200px breedte.
Er geldt een limiet van 10 MB en 20 megapixels voor het oorspronkelijke bestand.

## Bediening

In de eventsidebar opent de deelnemerskeuze de deelnameweergave. Hier kan een
admin/director deelnames aanpassen, maar geen eventgegevens. Klik op de
afbeelding of naam van een event voor de detailweergave. Daar zijn de gegevens
via Edit, Save en Cancel te wijzigen en zijn de twee groepen personages apart
te koppelen. Een koppeling wijzigt geen diary of gebruikersdeelname.

De deelnemerskeuze is voor admins/directors ook beschikbaar in de eventsidebar
op andere pagina's. Die keuze opent meteen de deelnames van de gekozen persoon.
Een eventlink opent direct de eventdetails, zonder tussentijds de deelnametabel
te laden of te tonen. Eventafbeeldingen in de sidebar hebben afgeronde hoeken.

Afbeeldingsuploads en het verwijderen van afbeeldingen worden meteen bewaard.
Een nieuw event moet eerst worden opgeslagen voordat een afbeelding kan worden
opgeladen. Events zonder afbeelding tonen voorlopig een placeholder.

Eventgegevens en beide accordions staan in één Aether-panel. In de accordions
staan portretten met de naam eronder. Admins/directors zien rechts een camera
(economiesnapshot voor het gekozen event), een tekstballon (alle drie
gossipvelden ingevuld) en een knop om het personage te ontkoppelen. Statusiconen
zijn taupe bij ontbrekende gegevens en rood wanneer de gegevens in orde zijn.
Lege rich-textopmaak telt niet als ingevulde gossip.

Koppelen en ontkoppelen werken asynchroon. De bestaande accordions, portretten
en eventueel open eventformulier blijven behouden, zonder herladen of een
nieuwe accordionanimatie. Rode camera- en gossipiconen openen een alleen-lezen
modal voor dat personage en event. Taupe iconen openen de personagepagina op
de juiste tab, met dat event geselecteerd. Ontbrekende gossip opent een lokaal
concept; pas Save maakt de diaryvermelding aan. Snapshot maken blijft een
expliciete actie in het snapshotformulier voor admins/directors. De modalleesactie
controleert op de server de event-personagekoppeling en toegang tot het personage.

Voor deze aanvulling ook `js/characterFunctions.js`, `js/diaryCharacter.js`
en `js/economyCharacter.js` uploaden. Er is geen extra SQL-migratie nodig.

Deelnemers zien de spelerslijst, met ook de namen en portretten van andere
gekoppelde spelers. Alleen hun eigen personages zijn aanklikbaar. Ze kunnen
hun eigen spelerspersonages buiten Draft toevoegen. Ontkoppelen, figuranten
toevoegen en personages van anderen koppelen blijven beheerderacties. Deze
grenzen worden bij elke koppeling opnieuw op de server gecontroleerd, met
een lock op het personage voor de eigenaar en de status.

Deelnemers zien ook hun eigen reeds gekoppelde figurantenrollen, maar geen
figuranten van anderen. Bij hun eigen gekoppelde personages tonen de iconen
de snapshot- en gossipstatus. Bestaande gegevens openen in een modal. Een
ontbrekende snapshot opent voor de deelnemer een modal met de melding
`Financiële toestand nog niet aangepast voor "naam event".` De eventnaam hoort
bij het gekozen event. Een ontbrekende gossip opent de diarytab op dat event:
eigen spelers kunnen aanvullen, bij figuranten blijven de gossipvelden
alleen-lezen. De bestaande serverregel voor figuranten bewaart de gossip bij
een wijziging van hun achievements. Er worden geen statussen van andere
personages aan deelnemers teruggegeven.

Voor deze rechtenuitbreiding ook `api/events/eventAccess.php` en `VERSION`
bijwerken. Er is geen nieuwe SQL-migratie nodig.

## Verificatie

De endpointtests controleren authenticatie, CSRF, rolrechten en invoervalidatie.
Servicetests controleren koppelen/ontkoppelen en afscherming van andere
persoonsgegevens. De beeldtest controleert normalisatie en behoud van de oude
afbeelding bij mislukte upload. De browservervangingstest controleert dat een
personagekoppeling een open eventformulier met onbewaarde tekst behoudt.
De SQL-migratie is hier niet tegen de online database uitgevoerd.
