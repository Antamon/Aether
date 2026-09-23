const adminActionsState = {
    events: [],
    catalogLoaded: false,
    selectedEventId: 0,
    items: [],
    modalInstance: null,
    saveInFlight: false,
};

window.addEventListener("load", () => {
    setupAdminActionsListeners();
});

function setupAdminActionsListeners() {
    const eventSelect = document.getElementById("adminActionsEventSelect");
    if (eventSelect) {
        eventSelect.addEventListener("change", async () => {
            const idEvent = Number(eventSelect.value || 0);
            adminActionsState.selectedEventId = idEvent;

            if (idEvent <= 0) {
                renderAdminActionItems([]);
                return;
            }

            await loadAdminActionEventUses(idEvent);
        });
    }

    const content = document.getElementById("adminActionsContent");
    if (content) {
        content.addEventListener("click", async (event) => {
            const editButton = event.target instanceof HTMLElement
                ? event.target.closest("button[data-admin-action-edit]")
                : null;
            if (editButton) {
                const idActionUse = Number(editButton.dataset.idActionUse || 0);
                if (idActionUse > 0) {
                    openAdminActionEditModal(idActionUse);
                }
                return;
            }

            const deleteButton = event.target instanceof HTMLElement
                ? event.target.closest("button[data-admin-action-delete]")
                : null;
            if (deleteButton) {
                const idActionUse = Number(deleteButton.dataset.idActionUse || 0);
                if (idActionUse > 0) {
                    await deleteAdminActionUseRecord(idActionUse);
                }
            }
        });
    }
}

async function ensureAdminActionsCatalogLoaded() {
    if (adminActionsState.catalogLoaded) {
        return;
    }

    await loadAdminActionEvents();
}

window.ensureAdminActionsCatalogLoaded = ensureAdminActionsCatalogLoaded;

async function loadAdminActionEvents() {
    try {
        const result = await apiFetchJson("api/admin/getActionEvents.php");
        adminActionsState.events = Array.isArray(result?.events) ? result.events : [];
        adminActionsState.catalogLoaded = true;
        syncAdminActionEventSelect();

        if (adminActionsState.events.length > 0) {
            const nextEventId = adminActionsState.selectedEventId > 0
                ? adminActionsState.selectedEventId
                : Number(
                    adminActionsState.events.find((eventOption) => Number(eventOption?.actionCount || 0) > 0)?.idEvent
                    || adminActionsState.events[0]?.idEvent
                    || 0
                );
            adminActionsState.selectedEventId = nextEventId;
            syncAdminActionEventSelect();

            if (nextEventId > 0) {
                await loadAdminActionEventUses(nextEventId);
                return;
            }
        }

        adminActionsState.items = [];
        renderAdminActionItems([]);
    } catch (err) {
        console.error("Fout bij laden actie-events:", err);
        adminActionsState.items = [];
        showAdminFeedback("Kon de eventlijst voor acties niet laden.", "danger");
        renderAdminActionsError("Kon de eventlijst voor acties niet laden.");
    }
}

function syncAdminActionEventSelect() {
    const select = document.getElementById("adminActionsEventSelect");
    if (!select) {
        return;
    }

    select.innerHTML = "";

    const placeholder = document.createElement("option");
    placeholder.value = "0";
    placeholder.textContent = adminActionsState.events.length > 0
        ? "Kies een event"
        : "Geen events beschikbaar";
    select.appendChild(placeholder);

    adminActionsState.events.forEach((eventOption) => {
        const option = document.createElement("option");
        option.value = String(eventOption.idEvent || 0);
        option.textContent = formatAdminActionEventLabel(eventOption);
        select.appendChild(option);
    });

    const selectedValue = String(adminActionsState.selectedEventId || 0);
    select.value = [...select.options].some((option) => option.value === selectedValue)
        ? selectedValue
        : "0";
}

function formatAdminActionEventLabel(eventOption) {
    const title = String(eventOption?.title || "").trim() || `Event #${eventOption?.idEvent || 0}`;
    const dateStart = String(eventOption?.dateStart || "").trim();
    const actionCount = Number(eventOption?.actionCount || 0);
    const countLabel = actionCount > 0 ? ` - ${actionCount} actie${actionCount === 1 ? "" : "s"}` : "";
    if (dateStart === "") {
        return `${title}${countLabel}`;
    }

    const date = new Date(dateStart);
    if (Number.isNaN(date.getTime())) {
        return `${title}${countLabel}`;
    }

    return `${title} (${date.toLocaleDateString("nl-BE")})${countLabel}`;
}

