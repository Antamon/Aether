let adminCurrentUser = null;
let adminSkillList = [];
let adminSkillTypeOptions = [];
let currentAdminSkill = null;
let currentAdminTab = "skills";
let isCreatingAdminSkill = false;
let isPopulatingAdminSkillForm = false;
let adminSkillAutoSavePending = false;
let adminSkillAutoSaveTimer = null;
let saveAdminSkillRequestInFlight = false;
let saveAdminSkillQueued = false;
let saveAdminSkillInFlightPromise = null;
let createAdminSkillPromise = null;
let adminSkillClientKeyCounter = 0;
let saveAdminSkillTypeRequestInFlight = false;

window.addEventListener("load", () => {
    initAdminPage();
});

async function initAdminPage() {
    try {
        adminCurrentUser = window.AETHER_CURRENT_USER || await apiFetchJson("getCurrentUser.php");
        if (!adminCurrentUser || !userHasPrivilegedRole(adminCurrentUser)) {
            window.location.href = "index.html";
            return;
        }

        window.AETHER_CURRENT_USER = adminCurrentUser;
        syncPrivilegedNavbar(adminCurrentUser);
        syncAdminNavbarName(adminCurrentUser);
        setupAdminPageListeners();
        setActiveAdminTab("skills");

        const catalog = await apiFetchJson("api/admin/getSkillList.php");
        applyAdminSkillCatalog(catalog);

        if (adminSkillList.length > 0) {
            await loadAdminSkill(adminSkillList[0].idSkill);
        } else {
            populateAdminSkillForm(null);
        }
    } catch (err) {
        console.error("Fout bij initialiseren adminpagina:", err);
        window.location.href = "index.html";
    }
}

function syncAdminNavbarName(user) {
    const navbarName = document.getElementById("navbarName");
    if (!navbarName || navbarName.textContent.trim() !== "") {
        return;
    }

    const parts = [user?.firstName, user?.lastName]
        .map((value) => String(value || "").trim())
        .filter(Boolean);
    if (parts.length > 0) {
        navbarName.textContent = "Welkom " + parts.join(" ");
    }
}

function adminUserCanManageSkillTypes() {
    return String(adminCurrentUser?.role || "") === "administrator";
}

