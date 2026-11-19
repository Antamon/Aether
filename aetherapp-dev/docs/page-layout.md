# Gedeelde pagina-uitlijning

De personagepagina, adminpagina (vaardigheden en eigenschappen) en Onderzoek
gebruiken dezelfde Bootstrap `.container.aether-page` voor de hoofdinhoud.
De page nav is een afzonderlijke `.container.aether-page-nav` vóór die inhoud,
zoals op de personagepagina. Voeg geen eigen paginabreedtes of zijmarges toe.

`aetherSidebars.js` bepaalt de navigatiepositie vanuit `#sheetBody` of
`main.container.aether-page`. De navigatie stopt vóór het ornament van de
topbar. Dezelfde berekening geldt voor alle pagina's en wordt bijgewerkt
bij herschalen en wanneer de inhoud na het laden zichtbaar wordt.

Op mobiel delen navigatie en inhoud dezelfde binnenmarge; rechtstreekse
Bootstrap-rijen gebruiken daarbij een passende kolomgoot.

Controle: `tests/page_layout_browser_test.cjs` vergelijkt de echte HTML/CSS
en de gedeelde navigatiescript op 390, 600, 900, 1200, 1500 en 1920 pixels.