async function loadAdminActionEventUses(idEvent) {
    if (Number(idEvent) <= 0) {
        adminActionsState.items = [];
        renderAdminActionItems([]);
        return;
    }

    renderAdminActionsLoading();

    try {
        const result = await apiFetchJson("api/admin/getActionEventUses.php", {
            method: "POST",
            body: { idEvent: Number(idEvent) },
        });

        adminActionsState.items = Array.isArray(result?.items) ? result.items : [];
        hideAdminFeedback();
        renderAdminActionItems(adminActionsState.items);
    } catch (err) {
        console.error("Fout bij laden acties:", err);
        adminActionsState.items = [];
        showAdminFeedback("Kon de acties van dit event niet laden.", "danger");
        renderAdminActionsError("Kon de acties van dit event niet laden.");
    }
}

function renderAdminActionsLoading() {
    const container = document.getElementById("adminActionsContent");
    if (!container) {
        return;
    }

    container.innerHTML = "";
    const loading = document.createElement("div");
    loading.className = "text-muted";
    loading.textContent = "Acties laden...";
    container.appendChild(loading);
}

function renderAdminActionsError(message) {
    const container = document.getElementById("adminActionsContent");
    if (!container) {
        return;
    }

    container.innerHTML = "";
    const error = document.createElement("div");
    error.className = "text-danger";
    error.textContent = message;
    container.appendChild(error);
}

function renderAdminActionItems(items) {
    const container = document.getElementById("adminActionsContent");
    if (!container) {
        return;
    }

    container.innerHTML = "";

    if (!Array.isArray(items) || items.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = adminActionsState.selectedEventId > 0
            ? "Voor dit event zijn nog geen speleracties geregistreerd."
            : "Kies bovenaan een event om de geregistreerde acties te zien.";
        container.appendChild(empty);
        return;
    }

    const list = document.createElement("div");
    list.className = "admin-action-list";

    items.forEach((item) => {
        const card = document.createElement("article");
        card.className = "admin-action-card";

        const portraitWrap = document.createElement("div");
        portraitWrap.className = "admin-action-portrait";

        const portraitUrl = String(item?.character?.portraitUrl || "").trim();
        if (portraitUrl !== "") {
            const portrait = document.createElement("img");
            portrait.className = "admin-action-portrait-image";
            portrait.src = portraitUrl;
            portrait.alt = item?.character?.displayName || "Pasfoto";
            portraitWrap.appendChild(portrait);
        } else {
            const placeholder = document.createElement("div");
            placeholder.className = "admin-action-portrait-placeholder";
            placeholder.textContent = "Geen pasfoto";
            portraitWrap.appendChild(placeholder);
        }
        card.appendChild(portraitWrap);

        const body = document.createElement("div");
        body.className = "admin-action-body";

        const header = document.createElement("div");
        header.className = "admin-action-header";

        const titleWrap = document.createElement("div");

        const title = document.createElement("h3");
        title.className = "admin-action-character-name";
        title.textContent = item?.character?.displayName || `Personage #${item?.character?.idCharacter || 0}`;
        titleWrap.appendChild(title);

        const timestamp = document.createElement("div");
        timestamp.className = "admin-action-timestamp";
        timestamp.textContent = formatAdminActionTimestamp(item?.createdAt || "");
        titleWrap.appendChild(timestamp);

        header.appendChild(titleWrap);

        const buttonRow = document.createElement("div");
        buttonRow.className = "admin-action-button-row";

        const editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "btn btn-outline-secondary btn-sm";
        editButton.dataset.adminActionEdit = "true";
        editButton.dataset.idActionUse = String(item?.idActionUse || 0);
        editButton.innerHTML = '<i class="fa-solid fa-pen"></i>';
        editButton.setAttribute("aria-label", "Bewerk actie");

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "btn btn-outline-danger btn-sm";
        deleteButton.dataset.adminActionDelete = "true";
        deleteButton.dataset.idActionUse = String(item?.idActionUse || 0);
        deleteButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
        deleteButton.setAttribute("aria-label", "Verwijder actie");

        buttonRow.appendChild(editButton);
        buttonRow.appendChild(deleteButton);
        header.appendChild(buttonRow);
        body.appendChild(header);

        const badges = document.createElement("div");
        badges.className = "admin-action-badges";

        badges.appendChild(createAdminActionBadge(item?.actionLabel || item?.actionCode || "Actie"));

        if (String(item?.character?.type || "").trim() !== "") {
            badges.appendChild(createAdminActionBadge(formatAdminActionCharacterType(item.character.type)));
        }

        if (String(item?.actionSubtypeLabel || "").trim() !== "") {
            badges.appendChild(createAdminActionBadge(item.actionSubtypeLabel));
        }

        if (String(item?.skill?.name || "").trim() !== "") {
            badges.appendChild(createAdminActionBadge(item.skill.name));
        }

        body.appendChild(badges);

        const meta = document.createElement("div");
        meta.className = "admin-action-meta";

        const rollBase = item?.roll?.base;
        const rollModifier = Number(item?.roll?.modifier || 0);
        const rollFinal = item?.roll?.final;
        if (rollBase !== null || rollFinal !== null) {
            const rollLine = document.createElement("div");
            rollLine.textContent = `Worp: ${formatAdminActionRoll(rollBase, rollModifier, rollFinal)}`;
            meta.appendChild(rollLine);
        }

        const burnBefore = item?.state?.before?.psiBurn;
        const burnAfter = item?.state?.after?.psiBurn;
        if (burnBefore !== undefined || burnAfter !== undefined) {
            const burnLine = document.createElement("div");
            burnLine.textContent = `Psi burn: ${burnBefore ?? "-"} -> ${burnAfter ?? "-"}`;
            meta.appendChild(burnLine);
        }

        if (String(item?.result?.title || "").trim() !== "") {
            const resultLine = document.createElement("div");
            resultLine.textContent = `Resultaat: ${item.result.title}`;
            meta.appendChild(resultLine);
        }

        if (meta.childNodes.length > 0) {
            body.appendChild(meta);
        }

        const resultText = document.createElement("div");
        resultText.className = "admin-action-result-text";
        resultText.textContent = String(item?.result?.text || "").trim() || "Geen resultaattekst opgeslagen.";
        body.appendChild(resultText);

        card.appendChild(body);
        list.appendChild(card);
    });

    container.appendChild(list);
}

