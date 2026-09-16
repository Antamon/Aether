# Herstel toegangscontrole

Datum: 15 september 2026.

Scope: authenticatie, rollen, eigenaarschap, bevoegdheidsvelden en CSRF in `aetherapp-dev`.

## Samenvatting

De rollen in de applicatiedatabase zijn exact `participant`, `director` en `administrator`. De server haalt de gebruikers-ID uit de PHP-/WordPress-sessie en leest de rol daarna opnieuw uit `tblUser`. Een rol uit de requestbody of uit een oude sessiewaarde geeft geen bevoegdheid meer. Een onbekende of ontbrekende databankrol wordt geweigerd.

De eerder onbeschermde of gedeeltelijk beschermde API-routes controleren nu ook bij een rechtstreekse aanroep:

- of er een aangemelde gebruiker met een geldige databankrol is;
- of `director`/`administrator` vereist is voor events, gebruikerslijsten, bedrijven en beheergegevens;
- of de gebruiker het concrete personage mag lezen of wijzigen;
- of publieke of geheime vaardigheden voor die rol toegankelijk zijn;
- of een schrijfverzoek een geldig sessiegebonden CSRF-token bevat.

Bij nieuwe personages bepaalt de server `createdBy`, `createdAt` en `state`. Voor een participant bepaalt de server bovendien `idUser`, `type` en de gratis gezondheidsvelden. Bij updates mag een participant `idUser`, `type` of `state` niet wijzigen; `createdBy` en `createdAt` zijn via deze API voor geen enkele rol wijzigbaar. Authenticatie op beschermde API-routes maakt niet langer automatisch een ontbrekende `tblUser`-rij aan, zodat een later geweigerd verzoek geen registratieneveneffect heeft. Registratie als `participant` gebeurt uitsluitend via de login/bootstrap-routes.

De WordPress-bootstrap vernieuwt na succesvolle authenticatie het PHP-sessie-ID. De frontend ontvangt het CSRF-token via `checkLogin.php` of `getCurrentUser.php`; de centrale JSON-helper en de twee uploadflows sturen dit token mee bij wijzigingen.

## Uitkomst controle OIDC-callback

`tokenhandler.php` was op de geconfigureerde productie-URL publiek bereikbaar: een read-only GET zonder autorisatiecode antwoordde op 15 september 2026 met HTTP 200. In de huidige applicatiecode staat echter geen link, redirect of andere login-initiator die deze callback aanroept. De actieve authenticatieroute gebruikt `sessionUserBootstrap.php` om een al door WordPress aangemelde gebruiker via de WordPress-cookie te laden.

