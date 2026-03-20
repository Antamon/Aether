// Diary tab logic

const AETHER_DIARY_TOOLBAR = `
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="bold"><i class="fa-solid fa-bold"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="italic"><i class="fa-solid fa-italic"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertUnorderedList"><i class="fa-solid fa-list-ul"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertOrderedList"><i class="fa-solid fa-list-ol"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="p">P</button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h4">H4</button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="removeFormat"><i class="fa-solid fa-eraser"></i></button>
`;

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
    wrap.className = "mb-4 border rounded p-3 bg-white diary-entry";

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

    const viewGoals = createViewBlock("Goals", entry.goals);
    const editGoals = createRichEditor(entry.goals);

    const viewAch = createViewBlock("Achievements", entry.achievements);
    const editAch = createRichEditor(entry.achievements);

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

    wrap.appendChild(viewGoals);
    wrap.appendChild(editGoals.wrapper);
    wrap.appendChild(viewAch);
    wrap.appendChild(editAch.wrapper);
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
        editGoals.wrapper.classList.add("d-none");
        editAch.wrapper.classList.add("d-none");
        viewGoals.classList.remove("d-none");
        viewAch.classList.remove("d-none");
        Object.values(gossipInputs).forEach(({ view, input }) => {
            input.classList.add("d-none");
            view.classList.remove("d-none");
        });
        actions.classList.add("d-none");
    };

    const enterEditMode = () => {
        editGoals.wrapper.classList.toggle("d-none", !canEditAll);
        editAch.wrapper.classList.remove("d-none"); // achievements altijd in edit als allowed
        viewGoals.classList.add("d-none");
        viewAch.classList.add("d-none");
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
        editGoals.editor.innerHTML = entry.goals || "";
        editAch.editor.innerHTML = entry.achievements || "";
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
            goals: editGoals.editor.innerHTML,
            achievements: editAch.editor.innerHTML,
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
            const updated = res && res.entries ? res.entries.find(e => e.id === entry.id) : null;
            if (updated) {
                Object.assign(entry, updated);
            } else {
                entry.goals = payload.goals;
                entry.achievements = payload.achievements;
                entry.gossip1 = payload.gossip1;
                entry.gossip2 = payload.gossip2;
                entry.gossip3 = payload.gossip3;
            }
            viewGoals.querySelector(".diary-view-body").innerHTML = entry.goals || "";
            viewAch.querySelector(".diary-view-body").innerHTML = entry.achievements || "";
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

function createViewBlock(label, html) {
    const wrap = document.createElement("div");
    wrap.className = "mb-3";
    const lbl = document.createElement("div");
    lbl.className = "fw-bold";
    lbl.textContent = label;
    const body = document.createElement("div");
    body.className = "border rounded p-2 bg-light diary-view-body";
    body.innerHTML = html || `<span class="text-muted">Geen inhoud.</span>`;
    wrap.appendChild(lbl);
    wrap.appendChild(body);
    return wrap;
}

function createRichEditor(initialHtml) {
    const wrap = document.createElement("div");
    wrap.className = "mb-3 d-none";

    const toolbar = document.createElement("div");
    toolbar.className = "btn-group mb-2 flex-wrap";
    toolbar.innerHTML = AETHER_DIARY_TOOLBAR;

    const editor = document.createElement("div");
    editor.className = "form-control";
    editor.contentEditable = "true";
    editor.style.minHeight = "140px";
    editor.innerHTML = initialHtml || "";

    toolbar.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", () => {
            const cmd = btn.dataset.cmd;
            const val = btn.dataset.value || null;
            editor.focus();
            document.execCommand(cmd, false, val);
        });
    });

    wrap.appendChild(toolbar);
    wrap.appendChild(editor);

    return { wrapper: wrap, editor };
}
