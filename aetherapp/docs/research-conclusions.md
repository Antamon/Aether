# Conclusions

Voer `0026_research_conclusions.sql` na 0024 en 0025 uit op beide databases,
vóór de nieuwe code gebruikt wordt. Bestaande discoveries blijven gewone
discoveries; hun QR-codes en voortgang veranderen niet.

Een challenge heeft geen conclusie-checkbox. Via **Discovery toevoegen →
Conclusie (zonder QR-code)** kan de administrator of director meerdere conclusions
maken. Elk heeft een eigen titel, vaardigheid, bonus-specialisaties en dezelfde
niveaus, varianten, media, opdrachten, timers en vervolgstappen als gewone
discoveries. De checkbox staat ook bij de algemene gegevens in bewerkmodus.
Zolang de discovery nog niet gevonden is, kan het type gewijzigd worden. Omzetten
naar een conclusie verwijdert de QR-token; terugzetten maakt een nieuwe token.

Een conclusion krijgt geen QR-token en komt niet op de QR-PDF. De scanroute
weigert ook een eventueel oud of handmatig ingevuld token van een conclusion.

De gebruiker krijgt actieve conclusions automatisch in het onderzoeksoverzicht
zodra alle gewone discoveries van die challenge volledig afgerond zijn door
het betreffende personage binnen hetzelfde event. Gevonden of bekeken is niet
voldoende als bevestigingen of vervolgstappen ontbreken. De bestaande afgeleide
afronding van persoonlijke/universele feedback blijft de bron van waarheid.
Een challenge zonder gewone discoveries geeft geen conclusions vrij.

Alle conclusions worden onafhankelijk vrijgegeven: de ene conclusion hoeft niet
afgerond te zijn om een andere te openen. Eventuele expliciete voorwaarden op
hun feedback blijven gelden. Gewone discoveries kunnen niet van hun eigen
conclusion afhankelijk worden: deze impliciete afhankelijkheid wordt meegenomen
in de cirkelcontrole.

Vrijgave wordt idempotent opgeslagen in de bestaande gevonden-discoveries- en
actietabellen, onder de bestaande onderzoekslock. Personages en events blijven
gescheiden. De controles gelden ook bij het openen, bevestigen en opvragen van
beschermde media. Bij opnieuw vervallen voorwaarden wordt geen conclusie-inhoud
teruggestuurd.

Gecontroleerd met servicetests (SQLite), browsercontroles, PHP-syntaxcontrole en
een gerichte QR-filtertest. De MariaDB-integratietest voert ook migratie 0026
tweemaal uit bij de volgende uitvoering tegen een aparte MariaDB-testserver.