function setupAdminPageListeners() {
    document.querySelectorAll("#adminMainTabs [data-admin-tab]").forEach((tab) => {
        tab.addEventListener("click", async (event) => {
            event.preventDefault();

            const nextTab = tab.dataset.adminTab || "skills";
            if (nextTab === currentAdminTab) {
                return;
            }

            if (currentAdminTab === "skills") {
                const canContinue = await flushAdminSkillAutoSave({ force: false });
                if (canContinue === false) {
                    return;
                }
            }

            setActiveAdminTab(nextTab);
        });
    });

    const adminSkillSelect = document.getElementById("adminSkillSelect");
    if (adminSkillSelect) {
        adminSkillSelect.addEventListener("change", async () => {
            const selectedValue = adminSkillSelect.value;
            if (selectedValue === "__draft__") {
                syncAdminSkillSelect();
                return;
            }

            const nextSkillId = Number(selectedValue || 0);
            const currentSkillId = Number(currentAdminSkill?.idSkill || 0);
            if (nextSkillId === currentSkillId && !isCreatingAdminSkill) {
                return;
            }

            const canContinue = await flushAdminSkillAutoSave({ force: false });
            if (canContinue === false) {
                syncAdminSkillSelect();
                return;
            }

            if (nextSkillId <= 0) {
                populateAdminSkillForm(null);
                return;
            }

            isCreatingAdminSkill = false;
            await loadAdminSkill(nextSkillId);
        });
    }

    const newSkillButton = document.getElementById("adminNewSkillButton");
    if (newSkillButton) {
        newSkillButton.addEventListener("click", async () => {
            const canContinue = await flushAdminSkillAutoSave({ force: false });
            if (canContinue === false) {
                return;
            }

            startCreatingAdminSkill();
        });
    }

    const form = document.getElementById("adminSkillForm");
    if (form) {
        form.addEventListener("submit", (event) => {
            event.preventDefault();
        });

        form.addEventListener("focusout", () => {
            void flushAdminSkillAutoSave({ force: false });
        });
    }

    bindAdminTextField("adminSkillName", "name", { createOnEnter: true });
    bindAdminTextField("adminSkillDescription", "description");
    bindAdminTextField("adminSkillBeginner", "beginner");
    bindAdminTextField("adminSkillProfessional", "professional");
    bindAdminTextField("adminSkillMaster", "master");

    const categoriesContainer = document.getElementById("adminSkillCategories");
    if (categoriesContainer) {
        categoriesContainer.addEventListener("change", (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || input.type !== "checkbox") {
                return;
            }

            if (!currentAdminSkill) {
                return;
            }

            if (input.dataset.adminSecretToggle === "true") {
                currentAdminSkill.isSecret = Boolean(input.checked);
                markAdminSkillAutoSavePending();
                return;
            }

            const categoryId = Number(input.value || 0);
            if (categoryId > 0) {
                const currentCategoryIds = Array.isArray(currentAdminSkill.categoryIds)
                    ? currentAdminSkill.categoryIds.slice()
                    : [];

                if (input.checked) {
                    if (!currentCategoryIds.includes(categoryId)) {
                        currentCategoryIds.push(categoryId);
                    }
                } else {
                    const index = currentCategoryIds.indexOf(categoryId);
                    if (index >= 0) {
                        currentCategoryIds.splice(index, 1);
                    }
                }

                currentAdminSkill.categoryIds = currentCategoryIds;
                renderAdminSpecialisations(currentAdminSkill);
                markAdminSkillAutoSavePending();
            }
        });

        categoriesContainer.addEventListener("input", (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || input.dataset.adminCategoryName !== "true") {
                return;
            }

            const categoryId = Number(input.dataset.idSkillType || 0);
            const category = adminSkillTypeOptions.find((item) => Number(item.idSkillType || 0) === categoryId);
            if (category) {
                category.name = input.value;
            }
        });

        categoriesContainer.addEventListener("focusout", (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || input.dataset.adminCategoryName !== "true") {
                return;
            }

            const categoryId = Number(input.dataset.idSkillType || 0);
            void saveAdminSkillType("update", {
                idSkillType: categoryId,
                name: input.value,
            });
        });

        categoriesContainer.addEventListener("keydown", (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || input.dataset.adminCategoryName !== "true") {
                return;
            }

            if (event.key !== "Enter") {
                return;
            }

            event.preventDefault();
            const categoryId = Number(input.dataset.idSkillType || 0);
            void saveAdminSkillType("update", {
                idSkillType: categoryId,
                name: input.value,
            });
        });

        categoriesContainer.addEventListener("click", (event) => {
            const button = event.target instanceof HTMLElement
                ? event.target.closest("button[data-admin-category-delete]")
                : null;
            if (!button) {
                return;
            }

            const idSkillType = Number(button.dataset.adminCategoryDelete || 0);
            if (idSkillType <= 0) {
                return;
            }

            const category = adminSkillTypeOptions.find((item) => Number(item.idSkillType || 0) === idSkillType);
            const confirmed = window.confirm(`Weet u zeker dat u de categorie "${category?.name || "deze categorie"}" wilt verwijderen?`);
            if (!confirmed) {
                return;
            }

            void saveAdminSkillType("delete", { idSkillType });
        });
    }

    const addCategoryButton = document.getElementById("adminAddCategoryButton");
    if (addCategoryButton) {
        addCategoryButton.addEventListener("click", async () => {
            const name = window.prompt("Naam van de nieuwe categorie:");
            if (name === null) {
                return;
            }

            await saveAdminSkillType("create", { name });
        });
    }

    const addSpecialisationButton = document.getElementById("adminAddSpecialisationButton");
    if (addSpecialisationButton) {
        addSpecialisationButton.addEventListener("click", () => {
            if (Number(currentAdminSkill?.idSkill || 0) <= 0) {
                showAdminFeedback("Geef eerst een naam op zodat de vaardigheid kan worden aangemaakt.", "danger");
                return;
            }

            currentAdminSkill.specialisations = Array.isArray(currentAdminSkill.specialisations)
                ? currentAdminSkill.specialisations
                : [];

            currentAdminSkill.specialisations.push({
                idSkillSpecialisation: 0,
                name: "",
                kind: getDefaultAdminSpecialisationKind(currentAdminSkill),
                clientKey: createAdminSpecialisationClientKey(),
            });

            renderAdminSpecialisations(currentAdminSkill);

            const rows = document.querySelectorAll(".admin-specialisation-row input");
            const lastInput = rows.length > 0 ? rows[rows.length - 1] : null;
            lastInput?.focus();
        });
    }

    const specialisationsContainer = document.getElementById("adminSkillSpecialisations");
    if (specialisationsContainer) {
        specialisationsContainer.addEventListener("input", (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || !input.matches("[data-admin-spec-key]")) {
                return;
            }

            const spec = findAdminSpecialisationByKey(input.dataset.adminSpecKey || "");
            if (!spec) {
                return;
            }

            spec.name = input.value;
            markAdminSkillAutoSavePending();
        });

        specialisationsContainer.addEventListener("click", (event) => {
            const button = event.target instanceof HTMLElement
                ? event.target.closest("button[data-admin-spec-remove]")
                : null;
            if (!button || !currentAdminSkill) {
                return;
            }

            const specKey = button.dataset.adminSpecRemove || "";
            currentAdminSkill.specialisations = (currentAdminSkill.specialisations || []).filter(
                (specialisation) => String(specialisation.clientKey || "") !== specKey
            );

            renderAdminSpecialisations(currentAdminSkill);
            markAdminSkillAutoSavePending();
            void flushAdminSkillAutoSave({ force: false });
        });
    }
}

