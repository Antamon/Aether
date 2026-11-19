# Eigenschappen beheren

Bereikbaar via de bestaande admin-sidebar, **Eigenschappen** (`admin.html?tab=traits`).
De pagina gebruikt dezelfde page nav en 2/3–1/3-indeling als Vaardigheden.
De twee panelen staan rechtstreeks naast elkaar binnen de standaard vaste
zetbreedte van de pagina, zonder extra rij-wrapper of dubbele zijruimte.
De dropdown bevat alleen de namen van de bestaande eigenschappen uit `tblTrait`.
**Nieuwe eigenschap** opent een
lokaal concept. **Edit**, **Opslaan** en **Annuleren** volgen de bestaande stijl.
Er wordt niets automatisch opgeslagen. Een nieuwe eigenschap wordt in één
mutatie volledig aangemaakt; annuleren bewaart niets. Bij fouten blijft het
ingevulde formulier beschikbaar.

De page nav heeft drie klassetabs: **Hogere klasse**, **Middenklasse** en
**Lagere klasse**. Groepen en eigenschappen worden beperkt tot de actieve
klasse; eigenschappen voor alle klassen blijven bij elke klasse beschikbaar.
Een groep die niet bestaat in de gekozen klasse wordt gewist bij het wisselen
van tab. De labels Groep en Eigenschap staan vóór hun dropdown. Tabs en filters
worden tijdens bewerken vergrendeld. Nieuwe eigenschappen nemen standaard de
actieve klasse en groep over.

Beheerbare velden: naam, beschrijving, type (status, beroep, kwaliteit), klasse,
groep, rangtype, uniek/gegroepeerd, kost, inkomsten, evolutie en personeelsvoorwaarde.
Bedragen die in de bestaande tabel nullable zijn, kunnen leeg blijven. Evolutie
accepteert een punt of komma en maximaal twee decimalen. Alle inhoud is gewone
tekst, conform de bestaande vaardigheidseditor; weergave gebruikt textContent.

Bij gekoppelde eigenschappen zijn type, klasse, groep en ranginstellingen
beveiligd tegen structurele wijzigingen. De overige velden blijven bewerkbaar.
Trajecten, vlaggen, aandelenmetadata en personagekoppelingen worden niet
overschreven door deze editor. De rechterkolom toont uitsluitend actieve
personages, per rang, met de gedeelde personagekaarten in drie kolommen en
horizontale scheidingslijnen tussen de rangen.

`api/admin/traits.php` gebruikt bestaande sessies, administrator/director-rechten,
CSRF, validatie en idempotentie. `adminTraitService.php` bevat PDO-query's,
transactieve opslag, dubbele-invoercontrole en een versievingerafdruk die
achterhaalde wijzigingen afwijst. Geen extra rechten op algemene configuratie.
Geen databankmigratie nodig.

Getest: bestaande admin/user endpointtests met extra rol-/CSRF-controles,
SQLite-servicetest voor aanmaken, wijzigen, nullable waarden, decimalen,
validatie, actieve personages, metadata en gelijktijdige wijzigingen;
browsertest voor beide adminpagina's inclusief fouten, annuleren en mobiel.
