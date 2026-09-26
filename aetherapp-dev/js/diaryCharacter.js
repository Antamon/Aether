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

    if (!entries || entries.length === 0) {
        const empty = document.createElement("div");
        empty.className = "text-muted mb-3";
        empty.textContent = "No diary entries yet.";
        container.appendChild(empty);
    } else {
        entries.forEach(entry => {
            container.appendChild(renderDiaryEntry(entry, rights, character));
        });
    }

    // Add new entry selector
    const addWrapper = document.createElement("div");
    addWrapper.className = "border-top pt-3 mt-3";
    const row = document.createElement("div");
    row.className = "row g-2 align-items-center";

    const colSelect = document.createElement("div");
    colSelect.className = "col-lg-8 col-12";
    const sel = document.createElement("select");
    sel.className = "form-select";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Select event";
    sel.appendChild(placeholder);
    (availableEvents || []).forEach(ev => {
        const opt = document.createElement("option");
        opt.value = ev.id;
        opt.textContent = ev.title;
        sel.appendChild(opt);
    });
    colSelect.appendChild(sel);

    const colBtn = document.createElement("div");
    colBtn.className = "col-lg-4 col-12";
    const btn = document.createElement("button");
    btn.className = "btn btn-primary w-100";
    btn.textContent = "Add diary";
    colBtn.appendChild(btn);

    row.appendChild(colSelect);
    row.appendChild(colBtn);
    addWrapper.appendChild(row);

    if (rights.all || rights.achievementsOnly) {
        btn.addEventListener("click", async () => {
            const idEvent = sel.value ? Number(sel.value) : 0;
            if (!idEvent) return;
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
            }
        });
    } else {
        btn.disabled = true;
    }

    container.appendChild(addWrapper);
}

function renderDiaryEntry(entry, rights, character) {
    const canEditAll = rights.all;
    const canEditAchievementsOnly = rights.achievementsOnly;

    const wrap = document.createElement("div");
    wrap.className = "aether-panel mb-4 diary-entry";

    const header = document.createElement("div");
    header.className = "d-flex align-items-center justify-content-center mb-2 position-relative";
    const title = document.createElement("h5");
    title.className = "mb-0 text-center";
    title.textContent = entry.eventTitle || "Event";
    header.appendChild(title);

    let editBtn = null;
    if (canEditAll || canEditAchievementsOnly) {
        editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "btn btn-link p-0 position-absolute end-0 top-0";
        editBtn.innerHTML = `<i class="fa-solid fa-pen"></i>`;
        header.appendChild(editBtn);
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
        view.className = "border rounded p-2 bg-light";
        view.textContent = entry[g.key] || "";
        const ta = document.createElement("textarea");
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
    actions.className = "mt-3 d-none gap-2";
    const btnSave = document.createElement("button");
    btnSave.className = "btn btn-sm btn-primary";
    btnSave.textContent = "Save";
    const btnCancel = document.createElement("button");
    btnCancel.className = "btn btn-sm btn-outline-secondary";
    btnCancel.textContent = "Cancel";
    actions.appendChild(btnSave);
    actions.appendChild(btnCancel);
    wrap.appendChild(actions);

    const exitEditMode = () => {
        goalsSection.edit.wrapper.classList.add("d-none");
        achievementsSection.edit.wrapper.classList.add("d-none");
        goalsSection.view.classList.remove("d-none");
        achievementsSection.view.classList.remove("d-none");
        Object.values(gossipInputs).forEach(({ view, input }) => {
            input.classList.add("d-none");
            view.classList.remove("d-none");
        });
        actions.classList.add("d-none");
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
        actions.classList.remove("d-none");
    };

    if (editBtn) {
        editBtn.addEventListener("click", enterEditMode);
    }

    btnCancel.addEventListener("click", () => {
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

        try {
            const res = await saveCharacterDiary(payload);
            const updated = Array.isArray(res?.entries)
                ? res.entries.find(item => Number(item.id) === Number(entry.id))
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
        }
    });

    // default view mode
    exitEditMode();

    return wrap;
}

function createDiarySection(label, html) {
    const wrap = document.createElement("div");
    wrap.className = "mb-3";

    const lbl = document.createElement("div");
    lbl.className = "fw-bold";
    lbl.textContent = label;

    const body = document.createElement("div");
    body.className = "border rounded p-2 bg-light diary-view-body";
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
