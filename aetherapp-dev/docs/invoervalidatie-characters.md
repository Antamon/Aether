# Invoervalidatie en veilige tekstweergave — personagemodule

Datum: 2026-09-16

## Resultaat

Alle routes in `api/characters` die browserinvoer accepteren gebruiken nu het gedeelde schema in `api/characters/characterRequestValidation.php`. De validator wijst ongeldige JSON, verkeerde datatypes, ontbrekende verplichte velden, waarden buiten grenzen en ieder onbekend veld af met HTTP 422 en een JSON-respons met `error` en `validationErrors`.

`newCharacter.php` en `updateCharacter.php` bouwen geen SQL-kolomnamen meer uit browserkeys. Aanmaken gebruikt een vaste `INSERT`; bijwerken gebruikt een vaste veld-naar-kolommapping. De eerder ingevoerde authenticatie-, rol-, eigenaarschaps- en CSRF-controles zijn behouden. Bevoegdheidsvelden (`idUser`, `type`, `state`) blijven door die controles beschermd; auditvelden (`createdBy`, `createdAt`) blijven bij updates expliciet geweigerd. Bij aanmaken bepaalt de server `createdBy`, `createdAt` en `state`, en voor participants ook `idUser`, `type` en de gratis gezondheidsvelden.

**Latere aanvulling:** de bewust als rich text ontworpen velden `personal_background`, `knowledge`, `nature`, `demeanour`, `goals` en `achievements` gebruiken opnieuw gesaniteerde HTML. Alle overige charactervelden blijven gewone tekst. De actuele beveiliging en allowlist staan in `docs/rich-text-characters.md`; die documentatie vervangt voor deze zes velden de eerdere conclusie hieronder over uitsluitend platte tekst.

## Algemene veldregels

- `int`: PHP-integer of een string met uitsluitend een optioneel minteken en cijfers; booleans en decimalen worden geweigerd.
- `number`: integer, float of numerieke string; moet eindig zijn en wordt voor geldbedragen op twee decimalen afgerond.
- `string`: moet werkelijk een string zijn, wordt getrimd en gemeten in UTF-8-tekens wanneer `mbstring` beschikbaar is.
- `date`: exacte, bestaande datum in formaat `YYYY-MM-DD`.
- IDs met `> 0`: minimum 1. Optionele bestaande-record-IDs laten 0 toe als “nieuw/niet gekozen”.
- Geldbedragen passen binnen `DECIMAL(12,2)`: maximaal `9.999.999.999,99` in absolute waarde; transacties vereisen een positief bedrag.
- Gewone tekst laat **geen HTML** toe als markup. Tekens zoals `<`, `>` en quotes worden als tekst bewaard en via DOM-tekstweergave getoond.
- Niet-genoemde velden zijn per endpoint verboden en leveren een concrete validatiefout op.

## Veldregels per route

### Personage lezen en basisgegevens

| Route | Geaccepteerde velden | Regels |
|---|---|---|
| `getCharacter.php` | `id` | verplicht int, > 0 |
| `getCharacterList.php` | geen | iedere bodyparameter wordt geweigerd; de browserrol is verwijderd |
| `getNewSkills.php` | `id` | verplicht int, > 0; browserrol is verwijderd |
| `getCharacterSections.php` | `idCharacter` | verplicht int, > 0 |
| `getCharacterTies.php` | `idCharacter` | verplicht int, > 0 |
| `getCharacterTieOptions.php` | geen | query- en formparameters worden geweigerd |
| `getCharacterDiary.php` | `idCharacter` | verplicht int, > 0 |
| `getCharacterLanguageOptions.php` | `idCharacter` | verplicht int, > 0 |
| `getDisciplineList.php`, `getSkillSpecialisations.php` | `idSkill`, `idCharacter` | beide verplicht int, > 0 |
| `getCharacterActionEvents.php` | `idCharacter` | verplicht int, > 0 |
| `getCharacterActionKnowledgeTargets.php` | `idCharacter`, `idEvent` | beide verplicht int, > 0 |

