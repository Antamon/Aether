// Shared read/edit controls for cards. A page supplies its own fields and save operation.
const aetherDataCard = (() => {
    let activeKey = null;

    function isEditing(key) { return activeKey === key; }
    function open(key) {
        if (activeKey !== null && activeKey !== key) return false;
        activeKey = key;
        return true;
    }
    function close(key) { if (activeKey === key) activeKey = null; }
    function reset() { activeKey = null; }
    function hasActive() { return activeKey !== null; }

    function button(label, className, onClick) {
        const element = document.createElement("button");
        element.type = "button";
        element.className = className;
        element.textContent = label;
        element.addEventListener("click", onClick);
        return element;
    }

    function controls(header, { key, canEdit = false, onEdit, onSave, onCancel, saveLabel = "Bewaren" }) {
        const actions = document.createElement("div");
        actions.className = "data-card-actions";
        if (!canEdit) return actions;
        if (isEditing(key)) {
            const cancel = button("Annuleren", "btn btn-outline-secondary btn-sm", onCancel);
            const save = button(saveLabel, "btn btn-primary btn-sm", async () => {
                save.disabled = true;
                cancel.disabled = true;
                try { await onSave(save); } finally {
                    if (save.isConnected) {
                        save.disabled = false;
                        cancel.disabled = false;
                    }
                }
            });
            actions.append(save, cancel);
        } else {
            const edit = button("✎", "btn btn-outline-secondary btn-sm data-card-edit-button", onEdit);
            edit.setAttribute("aria-label", "Bewerken");
            edit.title = "Bewerken";
            edit.disabled = activeKey !== null;
            actions.appendChild(edit);
        }
        header.appendChild(actions);
        return actions;
    }

    function fact(parent, label, value) {
        const item = document.createElement("div");
        item.className = "data-card-fact";
        const caption = document.createElement("span");
        caption.className = "data-card-fact-label";
        caption.textContent = label;
        const content = document.createElement("span");
        content.textContent = value == null || value === "" ? "—" : String(value);
        item.append(caption, content);
        parent.appendChild(item);
        return item;
    }

    return { isEditing, open, close, reset, hasActive, controls, fact };
})();