function bindAdminTextField(fieldId, propertyName, options = {}) {
    const input = document.getElementById(fieldId);
    if (!input) {
        return;
    }

    input.addEventListener("input", () => {
        if (!currentAdminSkill) {
            return;
        }

        currentAdminSkill[propertyName] = input.value;
        markAdminSkillAutoSavePending();
    });

    if (options.createOnEnter) {
        input.addEventListener("keydown", (event) => {
            if (event.key !== "Enter") {
                return;
            }

            if (Number(currentAdminSkill?.idSkill || 0) > 0) {
                return;
            }

            event.preventDefault();
            void ensureAdminSkillCreated(true);
        });
    }
}

function setActiveAdminTab(tabName) {
    currentAdminTab = tabName;

    document.querySelectorAll("#adminMainTabs [data-admin-tab]").forEach((tab) => {
        tab.classList.toggle("active", (tab.dataset.adminTab || "") === tabName);
    });

    const toolbar = document.getElementById("adminSkillsToolbar");
    toolbar?.classList.toggle("d-none", tabName !== "skills");

    document.getElementById("adminSkillsSection")?.classList.toggle("d-none", tabName !== "skills");
    document.getElementById("adminTraitsSection")?.classList.toggle("d-none", tabName !== "traits");
    document.getElementById("adminKnowledgeSection")?.classList.toggle("d-none", tabName !== "knowledge");
}

function applyAdminSkillCatalog(catalog) {
    adminSkillList = Array.isArray(catalog?.skills) ? catalog.skills : [];
    if (Array.isArray(catalog?.skillTypes)) {
        adminSkillTypeOptions = catalog.skillTypes;
    }
    syncAdminSkillSelect();
}

function syncAdminSkillSelect() {
    const select = document.getElementById("adminSkillSelect");
    if (!select) {
        return;
    }

    const currentValue = isCreatingAdminSkill && Number(currentAdminSkill?.idSkill || 0) <= 0
        ? "__draft__"
        : String(Number(currentAdminSkill?.idSkill || 0) || 0);

    select.innerHTML = "";

    const placeholder = document.createElement("option");
    placeholder.value = "0";
    placeholder.textContent = "Kies een vaardigheid";
    select.appendChild(placeholder);

    adminSkillList.forEach((skill) => {
        const option = document.createElement("option");
        option.value = String(skill.idSkill || 0);
        option.textContent = skill.name || `Vaardigheid #${skill.idSkill || 0}`;
        select.appendChild(option);
    });

    if (isCreatingAdminSkill && Number(currentAdminSkill?.idSkill || 0) <= 0) {
        const draftOption = document.createElement("option");
        draftOption.value = "__draft__";
        draftOption.textContent = "Nieuwe vaardigheid (nog niet opgeslagen)";
        select.appendChild(draftOption);
    }

    if ([...select.options].some((option) => option.value === currentValue)) {
        select.value = currentValue;
    } else {
        select.value = "0";
    }
}

async function loadAdminSkill(idSkill) {
    if (Number(idSkill) <= 0) {
        populateAdminSkillForm(null);
        return;
    }

    try {
        const skill = await apiFetchJson("api/admin/getSkill.php", {
            method: "POST",
            body: { idSkill: Number(idSkill) }
        });

        isCreatingAdminSkill = false;
        hideAdminFeedback();
        populateAdminSkillForm(skill);
    } catch (err) {
        console.error("Fout bij laden vaardigheid:", err);
        showAdminFeedback("Kon de geselecteerde vaardigheid niet laden.", "danger");
    }
}

function startCreatingAdminSkill() {
    isCreatingAdminSkill = true;
    populateAdminSkillForm({
        idSkill: 0,
        name: "",
        description: "",
        beginner: "",
        professional: "",
        master: "",
        isSecret: false,
        categoryIds: [],
        categories: [],
        specialisations: [],
        holders: [],
        isDraft: true,
    });

    const nameInput = document.getElementById("adminSkillName");
    nameInput?.focus();
}

