const aetherCharacterActionsState = {
    characterId: 0,
    events: [],
    worldKnowledgeLevel: 0,
    availableActions: [],
    psiBurn: 0,
    selectedEventId: 0,
    activeAction: "",
    knowledgeTargets: [],
    knowledgeTargetsEventId: 0,
    knowledgeAttemptCount: 0,
    isLoadingCatalog: false,
    isLoadingKnowledgeTargets: false,
    isRevealingKnowledge: false,
    isUsingSkillAction: false,
};

let aetherKnowledgeModalInstance = null;

async function fetchCharacterActionEvents(idCharacter) {
    return apiFetchJson("api/characters/getCharacterActionEvents.php", {
        method: "POST",
        body: { idCharacter },
    });
}

async function fetchCharacterActionKnowledgeTargets(idCharacter, idEvent) {
    return apiFetchJson("api/characters/getCharacterActionKnowledgeTargets.php", {
        method: "POST",
        body: { idCharacter, idEvent },
    });
}

async function revealCharacterActionKnowledge(idCharacter, idEvent, idSourceCharacter) {
    return apiFetchJson("api/characters/revealCharacterActionKnowledge.php", {
        method: "POST",
        body: { idCharacter, idEvent, idSourceCharacter },
    });
}

async function useCharacterSkillAction(idCharacter, idEvent, idSkill, actionCode, actionSubtype, clearBurn = false) {
    return apiFetchJson("api/characters/useCharacterSkillAction.php", {
        method: "POST",
        body: {
            idCharacter,
            idEvent,
            idSkill,
            actionCode,
            actionSubtype,
            clearBurn,
        },
    });
}

function showActionsTab(character) {
    aetherCharacterActionsState.events = [];
    aetherCharacterActionsState.availableActions = [];
    aetherCharacterActionsState.worldKnowledgeLevel = 0;
    aetherCharacterActionsState.psiBurn = 0;
    aetherCharacterActionsState.knowledgeTargets = [];
    aetherCharacterActionsState.knowledgeTargetsEventId = 0;
    aetherCharacterActionsState.knowledgeAttemptCount = 0;
    aetherCharacterActionsState.activeAction = "";

    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const sheetBody = document.getElementById("sheetBody");
    if (sheetBody) sheetBody.classList.remove("d-none");

    const characterForm = document.getElementById("characterForm");
    if (characterForm) characterForm.classList.add("d-none");
    const skills = document.getElementById("skills");
    if (skills) skills.classList.add("d-none");

    const backgroundTab = document.getElementById("backgroundTab");
    if (backgroundTab) backgroundTab.classList.add("d-none");
    const diaryTab = document.getElementById("diaryTab");
    if (diaryTab) diaryTab.classList.add("d-none");
    const personalityTab = document.getElementById("personalityTab");
    if (personalityTab) personalityTab.classList.add("d-none");
    const economyTab = document.getElementById("economyTab");
    if (economyTab) economyTab.classList.add("d-none");
    const passportTab = document.getElementById("passportTab");
    if (passportTab) passportTab.classList.add("d-none");

    const actionsTab = document.getElementById("actionsTab");
    if (!actionsTab) return;

    actionsTab.classList.remove("d-none");
    actionsTab.style.display = "block";
    actionsTab.hidden = false;
    actionsTab.classList.add("p-3");
    actionsTab.style.backgroundColor = "#fff";
    actionsTab.style.minHeight = "300px";

    void renderCharacterActionsTab(currentCharacter || character);
}

