// The four navigation tabs share one sidebar layout and one open panel at a time.
(() => {
    const navbar = document.querySelector(".aether-top-nav");
    if (!navbar) return;

    const sidebars = [
        { key: "characters", title: "Characters", icon: "tabPersonages.svg", page: "index.html", panelId: "offcanvasScrolling", tabId: "offcanvasTabToggle", templateId: "characterSidebarContent" },
        { key: "events", title: "Evenementen", icon: "tabEvenementen.svg", page: "eventParticipation.html" },
        { key: "companies", title: "Bedrijven", icon: "tabBedrijven.svg", page: "companies.html", templateId: "companySidebarContent" },
        { key: "admin", title: "Admin", icon: "tabAdmin.svg", page: "admin.html" }
    ];
    const currentPage = document.body.dataset.sidebarPage || "";
    const panels = new Map();
    const loadedLists = new Set();
    let activeKey = null;
    const deck = document.createElement("div");
    deck.className = "aether-sidebar-deck";
    deck.id = "aetherSidebarDeck";

    function createSidebar(config) {
        const panel = document.createElement("aside");
        panel.className = "aether-sidebar-panel";
        if (config.key === currentPage || (currentPage === "static" && config.key === "characters")) {
            panel.classList.add("is-home");
        }
        panel.id = config.panelId || `${config.key}SidebarPanel`;
        panel.setAttribute("aria-labelledby", `${config.key}SidebarTitle`);
        panel.setAttribute("aria-hidden", "true");
        panel.inert = true;

        const header = document.createElement("div");
        header.className = "aether-sidebar-header";
        const decorationLeft = document.createElement("img");
        decorationLeft.src = "img/titleDecoration.svg";
        decorationLeft.alt = "";
        decorationLeft.setAttribute("aria-hidden", "true");
        const title = document.createElement("h2");
        title.className = "aether-sidebar-title";
        title.id = `${config.key}SidebarTitle`;
        title.textContent = config.title;
        const decorationRight = decorationLeft.cloneNode();
        header.append(decorationLeft, title, decorationRight);

        const paper = document.createElement("div");
        paper.className = "aether-sidebar-paper";
        const scroll = document.createElement("div");
        scroll.className = "aether-sidebar-scroll";
        const template = config.key === currentPage && config.templateId
            ? document.getElementById(config.templateId)
            : null;
        if (template) scroll.append(template.content);
        if (!template && config.key === "characters") {
            scroll.innerHTML = `<p>Om een personage snel te vinden, kan u gebruik maken van onderstaande filters.</p>
                <select class="form-select mb-2 d-none" id="sidebarCharacterUserFilter" aria-label="Deelnemer"><option value="all">Alle deelnemers</option><option value="unassigned">Zonder deelnemer</option></select>
                <select class="form-select mb-2" id="sidebarCharacterTypeFilter"><option value="">Alle soorten</option><option value="player">Spelerspersonage</option><option value="extra">Figurantenrol</option></select>
                <select class="form-select mb-2" id="sidebarCharacterStatusFilter"><option value="">Alle statussen</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="deceased">Deceased</option><option value="other">Other</option><option value="draft">Draft</option><option value="approve">Approve</option></select>
                <select class="form-select mb-2" id="sidebarCharacterClassFilter"><option value="">Alle klassen</option><option value="upper class">Bovenklasse</option><option value="middle class">Middenklasse</option><option value="lower class">Onderklasse</option></select>
                <div id="characterNames" class="my-2"></div><a class="nav-link" id="startNewCharacter" href="index.html?new=1"><i class="fa-solid fa-user-plus"></i> Nieuw personage</a>`;
        }
        if (!template && config.key === "companies") {
            scroll.innerHTML = `<div class="list-group company-list" id="companyList"></div>
                <div class="company-list-actions mt-3"><a class="btn btn-outline-primary w-100" href="companies.html?new=1">Nieuw bedrijf</a></div>`;
        }
        paper.append(scroll);
        panel.append(header, paper);

        const tabHost = document.createElement("div");
        tabHost.className = `offcanvas-tab offcanvas-tab--${config.key}`;
        tabHost.id = config.tabId || `${config.key}SidebarTabToggle`;
        const tab = document.createElement("button");
        tab.className = "tab-button";
        tab.type = "button";
        tab.setAttribute("aria-controls", panel.id);
        tab.setAttribute("aria-label", `${config.title} openen of sluiten`);
        tab.setAttribute("aria-expanded", "false");
        const icon = document.createElement("img");
        icon.className = "aether-sidebar-tab-icon";
        icon.src = `img/${config.icon}`;
        icon.alt = "";
        icon.setAttribute("aria-hidden", "true");
        const gold = document.createElement("img");
        gold.className = "aether-sidebar-tab-gold";
        gold.src = "img/goldTab.png";
        gold.alt = "";
        gold.setAttribute("aria-hidden", "true");
        tab.append(icon, gold);
        tabHost.append(tab);
        deck.append(panel, tabHost);

        tab.addEventListener("click", () => {
            const nextKey = activeKey === config.key ? null : config.key;
            setOpen(nextKey);
            if (nextKey && !loadedLists.has(nextKey)) void loadNavigationList(nextKey);
        });
        panels.set(config.key, { panel, tab, tabHost });
    }

    async function loadNavigationList(key) {
        if (key === currentPage) return;
        if (key !== "characters" && key !== "companies") return;
        const list = document.getElementById(key === "characters" ? "characterNames" : "companyList");
        if (!list) return;
        list.textContent = "Laden…";
        try {
            const user = window.AETHER_CURRENT_USER || await apiFetchJson("getCurrentUser.php");
            if (key === "companies" && !userHasPrivilegedRole(user)) {
                list.textContent = "U hebt geen toegang tot de bedrijvenlijst.";
                return;
            }
            const items = await apiFetchJson(key === "characters"
                ? "api/characters/getCharacterList.php"
                : "api/companies/getCompanyList.php", key === "characters" ? { method: "POST", body: {} } : undefined);
            if (!Array.isArray(items)) throw new Error("Geen lijst ontvangen.");
            if (key === "characters") {
                if (userHasPrivilegedRole(user)) {
                    const userFilter = document.getElementById("sidebarCharacterUserFilter");
                    try {
                        const users = await apiFetchJson("api/users/getUserList.php");
                        for (const candidate of Object.values(users || {})) {
                            const option = document.createElement("option");
                            option.value = String(candidate.id);
                            option.textContent = candidate.displayName || [candidate.firstName, candidate.lastName].filter(Boolean).join(" ") || candidate.username || `Deelnemer #${candidate.id}`;
                            userFilter.append(option);
                        }
                        userFilter.classList.remove("d-none");
                    } catch (error) {
                        console.error("Deelnemersfilter laden mislukt:", error);
                    }
                }
                renderNavigationCharacters(items, list);
            }
            else renderNavigationCompanies(items, list);
            loadedLists.add(key);
        } catch (error) {
            console.error("Sidebarlijst laden mislukt:", error);
            list.textContent = "De lijst kon niet geladen worden. Sluit en open de tab om opnieuw te proberen.";
        }
    }

    function renderNavigationCharacters(items, list) {
        const type = document.getElementById("sidebarCharacterTypeFilter");
        const status = document.getElementById("sidebarCharacterStatusFilter");
        const classFilter = document.getElementById("sidebarCharacterClassFilter");
        const userFilter = document.getElementById("sidebarCharacterUserFilter");
        const render = () => {
            list.replaceChildren();
            const filtered = items.filter((item) => (!type.value || item.type === type.value)
                && (!status.value || item.state === status.value)
                && (!classFilter.value || item.class === classFilter.value)
                && (!userFilter || userFilter.value === "all"
                    || (userFilter.value === "unassigned" && !Number(item.idUser))
                    || Number(item.idUser) === Number(userFilter.value)));
            if (!filtered.length) { list.textContent = "Geen personages gevonden voor deze filters."; return; }
            for (const item of filtered) {
                const card = document.createElement("div");
                card.className = "character-sidebar-card";
                const portrait = document.createElement("div");
                portrait.className = "character-sidebar-portrait-frame";
                const name = [item.firstName, item.lastName].filter(Boolean).join(" ") || `Personage #${item.id}`;
                if (item.portraitUrl) {
                    const image = document.createElement("img");
                    image.className = "character-sidebar-portrait-image";
                    image.src = item.portraitUrl;
                    image.alt = name;
                    portrait.append(image);
                } else {
                    portrait.textContent = "Geen pasfoto";
                    portrait.classList.add("character-sidebar-portrait-placeholder");
                }
                const label = document.createElement("div");
                label.className = "character-sidebar-name";
                label.textContent = name;
                const actions = document.createElement("div");
                actions.className = "character-sidebar-actions";
                const button = document.createElement("button");
                button.type = "button";
                button.className = "btn btn-outline-primary btn-sm character-sidebar-action";
                button.setAttribute("aria-label", `Bekijk ${name}`);
                button.innerHTML = '<i class="fa-solid fa-eye"></i>';
                button.addEventListener("click", () => { window.location.href = `index.html?character=${encodeURIComponent(item.id)}`; });
                actions.append(button);
                card.append(portrait, label, actions);
                list.append(card);
            }
        };
        for (const filter of [userFilter, type, status, classFilter]) filter?.addEventListener("change", render);
        render();
    }

    function renderNavigationCompanies(items, list) {
        list.replaceChildren();
        if (!items.length) { list.textContent = "Nog geen bedrijven beschikbaar."; return; }
        for (const item of items) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "list-group-item list-group-item-action company-list-item";
            button.textContent = item.companyName || `Bedrijf #${item.id}`;
            button.addEventListener("click", () => { window.location.href = `companies.html?company=${encodeURIComponent(item.id)}`; });
            list.append(button);
        }
    }

    function setOpen(key) {
        activeKey = key;
        deck.classList.toggle("is-open", key !== null);
        for (const [sidebarKey, { panel, tab, tabHost }] of panels) {
            const open = sidebarKey === key;
            panel.classList.toggle("is-open", open);
            panel.inert = !open;
            panel.setAttribute("aria-hidden", String(!open));
            tab.setAttribute("aria-expanded", String(open));
            tabHost.classList.toggle("is-active", open);
            if (!open && panel.contains(document.activeElement)) tab.focus();
        }
        const navTrigger = document.getElementById("characterSidebarNavTrigger");
        if (navTrigger) navTrigger.setAttribute("aria-expanded", String(key === "characters"));
    }

    function positionBelowNavbar() {
        const bottom = Math.max(0, navbar.getBoundingClientRect().bottom);
        deck.style.setProperty("--aether-navbar-bottom", `${bottom}px`);
    }

    sidebars.forEach(createSidebar);
    const edge = document.createElement("span");
    edge.className = "aether-sidebar-gold-edge";
    edge.setAttribute("aria-hidden", "true");
    deck.prepend(edge);
    document.body.append(deck);
    const navTrigger = document.getElementById("characterSidebarNavTrigger");
    if (navTrigger) {
        navTrigger.addEventListener("click", (event) => {
            event.preventDefault();
            const nextKey = activeKey === "characters" ? null : "characters";
            setOpen(nextKey);
            if (nextKey && !loadedLists.has(nextKey)) void loadNavigationList(nextKey);
        });
    }
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && activeKey !== null) setOpen(null);
    });
    window.addEventListener("resize", positionBelowNavbar);
    window.addEventListener("scroll", positionBelowNavbar, { passive: true });
    if (typeof ResizeObserver !== "undefined") new ResizeObserver(positionBelowNavbar).observe(navbar);

    window.closeCharacterSidebar = () => { if (activeKey === "characters") setOpen(null); };
    window.closeCompanySidebar = () => { if (activeKey === "companies") setOpen(null); };
    positionBelowNavbar();
    const requested = new URLSearchParams(window.location.search).get("sidebar");
    const selectingCompany = new URLSearchParams(window.location.search).has("company")
        || new URLSearchParams(window.location.search).has("new");
    setOpen(requested === currentPage || (!requested && currentPage === "companies" && !selectingCompany) ? currentPage : null);
})();