### Personage aanmaken en bijwerken

| Veld | `newCharacter.php` | `updateCharacter.php` | Normalisatie / toegestane waarden |
|---|---|---|---|
| `id` | niet toegestaan | verplicht | int > 0 |
| `idUser` | optioneel | optioneel | int 0 of hoger; bestaande bevoegdheidscontrole blijft gelden |
| `type` | verplicht | optioneel | `player`, `extra`; bij participant-creatie forceert server `player` |
| `state` | optioneel | optioneel | `active`, `inactive`, `deceased`, `other`, `draft`, `approve`; creatie forceert `draft` |
| `firstName`, `lastName` | verplicht, 2–40 | optioneel, bij aanwezigheid 2–40 | string, trim, geen HTML |
| `class` | verplicht | optioneel | `upper class`, `middle class`, `lower class`; update laat ook lege waarde toe |
| `birthDate` | optioneel, standaard `1900-01-01` | optioneel; leeg betekent niet bijwerken | geldige `YYYY-MM-DD` |
| `birthPlace`, `nationality` | optioneel | optioneel | string, trim, max. 40, geen HTML |
| `stateRegisterNumber` | optioneel | optioneel | string, trim, max. 8, geen HTML |
| `street` | optioneel | optioneel | string, trim, max. 30, geen HTML |
| `houseNumber` | optioneel | optioneel | string, trim, max. 11, geen HTML |
| `municipality` | optioneel | optioneel | string, trim, max. 30, geen HTML |
| `postalCode` | optioneel | optioneel | string, trim, max. 4, geen HTML |
| `title` | optioneel | optioneel | string, trim, max. 30, geen HTML |
| `maritalStatus` | optioneel | optioneel | leeg, `Single`, `Married`, `Widowed` |
| `experienceToTrait` | optioneel | optioneel | int 0–6 |
| `physicalHealth`, `mentalHealth` | optioneel | optioneel | int -3–127; bestaande punt- en rechtencontrole blijft gelden |
| `physicalHealthFree`, `mentalHealthFree` | optioneel | optioneel | int -128–127; bestaande privileged-rolecontrole blijft gelden |
| `bankaccount` | niet toegestaan | optioneel | getal binnen `DECIMAL(12,2)`; bestaande rolcontrole blijft gelden |
| `securitiesaccount` | niet toegestaan | optioneel | getal 0–9.999.999.999,99; bestaande rolcontrole blijft gelden |
| `createdBy`, `createdAt` | niet toegestaan | alleen herkend om expliciet 403 te behouden | nooit in de update-SQL opgenomen |

De update vereist naast `id` minimaal één werkelijk te wijzigen veld. Alle SQL-kolommen komen uit de vaste servermapping.

### Verwijderen en portret

| Route | Geaccepteerde velden | Regels |
|---|---|---|
| `deleteCharacter.php`, `deleteCharacterPortrait.php` | `id` | verplicht int, > 0 |
| `deleteBankTransaction.php` | `idTransaction` | verplicht int, > 0 |
| `deleteCharacterEconomySnapshot.php` | `idSnapshot` | verplicht int, > 0 |
| `deleteCharacterLanguage.php` | `idCharacter`, `idCharacterLanguage` | beide verplicht int, > 0 |
| `deleteCharacterTie.php` | `idCharacter`, `idTie` | beide verplicht int, > 0 |
| `deleteSkillSpecialisation.php` | `idSkill`, `idCharacter`, `idSkillSpecialisation` | alle verplicht int, > 0 |
| `uploadCharacterPortrait.php` | formveld `id`, bestand `portrait` | `id` verplicht int > 0; exact dit ene uploadveld; succesvolle uploadstatus; bestand 1 byte–10 MB; bestaande afbeeldingsdecoder en dimensiecontrole blijven gelden |

### Tekst, relaties en dagboek