async function renderCharacterActionsTab(character) {
    const container = document.getElementById("actionsTabContent");
    if (!container || !character) {
        return;
    }

    if (Number(aetherCharacterActionsState.characterId || 0) !== Number(character.id || 0)) {
        resetCharacterActionsState(character.id);
    }

    if (!aetherCharacterActionsState.isLoadingCatalog && aetherCharacterActionsState.events.length === 0) {
        aetherCharacterActionsState.isLoadingCatalog = true;
        container.innerHTML = `<div class="text-muted">Acties laden...</div>`;

        try {
            const result = await fetchCharacterActionEvents(character.id);
            aetherCharacterActionsState.events = Array.isArray(result?.events) ? result.events : [];
            aetherCharacterActionsState.worldKnowledgeLevel = Number(result?.worldKnowledgeLevel || 0);
            aetherCharacterActionsState.availableActions = Array.isArray(result?.actions) ? result.actions : [];
            aetherCharacterActionsState.psiBurn = Number(result?.psiBurn || 0);
            if (aetherCharacterActionsState.selectedEventId <= 0 && aetherCharacterActionsState.events.length > 0) {
                aetherCharacterActionsState.selectedEventId = Number(aetherCharacterActionsState.events[0].idEvent || 0);
            }
        } catch (err) {
            console.error("Fout bij laden characteracties:", err);
            container.innerHTML = `<div class="text-danger">Kon de acties van dit personage niet laden.</div>`;
            aetherCharacterActionsState.isLoadingCatalog = false;
            return;
        } finally {
            aetherCharacterActionsState.isLoadingCatalog = false;
        }
    }

    container.innerHTML = "";
    container.appendChild(buildCharacterActionsLayout(character));
}

function resetCharacterActionsState(idCharacter) {
    aetherCharacterActionsState.characterId = Number(idCharacter || 0);
    aetherCharacterActionsState.events = [];
    aetherCharacterActionsState.worldKnowledgeLevel = 0;
    aetherCharacterActionsState.availableActions = [];
    aetherCharacterActionsState.psiBurn = 0;
    aetherCharacterActionsState.selectedEventId = 0;
    aetherCharacterActionsState.activeAction = "";
    aetherCharacterActionsState.knowledgeTargets = [];
    aetherCharacterActionsState.knowledgeTargetsEventId = 0;
    aetherCharacterActionsState.knowledgeAttemptCount = 0;
    aetherCharacterActionsState.isLoadingCatalog = false;
    aetherCharacterActionsState.isLoadingKnowledgeTargets = false;
    aetherCharacterActionsState.isRevealingKnowledge = false;
    aetherCharacterActionsState.isUsingSkillAction = false;
}

function getCharacterActionsCatalog() {
    const catalog = [];

    if (aetherCharacterActionsState.worldKnowledgeLevel > 0) {
        catalog.push({
            actionCode: "knowledge",
            buttonAction: "character-action-knowledge",
            label: "Wereldwijs",
            imageSrc: "img/actionWereldwijs.png",
        });
    }

    (Array.isArray(aetherCharacterActionsState.availableActions) ? aetherCharacterActionsState.availableActions : []).forEach((action) => {
        const actionCode = String(action?.actionCode || "").trim();
        if (actionCode === "") {
            return;
        }

        catalog.push({
            actionCode,
            buttonAction: String(action?.buttonAction || ""),
            label: String(action?.label || actionCode),
            imageSrc: String(action?.imageSrc || "img/actionPsy.png"),
            actionKind: String(action?.actionKind || ""),
            categoryCode: String(action?.categoryCode || ""),
            skills: Array.isArray(action?.skills) ? action.skills : [],
        });
    });

    return catalog;
}

function getActivePsiActionGroup() {
    const activeAction = String(aetherCharacterActionsState.activeAction || "");
    if (!activeAction.startsWith("psi:")) {
        return null;
    }

    return (Array.isArray(aetherCharacterActionsState.availableActions) ? aetherCharacterActionsState.availableActions : []).find(
        (action) => String(action?.actionCode || "") === activeAction
    ) || null;
}

