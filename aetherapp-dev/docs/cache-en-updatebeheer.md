# Cache- en updatebeheer

Datum: 17 september 2026

## Onderzoek van de bestaande situatie

- De applicatie heeft vijf gebruikte statische ingangspagina's: `index.html`, `admin.html`, `companies.html`, `eventParticipation.html` en `static.html`.
- Er is geen centrale PHP-header, layout of template. De navigatie en assettags staan per HTML-bestand uitgeschreven.
- Alle lokale CSS- en JavaScriptbestanden werden rechtstreeks en zonder versienummer geladen. Bootstrap en Font Awesome komen van externe CDN's.
- Voor deze wijziging bestond geen algemene cacheconfiguratie en geen `.htaccess`. Alleen de uitgeschakelde oude OIDC-callback in `tokenhandler.php` stuurde expliciet `Cache-Control: no-store`.
- Er is geen service worker, webmanifest of andere PWA-cache aangetroffen. Er is daarom geen service worker toegevoegd.

## Implementatie

### Assetversioning

`api/shared/assets.php` bevat de centrale assethelper. `aetherAssetUrl()`:

- accepteert alleen lokale `.css`- en `.js`-paden;
- weigert padtraversal;
- laat externe CDN-URL's ongewijzigd;
- gebruikt `filemtime()` als versie en onderdrukt filesystemwaarschuwingen;
- gebruikt bij een ontbrekend of niet leesbaar bestand de centrale deploymentversie als veilige fallback;
- ondersteunt een relatief basispad, zodat een installatie in een submap geen hardgecodeerde domein- of root-URL nodig heeft.

Omdat de ingangspagina's statische HTML zijn, kunnen zij de PHP-helper niet rechtstreeks uitvoeren. Zij laden lokale assets daarom via `asset.php?path=...`. Dit publieke endpoint wordt nooit gecachet, controleert het pad en stuurt door naar bijvoorbeeld:

```text
js/mainFunctions.js?v=1789644000
```

De browser moet de resolver dus bij een volgende paginalaad opnieuw raadplegen. De uiteindelijke versiegebonden asset mag een jaar uit de cache komen. Als de bestandstijd verandert, verwijst de resolver automatisch naar een nieuwe URL. De bestaande laadvolgorde van scripts blijft behouden. Externe CDN-tags zijn niet aangepast.

### Applicatieversie en updateherkenning

- `VERSION` bevat één opaque deployment-ID. De huidige waarde is `2026.09.17.1`.
- `api/shared/appVersion.php` leest en valideert dit bestand zonder waarschuwingen naar de gebruiker te lekken.
- `version.php` publiceert `{"version":"..."}` en stuurt altijd `no-store`.
- `js/updateManager.js` bepaalt het versie-endpoint relatief ten opzichte van zijn eigen URL. Dit blijft werken wanneer Aether in een applicatiesubmap staat.
- De checker haalt bij de start een basisversie op en controleert daarna iedere 60 seconden. Een tijdelijke netwerk- of JSON-fout blijft stil; een volgende timer probeert opnieuw.
- Eén globale manager, één lopend verzoek en één bestaande melding voorkomen dubbele timers, parallelle controles en herhaalde meldingen.
- Bij een andere deployment-ID verschijnt rechtsonder een melding met de knop **Vernieuwen**. De applicatie herlaadt nooit automatisch. Na invoer in een formulierveld of rich-textveld vraagt de knop eerst bevestiging wegens mogelijk niet-opgeslagen wijzigingen.
- De melding wordt met DOM-methodes en `textContent` opgebouwd; er wordt geen HTML uit het versie-endpoint geïnjecteerd.

Een versie is bewust een opaque deployment-ID. Iedere andere geldige waarde betekent dat een andere release beschikbaar is; dit werkt ook bij een rollback.

## Cacheheaders

| Antwoord | Cachebeleid | Implementatie |
|---|---|---|
| Statische HTML en dynamische PHP | `no-cache, must-revalidate, max-age=0` | root `.htaccess` |
| Ongeversioneerde lokale CSS/JS | `no-cache, must-revalidate, max-age=0` | root `.htaccess` |
| CSS/JS met geldige `v`-query | `public, max-age=31536000, immutable` | root `.htaccess` |
| API-antwoorden | `private, no-store, no-cache, must-revalidate, max-age=0` | `api/.htaccess`; de algemene authenticatielaag stuurt dezelfde bescherming ook vanuit PHP voor ingelogde routes |
| `asset.php` en `version.php` | `no-store, no-cache, must-revalidate, max-age=0` | PHP-helper en root `.htaccess` |

