# Google Translate in Aether

De koppeling gebruikt Cloud Translation Basic (v2), met het NMT-model. Alleen de
PHP-server benadert Google. Browsers krijgen nooit de Google-sleutel. De bestaande
bron, bewerkformulieren, rollen en toegangsregels blijven behouden.

## Activeren

1. Voer `sql/migrations/0029_google_translation.sql` uit op beide appdatabases,
   na migratie 0027. Migratie 0028 bevat de al gemaakte eerste vertaalset en blijft bruikbaar.
2. Maak of kies een Google Cloud-project, koppel de vereiste facturering en activeer
   de Cloud Translation API. Maak een API-sleutel met toegang tot uitsluitend die
   API. Server-IP-beperkingen kunnen worden gebruikt; browser-referrerbeperkingen
   passen niet bij deze serverkoppeling.
3. Voeg onderstaand onderdeel toe aan de bestaande `config.local.php`, naast
   `database` en `application`. Behoud de bestaande databasegegevens. Zet de echte
   sleutel uitsluitend in dit afgeschermde bestand of in de serveromgeving.

```php
'translation' => [
    'enabled' => true,
    'googleApiKey' => 'VUL_HIER_DE_ECHTE_SLEUTEL_IN',
    'monthlyCharacterLimit' => 500000,
    'maxAutomaticCharacters' => 2000,
    'budgetKey' => 'aether-google',
    'usageTimeZone' => 'Europe/Brussels',
    'usageDatabase' => null,
],
```

4. Als beide websites hetzelfde Google-project gebruiken, moeten ze ook dezelfde
   gebruiksteller gebruiken. Vul dan op **beide** websites dezelfde `usageDatabase`
   en `budgetKey` in, bijvoorbeeld de productiedatabase waarop 0029 is uitgevoerd:

```php
'usageDatabase' => [
    'dsn' => 'mysql:host=HOST;dbname=GEDEELDE_DATABASE;charset=utf8mb4',
    'user' => 'DATABASEGEBRUIKER',
    'password' => 'DATABASEWACHTWOORD',
],
```

Met `null` is de teller alleen gedeeld door gebruikers van die ene appdatabase.
Verschillende sleutels van hetzelfde Google-project kunnen dezelfde teller gebruiken.
Een sleutelrotatie reset de teller niet. Andere toepassingen die buiten Aether
hetzelfde Google-project gebruiken, worden door deze lokale teller niet gemeten.

5. De hosting heeft PHP cURL met werkende HTTPS-certificaatcontrole nodig. De
   bestaande DOM/mbstring/PDO-runtime blijft nodig. Er is geen Composer-update of
   Node.js op de hosting nodig. Vernieuw de pagina na het plaatsen van bestanden
   en configuratie. Test met een korte nieuwe tekst waarvoor nog geen vertaling bestaat.

Alternatieven voor het configuratiebestand zijn `AETHER_TRANSLATION_ENABLED`,
`AETHER_GOOGLE_TRANSLATION_API_KEY`, `AETHER_TRANSLATION_MONTHLY_LIMIT`,
`AETHER_TRANSLATION_MAX_CHARACTERS` en `AETHER_TRANSLATION_BUDGET_KEY`.

Geautomatiseerde tests gebruiken een nagebootste Google-verbinding en verbruiken geen vertaaltegoed.

## Gebruik

Pagina's openen, herladen, bladeren en achtergrondcontroles starten **geen** Google-aanvragen.
De app toont uitsluitend bestaande vertalingen voor de gekozen taal; ontbreekt die, dan blijft de bron zichtbaar.