function buildCharacterActionsLayout(character) {
    const wrapper = document.createElement("div");

    const card = document.createElement("div");
    card.className = "card character-sheet-card";

    const header = document.createElement("div");
    header.className = "card-header character-actions-card-header";

    const headerRow = document.createElement("div");
    headerRow.className = "character-actions-event-row";

    const label = document.createElement("label");
    label.className = "character-actions-event-label";
    label.htmlFor = "characterActionsEventSelect";
    label.textContent = "Event";

    const select = document.createElement("select");
    select.className = "form-select character-actions-event-select";
    select.id = "characterActionsEventSelect";
    select.dataset.role = "character-actions-event";

    const placeholder = document.createElement("option");
    placeholder.value = "0";
    placeholder.textContent = aetherCharacterActionsState.events.length > 0
        ? "Kies een event"
        : "Geen events beschikbaar";
    select.appendChild(placeholder);

    aetherCharacterActionsState.events.forEach((eventOption) => {
        const option = document.createElement("option");
        option.value = String(eventOption.idEvent || 0);
        option.textContent = formatCharacterActionEventLabel(eventOption);
        select.appendChild(option);
    });

    if ([...select.options].some((option) => option.value === String(aetherCharacterActionsState.selectedEventId || 0))) {
        select.value = String(aetherCharacterActionsState.selectedEventId || 0);
    }

    headerRow.appendChild(label);
    headerRow.appendChild(select);
    header.appendChild(headerRow);
    card.appendChild(header);

    const body = document.createElement("div");
    body.className = "card-body";

    const actionButtonRow = document.createElement("div");
    actionButtonRow.className = "character-actions-button-row";
    const availableActions = getCharacterActionsCatalog();

    availableActions.forEach((action) => {
        const actionButton = document.createElement("button");
        actionButton.type = "button";
        actionButton.className = `character-action-tile ${aetherCharacterActionsState.activeAction === action.actionCode ? "is-active" : ""}`;
        actionButton.dataset.action = String(action.buttonAction || "");
        actionButton.dataset.actionCode = String(action.actionCode || "");

        if (action.categoryCode) {
            actionButton.dataset.psiCategory = String(action.categoryCode);
        }

        const image = document.createElement("img");
        image.className = "character-action-tile-image";
        image.src = String(action.imageSrc || "img/actionPsy.png");
        image.alt = String(action.label || "Actie");

        const label = document.createElement("span");
        label.className = "character-action-tile-label";
        label.textContent = String(action.label || "Actie");

        actionButton.appendChild(image);
        actionButton.appendChild(label);
        actionButtonRow.appendChild(actionButton);
    });

    if (actionButtonRow.childNodes.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Dit personage heeft momenteel geen acties beschikbaar.";
        body.appendChild(empty);
    } else {
        body.appendChild(actionButtonRow);

        const actionContent = document.createElement("div");
        actionContent.className = "character-actions-content mt-4";

        if (aetherCharacterActionsState.activeAction === "knowledge") {
            actionContent.appendChild(buildKnowledgeActionContent(character));
        } else {
            const activePsiAction = getActivePsiActionGroup();
            if (activePsiAction) {
                actionContent.appendChild(buildPsiActionContent(character, activePsiAction));
            }
        }

        body.appendChild(actionContent);
    }

    card.appendChild(body);
    wrapper.appendChild(card);
    return wrapper;
}

function buildKnowledgeActionContent(character) {
    const wrapper = document.createElement("div");

    if (Number(aetherCharacterActionsState.selectedEventId || 0) <= 0) {
        const note = document.createElement("p");
        note.className = "text-muted mb-0";
        note.textContent = "Kies eerst een event om de wereldwijsroddels te bekijken.";
        wrapper.appendChild(note);
        return wrapper;
    }

    const intro = document.createElement("p");
    intro.className = "text-muted mb-3";
    intro.textContent = buildKnowledgeIntroText();
    wrapper.appendChild(intro);

    if (aetherCharacterActionsState.isLoadingKnowledgeTargets) {
        const loading = document.createElement("div");
        loading.className = "text-muted";
        loading.textContent = "Wereldwijsroddels laden...";
        wrapper.appendChild(loading);
        return wrapper;
    }

    const targets = Array.isArray(aetherCharacterActionsState.knowledgeTargets)
        ? aetherCharacterActionsState.knowledgeTargets
        : [];

    if (targets.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Voor dit event zijn er geen zichtbare wereldwijsroddels beschikbaar.";
        wrapper.appendChild(empty);
        return wrapper;
    }

    const grid = document.createElement("div");
    grid.className = "character-action-portrait-grid";

    targets.forEach((target) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "character-action-portrait-card";
        button.dataset.action = "character-action-knowledge-target";
        button.dataset.idCharacterTarget = String(target.idCharacter || 0);

        const portraitWrap = document.createElement("div");
        portraitWrap.className = "character-action-portrait-frame";

        const portraitUrl = String(target.portraitUrl || "").trim();
        if (portraitUrl !== "") {
            const portrait = document.createElement("img");
            portrait.className = "character-action-portrait-image";
            portrait.src = portraitUrl;
            portrait.alt = target.displayName || "Portret";
            portraitWrap.appendChild(portrait);
        } else {
            const placeholder = document.createElement("div");
            placeholder.className = "character-action-portrait-placeholder";
            placeholder.textContent = "Geen pasfoto";
            portraitWrap.appendChild(placeholder);
        }

        const name = document.createElement("div");
        name.className = "character-action-portrait-name";
        name.textContent = target.displayName || `Personage #${target.idCharacter || 0}`;

        const status = document.createElement("div");
        status.className = "character-action-portrait-status";
        const unlockedLevel = Number(target.unlockedGossipLevel || 0);
        if (unlockedLevel > 0) {
            status.textContent = target.isFullyUnlocked
                ? `Vrijgespeeld t.e.m. niveau ${unlockedLevel}`
                : `Ontdekt t.e.m. niveau ${unlockedLevel}`;
        } else {
            status.textContent = "Nog niets vrijgespeeld";
        }

        button.appendChild(portraitWrap);
        button.appendChild(name);
        button.appendChild(status);
        grid.appendChild(button);
    });

    wrapper.appendChild(grid);
    return wrapper;
}

