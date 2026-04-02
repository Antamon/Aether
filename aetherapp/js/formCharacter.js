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
        "maritalStatus"
    ];

    velden.forEach((veld) => {
        const el = document.getElementById(veld);
        if (el) {
            el.addEventListener("change", handleAutoSaveField);
        }
    });
}

function canEditHealthForCharacter(character) {
    if (!currentUser || !character) return false;

    const role = currentUser.role;
    if (role === "administrator" || role === "director") {
        return true;
    }

    return role === "participant"
        && character.type === "player"
        && character.state === "draft"
        && Number(character.idUser) === Number(currentUser.id);
}

function canEditFreeHealthForCharacter(character) {
    if (!currentUser) return false;
    return currentUser.role === "administrator" || currentUser.role === "director";
}

function canEditClassForCharacter(character) {
    if (!currentUser || !character) return false;

    const role = currentUser.role;
    if (role === "administrator" || role === "director") {
        return true;
    }

    return role === "participant"
        && character.type === "player"
        && character.state === "draft"
        && Number(character.idUser) === Number(currentUser.id);
}

function syncClassFieldPresentation(character) {
    const classRow = document.getElementById("classRow");
    if (!classRow || !character) return;

    const valueCol = classRow.querySelector(".col-sm-8");
    if (!valueCol) return;

    if (canEditClassForCharacter(character)) {
        const select = document.getElementById("class");
        if (select) {
            select.value = character.class || "";
        }
        return;
    }

    valueCol.textContent = character.class || "";
}

function formatCharacterCurrency(amount) {
    return `${Number(amount || 0).toLocaleString("nl-BE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function getUpperClassNobilityIncomeMultiplier(character) {
    if (character?.class !== "upper class") {
        return 1;
    }

    const traitGroups = Array.isArray(character?.traitGroups) ? character.traitGroups : [];
    const titleGroup = traitGroups.find((group) => group?.name === "Adellijke titel");
    const linkedTitle = Array.isArray(titleGroup?.linkedTraits) ? titleGroup.linkedTraits[0] : null;
    const linkedTitleName = String(linkedTitle?.name || "").trim().toLowerCase();

    if (linkedTitleName === "familiehoofd") {
        return 2;
    }

    if (linkedTitle) {
        return 1.5;
    }

    return 1;
}

function getCharacterTraitIncomeTotal(character) {
    const groups = [
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : []),
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : [])
    ];
    const seenTraitIds = new Set();
    const nobilityIncomeMultiplier = getUpperClassNobilityIncomeMultiplier(character);

    return groups.reduce((total, group) => {
        const linkedTraits = Array.isArray(group?.linkedTraits) ? group.linkedTraits : [];
        linkedTraits.forEach((trait) => {
            const traitId = Number(trait?.idTrait || trait?.id || 0);
            if (traitId > 0 && seenTraitIds.has(traitId)) {
                return;
            }

            if (traitId > 0) {
                seenTraitIds.add(traitId);
            }

            const income = typeof calculateTraitIncomeAtRank === "function"
                ? calculateTraitIncomeAtRank(trait)
                : Number(trait?.income || 0);

            if (income !== null && Number.isFinite(Number(income))) {
                const adjustedIncome = group?.name === "Adeldom"
                    ? Number(income) * nobilityIncomeMultiplier
                    : Number(income);
                total += adjustedIncome;
            }
        });
        return total;
    }, 0);
}

function renderCharacterWealthSection(character) {
    const container = document.getElementById("wealthSectionContent");
    if (!container || !character) return;

    const totalIncome = getCharacterTraitIncomeTotal(character);
    const bankBalance = typeof getDisplayedCharacterBankAccountAmount === "function"
        ? getDisplayedCharacterBankAccountAmount(character)
        : Number(character?.bankaccount ?? 0);
    container.innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-4">
            <span>Inkomsten: ${formatCharacterCurrency(totalIncome)}</span>
            <span>Bankrekening: ${formatCharacterCurrency(bankBalance)}</span>
        </div>
    `;
}

