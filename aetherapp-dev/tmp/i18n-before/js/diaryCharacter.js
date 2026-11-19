// Diary tab logic

function canEditDiary(character) {
    if (!currentUser || !character) return { all: false, achievementsOnly: false };
    const role = currentUser.role;
    if (role === "administrator" || role === "director") {
        return { all: true, achievementsOnly: false };
    }
    if (role === "participant") {
        if (character.type === "player" && Number(character.idUser) === Number(currentUser.id)) {
            return { all: true, achievementsOnly: false };
        }
        if (character.type === "extra" && Number(character.idUser) === Number(currentUser.id)) {
            return { all: false, achievementsOnly: true };
        }
    }
    return { all: false, achievementsOnly: false };
}

async function fetchCharacterDiary(idCharacter) {
    return apiFetchJson("api/characters/getCharacterDiary.php", {
        method: "POST",
        body: { idCharacter }
    });
}

async function saveCharacterDiary(payload) {
    return apiFetchJson("api/characters/saveCharacterDiary.php", {
        method: "POST",
        body: payload
    });
}

function showDiaryTab(character) {
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const sheetBody = document.getElementById("sheetBody");
    if (sheetBody) sheetBody.classList.remove("d-none");
    const characterForm = document.getElementById("characterForm");
    if (characterForm) characterForm.classList.add("d-none");
    const skills = document.getElementById("skills");
    if (skills) skills.classList.add("d-none");

    const bgTabHide = document.getElementById("backgroundTab");
    if (bgTabHide) bgTabHide.classList.add("d-none");
    const persTabHide = document.getElementById("personalityTab");
    if (persTabHide) persTabHide.classList.add("d-none");
    const economyTabHide = document.getElementById("economyTab");
    if (economyTabHide) economyTabHide.classList.add("d-none");
    const actionsTabHide = document.getElementById("actionsTab");
    if (actionsTabHide) actionsTabHide.classList.add("d-none");
    const passportTabHide = document.getElementById("passportTab");
    if (passportTabHide) passportTabHide.classList.add("d-none");

    const diaryTab = document.getElementById("diaryTab");
    if (!diaryTab) return;
    diaryTab.classList.remove("d-none");
    diaryTab.style.display = "block";
    diaryTab.hidden = false;
    diaryTab.classList.add("p-3");
    diaryTab.style.backgroundColor = "#fff";
    diaryTab.style.minHeight = "300px";

    const container = document.getElementById("diaryContent");
    if (!container) return;
    container.classList.remove("d-none");
    container.style.display = "block";
    container.hidden = false;
    container.innerHTML = `<div class="text-muted">Loading diary...</div>`;

    loadDiaryEntries(container, character);
}

async function loadDiaryEntries(container, character) {
    try {
        const data = await fetchCharacterDiary(character.id);
        renderDiaryEntries(container, character, data.entries || [], data.availableEvents || []);
    } catch (err) {
        console.error("Fout bij laden diary:", err);
        container.innerHTML = `<div class="text-danger">Kon diary niet laden.</div>`;
    }
}

