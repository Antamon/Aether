// Init na load
window.addEventListener("load", () => {
    initCharacterPage();
    setupSkillListeners();
    setupTraitListeners();
});

let characterSidebarItems = [];
let characterSidebarFiltersBound = false;
let characterSidebarUserFilterValue = "all";
let characterSidebarUserFilterInitialised = false;

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
            headers: {
                "X-CSRF-Token": window.AETHER_CSRF_TOKEN || ""
            },
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
        // Onafhankelijke lijsten tegelijk laden; beide gebruiken dezelfde sessie.
        await Promise.all([initCharacterSidebarUserFilter(), characterList()]);

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

        const params = new URLSearchParams(window.location.search);
        const selectedId = params.get("character");
        if (selectedId && /^[1-9]\d*$/.test(selectedId)) {
            document.getElementById("characterForm")?.classList.remove("d-none");
            document.getElementById("skills")?.classList.remove("d-none");
            const tab = ["diary", "economy"].includes(params.get("tab")) ? params.get("tab") : "sheet";
            const eventId = params.get("event");
            if (/^[1-9]\d*$/.test(eventId || "") && ((tab === "diary" && params.get("focus") === "gossip") || (tab === "economy" && params.get("focus") === "snapshot"))) {
                window.AETHER_EVENT_TARGET = { character: Number(selectedId), event: Number(eventId), kind: params.get("focus") };
            }
            await getCharacter(Number(selectedId), null, tab);
        } else if (params.get("new") === "1") {
            await aetherStartNewCharacterFlow();
        }

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
        renderCharacterDeleteActions(data);

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
        const formContainer = document.getElementById("characterForm");
        let accordionSkills = document.getElementById("accordionSkills");
        let traitsContent = document.getElementById("traitsContent");

        // De identiteitsgegevens zijn voor iedereen dezelfde gegevenskaart. Wie
        // schrijfrechten heeft, opent enkel die kaart in bewerkmodus.
        renderCharacterDetailsCard(data, canEdit, canEditTraits);
        applyCharacterPortraitState(data);

        // --- EDITABLE MODE (admin of owner-player) ---
        if (canEdit) {
            if (skillsEl && getOriginalSkillsHtml() !== null) {
                skillsEl.innerHTML = getOriginalSkillsHtml();
            }

            accordionSkills = document.getElementById("accordionSkills");
            traitsContent = document.getElementById("traitsContent");

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

            renderTraitGroups(traitsContent, mainTraitGroups, canEditTraits);
            renderCharacterHealthSection(data);
            await renderCharacterLivingStandardSection(data);
            await renderCharacterStaffSection(data);
            renderCharacterLanguagesSection(data);

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

            // Skills als leesbare lijst
            if (accordionSkills) {
            renderSkillsReadOnly(accordionSkills, data.skills || []);
            }

            const mainTraitGroups = getMainTraitGroups(data, data.traitGroups || []);

            renderTraitGroups(traitsContent, mainTraitGroups, canEditTraits);
            renderCharacterHealthSection(data);
            await renderCharacterLivingStandardSection(data);
            await renderCharacterStaffSection(data);
            renderCharacterLanguagesSection(data);

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

async function initCharacterSidebarUserFilter() {
    const wrapper = document.getElementById("sidebarCharacterUserFilterWrapper");
    const container = document.getElementById("sidebarCharacterUserFilter");
    if (!wrapper || !container) {
        return;
    }

    if (!userHasPrivilegedRole(currentUser)) {
        wrapper.classList.add("d-none");
        characterSidebarUserFilterValue = "all";
        return;
    }

    if (characterSidebarUserFilterInitialised) {
        wrapper.classList.remove("d-none");
        return;
    }

    try {
        const data = await apiFetchJson("api/users/getUserList.php");
        if (!data) {
            return;
        }

        const userOptions = [
            { id: "all", text: "all" },
            { id: "unassigned", text: "unassigned" },
            ...Object.values(data).map((value) => ({
                id: String(value.id),
                text: String(value.displayName || `${value.firstName || ""} ${value.lastName || ""}`.trim() || value.username || `User #${value.id}`)
            }))
        ];

        new DropdownInput(
            "sidebarCharacterUserFilter",
            userOptions,
            (selectedId) => {
                characterSidebarUserFilterValue = selectedId === null || selectedId === undefined || selectedId === ""
                    ? "all"
                    : String(selectedId);
                renderCharacterSidebarList();
            },
            characterSidebarUserFilterValue
        );

        wrapper.classList.remove("d-none");
        characterSidebarUserFilterInitialised = true;
    } catch (err) {
        console.error("Fout bij ophalen users voor sidebarfilter:", err);
    }
}

function getCharacterSidebarFilters() {
    return {
        idUser: characterSidebarUserFilterValue || "all",
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
    if (!currentUser || !character || !["draft", "inactive", "deceased"].includes(character.state)) {
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

function renderCharacterDeleteActions(character) {
    const host = document.getElementById("characterDeleteActions");
    if (!host) return;
    host.replaceChildren();
    const allowed = canDeleteCharacterFromSidebar(character);
    host.classList.toggle("d-none", !allowed);
    if (!allowed) return;
    const button = AetherActionButtons.button({
        icon: "fa-trash", label: "Delete character",
        onClick: () => handleSidebarCharacterDelete(character.id)
    });
    const group = AetherActionButtons.group([button]);
    group.classList.add("mb-3");
    host.append(group);
}

function isCharacterSidebarItemUnassigned(character) {
    const idUser = Number(character?.idUser || 0);
    return idUser <= 0;
}

function filterCharacterSidebarItems(characters) {
    const filters = getCharacterSidebarFilters();

    return characters.filter((character) => {
        if (filters.idUser === "unassigned" && !isCharacterSidebarItemUnassigned(character)) {
            return false;
        }

        if (
            filters.idUser
            && filters.idUser !== "all"
            && filters.idUser !== "unassigned"
            && Number(character.idUser || 0) !== Number(filters.idUser)
        ) {
            return false;
        }

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
    renderCharacterDeleteActions(null);

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
    ["backgroundTab", "diaryTab", "personalityTab", "economyTab", "actionsTab", "passportTab"].forEach((id) => {
        document.getElementById(id)?.classList.add("d-none");
    });
}

function closeCharacterSidebarIfOpen() {
    if (typeof closeOffcanvasIfOpen === "function") {
        closeOffcanvasIfOpen();
        return;
    }
    if (typeof window.closeCharacterSidebar === "function") {
        window.closeCharacterSidebar();
    }
}

async function handleSidebarCharacterDelete(idCharacter) {
    const character = Number(currentCharacter?.id) === Number(idCharacter) ? currentCharacter
        : characterSidebarItems.find((item) => Number(item.id) === Number(idCharacter));
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
        const card = window.createCharacterSidebarCard(character, async () => {
            const characterForm = document.getElementById("characterForm");
            const skills = document.getElementById("skills");
            if (characterForm) characterForm.classList.remove("d-none");
            if (skills) skills.classList.remove("d-none");
            closeCharacterSidebarIfOpen();
            await getCharacter(character.id);
        }, currentCharacter?.id);
        nameList.appendChild(card);
    });
}

async function characterList() {
    try {
        const data = await fetchCharacterList();

        if (!data) return;

        characterSidebarItems = Object.values(data);
        renderCharacterSidebarList();
    } catch (err) {
        console.error("Fout bij ophalen personagelijst:", err);
    }
}











