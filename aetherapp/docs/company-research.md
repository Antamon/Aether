# Bedrijfsinformatie in de economieactie

Versie 2026.10.27.2 gebruikt Economie **vaardigheid-ID 6**, Aandelenmarkt
**specialisatie-ID 72** en Auditeren **specialisatie-ID 73**. De naam van een
vaardigheid of specialisatie heeft geen invloed op de berekening.

## Aandelen vinden

Aandelenmarkt verhoogt het effectieve niveau met één, tot maximaal 4. Auditeren
beïnvloedt deze worp niet. De bestaande niveaus 1–3 blijven behouden; niveau 4
zet de reeks voort. Hoeveelheden zijn percentages van de vrije aandelen en
prijsaanpassingen zijn percentages boven/onder de snapshotmarktprijs.

| Effectief niveau | Vindkans | Hoeveelheid | Prijsaanpassing |
| --- | --- | --- | --- |
| 1 | 30% | 1–10% | +10 tot +40% |
| 2 | 50% | 10–30% | 0 tot +20% |
| 3 | 70% | 30–60% | −10 tot +10% |
| 4 | 90% | 60–90% | −20 tot 0% |

Bestaande zoekresultaten worden niet opnieuw geworpen. Nieuwe bedrijfsgebeurtenissen
kunnen via opnieuw onderzoeken alsnog een eerste beoordeling krijgen.

## Gebeurtenissen achterhalen

Auditeren verhoogt voor deze worp het niveau met één. Aandelenmarkt beïnvloedt
deze kans niet. De getallen volgen de aangeleverde tabel letterlijk:

| Zichtbaarheid | Niveau 1 | Niveau 2 | Niveau 3 | Niveau 4 |
| --- | --- | --- | --- | --- |
| 1 | 60% | 80% | 100% | 100% |
| 2 | 40% | 60% | 80% | 100% |
| 3 | 20% | 40% | 60% | 80% |
| 4 | 0% | 20% | 40% | 60% |
| 5 | 0% | 0% | 20% | 40% |

Bij het openen van een bedrijf in **Onderzoek bedrijf** bewaart de applicatie één uitslag per personage,
snapshot en gebeurtenis, ook als er geen verhandelbare aandelen zijn. De onderzoeksaanroep
bevat het bedrijfs-ID en onderzoekt alleen dat bedrijf. Alleen
gebeurtenissen tot en met het snapshotkwartaal komen in aanmerking. Lezen of
verversen voert geen worp uit. Bekende informatie wordt meegenomen naar latere
snapshots; een latere mislukte worp maakt een eerdere ontdekking niet ongedaan.
Een beheerder kan kennis expliciet toevoegen of verwijderen. Bij een gelijktijdige
correctie volgt een conflictmelding in plaats van overschrijven.

De bedrijfsweergave geeft gegevens uit de geselecteerde snapshot, een waardegrafiek
tot die snapshot en bekende gebeurtenissen. De browser van een deelnemer ontvangt
geen beschrijvingen van onbekende gebeurtenissen. De beheerder kan in Edit alle
in aanmerking komende gebeurtenissen aanvinken. Bewaren van een kwartaal behoudt
de gebeurtenis-ID's, zodat tekstcorrecties bestaande kennis behouden. Verwijderen
van een gebeurtenis verwijdert de bijbehorende kennis via de databasekoppeling.

## Uitrol en controle

1. Voer `sql/migrations/0022_company_research.sql` uit op beide databases na 0016.
2. Upload de gewijzigde PHP-, JavaScript-, CSS- en HTML-bestanden.
3. Open een personage, kies de actie Economie en open een bedrijfstegel om het onderzoek te starten.
4. Controleer met een deelnemer de bekende gebeurtenissen en met een beheerder Edit.
5. Controleer dat opnieuw openen dezelfde kennis toont en dat volgende snapshots
   bestaande ontdekkingen meenemen.

Lokale regressies: `tests/company_research_test.php`,
`tests/company_research_ui_test.cjs`, `tests/share_rules_test.php` en de bestaande
aankoop- en kwartaaltests. De SQL-migratie wordt handmatig online uitgevoerd.

Vanaf versie 2026.10.27.3 toont de actietab eerst de beschikbare actietegels.
Na het kiezen van een actie verschijnt de inhoud met **Toon acties** rechtsboven.
Alleen admins en directors kunnen een event kiezen; deelnemers gebruiken het
huidige of eerstvolgende event. De economieactie toont geen zoekknop of algemene
eventstatistieken. De zoekresultaten worden automatisch opgeslagen bij het openen
van het geselecteerde bedrijf; terugkeren naar de lijst voert geen onderzoek uit.

Vanaf versie 2026.10.27.4 staat de geselecteerde bedrijfsinformatie rechtstreeks
in de overkoepelende data-card, met **Alle bedrijven tonen** linksboven. De grafiek
heeft dezelfde zoombalken als de bedrijfpagina: horizontaal 25–300% en verticaal
100–400%. Bij inzoomen kan de grafiek worden gescrold of met de muis versleept.
Zichtbaarheidsniveaus van gebeurtenissen worden uitsluitend naar admins/directors
gestuurd en alleen bij hen getoond. De werkende Edit-knop bij de lijst van bekende
gebeurtenissen blijft beschikbaar voor deze rollen.

Vanaf versie 2026.10.27.5 staat het geselecteerde bedrijf rechtstreeks in het
overkoepelende Aether-paneel, zonder action-card eromheen. De terugknop staat
rechts van logo en bedrijfsnaam. De zes bedrijfsfeiten staan op grote schermen
in drie gelijke kolommen; elke gebeurtenis heeft een eigen data-card.

Admins/directors kunnen onder **Gevonden aandelen** een vondst toevoegen of het
aantal en de prijs per aandeel aanpassen. `saveShareDiscovery.php` gebruikt de
bestaande transacties en controleert rol, personage, bedrijfssnapshot en vrije
voorraad. Een succesvolle wijziging krijgt een auditregel. Een vondst vereist
een beursgenoteerd bedrijf met snapshotmarktprijs. Gedeelde of gedeeltelijk
gekochte aanbiedingen kunnen niet worden gewijzigd; bestaande aankopen blijven
behouden. Dit wijzigt geen bankrekening of bedrijfswaarde en vereist geen nieuwe
migratie naast 0022.
