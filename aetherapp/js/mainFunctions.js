// Kleine, centrale helper om JSON van de server op te halen
// - endpoint: bv. "checkLogin.php" of "api/events/getEventList.php"
// - options: { method: 'POST', body: {...} }
async function apiFetchJson(endpoint, options = {}) {
    const {
        method = "GET",
        body = null,
        headers = {}
    } = options;

    const fetchOptions = {
        method,
        headers: {
            ...headers
        }
    };

    if (body !== null) {
        fetchOptions.headers["Content-Type"] = "application/json";
        fetchOptions.body = JSON.stringify(body);
    }

    const response = await fetch(endpoint, fetchOptions);

    if (!response.ok) {
        // Probeer leesbare foutinfo te krijgen
        let text = "";
        try {
            text = await response.text();
        } catch (e) {
            text = "(geen body)";
        }
        throw new Error(`API-fout ${response.status}: ${text}`);
    }

    // Probeer JSON te parsen – als dat niet kan, geven we null terug
    try {
        return await response.json();
    } catch (e) {
        return null;
    }
}

// Globale plaats om basis-userinfo op te slaan (optioneel bruikbaar in andere files)
window.AETHER_CURRENT_USER = null;

// Login-check bij laden van de pagina
async function checkLoginOnLoad() {
    try {
        const data = await apiFetchJson("checkLogin.php");

        if (!data || data.status !== "ok" || !data.user) {
            // Niet (meer) ingelogd via Oneiros → terug naar hoofdsite
            window.location.href = "../../../index.php";
            return;
        }

        window.AETHER_CURRENT_USER = data.user;

        const navbarName = document.getElementById("navbarName");
        if (navbarName) {
            navbarName.textContent = "Welkom " + data.user.displayName;
        }
    } catch (error) {
        console.error("Fout bij sessiecontrole:", error);
        window.location.href = "../../../index.php";
    }
}

window.addEventListener("load", checkLoginOnLoad);

// --------------------------------------
//  HULPFUNCTIES VOOR FORMULIEREN / UI
// --------------------------------------

// Selecteer een optie in een <select> op basis van de value
function activateSelectOption(dropdownMenuId, activeValue) {
    const dropdown = document.getElementById(dropdownMenuId);
    if (!dropdown) return;

    for (let i = 0; i < dropdown.options.length; i++) {
        if (dropdown.options[i].value === activeValue) {
            dropdown.selectedIndex = i;
            break;
        }
    }
}