function renderDiaryEntries(container, character, entries, availableEvents) {
    container.innerHTML = "";
    const rights = canEditDiary(character);
    const panel = document.createElement("div");
    panel.className = "aether-panel diary-panel";
    container.appendChild(panel);

    // Add new entry selector
    const addWrapper = document.createElement("div");
    addWrapper.className = "mb-4";
    const row = document.createElement("div");
    row.className = "input-group";

    const sel = document.createElement("select");
    sel.className = "form-select";
    sel.setAttribute("aria-label", "Select event");
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Select event";
    sel.appendChild(placeholder);
    sortDiaryEventsNewestFirst(availableEvents || []).forEach(ev => {
        const opt = document.createElement("option");
        opt.value = ev.id;
        opt.textContent = ev.title;
        sel.appendChild(opt);
    });

    const colBtn = document.createElement("div");
    colBtn.className = "button-group";
    const btn = AetherActionButtons.button({ icon: "fa-plus", label: "Add diary", disabled: true });
    colBtn.appendChild(btn);

    row.appendChild(sel);
    row.appendChild(colBtn);
    addWrapper.appendChild(row);

    if (rights.all || rights.achievementsOnly) {
        sel.addEventListener("change", () => { btn.disabled = !sel.value; });
        btn.addEventListener("click", async () => {
            const idEvent = sel.value ? Number(sel.value) : 0;
            if (!idEvent) return;
            btn.disabled = true;
            try {
                await saveCharacterDiary({
                    idCharacter: character.id,
                    idEvent,
                    goals: "",
                    achievements: "",
                    gossip1: "",
                    gossip2: "",
                    gossip3: ""
                });
                await loadDiaryEntries(container, character);
            } catch (err) {
                console.error("Fout bij toevoegen diary:", err);
                alert("Kon diary entry niet toevoegen.");
            } finally {
                btn.disabled = !sel.value;
            }
        });
    } else {
        btn.disabled = true;
    }

    panel.appendChild(addWrapper);
    const target = window.AETHER_EVENT_TARGET;
    const targeted = target?.kind === "gossip" && target.character === Number(character.id) && (rights.all || rights.achievementsOnly);
    if (targeted && !entries.some(entry => Number(entry.idEvent) === target.event)) {
        const event = availableEvents.find(event => Number(event.id) === target.event);
        if (event && rights.all) {
            entries = [{ idEvent: event.id, eventTitle: event.title, dateStart: event.dateStart, goals: "", achievements: "", gossip1: "", gossip2: "", gossip3: "" }, ...entries];
            sel.querySelector(`option[value="${event.id}"]`)?.remove();
        } else if (event) {
            const notice = document.createElement("p"); notice.className = "data-card";
            notice.textContent = `Gossip voor "${event.title}" is nog niet ingevuld. Alleen een admin of director kan de gossip van deze figurantenrol aanpassen.`;
            panel.appendChild(notice); window.AETHER_EVENT_TARGET = null;
            requestAnimationFrame(() => notice.scrollIntoView({ block: "center" }));
        }
    }
    if (!entries || entries.length === 0) {
        const empty = document.createElement("div");
        empty.className = "text-muted";
        empty.textContent = "No diary entries yet.";
        panel.appendChild(empty);
    } else {
        sortDiaryEventsNewestFirst(entries).forEach(entry => {
            const openGossip = targeted && Number(entry.idEvent) === target.event;
            const card = renderDiaryEntry(entry, rights, character, openGossip && rights.all);
            panel.appendChild(card);
            if (openGossip) {
                window.AETHER_EVENT_TARGET = null;
                requestAnimationFrame(() => { card.scrollIntoView({ block: "center" }); card.querySelector("textarea[data-gossip]:not(.d-none)")?.focus({ preventScroll: true }); });
            }
        });
    }
}

function sortDiaryEventsNewestFirst(events) {
    return [...events].sort((a, b) => String(b.dateStart || "").localeCompare(String(a.dateStart || ""))
        || Number(b.idEvent ?? b.id ?? 0) - Number(a.idEvent ?? a.id ?? 0));
}

