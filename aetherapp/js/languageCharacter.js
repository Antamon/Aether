let languageModalInstance = null;

function sortLanguagesByName(items) {
    return [...(Array.isArray(items) ? items : [])].sort((left, right) =>
        String(left?.name || "").localeCompare(String(right?.name || ""), "nl-BE", { sensitivity: "base" })
    );
}

function shouldShowLanguageSectionClient(character) {
    const characterClass = String(character?.class || "").trim();
    return characterClass === "upper class"
        || characterClass === "middle class"
        || characterClass === "lower class";
}

function canManageCharacterLanguagesClient(character) {
    if (!currentUser || !character) return false;

    if (currentUser.role === "administrator" || currentUser.role === "director") {
        return true;
    }

    return currentUser.role === "participant"
        && character.type === "player"
        && Number(character.idUser) === Number(currentUser.id);
}

function getCharacterLanguageSummaryClient(character) {
    const summary = character?.languageSummary && typeof character.languageSummary === "object"
        ? character.languageSummary
        : null;

    const languages = getCharacterSelectedLanguagesClient(character);
    const freeSlots = getCharacterFreeLanguageSlotsClient(character);
    const selectedCount = languages.length;
    const paidLanguageCount = Math.max(0, selectedCount - freeSlots);
    const remainingExperience = getRemainingExperience(character);
    const canBuyWithExperience = character?.type === "player"
        ? remainingExperience >= 2
        : true;

    return {
        isVisible: summary ? Boolean(summary.isVisible) : canCharacterUseWrittenLanguagesClient(character),
        freeSlots: summary ? Number(summary.freeSlots || 0) : freeSlots,
        selectedCount: summary ? Number(summary.selectedCount || 0) : selectedCount,
        usedFreeSlots: summary ? Number(summary.usedFreeSlots || 0) : Math.min(selectedCount, freeSlots),
        paidLanguageCount: summary ? Number(summary.paidLanguageCount || 0) : paidLanguageCount,
        remainingExperience: summary ? Number(summary.remainingExperience || 0) : remainingExperience,
        canBuyWithExperience: summary ? Boolean(summary.canBuyWithExperience) : canBuyWithExperience,
        canAddLanguage: summary
            ? Boolean(summary.canAddLanguage)
            : (canCharacterUseWrittenLanguagesClient(character) && (selectedCount < freeSlots || canBuyWithExperience)),
    };
}

function ensureLanguageModal() {
    const modalEl = document.getElementById("languageModal");
    if (!modalEl) {
        return null;
    }

    if (!languageModalInstance) {
        languageModalInstance = new bootstrap.Modal(modalEl);
    }

    return modalEl;
}

async function openLanguageModal(idCharacter) {
    const modalEl = ensureLanguageModal();
    if (!modalEl || !idCharacter) return;

    modalEl.dataset.characterId = String(idCharacter);

    const select = document.getElementById("languageSelect");
    const customInput = document.getElementById("languageCustom");
    const tabExisting = document.getElementById("language-existing-tab");
    const tabNew = document.getElementById("language-new-tab");
    const paneExisting = document.getElementById("language-existing-pane");
    const paneNew = document.getElementById("language-new-pane");

    if (!select || !customInput || !tabExisting || !tabNew || !paneExisting || !paneNew) {
        return;
    }

    select.innerHTML = "";
    customInput.value = "";

    tabExisting.classList.add("active");
    tabExisting.setAttribute("aria-selected", "true");
    paneExisting.classList.add("show", "active");
    tabNew.classList.remove("active");
    tabNew.setAttribute("aria-selected", "false");
    paneNew.classList.remove("show", "active");

    try {
        const result = await apiFetchJson("api/characters/getCharacterLanguageOptions.php", {
            method: "POST",
            body: { idCharacter }
        });
        const options = sortLanguagesByName(Array.isArray(result?.options) ? result.options : []);
        options.forEach((option) => {
            const optionEl = document.createElement("option");
            optionEl.value = String(option.id || 0);
            optionEl.textContent = option.name || "";
            select.appendChild(optionEl);
        });

        if (options.length === 0) {
            tabExisting.classList.remove("active");
            tabExisting.setAttribute("aria-selected", "false");
            paneExisting.classList.remove("show", "active");
            tabNew.classList.add("active");
            tabNew.setAttribute("aria-selected", "true");
            paneNew.classList.add("show", "active");
        }
    } catch (err) {
        console.error("Fout bij ophalen taalopties:", err);
    }

    languageModalInstance.show();
}

async function saveCharacterLanguageFromModal() {
    const modalEl = ensureLanguageModal();
    if (!modalEl) return;

    const idCharacter = Number(modalEl.dataset.characterId || 0);
    if (idCharacter <= 0) return;

    const select = document.getElementById("languageSelect");
    const customInput = document.getElementById("languageCustom");
    const activeTab = document.querySelector("#languageTab .nav-link.active")?.dataset.tab;

    let payload = { idCharacter };

    if (activeTab === "existing") {
        const selectedValue = Number(select?.value || 0);
        if (selectedValue <= 0) {
            alert("Kies een bestaande taal of ga naar 'New'.");
            return;
        }
        payload.idLanguage = selectedValue;
    } else if (activeTab === "new") {
        const name = String(customInput?.value || "").trim();
        if (!name) {
            alert("Vul een naam in voor de nieuwe taal.");
            return;
        }
        payload.name = name;
    } else {
        alert("Onbekende tab-selectie.");
        return;
    }

    try {
        const result = await apiFetchJson("api/characters/addCharacterLanguage.php", {
            method: "POST",
            body: payload
        });
        if (result?.error) {
            alert(result.error);
            return;
        }

        await getCharacter(idCharacter, typeof getOpenSkillCollapseId === "function" ? getOpenSkillCollapseId() : null, typeof getActiveNavTabName === "function" ? getActiveNavTabName() : "sheet");
        languageModalInstance.hide();
    } catch (err) {
        console.error("Fout bij toevoegen taal:", err);
        alert("Toevoegen van taal mislukt.");
    }
}

