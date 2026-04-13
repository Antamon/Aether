// Init na load
window.addEventListener("load", () => {
    initCharacterPage();
    setupSkillListeners();
    setupTraitListeners();
});

let characterSidebarItems = [];
let characterSidebarFiltersBound = false;

function setupCharacterPortraitControls() {
    const uploadButton = document.getElementById("characterPortraitUploadButton");
    const deleteButton = document.getElementById("characterPortraitDeleteButton");
    const fileInput = document.getElementById("characterPortraitFileInput");

    if (uploadButton && !uploadButton.dataset.boundPortraitUpload) {
        uploadButton.addEventListener("click", () => {
            if (fileInput && !fileInput.disabled) {
                fileInput.click();
            }
        });
        uploadButton.dataset.boundPortraitUpload = "true";
    }

    if (fileInput && !fileInput.dataset.boundPortraitChange) {
        fileInput.addEventListener("change", async (event) => {
            const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
            if (!file) {
                return;
            }

            try {
                await uploadCharacterPortrait(file);
            } finally {
                fileInput.value = "";
            }
        });
        fileInput.dataset.boundPortraitChange = "true";
    }

    if (deleteButton && !deleteButton.dataset.boundPortraitDelete) {
        deleteButton.addEventListener("click", async () => {
            await deleteCharacterPortrait();
        });
        deleteButton.dataset.boundPortraitDelete = "true";
    }
}

function updateCharacterPortrait(portraitUrl, hasPortrait) {
    const portraitImage = document.getElementById("characterPortraitImage");
    const portraitPlaceholder = document.getElementById("characterPortraitPlaceholder");

    if (!portraitImage || !portraitPlaceholder) return;

    if (portraitUrl) {
        portraitImage.src = portraitUrl;
        portraitImage.classList.remove("d-none");
        portraitPlaceholder.classList.add("d-none");
        return;
    }

    portraitImage.src = "";
    portraitImage.classList.add("d-none");
    portraitPlaceholder.classList.remove("d-none");
    portraitPlaceholder.textContent = hasPortrait ? "Portret laden..." : "Geen portret";
}

function hasCharacterPortrait() {
    const portraitImage = document.getElementById("characterPortraitImage");
    return Boolean(portraitImage && !portraitImage.classList.contains("d-none") && portraitImage.src);
}

function updateCharacterPortraitActions(canManage, hasPortrait) {
    const uploadButton = document.getElementById("characterPortraitUploadButton");
    const deleteButton = document.getElementById("characterPortraitDeleteButton");
    const fileInput = document.getElementById("characterPortraitFileInput");

    if (uploadButton) {
        uploadButton.disabled = !canManage;
    }

    if (deleteButton) {
        deleteButton.disabled = !canManage || !hasPortrait;
    }

    if (fileInput) {
        fileInput.disabled = !canManage;
    }
}

function applyCharacterPortraitState(character) {
    setupCharacterPortraitControls();
    updateCharacterPortrait(character?.portraitUrl || null, Boolean(character?.portraitUrl));
    updateCharacterPortraitActions(Boolean(character?.canManagePortrait), Boolean(character?.portraitUrl));
}

async function uploadCharacterPortrait(file) {
    const id = Number(document.getElementById("idCharacter")?.value || currentCharacter?.id || 0);
    if (id <= 0) {
        alert("Maak eerst het personage aan voor je een portret oplaadt.");
        return;
    }

    const formData = new FormData();
    formData.append("id", String(id));
    formData.append("portrait", file);

    const portraitImage = document.getElementById("characterPortraitImage");
    const previousPortraitUrl = portraitImage && !portraitImage.classList.contains("d-none") ? portraitImage.src : null;
    const previousHasPortrait = Boolean(previousPortraitUrl);

    if (!previousHasPortrait) {
        updateCharacterPortrait(null, true);
    }

    updateCharacterPortraitActions(false, previousHasPortrait);

    try {
        const response = await fetch("api/characters/uploadCharacterPortrait.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) {
            let text = "";
            try {
                text = await response.text();
            } catch (error) {
                text = "(geen body)";
            }
            throw new Error(`API-fout ${response.status}: ${text}`);
        }

        const result = await response.json();
        if (currentCharacter) {
            currentCharacter.portraitUrl = result?.portraitUrl || null;
        }
        updateCharacterPortrait(result?.portraitUrl || null, Boolean(result?.portraitUrl));
        updateCharacterPortraitActions(Boolean(currentCharacter?.canManagePortrait), Boolean(result?.portraitUrl));
    } catch (err) {
        console.error("Fout bij opladen portret:", err);
        updateCharacterPortrait(previousPortraitUrl, previousHasPortrait);
        updateCharacterPortraitActions(Boolean(currentCharacter?.canManagePortrait), previousHasPortrait);
        alert("Kon het portret niet opladen.");
    }
}

