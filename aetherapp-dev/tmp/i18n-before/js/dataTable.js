// Shared tables, ten-row pagination and read-only detail dialogs.
const AetherDataTable = (() => {
    let nextModalId = 0;
    const node = (tag, className = "", text) => {
        const element = document.createElement(tag); element.className = className;
        if (text !== undefined) element.textContent = String(text);
        return element;
    };

    function showDetails({ title, fields, actions = [], trigger }) {
        const modal = node("div", "modal fade"); modal.tabIndex = -1;
        const titleId = `data-table-modal-${++nextModalId}`;
        modal.setAttribute("aria-labelledby", titleId);
        const dialog = node("div", "modal-dialog modal-lg modal-dialog-scrollable");
        const content = node("div", "modal-content"); const header = node("div", "modal-header");
        const heading = node("h2", "modal-title h5", title); heading.id = titleId; header.append(heading);
        const body = node("div", "modal-body"); const list = node("dl", "data-detail-fields");
        for (const [label, value] of fields) {
            list.append(node("dt", "", label), node("dd", "", value === null || value === undefined || value === "" ? "—" : value));
        }
        const error = node("p", "text-danger mb-0"); error.setAttribute("role", "alert");
        body.append(list, error);
        const buttons = [];
        for (const action of actions) {
            const button = AetherActionButtons.button({ icon: action.icon, label: action.label });
            button.addEventListener("click", async () => {
                buttons.forEach(item => { item.disabled = true; }); error.textContent = "";
                try { if (await action.run() !== false) instance.hide(); }
                catch (failure) { error.textContent = failure.message || "De wijziging kon niet worden bewaard."; }
                finally { buttons.forEach(item => { item.disabled = false; }); }
            });
            buttons.push(button);
        }
        const close = AetherActionButtons.button({ icon: "fa-xmark", label: "Sluiten" });
        close.dataset.bsDismiss = "modal"; buttons.push(close);
        const footer = node("div", "modal-footer"); footer.append(AetherActionButtons.group(buttons));
        content.append(header, body, footer); dialog.append(content); modal.append(dialog); document.body.append(modal);
        // Bootstrap caches the dialog during initialization, so assemble it first.
        const instance = new bootstrap.Modal(modal);
        modal.addEventListener("hidden.bs.modal", () => {
            instance.dispose(); modal.remove();
            if (trigger?.isConnected) trigger.focus({ preventScroll: true });
        }, { once: true });
        instance.show();
    }

    function infoButton(details) {
        const button = node("button", "data-info-button"); button.type = "button";
        button.setAttribute("aria-label", `Details: ${details.title}`); button.title = "Gegevens bekijken";
        const icon = node("i", "fa-solid fa-circle-info"); icon.setAttribute("aria-hidden", "true"); button.append(icon);
        button.addEventListener("click", () => showDetails({ ...details, trigger: button }));
        return button;
    }

    function render(host, { label, rows, columns, pageSize = 10 }) {
        const section = node("div", "data-table-section");
        const wrap = node("div", "table-responsive"); const table = node("table", "table aether-table align-middle mb-0");
        table.append(node("caption", "visually-hidden", label));
        const head = node("thead"); const heading = node("tr");
        for (const column of columns) {
            const cell = node("th", column.className || "", column.label); cell.setAttribute("scope", "col"); heading.append(cell);
        }
        head.append(heading); const body = node("tbody"); table.append(head, body); wrap.append(table); section.append(wrap);
        let page = 0;
        const count = Math.max(1, Math.ceil(rows.length / pageSize));
        const status = node("span", "data-table-page-status"); status.setAttribute("aria-live", "polite");
        const previous = AetherActionButtons.button({ icon: "fa-arrow-left", ariaLabel: "Vorige pagina", title: "Vorige pagina" });
        const next = AetherActionButtons.button({ icon: "fa-arrow-right", ariaLabel: "Volgende pagina", title: "Volgende pagina" });
        function draw() {
            body.replaceChildren();
            for (const row of rows.slice(page * pageSize, (page + 1) * pageSize)) {
                const tr = node("tr");
                for (const column of columns) {
                    const cell = node("td", column.className || ""); const value = column.render(row);
                    if (value && typeof value === "object") cell.append(value); else cell.textContent = String(value ?? "");
                    tr.append(cell);
                }
                body.append(tr);
            }
            status.textContent = `Pagina ${page + 1} van ${count}`;
            previous.disabled = page === 0; next.disabled = page === count - 1;
        }
        previous.addEventListener("click", () => { if (page > 0) { --page; draw(); } });
        next.addEventListener("click", () => { if (page < count - 1) { ++page; draw(); } });
        if (count > 1) {
            const pager = node("nav", "data-table-pagination mt-3"); pager.setAttribute("aria-label", `${label}: pagina's`);
            pager.append(AetherActionButtons.group([previous]), status, AetherActionButtons.group([next])); section.append(pager);
        }
        draw(); host.append(section); return section;
    }
    return { render, infoButton, showDetails };
})();
