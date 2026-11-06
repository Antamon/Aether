# Bedrijfsevolutie per kwartaal

De oprichting, snapshots en handmatig bewaarde kwartalen zijn vaste punten.
Bedrijfswaarde, marktprijs per aandeel, stabiliteit en rendabiliteit worden
lineair verdeeld tussen die punten. Het aantal uitgegeven aandelen blijft
tussen vaste punten gelijk aan het voorgaande aantal. Na het laatste vaste
punt blijven de cijfers gelijk totdat een nieuw punt wordt toegevoegd.

Het grafiekbereik loopt minstens tot het huidige spelkwartaal (Belgische
kalenderdatum min 100 jaar) en omvat het eerstvolgende eventkwartaal, ook
zonder aangemaakte snapshot. Verder gelegen aangemaakte bedrijfssnapshots
worden eveneens volledig meegenomen, tot en met hun laatste eventkwartaal.

Een nieuwe of gewijzigde snapshot berekent de tussenliggende kwartalen opnieuw.
De beheerder kan daarna in de grafiek meerdere punten verslepen of via de
kwartaalmodal aanpassen en alles in één keer bewaren. Snapshots zelf blijven
vaste punten; gebeurtenissen per kwartaal blijven bewaard.

Migratie 0016 is nodig voor de kwartaalhistoriek. Migratie 0018 voegde vroeger
`curveSeed` toe, maar de huidige code gebruikt deze kolom niet meer. Een reeds
uitgevoerde migratie hoeft niet teruggedraaid te worden.
