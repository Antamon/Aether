// Background tab logic (personal background & knowledge)

const AETHER_BACKGROUND_SECTIONS = {
    personal_background: {
        title: "Personal background",
        maxLength: 10000
    },
    knowledge: {
        title: "Knowledge",
        maxLength: 10000
    }
};

const AETHER_PERSONALITY_SECTIONS = {
    nature: {
        title: "Nature",
        maxLength: 1000
    },
    demeanour: {
        title: "Demeanour",
        maxLength: 1000
    }
};

const AETHER_TIE_TYPES = [
    { value: "superior", label: "Superior" },
    { value: "dependent", label: "Dependent" },
    { value: "spouse", label: "Spouse" },
    { value: "ally", label: "Ally" },
    { value: "adversary", label: "Adversary" },
    { value: "person_of_interest", label: "Person of interest" }
];

function canEditCharacterContent(character) {
    if (!currentUser || !character) return false;
    const role = currentUser.role;
    if (role === "administrator" || role === "director") return true;
    if (role === "participant") {
        return character.type === "player" && Number(character.idUser) === Number(currentUser.id);
    }
    return false;
}

async function fetchCharacterSections(idCharacter) {
    return apiFetchJson("api/characters/getCharacterSections.php", {
        method: "POST",
        body: { idCharacter }
    });
}

async function fetchCharacterTies(idCharacter) {
    return apiFetchJson("api/characters/getCharacterTies.php", {
        method: "POST",
        body: { idCharacter }
    });
}

async function fetchTieOptions() {
    return apiFetchJson("api/characters/getCharacterTieOptions.php", {
        method: "GET"
    });
}

async function saveCharacterTie(payload) {
    return apiFetchJson("api/characters/saveCharacterTie.php", {
        method: "POST",
        body: payload
    });
}

async function saveCharacterSection(idCharacter, section, content) {
    return apiFetchJson("api/characters/saveCharacterSection.php", {
        method: "POST",
        body: { idCharacter, section, content }
    });
}

function showSheetTab() {
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const bgTab = document.getElementById("backgroundTab");
    if (bgTab) bgTab.classList.add("d-none");
    const persTab = document.getElementById("personalityTab");
    if (persTab) persTab.classList.add("d-none");
    const characterForm = document.getElementById("characterForm");
    if (characterForm) characterForm.classList.remove("d-none");
    const skills = document.getElementById("skills");
    if (skills) skills.classList.remove("d-none");
}

async function showBackgroundTab(character) {
    // Laat de hoofd-row zichtbaar; we verbergen alleen de inhoud (form/skills).
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const sheetBody = document.getElementById("sheetBody");
    if (sheetBody) sheetBody.classList.remove("d-none");
    const characterForm = document.getElementById("characterForm");
    if (characterForm) characterForm.classList.add("d-none");
    const skills = document.getElementById("skills");
    if (skills) skills.classList.add("d-none");

    // Andere tab verbergen
    const persTabHide = document.getElementById("personalityTab");
    if (persTabHide) persTabHide.classList.add("d-none");

    const bgTab = document.getElementById("backgroundTab");
    if (!bgTab) return;
    bgTab.classList.remove("d-none");
    bgTab.style.display = "block";
    bgTab.hidden = false;
    bgTab.classList.add("p-3");
    bgTab.style.backgroundColor = "#fff";
    bgTab.style.minHeight = "300px";
    bgTab.style.position = "relative";
    bgTab.style.zIndex = "1";
    bgTab.style.clear = "both";

    const container = document.getElementById("backgroundContent");
    if (!container) return;
    container.classList.remove("d-none");
    container.style.display = "block";
    container.hidden = false;
    container.style.minHeight = "400px";
    container.style.border = "1px dashed #ccc";
    container.style.visibility = "visible";
    container.style.overflow = "visible";
    container.style.padding = "0";
    container.style.height = "auto";

    container.innerHTML = `<div class="text-muted">Loading background...</div>`;
    await loadAndRenderSections(container, character, AETHER_BACKGROUND_SECTIONS, "background");
}