function getCharacterActionSkillLevelLabel(level) {
    const numericLevel = Number(level || 0);
    if (numericLevel <= 1) {
        return "Beginneling";
    }

    if (numericLevel === 2) {
        return "Deskundige";
    }

    return "Meester";
}

function buildPsiActionContent(character, action) {
    const wrapper = document.createElement("div");

    if (Number(aetherCharacterActionsState.selectedEventId || 0) <= 0) {
        const note = document.createElement("p");
        note.className = "text-muted mb-0";
        note.textContent = "Kies eerst een event om een psi-vaardigheid te gebruiken.";
        wrapper.appendChild(note);
        return wrapper;
    }

    const burnPanel = document.createElement("div");
    burnPanel.className = "character-skill-action-panel";

    const burnTitle = document.createElement("div");
    burnTitle.className = "character-skill-action-panel-title";
    burnTitle.textContent = `${action?.label || "Psi"}: huidige burn ${Number(aetherCharacterActionsState.psiBurn || 0)}`;
    burnPanel.appendChild(burnTitle);

    const burnText = document.createElement("p");
    burnText.className = "text-muted mb-3";
    burnText.textContent = "Psi burn telt op bij je worp. Je kan alle burn in 1 keer wegwerken door deze activatie met een extra +5 modifier uit te voeren.";
    burnPanel.appendChild(burnText);

    if (Number(aetherCharacterActionsState.psiBurn || 0) > 0) {
        const clearWrap = document.createElement("div");
        clearWrap.className = "form-check mb-0";

        const clearInput = document.createElement("input");
        clearInput.type = "checkbox";
        clearInput.className = "form-check-input";
        clearInput.id = "characterPsiClearBurn";
        clearInput.dataset.role = "character-psi-clear-burn";

        const clearLabel = document.createElement("label");
        clearLabel.className = "form-check-label";
        clearLabel.htmlFor = "characterPsiClearBurn";
        clearLabel.textContent = "Verwijder alle psi burn voor deze activatie (+5 modifier, burn daarna terug naar 0).";

        clearWrap.appendChild(clearInput);
        clearWrap.appendChild(clearLabel);
        burnPanel.appendChild(clearWrap);
    }

    wrapper.appendChild(burnPanel);

    const intro = document.createElement("p");
    intro.className = "text-muted mb-3";
    intro.textContent = "Kies hieronder welke vaardigheid je activeert. Het gebruik wordt geregistreerd met personage, event, tijdstip en resultaat.";
    wrapper.appendChild(intro);

    const skills = Array.isArray(action?.skills) ? action.skills : [];
    if (skills.length < 1) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Voor deze psi-categorie zijn geen bruikbare vaardigheden gevonden.";
        wrapper.appendChild(empty);
        return wrapper;
    }

    const grid = document.createElement("div");
    grid.className = "character-skill-action-grid";

    skills.forEach((skill) => {
        const card = document.createElement("div");
        card.className = "character-skill-action-card";

        const name = document.createElement("div");
        name.className = "character-skill-action-name";
        name.textContent = String(skill?.name || `Vaardigheid #${skill?.idSkill || 0}`);

        const level = document.createElement("div");
        level.className = "character-skill-action-meta";
        level.textContent = `Niveau ${Number(skill?.level || 0)} - ${getCharacterActionSkillLevelLabel(skill?.level)}`;

        const useButton = document.createElement("button");
        useButton.type = "button";
        useButton.className = "btn btn-primary mt-auto";
        useButton.dataset.action = "character-action-psi-use";
        useButton.dataset.idSkill = String(skill?.idSkill || 0);
        useButton.dataset.psiCategory = String(action?.categoryCode || "");
        useButton.disabled = aetherCharacterActionsState.isUsingSkillAction;
        useButton.textContent = aetherCharacterActionsState.isUsingSkillAction ? "Bezig..." : "Gebruik";

        card.appendChild(name);
        card.appendChild(level);
        card.appendChild(useButton);
        grid.appendChild(card);
    });

    wrapper.appendChild(grid);
    return wrapper;
}

