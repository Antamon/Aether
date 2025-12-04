// Skills-related logic (fetch new skills, render accordion, modals, skill level updates)

function setupSkillListeners() {
    const accordion = document.getElementById("accordionSkills");
    if (!accordion) return;

    accordion.addEventListener("click", function (e) {
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
}

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
                    // de nieuwe skill met types bevat, en meteen de nieuwe skill openklappen
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

// Save knop discipline
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

    // Modal instantie maken (eenmalig)
    if (!disciplineModalInstance) {
        disciplineModalInstance = new bootstrap.Modal(modalEl);
    }
    disciplineModalInstance.show();
}

// Cancel knop: gewoon modal sluiten, geen skill-level wijzigen
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