function populateAdminSkillForm(skill) {
    isPopulatingAdminSkillForm = true;
    clearAdminSkillAutoSaveTimer();
    adminSkillAutoSavePending = false;

    currentAdminSkill = skill ? normalizeAdminSkillState(skill) : null;

    const title = document.getElementById("adminSkillTitle");
    const subtitle = document.getElementById("adminSkillSubtitle");
    const idInput = document.getElementById("adminSkillId");
    const nameInput = document.getElementById("adminSkillName");
    const descriptionInput = document.getElementById("adminSkillDescription");
    const beginnerInput = document.getElementById("adminSkillBeginner");
    const professionalInput = document.getElementById("adminSkillProfessional");
    const masterInput = document.getElementById("adminSkillMaster");
    const categoryHelp = document.getElementById("adminCategoryHelp");
    const addCategoryButton = document.getElementById("adminAddCategoryButton");

    if (categoryHelp) {
        categoryHelp.textContent = adminUserCanManageSkillTypes()
            ? "Selecteer de categorieën van deze vaardigheid. U kunt categorieën hier ook hernoemen, verwijderen en aanmaken."
            : "Selecteer de categorieën van deze vaardigheid.";
    }

    if (addCategoryButton) {
        addCategoryButton.classList.toggle("d-none", !adminUserCanManageSkillTypes());
    }

    if (!currentAdminSkill) {
        if (title) title.textContent = "Vaardigheid";
        if (subtitle) subtitle.textContent = adminSkillList.length > 0
            ? "Kies bovenaan een vaardigheid of maak een nieuwe aan."
            : "Er zijn nog geen vaardigheden. Maak bovenaan een nieuwe vaardigheid aan.";
        if (idInput) idInput.value = "";
        if (nameInput) nameInput.value = "";
        if (descriptionInput) descriptionInput.value = "";
        if (beginnerInput) beginnerInput.value = "";
        if (professionalInput) professionalInput.value = "";
        if (masterInput) masterInput.value = "";
        renderAdminCategories(null);
        renderAdminSpecialisations(null);
        renderAdminHolders(null);
        applyAdminSkillFormEditability(null);
        syncAdminSkillSelect();
        isPopulatingAdminSkillForm = false;
        return;
    }

    if (title) {
        title.textContent = currentAdminSkill.name?.trim() !== ""
            ? currentAdminSkill.name
            : "Nieuwe vaardigheid";
    }

    if (subtitle) {
        subtitle.textContent = Number(currentAdminSkill.idSkill || 0) > 0
            ? "Wijzigingen worden automatisch bewaard zodra u klaar bent met bewerken."
            : "Geef eerst een naam op. Zodra die naam ingevuld is, wordt de vaardigheid aangemaakt en kunt u de overige velden invullen.";
    }

    if (idInput) idInput.value = Number(currentAdminSkill.idSkill || 0) > 0 ? String(currentAdminSkill.idSkill) : "";
    if (nameInput) nameInput.value = currentAdminSkill.name || "";
    if (descriptionInput) descriptionInput.value = currentAdminSkill.description || "";
    if (beginnerInput) beginnerInput.value = currentAdminSkill.beginner || "";
    if (professionalInput) professionalInput.value = currentAdminSkill.professional || "";
    if (masterInput) masterInput.value = currentAdminSkill.master || "";

    renderAdminCategories(currentAdminSkill);
    renderAdminSpecialisations(currentAdminSkill);
    renderAdminHolders(currentAdminSkill);
    applyAdminSkillFormEditability(currentAdminSkill);
    syncAdminSkillSelect();
    isPopulatingAdminSkillForm = false;
}

function normalizeAdminSkillState(skill) {
    return {
        idSkill: Number(skill?.idSkill || 0),
        name: String(skill?.name || ""),
        description: String(skill?.description || ""),
        beginner: String(skill?.beginner || ""),
        professional: String(skill?.professional || ""),
        master: String(skill?.master || ""),
        isSecret: Boolean(skill?.isSecret),
        categoryIds: Array.isArray(skill?.categoryIds)
            ? skill.categoryIds.map((id) => Number(id || 0)).filter((id) => id > 0)
            : [],
        categories: Array.isArray(skill?.categories) ? skill.categories : [],
        specialisations: Array.isArray(skill?.specialisations)
            ? skill.specialisations.map((specialisation) => ({
                idSkillSpecialisation: Number(specialisation?.idSkillSpecialisation || 0),
                name: String(specialisation?.name || ""),
                kind: String(specialisation?.kind || "specialisation"),
                clientKey: createAdminSpecialisationClientKey(),
            }))
            : [],
        holders: Array.isArray(skill?.holders) ? skill.holders : [],
        isDraft: Boolean(skill?.isDraft),
    };
}