function getCharacterLinkedTraits(character) {
    const groups = [
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : []),
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : [])
    ];
    const seenTraitIds = new Set();
    const linkedTraits = [];

    groups.forEach((group) => {
        const groupTraits = Array.isArray(group?.linkedTraits) ? group.linkedTraits : [];
        groupTraits.forEach((trait) => {
            const traitId = Number(trait?.idTrait || trait?.id || 0);
            if (traitId > 0 && seenTraitIds.has(traitId)) {
                return;
            }

            if (traitId > 0) {
                seenTraitIds.add(traitId);
            }

            linkedTraits.push(trait);
        });
    });

    return linkedTraits;
}

function getCharacterStaffRequirementTraits(character) {
    return getCharacterLinkedTraits(character).filter((trait) => Number(trait?.staffRequirements ?? 0) > 0);
}

function isStaffTieConfirmed(tie) {
    const relationType = String(tie?.relationType || "").toLowerCase();
    if (relationType === "dependent") {
        return Boolean(tie?.hasReverseSuperior);
    }

    if (relationType === "spouse") {
        return Boolean(tie?.hasReverseSpouse);
    }

    return false;
}

async function renderCharacterStaffSection(character) {
    const container = document.getElementById("staffSectionContent");
    if (!container || !character?.id) return;

    const staffRequirementTraits = getCharacterStaffRequirementTraits(character);
    const requiredStaff = staffRequirementTraits.reduce(
        (total, trait) => total + Number(trait?.staffRequirements ?? 0),
        0
    );

    let ties = [];
    try {
        if (typeof fetchCharacterTies === "function") {
            ties = await fetchCharacterTies(character.id) || [];
        } else {
            ties = await apiFetchJson("api/characters/getCharacterTies.php", {
                method: "POST",
                body: { idCharacter: character.id }
            }) || [];
        }
    } catch (err) {
        console.error("Fout bij ophalen ties voor personeel:", err);
    }

    if (currentCharacter && Number(currentCharacter.id) !== Number(character.id)) {
        return;
    }

    const staffTies = ties.filter((tie) => {
        const relationType = String(tie?.relationType || "").toLowerCase();
        return relationType === "dependent" || relationType === "spouse";
    });
    const confirmedStaffTies = staffTies.filter((tie) => isStaffTieConfirmed(tie));

    const traitList = staffRequirementTraits.length > 0
        ? `<ul class="list-unstyled mb-3">${staffRequirementTraits
            .map((trait) => `
                <li class="d-flex justify-content-between align-items-center gap-3 mb-2">
                    <span>${trait.name || ""}</span>
                    <span class="badge text-bg-secondary">${Number(trait.staffRequirements || 0)}</span>
                </li>
            `)
            .join("")}</ul>`
        : `<p class="text-muted mb-3">Geen traits met personeelsvoorwaarde.</p>`;

    const dependentList = staffTies.length > 0
        ? `<ul class="list-unstyled mb-0">${staffTies
            .map((tie) => {
                const label = [tie.lastName, tie.firstName].filter(Boolean).join(" ").trim() || tie.otherName || "";
                const isConfirmed = isStaffTieConfirmed(tie);
                const badgeClass = isConfirmed ? "text-bg-success" : "text-bg-danger";
                const badgeText = isConfirmed ? "Bevestigd" : "Onbevestigd";
                return `
                    <li class="d-flex justify-content-between align-items-center gap-3 mb-2">
                        <span>${label}</span>
                        <span class="badge ${badgeClass}">${badgeText}</span>
                    </li>
                `;
            })
            .join("")}</ul>`
        : `<p class="text-muted mb-0">Geen dependent of spouse ties.</p>`;

    container.innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
            <span>Personeelsvoorwaarde: ${requiredStaff}</span>
            <span>Voldaan: ${confirmedStaffTies.length}</span>
        </div>
        <h5>Eigenschappen met personeelsvoorwaarden</h5>
        ${traitList}
        <h5>Gevolg</h5>
        ${dependentList}
    `;
}

function createHealthIconStrip(total, primaryIconPath, secondaryIconPath, label) {
    const safeTotal = Math.max(0, Number(total) || 0);
    const primaryCount = Math.ceil(safeTotal / 2);
    const secondaryCount = Math.floor(safeTotal / 2);

    const wrapper = document.createElement("span");
    wrapper.className = "ms-2 d-inline-flex flex-wrap align-items-center gap-1";
    wrapper.setAttribute("aria-label", `${label}: ${safeTotal}`);

    const appendIcon = (src, alt) => {
        const img = document.createElement("img");
        img.src = src;
        img.alt = alt;
        img.width = 20;
        img.height = 20;
        wrapper.appendChild(img);
    };

    for (let i = 0; i < primaryCount; i++) {
        appendIcon(primaryIconPath, label);
    }

    for (let i = 0; i < secondaryCount; i++) {
        appendIcon(secondaryIconPath, label);
    }

    return wrapper;
}

function renderCharacterHealthSection(character) {
    const container = document.getElementById("healthSectionContent");
    if (!container || !character) return;

    const canEditHealth = canEditHealthForCharacter(character);
    const canEditFreeHealth = canEditFreeHealthForCharacter(character);
    const remainingExperience = getRemainingExperience(character);

    const rows = [
        {
            label: "Fysieke gezondheid",
            value: getPhysicalHealthValue(character),
            paidField: "physicalHealth",
            freeField: "physicalHealthFree",
            primaryIconPath: "img/heart-red.png",
            secondaryIconPath: "img/heart-green.png"
        },
        {
            label: "Mentale gezondheid",
            value: getMentalHealthValue(character),
            paidField: "mentalHealth",
            freeField: "mentalHealthFree",
            primaryIconPath: "img/eye-yellow.png",
            secondaryIconPath: "img/eye-white.png"
        }
    ];

    container.innerHTML = "";

    rows.forEach((rowConfig) => {
        const paidValue = getCharacterIntValue(character, rowConfig.paidField);
        const freeValue = getCharacterIntValue(character, rowConfig.freeField);
        const totalValue = rowConfig.value;

        const row = document.createElement("div");
        row.className = "d-flex justify-content-between align-items-start gap-3 mb-3";

        const labelCol = document.createElement("div");
        labelCol.className = "me-2 d-flex flex-wrap align-items-center";

        const labelText = document.createElement("span");
        labelText.textContent = rowConfig.label;
        labelCol.appendChild(labelText);
        labelCol.appendChild(
            createHealthIconStrip(
                rowConfig.value,
                rowConfig.primaryIconPath,
                rowConfig.secondaryIconPath,
                rowConfig.label
            )
        );
        row.appendChild(labelCol);

        if (canEditHealth) {
            const controls = document.createElement("div");
            controls.className = "d-flex flex-column align-items-end gap-1";

            const paidControls = document.createElement("div");
            paidControls.className = "btn-group btn-group-sm";

            const minusBtn = document.createElement("button");
            minusBtn.type = "button";
            minusBtn.className = "btn btn-outline-secondary";
            minusBtn.dataset.action = "character-health-adjust";
            minusBtn.dataset.field = rowConfig.paidField;
            minusBtn.dataset.delta = "-1";
            minusBtn.innerHTML = `<i class="fa-solid fa-minus"></i>`;
            minusBtn.disabled = paidValue <= -3 || totalValue <= 1;
            paidControls.appendChild(minusBtn);

            const plusBtn = document.createElement("button");
            plusBtn.type = "button";
            plusBtn.className = "btn btn-outline-secondary";
            plusBtn.dataset.action = "character-health-adjust";
            plusBtn.dataset.field = rowConfig.paidField;
            plusBtn.dataset.delta = "1";
            plusBtn.innerHTML = `<i class="fa-solid fa-plus"></i>`;
            plusBtn.disabled = character.type === "player" && remainingExperience < CHARACTER_HEALTH_STEP_COST;
            paidControls.appendChild(plusBtn);

            controls.appendChild(paidControls);

            if (canEditFreeHealth) {
                const freeControls = document.createElement("div");
                freeControls.className = "d-flex align-items-center gap-2";

                const freeLabel = document.createElement("span");
                freeLabel.className = "small text-nowrap";
                freeLabel.textContent = "gratis speciale aanpassing";
                freeControls.appendChild(freeLabel);

                const freeButtons = document.createElement("div");
                freeButtons.className = "btn-group btn-group-sm";

                const freeMinusBtn = document.createElement("button");
                freeMinusBtn.type = "button";
                freeMinusBtn.className = "btn btn-outline-secondary";
                freeMinusBtn.dataset.action = "character-health-adjust";
                freeMinusBtn.dataset.field = rowConfig.freeField;
                freeMinusBtn.dataset.delta = "-1";
                freeMinusBtn.innerHTML = `<i class="fa-solid fa-minus"></i>`;
                freeMinusBtn.disabled = totalValue <= 1;
                freeButtons.appendChild(freeMinusBtn);

                const freePlusBtn = document.createElement("button");
                freePlusBtn.type = "button";
                freePlusBtn.className = "btn btn-outline-secondary";
                freePlusBtn.dataset.action = "character-health-adjust";
                freePlusBtn.dataset.field = rowConfig.freeField;
                freePlusBtn.dataset.delta = "1";
                freePlusBtn.innerHTML = `<i class="fa-solid fa-plus"></i>`;
                freeButtons.appendChild(freePlusBtn);

                freeControls.appendChild(freeButtons);
                controls.appendChild(freeControls);
            }

            row.appendChild(controls);
        }

        container.appendChild(row);
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

        if (currentCharacter) {
            currentCharacter[field] = value;
        }

        if (field === "class") {
            getCharacter(idCharacter);
        }
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
        <div class="mb-3 row" id="classRow">
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
        <div class="mb-3 row" id="birthRow">
            <div class="col-sm-4">Geboren te / op</div>
            <div class="col-sm-6">${birthLine}</div>
        </div>
        ${(character.class === "upper class" || character.class === "middle class" || character.class === "lower class") ? `
        <div id="leftTraitModuleHost"></div>
        ` : ""}
        <div class="mb-3 row">
            <div class="col-sm-4">Rijksregisternummer</div>
            <div class="col-sm-6">${character.stateRegisterNumber || ""}</div>
        </div>
        <div id="leftInfoSections">
            <h4>Gezondheid</h4>
            <div class="mb-4" id="healthSectionContent"></div>
            <h4>Rijkdom</h4>
            <div class="mb-4" id="wealthSectionContent"></div>
            <h4>Personeel</h4>
            <div class="mb-4" id="staffSectionContent"></div>
            <h4>Talen</h4>
            <div class="mb-4" id="languagesSectionContent"></div>
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
        "maritalStatus"
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

async function updateCharacterHealthField(field, delta) {
    if (!currentCharacter?.id || !field) return;

    const currentValue = getCharacterIntValue(currentCharacter, field);
    const nextValue = currentValue + Number(delta || 0);
    const isPhysicalField = field === "physicalHealth" || field === "physicalHealthFree";
    const paidField = isPhysicalField ? "physicalHealth" : "mentalHealth";
    const freeField = isPhysicalField ? "physicalHealthFree" : "mentalHealthFree";
    const nextPaidValue = field === paidField
        ? nextValue
        : getCharacterIntValue(currentCharacter, paidField);
    const nextFreeValue = field === freeField
        ? nextValue
        : getCharacterIntValue(currentCharacter, freeField);
    const nextTotalValue = CHARACTER_BASE_HEALTH + nextPaidValue + nextFreeValue;

    if ((field === "physicalHealth" || field === "mentalHealth") && nextValue < -3) {
        return;
    }

    if (nextTotalValue < 1) {
        return;
    }

    try {
        await updateCharacter({
            id: currentCharacter.id,
            [field]: nextValue
        });

        const openCollapseId = typeof getOpenSkillCollapseId === "function"
            ? getOpenSkillCollapseId()
            : null;
        await getCharacter(currentCharacter.id, openCollapseId);
    } catch (err) {
        console.error("Fout bij aanpassen van gezondheid:", err);
    }
}

function setupHealthSectionListeners() {
    if (window.aetherHealthListenersInitialized) return;
    window.aetherHealthListenersInitialized = true;

    document.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-action='character-health-adjust']");
        if (!btn || btn.disabled || btn.classList.contains("disabled")) return;

        const field = btn.dataset.field || "";
        const delta = Number(btn.dataset.delta || 0);
        if (!field || !delta) return;

        await updateCharacterHealthField(field, delta);
    });
}

setupHealthSectionListeners();