function createAdminActionBadge(label) {
    const badge = document.createElement("span");
    badge.className = "admin-action-badge";
    badge.textContent = label;
    return badge;
}

function formatAdminActionCharacterType(type) {
    const normalized = String(type || "").trim().toLowerCase();
    if (normalized === "player") {
        return "Speler";
    }
    if (normalized === "extra") {
        return "Figurant";
    }
    return normalized !== "" ? normalized : "Onbekend";
}

function formatAdminActionRoll(base, modifier, finalValue) {
    const segments = [];
    if (base !== null && base !== undefined && base !== "") {
        segments.push(String(base));
    }

    segments.push(modifier >= 0 ? `+ ${modifier}` : `- ${Math.abs(modifier)}`);

    if (finalValue !== null && finalValue !== undefined && finalValue !== "") {
        segments.push(`= ${finalValue}`);
    }

    return segments.join(" ");
}

function formatAdminActionTimestamp(value) {
    const trimmed = String(value || "").trim();
    if (trimmed === "") {
        return "Onbekend tijdstip";
    }

    const normalized = trimmed.includes("T") ? trimmed : trimmed.replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
        return trimmed;
    }

    return `${date.toLocaleDateString("nl-BE")} ${date.toLocaleTimeString("nl-BE", { hour: "2-digit", minute: "2-digit" })}`;
}

function getAdminActionItemById(idActionUse) {
    return adminActionsState.items.find(
        (item) => Number(item?.idActionUse || 0) === Number(idActionUse || 0)
    ) || null;
}

