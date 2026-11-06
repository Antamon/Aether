# Snapshots verwijderen en aandelenhistoriek

28 oktober 2026.

Een eventsnapshot zonder effectieve aankoop mag verwijderd worden, ook als
er al aandelenzoekresultaten of aanbiedingen bestaan. Binnen dezelfde
transactie worden eerst de ontvangers, dan de aanbiedingen en daarna de
zoekbeoordelingen van de snapshot verwijderd. Ook aanbiedingen die via een
zoekbeoordeling gekoppeld zijn, vallen onder deze opruiming. Bedrijfsonderzoek
uit migratie 0022 verdwijnt via de bestaande cascade op de snapshot.

De eventbrede zoekregistratie blijft bestaan: die kan ook andere bedrijven
bevatten. Hun beoordelingen en aanbiedingen blijven onaangeroerd. Bij een
volgende zoekactie na het opnieuw maken van de snapshot ontbreekt de
beoordeling voor die snapshot en voert `aetherShareSearch()` deze opnieuw uit.
Het aanmaken van een snapshot alleen voert geen zoekactie uit.

Aankooptransacties voor hetzelfde bedrijf en event blokkeren verwijdering.
Ook een aankoopcorrectie, een gekocht aantal op een aanbieding of een
transactie gekoppeld aan een snapshotaanbieding blokkeert de actie. Zo blijft
de oorspronkelijke aankoopgeschiedenis beschermd, ook wanneer de aankoopdatum
later gecorrigeerd is of de aandelen inmiddels doorverkocht zijn. Er wordt
geen aankoop-, bank- of transactiehistoriek verwijderd.

De bestaande companylock serialiseert verwijderen met zoeken en kopen.
Een fout bij het verdere verwijderen draait de hele transactie terug.
Er is geen databasemigratie nodig. De code is gelijk in `aetherapp` en
`aetherapp-dev`.

## Nagekeken opslag en waardering

- Markt-, beheerders- en onderlinge aankopen bewaren het event in
  `tblCompanyShareTransaction.idEvent`, naast aantal, prijs en totaalbedrag.
  Een onderlinge verkoop is dezelfde `buy_from_holder`-transactie, met de
  koper in `idCharacter` en de verkoper in `idCounterpartyCharacter`.
  Een nog niet geaccepteerd verkoopaanbod is geen verkooptransactie.
- Draft-aankopen bij oprichting en oud overgenomen bezit kunnen geen event
  hebben. Inkoop van vrije aandelen door het bedrijf (`buyback_free`) heeft
  eveneens geen event. De opslag is dus niet voor elke historische of
  administratieve aandelenmutatie aan een event gekoppeld.
- De huidige portefeuillewaarde en aandelenstatus gebruiken aantal aandelen
  maal bedrijfswaarde gedeeld door uitgegeven aandelen van de laatste
  opgeslagen eventsnapshot, geselecteerd op eventdatum en daarna snapshot-ID.
  Ook toekomstige opgeslagen snapshots tellen mee. Zonder eventsnapshot geldt
  de oprichtingssnapshot, en zonder vastgelegde waarde de bedrijfsrecord.
  De aankoopprijs speelt geen rol. De afzonderlijke `shareMarketPrice` wordt
  getoond als marktprijs, maar is niet de factor voor deze intrinsieke waarde.
  Een afwijkende marktprijs kan dus een andere marktwaarde opleveren.

`tests/company_snapshot_delete_test.php` controleert de opruiming met echte
SQL en foreign keys, aankoopblokkades, behoud van andere bedrijven/events,
opnieuw kunnen beoordelen en rollback. De bestaande aankoop- en
snapshotwaarderingstests controleren transacties per event en de gebruikte
waarderingsbron.