function formatCharacterActionEventLabel(eventOption) {
    const title = String(eventOption?.title || "").trim() || `Event #${eventOption?.idEvent || 0}`;
    const dateStart = String(eventOption?.dateStart || "").trim();
    if (dateStart === "") {
        return title;
    }

    const date = new Date(dateStart);
    if (Number.isNaN(date.getTime())) {
        return title;
    }

    return `${title} (${date.toLocaleDateString("nl-BE")})`;
}

async function loadCharacterKnowledgeTargets(character) {
    if (!character || Number(aetherCharacterActionsState.selectedEventId || 0) <= 0) {
        return;
    }

    aetherCharacterActionsState.isLoadingKnowledgeTargets = true;
    await renderCharacterActionsTab(character);

    try {
        const result = await fetchCharacterActionKnowledgeTargets(character.id, aetherCharacterActionsState.selectedEventId);
        aetherCharacterActionsState.worldKnowledgeLevel = Number(result?.worldKnowledgeLevel || aetherCharacterActionsState.worldKnowledgeLevel || 0);
        aetherCharacterActionsState.knowledgeAttemptCount = Number(result?.attemptCount || 0);
        aetherCharacterActionsState.knowledgeTargets = Array.isArray(result?.targets) ? result.targets : [];
        aetherCharacterActionsState.knowledgeTargetsEventId = Number(aetherCharacterActionsState.selectedEventId || 0);
    } catch (err) {
        console.error("Fout bij laden wereldwijsdoelen:", err);
        alert("Kon de wereldwijsroddels voor dit event niet laden.");
    } finally {
        aetherCharacterActionsState.isLoadingKnowledgeTargets = false;
        await renderCharacterActionsTab(character);
    }
}

async function openCharacterKnowledgeAction(character) {
    aetherCharacterActionsState.activeAction = "knowledge";
    if (aetherCharacterActionsState.knowledgeTargetsEventId !== Number(aetherCharacterActionsState.selectedEventId || 0)) {
        aetherCharacterActionsState.knowledgeTargets = [];
    }
    await renderCharacterActionsTab(character);
    await loadCharacterKnowledgeTargets(character);
}