function ensureAdminActionEditModal() {
    let modalEl = document.getElementById("adminActionEditModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "adminActionEditModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Actie bewerken</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="adminActionEditForm" class="admin-action-form" novalidate>
                            <input type="hidden" id="adminActionEditId">
                            <div class="admin-action-form-note">Deze bewerking past enkel de opgeslagen actieregistratie aan. Bestaande psi burn-status wordt niet automatisch herberekend.</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="adminActionEditCharacter">Personage</label>
                                    <input type="text" class="form-control" id="adminActionEditCharacter" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="adminActionEditEvent">Event</label>
                                    <input type="text" class="form-control" id="adminActionEditEvent" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="adminActionEditSkill">Vaardigheid</label>
                                    <select class="form-select" id="adminActionEditSkill"></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="adminActionEditCreatedAt">Tijdstip</label>
                                    <input type="datetime-local" class="form-control" id="adminActionEditCreatedAt">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="adminActionEditCode">Actiecode</label>
                                    <input type="text" class="form-control" id="adminActionEditCode" maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="adminActionEditSubtype">Subtype</label>
                                    <input type="text" class="form-control" id="adminActionEditSubtype" maxlength="100">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="adminActionEditRollBase">Worp basis</label>
                                    <input type="number" class="form-control" id="adminActionEditRollBase">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="adminActionEditRollModifier">Modifier</label>
                                    <input type="number" class="form-control" id="adminActionEditRollModifier">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="adminActionEditRollFinal">Worp totaal</label>
                                    <input type="number" class="form-control" id="adminActionEditRollFinal">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="adminActionEditResultTitle">Resultaattitel</label>
                                    <input type="text" class="form-control" id="adminActionEditResultTitle" maxlength="255">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="adminActionEditResultText">Resultaattekst</label>
                                    <textarea class="form-control" id="adminActionEditResultText" rows="8"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="button" class="btn btn-primary" id="adminActionEditSaveButton">Opslaan</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);

        const saveButton = modalEl.querySelector("#adminActionEditSaveButton");
        saveButton?.addEventListener("click", () => {
            void saveAdminActionFromModal();
        });

        const form = modalEl.querySelector("#adminActionEditForm");
        form?.addEventListener("submit", (event) => {
            event.preventDefault();
            void saveAdminActionFromModal();
        });
    }

    if (!adminActionsState.modalInstance) {
        adminActionsState.modalInstance = new bootstrap.Modal(modalEl);
    }

    return modalEl;
}

function openAdminActionEditModal(idActionUse) {
    const item = getAdminActionItemById(idActionUse);
    if (!item) {
        showAdminFeedback("Kon deze actie niet terugvinden.", "danger");
        return;
    }

    const modalEl = ensureAdminActionEditModal();
    fillAdminActionSkillSelect(modalEl, Number(item?.skill?.idSkill || 0));

    const idInput = modalEl.querySelector("#adminActionEditId");
    const characterInput = modalEl.querySelector("#adminActionEditCharacter");
    const eventInput = modalEl.querySelector("#adminActionEditEvent");
    const createdAtInput = modalEl.querySelector("#adminActionEditCreatedAt");
    const codeInput = modalEl.querySelector("#adminActionEditCode");
    const subtypeInput = modalEl.querySelector("#adminActionEditSubtype");
    const rollBaseInput = modalEl.querySelector("#adminActionEditRollBase");
    const rollModifierInput = modalEl.querySelector("#adminActionEditRollModifier");
    const rollFinalInput = modalEl.querySelector("#adminActionEditRollFinal");
    const resultTitleInput = modalEl.querySelector("#adminActionEditResultTitle");
    const resultTextInput = modalEl.querySelector("#adminActionEditResultText");

    if (idInput) idInput.value = String(item?.idActionUse || 0);
    if (characterInput) characterInput.value = item?.character?.displayName || "";
    if (eventInput) eventInput.value = item?.eventTitle || "";
    if (createdAtInput) createdAtInput.value = toAdminDateTimeLocalValue(item?.createdAt || "");
    if (codeInput) codeInput.value = item?.actionCode || "";
    if (subtypeInput) subtypeInput.value = item?.actionSubtype || "";
    if (rollBaseInput) rollBaseInput.value = item?.roll?.base ?? "";
    if (rollModifierInput) rollModifierInput.value = item?.roll?.modifier ?? 0;
    if (rollFinalInput) rollFinalInput.value = item?.roll?.final ?? "";
    if (resultTitleInput) resultTitleInput.value = item?.result?.title || "";
    if (resultTextInput) resultTextInput.value = item?.result?.text || "";

    setAdminActionModalSavingState(false);
    adminActionsState.modalInstance.show();
}

function fillAdminActionSkillSelect(modalEl, selectedIdSkill) {
    const select = modalEl.querySelector("#adminActionEditSkill");
    if (!select) {
        return;
    }

    select.innerHTML = "";

    const noneOption = document.createElement("option");
    noneOption.value = "0";
    noneOption.textContent = "Geen vaardigheid";
    select.appendChild(noneOption);

    adminSkillList.forEach((skill) => {
        const option = document.createElement("option");
        option.value = String(skill.idSkill || 0);
        option.textContent = skill.name || `Vaardigheid #${skill.idSkill || 0}`;
        select.appendChild(option);
    });

    select.value = [...select.options].some((option) => option.value === String(selectedIdSkill || 0))
        ? String(selectedIdSkill || 0)
        : "0";
}

