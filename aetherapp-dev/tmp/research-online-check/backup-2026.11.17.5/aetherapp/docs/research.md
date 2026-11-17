# Onderzoek

Geïntegreerd in Aether, versie **2026.11.17.4**. PHP/PDO en browser-JavaScript;
de hosting heeft geen Node.js nodig. Het aangeleverde HTML-schema is alleen als
wireframe gebruikt.

## Bereikbaarheid en gebruik

- **SF:** Personages → Acties → Onderzoek, naast Wereldwijs. Kies een event
  waaraan het actieve eigen spelerspersonage of de eigen extra gekoppeld is.
  Scan een QR-code, kies een foto of plak de code. Alleen gevonden discoveries
  verschijnen in de lijst. Opnieuw openen verandert geen voortgang.
- **AD:** admin-sidebar → Onderzoek (`research.html`). De tabs zijn Challenges
  en Voortgang. Directors krijgen uitsluitend de nieuwe onderzoeksrechten;
  de autorisatie van bestaande adminfuncties is niet gewijzigd.
- Maak eerst een challenge met titel, beschrijving en events. Maak daarna een
  discovery met titel, beschrijving en vaardigheid. Open vervolgens het gewenste
  niveau en de route om feedbackstappen toe te voegen. Een nieuwe discovery is
  standaard actief en bevat bij Ongetraind één stap met “No additional information”.
  Niet alle niveaus of conceptstappen hoeven ingevuld te zijn om te activeren.
