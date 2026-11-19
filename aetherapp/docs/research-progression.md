# Persoonlijke en universele onderzoeksvoortgang

Voer `sql/migrations/0025_research_progression.sql` uit na 0024 op beide
databases, vóór de aangepaste beheerinterface gebruikt wordt. De migratie is
herhaalbaar en behoudt persoonlijke voortgang voor bestaande discoveries.

Bij een discovery kiest de administrator of director persoonlijke of universele
voortgang. Na de eerste scan ligt deze structurele instelling vast.

- Persoonlijk: ieder personage begint bij de eerste stap en doorloopt de eigen
  geselecteerde feedbackroutes.
- Universeel: afgehandelde stapnummers worden binnen de discovery en het event
  gedeeld. Een latere scanner ziet de actuele eigen toegestane feedback of de
  lopende wachttijd, zonder oude opdrachten opnieuw uit te voeren. Andere
  vaardigheidsniveaus en varianten blijven afgeschermd. Universele timers zijn
  gedeeld, ook als de tijdsduur oorspronkelijk op per-personage stond.

Getoond en bevestigd blijven per personage geregistreerd. Universele afrondingen
registreren apart wie welke stap wanneer afhandelde. Het voortgangsscherm toont
dit apart van een eigen bevestiging. Bestaande transactielocks en unieke sleutels
beveiligen gelijktijdige verzoeken en herhaalde bevestigingen.

Zonder bevestiging rondt het daadwerkelijk tonen van feedback de stap af. Met
bevestiging gebruikt de deelnemer de knop **Afgerond**, ook als er geen vervolg is.

Een tijdsduur of vast tijdstip heeft twee toepassingen:

- **Feedback zichtbaar maken**: zet de voorwaarde op de vervolgstap. De vorige
  stap kan al afgerond zijn; tot het eindmoment ziet de gebruiker een wachttijd.
- **Knop Afgerond beschikbaar maken**: zet de voorwaarde op de huidige opdracht
  en vink bevestiging aan. De tekst is direct leesbaar, de knop blijft geblokkeerd
  tot het eindmoment. Een duur kan bij het tonen van deze opdracht starten;
  bewaar de stap eerst en selecteer haar daarna als startende feedbackstap.

De vereenvoudigde feedbackmodal regelt dit nu automatisch via **Vervolgstap
toevoegen → Tijd instellen**. Bij **Geen tijd** is **Bevestiging nodig** verplicht.
Bij **Timer** worden dagen, uren, minuten en seconden ingevoerd; de klok start
altijd zodra de huidige feedback daadwerkelijk getoond is. **Vast tijdstip**
gebruikt de ingevoerde datum en tijd in de tijdzone van het gekoppelde event.
Zonder bevestiging wordt de geopende stap automatisch afgerond zodra de tijd
verstreken is en komt de vervolgstap beschikbaar. Met bevestiging wordt op dat
moment de knop **Afgerond** beschikbaar. De gebruiker hoeft geen bronhandeling
of tijdsdoel meer te kiezen. Eerder ingestelde voorwaarden blijven bewaard.

De overige voorwaarden en de normale stapvolgorde blijven gelden. Alle
voorwaarden moeten vervuld zijn. Een verborgen countdown verbergt alleen de
resterende tijd, niet het klokicoon. Klokken en universele voortgang worden nooit
tussen events gedeeld of bij opnieuw scannen gereset.

Getest in een tijdelijke MariaDB: 0024 en 0025 tweemaal uitvoeren, late scans,
universele bevestiging en herhaling, eventisolatie, gedeelde wachttijd,
tijdsduur vanaf het tonen van een opdracht, datum/tijd op de afrondknop, reguliere
persoonlijke stappen, rollen, media, bestaande timer- en concurrencyregels.
Daarnaast browsercontroles voor de beheerschermen en deelnemersbevestigingen.

## Vervolgstap verwijderen

De modal van een vervolgstap heeft **Stap verwijderen**, naast Opslaan. Na
bevestiging verdwijnen die stap en alle latere stappen uit dezelfde route.
De vorige stap behoudt haar tekst en bevestigingskeuze, maar haar timers en
datums vervallen. Omdat er geen latere stap meer bestaat, staat **Vervolgstap
toevoegen** weer uit. Media bij verwijderde stappen worden mee verwijderd;
gekoppelde kopieën bij andere feedback blijven behouden.

Ook de eerste feedbackstap kan verwijderd worden. Bij een variant verdwijnt
dan de hele variant; bij een algemeen hoger niveau wordt de lagere feedback
weer gebruikt. Ongetraind krijgt opnieuw de verplichte standaardtekst
“No additional information”. De bestaande vergrendeling van
al gevonden discoveries blijft gelden. Verwijzingen vanuit andere feedback
moeten eerst verwijderd worden; anders weigert de server de gehele actie.
Versiecontrole op de gekozen én de vorige stap voorkomt overschrijven van
tussentijdse wijzigingen. De mutatie gebruikt de bestaande transactie,
onderzoekslock, autorisatie en idempotentielaag. Geen extra migratie nodig.
