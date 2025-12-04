// =====================================================
// NIEUW: logic voor het aanmaken van een nieuw personage
// =====================================================

let aetherIsCreatingCharacter = false;
let aetherCurrentUserForCreation = null;

/**
 * Haal de huidige ingelogde user op via getCurrentUser.php
 * (id, firstName, lastName, role)
 */
async function aetherFetchCurrentUser() {
    if (aetherCurrentUserForCreation) {
        return aetherCurrentUserForCreation;
    }

    const response = await fetch('getCurrentUser.php', {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
        throw new Error('Kon huidige gebruiker niet ophalen.');
    }

    const user = await response.json();
    aetherCurrentUserForCreation = user;
    return user;
}

/**
 * Vul de deelnemersâ€dropdown (alleen voor director/administrator)
 */
async function aetherPopulateParticipantSelect() {
    const host = document.getElementById('listParticipant');
    if (!host) return;

    host.innerHTML = '<div class="form-text">Ladenâ€¦</div>';

    try {
        const response = await fetch('api/users/getUserList.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            throw new Error('Kon userlijst niet laden.');
        }

        const users = await response.json();

        const select = document.createElement('select');
        select.id = 'listParticipant-input';
        select.name = 'idUser';
        select.className = 'form-select';

        // Optie: voorlopig geen deelnemer koppelen
        const optEmpty = document.createElement('option');
        optEmpty.value = '';
        optEmpty.textContent = 'â€” Nog geen deelnemer koppelen â€”';
        select.appendChild(optEmpty);

        users.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = `${u.firstName} ${u.lastName} (${u.role})`;
            select.appendChild(opt);
        });

        host.innerHTML = '';
        host.appendChild(select);
    } catch (err) {
        host.innerHTML = '<div class="text-danger">Fout bij het laden van de deelnemerslijst.</div>';
        console.error(err);
    }
}

/**
 * Toon de juiste header (admin vs participant) in create-modus
 */
async function aetherRenderHeaderForNewCharacter(user) {
    const pageNav = document.getElementById('pageNav');
    if (!pageNav) return;

    pageNav.classList.remove('d-none');
    pageNav.innerHTML = '';

    const isAdmin = (user.role === 'administrator' || user.role === 'director');

    if (isAdmin) {
        const tpl = document.getElementById('tplNavSheetAdmin');
        if (tpl) {
            pageNav.appendChild(tpl.content.cloneNode(true));
        }
        // Deelnemersâ€dropdown opbouwen
        await aetherPopulateParticipantSelect();

        // Status altijd als label "Draft" tonen (geen dropdown)
        const stateEl = document.getElementById('state');
        if (stateEl) {
            if (stateEl.tagName === 'SELECT') {
                const parent = stateEl.parentElement;
                const label = document.createElement('div');
                label.id = 'state';
                label.className = 'col-sm-9 col-form-label';
                label.textContent = 'Draft';
                if (parent) {
                    parent.replaceChild(label, stateEl);
                }
            } else {
                stateEl.textContent = 'Draft';
            }
        }

        // XP voorlopig op "0 (...)" zetten â€“ echte berekening gebeurt na aanmaken
        const xpLabel = document.getElementById('lblExperiance');
        if (xpLabel) {
            xpLabel.textContent = '0 (nog te berekenen na creatie)';
        }

    } else {
        // Gewone participant
        const tpl = document.getElementById('tplNavSheetParticipant');
        if (tpl) {
            pageNav.appendChild(tpl.content.cloneNode(true));
        }
        const nameSpan = document.getElementById('nameParticipant');
        if (nameSpan) {
            nameSpan.textContent = `${user.firstName} ${user.lastName}`;
        }
        const typeLabel = document.getElementById('type');
        if (typeLabel) {
            typeLabel.textContent = 'Spelerspersonage';
        }
        const stateLabel = document.getElementById('state');
        if (stateLabel) {
            stateLabel.textContent = 'Draft';
        }
        const xpLabel = document.getElementById('lblExperiance');
        if (xpLabel) {
            xpLabel.textContent = '0 / (verzamelde EP na koppeling)';
        }
    }
}

/**
 * Character form klaarmaken voor create-modus:
 * - Alleen klasse, voornaam en familienaam zichtbaar/actief
 * - Buttons: create/cancel tonen, update verbergen
 * - Skills: placeholder "character creation"
 */