function applyAdminSkillFormEditability(skill) {
    const hasSkill = Boolean(skill);
    const isPersistedSkill = Number(skill?.idSkill || 0) > 0;
    const nameInput = document.getElementById("adminSkillName");
    const addSpecialisationButton = document.getElementById("adminAddSpecialisationButton");

    const fieldIds = [
        "adminSkillDescription",
        "adminSkillBeginner",
        "adminSkillProfessional",
        "adminSkillMaster",
    ];

    if (nameInput) {
        nameInput.disabled = !hasSkill;
    }

    fieldIds.forEach((fieldId) => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.disabled = !isPersistedSkill;
        }
    });

    document.querySelectorAll("#adminSkillCategories input[type='checkbox']").forEach((input) => {
        input.disabled = !isPersistedSkill;
    });

    document.querySelectorAll("#adminSkillCategories input[data-admin-category-name='true'], #adminSkillCategories button[data-admin-category-delete]").forEach((element) => {
        element.disabled = !adminUserCanManageSkillTypes();
    });

    const addCategoryButton = document.getElementById("adminAddCategoryButton");
    if (addCategoryButton) {
        addCategoryButton.disabled = !adminUserCanManageSkillTypes();
    }

    document.querySelectorAll("#adminSkillSpecialisations input, #adminSkillSpecialisations button").forEach((element) => {
        element.disabled = !isPersistedSkill;
    });

    if (addSpecialisationButton) {
        addSpecialisationButton.disabled = !isPersistedSkill;
    }
}

function renderAdminCategories(skill) {
    const container = document.getElementById("adminSkillCategories");
    if (!container) {
        return;
    }

    container.innerHTML = "";

    if (!Array.isArray(adminSkillTypeOptions) || adminSkillTypeOptions.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Geen categorieën beschikbaar.";
        container.appendChild(empty);
        return;
    }

    const selectedIds = Array.isArray(skill?.categoryIds) ? skill.categoryIds : [];

    adminSkillTypeOptions.forEach((category) => {
        const label = document.createElement("label");
        label.className = "admin-category-item";
        if (category.description) {
            label.title = category.description;
        }

        const input = document.createElement("input");
        input.type = "checkbox";
        input.className = "form-check-input mt-0";
        input.value = String(category.idSkillType || 0);
        input.checked = selectedIds.includes(Number(category.idSkillType || 0));

        const text = document.createElement("span");
        text.textContent = category.name || `Categorie #${category.idSkillType || 0}`;

        label.appendChild(input);
        label.appendChild(text);
        container.appendChild(label);
    });
}

function renderAdminSpecialisations(skill) {
    const container = document.getElementById("adminSkillSpecialisations");
    if (!container) {
        return;
    }

    container.innerHTML = "";

    if (!skill) {
        const info = document.createElement("p");
        info.className = "text-muted mb-0";
        info.textContent = "Kies een vaardigheid om specialisaties te beheren.";
        container.appendChild(info);
        return;
    }

    if (Number(skill.idSkill || 0) <= 0) {
        const note = document.createElement("p");
        note.className = "admin-form-disabled-note mb-0";
        note.textContent = "Geef eerst een naam op. Daarna kunnen specialisaties toegevoegd en bewerkt worden.";
        container.appendChild(note);
        return;
    }

    if (!Array.isArray(skill.specialisations) || skill.specialisations.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Nog geen specialisaties.";
        container.appendChild(empty);
        return;
    }

    skill.specialisations.forEach((specialisation) => {
        const row = document.createElement("div");
        row.className = "admin-specialisation-row";

        const input = document.createElement("input");
        input.type = "text";
        input.className = "form-control";
        input.value = specialisation.name || "";
        input.placeholder = "Naam van de specialisatie";
        input.dataset.adminSpecKey = String(specialisation.clientKey || "");

        const meta = document.createElement("div");
        meta.className = "admin-specialisation-meta";
        meta.textContent = specialisation.kind === "discipline" ? "Discipline" : "Specialisatie";

        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "btn btn-outline-danger";
        removeButton.dataset.adminSpecRemove = String(specialisation.clientKey || "");
        removeButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
        removeButton.setAttribute("aria-label", "Verwijder specialisatie");

        row.appendChild(input);
        row.appendChild(meta);
        row.appendChild(removeButton);
        container.appendChild(row);
    });
}