async function showPersonalityTab(character) {
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const sheetBody = document.getElementById("sheetBody");
    if (sheetBody) sheetBody.classList.remove("d-none");
    const characterForm = document.getElementById("characterForm");
    if (characterForm) characterForm.classList.add("d-none");
    const skills = document.getElementById("skills");
    if (skills) skills.classList.add("d-none");

    // Andere tab verbergen
    const bgTabHide = document.getElementById("backgroundTab");
    if (bgTabHide) bgTabHide.classList.add("d-none");

    const persTab = document.getElementById("personalityTab");
    if (!persTab) return;
    persTab.classList.remove("d-none");
    persTab.style.display = "block";
    persTab.hidden = false;
    persTab.classList.add("p-3");
    persTab.style.backgroundColor = "#fff";
    persTab.style.minHeight = "300px";
    persTab.style.position = "relative";
    persTab.style.zIndex = "1";
    persTab.style.clear = "both";

    const container = document.getElementById("personalityContent");
    if (!container) return;
    container.classList.remove("d-none");
    container.style.display = "block";
    container.hidden = false;
    container.style.minHeight = "400px";
    container.style.border = "1px dashed #ccc";
    container.style.visibility = "visible";
    container.style.overflow = "visible";
    container.style.padding = "0";
    container.style.height = "auto";

    container.innerHTML = `<div class="text-muted">Loading personality...</div>`;
    await loadAndRenderSections(container, character, AETHER_PERSONALITY_SECTIONS, "personality");
}

async function loadAndRenderSections(container, character, metaMap, tabName) {
    try {
        const data = await fetchCharacterSections(character.id);
        console.log(`${tabName === "background" ? "Background" : "Personality"} sections:`, data);
        renderSections(container, character, data || {}, metaMap, tabName);
        container.parentElement?.scrollIntoView({ behavior: "smooth", block: "start" });
        // Enkel bij background: ties laden en renderen
        if (tabName === "background") {
            await renderTies(container, character);
        }
    } catch (err) {
        console.error(`Fout bij laden ${tabName}:`, err);
        container.innerHTML = `<div class="text-danger">Kon ${tabName} niet laden.</div>`;
        renderSections(container, character, {}, metaMap, tabName);
    }
}

function renderSections(container, character, sections, metaMap, tabName) {
    container.style.display = "block";
    container.hidden = false;
    container.innerHTML = "";
    const canEdit = canEditCharacterContent(character);

    Object.entries(metaMap).forEach(([key, meta]) => {
        const block = renderSectionBlock(character, key, meta.title, sections[key] || "", canEdit);
        container.appendChild(block);
    });

    console.log("Background blocks rendered:", container.children.length);
    console.log("Background container height:", container.clientHeight);
    const csTab = getComputedStyle(tabName === "background" ? document.getElementById("backgroundTab") : document.getElementById("personalityTab"));
    const csCont = getComputedStyle(container);
    console.log("backgroundTab style:", { display: csTab.display, height: csTab.height });
    console.log("backgroundContent style:", { display: csCont.display, height: csCont.height });
    console.log("backgroundContent innerHTML:", container.innerHTML);

    // Force height based on content in case parent collapse
    container.style.height = container.scrollHeight + "px";
    const bgTab = tabName === "background" ? document.getElementById("backgroundTab") : document.getElementById("personalityTab");
    if (bgTab) {
        bgTab.style.height = "auto";
    }

    setTimeout(() => {
        const csTab2 = getComputedStyle(tabName === "background" ? document.getElementById("backgroundTab") : document.getElementById("personalityTab"));
        const csCont2 = getComputedStyle(container);
        console.log("backgroundTab style (after timeout):", { display: csTab2.display, height: csTab2.height });
        console.log("backgroundContent style (after timeout):", { display: csCont2.display, height: csCont2.height });
        // Log parent chain for visibility/debug
        let p = container;
        const chain = [];
        while (p) {
            const cs = getComputedStyle(p);
            chain.push({
                node: p.id || p.className || p.nodeName,
                display: cs.display,
                visibility: cs.visibility,
                height: cs.height,
                clientHeight: p.clientHeight
            });
            p = p.parentElement;
            if (p && p.id === "sheetBody") break;
        }
        console.log("backgroundContent parent chain:", chain);
    }, 200);
}