function aetherPrepareCharacterFormForCreation() {
    const idCharacterInput = document.getElementById('idCharacter');
    if (idCharacterInput) {
        idCharacterInput.value = '';
    }

    const formContainer = document.getElementById('characterForm');
    if (!formContainer) return;

    formContainer.classList.remove('d-none');

    // Alle rijen uit #characterForm ophalen
    const rows = formContainer.querySelectorAll('.mb-3.row');
    rows.forEach(row => {
        const input = row.querySelector('input, select, textarea');
        if (!input) return;

        const keep = ['class', 'firstName', 'lastName'].includes(input.id);
        if (keep) {
            input.disabled = false;
            row.classList.remove('d-none');
        } else {
            input.disabled = true;
            row.classList.add('d-none');
            // velden ook leegmaken zodat we bij update geen rommel meeslepen
            if (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        }
    });

    // Buttons: new vs edit
    const groupNew = document.getElementById('groupNewCharacter');
    const groupEdit = document.getElementById('groupEditCharacter');
    if (groupNew) groupNew.classList.remove('d-none');
    if (groupEdit) groupEdit.classList.add('d-none');

    // Skills-kolom: enkel placeholder
    const skillsDiv = document.getElementById('skills');
    if (skillsDiv) {
        skillsDiv.classList.remove('d-none');
        skillsDiv.innerHTML = `
            <div class="alert alert-info mt-2">
                Character creation â€” vaardigheden kunnen pas worden toegevoegd nadat het personage is aangemaakt.
            </div>
        `;
    }

    // Klasse, voornaam en familienaam leeg/standaard zetten
    const classSelect = document.getElementById('class');
    if (classSelect) {
        // Als je wilt verplicht laten kiezen, kan je hier eventueel
        // classSelect.selectedIndex = -1; zetten en in HTML een lege optie toevoegen.
    }
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    if (firstName) firstName.value = '';
    if (lastName) lastName.value = '';
}

/**
 * Enable/disable "Aanmaken" knop op basis van validatie
 * - Klasse gekozen
 * - Voornaam en familienaam minstens 2 letters
 */
function aetherUpdateCreateButtonState() {
    const btn = document.getElementById('createNewCharacter');
    if (!btn) return;

    const classSelect = document.getElementById('class');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');

    const classOk = !!(classSelect && classSelect.value);
    const firstOk = !!(firstName && firstName.value.trim().length >= 2);
    const lastOk = !!(lastName && lastName.value.trim().length >= 2);

    btn.disabled = !(classOk && firstOk && lastOk);
}

/**
 * Start create-modus wanneer de user klikt op "Nieuw personage"
 */
async function aetherStartNewCharacterFlow() {
    try {
        const user = await aetherFetchCurrentUser();
        aetherIsCreatingCharacter = true;

        await aetherRenderHeaderForNewCharacter(user);
        aetherPrepareCharacterFormForCreation();

        // Validatie events
        const classSelect = document.getElementById('class');
        const firstName = document.getElementById('firstName');
        const lastName = document.getElementById('lastName');

        if (classSelect) {
            classSelect.addEventListener('change', aetherUpdateCreateButtonState);
        }
        if (firstName) {
            firstName.addEventListener('input', aetherUpdateCreateButtonState);
        }
        if (lastName) {
            lastName.addEventListener('input', aetherUpdateCreateButtonState);
        }

        aetherUpdateCreateButtonState();
    } catch (err) {
        console.error(err);
        alert('Er ging iets mis bij het starten van character creation.');
    }
}

/**
 * Cancel uit create-modus:
 * - Form & header verbergen
 * - skills leegmaken
 * (Je kan hier eventueel ook terug een geselecteerd personage herladen als je dat later wilt.)
 */
function aetherCancelNewCharacter() {
    aetherIsCreatingCharacter = false;

    const pageNav = document.getElementById('pageNav');
    if (pageNav) {
        pageNav.classList.add('d-none');
        pageNav.innerHTML = '';
    }

    const formContainer = document.getElementById('characterForm');
    if (formContainer) {
        formContainer.classList.add('d-none');
    }

    const skillsDiv = document.getElementById('skills');
    if (skillsDiv) {
        skillsDiv.classList.add('d-none');
        skillsDiv.innerHTML = '';
    }

    const idCharacterInput = document.getElementById('idCharacter');
    if (idCharacterInput) {
        idCharacterInput.value = '';
    }
}

/**
 * Verstuur de data naar api/characters/newCharacter.php
 * en laad daarna meteen het nieuwe personage in de gewone "update"-modus.
 *
 * LET OP:
 * We sturen hier basishulpwaarden mee (lege strings en "0000-00-00")
 * zodat de NOT NULL-velden in de databank tevreden zijn.
 * Na creatie kunnen deze velden in de gewone edit-modus aangevuld worden.
 */
async function aetherCreateNewCharacter() {
    const user = await aetherFetchCurrentUser();

    const classSelect = document.getElementById('class');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');

    if (!classSelect || !firstName || !lastName) {
        alert('Formulier niet volledig geladen.');
        return;
    }

    const classVal = classSelect.value;
    const firstVal = firstName.value.trim();
    const lastVal = lastName.value.trim();

    if (firstVal.length < 2 || lastVal.length < 2 || !classVal) {
        alert('Kies een klasse en geef minstens 2 letters voor voornaam Acn familienaam.');
        return;
    }

    const isAdmin = (user.role === 'administrator' || user.role === 'director');

    let idUser = 0;
    let type = 'player';

    if (isAdmin) {
        const select = document.getElementById('listParticipant-input');
        if (select && select.value !== '') {
            idUser = parseInt(select.value, 10) || 0;
        }
        const typeEl = document.getElementById('type');
        if (typeEl && typeEl.tagName === 'SELECT') {
            type = typeEl.value || 'player';
        }
    } else {
        idUser = user.id;
        type = 'player';
    }

    // Payload voor newCharacter.php
    const payload = {
        idUser: idUser,
        type: type,
        state: 'draft',
        firstName: firstVal,
        lastName: lastVal,
        class: classVal,
        // Verplichte velden uit de DB met "lege" standaardwaarden:
        birthDate: '1900-01-01',
        birthPlace: '',
        nationality: '',
        stateRegisterNumber: '',
        street: '',
        houseNumber: '',
        municipality: '',
        postalCode: '',
        title: '',
        maritalStatus: '',
        profession: ''
        // Als je kolommen createdAt / createdBy hebt toegevoegd:
        // createdBy: user.id
        // createdAt: (laat je best door de DB op CURRENT_TIMESTAMP zetten)
    };

    try {
        const result = await apiFetchJson("api/characters/newCharacter.php", {
            method: "POST",
            body: payload
        });

        let newId = null;
        if (result && typeof result === "object" && "id" in result) {
            newId = result.id;
        } else if (typeof result === "number" || typeof result === "string") {
            newId = result;
        }

        if (!newId) {
            throw new Error("Geen geldig ID teruggekregen.");
        }

        // We verlaten de create-modus en laden direct het nieuwe personage
        aetherIsCreatingCharacter = false;

        // Lijst verversen zodat het nieuwe personage ook in de lijst staat
        await characterList();

        // Laad het nieuwe personage in de standaard edit-view
        if (typeof loadCharacter === "function") {
            await loadCharacter(newId);
        } else {
            await getCharacter(newId);
        }

    } catch (err) {
        console.error(err);
        alert('Het aanmaken van het personage is mislukt.');
    }
}/**
 * Event listeners koppelen (wordt op DOMContentLoaded opgeroepen)
 */
function aetherSetupNewCharacterUI() {
    const startNewCharacterLink = document.getElementById('startNewCharacter');
    if (startNewCharacterLink) {
        startNewCharacterLink.addEventListener('click', function (e) {
            e.preventDefault();
            aetherStartNewCharacterFlow();
        });
    }

    const createBtn = document.getElementById('createNewCharacter');
    if (createBtn) {
        createBtn.addEventListener('click', function (e) {
            e.preventDefault();
            aetherCreateNewCharacter();
        });
    }

    const cancelBtn = document.getElementById('cancelNewCharacter');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            aetherCancelNewCharacter();
        });
    }
}

// Deze listener stoort je bestaande listeners NIET; je mag er meerdere hebben.
document.addEventListener('DOMContentLoaded', aetherSetupNewCharacterUI);






