// The four navigation tabs share one sidebar layout and one open panel at a time.
(() => {
    const navbar = document.querySelector(".aether-top-nav");
    if (!navbar) return;

    const sidebars = [
        { key: "characters", title: "Characters", icon: "tabPersonages.svg", page: "index.html", panelId: "offcanvasScrolling", tabId: "offcanvasTabToggle", templateId: "characterSidebarContent" },
        { key: "events", title: "Evenementen", icon: "tabEvenementen.svg", page: "eventParticipation.html", templateId: "eventSidebarContent" },
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
            scroll.innerHTML = `<div class="company-list" id="companyList"></div>
                <div class="company-list-actions button-group mt-3 w-100"><a class="action-button w-100 text-decoration-none" href="companies.html?new=1"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nieuw bedrijf</a></div>`;
        }
        if (!template && config.key === "events") {
            const filter = document.createElement("div");
            filter.className = "mb-3 d-none"; filter.id = "participantFilterWrapper";
            const label = document.createElement("label"); label.className = "form-label";
            label.htmlFor = "eventParticipantSelect"; label.textContent = "Deelnames van deelnemer";
            const choices = document.createElement("div"); choices.id = "eventParticipantFilterChoices";
            filter.append(label, choices); scroll.append(filter);
            const list = document.createElement("div");
            list.id = "eventSidebarList";
            list.className = "company-list event-sidebar-list";
            scroll.append(list);
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
        if (!["characters", "companies", "events"].includes(key)) return;
        const list = document.getElementById(key === "characters" ? "characterNames" : key === "events" ? "eventSidebarList" : "companyList");
        if (!list) return;
        list.textContent = "Laden…";
        try {
            const user = await aetherGetCurrentUser();
            if (key === "events") {
                const [items] = await Promise.all([
                    apiFetchJson("api/events/getEventList.php", { method: "POST", body: {} }),
                    setupEventParticipantFilter(id => { window.location.href = `eventParticipation.html?participant=${encodeURIComponent(id)}`; }, "", user)
                ]);
                renderEventSidebarCards(items, list, event => {
                    window.location.href = `eventParticipation.html?event=${encodeURIComponent(event.id)}`;
                });
                loadedLists.add(key);
                return;
            }
            if (key === "companies" && !userHasPrivilegedRole(user)) {
                const result = await apiFetchJson("api/companies/getMyLeaderCompanies.php");
                renderNavigationCompanies(result?.companies || [], list, "leaderPersonnel.html");
                list.nextElementSibling?.classList.add("d-none");
                loadedLists.add(key);
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
                list.append(createCharacterSidebarCard(item, () => {
                    window.location.href = `index.html?character=${encodeURIComponent(item.id)}`;
                }));
            }
        };
        for (const filter of [userFilter, type, status, classFilter]) filter?.addEventListener("change", render);
        render();
    }

    function createCharacterSidebarCard(item, onOpen, activeId = 0) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "company-sidebar-card character-sidebar-card";
        const name = [item.firstName, item.lastName].filter(Boolean).join(" ") || `Personage #${item.id}`;
        button.setAttribute("aria-label", `Open ${name}`);
        button.dataset.idCharacter = String(item.id || 0);
        if (Number(item.id) === Number(activeId)) {
            button.classList.add("active");
            button.setAttribute("aria-current", "true");
        }
        const imageFrame = document.createElement("span");
        imageFrame.className = "company-sidebar-image-frame character-sidebar-portrait-frame";
        const showPlaceholder = () => {
            imageFrame.replaceChildren();
            imageFrame.classList.add("character-sidebar-portrait-placeholder");
            imageFrame.textContent = "Geen pasfoto";
        };
        if (item.portraitUrl) {
            const image = document.createElement("img");
            image.className = "company-sidebar-image character-sidebar-portrait-image";
            image.src = String(item.portraitUrl);
            image.alt = "";
            image.loading = "lazy";
            image.addEventListener("error", showPlaceholder, { once: true });
            imageFrame.append(image);
        } else showPlaceholder();
        const label = document.createElement("span");
        label.className = "company-sidebar-name character-sidebar-name";
        label.textContent = name;
        button.append(imageFrame, label);
        button.addEventListener("click", () => onOpen(item));
        return button;
    }
    window.createCharacterSidebarCard = createCharacterSidebarCard;

    function renderCompanySidebarCards(items, list, onOpen, activeId = 0) {
        list.replaceChildren();
        if (!items.length) { list.textContent = "Nog geen bedrijven beschikbaar."; return; }
        for (const item of items) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "company-sidebar-card";
            const name = String(item.companyName || `Bedrijf #${item.id}`);
            button.setAttribute("aria-label", `Open ${name}`);
            button.dataset.idCompany = String(item.id || 0);
            if (Number(item.id) === Number(activeId)) {
                button.classList.add("active");
                button.setAttribute("aria-current", "true");
            }
            const imageFrame = document.createElement("span");
            imageFrame.className = "company-sidebar-image-frame";
            if (item.logoUrl) {
                const image = document.createElement("img");
                image.className = "company-sidebar-image";
                image.src = String(item.logoUrl);
                image.alt = "";
                image.loading = "lazy";
                image.addEventListener("error", () => {
                    imageFrame.replaceChildren();
                    imageFrame.classList.add("company-sidebar-image-placeholder");
                    imageFrame.textContent = "Geen logo";
                }, { once: true });
                imageFrame.append(image);
            } else {
                imageFrame.classList.add("company-sidebar-image-placeholder");
                imageFrame.textContent = "Geen logo";
            }
            const label = document.createElement("span");
            label.className = "company-sidebar-name";
            label.textContent = name;
            button.append(imageFrame, label);
            button.addEventListener("click", () => onOpen(item));
            list.append(button);
        }
    }
    window.renderCompanySidebarCards = renderCompanySidebarCards;

    function renderEventSidebarCards(items, list, onOpen, activeId = 0) {
        if (!list) return;
        renderCompanySidebarCards(items.map(event => ({ ...event, companyName: event.title, logoUrl: event.imageUrl })), list, onOpen, activeId);
        if (!items.length) { list.textContent = "Nog geen events beschikbaar."; return; }
        for (const card of list.children) {
            card.classList.add("event-sidebar-card");
            const placeholder = card.querySelector(".company-sidebar-image-placeholder");
            if (placeholder) placeholder.textContent = "Geen afbeelding";
        }
    }
    window.renderEventSidebarCards = renderEventSidebarCards;

    async function setupEventParticipantFilter(onChoose, selectedId = "", user = null) {
        const wrapper = document.getElementById("participantFilterWrapper");
        const host = document.getElementById("eventParticipantFilterChoices");
        if (!wrapper || !host) return;
        try {
            if (!userHasPrivilegedRole(user || await aetherGetCurrentUser())) return;
            const users = await apiFetchJson("api/users/getUserList.php");
            const options = Object.values(users || {}).map(user => ({ id: user.id,
                text: user.displayName || [user.firstName, user.lastName].filter(Boolean).join(" ") || user.username || `Deelnemer #${user.id}`
            })).sort((a, b) => a.text.localeCompare(b.text));
            const select = document.createElement("select"); select.id = "eventParticipantSelect";
            select.className = "form-select"; select.setAttribute("aria-label", "Deelnames van deelnemer");
            const prompt = document.createElement("option"); prompt.value = ""; prompt.textContent = "Kies een deelnemer"; select.append(prompt);
            for (const user of options) { const option = document.createElement("option"); option.value = String(user.id); option.textContent = user.text; select.append(option); }
            select.value = selectedId ? String(selectedId) : "";
            select.addEventListener("change", () => { if (select.value) onChoose(select.value); });
            host.replaceChildren(select); wrapper.classList.remove("d-none");
        } catch (error) { console.error("Deelnemerskeuze laden mislukt:", error); }
    }
    window.setupEventParticipantFilter = setupEventParticipantFilter;

    function renderNavigationCompanies(items, list, page = "companies.html") {
        renderCompanySidebarCards(items, list, (item) => {
            window.location.href = `${page}?company=${encodeURIComponent(item.id)}`;
        });
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
        // Keep the character header inside the thin part of the proportional topbar.
        // Use the sheet container as the anchor even while pageNav is hidden/loading.
        const pageNav = document.getElementById("pageNav");
        const sheet = document.getElementById("sheetBody");
        if (pageNav && sheet) {
            const bounds = sheet.getBoundingClientRect();
            const artwork = navbar.getBoundingClientRect();
            const availableWidth = artwork.left + artwork.width * 0.75 - bounds.left - 16;
            pageNav.style.setProperty("--aether-page-nav-left", `${bounds.left}px`);
            pageNav.style.setProperty("--aether-page-nav-width", `${Math.max(0, availableWidth)}px`);
            pageNav.style.setProperty("--aether-page-nav-container-width", `${bounds.width}px`);
        }
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
    window.closeEventSidebar = () => { if (activeKey === "events") setOpen(null); };
    positionBelowNavbar();
    const requested = new URLSearchParams(window.location.search).get("sidebar");
    const selectingCompany = new URLSearchParams(window.location.search).has("company")
        || new URLSearchParams(window.location.search).has("new");
    const selectingEvent = new URLSearchParams(window.location.search).has("event")
        || new URLSearchParams(window.location.search).has("participant");
    setOpen(requested === currentPage || (!requested && (currentPage === "companies" && !selectingCompany
        || currentPage === "events" && !selectingEvent)) ? currentPage : null);
})();
