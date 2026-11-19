# Vaardigheden beheren

De twee kolommen staan rechtstreeks in de paginarij, elk met een eigen Aether
panel, zonder extra rij-wrapper. De standaard vaste zetbreedte van de pagina
blijft behouden; de rijmarges compenseren uitsluitend de containerpadding zodat
er geen extra zijruimte rond de panelen ontstaat.

De bestaande adminpagina begint voor vaardigheden in kijkmodus. **Edit** opent
de bewerkmodus. Alleen **Opslaan** schrijft de wijzigingen weg.
Typen, focusverlies, categorieën aanvinken en opties toevoegen of verwijderen
veranderen uitsluitend het lokale concept. Een fout bij opslaan behoudt de
invoer en bewerkmodus. De vaardigheidskeuze en knop voor een nieuwe vaardigheid
staan in de page nav boven de panelen en zijn tijdens bewerken uitgeschakeld.
Een nieuwe vaardigheid wordt pas bij het
verlaten van bewerkmodus aangemaakt; alle ingevulde velden blijven daarbij bewaard.
Annuleren staat bij nieuwe en bestaande vaardigheden naast opslaan, in dezelfde
groep rechtsboven. Annuleren verwijdert het lokale concept zonder API-aanroep en
herstelt de eerder gekozen vaardigheid (of de lege kijkmodus).

Disciplinevaardigheden hebben aparte blokken voor disciplines en specialisaties.
Het optionele `kind` in het bestaande saveSkill-contract maakt expliciete opslag
van beide typen mogelijk. Oudere clients zonder kind behouden hun eerdere
gedrag. Een bestaande optie kan niet ongemerkt van type veranderen. Rollen,
CSRF, idempotentie, transacties en bescherming van gekoppelde opties zijn behouden.

De personagekolom neemt op grote schermen een derde van de breedte in. Per niveau
staan alleen actieve personages in drie kolommen met portret, naam (link naar de character sheet) en de
specialisaties voor de gekozen vaardigheid. De kaarten hergebruiken de
portret- en naamklassen van de personage-sidebar. Portretten gebruiken de bestaande portrait-resolver. Op
kleine schermen staan beide panelen onder elkaar.
De niveaugroepen hebben alleen een titel en een horizontale scheidingslijn;
er zijn geen kaders rond de groepen. De kijkmodus toont onder de vaardigheidsnaam
de kommagescheiden lijsten Categorie, Discipline (waar van toepassing) en
Specialisaties, gevolgd door de beschrijving zonder titel. De drie
bekwaamheidsniveaus staan in een tabel zonder koprij.

Geen schemawijziging. Getest met de bestaande PHP endpointtest, aangevuld met
gemengde disciplines/specialisaties en validatie. De browsertest controleert
kijk- en bewerkmodus, geen autosave, gezamenlijk opslaan, behoud van invoer bij
fouten, nieuwe vaardigheden, portretten, kolombreedte en mobiel formaat.
