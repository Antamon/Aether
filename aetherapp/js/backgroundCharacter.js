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
}

async function showBackgroundTab(character) {
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.add("d-none");
    const bgTab = document.getElementById("backgroundTab");
    if (!bgTab) return;
    bgTab.classList.remove("d-none");

    const container = document.getElementById("backgroundContent");
    if (!container) return;

    container.innerHTML = `<div class="text-muted">Loading background...</div>`;
    try {
        const data = await fetchCharacterSections(character.id);
        renderBackgroundSections(container, character, data || {});
    } catch (err) {
        console.error("Fout bij laden background:", err);
        container.innerHTML = `<div class="text-danger">Kon background niet laden.</div>`;
    }
}

function renderBackgroundSections(container, character, sections) {
    container.innerHTML = "";
    const canEdit = canEditCharacterContent(character);

    Object.entries(AETHER_BACKGROUND_SECTIONS).forEach(([key, meta]) => {
        const block = renderBackgroundBlock(character, key, meta.title, sections[key] || "", canEdit);
        container.appendChild(block);
    });
}

function renderBackgroundBlock(character, sectionKey, title, content, canEdit) {
    const wrapper = document.createElement("div");
    wrapper.className = "mb-4";

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
    viewDiv.className = "border rounded p-3 bg-light";
    viewDiv.innerHTML = content || `<span class="text-muted">Geen tekst beschikbaar.</span>`;

    // Editor
    const editorWrap = document.createElement("div");
    editorWrap.className = "d-none";

    const toolbar = document.createElement("div");
    toolbar.className = "btn-group mb-2";
    toolbar.innerHTML = `
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="bold"><i class="fa-solid fa-bold"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="italic"><i class="fa-solid fa-italic"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertUnorderedList"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h4">H4</button>
    `;

    const editor = document.createElement("div");
    editor.className = "form-control";
    editor.contentEditable = "true";
    editor.style.minHeight = "180px";
    editor.innerHTML = content || "";

    toolbar.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", () => {
            const cmd = btn.dataset.cmd;
            const val = btn.dataset.value || null;
            editor.focus();
            document.execCommand(cmd, false, val);
        });
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