// Dropdown + input component (voor bv. deelnemerslijst)
// Gebruikt in zowel characters als events
class DropdownInput {
    constructor(containerId, optionsArray, onSelect, startId = null) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`DropdownInput: container #${containerId} niet gevonden.`);
            return;
        }

        this.options = optionsArray || [];
        this.onSelect = onSelect; // callback functie
        this.startId = startId;

        this.inputId = `${containerId}-input`;
        this.buttonId = `${containerId}-toggle`;
        this.dropdownId = `${containerId}-dropdown`;

        this.render();
        this.setupEvents();

        if (this.startId) {
            this.selectById(this.startId);
        }
    }

    render() {
        this.container.classList.add("dropdown-container");
        this.container.innerHTML = `
          <div class="input-group" id="${this.container.id}-group">
              <button type="button" class="btn btn-outline-secondary" id="${this.container.id}-clear">
                  <i class="fa-solid fa-xmark text-light"></i>
              </button>
              <input id="${this.inputId}" type="text" class="form-control" aria-label="Text input with dropdown">
              <button type="button" class="btn btn-outline-secondary" id="${this.container.id}-reset">
                  <i class="fa-solid fa-trash text-light"></i>
              </button>
              <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" id="${this.buttonId}">
                  <span class="visually-hidden">Toggle Dropdown</span>
              </button>
          </div>
          <ul class="dropdown-menu" id="${this.dropdownId}">
              ${this.options
                  .map(opt => `<li><a class="dropdown-item" href="#" data-id="${opt.id}">${opt.text}</a></li>`)
                  .join("")}
          </ul>
      `;

        this.input = document.getElementById(this.inputId);
        this.button = document.getElementById(this.buttonId);
        this.dropdown = document.getElementById(this.dropdownId);
        this.items = Array.from(this.dropdown.querySelectorAll(".dropdown-item"));
        this.clearBtn = document.getElementById(`${this.container.id}-clear`);
        this.resetBtn = document.getElementById(`${this.container.id}-reset`);
    }

    setupEvents() {
        if (!this.input) return;

        this.input.addEventListener("input", () => this.filterOptions());
        this.input.addEventListener("focus", () => this.filterOptions());

        if (this.button) {
            this.button.addEventListener("click", () => {
                this.dropdown.classList.toggle("show");
            });
        }

        this.items.forEach(item => {
            item.addEventListener("click", e => {
                e.preventDefault();
                this.input.value = item.textContent;
                this.dropdown.classList.remove("show");
                this.updateIcons();

                const selectedId = item.dataset.id;
                if (this.onSelect && typeof this.onSelect === "function") {
                    this.onSelect(selectedId);
                }
            });
        });

        if (this.clearBtn) {
            this.clearBtn.addEventListener("click", () => {
                this.input.value = "";
                this.filterOptions();
            });
        }

        this.resetBtn.addEventListener('click', () => {
            this.input.value = '';
            this.filterOptions();

            // Deelnemer loskoppelen (bv. character zonder speler)
            if (this.onSelect && typeof this.onSelect === 'function') {
            this.onSelect(null); // speciale waarde voor "unlink"
            }
        });


        document.addEventListener("click", e => {
            if (!this.container.contains(e.target)) {
                this.dropdown.classList.remove("show");
            }
        });
    }

    updateIcons() {
        if (!this.input || !this.clearBtn || !this.resetBtn) return;

        const inputVal = this.input.value.trim();
        const xIcon = this.clearBtn.querySelector("i");
        const trashIcon = this.resetBtn.querySelector("i");

        const visibleItems = this.items.filter(i =>
            i.textContent.toLowerCase().includes(inputVal.toLowerCase())
        );

        if (inputVal === "") {
            xIcon.className = "fa-solid fa-xmark text-light";
            trashIcon.className = "fa-solid fa-trash text-light";
        } else if (
            visibleItems.length === 1 &&
            visibleItems[0].textContent.toLowerCase() === inputVal.toLowerCase()
        ) {
            xIcon.className = "fa-solid fa-check text-success";
            trashIcon.className = "fa-solid fa-trash text-secondary";
        } else {
            xIcon.className = "fa-solid fa-xmark text-danger";
            trashIcon.className = "fa-solid fa-trash text-secondary";
        }
    }

    filterOptions() {
        const query = this.input.value.toLowerCase();
        let visibleItems = 0;

        this.items.forEach(item => {
            const text = item.textContent.toLowerCase();
            const match = text.includes(query);
            item.style.display = match ? "block" : "none";
            if (match) visibleItems++;
        });

        if (query && visibleItems === 1) {
            const matchedItem = this.items.find(i => i.style.display === "block");
            if (matchedItem) {
                this.input.value = matchedItem.textContent;
                this.dropdown.classList.remove("show");
                this.updateIcons();

                const selectedId = matchedItem.dataset.id;
                if (this.onSelect && typeof this.onSelect === "function") {
                    this.onSelect(selectedId);
                }

                return;
            }
        }

        this.dropdown.classList.toggle("show", query !== "" && visibleItems > 0);
        this.updateIcons();
    }

    selectById(id) {
    const idStr = String(id);
    const item = this.items.find(i => i.dataset.id === idStr);
    if (item) {
        this.input.value = item.textContent;
        this.dropdown.classList.remove("show");
        this.updateIcons();
        if (this.onSelect && typeof this.onSelect === "function") {
        this.onSelect(id);
        }
    }
    }
}
