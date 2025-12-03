let currentUser = null;
let currentCharacter = null;
let originalCharacterFormHtml = null;

// Init na load
window.addEventListener("load", () => {
    initCharacterPage();

    document.getElementById("accordionSkills").addEventListener("click", function (e) {
        const btn = e.target.closest("button");
        if (!btn) return;

        // Als knop disabled is, doe niets
        if (btn.disabled || btn.classList.contains("disabled")) {
            return;
        }

        const action = btn.dataset.action;
        if (!action) return;

        // skillId kan uit data-skill of data-skill-id komen
        const skillId = btn.dataset.skill || btn.dataset.skillId;
        const idCharacter = document.getElementById("idCharacter").value;

        // Learn / Unlearn / Delete
        if (action === "up" || action === "down" || action === "delete") {
            if (!skillId) return;
            updateSkillLevel(action, skillId, idCharacter);
            return;
        }

        // Specialisatie toevoegen
        if (action === "spec-add") {
            if (!skillId) return;
            openSpecModal(parseInt(skillId, 10), parseInt(idCharacter, 10));
            return;
        }

        // Specialisatie verwijderen
        if (action === "spec-delete") {
            if (!skillId) return;
            const idSpec = parseInt(btn.dataset.specId, 10);
            deleteSpecialisation(skillId, idCharacter, idSpec);
            return;
        }
    });

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
    const btnCreate = document.getElementById("createNewCharacter");
    const btnCancel = document.getElementById("cancelNewCharacter");
    const btnStartNew = document.getElementById("startNewCharacter");

    // Nieuw personage aanmaken
    if (btnCreate) {
        btnCreate.addEventListener("click", handleCreateNewCharacter);
    }

    // Nieuw personage annuleren
    if (btnCancel) {
        btnCancel.addEventListener("click", () => {
            clearCharacterFields();
            document.getElementById("characterForm").classList.add("d-none");
            document.getElementById("skills").classList.add("d-none");
            document.getElementById("idCharacter").value = "";
        });
    }

    // “Nieuw personage” uit de offcanvas
    if (btnStartNew) {
        btnStartNew.addEventListener("click", () => {
            clearCharacterFields();
            document.getElementById("characterForm").classList.remove("d-none");
            document.getElementById("groupNewCharacter").classList.remove("d-none");
            document.getElementById("skills").classList.add("d-none");
            document.getElementById("idCharacter").value = "";
        });
    }

    // Autosave op alle relevante velden
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

// Template-renderer voor nav boven de sheet
function renderCharacterNav(isAdminView) {
    const container = document.getElementById("pageNav");
    if (!container) return;

    const templateId = isAdminView ? "tplNavSheetAdmin" : "tplNavSheetParticipant";
    const tpl = document.getElementById(templateId);

    if (!tpl) {
        console.error("Template niet gevonden:", templateId);
        return;
    }

    container.innerHTML = "";
    container.appendChild(tpl.content.cloneNode(true));
    container.classList.remove("d-none");
}

// -------------------
//  Autosave per veld
// -------------------

async function handleAutoSaveField(e) {
    const idCharacter = document.getElementById("idCharacter").value;
    if (!idCharacter) {
        // Nog geen personage-id → nieuwe personages worden via de Aanmaken-knop gemaakt
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
//  Navigatie boven sheet
// -----------------------

function pageNav(userRole, character) {
    const pageNavContainer = document.getElementById("pageNav");
    const usedExperience = calculateExperience(character);
    const expertise = expertiseExtra(usedExperience);

    const isAdmin = userRole === "administrator" || userRole === "director";

    if (isAdmin) {
        // Admin-template
        renderCharacterNav(true);

        // Deelnemer-koppeling (DropdownInput)
        function getUserList() {
            apiFetchJson("api/users/getUserList.php")
                .then((data) => {
                    if (!data) return;

                    const userList = [];
                    for (const value of Object.values(data)) {
                        userList.push({
                            id: value.id,
                            text: value.firstName + " " + value.lastName
                        });
                    }

                    new DropdownInput(
                        "listParticipant",
                        userList,
                        async (selectedId) => {
                            let newIdUser;

                            // null vanuit reset-knop = loskoppelen
                            if (selectedId === null || selectedId === undefined || selectedId === "") {
                                newIdUser = 0;
                            } else {
                                newIdUser = parseInt(selectedId, 10);
                            }

                            if (character.idUser == newIdUser) {
                                return;
                            }

                            const payload = {
                                id: character.id,
                                idUser: newIdUser
                            };

                            try {
                                await apiFetchJson("api/characters/updateCharacter.php", {
                                    method: "POST",
                                    body: payload
                                });
                                character.idUser = newIdUser;
                            } catch (err) {
                                console.error("Fout bij koppelen/loskoppelen deelnemer:", err);
                            }
                        },
                        character.idUser
                    );
                })
                .catch((err) => {
                    console.error("Fout bij ophalen userlist:", err);
                });
        }

        activateSelectOption("type", character["type"]);
        activateSelectOption("state", character["state"]);

        // Autosave voor type & state
        const selType = document.getElementById("type");
        const selState = document.getElementById("state");

        if (selType) {
            selType.addEventListener("change", async (e) => {
                const idCharacter = document.getElementById("idCharacter").value;
                if (!idCharacter) return;

                const newType = e.target.value;
                const payload = {
                    id: idCharacter,
                    type: newType
                };
                try {
                    await apiFetchJson("api/characters/updateCharacter.php", {
                        method: "POST",
                        body: payload
                    });

                    // Frontend state mee aanpassen
                    if (currentCharacter) {
                        currentCharacter.type = newType;
                        pageNav(currentUser.role, currentCharacter);
                    }

                } catch (err) {
                    console.error("Fout bij opslaan type:", err);
                }
            });
        }

        if (selState) {
            selState.addEventListener("change", async (e) => {
                const idCharacter = document.getElementById("idCharacter").value;
                if (!idCharacter) return;

                const payload = {
                    id: idCharacter,
                    state: e.target.value
                };
                try {
                    await apiFetchJson("api/characters/updateCharacter.php", {
                        method: "POST",
                        body: payload
                    });
                } catch (err) {
                    console.error("Fout bij opslaan status:", err);
                }
            });
        }

        getUserList();
    } else {
        // Participant-template
        renderCharacterNav(false);
        document.getElementById("nameParticipant").innerHTML = character.nameParticipant;
        document.getElementById("type").innerHTML = character.type;
        document.getElementById("state").innerHTML = character.state;
    }

    // Experience-label
    const lblExp = document.getElementById("lblExperiance");
    if (lblExp) {
        if (character.type === "player") {
            // max EP bepalen
            let maxExperience;

            if (typeof character.experience === "number") {
                // normale situatie: backend stuurde experience/maxExperience mee
                maxExperience = character.experience;
            } else if (typeof character.maxExperience === "number") {
                maxExperience = character.maxExperience;
            } else if (!character.idUser || character.idUser === 0) {
                // géén deelnemer gekoppeld → standaard 15 EP
                maxExperience = 15;
            } else {
                // safety fallback
                maxExperience = 15;
            }

            lblExp.innerHTML = `${usedExperience} / ${maxExperience}`;
        } else {
            // figurant / NPC
            lblExp.innerHTML = `${usedExperience} (${expertise})`;
        }
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

        // Gebruikte XP één keer berekenen
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
    // 🔹 1. Characterformulier: inputs wel/niet bewerkbaar
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
            // optioneel: visueel meer “tekstachtig” maken
            // el.classList.add("form-control-plaintext");
        }
    });

    // 🔹 2. Nieuwe skill toevoegen (dropdown + plusknop)
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

    // 🔹 3. Alle skill-knoppen (Learn/Unlearn/Delete + specialisaties) blokkeren
    const accordion = document.getElementById("accordionSkills");
    if (accordion) {
        const buttons = accordion.querySelectorAll("button[data-action]");
        buttons.forEach((btn) => {
            if (canEdit) {
                // NIET actief her-enable-en → XP-logica mag z’n werk blijven doen.
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

// -----------------------
//  Skills
// -----------------------

async function getNewSkills(idCharacter) {
    const dataObject = {
        id: idCharacter,
        role: (currentUser && currentUser.role) ? currentUser.role : "participant"
    };

    try {
        const data = await apiFetchJson("api/characters/getNewSkills.php", {
            method: "POST",
            body: dataObject
        });

        const idNewSkill = document.getElementById("idNewSkill");
        while (idNewSkill.firstChild) {
            idNewSkill.removeChild(idNewSkill.lastChild);
        }

        if (!data) return;

        for (const value of Object.values(data)) {
            const newOption = document.createElement("option");
            newOption.innerHTML =
                value["name"].charAt(0).toUpperCase() + value["name"].slice(1);
            newOption.value = value["id"];
            idNewSkill.appendChild(newOption);
        }

        // Nieuwe skill meteen toevoegen-knop
        const btnAddSkill = document.getElementById("addNewSkill");
        if (btnAddSkill) {
            btnAddSkill.onclick = async () => {
                const payload = {
                    idCharacter: document.getElementById("idCharacter").value,
                    idSkill: document.getElementById("idNewSkill").value,
                    level: 0
                };

                try {
                    const result = await apiFetchJson("api/characters/AddNewSkill.php", {
                        method: "POST",
                        body: payload
                    });

                    if (!result) return;

                    const idChar = document.getElementById("idCharacter").value;
                    const newSkillId = result.id;

                    // Volledig personage opnieuw ophalen zodat currentCharacter.skills
                    // de nieuwe skill mét types bevat, en meteen de nieuwe skill openklappen
                    const openCollapseId = "collapse" + newSkillId;
                    getCharacter(idChar, openCollapseId);

                } catch (err) {
                    console.error("Fout bij toevoegen vaardigheid:", err);
                }
            };
        }
    } catch (err) {
        console.error("Fout bij ophalen nieuwe skills:", err);
    }
}

// Velden leegmaken
function clearCharacterFields() {
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
        if (el) el.value = "";
    });
}

function updateSkillLevel(action, idSkill, idCharacter, options = {}) {
    const { skipDisciplineCheck = false } = options;

    // 1) Discipline-check op basis van currentCharacter
    const skill = (currentCharacter?.skills || []).find(
        s => Number(s.id) === Number(idSkill)
    );

    // Bepaal of deze skill een discipline-type heeft
    let hasDiscipline = false;
    if (skill && skill.types) {
        if (Array.isArray(skill.types)) {
            hasDiscipline = skill.types.some(t =>
                (t.code && t.code.toLowerCase() === "discipline") ||
                (t.name && t.name.toLowerCase() === "discipline")
            );
        } else if (typeof skill.types === "string") {
            hasDiscipline = skill.types
                .toLowerCase()
                .split(",")
                .map(t => t.trim())
                .includes("discipline");
        }
    }

    const currentLevel = skill ? Number(skill.level || 0) : 0;

    // Alleen de eerste keer (bij level 0) de modal tonen
    if (!skipDisciplineCheck && hasDiscipline && action === "up" && currentLevel === 0) {
        openDisciplineModal(Number(idSkill), Number(idCharacter), skill);
        return; // NIET naar updateSkill.php
    }

    // 2) Accordion-state onthouden
    let openCollapseId = null;
    const openCollapse = document.querySelector(
        "#accordionSkills .accordion-collapse.show"
    );
    if (openCollapse) {
        openCollapseId = openCollapse.id;
    }

    // 3) Normale flow
    apiFetchJson("api/characters/updateSkill.php", {
        method: "POST",
        body: {
            action: action,
            idSkill: idSkill,
            idCharacter: idCharacter
        }
    })
    .then(data => {
        if (!data) {
            console.error("Lege response van updateSkill.php");
            return;
        }
        if (data.error) {
            alert(data.error);
            return;
        }

        // Personage opnieuw laden, dezelfde collapse open
        getCharacter(idCharacter, openCollapseId);
    })
    .catch(err => {
        console.error("Fout bij updateSkillLevel:", err);
    });
}


function addSkillAccordionItem(skill, usedExperience, character) {
    // --- Basisdata & defaults ---
    const level = Number(skill.level) || 0;

    // Specialisaties kunnen als array van objects komen
    // bv. [{id:1,name:"Historian"}, ...]
    const specialisations = Array.isArray(skill.specialisations)
        ? skill.specialisations
        : [];

    // splitsen: disciplines vs andere specialisaties
    const disciplineSpecs = specialisations.filter(s => s.kind === "discipline");
    const normalSpecs     = specialisations.filter(s => s.kind !== "discipline");

    // Proficiency label
    let proficiency = "ongetraind";
    if (level === 1) proficiency = "Beginneling";
    if (level === 2) proficiency = "Deskundige";
    if (level === 3) proficiency = "Meester";

    // --- Accordion container ---
    const accordionItem = document.createElement("div");
    accordionItem.classList.add("accordion-item");

    // Header
    const header = document.createElement("h2");
    header.classList.add("accordion-header");

    const headerBtn = document.createElement("button");
    headerBtn.classList.add("accordion-button", "collapsed");
    headerBtn.type = "button";
    headerBtn.setAttribute("data-bs-toggle", "collapse");
    headerBtn.setAttribute("data-bs-target", "#collapse" + skill.id);
    headerBtn.setAttribute("aria-expanded", "false");
    headerBtn.setAttribute("aria-controls", "collapse" + skill.id);

    let title = skill.name.charAt(0).toUpperCase() + skill.name.slice(1);

    // discipline(s) achter de naam tussen haakjes
    if (disciplineSpecs.length > 0) {
        const labels = disciplineSpecs.map(d => d.name).join(", ");
        title += ` (${labels})`;
    }

    headerBtn.innerHTML = `${title} - ${proficiency}`;


    header.appendChild(headerBtn);
    accordionItem.appendChild(header);

    // Collapse
    const collapse = document.createElement("div");
    collapse.classList.add("accordion-collapse", "collapse");
    collapse.id = "collapse" + skill.id;
    collapse.setAttribute("data-bs-parent", "#accordionSkills");

    const body = document.createElement("div");
    body.classList.add("accordion-body");

    // --- Types (attribuut-types) ---
    // Backend stuurt nu idealiter: skill.types = [{name, description}, ...]
    let types = [];
    if (Array.isArray(skill.types)) {
        types = skill.types;
    } else if (typeof skill.types === "string") {
        // fallback voor oude data: "Academic,Criminal"
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

            // Bootstrap tooltip met de BESCHRIJVING
            li.setAttribute("data-bs-toggle", "tooltip");
            li.setAttribute("data-bs-placement", "left"); // boven het woord
            li.setAttribute(
                "title",
                typeObj.description || ("Type " + typeObj.name)
            );

            ul.appendChild(li);
        });

        body.appendChild(ul);
    }

    // --- Specialisations-balk ---
    const specWrapper = document.createElement("div");
    specWrapper.classList.add(
        "d-flex",
        "justify-content-start",
        "flex-wrap",
        "mb-2"
    );

    const specLabel = document.createElement("div");
    specLabel.classList.add("me-2", "fw-bold");
    specLabel.textContent = "Specialisations:";
    specWrapper.appendChild(specLabel);

    // Bestaande specialisaties
    normalSpecs.forEach(spec => {
        const specGroup = document.createElement("div");
        specGroup.classList.add("spec-group", "me-2", "mb-2");
        specGroup.dataset.specId = spec.id;
        specGroup.dataset.skillId = skill.id;

        const badge = document.createElement("div");
        badge.classList.add("input-group-text", "btn-outline-secondary");
        badge.textContent = spec.name;

        const delBtn = document.createElement("button");
        delBtn.type = "button";
        delBtn.classList.add("btn", "btn-danger");
        delBtn.innerHTML = `<i class="fa-solid fa-trash"></i>`;
        delBtn.dataset.action = "spec-delete";
        delBtn.dataset.specId = spec.id;
        delBtn.dataset.skillId = skill.id;

        specGroup.appendChild(badge);
        specGroup.appendChild(delBtn);
        specWrapper.appendChild(specGroup);
    });

    // --- Kan speler nog een (niet-discipline) specialisatie betalen? ---
    let canAddSpec = true;

    if (character && character.type === "player") {
        const maxXP = Number(character.experience) || 0;
        const costSpec = 2; // elke niet-discipline specialisatie kost 2 EP

        if (maxXP > 0 && (usedExperience + costSpec) > maxXP) {
            canAddSpec = false;
        }
    }

    // “+” knop om specialisatie toe te voegen
    const addSpecBtn = document.createElement("button");
    addSpecBtn.type = "button";
    addSpecBtn.classList.add("btn", "btn-primary", "btn-sm", "mb-2");
    addSpecBtn.innerHTML = `<i class="fa-solid fa-plus"></i>`;
    addSpecBtn.dataset.action = "spec-add";
    addSpecBtn.dataset.skillId = skill.id;

    // Als er niet genoeg XP is: disabled
    if (!canAddSpec) {
        addSpecBtn.disabled = true;
        addSpecBtn.classList.add("disabled");
        addSpecBtn.setAttribute(
            "title",
            "Onvoldoende ervaringspunten voor een extra specialisatie."
        );
    }

    specWrapper.appendChild(addSpecBtn);

    body.appendChild(specWrapper);

    // --- Beschrijving & niveaus ---
    if (skill.description) {
        const pDesc = document.createElement("p");
        pDesc.textContent = skill.description;
        body.appendChild(pDesc);
    }

    // Niveauregels als genummerde lijst
    const ol = document.createElement("ol");

    const li1 = document.createElement("li");
    li1.textContent = skill.beginner || "";
    if (level < 1) li1.classList.add("opacity-25");
    ol.appendChild(li1);

    const li2 = document.createElement("li");
    li2.textContent = skill.professional || "";
    if (level < 2) li2.classList.add("opacity-25");
    ol.appendChild(li2);

    const li3 = document.createElement("li");
    li3.textContent = skill.master || "";
    if (level < 3) li3.classList.add("opacity-25");
    ol.appendChild(li3);

    body.appendChild(ol);

    // --- Learn / Unlearn / Delete buttons ---
    const btnGroup = document.createElement("div");
    btnGroup.classList.add("btn-group", "mt-3");
    btnGroup.setAttribute("role", "group");

    // --- Learn button ---
    const btnUp = document.createElement("button");
    btnUp.type = "button";
    btnUp.classList.add("btn", "btn-primary");
    btnUp.dataset.action = "up";
    btnUp.dataset.skill = skill.id;
    btnUp.innerHTML = `<i class="fa-solid fa-caret-up"></i> Learn`;

    // disable bij max niveau
    if (level >= 3) {
        btnUp.disabled = true;
        btnUp.classList.add("disabled");
    }

    // --- Unlearn button ---
    const btnDown = document.createElement("button");
    btnDown.type = "button";
    btnDown.classList.add("btn", "btn-primary");
    btnDown.dataset.action = "down";
    btnDown.dataset.skill = skill.id;
    btnDown.innerHTML = `<i class="fa-solid fa-caret-down"></i> Unlearn`;

    // disable bij niveau 0
    if (level <= 0) {
        btnDown.disabled = true;
        btnDown.classList.add("disabled");
    }

    // --- Delete button ---
    const btnDelete = document.createElement("button");
    btnDelete.type = "button";
    btnDelete.classList.add("btn", "btn-danger");
    btnDelete.dataset.action = "delete";
    btnDelete.dataset.skill = skill.id;
    btnDelete.innerHTML = `<i class="fa-solid fa-trash" aria-hidden="true"></i> Delete`;

    btnGroup.appendChild(btnUp);
    btnGroup.appendChild(btnDown);
    btnGroup.appendChild(btnDelete);

    body.appendChild(btnGroup);

    collapse.appendChild(body);
    accordionItem.appendChild(collapse);

    return accordionItem;
}



function initTooltips() {
    const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.forEach(el => {
        new bootstrap.Tooltip(el);
    });
}

let specModalInstance = null;

function openSpecModal(idSkill, idCharacter) {
    const modalEl = document.getElementById("specModal");
    modalEl.dataset.skillId = idSkill;
    modalEl.dataset.characterId = idCharacter;

    // Onthouden welke collapse open staat
    const collapseEl = document.getElementById("collapse" + idSkill);
    if (collapseEl && collapseEl.classList.contains("show")) {
        modalEl.dataset.openCollapseId = collapseEl.id;
    } else {
        delete modalEl.dataset.openCollapseId;
    }

    // Velden leegmaken
    const select = document.getElementById("specSelect");
    select.innerHTML = "";
    document.getElementById("specCustom").value = "";

    // Default: existing-tab actief (wordt aangepast als er geen opties zijn)
    const tabExisting = document.getElementById("spec-existing-tab");
    const tabNew = document.getElementById("spec-new-tab");
    const paneExisting = document.getElementById("spec-existing-pane");
    const paneNew = document.getElementById("spec-new-pane");

    tabExisting.classList.add("active");
    tabExisting.setAttribute("aria-selected", "true");
    paneExisting.classList.add("show", "active");

    tabNew.classList.remove("active");
    tabNew.setAttribute("aria-selected", "false");
    paneNew.classList.remove("show", "active");

    // opties ophalen
    apiFetchJson("api/characters/getSkillSpecialisations.php", {
        method: "POST",
        body: { idSkill, idCharacter }
    })
    .then(data => {
        const options = data?.options || [];

        options.forEach(opt => {
            const o = document.createElement("option");
            o.value = opt.id;
            o.textContent = opt.name;
            select.appendChild(o);
        });

        // Als er geen bestaande opties zijn: meteen naar "New" tab schakelen
        if (options.length === 0) {
            tabExisting.classList.remove("active");
            tabExisting.setAttribute("aria-selected", "false");
            paneExisting.classList.remove("show", "active");

            tabNew.classList.add("active");
            tabNew.setAttribute("aria-selected", "true");
            paneNew.classList.add("show", "active");
        }
    })
    .catch(err => console.error("Fout bij ophalen specialisaties:", err));

    if (!specModalInstance) {
        specModalInstance = new bootstrap.Modal(modalEl);
    }
    specModalInstance.show();
}

document.getElementById("specSaveBtn").addEventListener("click", () => {
    const modalEl = document.getElementById("specModal");
    const idSkill = parseInt(modalEl.dataset.skillId, 10);
    const idCharacter = parseInt(modalEl.dataset.characterId, 10);
    const openCollapseId = modalEl.dataset.openCollapseId || null;

    const select = document.getElementById("specSelect");
    const custom = document.getElementById("specCustom").value.trim();

    // Welke tab is actief?
    const activeTab = document
        .querySelector("#specTab .nav-link.active")
        ?.dataset.tab;

    let payload;

    if (activeTab === "existing") {
        // Gebruiker kiest uit de dropdown
        const selectedVal = select.value;
        if (!selectedVal) {
            alert("Kies een bestaande specialisatie of ga naar 'New'.");
            return;
        }
        payload = {
            idSkill,
            idCharacter,
            idSkillSpecialisation: parseInt(selectedVal, 10),
            name: ""           // geen nieuwe naam
        };
    } else if (activeTab === "new") {
        if (!custom) {
            alert("Vul een naam in voor de nieuwe specialisatie.");
            return;
        }
        payload = {
            idSkill,
            idCharacter,
            idSkillSpecialisation: 0, // 0 = nieuwe / onbekende specialisatie
            name: custom
        };
    } else {
        alert("Onbekende tab-selectie.");
        return;
    }

    apiFetchJson("api/characters/addSkillSpecialisation.php", {
        method: "POST",
        body: payload
    })
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        // Character opnieuw laden, met dezelfde collapse open
        getCharacter(idCharacter, openCollapseId);
        specModalInstance.hide();
    })
    .catch(err => {
        console.error("Fout bij opslaan specialisatie:", err);
    });
});

function deleteSpecialisation(idSkill, idCharacter, idSpec) {
    const openCollapseId = "collapse" + idSkill;

    apiFetchJson("api/characters/deleteSkillSpecialisation.php", {
        method: "POST",
        body: {
            idSkill: parseInt(idSkill, 10),
            idCharacter: parseInt(idCharacter, 10),
            idSkillSpecialisation: parseInt(idSpec, 10)
        }
    })
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        getCharacter(idCharacter, openCollapseId);
    })
    .catch(err => {
        console.error("Fout bij verwijderen specialisatie:", err);
    });
}

