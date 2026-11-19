# Nederlandse en Engelse spelinhoud

De bestaande taalkeuze naast de verwelkoming bepaalt zowel interface als spelinhoud.
De WordPress-profieltaal blijft het uitgangspunt; de handmatige keuze per gebruiker
op dit toestel heeft voorrang. De app blijft PHP/PDO en browser-JavaScript gebruiken.
Er is geen Node.js-runtime nodig op de hosting. De optionele Google-koppeling
gebruikt PHP/cURL; zie [google-translation.md](google-translation.md).

## Installatie

Voer op elke appdatabase achtereenvolgens uit:

1. `sql/migrations/0027_content_translations.sql`: voegt uitsluitend de vertaaltabel toe.
2. `sql/migrations/0028_content_translation_seed.sql`: importeert de eerste vertalingen
   van `c8q4331sl_oneiros_be_aether.sql` van 19 november 2026.

Beide bestanden zijn herhaalbaar. De seed gebruikt `INSERT IGNORE`, zodat een later
handmatig verbeterde vertaling niet wordt overschreven. Bestaande personages,
vaardigheden, feedbackroutes, timers, aandelen en andere spelgegevens veranderen niet.
Upload ook de gewijzigde PHP, JavaScript en HTML met de bijgewerkte assetversies.
Ontbreekt de nieuwe tabel nog, dan blijft de app de oorspronkelijke tekst tonen.

## Wat wordt vertaald?

Alle gevulde tekstvelden van de actieve spelinhoud uit de export: achtergronden en
persoonlijkheid, dagboeken en roddels, vaardigheden en specialisaties, categorieën,
statuseigenschappen en hun groepen, relaties, bedrijfsbeschrijvingen en -gebeurtenissen,
functieomschrijvingen, challenges, discoveries en feedback, eventbeschrijvingen,
talen, nationaliteiten, actie-resultaten en beschrijvingen van effectenverrichtingen.
Ook de bestaande uitleg van bedrijfstypes is meegenomen.

Eigen namen van personen, bedrijven en events, adressen, bestandsnamen, interne
codes, IDs, audit-/transactiereplays en oude niet-gebruikte legacytabellen blijven
ongewijzigd. Media zelf worden niet vertaald. De dekking staat zonder spelinhoud in
`content-translation-coverage.json`.

De eerste vertalingen zijn lokaal automatisch gemaakt met de Marian-modellen
[opus-mt-nl-en](https://huggingface.co/Xenova/opus-mt-nl-en) en
[opus-mt-en-nl](https://huggingface.co/Xenova/opus-mt-en-nl), met een aanvullende
woordenlijst voor speltermen. Ook oorspronkelijk Engelse en gemengde teksten krijgen
een Nederlandse weergave. HTML wordt via de bestaande HTMLPurifier verwerkt.
Het betreft machinevertalingen: lange verhalen en gespecialiseerde speltermen kunnen
redactionele verbetering nodig hebben. Voor die eerste import zijn geen spelteksten
naar een externe vertaaldienst gestuurd. Nieuwe korte teksten kunnen nu via de
afzonderlijk te activeren Google-koppeling worden vertaald.

## Bron, actualiteit en toegang

`tblContentTranslation` koppelt de SHA-256-hash van de exacte brontekst aan een
doeltaal. Een gewijzigde brontekst kan daardoor nooit de vertaling van een oudere
versie krijgen. Nieuwe of gewijzigde teksten vallen terug op de brontekst zolang
er geen vertaling van die nieuwe versie bestaat. De Google-koppeling vult korte
teksten op de achtergrond aan. Teksten boven 2.000 tekens worden alleen handmatig vertaald.

De API ontvangt optioneel `X-Aether-Content-Language: nl|en`. Pas na de bestaande
autorisatie en redactie worden vertalingen opgezocht van tekst die daadwerkelijk
in dat antwoord staat. Alleen dat antwoord krijgt een vertaalsupplement mee.
Er is geen publiek woordenboek met verborgen onderzoeksfeedback of personagegegevens.
Andere niveaus, toekomstige stappen en media behouden hun bestaande toegangsregels.
API-clients zonder de nieuwe header behouden het bestaande antwoordcontract.

`js/mainFunctions.js` registreert het supplement in het geheugen en geeft het
oorspronkelijke antwoord terug aan de bestaande appcode. `js/contentLanguage.js`
vertaalt zichtbare tekst en rich-textweergaven. Bewerkbare velden, contenteditable,
optionwaarden, bronteksten en de objecten voor opslag blijven origineel. Een Engelse
view opslaan via een editor overschrijft dus niet onbedoeld de Nederlandse bron.
QR-kaarttitels en spelinhoud in de paspoort-PDF gebruiken eveneens de gekozen taal.

Er worden geen spelteksten of vertalingen in localStorage opgeslagen; alleen de
taalvoorkeur wordt daar onthouden. Herladen bouwt de geautoriseerde inhoud opnieuw op.

## Lokale generatie en controle

`tools/translate-content-offline.cjs` is uitsluitend een ontwikkelhulpmiddel. Het leest
een export zonder SQL uit te voeren. De lokale modellen en tijdelijke tekstbestanden
staan buiten de webroot in `%TEMP%/aether-content-translation`. De lokale runtime
bevat `@huggingface/transformers` en `franc-min`; PHP CLI heeft DOM en mbstring nodig.
De bronbestanden en modellen worden niet als runtimeafhankelijkheid mee gehost.

Gebruik `node tools/translate-content-offline.cjs pad/naar/export.sql` voor voorbereiden,
lokaal vertalen en seedgeneratie. De afzonderlijke fases zijn `--prepare`, `--translate`
en `--seed`. De segmentcache maakt een onderbroken vertaalronde hervatbaar.
Controleer de gegenereerde seed voordat die wordt toegepast. Handmatige verbeteringen
kunnen per exacte bronhash en doeltaal in `tblContentTranslation` worden aangebracht.

Gerichte controles: `tests/content_language_test.php` en
`tests/content_language_browser_test.cjs` en `tests/content_translation_seed_test.php`.
Die verifiëren Nederlands/Engels, verborgen
inhoud, ontbrekende/verouderde vertalingen, HTML-veiligheid, oorspronkelijke API-data,
rijke editors, dropdownwaarden en later toegevoegde DOM-inhoud.

Bij deze implementatie slagen ook de bestaande interface-, rol-, karakterlees-,
dagboek-, vaardigheden-, eigenschappen-, onderzoeks-, aandeeltransactie-,
waarderings-, assetversie- en responscontroles. De brede oude
`company_module_endpoints_test.php` kan niet als geslaagde controle worden gemeld:
zijn tijdelijke fixture mist vendor/autoload en bevat conflicterende financiële
stubs. De gerichte bedrijfs- en aandelencontroles slagen wel. De migraties zijn
structureel en met een geïsoleerde SQLite-import gecontroleerd; ze zijn niet op
de online MariaDB-databases uitgevoerd.
