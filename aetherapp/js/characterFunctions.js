let currentUser = null;
let currentCharacter = null;
let originalCharacterFormHtml = null;

// Init na load
window.addEventListener("load", () => {
    initCharacterPage();
    setupSkillListeners();
});

async function initCharacterPage() {
    try {
        // Huidige gebruiker ophalen (gebruik globale indien al gezet)
        currentUser = window.AETHER_CURRENT_USER || await apiFetchJson("getCurrentUser.php");
        window.AETHER_CURRENT_USER = currentUser;

        // Lijst met personages
        await characterList();

        // Eventlisteners & autosave instellen
        setupCharacterFormListeners();

        // Oorspronkelijke markup van het karakterformulier bewaren
        const formContainer = document.getElementById("characterForm");
        if (formContainer && originalCharacterFormHtml === null) {
            originalCharacterFormHtml = formContainer.innerHTML;
        }

    } catch (err) {
        console.error("Fout bij initialiseren personagepagina:", err);
    }
}

function setupCharacterFormListeners() {
    // Alleen autosave; de nieuwe create-flow listeners worden elders gekoppeld (aetherSetupNewCharacterUI)
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

// -------------------
//  Basisfuncties
// -------------------

function calculateExperience(character) {
    let experience = 0;

    (character.skills || []).forEach(skill => {
        // levels
        if (skill.level == 1) experience += 1;
        else if (skill.level == 2) experience += 3;
        else if (skill.level == 3) experience += 6;

        // specialisaties: 2 XP per stuk, MAAR disciplines zijn gratis
        if (Array.isArray(skill.specialisations)) {
            const nonDisciplineSpecs = skill.specialisations.filter(
                spec => spec.kind !== "discipline"
            );
            experience += nonDisciplineSpecs.length * 2;
        }
    });

    return experience;
}


function expertiseExtra(experience) {
    if (experience <= 20) return "Average person";
    else if (experience <= 30) return "Professional";
    else if (experience <= 40) return "Master";
    else return "Super human";
}

// -------------------
//  Autosave per veld
// -------------------

async function handleAutoSaveField(e) {
    const idCharacter = document.getElementById("idCharacter").value;
    if (!idCharacter) {
        // Nog geen personage-id â†’ nieuwe personages worden via de Aanmaken-knop gemaakt
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
        // optioneel: console.log("Autosave ok voor veld", field);
    } catch (err) {
        console.error("Fout bij autosave van veld", field, err);
    }
}

// -----------------------
//  Nieuw personage
// -----------------------

async function handleCreateNewCharacter() {
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

    let dataObject = {};
    velden.forEach((veld) => {
        dataObject[veld] = document.getElementById(veld).value;
    });

    try {
        const result = await apiFetchJson("api/characters/newCharacter.php", {
            method: "POST",
            body: dataObject
        });

        // We verwachten JSON { id: ... }
        let newId = null;
        if (result && typeof result === "object" && "id" in result) {
            newId = result.id;
        } else if (typeof result === "number" || typeof result === "string") {
            newId = result;
        }

        if (!newId) {
            console.error("Kon geen nieuw character-ID afleiden uit response:", result);
            return;
        }

        document.getElementById("idCharacter").value = newId;
        clearCharacterFields();
        document.getElementById("groupNewCharacter").classList.add("d-none");

        // Lijst opnieuw laden, zodat nieuw personage in de lijst verschijnt
        await characterList();
    } catch (err) {
        console.error("Fout bij aanmaken nieuw personage:", err);
    }
}

// -----------------------
//  Character ophalen
// -----------------------

async function getCharacter(id, openCollapseId = null) {
    const dataObject = { id: id };

    try {
        const data = await apiFetchJson("api/characters/getCharacter.php", {
            method: "POST",
            body: dataObject
        });

        if (!data) return;

        // Bewaar globally zodat updateSkillLevel types/levels kent
        currentCharacter = data;

        // Debug
        console.log("Character from API:", data);
        console.log("Skills from API:", data.skills);

        // Gebruikte XP Ã©Ã©n keer berekenen
        const usedExperience = calculateExperience(data);

        // Navigatie bovenaan
        pageNav(currentUser.role, data);

        // Bepalen of dit personage bewerkbaar is voor de huidige user
        const role = currentUser?.role || "";
        const isAdmin = role === "administrator" || role === "director";

        const isOwnerPlayer =
            !isAdmin &&
            data.type === "player" &&
            Number(data.idUser) === Number(currentUser.id);

        const canEdit = isAdmin || isOwnerPlayer;

        console.log("currentUser:", currentUser);
        console.log("character type:", data.type, "idUser:", data.idUser);
        console.log("isAdmin:", isAdmin, "isOwnerPlayer:", isOwnerPlayer, "canEdit:", canEdit);

        // Algemene zichtbaarheid
        document.getElementById("groupNewCharacter").classList.add("d-none");
        document.getElementById("characterForm").classList.remove("d-none");
        document.getElementById("skills").classList.remove("d-none");
        document.getElementById("idCharacter").value = data["id"];

        const formContainer = document.getElementById("characterForm");
        const accordionSkills = document.getElementById("accordionSkills");

        // --- EDITABLE MODE (admin of owner-player) ---
        if (canEdit) {
            // Originele form-markup terugzetten (indien overschreven)
            if (originalCharacterFormHtml !== null) {
                formContainer.innerHTML = originalCharacterFormHtml;
                // Events opnieuw koppelen aan inputs
                setupCharacterFormListeners();
            }

            // Velden invullen
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
                    el.value = data[veld] ?? "";
                }
            });

            // Skills: normale accordion met knoppen
            if (accordionSkills) {
                accordionSkills.innerHTML = "";
                (data["skills"] || []).forEach((skill) => {
                    accordionSkills.appendChild(
                        addSkillAccordionItem(skill, usedExperience, data)
                    );
                });
            }

            // Nieuwe skills mogen toegevoegd worden
            const selNew = document.getElementById("idNewSkill");
            const btnAdd = document.getElementById("addNewSkill");
            if (selNew) selNew.disabled = false;
            if (btnAdd) btnAdd.disabled = false;

            // Eventuele extra readOnly-logica
            if (typeof applyCharacterEditability === "function") {
                applyCharacterEditability(data, true);
            }

        // --- READ-ONLY MODE (participant die naar extra / andere speler kijkt) ---
        } else {

            // Karaktergegevens in tekst-layout weergeven
            renderCharacterDetailsReadOnly(data);

            // Skills als leesbare lijst
            if (accordionSkills) {
                renderSkillsReadOnly(accordionSkills, data.skills || []);
            }

            // Nieuwe skills niet toe te voegen
            const selNew = document.getElementById("idNewSkill");
            const btnAdd = document.getElementById("addNewSkill");
            if (selNew) selNew.disabled = true;
            if (btnAdd) {
                btnAdd.disabled = true;
                btnAdd.classList.add("disabled");
            }
        }

        // Nieuwe skillslijst ophalen (voor editable case; voor read-only geeft visibility toch al filter)
        getNewSkills(data["id"]);

        initTooltips();

        // Na hertekenen: dezelfde collapse weer openklappen (enkel zinvol in editable mode)
        if (canEdit && openCollapseId) {
            const collapseEl = document.getElementById(openCollapseId);
            if (collapseEl) {
                collapseEl.classList.add("show");
                const headerBtn = collapseEl.previousElementSibling
                    ? collapseEl.previousElementSibling.querySelector("button")
                    : null;
                if (headerBtn) {
                    headerBtn.classList.remove("collapsed");
                    headerBtn.setAttribute("aria-expanded", "true");
                }
            }
        }

    } catch (err) {
        console.error("Fout bij ophalen personage:", err);
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

function renderSkillsReadOnly(container, skills) {
    container.innerHTML = "";

    if (!Array.isArray(skills) || skills.length === 0) {
        container.innerHTML = "<p>Geen vaardigheden.</p>";
        return;
    }

    skills.forEach(skill => {
        const level = Number(skill.level) || 0;
        if (level <= 0) {
            // Ongetraind: niet tonen in read-only lijst
            return;
        }

        // Niveau-label
        let proficiency = "ongetraind";
        if (level === 1) proficiency = "Beginneling";
        if (level === 2) proficiency = "Deskundige";
        if (level === 3) proficiency = "Meester";

        // Specialisaties splitsen in discipline vs gewone
        const specialisations = Array.isArray(skill.specialisations)
            ? skill.specialisations
            : [];

        const disciplineSpecs = specialisations.filter(s => s.kind === "discipline");
        const normalSpecs     = specialisations.filter(s => s.kind !== "discipline");

        // Titel: naam (+ discipline) - niveau
        let title = skill.name
            ? skill.name.charAt(0).toUpperCase() + skill.name.slice(1)
            : "Onbekende vaardigheid";

        if (disciplineSpecs.length > 0) {
            const labels = disciplineSpecs.map(d => d.name).join(", ");
            title += ` (${labels})`;
        }
        title += ` - ${proficiency}`;

        // H5
        const h5 = document.createElement("h5");
        h5.textContent = title;
        container.appendChild(h5);

        // Types (attribuut-types) als ul
        let types = [];
        if (Array.isArray(skill.types)) {
            types = skill.types;
        } else if (typeof skill.types === "string") {
            types = skill.types
                .split(",")
                .map(t => ({ name: t.trim(), description: "" }))
                .filter(t => t.name);
        }

        if (types.length > 0) {
            const ul = document.createElement("ul");
            types.forEach(typeObj => {
                const li = document.createElement("li");
                li.textContent = typeObj.name;
                ul.appendChild(li);
            });
            container.appendChild(ul);
        }

        // Specialisations (niet-discipline) komma-gescheiden
        if (normalSpecs.length > 0) {
            const pSpecs = document.createElement("p");
            const names = normalSpecs.map(s => s.name).join(", ");
            pSpecs.innerHTML = `<strong>Specialisations:</strong> ${names}`;
            container.appendChild(pSpecs);
        }

        // Beschrijving
        if (skill.description) {
            const pDesc = document.createElement("p");
            pDesc.textContent = skill.description;
            container.appendChild(pDesc);
        }

        // Alleen de GEKOZEN niveaus in de ol
        const ol = document.createElement("ol");

        if (level >= 1 && skill.beginner) {
            const li1 = document.createElement("li");
            li1.textContent = skill.beginner;
            ol.appendChild(li1);
        }

        if (level >= 2 && skill.professional) {
            const li2 = document.createElement("li");
            li2.textContent = skill.professional;
            ol.appendChild(li2);
        }

        if (level >= 3 && skill.master) {
            const li3 = document.createElement("li");
            li3.textContent = skill.master;
            ol.appendChild(li3);
        }

        container.appendChild(ol);

        // Scheiding tussen skills
        const hr = document.createElement("hr");
        container.appendChild(hr);
    });
}

function applyCharacterEditability(character, canEdit) {
    // ðŸ”¹ 1. Characterformulier: inputs wel/niet bewerkbaar
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
            // optioneel: visueel meer â€œtekstachtigâ€ maken
            // el.classList.add("form-control-plaintext");
        }
    });

    // ðŸ”¹ 2. Nieuwe skill toevoegen (dropdown + plusknop)
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

    // ðŸ”¹ 3. Alle skill-knoppen (Learn/Unlearn/Delete + specialisaties) blokkeren
    const accordion = document.getElementById("accordionSkills");
    if (accordion) {
        const buttons = accordion.querySelectorAll("button[data-action]");
        buttons.forEach((btn) => {
            if (canEdit) {
                // NIET actief her-enable-en â†’ XP-logica mag zâ€™n werk blijven doen.
                return;
            }
            btn.setAttribute("disabled", "disabled");
            btn.classList.add("disabled");
        });
    }
}


// -----------------------
//  Lijst van personages
// -----------------------

async function characterList() {
    const dataObject = {
        role: currentUser["role"]
    };

    try {
        const data = await apiFetchJson("api/characters/getCharacterList.php", {
            method: "POST",
            body: dataObject
        });

        const nameList = document.getElementById("characterNames");
        while (nameList.firstChild) {
            nameList.removeChild(nameList.lastChild);
        }

        if (!data) return;

        for (const value of Object.values(data)) {
            const newButton = document.createElement("button");
            newButton.classList.add("btn", "btn-primary", "btn-sm", "m-1");
            newButton.id = "showCharacter" + value["id"];
            newButton.innerHTML = value["firstName"] + " " + value["lastName"];
            newButton.setAttribute("data-idcharacter", value["id"]);
            newButton.setAttribute("data-bs-dismiss", "offcanvas");

            newButton.addEventListener("click", (e) => {
                document.getElementById("characterForm").classList.remove("d-none");
                document.getElementById("skills").classList.remove("d-none");
                getCharacter(e.target.dataset.idcharacter);
            });

            nameList.appendChild(newButton);
        }
    } catch (err) {
        console.error("Fout bij ophalen personagelijst:", err);
    }
}


