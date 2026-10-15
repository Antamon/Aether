# Nulmeting Aether

Upload vanuit `aetherapp`: `index.html`, `companies.html`, `js/performance.js` en `VERSION`.
Er is geen SQL-migratie nodig. De meetfunctie verstuurt geen rapporten naar een server.

## Meten

1. Log normaal in en open `index.html?character=15&performance=1` (een ander personage mag ook).
2. Wacht tot de fiche zichtbaar is en open twee andere personages via de sidebar.
3. Open `companies.html` in dezelfde tab. De meetmodus blijft actief binnen die tab.
4. Bewaar een personeels- of snapshotkaart tijdens een gewone bewerking.
5. Voer in de browserconsole `AetherPerformance.download()` uit om het JSON-rapport te downloaden.
6. Download ook een rapport op de personagepagina: resultaten zijn per pagina, niet samengevoegd.

Doe bij voorkeur drie metingen per pagina, met dezelfde personages en hetzelfde bedrijf.
Vergelijk een eerste bezoek en een normale herlaadbeurt afzonderlijk; laat voor normale
metingen de browsercache ingeschakeld. De meetfunctie voert zelf geen wijzigingen aan
personages, personeel of snapshots uit.

`AetherPerformance.report()` toont de resultaten als tabellen in de console.
`AetherPerformance.stop()` stopt de huidige registratie. Open daarna de pagina met
`performance=0` om meetmodus uit te schakelen, of sluit de tab.

## Interpretatie

- `navigation`: ontvangst van de pagina, DOM gereed en het load-event, in milliseconden.
- `character.page.initialize` / `company.page.initialize`: voltooiing van de opstartfunctie.
- `character.open` / `company.open`: ophalen en opbouwen van het gekozen object.
- `card.save`: de volledige bewaarcallback. `not-saved` betekent dat de kaart open bleef
  of de callback `false` retourneerde; een intern opgevangen fout kan daarin zitten.
- `api`: tijd van de API-helper inclusief netwerk, serverwerk en JSON-parsing.
- `resources`: laadduur, doorverwijzingen en bestandsgrootte waar de browser die vrijgeeft.
- `longTasks`: browserwerk dat langer dan 50 ms duurde, indien ondersteund.
- `nextFrameMs`: een schatting van de volgende tekengelegenheid, geen bewijs dat alle
  zichtbare onderdelen klaar zijn. Los gestarte verzoeken kunnen later eindigen.

Tijden overlappen: tel ze niet op als totale laadtijd. Nul bytes kan betekenen dat een
bestand gecachet is of dat timinginformatie van een externe bron ontbreekt. Per categorie
worden maximaal 500 records bijgehouden. Rapporten bevatten geen formulierinhoud,
API-antwoorden, cookies, foutteksten, personage-ID's of querywaarden. Assetpaden uit
`asset.php?path=...` worden wel behouden om de bestanden te kunnen herkennen.

Deze meting onderscheidt browserwerk en API-wachttijd. Om trage API's verder te verklaren,
is daarna serverprofilering van logincontrole en databasequeries nodig.
