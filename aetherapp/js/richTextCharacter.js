const AETHER_CHARACTER_RICH_TEXT_TOOLBAR = `
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="bold" aria-label="Vet"><i class="fa-solid fa-bold"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="italic" aria-label="Cursief"><i class="fa-solid fa-italic"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertUnorderedList" aria-label="Ongenummerde lijst"><i class="fa-solid fa-list-ul"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="insertOrderedList" aria-label="Genummerde lijst"><i class="fa-solid fa-list-ol"></i></button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="p" aria-label="Alinea">P</button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="formatBlock" data-value="h4" aria-label="Kopniveau 4">H4</button>
    <button type="button" class="btn btn-sm btn-secondary" data-cmd="removeFormat" aria-label="Opmaak verwijderen"><i class="fa-solid fa-eraser"></i></button>
`;

function renderCharacterRichText(element, sanitizedHtml, emptyText = "Geen inhoud.") {
    const html = String(sanitizedHtml || "").trim();
    if (html === "") {
        element.textContent = emptyText;
        element.classList.add("text-muted");
        element.style.whiteSpace = "pre-wrap";
        return;
    }

    element.classList.remove("text-muted");
    element.style.whiteSpace = "normal";
    // Alleen server-side gesaniteerde rich text uit de character-API mag hier komen.
    element.innerHTML = html;
}

function createCharacterRichTextEditor(initialHtml, options = {}) {
    const wrap = document.createElement("div");
    wrap.className = "aether-rich-text-editor";

    const toolbar = document.createElement("div");
    toolbar.className = "btn-group mb-2 flex-wrap";
    toolbar.innerHTML = AETHER_CHARACTER_RICH_TEXT_TOOLBAR;

    const editor = document.createElement("div");
    editor.className = "form-control";
    editor.contentEditable = "true";
    editor.setAttribute("role", "textbox");
    editor.setAttribute("aria-multiline", "true");
    editor.spellcheck = true;
    editor.style.minHeight = options.minHeight || "140px";
    // De initiële inhoud komt uit dezelfde server-side gesaniteerde API-responses.
    editor.innerHTML = String(initialHtml || "");

    const selectionIsInsideEditor = () => {
        const selection = document.getSelection();
        return Boolean(selection?.anchorNode && editor.contains(selection.anchorNode));
    };

    const refreshToolbarState = () => {
        toolbar.querySelectorAll("button").forEach(button => button.classList.remove("active"));
        if (!selectionIsInsideEditor()) return;

        for (const command of ["bold", "italic", "insertUnorderedList", "insertOrderedList"]) {
            if (document.queryCommandState(command)) {
                toolbar.querySelector(`[data-cmd="${command}"]`)?.classList.add("active");
            }
        }

        const selection = document.getSelection();
        let node = selection?.anchorNode || null;
        while (node && node !== editor) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.nodeName.toLowerCase();
                if (tagName === "p" || tagName === "h4") {
                    toolbar.querySelector(`[data-cmd="formatBlock"][data-value="${tagName}"]`)?.classList.add("active");
                    break;
                }
            }
            node = node.parentNode;
        }
    };

    toolbar.querySelectorAll("button").forEach(button => {
        button.addEventListener("click", () => {
            editor.focus();
            document.execCommand(button.dataset.cmd, false, button.dataset.value || null);
            refreshToolbarState();
        });
    });

    for (const eventName of ["keyup", "mouseup", "focus", "input"]) {
        editor.addEventListener(eventName, refreshToolbarState);
    }

    wrap.appendChild(toolbar);
    wrap.appendChild(editor);

    return {
        wrapper: wrap,
        toolbar,
        editor,
        getHtml: () => editor.innerHTML.trim(),
        setHtml: html => {
            editor.innerHTML = String(html || "");
        }
    };
}