function renderSectionBlock(character, sectionKey, title, content, canEdit) {
    const wrapper = document.createElement("div");
    wrapper.className = "mb-4 border rounded p-3 bg-white background-section";

    const header = document.createElement("div");
    header.className = "d-flex align-items-center mb-2";

    const h4 = document.createElement("h4");
    h4.className = "mb-0";
    h4.textContent = title;
    header.appendChild(h4);

    let editBtn = null;
    if (canEdit) {
        editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "btn btn-link ms-2 p-0";
        editBtn.innerHTML = `<i class="fa-solid fa-pen"></i>`;
        header.appendChild(editBtn);
    }

    const viewDiv = document.createElement("div");
    viewDiv.className = "p-3";
    viewDiv.innerHTML = content || `<span class="text-muted">Geen tekst beschikbaar.</span>`;

    // Editor
    const editorWrap = document.createElement("div");
    editorWrap.className = "d-none";

    const toolbar = document.createElement("div");
    toolbar.className = "btn-group mb-2 flex-wrap";
    toolbar.innerHTML = `
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="bold"><i class="fa-solid fa-bold"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="italic"><i class="fa-solid fa-italic"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertUnorderedList"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertOrderedList"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h2">H2</button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h3">H3</button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h4">H4</button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h5">H5</button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="p">P</button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="removeFormat"><i class="fa-solid fa-eraser"></i></button>
    `;

    const editor = document.createElement("div");
    editor.className = "form-control";
    editor.contentEditable = "true";
    editor.style.minHeight = "180px";
    editor.innerHTML = content || "";

    const refreshToolbarState = () => {
        const sel = document.getSelection();
        let inEditor = false;
        if (sel && sel.anchorNode) {
            let node = sel.anchorNode;
            while (node) {
                if (node === editor) {
                    inEditor = true;
                    break;
                }
                node = node.parentNode;
            }
        }
        toolbar.querySelectorAll("button").forEach(btn => btn.classList.remove("active"));
        if (!inEditor) return;

        const boldActive = document.queryCommandState("bold");
        const italicActive = document.queryCommandState("italic");
        const listActive = document.queryCommandState("insertUnorderedList");
        const olistActive = document.queryCommandState("insertOrderedList");
        if (boldActive) toolbar.querySelector('[data-cmd="bold"]')?.classList.add("active");
        if (italicActive) toolbar.querySelector('[data-cmd="italic"]')?.classList.add("active");
        if (listActive) toolbar.querySelector('[data-cmd="insertUnorderedList"]')?.classList.add("active");
        if (olistActive) toolbar.querySelector('[data-cmd="insertOrderedList"]')?.classList.add("active");

        const blockTag = (() => {
            const sel = document.getSelection();
            if (!sel || !sel.anchorNode) return null;
            let n = sel.anchorNode;
            while (n && n !== editor) {
                if (n.nodeType === 1) {
                    const tag = n.nodeName.toLowerCase();
                    if (["h2", "h3", "h4", "h5", "p", "div"].includes(tag)) return tag;
                }
                n = n.parentNode;
            }
            return null;
        })();
        if (blockTag) {
            const btn = toolbar.querySelector(`[data-cmd="formatBlock"][data-value="${blockTag}"]`);
            if (btn) btn.classList.add("active");
        }
    };

    toolbar.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", () => {
            const cmd = btn.dataset.cmd;
            const val = btn.dataset.value || null;
            editor.focus();
            document.execCommand(cmd, false, val);
            refreshToolbarState();
        });
    });

    ["keyup", "mouseup", "focus"].forEach(evt => {
        editor.addEventListener(evt, refreshToolbarState);
    });

    const actions = document.createElement("div");
    actions.className = "mt-2 d-flex gap-2";
    const btnSave = document.createElement("button");
    btnSave.className = "btn btn-sm btn-primary";
    btnSave.textContent = "Save";

    const btnCancel = document.createElement("button");
    btnCancel.className = "btn btn-sm btn-outline-secondary";
    btnCancel.textContent = "Cancel";

    actions.appendChild(btnSave);
    actions.appendChild(btnCancel);

    editorWrap.appendChild(toolbar);
    editorWrap.appendChild(editor);
    editorWrap.appendChild(actions);

    // Wire buttons
    if (editBtn) {
        editBtn.addEventListener("click", () => {
            viewDiv.classList.add("d-none");
            editorWrap.classList.remove("d-none");
            editor.focus();
        });
    }

    btnCancel.addEventListener("click", () => {
        editor.innerHTML = content || "";
        editorWrap.classList.add("d-none");
        viewDiv.classList.remove("d-none");
    });

    btnSave.addEventListener("click", async () => {
        const newContent = editor.innerHTML.trim();
        try {
            await saveCharacterSection(character.id, sectionKey, newContent);
            // update view
            viewDiv.innerHTML = newContent || `<span class="text-muted">Geen tekst beschikbaar.</span>`;
            editorWrap.classList.add("d-none");
            viewDiv.classList.remove("d-none");
        } catch (err) {
            console.error("Fout bij opslaan sectie:", err);
            alert("Opslaan mislukt.");
        }
    });

    wrapper.appendChild(header);
    wrapper.appendChild(viewDiv);
    if (canEdit) {
        wrapper.appendChild(editorWrap);
    }

    return wrapper;
}