function renderAdminHolders(skill) {
    const container = document.getElementById("adminSkillHolders");
    if (!container) {
        return;
    }

    container.innerHTML = "";

    if (!skill || Number(skill.idSkill || 0) <= 0) {
        const info = document.createElement("p");
        info.className = "text-muted mb-0";
        info.textContent = "Kies een vaardigheid om de gekoppelde personages te zien.";
        container.appendChild(info);
        return;
    }

    const groups = Array.isArray(skill.holders) ? skill.holders : [];
    if (groups.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Geen personages gekoppeld aan deze vaardigheid.";
        container.appendChild(empty);
        return;
    }

    groups.forEach((group) => {
        const groupEl = document.createElement("div");
        groupEl.className = "admin-holder-group";

        const title = document.createElement("h3");
        title.className = "admin-holder-group-title";
        title.textContent = group.label || `Niveau ${group.level || ""}`;
        groupEl.appendChild(title);

        const list = document.createElement("ul");
        list.className = "admin-holder-group-list";

        (Array.isArray(group.characters) ? group.characters : []).forEach((character) => {
            const item = document.createElement("li");
            item.className = "admin-holder-item";

            const specs = Array.isArray(character.specialisations)
                ? character.specialisations.filter(Boolean)
                : [];

            const baseName = character.displayName || `Personage #${character.idCharacter || 0}`;
            item.textContent = specs.length > 0
                ? `${baseName} (${specs.join(", ")})`
                : baseName;

            list.appendChild(item);
        });

        groupEl.appendChild(list);
        container.appendChild(groupEl);
    });
}

function createAdminSpecialisationClientKey() {
    adminSkillClientKeyCounter += 1;
    return `spec-${adminSkillClientKeyCounter}`;
}

function findAdminSpecialisationByKey(clientKey) {
    if (!currentAdminSkill || !Array.isArray(currentAdminSkill.specialisations)) {
        return null;
    }

    return currentAdminSkill.specialisations.find(
        (specialisation) => String(specialisation.clientKey || "") === String(clientKey)
    ) || null;
}

function getDefaultAdminSpecialisationKind(skill) {
    const categoryIds = Array.isArray(skill?.categoryIds) ? skill.categoryIds : [];
    return adminSkillTypeOptions.some((category) =>
        categoryIds.includes(Number(category.idSkillType || 0))
        && String(category.code || "").toLowerCase() === "discipline"
    )
        ? "discipline"
        : "specialisation";
}

function clearAdminSkillAutoSaveTimer() {
    if (adminSkillAutoSaveTimer !== null) {
        window.clearTimeout(adminSkillAutoSaveTimer);
        adminSkillAutoSaveTimer = null;
    }
}

function markAdminSkillAutoSavePending() {
    if (isPopulatingAdminSkillForm || !currentAdminSkill) {
        return;
    }

    adminSkillAutoSavePending = true;
    clearAdminSkillAutoSaveTimer();
    adminSkillAutoSaveTimer = window.setTimeout(() => {
        void flushAdminSkillAutoSave({ force: false });
    }, 700);
}

async function ensureAdminSkillCreated(force = false) {
    if (!currentAdminSkill) {
        return false;
    }

    if (Number(currentAdminSkill.idSkill || 0) > 0) {
        return true;
    }

    const name = String(currentAdminSkill.name || "").trim();
    if (name === "") {
        if (force) {
            showAdminFeedback("De naam van de vaardigheid is verplicht.", "danger");
        }
        return !force;
    }

    if (createAdminSkillPromise) {
        return createAdminSkillPromise;
    }

    createAdminSkillPromise = (async () => {
        try {
            const result = await apiFetchJson("api/admin/newSkill.php", {
                method: "POST",
                body: { name }
            });

            if (Array.isArray(result?.skills)) {
                adminSkillList = result.skills;
            }

            isCreatingAdminSkill = false;
            populateAdminSkillForm(result?.skill || null);
            showAdminFeedback("Vaardigheid aangemaakt.", "success");
            return true;
        } catch (err) {
            console.error("Fout bij aanmaken vaardigheid:", err);
            showAdminFeedback("Kon de vaardigheid niet aanmaken.", "danger");
            return false;
        } finally {
            createAdminSkillPromise = null;
        }
    })();

    return createAdminSkillPromise;
}