Alleen een succesvolle opslag van **nieuwe of gewijzigde tekst** maakt een automatische opdracht.
De server vergelijkt de opgeslagen tekst vóór en na de bewerking binnen de betrokken diary, sectie,
vaardigheid, eigenschap, bedrijfsgegevens, personeelsgegevens, kwartaalgebeurtenis, event of research-item.
Ongewijzigde velden uit hetzelfde formulier worden niet mee vertaald. Een bekende brontaal blijft behouden bij wisselen van interfacetaal; voor nieuwe tekst geldt de interface-/invoertaal
als brontaal; alleen de andere taal is het doel. De achtergrondaanvraag gebruikt een afzonderlijk,
ondertekend opslagbewijs. Een bewijs uit een gewone paginaweergave kan geen betaalde aanvraag starten.
Ook oude browserwachtrijen worden hierdoor geweigerd. Herhaald opslaan van dezelfde tekst en
hergebruik van een bestaande vertaling verbruiken niets. De oorspronkelijke invoer blijft bewaard.

Teksten langer dan 2.000 tekens blijven handmatig. Er is geen nieuwe migratie nodig voor deze wijziging.
Automatische vertalingen worden aangeduid met een link naar Google Translate.

Administrators en directors krijgen naast de taalkeuze een taalicoon. Daarmee openen
ze **Vertalingen beheren** voor de teksten die op de huidige pagina geautoriseerd
zijn geladen. Ze kunnen zoeken, op lange teksten filteren, de doeltaal kiezen en een
vertaling invoeren of corrigeren. De modal heeft het bestaande Aether-frame en alleen
het sluitkruisje van dat frame. Handmatige vertalingen verbruiken geen Google-tegoed.

Teksten van **meer dan 2.000 Unicode-tekens**, inclusief eventuele HTML-opmaak, worden
nooit automatisch gesplitst of naar Google gestuurd. Bestaande vertalingen van lange
teksten blijven zichtbaar. Bij gewijzigde bronteksten vervalt de oude vertaling door
de andere bronhash en kan de nieuwe tekst weer handmatig worden vertaald.

Directors kunnen vertalingen beheren, maar krijgen geen toegang tot appconfiguratie,
Google-sleutels of het wijzigen van de maandlimiet.

## Teller en maandlimiet

De standaardlimiet is 500.000 tekens per kalendermaand in Europe/Brussels. De teller
meet Unicode-codepunten van de daadwerkelijk aangeboden tekst, dus geen bytes.
De volledige aanvraag moet nog binnen het budget passen; anders wordt niets naar
Google gestuurd. Kleine resterende ruimte kan nog voor een kleinere tekst worden gebruikt.

De app boekt het aantal tekens **vóór** de externe aanvraag met een atomaire update
en vergrendelde requestregistratie. Gelijktijdig openen van dezelfde tekst levert
één aanvraag op. De databasevergrendeling en de PHP-sessielock worden niet tijdens
de Google-aanvraag vastgehouden. Hergebruik van de cache kost geen tekens.

Onzekere en mislukte externe pogingen blijven conservatief meetellen als verzonden
tekens; succesvolle tekens worden daarnaast apart bijgehouden. Na een storing geldt
een wachttijd van vijf minuten voordat een nieuwe poging mogelijk is. Die nieuwe
poging reserveert opnieuw capaciteit. Zo kan een timeout niet ongemerkt tot een
overschrijding leiden. De teller is een appbudget, geen uitlezing van de Google-factuur.

Bij een lege maandruimte staat **Vertaaltegoed op** naast de naam van administrators
en directors. Past een specifieke tekst niet meer binnen de resterende ruimte, dan
staat er **Onvoldoende vertaaltegoed**. Het taalicoon toont het actuele gebruik als
tooltip. Dit wordt na vertaalacties en ongeveer elke minuut bijgewerkt. In een nieuwe
maand begint een nieuwe teller; er hoeft niets handmatig gereset te worden.