// -------- Ties ----------
async function renderTies(container, character) {
    const canEdit = canEditCharacterContent(character);
    let ties = [];
    let options = [];
    try {
        ties = await fetchCharacterTies(character.id) || [];
    } catch (err) {
        console.error("Fout bij ophalen ties:", err);
    }
    try {
        options = await fetchTieOptions() || [];
    } catch (err) {
        console.error("Fout bij ophalen tie opties:", err);
    }

    const block = document.createElement("div");
    block.className = "mb-4 border rounded p-3 bg-white background-section";

    const header = document.createElement("div");
    header.className = "d-flex align-items-center mb-2";

    const h4 = document.createElement("h4");
    h4.className = "mb-0";
    h4.textContent = "Ties";
    header.appendChild(h4);

    const addBtn = document.createElement("button");
    addBtn.type = "button";
    addBtn.className = "btn btn-sm btn-primary ms-2";
    addBtn.textContent = "Add tie";
    if (canEdit) {
        header.appendChild(addBtn);
    }

    block.appendChild(header);

    // Editor row
    const editorRow = document.createElement("div");
    editorRow.className = "row g-2 align-items-center mb-3 d-none";

    const selCharCol = document.createElement("div");
    selCharCol.className = "col-lg-4 col-12";
    const selChar = document.createElement("select");
    selChar.className = "form-select form-select-sm";
    selCharCol.appendChild(selChar);

    const selTypeCol = document.createElement("div");
    selTypeCol.className = "col-lg-3 col-6";
    const selType = document.createElement("select");
    selType.className = "form-select form-select-sm";
    AETHER_TIE_TYPES.forEach(t => {
        const opt = document.createElement("option");
        opt.value = t.value;
        opt.textContent = t.label;
        selType.appendChild(opt);
    });
    selTypeCol.appendChild(selType);

    const descCol = document.createElement("div");
    descCol.className = "col-lg-5 col-12";
    const descInput = document.createElement("input");
    descInput.type = "text";
    descInput.maxLength = 255;
    descInput.className = "form-control form-control-sm";
    descInput.placeholder = "Description";
    descCol.appendChild(descInput);

    editorRow.appendChild(selCharCol);
    editorRow.appendChild(selTypeCol);
    editorRow.appendChild(descCol);

    const actionsRow = document.createElement("div");
    actionsRow.className = "d-flex gap-2 mb-3 d-none";
    const saveBtn = document.createElement("button");
    saveBtn.className = "btn btn-sm btn-primary";
    saveBtn.textContent = "Save";
    const cancelBtn = document.createElement("button");
    cancelBtn.className = "btn btn-sm btn-outline-secondary";
    cancelBtn.textContent = "Cancel";
    actionsRow.appendChild(saveBtn);
    actionsRow.appendChild(cancelBtn);

    block.appendChild(editorRow);
    block.appendChild(actionsRow);

    // List
    const list = document.createElement("div");
    block.appendChild(list);

    // Populate options
    const fillOptions = () => {
        selChar.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "Select character";
        selChar.appendChild(placeholder);
        options.forEach(opt => {
            const o = document.createElement("option");
            o.value = opt.id;
            o.textContent = opt.displayName;
            selChar.appendChild(o);
        });
    };
    fillOptions();

    let editTieId = null;

    function hideEditor() {
        editTieId = null;
        editorRow.classList.add("d-none");
        actionsRow.classList.add("d-none");
        selChar.value = "";
        selType.value = AETHER_TIE_TYPES[0].value;
        descInput.value = "";
    }

    function showEditor(tie) {
        if (!canEdit) return;
        editorRow.classList.remove("d-none");
        actionsRow.classList.remove("d-none");
        editTieId = tie ? tie.id : null;
        selChar.value = tie ? tie.idOtherCharacter : "";
        selType.value = tie ? tie.relationType : AETHER_TIE_TYPES[0].value;
        descInput.value = tie ? (tie.description || "") : "";
    }

    function renderList() {
        list.innerHTML = "";
        if (!ties || ties.length === 0) {
            const empty = document.createElement("div");
            empty.className = "text-muted";
            empty.textContent = "No ties yet.";
            list.appendChild(empty);
            return;
        }
        ties.forEach(t => {
            const row = document.createElement("div");
            row.className = "mb-2";

            if (canEdit) {
                const editBtn = document.createElement("button");
                editBtn.type = "button";
                editBtn.className = "btn btn-link p-0 me-1";
                editBtn.innerHTML = `<i class="fa-solid fa-pen"></i>`;
                editBtn.addEventListener("click", () => showEditor(t));
                row.appendChild(editBtn);
            }

            const name = document.createElement("strong");
            name.textContent = t.otherName || "Onbekend";
            row.appendChild(name);

            const typeSpan = document.createElement("span");
            typeSpan.className = "text-muted ms-1";
            typeSpan.textContent = `(${t.relationTypeLabel || t.relationType || ""})`;
            row.appendChild(typeSpan);

            const descSpan = document.createElement("span");
            descSpan.className = "ms-1";
            descSpan.textContent = `- ${t.description || ""}`;
            row.appendChild(descSpan);

            list.appendChild(row);
        });
    }

    renderList();

    if (canEdit) {
        addBtn.addEventListener("click", () => showEditor(null));
        cancelBtn.addEventListener("click", () => hideEditor());
        saveBtn.addEventListener("click", async () => {
            const payload = {
                idCharacter: character.id,
                idTie: editTieId,
                idOtherCharacter: selChar.value ? Number(selChar.value) : 0,
                relationType: selType.value,
                description: descInput.value.trim()
            };
            try {
                const result = await saveCharacterTie(payload);
                ties = result && result.ties ? result.ties : await fetchCharacterTies(character.id);
                renderList();
                hideEditor();
            } catch (err) {
                console.error("Fout bij opslaan tie:", err);
                alert("Opslaan van tie mislukt.");
            }
        });
    }

    container.appendChild(block);
}