function renderDiaryEntry(entry, rights, character, openGossip = false) {
    const canEditAll = rights.all;
    const canEditAchievementsOnly = rights.achievementsOnly;

    const wrap = document.createElement("div");
    wrap.className = "data-card mb-3 diary-entry";

    const header = document.createElement("div");
    header.className = "diary-entry-header mb-3";
    const title = document.createElement("h5");
    title.className = "mb-0";
    title.textContent = entry.eventTitle || "Event";
    header.appendChild(title);

    let editBtn = null;
    if (canEditAll || canEditAchievementsOnly) {
        editBtn = AetherActionButtons.button({ icon: "fa-pen-nib", label: "Edit" });
    }

    wrap.appendChild(header);

    const goalsSection = createDiarySection("Goals", entry.goals);
    const achievementsSection = createDiarySection("Achievements", entry.achievements);

    const gossipRow = document.createElement("div");
    gossipRow.className = "row g-2";
    const gossips = [
        { label: "Gossip 1", key: "gossip1" },
        { label: "Gossip 2", key: "gossip2" },
        { label: "Gossip 3", key: "gossip3" }
    ];
    const gossipInputs = {};
    gossips.forEach(g => {
        const col = document.createElement("div");
        col.className = "col-lg-4 col-12";
        const lbl = document.createElement("div");
        lbl.className = "fw-bold";
        lbl.textContent = g.label;
        const view = document.createElement("div");
        view.className = "diary-view-body";
        view.textContent = entry[g.key] || "";
        const ta = document.createElement("textarea");
        ta.dataset.gossip = g.key;
        ta.className = "form-control form-control-sm d-none";
        ta.value = entry[g.key] || "";
        gossipInputs[g.key] = { view, input: ta };
        col.appendChild(lbl);
        col.appendChild(view);
        col.appendChild(ta);
        gossipRow.appendChild(col);
    });

    wrap.appendChild(goalsSection.wrapper);
    wrap.appendChild(achievementsSection.wrapper);
    wrap.appendChild(gossipRow);

    const actions = document.createElement("div");
    actions.className = "button-group";
    const btnSave = AetherActionButtons.button({ icon: "fa-check", label: "Save" });
    const btnCancel = AetherActionButtons.button({ icon: "fa-xmark", label: "Cancel" });
    if (editBtn) header.appendChild(actions);

    const exitEditMode = () => {
        goalsSection.edit.wrapper.classList.add("d-none");
        achievementsSection.edit.wrapper.classList.add("d-none");
        goalsSection.view.classList.remove("d-none");
        achievementsSection.view.classList.remove("d-none");
        Object.values(gossipInputs).forEach(({ view, input }) => {
            input.classList.add("d-none");
            view.classList.remove("d-none");
        });
        wrap.classList.remove("is-editing");
        actions.replaceChildren(...(editBtn ? [editBtn] : []));
    };

    const enterEditMode = () => {
        goalsSection.edit.wrapper.classList.toggle("d-none", !canEditAll);
        goalsSection.view.classList.toggle("d-none", canEditAll);
        achievementsSection.edit.wrapper.classList.remove("d-none"); // achievements altijd in edit als allowed
        achievementsSection.view.classList.add("d-none");
        Object.values(gossipInputs).forEach(({ view, input }) => {
            const canEditGossip = canEditAll;
            input.classList.toggle("d-none", !canEditGossip);
            view.classList.toggle("d-none", canEditGossip);
        });
        wrap.classList.add("is-editing");
        actions.replaceChildren(btnSave, btnCancel);
    };

    if (editBtn) {
        editBtn.addEventListener("click", enterEditMode);
    }

    btnCancel.addEventListener("click", () => {
        if (!entry.id) { void loadDiaryEntries(document.getElementById("diaryContent"), character); return; }
        goalsSection.edit.setHtml(entry.goals || "");
        achievementsSection.edit.setHtml(entry.achievements || "");
        Object.entries(gossipInputs).forEach(([key, obj]) => {
            obj.input.value = entry[key] || "";
        });
        exitEditMode();
    });

    btnSave.addEventListener("click", async () => {
        const payload = {
            idCharacter: character.id,
            idDiary: entry.id,
            idEvent: entry.idEvent,
            goals: goalsSection.edit.getHtml(),
            achievements: achievementsSection.edit.getHtml(),
            gossip1: gossipInputs.gossip1?.input.value || "",
            gossip2: gossipInputs.gossip2?.input.value || "",
            gossip3: gossipInputs.gossip3?.input.value || ""
        };

        // Filter fields if only achievements allowed
        if (canEditAchievementsOnly && !canEditAll) {
            payload.goals = entry.goals || "";
            payload.gossip1 = entry.gossip1 || "";
            payload.gossip2 = entry.gossip2 || "";
            payload.gossip3 = entry.gossip3 || "";
        }

        btnSave.disabled = true;
        btnCancel.disabled = true;
        try {
            const res = await saveCharacterDiary(payload);
            const updated = Array.isArray(res?.entries)
                ? res.entries.find(item => entry.id ? Number(item.id) === Number(entry.id) : Number(item.idEvent) === Number(entry.idEvent))
                : null;
            if (!updated) {
                throw new Error("De server gaf geen gesaniteerde diary terug.");
            }
            Object.assign(entry, updated);
            goalsSection.edit.setHtml(entry.goals);
            achievementsSection.edit.setHtml(entry.achievements);
            renderCharacterRichText(goalsSection.view, entry.goals);
            renderCharacterRichText(achievementsSection.view, entry.achievements);
            gossipInputs.gossip1.view.textContent = entry.gossip1 || "";
            gossipInputs.gossip2.view.textContent = entry.gossip2 || "";
            gossipInputs.gossip3.view.textContent = entry.gossip3 || "";
            exitEditMode();
        } catch (err) {
            console.error("Fout bij opslaan diary:", err);
            alert("Opslaan van diary mislukt.");
        } finally {
            btnSave.disabled = false;
            btnCancel.disabled = false;
        }
    });

    // default view mode
    exitEditMode();
    if (openGossip && canEditAll) enterEditMode();

    return wrap;
}

function createDiarySection(label, html) {
    const wrap = document.createElement("div");
    wrap.className = "mb-3";

    const lbl = document.createElement("div");
    lbl.className = "fw-bold";
    lbl.textContent = label;

    const body = document.createElement("div");
    body.className = "diary-view-body";
    renderCharacterRichText(body, html);

    const edit = createCharacterRichTextEditor(html, { minHeight: "140px" });
    edit.wrapper.classList.add("mb-3", "d-none");

    wrap.appendChild(lbl);
    wrap.appendChild(body);
    wrap.appendChild(edit.wrapper);

    return {
        wrapper: wrap,
        label: lbl,
        view: body,
        edit
    };
}