De [officiële miniOrange-documentatie](https://developers.miniorange.com/docs/oauth/wordpress/server/openid-discovery) geeft voor deze WordPress-provider een clientgebonden discovery-URL. Het discovery-document van de bestaande provider was bereikbaar en publiceerde een issuer, authorization endpoint, token endpoint, userinfo endpoint, JWKS-URL en ondersteuning voor HS256 en RS256. Dat maakt provideridentificatie mogelijk, maar de applicatie mist de andere helft van de flow: er wordt nergens vooraf een willekeurige, sessiegebonden `state` en `nonce` aangemaakt of opgeslagen. Ook is uit de code niet vast te stellen welk van de twee gepubliceerde signing-algoritmen voor deze client is ingesteld.

Daarom is de losse callback lokaal fail-closed uitgeschakeld. `tokenhandler.php` antwoordt nu altijd met HTTP 410, `Cache-Control: no-store` en maakt geen sessie aan, wisselt geen code om en verwerkt geen token. De WordPress-cookieauthenticatie is niet gewijzigd. Deze wijziging is niet gedeployed; de publiek bereikbare versie verandert pas bij een afzonderlijke deployment.

## Rechtenmatrix

`Eigen` betekent dat `tblCharacter.idUser` gelijk is aan de server-side vastgestelde gebruikers-ID. `Beheerrol` betekent `director` of `administrator`.

| Handeling | Participant | Director | Administrator | Voorwaarden |
| --- | --- | --- | --- | --- |
| Personagelijst | Eigen personages | Alle personages | Alle personages | Browserveld `role` wordt genegeerd |
| Personage lezen | Eigen speler en eigen extra | Alle | Alle | Controle op het opgevraagde personage vóór onderliggende detailqueries |
| Personage aanmaken | Eigen speler | Voor gekozen gebruiker/type | Voor gekozen gebruiker/type | Altijd `draft`; maker en aanmaaktijd komen van de server |
| Algemene personagegegevens wijzigen of personage verwijderen | Eigen speler | Alle | Alle | Objectcontrole geldt ook bij gemanipuleerd ID |
| Eigenaar, type of status wijzigen | Niet toegestaan | Toegestaan | Toegestaan | `createdBy` en `createdAt` zijn via de update-API nooit wijzigbaar |
| Klasse, betaalde gezondheid en traits wijzigen | Eigen speler in `draft` | Alle | Alle | Gratis gezondheidsvelden blijven voorbehouden aan beheerrollen |
| Vaardigheden en specialisaties wijzigen | Eigen speler; alleen publieke vaardigheid | Alle, inclusief geheim | Alle, inclusief geheim | Participant kan via specialisatie-input geen globale discipline maken |
| Talen wijzigen | Eigen speler | Alle | Alle | Objectcontrole op het personage |
| Portret wijzigen | Eigen speler of eigen extra | Alle | Alle | Objectcontrole op het personage |
| Achtergrondsecties en relaties wijzigen | Eigen speler | Alle | Alle | Participant kan als relatiedoel alleen een actieve speler kiezen |
| Dagboek lezen | Eigen speler of eigen extra | Alle | Alle | Objectcontrole op het personage |
| Dagboek wijzigen | Volledig voor eigen speler; alleen prestaties voor eigen extra | Alle velden | Alle velden | Bestaande bedoelde uitzondering voor eigen extra blijft behouden |
| Personageacties bekijken/uitvoeren | Eigen speler of eigen extra | Alle | Alle | Objectcontrole en CSRF bij uitvoeren/vrijspelen |
| Economie bekijken | Eigen personage | Alle | Alle | Detailacties behouden hun bestaande aanvullende spelregels |
| Bankbedrag, banktransfer en economy-snapshot wijzigen | Niet toegestaan | Niet-draft personages | Niet-draft personages | Verwijderen van banktransacties blijft een beheerhandeling |
| Effecten beheren | Eigen niet-draft personage | Alle niet-draft personages | Alle niet-draft personages | Goedkeuren van snapshots blijft een beheerhandeling |
| Bedrijfsaandelen beheren | Eigen speler volgens bestaande actievoorwaarden | Speler/extra volgens bestaande actievoorwaarden | Speler/extra volgens bestaande actievoorwaarden | Kopen/verlagen niet in `draft` |
| Eventlijst en deelname lezen | Eventlijst met eigen deelname | Mag deelname van gekozen gebruiker lezen | Mag deelname van gekozen gebruiker lezen | Participant kan een ander `idUser` niet gebruiken |
| Event of deelname wijzigen | Niet toegestaan | Toegestaan | Toegestaan | CSRF verplicht; identiteit komt niet uit de browser |
| Gebruikerslijst lezen | Niet toegestaan | Toegestaan | Toegestaan | Rechtstreekse API-aanroep is beschermd |
| Bedrijven lezen of wijzigen | Niet toegestaan | Toegestaan | Toegestaan | CSRF verplicht voor wijzigingen en uploads |
| Vaardigheden beheren in adminmodule | Niet toegestaan | Toegestaan | Toegestaan | Geheime inhoud blijft afgeschermd |
| Vaardigheidscategorieën beheren | Niet toegestaan | Niet toegestaan | Toegestaan | Bestaande administrator-only regel behouden |

Alle schrijfacties in deze matrix vereisen daarnaast een geldige, aangemelde sessie en een geldig CSRF-token. Een ongeldige rol krijgt geen participant-fallback op beschermde routes.

## Gewijzigde bestanden

### Gedeelde authenticatie en frontend

- `api/auth/accessControl.php`: vertrouwde gebruiker/rol, rolconstanten, authenticatie- en beheercontroles, CSRF, karakterobjectcontrole, bevoegdheidsvelden en zichtbaarheid van vaardigheden.
- `sessionUserBootstrap.php`: sessie-ID vernieuwen na WordPress-authenticatie.
- `tokenhandler.php`: de niet-aangeroepen en onvolledige OIDC-callback fail-closed uitschakelen met HTTP 410, zonder sessie- of tokenverwerking.
- `checkLogin.php`: veilige participant-registratie, rol uit `tblUser`, bestaande profielsynchronisatie en uitgifte van CSRF-token.
- `getCurrentUser.php`: gebruiker en rol uit `tblUser`, veilige registratie en uitgifte van CSRF-token.
- `js/mainFunctions.js`: CSRF-token bewaren en meesturen bij niet-GET JSON-verzoeken.
- `js/createCharacter.js`: CSRF-token ook uit de rechtstreekse current-user-call bewaren.
- `js/characterFunctions.js`, `js/companyFunctions.js`: CSRF-header bij multipart uploads.

### Beheer, bedrijven, events en gebruikers

- `api/admin/adminUtils.php` en `api/admin/{deleteActionUse,deleteKnowledgeUnlock,newSkill,saveKnowledgeVisibility,saveSkill,saveSkillType,updateActionUse}.php`: databankrol afdwingen; CSRF op writes; administrator-only categoriebeheer behouden.
- `api/companies/companyUtils.php` en `api/companies/{deleteCompanyLogo,newCompany,saveCompanyPersonnel,saveCompanySnapshot,updateCompany,uploadCompanyLogo}.php`: beheerrol en CSRF afdwingen.
- `api/events/{getEventList,newEvent,updateEvent,updateParticipation}.php`: login voor lezen, eigen deelname voor participants, beheerrol en CSRF voor wijzigingen.
- `api/users/getUserList.php`: alleen toegankelijk voor beheerrollen.

### Personages

- `api/characters/characterPointUtils.php`, `api/characters/economyUtils.php`: gebruiker en rol uitsluitend via de gedeelde server-side authenticatie.
- `api/characters/{getCharacter,getCharacterList,newCharacter,updateCharacter,deleteCharacter}.php`: algemene login-, object- en bevoegdheidsveldcontrole; geheime vaardigheden filteren; server-side eigenaar/maker/status bij creatie.
- `api/characters/{AddNewSkill,addSkillSpecialisation,deleteSkillSpecialisation,getDisciplineList,getNewSkills,getSkillSpecialisations,updateSkill,updateTrait}.php`: objecttoegang, zichtbaarheid van vaardigheden en CSRF op wijzigingen.
- `api/characters/{addCharacterLanguage,deleteCharacterLanguage,getCharacterLanguageOptions}.php`: vertrouwde rol/identiteit, eigenaarschap en CSRF op wijzigingen.
- `api/characters/{getCharacterDiary,saveCharacterDiary,getCharacterSections,saveCharacterSection}.php`: lees- en schrijfrechten per personage; CSRF op wijzigingen.
- `api/characters/{getCharacterTieOptions,getCharacterTies,saveCharacterTie,deleteCharacterTie}.php`: authenticatie, bronobjectcontrole, veilige doelkeuze voor participants en CSRF.
- `api/characters/{getCharacterActionEvents,getCharacterActionKnowledgeTargets,revealCharacterActionKnowledge,useCharacterSkillAction}.php`: vertrouwde identiteit, objectcontrole en CSRF op acties met neveneffecten.
- `api/characters/{uploadCharacterPortrait,deleteCharacterPortrait}.php`: eigenaarschap/beheerrol en CSRF vóór bestandswijziging.
- `api/characters/{buyCompanyShare,saveCompanyShare,saveBankTransfer,deleteBankTransaction,saveCharacterEconomySnapshot,deleteCharacterEconomySnapshot,saveCharacterSecuritiesPortfolio}.php`: gedeelde authenticatie en CSRF, met behoud van de bestaande specifieke economierechten.

### Tests

- `tests/access_control_test.php`: pure rechtenbeslissingen voor eigen/andere speler, extra, draft, beheerrollen, vervalste rollen en CSRF-tokenvergelijking.
- `tests/authenticated_user_test.php`: geïsoleerde PDO/SQLite-test voor de databankrol, veilige participant-registratie, ontbrekende gebruiker zonder neveneffect, objecteigenaarschap, geheime vaardigheden en een geweigerde wijziging zonder datamutatie.
- `tests/access_control_route_coverage_test.php`: herhaalbare controle dat alle aangepaste schrijfroutes authenticatie en CSRF bevatten, karakterroutes objectcontrole toepassen, browserrollen niet vertrouwen, bevoegdheidsvelden afschermen, uploads een CSRF-header sturen en de oude callback uitgeschakeld blijft.
- `tests/oidc_callback_test.php`: controleert dat de callback geen code/token of sessie verwerkt, geen clientgeheim bevat, HTTP 410 configureert, nergens vanuit de app wordt aangeroepen en de WordPress-bootstrap behouden blijft.

## Uitgevoerde tests

De applicatietests gebruikten lokale code en een in-memory SQLite-database. Voor de OIDC-beoordeling zijn twee read-only publieke GET-verzoeken uitgevoerd: één naar het officiële discovery-document en één zonder autorisatiecode naar de bestaande callback. Daarbij zijn geen gebruikers- of applicatiegegevens gelezen of gewijzigd.

| Test | Resultaat | Wat werkelijk is gecontroleerd |
| --- | --- | --- |
| PHP 8.4.25 `php -l` op alle niet-legacy PHP-bestanden | Geslaagd | Geen PHP-syntaxfouten |
| `tests/access_control_test.php` | Geslaagd | Rechtenbeslissingen participant/director/administrator, eigen/ander object, draft, bevoegdheidsvelden en CSRF-match |
| `tests/authenticated_user_test.php` met PDO SQLite | Geslaagd | Anonieme load geweigerd; rol uit database ondanks vervalste sessierol; nieuwe login altijd participant; beschermde load maakt geen gebruiker; eigen objectwrite werkt; geweigerde objectwrite verandert niets; geheime vaardigheid afgeschermd |
| `tests/access_control_route_coverage_test.php` | Geslaagd | Route-dekking voor authenticatie, objectcontrole, bevoegdheidsvelden en CSRF inclusief uploads en sessie-ID-vernieuwing |
| `tests/oidc_callback_test.php` | Geslaagd | Geen interne verwijzingen; geen tokenuitwisseling, sessielogin of clientgeheim; WordPress-bootstrap behouden |
| Lokale PHP-webserver: `tokenhandler.php?code=forged-test-code` | Geslaagd | HTTP 410, `Cache-Control: no-store`, tekstrespons en geen loginflow |
| Officieel provider-discovery-document | Geslaagd | Bestaande issuer, endpoints, JWKS-URL en gepubliceerde algoritmen vastgesteld zonder waarden te verzinnen |
| Bestaande publieke callback-URL vóór deployment | Bereikbaar | Read-only GET antwoordde HTTP 200; dit bewijst bereikbaarheid, niet actief gebruik door de app |
| `git diff --check` | Geslaagd | Geen whitespacefouten; Git meldde alleen de bestaande LF/CRLF-conversiewaarschuwingen |

Er zijn geen mislukte tests in de uiteindelijke run.

## Niet getest en resterende punten

- De echte WordPress-login, cookies, sessie-instellingen en de MySQL/MariaDB-routes zijn niet end-to-end getest: in deze lokale sessie was geen geïsoleerde WordPress- en MySQL-testinstallatie beschikbaar die zonder risico mocht worden gewijzigd.
- Uploaden/verwijderen van echte portret- en logobestanden is niet uitgevoerd. De servercontrole en aanwezigheid van de CSRF-header zijn wel statisch gecontroleerd.
- De lokaal uitgeschakelde callback is niet op de server geplaatst; HTTP 410 op de publieke URL is daarom nog niet gecontroleerd.
- Dynamische toegestane velden voor algemene event- en profielinhoud en configuratiescheiding vallen volgens de opdrachtafbakening buiten dit herstel. De velden die identiteit, eigenaar, type, status en auditinformatie bepalen zijn wel afgeschermd zoals hierboven beschreven.

### Vereiste configuratie voordat OIDC opnieuw geactiveerd mag worden

De callback mag pas opnieuw tokens accepteren wanneer de volgende gegevens en onderdelen expliciet zijn vastgelegd en getest:

1. De clientgebonden officiële discovery-URL en de exact verwachte `issuer`.
2. Client-ID, client secret en exacte geregistreerde redirect-URI vanuit serverconfiguratie buiten de publieke broncode.
3. Het signing-algoritme dat voor deze concrete client actief is. De discovery-metadata publiceert zowel HS256 als RS256; daaruit volgt niet welk algoritme een token voor deze client daadwerkelijk moet gebruiken.
4. Een login-initiator binnen dezelfde applicatiesessie die cryptografisch willekeurige `state` en `nonce` maakt, met korte vervaltijd en eenmalig gebruik.
5. Een onderhouden OIDC/JWT-library die discovery en JWKS gebruikt en de handtekening, `iss`, `aud`/`azp`, `exp`, `iat`, `nonce` en sessiegebonden `state` strikt valideert voordat sessiedata wordt geschreven.
6. De officiële claimmapping voor de lokale gebruikers-ID. De oude callback gebruikte een provider-specifieke `ID`-claim; de repository bewijst niet dat dit de blijvende, unieke identifier van de OIDC-client is.
7. Integratietests met testaccounts voor een geldige login en voor verkeerde handtekening, issuer, audience, vervaltijd, nonce, state en hergebruikte callback.

### Concrete handmatige integratietest

Voer dit uitsluitend uit met een aparte ontwikkelingsdatabase en testaccounts:

1. Meld aan als participant A, haal via `checkLogin.php` het CSRF-token op en controleer dat eigen personagegegevens kunnen worden gelezen en een toegestane wijziging met token slaagt.
2. Herhaal dezelfde calls met het ID van participant B. Verwacht HTTP 403 en vergelijk de betrokken rijen vóór en na de poging.
3. Stuur bij een eigen wijziging geen token en daarna een aangepast token. Verwacht telkens HTTP 403 en geen database- of bestandswijziging.
4. Voeg `role=administrator`, een ander `idUser`, `idUser`/`createdBy`/`state` of een ander eigenaars-ID toe aan de participantpayload. Verwacht geen extra rechten; bij verboden updatevelden HTTP 403.
5. Meld aan als director en administrator en controleer de toegestane beheerhandelingen uit de matrix. Controleer apart dat alleen administrator vaardigheidscategorieën kan wijzigen.
6. Meld volledig af en roep een beschermde read en write rechtstreeks aan. Verwacht HTTP 401.
7. Controleer in de browser dat normale JSON-writes, portretupload en logoupload na login slagen en dat de bestaande frontend de JSON-fout bij HTTP 401/403 toont of afhandelt.