function toAdminDateTimeLocalValue(value) {
    const trimmed = String(value || "").trim();
    if (trimmed === "") {
        return "";
    }

    const normalized = trimmed.includes("T") ? trimmed : trimmed.replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
        return normalized.slice(0, 16);
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function setAdminActionModalSavingState(isSaving) {
    adminActionsState.saveInFlight = isSaving;
    const modalEl = document.getElementById("adminActionEditModal");
    if (!modalEl) {
        return;
    }

    const saveButton = modalEl.querySelector("#adminActionEditSaveButton");
    const formControls = modalEl.querySelectorAll("input, textarea, select, button");
    formControls.forEach((control) => {
        if (!(control instanceof HTMLInputElement) && !(control instanceof HTMLTextAreaElement) && !(control instanceof HTMLSelectElement) && !(control instanceof HTMLButtonElement)) {
            return;
        }

        if (control.id === "adminActionEditCharacter" || control.id === "adminActionEditEvent") {
            return;
        }

        if (control.dataset.bsDismiss === "modal") {
            control.disabled = isSaving;
            return;
        }

        control.disabled = isSaving;
    });

    if (saveButton instanceof HTMLButtonElement) {
        saveButton.textContent = isSaving ? "Opslaan..." : "Opslaan";
    }
}

async function saveAdminActionFromModal() {
    if (adminActionsState.saveInFlight) {
        return;
    }

    const modalEl = document.getElementById("adminActionEditModal");
    if (!modalEl) {
        return;
    }

    const idActionUse = Number(modalEl.querySelector("#adminActionEditId")?.value || 0);
    if (idActionUse <= 0) {
        showAdminFeedback("Geen geldige actie geselecteerd.", "danger");
        return;
    }

    const createdAt = String(modalEl.querySelector("#adminActionEditCreatedAt")?.value || "").trim();
    const actionCode = String(modalEl.querySelector("#adminActionEditCode")?.value || "").trim();
    if (createdAt === "" || actionCode === "") {
        showAdminFeedback("Actiecode en tijdstip zijn verplicht.", "danger");
        return;
    }

    setAdminActionModalSavingState(true);

    try {
        await apiFetchIdempotentJson("api/admin/updateActionUse.php", {
            method: "POST",
            body: {
                idActionUse,
                idSkill: Number(modalEl.querySelector("#adminActionEditSkill")?.value || 0),
                createdAt,
                actionCode,
                actionSubtype: String(modalEl.querySelector("#adminActionEditSubtype")?.value || "").trim(),
                rollBase: String(modalEl.querySelector("#adminActionEditRollBase")?.value || "").trim(),
                rollModifier: Number(modalEl.querySelector("#adminActionEditRollModifier")?.value || 0),
                rollFinal: String(modalEl.querySelector("#adminActionEditRollFinal")?.value || "").trim(),
                resultTitle: String(modalEl.querySelector("#adminActionEditResultTitle")?.value || "").trim(),
                resultText: String(modalEl.querySelector("#adminActionEditResultText")?.value || "").trim(),
            },
        });

        hideAdminFeedback();
        adminActionsState.modalInstance?.hide();
        await loadAdminActionEventUses(adminActionsState.selectedEventId);
        showAdminFeedback("Actie bijgewerkt.", "success");
    } catch (err) {
        console.error("Fout bij bewaren actie:", err);
        showAdminFeedback(err?.message || "Kon deze actie niet bewaren.", "danger");
    } finally {
        setAdminActionModalSavingState(false);
    }
}

async function deleteAdminActionUseRecord(idActionUse) {
    const item = getAdminActionItemById(idActionUse);
    const label = item?.character?.displayName || "dit personage";
    const confirmed = window.confirm(`Weet u zeker dat u deze actie van ${label} wilt verwijderen?`);
    if (!confirmed) {
        return;
    }

    try {
        await apiFetchIdempotentJson("api/admin/deleteActionUse.php", {
            method: "POST",
            body: { idActionUse: Number(idActionUse) },
        });

        hideAdminFeedback();
        await loadAdminActionEventUses(adminActionsState.selectedEventId);
        showAdminFeedback("Actie verwijderd.", "success");
    } catch (err) {
        console.error("Fout bij verwijderen actie:", err);
        showAdminFeedback(err?.message || "Kon deze actie niet verwijderen.", "danger");
    }
}
