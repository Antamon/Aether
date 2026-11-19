const eventDetailsState = { events: [], selectedId: 0, request: 0, participationRequest: 0, modalRequest: 0, editing: false };
const eventNode = (tag, className = "", text) => {
    const node = document.createElement(tag); node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

function showEventPageView(detail) {
    document.getElementById("eventParticipationView").classList.toggle("d-none", detail);
    document.getElementById("eventDetailsView").classList.toggle("d-none", !detail);
    document.getElementById("eventFeedback").classList.add("d-none");
}

function eventDetailError(error) {
    const host = document.getElementById("eventFeedback");
    let message = error.message || "Kon eventgegevens niet verwerken.";
    try { const response = JSON.parse(error.responseBody); message = response.validationErrors?.join(" ") || response.error || message; } catch (_) {}
    host.textContent = message; host.classList.remove("d-none");
    console.error(error);
}

function mayLeaveEventDetails() {
    return !eventDetailsState.editing || window.confirm("Niet bewaarde eventgegevens verlaten?");
}

async function refreshEventSidebar() {
    eventDetailsState.events = await apiFetchJson("api/events/getEventList.php", { method: "POST", body: {} });
    window.renderEventSidebarCards(eventDetailsState.events, document.getElementById("eventSidebarList"),
        event => { void openEventDetails(event.id); }, eventDetailsState.selectedId);
}

function setupEventDetailUI() {
    document.getElementById("eventCreateActions").classList.remove("d-none");
    document.getElementById("eventCreateButton").addEventListener("click", () => {
        if (!mayLeaveEventDetails()) return;
        ++eventDetailsState.request; eventDetailsState.selectedId = 0;
        showEventPageView(true);
        renderEventDetails({ event: { type: "weekend", ep: 0 }, characters: [], canManage: true }, true);
        window.closeEventSidebar?.();
    });
}

async function openEventDetails(id) {
    if (!mayLeaveEventDetails()) return;
    const request = ++eventDetailsState.request;
    try {
        const data = await apiFetchJson("api/events/eventDetails.php", { method: "POST", body: { idEvent: Number(id) } });
        if (request !== eventDetailsState.request) return;
        eventDetailsState.selectedId = Number(id); eventDetailsState.editing = false;
        showEventPageView(true); renderEventDetails(data);
        const select = document.getElementById("eventParticipantSelect");
        if (select) select.value = "";
        window.renderEventSidebarCards(eventDetailsState.events, document.getElementById("eventSidebarList"),
            event => { void openEventDetails(event.id); }, id);
        window.closeEventSidebar?.();
    } catch (error) { if (request === eventDetailsState.request) eventDetailError(error); }
}

function renderEventDetails(data, edit = false) {
    const host = document.getElementById("eventDetailsView"); host.replaceChildren();
    eventDetailsState.editing = edit;
    const event = data.event;
    const card = eventNode("section", "aether-panel mb-4");
    const header = eventNode("div", "d-flex justify-content-between align-items-start gap-3 mb-3");
    header.append(eventNode("h1", "h3", event.title || "Nieuw event")); card.append(header);
    const layout = eventNode("div", "event-detail-layout");
    const media = eventNode("div");
    const frame = eventNode("div", "event-image-frame");
    function showImage() {
        frame.replaceChildren();
        if (event.imageUrl) { const image = eventNode("img"); image.src = event.imageUrl; image.alt = event.title || "Eventafbeelding"; frame.append(image); }
        else frame.append(eventNode("span", "text-muted", "Nog geen afbeelding"));
    }
    showImage(); media.append(frame); layout.append(media);
    const fields = eventNode("div", "event-detail-fields"); const inputs = {};
    for (const [key, title, type, limit] of [
        ["title", "Titel", "text", 50], ["type", "Type", "select"], ["dateStart", "Begindatum", "date"],
        ["dateEnd", "Einddatum", "date"], ["venue", "Locatie", "text", 50],
        ["ep", "Ervaringspunten", "number"], ["description", "Beschrijving", "textarea", 255]
    ]) {
        if (edit) {
            const label = eventNode("label", "form-label", title);
            const input = eventNode(type === "textarea" ? "textarea" : type === "select" ? "select" : "input", type === "select" ? "form-select" : "form-control");
            if (type === "select") for (const value of ["weekend", "mini"]) { const option = eventNode("option", "", value); option.value = value; input.append(option); }
            else if (type !== "textarea") input.type = type;
            if (limit) input.maxLength = limit;
            if (key === "ep") { input.min = "0"; input.step = "1"; }
            input.value = String(event[key] ?? ""); inputs[key] = input; label.append(input); fields.append(label);
        } else {
            const row = eventNode("div"); row.append(eventNode("strong", "d-block", title), eventNode("span", "event-detail-value", event[key] ?? "—")); fields.append(row);
        }
    }
    layout.append(fields); card.append(layout); host.append(card);
    if (data.canManage && !edit) header.append(AetherActionButtons.group([AetherActionButtons.button({ icon: "fa-pen-nib", label: "Edit", onClick: () => renderEventDetails(data, true) })]));
    if (data.canManage && edit) {
        const save = AetherActionButtons.button({ icon: "fa-check", label: "Save" });
        const cancel = AetherActionButtons.button({ icon: "fa-xmark", label: "Cancel", onClick: () => {
            if (event.id) renderEventDetails(data);
            else { eventDetailsState.editing = false; showEventPageView(false); }
        } });
        header.append(AetherActionButtons.group([save, cancel]));
        save.addEventListener("click", async () => {
            const request = eventDetailsState.request;
            const body = Object.fromEntries(Object.entries(inputs).map(([key, input]) => [key, key === "ep" ? Number(input.value) : input.value]));
            if (!body.title.trim() || !body.dateStart || !body.dateEnd || !Number.isSafeInteger(body.ep) || body.ep < 0) { eventDetailError(new Error("Controleer de titel, datums en ervaringspunten.")); return; }
            save.disabled = true; cancel.disabled = true;
            try {
                const result = await apiFetchJson(`api/events/${event.id ? "updateEvent" : "newEvent"}.php`, { method: "POST", body: { ...body, ...(event.id ? { id: event.id } : {}) } });
                if (request !== eventDetailsState.request) return;
                eventDetailsState.editing = false;
                await refreshEventSidebar(); await openEventDetails(event.id || Number(result));
            } catch (error) { eventDetailError(error); save.disabled = false; cancel.disabled = false; }
        });
        if (event.id) {
            const upload = eventNode("input", "d-none"); upload.type = "file"; upload.accept = "image/png,image/jpeg,image/webp,image/gif";
            const uploadButton = AetherActionButtons.button({ icon: "fa-upload", label: "Opladen", onClick: () => upload.click() });
            const deleteButton = AetherActionButtons.button({ icon: "fa-trash-can", label: "Verwijderen", disabled: !event.imageUrl });
            const group = AetherActionButtons.group([uploadButton, deleteButton]); group.classList.add("mt-2"); media.append(group, upload);
            async function updateImage(action, file) {
                uploadButton.disabled = true; deleteButton.disabled = true;
                save.disabled = true; cancel.disabled = true;
                try {
                    let result;
                    if (file) {
                        const body = new FormData(); body.append("action", action); body.append("idEvent", event.id); body.append("image", file);
                        const response = await fetch("api/events/eventDetails.php", { method: "POST", credentials: "same-origin", headers: { "X-CSRF-Token": window.AETHER_CSRF_TOKEN || "" }, body });
                        if (!response.ok) { const error = new Error("Upload mislukt"); error.responseBody = await response.text(); throw error; }
                        result = await response.json();
                    } else result = await apiFetchJson("api/events/eventDetails.php", { method: "POST", body: { action, idEvent: event.id } });
                    event.imageUrl = result.imageUrl; showImage(); await refreshEventSidebar();
                } catch (error) { eventDetailError(error); }
                finally { uploadButton.disabled = false; deleteButton.disabled = !event.imageUrl; save.disabled = false; cancel.disabled = false; upload.value = ""; }
            }
            upload.addEventListener("change", () => { if (upload.files[0]) void updateImage("upload", upload.files[0]); });
            deleteButton.addEventListener("click", () => { if (window.confirm("Eventafbeelding verwijderen?")) void updateImage("deleteImage"); });
            media.append(eventNode("p", "small text-muted mt-2", "Afbeeldingen worden meteen bewaard."));
        } else media.append(eventNode("p", "small text-muted mt-2", "Bewaar het event om een afbeelding op te laden."));
    }
    if (event.id) renderEventCharacters(card, data);
}

async function showEventCharacterModal(idEvent, idCharacter, kind, button) {
    const request = eventDetailsState.request;
    const modalRequest = ++eventDetailsState.modalRequest;
    button.disabled = true;
    try {
        const data = await apiFetchJson("api/events/eventDetails.php", { method: "POST", body: { action: kind, idEvent, idCharacter } });
        if (request !== eventDetailsState.request || modalRequest !== eventDetailsState.modalRequest) return;
        const modal = eventNode("div", "modal fade"); modal.tabIndex = -1;
        modal.setAttribute("aria-labelledby", "eventCharacterModalTitle");
        const dialog = eventNode("div", "modal-dialog modal-lg modal-dialog-scrollable");
        const content = eventNode("div", "modal-content"); const header = eventNode("div", "modal-header");
        const title = eventNode("h2", "modal-title h5", `${kind === "gossip" ? "Gossip" : "Snapshot"} — ${[data.identity.firstName, data.identity.lastName].filter(Boolean).join(" ")}`);
        title.id = "eventCharacterModalTitle"; header.append(title);
        const body = eventNode("div", "modal-body"); body.append(eventNode("p", "fw-semibold", data.identity.eventTitle));
        if (data.message) body.append(eventNode("p", "event-character-modal-message", data.message));
        const fields = !data.details ? [] : kind === "gossip" ? [["gossip1", "Gossip 1"], ["gossip2", "Gossip 2"], ["gossip3", "Gossip 3"]] : [
            ["transactionDate", "Datum"], ["amount", "Inkomsten", "money"], ["securitiesBalanceSnapshot", "Saldo effectenportefeuille", "money"],
            ["securitiesManagerType", "Beheer"], ["securitiesRiskProfile", "Risicoprofiel"], ["securitiesManagerSkillLevel", "Vaardigheidsniveau beheerder"],
            ["securitiesBasePercentage", "Basisrendement", "percent"], ["securitiesVariationLimitPercentage", "Maximale afwijking", "percent"],
            ["securitiesVariationPercentage", "Afwijking", "percent"], ["securitiesReturnPercentage", "Rendement", "percent"],
            ["securitiesReturnAmount", "Rendementbedrag", "money"], ["securitiesStatus", "Status effectenrendement"]
        ];
        for (const [key, label, format] of fields) {
            const value = data.details[key];
            let text = value == null ? "—" : String(value);
            if (value != null && format) text = new Intl.NumberFormat("nl-BE", { maximumFractionDigits: 2, minimumFractionDigits: format === "money" ? 2 : 0 }).format(Number(value)) + (format === "money" ? " Fr" : "%");
            const section = eventNode("div", "mb-3"); section.append(eventNode("strong", "d-block", label), eventNode("div", "event-character-modal-value", text)); body.append(section);
        }
        const close = AetherActionButtons.button({ icon: "fa-xmark", label: "Sluiten" }); close.dataset.bsDismiss = "modal";
        const footer = eventNode("div", "modal-footer"); footer.append(AetherActionButtons.group([close]));
        content.append(header, body, footer); dialog.append(content); modal.append(dialog); document.body.append(modal);
        const instance = new bootstrap.Modal(modal);
        modal.addEventListener("hidden.bs.modal", () => { instance.dispose(); modal.remove(); if (button.isConnected) button.focus({ preventScroll: true }); }, { once: true }); instance.show();
    } catch (error) { if (request === eventDetailsState.request) eventDetailError(error); }
    finally { button.disabled = false; }
}

function renderEventCharacters(host, data) {
    for (const [type, title] of [["player", "Spelerspersonages"], ["extra", "Figurantenrollen"]]) {
        if (type === "extra" && !data.canManage && !data.characters.some(character => character.type === "extra" && character.linked && character.canViewStatus)) continue;
        const entries = data.characters.filter(character => character.type === type);
        const linked = entries.filter(character => character.linked);
        const cards = new Map();
        const panel = eventNode("section", "event-character-panel mt-4");
        const accordion = eventNode("div", "accordion event-character-accordion"); const item = eventNode("div", "accordion-item");
        const heading = eventNode("h2", "accordion-header"); const trigger = eventNode("button", "accordion-button collapsed", `${title} (${linked.length})`);
        trigger.type = "button"; trigger.dataset.bsToggle = "collapse";
        const collapseId = `event-${data.event.id}-${type}`;
        trigger.dataset.bsTarget = `#${collapseId}`; trigger.setAttribute("aria-expanded", "false"); trigger.setAttribute("aria-controls", collapseId);
        heading.append(trigger); item.append(heading);
        const collapse = eventNode("div", "accordion-collapse collapse"); collapse.id = collapseId;
        const body = eventNode("div", "accordion-body");
        const grid = eventNode("div", "event-character-grid");
        if (!linked.length) grid.append(eventNode("p", "text-muted", "Nog geen personages gekoppeld."));
        function buildCard(character) {
            const row = eventNode("div", "event-character-card");
            const canOpen = data.canManage || character.canOpen === true;
            const name = [character.firstName, character.lastName].filter(Boolean).join(" ");
            const portrait = eventNode(canOpen ? "a" : "div", "event-character-link");
            if (canOpen) { portrait.href = `index.html?character=${character.id}`; portrait.setAttribute("aria-label", `Open ${name}`); }
            const frame = eventNode("span", "event-character-portrait");
            if (character.portraitUrl) {
                const image = eventNode("img"); image.src = character.portraitUrl; image.alt = ""; image.loading = "lazy";
                image.addEventListener("error", () => { frame.replaceChildren(eventNode("span", "", "Geen portret")); }, { once: true });
                frame.append(image);
            } else frame.append(eventNode("span", "", "Geen portret"));
            portrait.append(frame, eventNode("span", "event-character-name", name)); row.append(portrait);
            if (data.canManage || character.canViewStatus) {
                const icons = eventNode("div", "event-character-icons");
                for (const [icon, complete, label] of [
                    ["fa-camera", character.hasSnapshot, character.hasSnapshot ? "Snapshot voor dit event aanwezig" : "Snapshot voor dit event ontbreekt"],
                    ["fa-comment", character.gossipComplete, character.gossipComplete ? "Drie gossipniveaus ingevuld" : "Nog geen drie gossipniveaus ingevuld"]
                ]) {
                    const status = eventNode("button", `event-character-status${complete ? " is-complete" : ""}`);
                    status.type = "button"; status.setAttribute("aria-label", label); status.title = label;
                    status.addEventListener("click", () => {
                        const kind = icon === "fa-camera" ? "snapshot" : "gossip";
                        if (complete || (kind === "snapshot" && !data.canManage)) void showEventCharacterModal(data.event.id, character.id, kind, status);
                        else if (mayLeaveEventDetails()) window.location.href = `index.html?character=${character.id}&tab=${kind === "snapshot" ? "economy" : "diary"}&event=${data.event.id}&focus=${kind}`;
                    });
                    const symbol = eventNode("i", `fa-solid ${icon}`); symbol.setAttribute("aria-hidden", "true"); status.append(symbol); icons.append(status);
                }
                if (data.canManage) {
                    const remove = eventNode("button", "event-character-remove"); remove.type = "button";
                    remove.setAttribute("aria-label", `${name} van dit event verwijderen`); remove.title = "Van dit event verwijderen";
                    const trash = eventNode("i", "fa-solid fa-trash-can"); trash.setAttribute("aria-hidden", "true"); remove.append(trash);
                    remove.addEventListener("click", () => { void setLink(character.id, false); }); icons.append(remove);
                }
                row.append(icons);
            }
            return row;
        }
        for (const character of linked) { const row = buildCard(character); cards.set(Number(character.id), row); grid.append(row); }
        body.append(grid);
        let select, add;
        function updateChoices() {
            if (!select) return;
            const blank = eventNode("option", "", "Kies een personage"); blank.value = ""; select.replaceChildren(blank); select.value = "";
            for (const character of entries.filter(character => !character.linked && (data.canManage || character.canLink))) { const option = eventNode("option", "", [character.firstName, character.lastName].filter(Boolean).join(" ")); option.value = character.id; select.append(option); }
            add.disabled = !entries.some(character => !character.linked && (data.canManage || character.canLink));
        }
        if (data.canManage || entries.some(character => character.canLink)) {
            select = eventNode("select", "form-select"); select.setAttribute("aria-label", `${title} koppelen`);
            add = AetherActionButtons.button({ icon: "fa-plus", label: "Koppelen", onClick: () => { if (select.value) void setLink(Number(select.value), true); } });
            updateChoices();
            const group = eventNode("div", "input-group mt-3"); group.append(select, AetherActionButtons.group([add])); body.append(group);
        }
        async function setLink(id, linkedState) {
            const request = eventDetailsState.request;
            const buttons = body.querySelectorAll("button"); buttons.forEach(button => { button.disabled = true; });
            select.disabled = true;
            try {
                await apiFetchJson("api/events/eventDetails.php", { method: "POST", body: { action: "link", idEvent: data.event.id, idCharacter: id, linked: linkedState } });
                if (request !== eventDetailsState.request) return;
                data.characters.find(character => Number(character.id) === Number(id)).linked = linkedState;
                // Keep accordion, editor, and unaffected portrait nodes in place.
                const x = window.scrollX, y = window.scrollY;
                if (linkedState && !cards.has(Number(id))) {
                    cards.set(Number(id), buildCard(entries.find(character => Number(character.id) === Number(id))));
                }
                const current = entries.filter(character => character.linked);
                grid.replaceChildren(...current.map(character => cards.get(Number(character.id))));
                if (!current.length) grid.append(eventNode("p", "text-muted", "Nog geen personages gekoppeld."));
                trigger.textContent = `${title} (${current.length})`;
                window.scrollTo?.({ left: x, top: y, behavior: "instant" });
            } catch (error) { if (request === eventDetailsState.request) eventDetailError(error); }
            finally { buttons.forEach(button => { button.disabled = false; }); select.disabled = false; updateChoices(); }
        }
        collapse.append(body); item.append(collapse); accordion.append(item); panel.append(accordion); host.append(panel);
    }
}