async function revealCharacterKnowledgeTarget(character, idSourceCharacter) {
    if (!character || aetherCharacterActionsState.isRevealingKnowledge) {
        return;
    }

    const idEvent = Number(aetherCharacterActionsState.selectedEventId || 0);
    if (idEvent <= 0 || Number(idSourceCharacter || 0) <= 0) {
        return;
    }

    const target = aetherCharacterActionsState.knowledgeTargets.find(
        (item) => Number(item.idCharacter || 0) === Number(idSourceCharacter)
    ) || null;

    if (target && !target.isFullyUnlocked) {
        const nextAttemptNumber = Number(aetherCharacterActionsState.knowledgeAttemptCount || 0) + 1;
        const confirmed = await confirmKnowledgeReveal(target, nextAttemptNumber);
        if (!confirmed) {
            return;
        }
    }

    aetherCharacterActionsState.isRevealingKnowledge = true;

    try {
        const result = await revealCharacterActionKnowledge(character.id, idEvent, Number(idSourceCharacter));
        aetherCharacterActionsState.knowledgeAttemptCount = Number(result?.attemptCount || aetherCharacterActionsState.knowledgeAttemptCount || 0);
        const targetIndex = aetherCharacterActionsState.knowledgeTargets.findIndex(
            (target) => Number(target.idCharacter || 0) === Number(idSourceCharacter)
        );

        if (targetIndex >= 0) {
            aetherCharacterActionsState.knowledgeTargets[targetIndex] = {
                ...aetherCharacterActionsState.knowledgeTargets[targetIndex],
                unlockedGossipLevel: Number(result?.unlockedGossipLevel || 0),
                isFullyUnlocked: Boolean(result?.isFullyUnlocked),
            };
        }

        await renderCharacterActionsTab(character);
        openKnowledgeGossipModal(result);
    } catch (err) {
        console.error("Fout bij vrijspelen wereldwijsroddels:", err);
        alert(err?.message || "Kon de wereldwijsroddels niet vrijspelen.");
    } finally {
        aetherCharacterActionsState.isRevealingKnowledge = false;
    }
}

function buildKnowledgeIntroText() {
    const level = Number(aetherCharacterActionsState.worldKnowledgeLevel || 0);
    const attemptCount = Number(aetherCharacterActionsState.knowledgeAttemptCount || 0);

    return `Je bezit Wereldwijs op niveau ${level}. Per event starten je kansen maximaal: bij je eerste poging kan je alle roddels tot en met jouw niveau ontdekken. Daarna daalt de kans op het hoogste niveau telkens met 1 op 10 per extra poging; de lagere niveaus volgen telkens één poging later. Voor dit event heb je al ${attemptCount} poging${attemptCount === 1 ? "" : "en"} ondernomen. Klik op een personage om vrijgespeelde roddels te lezen of een nieuwe poging te wagen.`;
}