// Save knop
document.getElementById("disciplineSaveBtn")?.addEventListener("click", async () => {
    const modalEl = document.getElementById("disciplineModal");
    if (!modalEl) return;

    const idSkill = parseInt(modalEl.dataset.skillId, 10);
    const idCharacter = parseInt(modalEl.dataset.characterId, 10);

    const select = document.getElementById("disciplineSelect");
    const customInput = document.getElementById("disciplineCustom");

    const activeTab = document
        .querySelector("#disciplineTab .nav-link.active")
        ?.getAttribute("data-tab");

    let idSpec = 0;
    let name = "";

    if (activeTab === "existing") {
        idSpec = select && select.value ? parseInt(select.value, 10) : 0;
        if (!idSpec) {
            alert("Kies een bestaande discipline of ga naar 'New'.");
            return;
        }
    } else if (activeTab === "new") {
        name = customInput ? customInput.value.trim() : "";
        if (!name) {
            alert("Geef een naam in voor de nieuwe discipline.");
            return;
        }
    }

    // 1) Discipline-specialisatie opslaan (met kind = 'discipline')
    const payload = {
        idSkill,
        idCharacter,
        idSkillSpecialisation: idSpec,
        name: name,
        kind: "discipline"
    };

    try {
        const result = await apiFetchJson("api/characters/addSkillSpecialisation.php", {
            method: "POST",
            body: payload
        });

        if (result?.error) {
            alert(result.error);
            return;
        }

        // 2) Nu de skill echt levelen, ZONDER discipline-check
        await updateSkillLevel("up", idSkill, idCharacter, { skipDisciplineCheck: true });

        // 3) Modal sluiten
        disciplineModalInstance?.hide();

    } catch (err) {
        console.error("Fout bij opslaan discipline:", err);
    }
});

