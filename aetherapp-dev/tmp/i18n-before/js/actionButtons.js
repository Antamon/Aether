// Reusable compact action groups for panel and card headers.
const AetherActionButtons = (() => {
    function button({ icon, label = "", title = label, ariaLabel = label, onClick, disabled = false, className = "" }) {
        const element = document.createElement("button");
        element.type = "button";
        element.className = `action-button${className ? ` ${className}` : ""}`;
        element.disabled = Boolean(disabled);
        if (ariaLabel) element.setAttribute("aria-label", ariaLabel);
        if (title) element.title = title;

        if (icon) {
            const iconElement = document.createElement("i");
            iconElement.className = `fa-solid ${icon}`;
            iconElement.setAttribute("aria-hidden", "true");
            element.appendChild(iconElement);
        }
        if (label) {
            const text = document.createElement("span");
            text.textContent = label;
            element.appendChild(text);
        }
        if (typeof onClick === "function") element.addEventListener("click", onClick);
        return element;
    }

    function group(buttons = []) {
        const element = document.createElement("div");
        element.className = "button-group";
        element.append(...buttons);
        return element;
    }

    return { button, group };
})();