async function deleteCharacterPortrait() {
    const id = Number(document.getElementById("idCharacter")?.value || currentCharacter?.id || 0);
    if (id <= 0) {
        alert("Kies eerst een personage.");
        return;
    }

    updateCharacterPortraitActions(false, hasCharacterPortrait());

    try {
        await apiFetchJson("api/characters/deleteCharacterPortrait.php", {
            method: "POST",
            body: { id }
        });

        if (currentCharacter) {
            currentCharacter.portraitUrl = null;
        }
        updateCharacterPortrait(null, false);
        updateCharacterPortraitActions(Boolean(currentCharacter?.canManagePortrait), false);
    } catch (err) {
        console.error("Fout bij verwijderen portret:", err);
        updateCharacterPortraitActions(Boolean(currentCharacter?.canManagePortrait), hasCharacterPortrait());
        alert("Kon het portret niet verwijderen.");
    }
}

async function initCharacterPage() {
    try {
        // Huidige gebruiker ophalen (gebruik globale indien al gezet)
        currentUser = window.AETHER_CURRENT_USER || await fetchCurrentUser();
        window.AETHER_CURRENT_USER = currentUser;

        setupCharacterSidebarFilters();

        // Lijst met personages
        await characterList();

        // Eventlisteners & autosave instellen
        setupCharacterFormListeners();

        // Oorspronkelijke markup van het karakterformulier bewaren
        const formContainer = document.getElementById("characterForm");
        if (formContainer && originalCharacterFormHtml === null) {
            originalCharacterFormHtml = formContainer.innerHTML;
        }

        const skillsContainer = document.getElementById("skills");
        if (skillsContainer && getOriginalSkillsHtml() === null) {
            setOriginalSkillsHtml(skillsContainer.innerHTML);
        }

        setupCharacterPortraitControls();

    } catch (err) {
        console.error("Fout bij initialiseren personagepagina:", err);
    }
}
// -----------------------
//  Character ophalen
// -----------------------