async function flushAdminSkillAutoSave(options = {}) {
    const { force = false } = options;
    clearAdminSkillAutoSaveTimer();

    if (!currentAdminSkill || isPopulatingAdminSkillForm) {
        return true;
    }

    if (!adminSkillAutoSavePending && !force) {
        return true;
    }

    const hasPersistedSkill = Number(currentAdminSkill.idSkill || 0) > 0;
    if (!hasPersistedSkill) {
        const created = await ensureAdminSkillCreated(force);
        if (created === false) {
            return !force;
        }
    }

    if (Number(currentAdminSkill.idSkill || 0) <= 0) {
        return !force;
    }

    if (!adminSkillAutoSavePending) {
        return true;
    }

    adminSkillAutoSavePending = false;
    return saveAdminSkill({
        successMessage: "Wijzigingen automatisch bewaard.",
        silentWhenUnavailable: true,
    });
}

async function saveAdminSkill(options = {}) {
    if (saveAdminSkillRequestInFlight) {
        saveAdminSkillQueued = true;
        return false;
    }

    const task = (async () => {
        saveAdminSkillRequestInFlight = true;

        try {
            const {
                successMessage = "Vaardigheid opgeslagen.",
                silentWhenUnavailable = false,
            } = options;

            if (!currentAdminSkill) {
                return true;
            }

            let idSkill = Number(currentAdminSkill.idSkill || 0);
            if (idSkill <= 0) {
                const created = await ensureAdminSkillCreated(!silentWhenUnavailable);
                if (!created) {
                    return false;
                }
                idSkill = Number(currentAdminSkill?.idSkill || 0);
            }

            if (idSkill <= 0) {
                if (!silentWhenUnavailable) {
                    showAdminFeedback("Kies eerst een vaardigheid of maak er een nieuwe aan.", "danger");
                }
                return true;
            }

            const name = String(currentAdminSkill.name || "").trim();
            if (name === "") {
                showAdminFeedback("De naam van de vaardigheid is verplicht.", "danger");
                return false;
            }

            const result = await apiFetchJson("api/admin/saveSkill.php", {
                method: "POST",
                body: {
                    idSkill,
                    name,
                    description: currentAdminSkill.description || "",
                    beginner: currentAdminSkill.beginner || "",
                    professional: currentAdminSkill.professional || "",
                    master: currentAdminSkill.master || "",
                    isSecret: Boolean(currentAdminSkill.isSecret),
                    categoryIds: Array.isArray(currentAdminSkill.categoryIds) ? currentAdminSkill.categoryIds : [],
                    specialisations: (Array.isArray(currentAdminSkill.specialisations) ? currentAdminSkill.specialisations : [])
                        .filter((specialisation) => {
                            const nameValue = String(specialisation.name || "").trim();
                            return Number(specialisation.idSkillSpecialisation || 0) > 0 || nameValue !== "";
                        })
                        .map((specialisation) => ({
                            idSkillSpecialisation: Number(specialisation.idSkillSpecialisation || 0),
                            name: String(specialisation.name || "").trim(),
                        })),
                }
            });

            if (Array.isArray(result?.skills)) {
                adminSkillList = result.skills;
            }

            populateAdminSkillForm(result?.skill || null);
            showAdminFeedback(successMessage, "success");
            return true;
        } catch (err) {
            console.error("Fout bij opslaan vaardigheid:", err);
            showAdminFeedback("Kon de vaardigheid niet opslaan.", "danger");
            return false;
        } finally {
            saveAdminSkillRequestInFlight = false;
        }
    })();

    saveAdminSkillInFlightPromise = task;

    try {
        return await task;
    } finally {
        if (saveAdminSkillInFlightPromise === task) {
            saveAdminSkillInFlightPromise = null;
        }

        if (saveAdminSkillQueued) {
            saveAdminSkillQueued = false;
            adminSkillAutoSavePending = true;
            void flushAdminSkillAutoSave({ force: false });
        }
    }
}

function showAdminFeedback(message, type = "success") {
    const feedback = document.getElementById("adminFeedback");
    if (!feedback) {
        return;
    }

    feedback.className = `alert py-2 px-3 mb-4 alert-${type}`;
    feedback.textContent = message;
}

function hideAdminFeedback() {
    const feedback = document.getElementById("adminFeedback");
    if (!feedback) {
        return;
    }

    feedback.className = "alert py-2 px-3 d-none mb-4";
    feedback.textContent = "";
}