| Route | Geaccepteerde velden | Regels |
|---|---|---|
| `saveCharacterSection.php` | `idCharacter`, `section`, `content` | ID verplicht; section = `personal_background`, `knowledge`, `nature`, `demeanour`; content optionele getrimde rich text max. 16.000, daarna server-side gesanitized volgens `docs/rich-text-characters.md` |
| `saveCharacterDiary.php` | `idCharacter`, `idDiary`, `idEvent`, `goals`, `achievements`, `gossip1`, `gossip2`, `gossip3` | character/event verplicht > 0; diary optioneel 0 of hoger; alle tekst optioneel, getrimd, max. 16.000 per veld; alleen goals en achievements zijn gesaniteerde rich text, gossip blijft gewone tekst zonder HTML |
| `saveCharacterTie.php` | `idCharacter`, `idTie`, `idOtherCharacter`, `relationType`, `description` | character/other verplicht > 0; tie optioneel 0 of hoger; type = `superior`, `dependent`, `landlord`, `household_staff`, `spouse`, `ally`, `adversary`, `person_of_interest`; description max. 255, trim, geen HTML |
| `addCharacterLanguage.php` | `idCharacter`, `idLanguage`, `name` | character verplicht > 0; language optioneel 0 of hoger; óf bestaand ID óf getrimde naam vereist; naam max. 120, geen HTML |

### Skills, traits en characteracties

| Route | Geaccepteerde velden | Regels |
|---|---|---|
| `AddNewSkill.php` | `idCharacter`, `idSkill`, `level` | IDs verplicht > 0; level optioneel int 0–3, standaard 0 |
| `updateSkill.php` | `action`, `idSkill`, `idCharacter` | action = `up`, `down`, `delete`; IDs verplicht > 0 |
| `addSkillSpecialisation.php` | `idSkill`, `idCharacter`, `idSkillSpecialisation`, `name`, `kind` | IDs skill/character verplicht > 0; specialisatie-ID optioneel 0 of hoger; bij nieuw record naam verplicht, trim, max. 100, geen HTML; kind optioneel/null of `discipline`, `specialisation` |
| `updateTrait.php` | `action`, `idCharacter`, `idTrait`, `idCurrentTrait` | action = `add`, `change`, `remove`, `rank_up`, `rank_down`; character/trait > 0; current trait optioneel 0 of hoger |
| `revealCharacterActionKnowledge.php` | `idCharacter`, `idEvent`, `idSourceCharacter` | alle verplicht int, > 0 |
| `useCharacterSkillAction.php` | `idCharacter`, `idEvent`, `idSkill`, `actionCode`, `actionSubtype`, `clearBurn` | IDs > 0; actionCode = `psi`; subtype getrimde tekst 1–64, geen HTML; clearBurn optionele echte boolean |

### Economie binnen het personage

| Route | Geaccepteerde velden | Regels |
|---|---|---|
| `saveBankTransfer.php` | `idSourceCharacter`, `idTargetCharacter`, `amount`, `description`, `transactionDate` | IDs > 0; amount > 0 en max. `DECIMAL(12,2)`; description trim/max. 255/geen HTML; geldige datum |
| `saveCharacterEconomySnapshot.php` | `idCharacter`, `idEvent` | beide verplicht int, > 0 |
| `buyCompanyShare.php` | `idCharacter`, `idCompany`, `shareClass` | IDs > 0; class = `A` of `B` |
| `saveCompanyShare.php` | `action`, `idLinkCharacterTrait`; action-specifiek `idCompany` | action = `assign_company`, `clear_company`, `increase_rank`, `decrease_rank`; link-ID > 0; company-ID verplicht > 0 bij assign, optioneel/null bij clear en verboden bij verhogen/verlagen |
| `saveCharacterSecuritiesPortfolio.php` | altijd `action`, `idCharacter`; action-specifiek hieronder | character > 0; velden van een andere action worden als onverwacht geweigerd |