// ---------------------------
// Discipline-modal
// ---------------------------
let disciplineModalInstance = null;

function openDisciplineModal(idSkill, idCharacter, skill) {
    const modalEl = document.getElementById("disciplineModal");
    if (!modalEl) {
        console.error("disciplineModal element niet gevonden");
        return;
    }

    modalEl.dataset.skillId = idSkill;
    modalEl.dataset.characterId = idCharacter;

    // Titel: skillnaam tonen
    const titleSpan = document.getElementById("disciplineSkillTitle");
    if (titleSpan && skill) {
        titleSpan.textContent = skill.name;
    }

    // Velden leegmaken
    const select = document.getElementById("disciplineSelect");
    const customInput = document.getElementById("disciplineCustom");
    if (select) select.innerHTML = "";
    if (customInput) customInput.value = "";

    // Standaard tab: Existing
    const tabExisting = document.getElementById("discipline-existing-tab");
    const tabNew = document.getElementById("discipline-new-tab");
    if (tabExisting && tabNew) {
        const tab = new bootstrap.Tab(tabExisting);
        tab.show();
    }

    // Disciplines ophalen (bestaande entries voor deze skill)
    apiFetchJson("api/characters/getDisciplineList.php", {
        method: "POST",
        body: { idSkill, idCharacter }
    })
        .then(data => {
            const options = data?.options || [];
            if (select) {
                options.forEach(opt => {
                    const o = document.createElement("option");
                    o.value = opt.id;
                    o.textContent = opt.name;
                    select.appendChild(o);
                });
            }
        })
        .catch(err => {
            console.error("Fout bij ophalen discipline-lijst:", err);
        });

    // Modal instantie maken (éénmalig)
    if (!disciplineModalInstance) {
        disciplineModalInstance = new bootstrap.Modal(modalEl);
    }
    disciplineModalInstance.show();
}

// Cancel knop: gewoon modal sluiten, géén skill-level wijzigen
document.getElementById("disciplineCancelBtn")?.addEventListener("click", () => {
    if (disciplineModalInstance) {
        disciplineModalInstance.hide();
    } else {
        const modalEl = document.getElementById("disciplineModal");
        if (modalEl) {
            const inst = bootstrap.Modal.getInstance(modalEl);
            inst?.hide();
        }
    }
});