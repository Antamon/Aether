# Synchronisatie hersteld op 17 november 2026

De actieve SFTP-profielen in de bovenliggende werkmap en aetherapp-dev gebruikten
relatieve remotePath-paden. Op deze hosting werden die vanaf de SSH-thuismap
opgelost. Daar stonden de nieuwe bestanden correct, terwijl Apache vanuit een
andere publieke webroot de oudere bestanden bleef leveren. Dit is vastgesteld
door de SHA-256-hashes via SFTP en publieke HTTPS-aanvragen te vergelijken.

Beide profielen verwijzen nu absoluut naar de bestaande publieke map onder
`webroots/www/aether/` en respectievelijk `webroots/www/aether/aetherapp-dev/`.
De tijdelijke map `tmp/` is van automatische uploads uitgesloten. Andere
verbindingsinstellingen en inloggegevens zijn behouden.

Alleen de expliciete frontendbestanden voor Onderzoek zijn gecontroleerd
bijgewerkt in beide publieke appmappen. Gewijzigde bestanden zijn eerst lokaal
geback-upt, atomair vervangen en via SFTP geverifieerd; VERSION is als laatste
geplaatst. Databasegegevens zijn niet gewijzigd.

Na afloop zijn VERSION, research.html, researchAdmin.js, beide stylesheets,
de QR-PDF-module, jsPDF en het PDF-lettertype op beide websites via HTTPS
opgehaald. Alle zestien controles kwamen overeen met de lokale SHA-256-hashes;
beide websites leverden versie 2026.11.17.1.

Gebruik de normale automatische synchronisatie; er is geen uploadpakket nodig.
