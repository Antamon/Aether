# Gedeelde paginaopmaak

De vijf rootpagina's laden `css/style.css` via `asset.php`. `index.html`, `eventParticipation.html`, `companies.html` en `admin.html` zijn de actuele schermen; `static.html` is nog bereikbaar als oudere prototypepagina maar wordt niet vanuit de actieve navigatie gelinkt. `sidebar.html` is een los fragment en is niet als ingangspagina gevonden. Hun specifieke inhoud en JavaScriptcontracten blijven ongewijzigd.

De basis is nu:

- `.aether-page` voor de inhoudscontainer en dezelfde onderruimte;
- `.aether-panel` voor buitenpanelen met één gedeelde randkleur;
- `.aether-inset` voor binnenpanelen zoals bedrijfstype, aandeelhouders en snapshots;
- `.aether-field-label` voor nadruk op formulierlabels;
- Bootstrap `container`, `row g-*`, `col-*`, `card`, `form-control` en `form-select` voor de eigenlijke layout en invoervelden.

De bedrijfspagina gebruikt de gedeelde paneel- en subpaneelklassen; de admin- en characterkaarten delen de paneelrand. De via JavaScript opgebouwde character-, economy-, actie- en passportkaarten krijgen dezelfde klasse. Op de eventpagina staat de tabel in een responsief cardpaneel, heeft de nieuwe rij dezelfde kolomindeling als de kop, en gebruiken alle zichtbare nieuwe-eventvelden de bestaande Bootstrap-formulierstijl. Er is geen nieuwe CSS-bibliotheek of runtimecomponent toegevoegd.

De navbar staat nog per pagina in HTML. Een runtime-include of templatemigratie zou de huidige laad- en toegangsflow raken en is voor deze visuele basis niet nodig. Specifieke character-, company- en adminstijlen blijven waar de inhoud werkelijk verschilt. De oudere prototypepagina is alleen op het gedeelde containerniveau aangesloten; haar verouderde voorbeeldinhoud is niet opnieuw gebouwd.

Controle: `tests/page_layout_consistency_test.php` controleert de vijf pagina's, gedeelde klassen, eventvelden en CSS-balans. Een echte browsercontrole van alle schermen op verschillende breedtes is nog nodig na upload naar `aetherapp-dev`; die is lokaal zonder werkende WordPress- en databaseomgeving niet uitgevoerd.