Google hanteert momenteel een maandelijks tegoed voor de eerste 500.000 NMT-tekens,
gezamenlijk voor Basic en Advanced. De limiet van Aether is daarop afgestemd.
Zie [Google-prijzen](https://cloud.google.com/products/translate/pricing).

## Toegang en opslag

`tblContentTranslation` blijft de versiegebonden cache. De nieuwe tabellen
`tblTranslationUsage` en `tblTranslationRequest` bevatten respectievelijk het budget
en de coördinatie van aanvragen. Ze veranderen geen oorspronkelijke spelgegevens.

Handmatige bronbewijzen horen bij geautoriseerde pagina-inhoud. Automatische opslagbewijzen worden
alleen gemaakt voor daadwerkelijk gewijzigde, opgeslagen tekst uit een vaste lijst schrijfendpoints. Ze zijn ondertekend met
een geheime sessiesleutel, gebonden aan gebruiker/bron/opmaak en één uur geldig.
De vertaalroute controleert rol, CSRF en dit bewijs opnieuw. Een browser kan daarmee
geen verborgen feedback opvragen of een willekeurige tekst in de cache overschrijven.
Handmatige opslag gebruikt bestaande idempotentie, HTMLPurifier en versiecontrole.

## Controle en bronnen

- `tests/translation_save_only_test.php`: kostenloze reads, gewijzigde opgeslagen velden, tegenovergestelde taal, onveranderde opslag, rollback, limiet en cache.
- `tests/google_translation_test.php`: maandgrens, limiet, Unicode, dubbele aanvragen,
  hergebruik, foutpaden, wachttijd, bronbewijzen, handmatige correcties en veilige HTML.
- `tests/translation_http_test.php`: echte requesthandlers met deelnemers/directors,
  CSRF, vervalste bronbewijzen, lezen/opslaan en afgeschermde geheimen.
- `tests/google_translation_browser_test.cjs`: achtergrondvertaling van tekst/HTML,
  handmatige lange tekst, melding naast de naam, rollen, modal en mobiel scherm.

De Google-transportlaag is tijdens deze controles nagebootst. De migraties moeten op de gebruikte online database zijn uitgevoerd. Voor de eerste echte aanvraag zijn
een geactiveerd Google-project en een geldige serversleutel nodig.

Officiële documentatie:
[Setup](https://docs.cloud.google.com/translate/docs/setup),
[API-authenticatie](https://docs.cloud.google.com/docs/authentication/api-keys-use),
[v2 translate](https://docs.cloud.google.com/translate/docs/reference/rest/v2/translate),
[attributie](https://docs.cloud.google.com/translate/attribution).
De automatische vertaling van Aether wordt mogelijk gemaakt door Google Translate;
automatische vertalingen kunnen fouten bevatten en vervangen de oorspronkelijke tekst niet.

## Vertalingen corrigeren in gewone formulieren

Administrators en directors kunnen ook de bestaande bewerkvelden gebruiken. Kies de taal
van de te corrigeren vertaling en open het normale Edit-formulier. Tekstvakken en rich-textvelden
tonen de bestaande vertaling. Opslaan bewaart alleen die handmatige vertaling wanneer het om
een andere taal dan de bron gaat. De oorspronkelijke tekst blijft gelijk.

Bijvoorbeeld: bron Engels Cut!, Nederlandse vertaling Snee!. Open het event in het Nederlands
en wijzig de titel naar Cut!. De Engelse bron blijft Cut!; de Nederlandse vertaling wordt
handmatig Cut!. Ook een vertaling die gelijk is aan de bron vernieuwt onmiddellijk de weergave.
Deze correcties gebruiken geen Google-tegoed en worden niet door automatische vertalingen overschreven.

Bewerk je in de brontaal, dan verandert de oorspronkelijke tekst en blijft de bestaande automatische
vertaling-uitsluitend-na-opslag gelden. De bestaande rechten op vertaalbeheer blijven beperkt tot
administrators/directors; gewone deelnemers krijgen hierdoor geen extra beheerrechten.

De server valideert de bronhash, het veldpad, de onderliggende normale opslagrechten en de versie
van de vertaling. Een verouderd formulier of gewijzigde bron vraagt om opnieuw laden. Vertalingen
blijven gedeeld per identieke brontekst, zoals in de bestaande vertaalcache. Er is geen migratie nodig.
