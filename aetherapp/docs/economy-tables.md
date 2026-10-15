# Tabellen en details op de personagepagina

## Bediening

In de Statuseigenschappen-box zijn genomen eigenschappen labels in de
bestaande knoppengroep. Het label heeft een witte achtergrond met 20% opacity.
De boek-, rang- en verwijderknoppen blijven werken. Keuzes voor nieuwe
eigenschappen en de beroep/titelkeuze in de persoonlijke bewerkkaart behouden
hun keuzelijst. Het label Vaardigheid toevoegen gebruikt rood met taupe tekst.

De economie-tab gebruikt echte tabellen met `aether-table`:

- Verrichtingen: datum, afzender/begunstigde, bedrag en info.
- Effectenportefeuille: event, rendementbedrag en info.
- Snapshots: event, inkomstenbedrag en info.

Elke lijst toont maximaal tien rijen, gesorteerd op datum en vervolgens ID,
nieuwste eerst. Bij meer dan tien rijen verschijnen pijlen en de huidige en
totale pagina. Wisselen van pagina vervangt alleen de tabelrijen.

Het Font Awesome circle-info-icoon opent de detailmodal. Mededelingen en
eventnamen worden als tekst getoond. De modal voor een economische snapshot
bevat ook de bewaarde effecten- en rendementsgegevens. Verwijderen,
rendement opnieuw genereren en goedkeuren blijven onder de bestaande
rechten en servercontroles vallen. Bestaande financiële bevestigingen en
idempotente API-aanvragen blijven behouden. Annuleren van een
verwijderbevestiging laat de modal open.
Na een geslaagde wijziging ververst de economie-tab en sluit de modal.

## Hergebruik

`js/dataTable.js` biedt `AetherDataTable.render(host, { label, rows, columns })`.
Elke kolom heeft `label`, optioneel `className` en `render(row)`, die tekst of
een DOM-element retourneert. De standaardpaginagrootte is tien. De aanroeper
levert de rijen in de gewenste sorteervolgorde.

`AetherDataTable.infoButton({ title, fields, actions })` maakt een infoknop;
`fields` bevat paren van label en waarde. Optionele acties hebben `icon`,
`label` en een asynchrone `run`. Een actie die `false` retourneert laat de
modal open. Andere geslaagde acties sluiten hem. Fouten worden in de modal
getoond. De component gebruikt de gedeelde AetherActionButtons en Bootstrap
Modal en ruimt de modal na sluiten op.

## Bestanden

Upload in beide omgevingen:

- `js/dataTable.js` (nieuw), `js/economyCharacter.js`, `js/traitsCharacter.js`
- `css/style.css`
- Alle door `tools/version-assets.cjs` bijgewerkte HTML-pagina's
- `VERSION`

Er is geen SQL-migratie of API-wijziging nodig. De browser- en regressietests
controleren paginering, sortering, modalgegevens, rechten, financiële acties,
de statuseigenschaplabels en behoud van de persoonlijke bewerkkaart.