async function deleteCharacterLanguage(idCharacterLanguage) {
    if (!currentCharacter?.id || !idCharacterLanguage) return;

    try {
        const result = await apiFetchJson("api/characters/deleteCharacterLanguage.php", {
            method: "POST",
            body: {
                idCharacter: currentCharacter.id,
                idCharacterLanguage
            }
        });
        if (result?.error) {
            alert(result.error);
            return;
        }

        await getCharacter(currentCharacter.id, typeof getOpenSkillCollapseId === "function" ? getOpenSkillCollapseId() : null, typeof getActiveNavTabName === "function" ? getActiveNavTabName() : "sheet");
    } catch (err) {
        console.error("Fout bij verwijderen taal:", err);
        alert("Verwijderen van taal mislukt.");
    }
}

function renderCharacterLanguagesSection(character) {
    const title = document.getElementById("languagesSectionTitle");
    const container = document.getElementById("languagesSectionContent");
    if (!title || !container) return;

    const shouldShowSection = shouldShowLanguageSectionClient(character);
    const canUseWrittenLanguages = canCharacterUseWrittenLanguagesClient(character);
    const summary = getCharacterLanguageSummaryClient(character);
    const canManage = canManageCharacterLanguagesClient(character) && Boolean(character?.canManageLanguages);
    const languages = sortLanguagesByName(getCharacterSelectedLanguagesClient(character));
    const isParticipantViewingExtra = currentUser?.role === "participant" && character?.type === "extra";

    title.classList.toggle("d-none", !shouldShowSection);
    container.classList.toggle("d-none", !shouldShowSection);
    if (!shouldShowSection) {
        container.innerHTML = "";
        if (typeof syncCharacterLeftInfoSectionsVisibility === "function") {
            syncCharacterLeftInfoSectionsVisibility();
        }
        return;
    }

    if (!canUseWrittenLanguages) {
        container.innerHTML = `
            <p class="text-muted mb-0">
                Iedereen mag binnen spel elke taal gebruiken die je logisch acht voor je personage en die je buitenspel effectief kan spreken.
                Op dit moment ben je analfabeet. Wanneer de de Achtergrond "Lezen en Schrijven" of "Opleiding" neemt, kan je hier extra tale nemen die je kan lezen en schrijven.
            </p>
        `;
        if (typeof syncCharacterLeftInfoSectionsVisibility === "function") {
            syncCharacterLeftInfoSectionsVisibility();
        }
        return;
    }

    const remainingFreeSlots = Math.max(0, summary.freeSlots - summary.selectedCount);
    let introText = "Iedereen mag binnen spel elke taal gebruiken die je logisch acht voor je personage en die je buitenspel effectief kan spreken. De talen die hieronder opgelijst staan, zijn uitsluitend om teksten uit boeken en documenten te lezen en schrijven.";

    if (!isParticipantViewingExtra) {
        if (remainingFreeSlots > 0) {
            introText += ` Je kan er nog ${remainingFreeSlots} kiezen.`;
        } else if (character?.type === "player") {
            if (summary.canBuyWithExperience) {
                introText += " Je kan nog extra talen aankopen voor 2 EP.";
            }
        } else {
            introText += " Je kan nog extra talen aankopen voor 2 EP.";
        }
    }

    const languageList = languages.length > 0
        ? `<ul class="list-unstyled mb-3">${languages.map((language) => `
            <li class="d-flex justify-content-between align-items-center gap-3 mb-2">
                <span>${language.name || ""}</span>
                ${canManage ? `
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-action="delete-character-language"
                            data-id-character-language="${Number(language.id || 0)}">
                        Verwijderen
                    </button>
                ` : ""}
            </li>
        `).join("")}</ul>`
        : `<p class="text-muted mb-3">Nog geen extra schrijftalen gekozen.</p>`;

    const addButtonDisabled = !canManage || !summary.canAddLanguage;
    const addHelp = !summary.canAddLanguage && character?.type === "player"
        ? `<div class="form-text">Geen vrije taalslots meer en onvoldoende ervaringspunten voor een extra taal.</div>`
        : "";

    container.innerHTML = `
        <p class="text-muted mb-3">${introText}</p>
        ${languageList}
        ${canManage ? `
            <div>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        id="addCharacterLanguageButton"
                        ${addButtonDisabled ? "disabled" : ""}>
                    Nieuwe taal
                </button>
                ${addHelp}
            </div>
        ` : ""}
    `;

    const addButton = document.getElementById("addCharacterLanguageButton");
    if (addButton) {
        addButton.addEventListener("click", () => openLanguageModal(Number(character?.id || 0)));
    }

    container.querySelectorAll("[data-action='delete-character-language']").forEach((button) => {
        button.addEventListener("click", async () => {
            await deleteCharacterLanguage(Number(button.dataset.idCharacterLanguage || 0));
        });
    });

    if (typeof syncCharacterLeftInfoSectionsVisibility === "function") {
        syncCharacterLeftInfoSectionsVisibility();
    }
}

document.getElementById("languageSaveBtn")?.addEventListener("click", async () => {
    await saveCharacterLanguageFromModal();
});
