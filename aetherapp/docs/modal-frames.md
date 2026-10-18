# Gemeenschappelijke modalopmaak

`css/modal.css` en `js/modal.js` verzorgen de gouden omlijsting, rode titelbalk,
papieren achtergrond en het Font Awesome-kruisje in het rode medaillon.
Alle zes HTML-pagina's laden beide bestanden.

Bootstrap-modals worden bij `show.bs.modal` ingericht. Een MutationObserver
ondersteunt ook later toegevoegde modals en native `dialog`-vensters voor aandelen.
Bestaande invoervelden, knoppen en eventhandlers blijven dezelfde DOM-elementen.
De inhoud scrolt binnen het kader; op smalle of lage schermen wordt de decoratie kleiner.

Upload bij deze wijziging beide nieuwe bestanden, de zes bijgewerkte HTML-pagina's,
`VERSION` en de elf afbeeldingen in `images/` (`modalFrame*.png`,
`modalTitleBar.png`, `paper.png` en `red.jpg`). Er is geen databankmigratie nodig.

Controle: `node tests/modal_frame_test.cjs`.
