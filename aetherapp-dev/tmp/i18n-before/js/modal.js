// One decorative frame for static Bootstrap modals and dynamically created dialogs.
(() => {
    let nextTitle = 0;
    const frames = ["top", "bottom", "left", "right", "top-left", "top-right", "bottom-left", "bottom-right"];

    function decorate(surface) {
        if (surface.classList.contains("aether-modal-surface")) return;
        const native = surface.tagName === "DIALOG";
        if (!native && !surface.closest(".modal")) return;
        surface.classList.add("aether-modal-surface");

        if (native) {
            // Keep the original controls and listeners; move them into a scrollable body.
            const children = [...surface.childNodes];
            const title = children.find(child => child.nodeType === 1 && /^H[1-6]$/.test(child.tagName));
            const header = document.createElement("div");
            header.className = "modal-header";
            if (title) {
                title.classList.add("modal-title");
                if (!title.id) title.id = `aether-dialog-title-${++nextTitle}`;
                surface.setAttribute("aria-labelledby", title.id);
                header.appendChild(title);
            }
            const body = document.createElement("div");
            body.className = "modal-body";
            for (const child of children) if (child !== title) body.appendChild(child);
            surface.append(header, body);
        }

        for (const position of frames) {
            const frame = document.createElement("span");
            frame.className = `aether-modal-frame aether-modal-frame-${position}`;
            frame.setAttribute("aria-hidden", "true");
            surface.appendChild(frame);
        }
        const close = document.createElement("button");
        close.type = "button";
        close.className = "aether-modal-close";
        close.setAttribute("aria-label", "Sluiten");
        const icon = document.createElement("i");
        icon.className = "fa-solid fa-xmark";
        icon.setAttribute("aria-hidden", "true");
        close.appendChild(icon);
        if (native) close.addEventListener("click", () => surface.close());
        else close.setAttribute("data-bs-dismiss", "modal");
        surface.appendChild(close);
    }

    function scan(root) {
        if (root.nodeType !== 1 && root.nodeType !== 9) return;
        if (root.matches?.(".modal-content, dialog")) decorate(root);
        root.querySelectorAll(".modal-content, dialog").forEach(decorate);
    }

    // Bootstrap emits this synchronously, before displaying the backdrop and dialog.
    document.addEventListener("show.bs.modal", event => scan(event.target));
    // Native dialogs have no Bootstrap event. Decoration happens before the next paint.
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) scan(node);
    }).observe(document.documentElement, { childList: true, subtree: true });
    scan(document);
})();