function ensureKnowledgeConfirmModal() {
    let modalEl = document.getElementById("knowledgeRevealConfirmModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "knowledgeRevealConfirmModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Wereldwijs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" data-role="knowledge-reveal-confirm-text"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="button" class="btn btn-primary" data-role="knowledge-reveal-confirm-accept">Poging gebruiken</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    return modalEl;
}

function confirmKnowledgeReveal(target, nextAttemptNumber) {
    const modalEl = ensureKnowledgeConfirmModal();
    const text = modalEl.querySelector("[data-role='knowledge-reveal-confirm-text']");
    const acceptButton = modalEl.querySelector("[data-role='knowledge-reveal-confirm-accept']");
    if (!text || !acceptButton) {
        return Promise.resolve(false);
    }

    text.textContent = `Dit is je ${nextAttemptNumber}e poging om tijdens dit event informatie te verzamelen. Ben je zeker dat je een poging wilt gebruiken op ${target?.displayName || "dit personage"}?`;

    return new Promise((resolve) => {
        const modal = new bootstrap.Modal(modalEl);

        const cleanup = (result) => {
            acceptButton.removeEventListener("click", handleAccept);
            modalEl.removeEventListener("hidden.bs.modal", handleHidden);
            resolve(result);
        };

        const handleAccept = () => {
            modal.hide();
            cleanup(true);
        };

        const handleHidden = () => {
            cleanup(false);
        };

        acceptButton.addEventListener("click", handleAccept, { once: true });
        modalEl.addEventListener("hidden.bs.modal", handleHidden, { once: true });
        modal.show();
    });
}

function ensureKnowledgeGossipModal() {
    let modalEl = document.getElementById("knowledgeGossipModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "knowledgeGossipModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Wereldwijs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs mb-3" id="knowledgeGossipModalTabs" role="tablist"></ul>
                        <div class="tab-content" id="knowledgeGossipModalContent"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    if (!aetherKnowledgeModalInstance) {
        aetherKnowledgeModalInstance = new bootstrap.Modal(modalEl);
    }

    return modalEl;
}

function openKnowledgeGossipModal(result) {
    const modalEl = ensureKnowledgeGossipModal();
    const title = modalEl.querySelector(".modal-title");
    const tabs = modalEl.querySelector("#knowledgeGossipModalTabs");
    const content = modalEl.querySelector("#knowledgeGossipModalContent");
    if (!title || !tabs || !content) {
        return;
    }

    title.textContent = result?.displayName || "Wereldwijs";
    tabs.innerHTML = "";
    content.innerHTML = "";

    const unlockedGossips = Array.isArray(result?.unlockedGossips) ? result.unlockedGossips : [];
    if (unlockedGossips.length === 0) {
        const empty = document.createElement("div");
        empty.className = "text-muted";
        empty.textContent = "Deze poging leverde nog geen nieuwe of eerder vrijgespeelde roddels op.";
        content.appendChild(empty);
        aetherKnowledgeModalInstance.show();
        return;
    }

    unlockedGossips.forEach((gossip, index) => {
        const level = Number(gossip.level || 0);
        const tabId = `knowledge-gossip-tab-${level}`;
        const paneId = `knowledge-gossip-pane-${level}`;

        const li = document.createElement("li");
        li.className = "nav-item";
        li.role = "presentation";

        const button = document.createElement("button");
        button.className = `nav-link${index === 0 ? " active" : ""}`;
        button.id = tabId;
        button.dataset.bsToggle = "tab";
        button.dataset.bsTarget = `#${paneId}`;
        button.type = "button";
        button.role = "tab";
        button.setAttribute("aria-controls", paneId);
        button.setAttribute("aria-selected", index === 0 ? "true" : "false");
        button.textContent = `Niveau ${level}`;
        li.appendChild(button);
        tabs.appendChild(li);

        const pane = document.createElement("div");
        pane.className = `tab-pane fade${index === 0 ? " show active" : ""}`;
        pane.id = paneId;
        pane.role = "tabpanel";
        pane.setAttribute("aria-labelledby", tabId);

        const text = document.createElement("div");
        text.className = "character-action-gossip-text";
        text.textContent = gossip.value || "";
        pane.appendChild(text);
        content.appendChild(pane);
    });

    aetherKnowledgeModalInstance.show();
}

function ensureCharacterSkillActionResultModal() {
    let modalEl = document.getElementById("characterSkillActionResultModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "characterSkillActionResultModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Vaardigheidsactie</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="character-skill-action-result-meta" data-role="skill-action-result-meta"></div>
                        <div class="character-action-gossip-text" data-role="skill-action-result-text"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    return new bootstrap.Modal(modalEl);
}

function openCharacterSkillActionResultModal(result) {
    const modalInstance = ensureCharacterSkillActionResultModal();
    const modalEl = document.getElementById("characterSkillActionResultModal");
    if (!modalEl) {
        return;
    }

    const title = modalEl.querySelector(".modal-title");
    const meta = modalEl.querySelector("[data-role='skill-action-result-meta']");
    const text = modalEl.querySelector("[data-role='skill-action-result-text']");
    if (!title || !meta || !text) {
        return;
    }

    title.textContent = `${result?.skill?.name || "Vaardigheidsactie"} - ${result?.categoryLabel || ""}`.trim();
    meta.innerHTML = "";

    const details = [
        `Event: ${result?.event?.title || `#${result?.event?.idEvent || 0}`}`,
        `Worp: ${Number(result?.roll?.base || 0)} + burn ${Number(result?.roll?.burnBefore || 0)} + modifier ${Number(result?.roll?.clearBurnModifier || 0)} = ${Number(result?.roll?.final || 0)}`,
        `Tabelrij: ${result?.roll?.rangeLabel || "-"}`,
        `Psi burn: ${Number(result?.burn?.before || 0)} -> ${Number(result?.burn?.after || 0)}${result?.burn?.cleared ? " (gewist)" : ""}`,
    ];

    details.forEach((line) => {
        const item = document.createElement("div");
        item.textContent = line;
        meta.appendChild(item);
    });

    text.textContent = String(result?.result?.text || "");
    modalInstance.show();
}

async function openCharacterPsiAction(character, categoryCode) {
    if (!character || !categoryCode) {
        return;
    }

    aetherCharacterActionsState.activeAction = `psi:${categoryCode}`;
    await renderCharacterActionsTab(character);
}

async function useCharacterPsiSkill(character, idSkill, categoryCode) {
    if (!character || aetherCharacterActionsState.isUsingSkillAction) {
        return;
    }

    const idEvent = Number(aetherCharacterActionsState.selectedEventId || 0);
    if (idEvent <= 0 || Number(idSkill || 0) <= 0 || !categoryCode) {
        alert("Kies eerst een event en een geldige psi-vaardigheid.");
        return;
    }

    const clearBurnInput = document.getElementById("characterPsiClearBurn");
    const clearBurn = Boolean(clearBurnInput?.checked);

    aetherCharacterActionsState.isUsingSkillAction = true;
    await renderCharacterActionsTab(character);

    try {
        const result = await useCharacterSkillAction(
            character.id,
            idEvent,
            Number(idSkill),
            "psi",
            String(categoryCode),
            clearBurn
        );

        aetherCharacterActionsState.psiBurn = Number(result?.burn?.after || 0);
        await renderCharacterActionsTab(character);
        openCharacterSkillActionResultModal(result);
    } catch (err) {
        console.error("Fout bij registreren psi-gebruik:", err);
        alert(err?.message || "Kon het gebruik van deze psi-vaardigheid niet registreren.");
    } finally {
        aetherCharacterActionsState.isUsingSkillAction = false;
        await renderCharacterActionsTab(character);
    }
}

document.addEventListener("change", (event) => {
    const select = event.target instanceof HTMLElement
        ? event.target.closest("[data-role='character-actions-event']")
        : null;
    if (!select || Number(currentCharacter?.id || 0) <= 0) {
        return;
    }

    aetherCharacterActionsState.selectedEventId = Number(select.value || 0);
    aetherCharacterActionsState.knowledgeTargets = [];
    aetherCharacterActionsState.knowledgeTargetsEventId = 0;

    if (aetherCharacterActionsState.activeAction === "knowledge") {
        void openCharacterKnowledgeAction(currentCharacter);
        return;
    }

    void renderCharacterActionsTab(currentCharacter);
});

document.addEventListener("click", (event) => {
    const knowledgeButton = event.target instanceof HTMLElement
        ? event.target.closest("[data-action='character-action-knowledge']")
        : null;
    if (knowledgeButton && Number(currentCharacter?.id || 0) > 0) {
        void openCharacterKnowledgeAction(currentCharacter);
        return;
    }

    const targetButton = event.target instanceof HTMLElement
        ? event.target.closest("[data-action='character-action-knowledge-target']")
        : null;
    if (targetButton && Number(currentCharacter?.id || 0) > 0) {
        const idSourceCharacter = Number(targetButton.dataset.idCharacterTarget || 0);
        if (idSourceCharacter > 0) {
            void revealCharacterKnowledgeTarget(currentCharacter, idSourceCharacter);
        }
        return;
    }

    const psiButton = event.target instanceof HTMLElement
        ? event.target.closest("[data-action='actionPsi']")
        : null;
    if (psiButton && Number(currentCharacter?.id || 0) > 0) {
        const categoryCode = String(psiButton.dataset.psiCategory || "");
        if (categoryCode !== "") {
            void openCharacterPsiAction(currentCharacter, categoryCode);
        }
        return;
    }

    const psiUseButton = event.target instanceof HTMLElement
        ? event.target.closest("[data-action='character-action-psi-use']")
        : null;
    if (psiUseButton && Number(currentCharacter?.id || 0) > 0) {
        const idSkill = Number(psiUseButton.dataset.idSkill || 0);
        const categoryCode = String(psiUseButton.dataset.psiCategory || "");
        if (idSkill > 0 && categoryCode !== "") {
            void useCharacterPsiSkill(currentCharacter, idSkill, categoryCode);
        }
    }
});