De `.htaccess`-regels vereisen Apache `mod_headers` en toegestane `.htaccess`-overrides. De PHP-headers op de assetresolver, het versie-endpoint en routes die `accessControl.php` laden blijven actief wanneer Apacheconfiguratie niet beschikbaar is. De webserverconfiguratie moet na deployment eenmalig met de onderstaande `curl`-controles worden bevestigd.

## Deployment

1. Kies voor iedere release een nieuwe, maximaal 64 tekens lange deployment-ID met alleen letters, cijfers, punt, underscore of koppelteken. Werk `VERSION` vóór deployment bij.
2. Deploy `VERSION`, `.htaccess`, `api/.htaccess`, de PHP-helpers, de HTML-pagina's en de assets samen. Een half uitgerolde release kan tijdelijk naar een ontbrekende asset verwijzen.
3. Zorg dat PHP het applicatiepad en `VERSION` kan lezen. Er is geen Composer- of Node.js-afhankelijkheid toegevoegd.
4. Zorg op Apache dat `mod_headers` actief is en `AllowOverride` de headerregels toestaat.
5. Controleer na deployment:

   ```sh
   curl -i https://voorbeeld.example/aether/version.php
   curl -I "https://voorbeeld.example/aether/asset.php?path=js/updateManager.js"
   curl -I "https://voorbeeld.example/aether/js/updateManager.js?v=<waarde-uit-redirect>"
   curl -I https://voorbeeld.example/aether/index.html
   ```

   Verwacht respectievelijk `no-store`, een niet-gecachete redirect met een `Location` die `?v=` bevat, een jaar `immutable`, en `no-cache, must-revalidate`.

6. Laat een reeds geopende pagina tijdens een testdeployment openstaan. Na maximaal ongeveer 60 seconden moet één updatemelding verschijnen. Typ eerst in een veld om ook de bevestiging voor niet-opgeslagen invoer te controleren.

## Tests

`tests/cache_update_management_test.php` controleert:

- een stabiele URL voor een ongewijzigd bestand en een nieuwe URL na een gewijzigd `filemtime()`;
- relatieve basispaden, de deploymentfallback zonder PHP-waarschuwing, externe CDN-URL's en ongeldige lokale paden;
- lezen en valideren van `VERSION`;
- de vereiste Apache-cachebeleidsregels;
- dat alle vijf ingangspagina's de centrale resolver en updatechecker gebruiken;
- het JSON-contract van het echte `version.php`-endpoint in een afzonderlijk PHP-proces;
- de frontendcode voor versieverschil, `no-store`, interval, request- en meldingsguards, stille netwerkfouten en bescherming van mogelijk niet-opgeslagen invoer.

`tests/update_manager_browser_test.html` is de herhaalbare browsertest met afgeschermde fetch-, timer- en bevestigingstestdoubles. De test voert de echte `js/updateManager.js` uit en controleert een gewijzigde versie, precies één timer en melding, bevestiging bij gewijzigde invoer en een stille gesimuleerde netwerkfout. Het resultaat staat in het element `#test-result`.

Uitgevoerd op 17 september 2026 met PHP 8.4.25:

- `tests/cache_update_management_test.php`: geslaagd.
- `tests/update_manager_browser_test.html`: geslaagd in Microsoft Edge headless; het gerenderde resultaat was `PASS` en bevatte precies één updatemelding.
- Bestaande toegangscontrole-, routecoverage-, requestvalidatie-, responsecontract-, character-validatie-, rich-text-, OIDC- en `saveCharacterSection`-tests: geslaagd.
- `tests/authenticated_user_test.php`: niet uitgevoerd; de test meldt `SKIP` omdat PDO SQLite in de lokale PHP-build ontbreekt.
- PHP-syntaxcontrole van alle nieuwe en gewijzigde PHP-bestanden: geslaagd.
- Handmatige HTTP-controle via de lokale PHP-server: `version.php` gaf HTTP 200 met het verwachte JSON en `no-store`; `asset.php` gaf HTTP 302 met `no-store` en een `Location` met de actuele bestandstijd.
- `git diff --check`: geslaagd.

De lokale PHP-server verwerkt geen `.htaccess`. De productieheaders voor HTML en statische bestanden zijn daarom hier via configuratietests gecontroleerd en moeten na deployment eenmaal op de echte Apachehost met de bovenstaande commando's worden bevestigd.