- Bonus-specialisaties, varianten en voorwaarden worden pas getoond wanneer
  je ze toevoegt/opent. De knop QR-code staat naast Edit en Discovery toevoegen bij de challenge. Hij downloadt alle discoverycodes in één A4-PDF: drie kolommen en maximaal twaalf kaarten per pagina. Iedere kaart heeft een rood vlak van 65 × 65 mm (#640000), een wit vlak van 60 × 60 mm met 4 mm hoekafronding, de challengetitel, de vaardigheid en de eigen QR-code. Een tijdsvoorwaarde ergens in de challenge geeft alle kaarten een stopwatchicoon. De browserinstellingen bepalen de downloadmap of tonen een opslagvenster; druk af op 100% voor de juiste afmetingen.
- Voortgang filtert op event, challenge, discovery en status. Het persoon-icoon
  opent getoonde/afgeronde/bevestigde stappen en gestarte timers. Een AD kan met
  een verplichte reden een stap afronden of het einde van een bestaande klok
  wijzigen. Deze ingrepen krijgen een auditregistratie. Een AD-afronding is niet
  hetzelfde als een bevestiging of weergave door de SF.

## Hergebruik en bestanden

De bestaande `tblCharacter`, `tblLinkEventCharacter`, `tblEvent`, `tblSkill`,
`tblLinkCharacterSkill`, `tblSkillSpecialisation` en `tblCharacterSpecialisation`
zijn de bronnen voor personage, event, vaardigheid en specialisaties.
Login/sessies, rolcontrole, eigenaarschap van personages, CSRF, idempotentie,
HTMLPurifier, rich-texteditor, Aether-panels, modale frames en button groups zijn
hergebruikt. De bestaande page nav en de overige personage-acties blijven intact.

| Bestanden | Functie |
| --- | --- |
| `sql/migrations/0024_research.sql` | Challenges, eventkoppelingen, discoveries, routes, stappen, afgeschermde media, voortgang, handelingen, timers en audit |
| `api/research/researchRules.php` | Niveau, variantprioriteit, conflicten, cycli en tijdzoneconversie |
| `api/research/researchRepository.php` | PDO-queryhelpers en transactionele onderzoekslock |
| `api/research/researchAccess.php` | AD-/SF-autorisatie, actieve personages en eventtoegang |
| `api/research/researchService.php` | Scannen, toegestane feedback, tonen, bevestigen, afronden en timers |
| `api/research/researchAdminService.php` | Beheer, validatie, versiecontrole en audit |
| `api/research/researchReadService.php` | Gescheiden SF- en AD-projecties en voortgang |
| `api/research/http.php` | JSON-API, sessie, CSRF, idempotentie en foutafhandeling |
| `api/research/upload.php`, `media.php` | Beveiligde uploads en geautoriseerde mediaweergave met byte ranges |
| `research.html`, `js/researchAdmin.js` | Beheerschermen |
| `js/researchCharacter.js`, `js/researchUi.js`, `css/research.css` | Scanflow, SF-weergave en gedeelde componenten |
| `js/actionsCharacter.js`, `index.html` | Integratie in de bestaande actiepagina |
| `js/aetherSidebars.js`, `img/actionSpeuren.png` | Onderzoek in de sidebar, hergebruik bestaand vergrootglas |
| `js/researchQrPdf.js`, `js/vendor/`, `fonts/Amarante-*` | PDF-kaarten, QR-generator/decoder, ingesloten lettertype en licenties |
| `tests/research_*`, `tests/character_action_navigation_test.cjs` | Nieuwe tests en bijgewerkte navigatietest |
| `VERSION`, HTML-assetverwijzingen | Releaseversie en bestaande SHA-256 assetversioning |

## Spelregels en consistentie

Het basisniveau is 0–3. Eén of meer passende bonus-specialisaties geven samen
precies +1, tot maximaal 4. Een verplichte specialisatie bij een variant is alleen
een toegangsvoorwaarde en geeft zelf geen niveauverhoging. De zichtbare niveaus
zijn Ongetraind, Beginner, Gevorderde, Meester en Specialist, zonder nummer.

Een leeg niveau gebruikt de dichtstbijzijnde ingevulde lagere feedbackroute
waarvoor dit personage in aanmerking komt. Daarbij wordt de volledige route
overgenomen, inclusief bevestigingen en tijdsvoorwaarden. Lege varianten
verdringen geen ingevulde feedback. Lege conceptstappen worden overgeslagen;
tekst of een mediabestand maakt een stap ingevuld. Ongetraind krijgt bij het
aanmaken of bewaren van de discovery de standaardtekst als deze route leeg is.

De selectie is **personagevariant → specialisatievariant → algemene route**.
Bij meerdere passende specialisatievarianten wint de hoogste expliciete
prioriteit. Gelijke specialisatieprioriteiten of overlappende personagevarianten
op hetzelfde niveau worden geweigerd. Een variant vervangt de algemene route.

De route wordt vastgelegd bij de eerste scan, afzonderlijk per personage en
event. Vanaf die eerste scan staan vaardigheidskeuze, bonussen, varianten,
stapvolgorde, bevestigingsregels en voorwaarden van die discovery vast. Inhoud,
media en activering blijven bewerkbaar, zolang een ingevulde stap daardoor niet
leeg wordt of omgekeerd. Voor een structureel andere route maak
je een nieuwe discovery. Dit voorkomt dat reeds bevestigde opdrachten of lopende
timers door een latere configuratiewijziging van betekenis veranderen.

Stappen volgen elkaar op; alle extra voorwaarden gelden met **EN**. Een expliciete
eerdere-stapvoorwaarde verwijst naar dezelfde route. Discoveryvoorwaarden en
timertriggers verwijzen naar discoveries van dezelfde challenge. De hele
afhankelijkheidsgraaf wordt op cirkels gecontroleerd. Een discovery is pas klaar
wanneer alle stappen van de geselecteerde route afgerond zijn.

Een GET of scan voltooit geen stap. De browser meldt **getoond** pas nadat de
feedback in een zichtbaar venster in beeld is gekomen. Zonder bevestigingsregel
rondt die melding de stap af; anders is een aparte bevestiging nodig. Getoond,
afgerond en bevestigd zijn afzonderlijke tijdstippen. Zoals bij iedere webapp
kan de server niet bewijzen dat iemand de getoonde tekst daadwerkelijk las.

## Tijd en concurrency

- Alle tijdstippen worden intern in UTC opgeslagen. Vaste tijden worden per
  gekoppeld event ingevoerd in zijn onderzoekstijdzone, standaard
  `Europe/Brussels`. De AD kan die instellen voordat vaste tijden geconfigureerd
  zijn. Niet-bestaande en dubbelzinnige tijden tijdens een DST-overgang worden
  geweigerd in plaats van stilzwijgend verschoven.
- Een duur kan starten bij scannen, feedback tonen, opdracht bevestigen, stap
  afronden of discovery afronden. De bron is een concrete discovery/stap.
- Gedeelde klokken zijn uniek per event en doelstap; persoonlijke klokken ook
  per personage. De eerste bijpassende handeling bepaalt start, einde en
  initiator. Een latere scan of browserherstart verandert die niet.
- Alle onderzoeksbewerkingen en consistente projecties gebruiken een korte
  InnoDB-transactie met dezelfde onderzoekslock. Unieke sleutels en de bestaande
  idempotentielaag voorkomen dubbele scans, weergaven, afrondingen en starts.
  Uploads worden per stap en inhoudshash ontdubbeld.
- SF-gegevens worden elke tien seconden opnieuw gecontroleerd; de zichtbare
  countdown telt lokaal per seconde af. Alleen de server kan feedback vrijgeven.
  Bevestigen ververst onmiddellijk. Polling vervangt ongewijzigde media niet.

## QR en media

De QR-inhoud is `aether-research:` plus 32 cryptografisch willekeurige bytes in
hexnotatie. Geen discovery-ID, niveau, feedback of mediapad staat in de code.
De server controleert code, activering, challenge/eventkoppeling, personage en
eigenaarschap. Een onbekende discovery kan niet via haar numerieke ID geopend
worden. De applicatie navigeert niet naar een URL uit een gescande code.

Media staat in de database, nooit in een publiek uploadpad. `media.php`
controleert bij elke aanvraag opnieuw de huidige gebruiker en de zichtbare of
eerder getoonde stap. AD-preview heeft dezelfde rolcontrole. Ook audio/video
byte-range-aanvragen worden geautoriseerd. Verborgen niveaus, toekomstige
stappen en hun media ontbreken in SF-JSON.

Uploads: maximaal **8 MB** per bestand; JPEG/PNG/WebP, MP3/Ogg/WAV en MP4/WebM/Ogg.
MIME-detectie gebeurt met Fileinfo. Afbeeldingen worden gecontroleerd op maximaal
20 megapixel en met GD opnieuw als PNG opgebouwd. Rich text gaat door de
bestaande HTMLPurifier. SVG/HTML, embeds en externe mediapaden zijn niet toegestaan.
Er is geen transcoder; afspeelbaarheid hangt ook af van de codec in het bestand
en de browser. Camera vereist HTTPS of localhost en toestemming.

De browserbibliotheken zijn lokaal vastgezet:

- [qrcode-generator 2.0.4](https://github.com/kazuhikoarase/qrcode-generator), MIT.
- [jsQR 1.4.0](https://github.com/cozmo/jsQR), MIT.

## Installatie

1. Maak zoals gebruikelijk een databaseback-up. Voer `0024_research.sql` uit op
   elke database waarop deze code gebruikt wordt, vóór het gebruik van Onderzoek.
   De bestaande migraties 0004 (idempotentie) en 0014 (event/personage) zijn vereist.
   De migratie is herhaalbaar en wijzigt geen bestaande spelgegevens.
2. Plaats de nieuwe PHP-, JS-, CSS-, HTML- en afbeeldingsbestanden samen met de
   gewijzigde navigatiebestanden. Neem `js/vendor` mee. Gebruik de vernieuwde
   HTML-assetverwijzingen en `VERSION` samen.
3. PHP heeft PDO MySQL, DOM, mbstring, Fileinfo en GD nodig, naast de reeds
   aanwezige Composer/vendor-code. Voor 8 MB bestanden moeten PHP's
   `upload_max_filesize` en `post_max_size` en MySQL's `max_allowed_packet`
   voldoende ruimte bieden (bijvoorbeeld 10 MB, 12 MB en 16 MB).
4. Controleer de event/personagekoppelingen en stel zo nodig de eventtijdzone in.
   Vul desgewenst aanvullende niveaus in en druk de QR-code af. Voor deze wijziging
   in standaardfeedback en terugval is geen extra databankmigratie nodig.

De migratie en de deployment zijn niet op de online databases uitgevoerd door
deze implementatie.

## Controles en grenzen

De integratietest gebruikt echte MariaDB/InnoDB-transacties in een tijdelijk
schema en voert de migratie tweemaal uit. Getest: niveaus 0–4, bonuslimiet,
standaard actieve discovery, lege niveaus en varianten, terugval met opdrachten
en afgeschermde media, overslaan van lege conceptstappen,
variantprioriteit/conflicten, rich text, actieve speler/extra, eigenaarschap,
event- en rolcontrole, afzonderlijk getoond/bevestigd/afgerond, gemengde
bevestigingsstappen, meerdere prerequisites, cycli, gedeelde klokken met twee
gelijktijdige PHP-processen, persoonlijke klokken, latere scans, vaste tijden,
DST, idempotentie, media-autorisatie en voortgang.

De Edge-browsertest gebruikt de echte onderzoeks-HTTP-routes met een lokale
testdatabase. Getest: beheerformulieren, progressive disclosure, bewaren,
media-upload/ontdubbeling/range, CSRF, QR-PDF-download, voortgang, scan via code,
zichtbaarheidsmelding, bevestiging, timer, verborgen media, bevoegdheden,
eventwissel, foutieve code, geweigerde camera en mobiel formaat zonder
horizontale overflow. Generator en decoder hebben ook een QR-roundtriptest.

Een echte telefooncamera en fysieke printer zijn niet getest. De SQL is getest
op MariaDB 11.4; de online database is niet benaderd. De centrale onderzoekslock
kiest consistentie boven maximale paralleliteit; zeer grote evenementen kunnen
later fijnere locks of paginering nodig hebben.

Reproduceerbaar:

```powershell
# Gebruik uitsluitend een aparte lokale MariaDB-instance; de test maakt en verwijdert een eigen schema.
$env:RESEARCH_TEST_DSN='mysql:host=127.0.0.1;port=33079'
php tests/research_integration_test.php
node tests/research_qr_test.cjs
node tests/character_action_navigation_test.cjs
```

De browserharness `research_browser_router.php` is uitsluitend voor PHP's lokale
testserver en vereist `RESEARCH_BROWSER_FIXTURE`. Hij is niet bereikbaar in de
normale app; `tests/` wordt al door `.htaccess` geblokkeerd. De browsertest gebruikt
een tijdelijke Playwright Core-installatie met de aanwezige Edge-browser; dit is
geen hostingafhankelijkheid. Tijdelijke runtimes en testdatabases horen niet bij
de deployment.

Links staat een accordion in de bestaande vaardigheidsopmaak: challenges als hoofditems en hun discoveries in een tabel met oogiconen. Nieuwe challenge staat erboven. De groep Edit, Discovery toevoegen en QR-code staat onder de discoveries van de geopende challenge. Rechts blijft de inhoud aanvankelijk leeg; een keuze toont de challenge of uitsluitend de geselecteerde discovery. De accordion-container stelt ook de Bootstrap-variabelen voor pijltjes en opmaak beschikbaar.

Aanvullende controle: `tests/research_qr_pdf_test.cjs` test de lijst, accordion-opmaak, knopplaatsing, download, paginering, het stopwatchicoon en decodeert alle twaalf QR-codes uit de gerenderde PDF. De PDF is ook visueel gecontroleerd. De test verwacht tijdelijk Playwright Core, PDF.js en Bootstrap in `tmp/pdfs` of een Playwright-pad via `RESEARCH_PLAYWRIGHT`.

## Update 2026.11.17.1

De beheerpagina heeft op grote schermen twee even brede kolommen; op kleine schermen staan ze onder elkaar. Rechts staat alleen de geselecteerde discovery, als gewone sectie met Edit rechtsboven. Bonus-specialisaties en Feedbackvariant toevoegen blijven op hun bestaande plaats.

De QR-knop controleert of de PDF-exportmodule, QR-generator en PDF-bibliotheek beschikbaar zijn. Ontbrekende modules worden op aanvraag geladen via de bestaande asset.php-route, die naar de actuele assetversie verwijst. Een mislukte laadpoging toont een leesbare melding en kan opnieuw geprobeerd worden. Dit is getest door de globals te verwijderen en een scriptaanvraag tijdelijk te blokkeren, gevolgd door een echte download en het decoderen van alle twaalf QR-codes op pagina 1.

Bestanden worden via de gebruikelijke automatische synchronisatie geplaatst; een apart uploadpakket is niet nodig. De HTML-assetversies en VERSION zijn bijgewerkt. Voor deze wijziging is geen nieuwe databankmigratie nodig.
## Gedeelde accordions en discoverytabel (17 november 2026)

Alle accordiononderdelen gebruiken de gedeelde regels in css/style.css, gebaseerd op Vaardigheden op de Character sheet: transparante inhoud, een halfwitte gesloten kop, een rode geopende kop en Amarante voor de koptekst. De chevron is een CSS-masker met currentColor en neemt daardoor altijd de actuele tekstkleur over. Research heeft geen afwijkende padding, kopstijl of ruimte tussen accordionitems meer.

Binnen een challenge staan discoveries in dezelfde table/aether-table-opmaak als aandeelhouders. Een zelfstandig bruin oogicoon opent de discovery rechts; de geselecteerde rij is gemarkeerd en de toetsenbordfocus blijft op het icoon. Nieuwe challenge heeft zijn natuurlijke breedte en staat gecentreerd boven de linker accordion. De 50/50-indeling, gewone discoverysectie, Edit rechtsboven en QR-PDF blijven behouden.

De browsercontrole vergelijkt de geopende en gesloten accordionstijl van vaardigheden, events, algemene Bootstrap-accordions en Research, inclusief chevronkleur. Ook tabelselectie, focus, knopcentrering, mobiele breedte en de PDF-download zijn gecontroleerd.
## Zichtbare feedback (17 november 2026, versie 2026.11.17.2)

De discoverytabel heeft geen zichtbare hoofding meer. Een verborgen caption bewaart de toegankelijke tabelnaam. Rechts worden alle feedbackniveaus, varianten en stappen als gewone secties met koppen weergegeven. Er zijn geen accordions of ingeklapte delen in de rechterkolom. De challenge-accordion links blijft behouden. Knoppen en media zijn ongewijzigd beschikbaar.

De browsercontrole verifieert een tabel zonder thead/th, geen details/summary/accordion-buttons rechts en direct zichtbare inhoud uit meerdere stappen en verschillende niveaus. Mobiele breedte en PDF-download blijven getest.
## Challengegegevens links en inline discoverybewerking (2026.11.17.3)

De challengebeschrijving en een ul-lijst met gekoppelde events staan links, tussen de challengetitel en de discoverytabel. De rechterkolom bevat uitsluitend de geselecteerde discovery. Edit bij de discovery wisselt de algemene gegevens ter plaatse naar invoervelden met Opslaan en Annuleren, zonder modal. Titel, rich-textbeschrijving, vaardigheid, activering en optionele bonus-specialisaties gebruiken de bestaande discovery_save-API, inclusief revision, autorisatie en validatie.

De aparte bonusknop is verwijderd. Geselecteerde bonus-specialisaties staan in view mode als lijst onder de vaardigheid. In edit mode filtert de selectie op de gekozen vaardigheid. Voor reeds gevonden discoveries blijven vaardigheid en bonussen vast volgens de bestaande serverregel; inhoud en activering blijven bewerkbaar. Annuleren schrijft niets en een fout bij bewaren behoudt de ingevoerde gegevens.

De browsercontrole test de plaatsing van challengegegevens, inline bewerken zonder dialoog, annuleren, bewaren, foutbehoud, filtering, zichtbare bonusgegevens na heropenen en de bestaande beperking voor gebruikte discoveries. Ook de 50/50-indeling, mobiele breedte en PDF-download blijven gecontroleerd.
