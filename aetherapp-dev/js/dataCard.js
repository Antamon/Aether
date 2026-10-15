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

    function button(label, icon, onClick) {
        return AetherActionButtons.button({ label, icon, onClick });
    }

    function controls(header, { key, canEdit = false, onEdit, onSave, onCancel, saveLabel = "Bewaren" }) {
        const actions = document.createElement("div");
        actions.className = "data-card-actions button-group";
        if (!canEdit) return actions;
        if (isEditing(key)) {
            const cancel = button("Cancel", "fa-xmark", onCancel);
            const save = button(saveLabel === "Bewaren" ? "Save" : saveLabel, "fa-check", async () => {
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
            const edit = button("Edit", "fa-pen-nib", onEdit);
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