function renderAdminCategories(skill) {
    const container = document.getElementById("adminSkillCategories");
    if (!container) {
        return;
    }

    container.innerHTML = "";

    if (!Array.isArray(adminSkillTypeOptions) || adminSkillTypeOptions.length === 0) {
        if (!adminUserCanManageSkillTypes()) {
            const empty = document.createElement("p");
            empty.className = "text-muted mb-0";
            empty.textContent = "Geen categorieën beschikbaar.";
            container.appendChild(empty);
        }
    }

    const selectedIds = Array.isArray(skill?.categoryIds) ? skill.categoryIds : [];
    const canToggleSkill = Number(skill?.idSkill || 0) > 0;
    const canManageTypes = adminUserCanManageSkillTypes();

    adminSkillTypeOptions.forEach((category) => {
        const row = document.createElement("div");
        row.className = "admin-category-item";
        if (category.description) {
            row.title = category.description;
        }

        const input = document.createElement("input");
        input.type = "checkbox";
        input.className = "form-check-input mt-0";
        input.value = String(category.idSkillType || 0);
        input.checked = selectedIds.includes(Number(category.idSkillType || 0));
        input.disabled = !canToggleSkill;

        const toggleWrap = document.createElement("label");
        toggleWrap.className = "admin-category-toggle";
        toggleWrap.appendChild(input);

        if (canManageTypes) {
            const nameInput = document.createElement("input");
            nameInput.type = "text";
            nameInput.className = "form-control form-control-sm admin-category-name-input";
            nameInput.value = category.name || `Categorie #${category.idSkillType || 0}`;
            nameInput.dataset.adminCategoryName = "true";
            nameInput.dataset.idSkillType = String(category.idSkillType || 0);
            toggleWrap.appendChild(nameInput);
        } else {
            const text = document.createElement("span");
            text.className = "admin-category-name";
            text.textContent = category.name || `Categorie #${category.idSkillType || 0}`;
            toggleWrap.appendChild(text);
        }

        row.appendChild(toggleWrap);

        if (canManageTypes) {
            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "btn btn-outline-danger btn-sm";
            deleteButton.dataset.adminCategoryDelete = String(category.idSkillType || 0);
            deleteButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
            deleteButton.setAttribute("aria-label", `Verwijder categorie ${category.name || ""}`);
            row.appendChild(deleteButton);
        }

        container.appendChild(row);
    });

    const secretRow = document.createElement("div");
    secretRow.className = "admin-category-item";

    const secretToggle = document.createElement("label");
    secretToggle.className = "admin-category-toggle";

    const secretInput = document.createElement("input");
    secretInput.type = "checkbox";
    secretInput.className = "form-check-input mt-0";
    secretInput.checked = Boolean(skill?.isSecret);
    secretInput.disabled = !canToggleSkill;
    secretInput.dataset.adminSecretToggle = "true";

    const secretLabel = document.createElement("span");
    secretLabel.className = "admin-category-fixed-label";
    secretLabel.textContent = "Geheime vaardigheid";

    secretToggle.appendChild(secretInput);
    secretToggle.appendChild(secretLabel);
    secretRow.appendChild(secretToggle);
    container.appendChild(secretRow);
}

async function saveAdminSkillType(action, payload = {}) {
    if (!adminUserCanManageSkillTypes()) {
        showAdminFeedback("Alleen administrators kunnen categorieën beheren.", "danger");
        return false;
    }

    if (saveAdminSkillTypeRequestInFlight) {
        return false;
    }

    saveAdminSkillTypeRequestInFlight = true;
    const previousSkillTypeOptions = Array.isArray(adminSkillTypeOptions)
        ? adminSkillTypeOptions.map((category) => ({ ...category }))
        : [];

    try {
        const result = await apiFetchJson("api/admin/saveSkillType.php", {
            method: "POST",
            body: {
                action,
                idSkillType: Number(payload.idSkillType || 0),
                name: String(payload.name || "").trim(),
            }
        });

        adminSkillTypeOptions = Array.isArray(result?.skillTypes) ? result.skillTypes : adminSkillTypeOptions;

        const validCategoryIds = new Set(adminSkillTypeOptions.map((category) => Number(category.idSkillType || 0)));
        if (currentAdminSkill) {
            currentAdminSkill.categoryIds = (Array.isArray(currentAdminSkill.categoryIds) ? currentAdminSkill.categoryIds : [])
                .filter((id) => validCategoryIds.has(Number(id || 0)));
        }

        renderAdminCategories(currentAdminSkill);
        showAdminFeedback(
            action === "create"
                ? "Categorie aangemaakt."
                : (action === "delete" ? "Categorie verwijderd." : "Categorie bijgewerkt."),
            "success"
        );
        return true;
    } catch (err) {
        console.error("Fout bij bewaren categorie:", err);
        adminSkillTypeOptions = previousSkillTypeOptions;
        showAdminFeedback("Kon de categorie niet bewaren.", "danger");
        renderAdminCategories(currentAdminSkill);
        return false;
    } finally {
        saveAdminSkillTypeRequestInFlight = false;
    }
}
