# Aandelen tijdens Draft

## Ingebruikname

Voer eerst `sql/migrations/0015_draft_share_capital.sql` uit op
`oneiros_be_aether` en `oneiros_beaetherdev`. Het script voegt de gewone
statuseigenschap Aandelen toe, herkenbaar aan de metadatavlag `share_capital`.
Het wijzigt geen bestaande aandelenbezittingen, procentuele migratiebronnen,
banksaldi of transacties. Het gebruikt de bestaande Draft-budgettabel en is
herhaalbaar.

Werk daarna in beide appmappen deze bestanden bij:

- `api/shares/shareDraftBudget.php` (nieuw)
- `api/shares/shareDraftService.php` (nieuw)
- `api/shares/buyDraftShares.php` (nieuw)
- `api/shares/shareService.php`
- `api/shares/shareWriteHttp.php`
- `api/characters/traitUtils.php`
- `api/characters/characterTraitService.php`
- `api/characters/updateTrait.php`
- `api/characters/characterPointUtils.php`
- `api/characters/characterReadService.php`
- `api/characters/characterService.php`
- `api/characters/characterLanguageRepository.php`
- `api/characters/characterSkillRepository.php`
- `js/traitsCharacter.js`
- `js/shareInterface.js`
- De gewijzigde HTML-pagina's met cacheversies: `index.html`, `companies.html`
  en `leaderPersonnel.html`.
- `VERSION`

Als je alle API-, JS- en CSS-bestanden overzet, blijven buiten die mappen de
HTML-pagina's en `VERSION` over. Het SQL-bestand wordt via de databasebeheerder
uitgevoerd.

## Werking

Tijdens Draft wordt Aandelen met de gewone traitknoppen gekozen, verhoogd,
verlaagd of verwijderd. De rang kost evenveel statuspunten en bepaalt het
totale startkapitaal: 600.000 / 1.200.000 / 2.500.000 / 5.000.000 / 10.000.000 /
20.000.000 Fr voor rang 1 t/m 6; daarna verdubbelt het bedrag per rang.
Verlagen of verwijderen kan niet als het nieuwe budget lager is dan wat al
besteed is. Een rangverhoging behoudt eerdere bestedingen.

In Economie toont de aandelenportefeuille het totale, bestede en beschikbare
startkapitaal. Alle geïnitialiseerde bedrijven zijn beschikbaar, ook zonder
beursnotering of zoekactie, mits er een marktprijs op hun snapshot staat.
De gewone deelnemer koopt tegen de marktprijs van de recentste event-snapshot
van elk bedrijf. Als er nog geen event-snapshot is, geldt de oprichtingssnapshot.
Een recente snapshot zonder marktprijs wordt niet stilzwijgend vervangen door
een oudere prijs. Admins/directors mogen expliciet een andere snapshot kiezen.

De server haalt de prijs zelf op; een browserprijs of vaardigheidskorting wordt
niet geaccepteerd. Historisch onbekende prijzen worden niet geraden. Het bezit
en de actuele vrije voorraad worden bij de aankoop gecontroleerd. Het betaalde
bedrag verhoogt de actuele bedrijfswaarde, ook als een historische prijs is
gekozen; bestaande snapshots blijven bewaard. De aankoop gebruikt uitsluitend
het Draft-kapitaal, niet het banksaldo. De transactie bewaart de snapshotbron,
het event (indien aanwezig), het aantal, de prijs en de actor.

Bij iedere overgang uit Draft wordt het budget afgesloten in dezelfde
databasetransactie als de statuswijziging. Het ongebruikte bedrag vervalt en
wordt niet op de bank gezet. Terugzetten naar Draft heropent een afgesloten
budget niet automatisch. Buiten Draft volgen de rang, personeelsvoorwaarde
en gebruikte statuspunten de actuele intrinsieke portefeuillewaarde; de
handmatige rangknoppen verdwijnen. Bestaande actieve aandeelhouders krijgen
de berekende eigenschap Aandeelhouder ook zichtbaar zonder een nieuwe aankoop
in statuspunten. Positief aandelenbezit maakt de eigenschap zichtbaar, ook als
de portefeuille onder de grens voor rang 1 blijft en de berekende rang 0 is.
Personages zonder bezit en met rang 0 krijgen geen extra eigenschap toegevoegd.

## Verificatie

Gerichte tests controleren gewone traitacties en ranggrenzen, budgetbesteding,
bankbehoud, historische prijzen en rolrechten, idempotentie, afgeschermd bezit,
afgesloten budgetten, behoud van bestaande aankopen en de aankoopformulieren.
De read-, update-, skill- en trait/diary/language-endpointtests controleren
de betrokken personagefuncties. Er is geen live database gemigreerd.
