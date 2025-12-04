// Form-related helpers: listeners, autosave, read-only render, editability toggles

function setupCharacterFormListeners() {
    // Alleen autosave; de create-flow listeners zitten in createCharacter.js
    const velden = [
        "firstName",
        "lastName",
        "class",
        "birthDate",
        "birthPlace",
        "nationality",
        "stateRegisterNumber",
        "street",
        "houseNumber",
        "municipality",
        "postalCode",
        "title",
        "maritalStatus",
        "profession"
    ];

    velden.forEach((veld) => {
        const el = document.getElementById(veld);
        if (el) {
            el.addEventListener("change", handleAutoSaveField);
        }
    });
}

async function handleAutoSaveField(e) {
    const idCharacter = document.getElementById("idCharacter").value;
    if (!idCharacter) {
        // Nog geen personage-id ⇒ nieuwe personages worden via de Aanmaken-knop gemaakt
        return;
    }

    const field = e.target.id;
    const value = e.target.value;

    const dataObject = {
        id: idCharacter
    };
    dataObject[field] = value;

    try {
        await apiFetchJson("api/characters/updateCharacter.php", {
            method: "POST",
            body: dataObject
        });
    } catch (err) {
        console.error("Fout bij autosave van veld", field, err);
    }
}

function renderCharacterDetailsReadOnly(character) {
    const container = document.getElementById("characterForm");
    if (!container) return;

    // Simpele formatter voor geboortedatum (db-formaat -> gewoon tonen)
    const birthDate = character.birthDate && character.birthDate !== "0001-01-01"
        ? character.birthDate
        : "";

    const streetLine = [character.street, character.houseNumber].filter(Boolean).join(" ");
    const cityLine = [character.postalCode, character.municipality].filter(Boolean).join(" ");
    const birthLine = [character.birthPlace, birthDate].filter(Boolean).join(" / ");

    container.innerHTML = `
        <div class="mb-3 row">
            <div class="col-sm-4">Klasse</div>
            <div class="col-sm-8">${character.class || ""}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Aanspreking</div>
            <div class="col-sm-8">${character.title || ""}</div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-4">Familienaam</label>
            <div class="col-sm-8">${character.lastName || ""}</div>
        </div>
        <div class="mb-3 row">
            <label class="col-sm-4">Voornaam</label>
            <div class="col-sm-8">${character.firstName || ""}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Straat en nummer</div>
            <div class="col-sm-6">${streetLine}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Postcode en gemeente</div>
            <div class="col-sm-6">${cityLine}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Burgelijke staat</div>
            <div class="col-sm-6">${character.maritalStatus || ""}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Nationaliteit</div>
            <div class="col-sm-6">${character.nationality || ""}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Geboren te / op</div>
            <div class="col-sm-6">${birthLine}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Beroep</div>
            <div class="col-sm-6">${character.profession || ""}</div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-4">Rijksregisternummer</div>
            <div class="col-sm-6">${character.stateRegisterNumber || ""}</div>
        </div>
    `;
}

function applyCharacterEditability(character, canEdit) {
    // 1. Characterformulier: inputs wel/niet bewerkbaar
    const formFields = [
        "firstName",
        "lastName",
        "class",
        "birthDate",
        "birthPlace",
        "nationality",
        "stateRegisterNumber",
        "street",
        "houseNumber",
        "municipality",
        "postalCode",
        "title",
        "maritalStatus",
        "profession"
    ];

    formFields.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (canEdit) {
            el.removeAttribute("disabled");
            el.classList.remove("form-control-plaintext");
        } else {
            el.setAttribute("disabled", "disabled");
        }
    });

    // 2. Nieuwe skill toevoegen (dropdown + plusknop)
    const idNewSkill = document.getElementById("idNewSkill");
    const btnAddSkill = document.getElementById("addNewSkill");

    if (idNewSkill) {
        if (canEdit) {
            idNewSkill.removeAttribute("disabled");
        } else {
            idNewSkill.setAttribute("disabled", "disabled");
        }
    }

    if (btnAddSkill) {
        if (canEdit) {
            btnAddSkill.removeAttribute("disabled");
            btnAddSkill.classList.remove("disabled");
        } else {
            btnAddSkill.setAttribute("disabled", "disabled");
            btnAddSkill.classList.add("disabled");
        }
    }

    // 3. Alle skill-knoppen (Learn/Unlearn/Delete + specialisaties) blokkeren
    const accordion = document.getElementById("accordionSkills");
    if (accordion) {
        const buttons = accordion.querySelectorAll("button[data-action]");
        buttons.forEach((btn) => {
            if (canEdit) {
                return; // XP-logica blijft zijn werk doen
            }
            btn.setAttribute("disabled", "disabled");
            btn.classList.add("disabled");
        });
    }
}