Action-specifieke effectenvelden:

- `save_settings`: `managerType` (`self`, `bank`, `third`), `riskProfile` int 1–5, optionele/null `managerCharacterId` > 0.
- `deposit`, `manual_withdrawal`: verplicht `amount` > 0, maximaal `DECIMAL(12,2)`.
- `reroll_snapshot`, `approve_snapshot`: verplicht `idSnapshot` > 0.
- `withdraw_snapshot`: verplicht `idSnapshot` > 0 en `amount` 0–9.999.999.999,99.

## Gewijzigde bestanden

- `api/characters/characterRequestValidation.php`: gedeelde schema’s, type- en grensvalidatie, onbekende-veldencontrole en consistente 422-respons.
- Alle requestroutes in `api/characters/*.php`: koppeling met het eigen expliciete schema; `newCharacter.php` en `updateCharacter.php` gebruiken vaste SQL-kolommen; portretupload valideert multipartvelden en grootte.
- `js/apiCharacter.js`, `js/characterFunctions.js`, `js/skillsCharacter.js`: niet-vertrouwde browserrol uit list-requests verwijderd; skilltekst via `textContent`.
- `js/backgroundCharacter.js`, `js/diaryCharacter.js`: de zes gedocumenteerde rich-textvelden gebruiken de gedeelde editor en gesaniteerde HTML-weergave; gossip blijft `textContent`. `js/passportCharacter.js` blijft gewone tekst gebruiken.
- `js/formCharacter.js`, `js/languageCharacter.js`, `js/navCharacter.js`, `js/traitsCharacter.js`: databasewaarden via tekstnodes/`textContent` tonen.
- `tests/character_input_validation_test.php`: gerichte validator-, route-, SQL-mapping- en veilige-weergavetests.

## Uitgevoerde tests

| Controle | Resultaat |
|---|---|
| Geldige invoer en trimnormalisatie | geslaagd |
| Ontbrekend verplicht veld | geslaagd |
| Verkeerd datatype | geslaagd |
| Te lange waarde | geslaagd |
| Onverwacht veld | geslaagd |
| Gemanipuleerde SQL-kolomnaam | geslaagd; vóór databasegebruik geweigerd en vaste mappings statisch gecontroleerd |
| Opgeslagen XSS-payload | geslaagd voor servervalidator (ongewijzigd als tekst) en statische controle van DOM-tekstweergave; geen browser-end-to-endtest uitgevoerd |
| Action-specifiek onverwacht veld | geslaagd |
| Bestaande toegangscontrolebeslissingen | geslaagd (`tests/access_control_test.php`) |
| Bestaande routecoverage voor toegangscontrole | geslaagd (`tests/access_control_route_coverage_test.php`) |
| OIDC-callbacktest | geslaagd (`tests/oidc_callback_test.php`) |
| Authenticated-user integratietest | geslaagd met de lokaal aanwezige `pdo_sqlite`-extensie (`tests/authenticated_user_test.php`) |
| PHP-syntaxcontrole van alle 39 gewijzigde PHP-bestanden | geslaagd |
| `git diff --check` | geslaagd; alleen informatieve CRLF-waarschuwingen |

## Beperkingen

- Er is geen lokale browser-end-to-endomgeving met een gekoppelde WordPress-sessie en testdatabase gebruikt. Daardoor zijn de gewijzigde formulierflows en een daadwerkelijk in een browser geladen opgeslagen XSS-payload niet interactief getest. Er was lokaal ook geen Node.js-runtime beschikbaar voor een aanvullende JavaScript-syntaxcheck; de oplossing voegt geen Node.js-afhankelijkheid toe.
- Bestaande historische achtergrond- en dagboekrecords bevatten HTML. Die data wordt niet massaal gemigreerd: leesroutes saniteren haar voor weergave en een gewijzigde rich-textwaarde wordt bij de eerstvolgende opslag genormaliseerd. Zie `docs/rich-text-characters.md`.