async function getCharacter(id, openCollapseId = null, activeTab = "sheet") {
    try {
        const data = await fetchCharacter(id);

        if (!data) return;

        // Bewaar globally zodat updateSkillLevel types/levels kent
        currentCharacter = data;

        // Debug
        console.log("Character from API:", data);
        console.log("Skills from API:", data.skills);

        // Gebruikte XP Ã©Ã©n keer berekenen
        const usedExperience = calculateExperience(data);

        // Navigatie bovenaan
        pageNav(currentUser.role, data, activeTab);

        // Bepalen of dit personage bewerkbaar is voor de huidige user
        const role = currentUser?.role || "";
        const isAdmin = role === "administrator" || role === "director";

        const isOwnerPlayer =
            !isAdmin &&
            data.type === "player" &&
            Number(data.idUser) === Number(currentUser.id);

        const canEdit = isAdmin || isOwnerPlayer;
        const canEditTraits = typeof canEditTraitsForCharacter === "function"
            ? canEditTraitsForCharacter(data)
            : false;

        console.log("currentUser:", currentUser);
        console.log("character type:", data.type, "idUser:", data.idUser);
        console.log("isAdmin:", isAdmin, "isOwnerPlayer:", isOwnerPlayer, "canEdit:", canEdit);

        // Algemene zichtbaarheid
        const grpNew = document.getElementById("groupNewCharacter");
        if (grpNew) grpNew.classList.add("d-none");
        const formEl = document.getElementById("characterForm");
        if (formEl) formEl.classList.remove("d-none");
        const skillsEl = document.getElementById("skills");
        if (skillsEl) skillsEl.classList.remove("d-none");
        const idInput = document.getElementById("idCharacter");
        if (idInput) idInput.value = data["id"];
        applyCharacterPortraitState(data);

        const formContainer = document.getElementById("characterForm");
        let accordionSkills = document.getElementById("accordionSkills");
        let traitsContent = document.getElementById("traitsContent");

        // --- EDITABLE MODE (admin of owner-player) ---
        if (canEdit) {
            // Originele form-markup terugzetten (indien overschreven)
            if (originalCharacterFormHtml !== null) {
                formContainer.innerHTML = originalCharacterFormHtml;
                // Events opnieuw koppelen aan inputs
                setupCharacterFormListeners();
                setupCharacterPortraitControls();
            }

            if (skillsEl && getOriginalSkillsHtml() !== null) {
                skillsEl.innerHTML = getOriginalSkillsHtml();
            }

            applyCharacterPortraitState(data);

            accordionSkills = document.getElementById("accordionSkills");
            traitsContent = document.getElementById("traitsContent");

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
                "maritalStatus"
            ];

            velden.forEach((veld) => {
                const el = document.getElementById(veld);
                if (el) {
                    el.value = data[veld] ?? "";
                }
            });

            syncClassFieldPresentation(data);

            // Skills: normale accordion met knoppen
            if (accordionSkills) {
                accordionSkills.innerHTML = "";
                (data["skills"] || []).forEach((skill) => {
                    accordionSkills.appendChild(
                        addSkillAccordionItem(skill, usedExperience, data)
                    );
                });
            }

            const mainTraitGroups = getMainTraitGroups(data, data.traitGroups || []);

            renderLeftTraitModule(data, canEditTraits);
            renderTraitGroups(traitsContent, mainTraitGroups, canEditTraits);
            renderCharacterHealthSection(data);
            await renderCharacterLivingStandardSection(data);
            await renderCharacterStaffSection(data);
            renderCharacterLanguagesSection(data);
            renderCharacterEconomyTab(data);

            // Nieuwe skills mogen toegevoegd worden
            const selNew = document.getElementById("idNewSkill");
            const btnAdd = document.getElementById("addNewSkill");
            const skillAddGroup = selNew?.closest(".input-group");
            if (skillAddGroup) skillAddGroup.classList.remove("d-none");
            if (selNew) selNew.disabled = false;
            if (btnAdd) btnAdd.disabled = false;

            // Eventuele extra readOnly-logica
            if (typeof applyCharacterEditability === "function") {
                applyCharacterEditability(data, true);
            }

        // --- READ-ONLY MODE (participant die naar extra / andere speler kijkt) ---
        } else {
            if (skillsEl && getOriginalSkillsHtml() !== null) {
                skillsEl.innerHTML = getOriginalSkillsHtml();
            }

            accordionSkills = document.getElementById("accordionSkills");
            traitsContent = document.getElementById("traitsContent");

            // Karaktergegevens in tekst-layout weergeven
            renderCharacterDetailsReadOnly(data);
            applyCharacterPortraitState(data);

            // Skills als leesbare lijst
            if (accordionSkills) {
            renderSkillsReadOnly(accordionSkills, data.skills || []);
            }

            const mainTraitGroups = getMainTraitGroups(data, data.traitGroups || []);

            renderLeftTraitModule(data, canEditTraits);
            renderTraitGroups(traitsContent, mainTraitGroups, canEditTraits);
            renderCharacterHealthSection(data);
            await renderCharacterLivingStandardSection(data);
            await renderCharacterStaffSection(data);
            renderCharacterLanguagesSection(data);
            renderCharacterEconomyTab(data);

            // Nieuwe skills niet toe te voegen
            const selNew = document.getElementById("idNewSkill");
            const btnAdd = document.getElementById("addNewSkill");
            const skillAddGroup = selNew?.closest(".input-group");
            if (skillAddGroup) skillAddGroup.classList.add("d-none");
            if (selNew) selNew.disabled = true;
            if (btnAdd) {
                btnAdd.disabled = true;
                btnAdd.classList.add("disabled");
            }
        }

        // Nieuwe skillslijst ophalen (voor editable case; voor read-only geeft visibility toch al filter)
        getNewSkills(data["id"]);

        initTooltips();
        showCharacterTab(activeTab, data);

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

// -----------------------
//  Lijst van personages
// -----------------------

function setupCharacterSidebarFilters() {
    if (characterSidebarFiltersBound) {
        return;
    }

    const filterIds = [
        "sidebarCharacterTypeFilter",
        "sidebarCharacterStatusFilter",
        "sidebarCharacterClassFilter"
    ];

    filterIds.forEach((id) => {
        const element = document.getElementById(id);
        if (!element) {
            return;
        }

        element.addEventListener("change", () => {
            renderCharacterSidebarList();
        });
    });

    characterSidebarFiltersBound = true;
}

function getCharacterSidebarFilters() {
    return {
        type: document.getElementById("sidebarCharacterTypeFilter")?.value || "",
        state: document.getElementById("sidebarCharacterStatusFilter")?.value || "",
        className: document.getElementById("sidebarCharacterClassFilter")?.value || ""
    };
}

function getCharacterSidebarDisplayName(character) {
    return [character?.firstName, character?.lastName]
        .map((value) => (value || "").trim())
        .filter(Boolean)
        .join(" ")
        .trim();
}

function canDeleteCharacterFromSidebar(character) {
    if (!currentUser || !character) {
        return false;
    }

    const role = currentUser.role || "";
    if (role === "administrator" || role === "director") {
        return true;
    }

    return role === "participant"
        && character.type === "player"
        && Number(character.idUser) === Number(currentUser.id);
}

function filterCharacterSidebarItems(characters) {
    const filters = getCharacterSidebarFilters();

    return characters.filter((character) => {
        if (filters.type && character.type !== filters.type) {
            return false;
        }

        if (filters.state && character.state !== filters.state) {
            return false;
        }

        if (filters.className && character.class !== filters.className) {
            return false;
        }

        return true;
    });
}

function clearCharacterWorkspace() {
    currentCharacter = null;

    const idInput = document.getElementById("idCharacter");
    if (idInput) {
        idInput.value = "";
    }

    const pageNav = document.getElementById("pageNav");
    if (pageNav) {
        pageNav.classList.add("d-none");
        pageNav.innerHTML = "";
    }

    const characterForm = document.getElementById("characterForm");
    if (characterForm) {
        characterForm.classList.add("d-none");
    }

    const skills = document.getElementById("skills");
    if (skills) {
        skills.classList.add("d-none");
    }
}

function closeCharacterSidebarIfOpen() {
    if (typeof closeOffcanvasIfOpen === "function") {
        closeOffcanvasIfOpen();
        return;
    }

    const offcanvasEl = document.getElementById("offcanvasScrolling");
    if (!offcanvasEl || typeof bootstrap === "undefined") {
        return;
    }

    const instance = bootstrap.Offcanvas.getInstance(offcanvasEl);
    if (instance) {
        instance.hide();
    }
}

async function handleSidebarCharacterDelete(idCharacter) {
    const character = characterSidebarItems.find((item) => Number(item.id) === Number(idCharacter));
    const displayName = getCharacterSidebarDisplayName(character) || "dit personage";
    const confirmed = window.confirm(`Weet u zeker dat u ${displayName} definitief wilt verwijderen?`);

    if (!confirmed) {
        return;
    }

    try {
        await deleteCharacterById(idCharacter);

        if (Number(currentCharacter?.id) === Number(idCharacter)) {
            clearCharacterWorkspace();
        }

        await characterList();
    } catch (err) {
        console.error("Fout bij verwijderen personage:", err);
        alert(err?.message || "Het verwijderen van het personage is mislukt.");
    }
}

function renderCharacterSidebarList() {
    const nameList = document.getElementById("characterNames");
    if (!nameList) {
        return;
    }

    nameList.innerHTML = "";

    const filteredCharacters = filterCharacterSidebarItems(characterSidebarItems);

    if (filteredCharacters.length < 1) {
        const emptyState = document.createElement("div");
        emptyState.className = "text-muted small py-2";
        emptyState.textContent = "Geen personages gevonden voor deze filters.";
        nameList.appendChild(emptyState);
        return;
    }

    filteredCharacters.forEach((character) => {
        const row = document.createElement("div");
        row.className = "character-sidebar-row";

        const selectButton = document.createElement("button");
        selectButton.type = "button";
        selectButton.className = "btn btn-outline-primary character-sidebar-open";
        selectButton.dataset.idcharacter = character.id;
        selectButton.textContent = getCharacterSidebarDisplayName(character) || `Personage #${character.id}`;
        selectButton.addEventListener("click", async () => {
            const characterForm = document.getElementById("characterForm");
            const skills = document.getElementById("skills");
            if (characterForm) characterForm.classList.remove("d-none");
            if (skills) skills.classList.remove("d-none");
            closeCharacterSidebarIfOpen();
            await getCharacter(character.id);
        });

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "btn btn-outline-danger btn-sm character-sidebar-delete";
        deleteButton.dataset.idcharacter = character.id;
        deleteButton.setAttribute("aria-label", `Verwijder ${selectButton.textContent}`);
        deleteButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
        deleteButton.addEventListener("click", (event) => {
            event.preventDefault();
            handleSidebarCharacterDelete(character.id);
        });

        row.appendChild(selectButton);
        if (canDeleteCharacterFromSidebar(character)) {
            row.appendChild(deleteButton);
        }
        nameList.appendChild(row);
    });
}

async function characterList() {
    try {
        const data = await fetchCharacterList(currentUser["role"]);

        if (!data) return;

        characterSidebarItems = Object.values(data);
        renderCharacterSidebarList();
    } catch (err) {
        console.error("Fout bij ophalen personagelijst:", err);
    }
}











