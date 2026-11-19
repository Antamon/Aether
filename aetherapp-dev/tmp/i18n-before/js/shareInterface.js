// Share screens use the dedicated, permission-checked API. Numbers sent to it stay locale neutral.
const aetherShareUi = (() => {
    const copy = {
        nl: {
            name: "Naam", purchases: "Aankopen", eventUnknown: "Event onbekend", addPurchase: "Aandelenaankoop toevoegen",
            purchaseQuarter: "Aankoopkwartaal", purchaseSummary: "Overzicht", adjustBank: "Bankrekening aanpassen",
            purchaseEvent: "Event van aankoop", purchaseDebits: "De aankoopprijs wordt van de bankrekening van het personage afgetrokken.",
            setupPurchase: "Maak eerst een event en bedrijfsaandelen aan.",
            economyAction: "Economie",
            portfolio: "Aandelenportefeuille", emptyPortfolio: "Dit personage bezit nog geen aandelen.",
            intrinsic: "Intrinsieke bezitswaarde", level: "Aandelen-niveau", status: "Gebruikte statuspunten",
            personnel: "Personeelsvoorwaarde", shares: "aandelen", issued: "Uitgegeven", sold: "Verkocht",
            free: "Vrij", anonymous: "Anoniem", nominal: "Nominale waarde per aandeel",
            intrinsicUnit: "Intrinsieke waarde per aandeel", market: "Marktprijs", listed: "Beursgenoteerd",
            initialValue: "Initiële bedrijfswaarde", initialCount: "Initieel aantal aandelen",
            companyValue: "Actuele bedrijfswaarde", quantity: "Aantal aandelen", price: "Prijs per aandeel",
            save: "Bewaren", cancel: "Annuleren", create: "Aanbod maken", close: "Sluiten", removeHolding: "Aandelen verwijderen",
            recipient: "Ontvangend personage", seller: "Verkopend personage", board: "Raad van bestuur",
            offer: "Rechtstreeks aanbod", sell: "Aandelen rechtstreeks aanbieden",
            privateOffer: "Privéaanbod", buyback: "Vrije aandelen intrekken",
            snapshot: "Snapshot", loading: "Aandelengegevens laden…", unavailable: "Aandelengegevens zijn niet beschikbaar.",
            invalid: "Controleer het aantal en de prijs. Gebruik gehele aantallen en bedragen met maximaal twee decimalen.",
            saved: "Wijziging bewaard.", company: "Bedrijfsaandelen", manager: "Aandelenbeheer",
            myOffers: "Mijn open aanbiedingen", noOffers: "Geen open aanbiedingen.", revoke: "Intrekken",
            noBoard: "Je bent geen bestuurslid van dit bedrijf.", noRecipients: "Geen actieve ontvangers beschikbaar.",
            noSnapshotsForOffer: "Maak eerst een bedrijfssnapshot om aandelen voor die snapshot aan te bieden.",
            conflict: "De voorraad of het aanbod is gewijzigd. De gegevens zijn ververst.",
            forbidden: "Je hebt geen toegang tot deze aandelenactie.",
        },
        en: {
            name: "Name", purchases: "Purchases", eventUnknown: "Unknown event", addPurchase: "Add share purchase",
            purchaseQuarter: "Purchase quarter", purchaseSummary: "Overview", adjustBank: "Adjust bank account",
            purchaseEvent: "Purchase event", purchaseDebits: "The purchase price is deducted from the character's bank account.",
            setupPurchase: "Create an event and company shares first.",
            economyAction: "Economy",
            portfolio: "Share portfolio", emptyPortfolio: "This character owns no shares yet.",
            intrinsic: "Intrinsic portfolio value", level: "Share level", status: "Status points used",
            personnel: "Staff requirement", shares: "shares", issued: "Issued", sold: "Sold",
            free: "Free", anonymous: "Anonymous", nominal: "Nominal value per share",
            intrinsicUnit: "Intrinsic value per share", market: "Market price", listed: "Listed",
            initialValue: "Initial company value", initialCount: "Initial share count",
            companyValue: "Current company value", quantity: "Number of shares", price: "Price per share",
            save: "Save", cancel: "Cancel", create: "Create offer", close: "Close", removeHolding: "Remove shares",
            recipient: "Recipient character", seller: "Selling character", board: "Board of directors",
            offer: "Direct offer", sell: "Offer owned shares directly",
            privateOffer: "Private offer", buyback: "Retire free shares",
            snapshot: "Snapshot", loading: "Loading shares…", unavailable: "Share data is unavailable.",
            invalid: "Check the quantity and price. Use whole share counts and amounts with at most two decimals.",
            saved: "Change saved.", company: "Company shares", manager: "Share management",
            myOffers: "My open offers", noOffers: "No open offers.", revoke: "Cancel offer",
            noBoard: "You are not a board member of this company.", noRecipients: "No active recipients available.",
            noSnapshotsForOffer: "Create a company snapshot before offering shares for it.",
            conflict: "The stock or offer changed. The data has been refreshed.",
            forbidden: "You do not have access to this share action.",
        },
    };
    const tr = (key) => (copy[document.documentElement.lang?.slice(0, 2)] || copy.nl)[key] || key;
    const number = (value, digits = 0) => new Intl.NumberFormat(document.documentElement.lang || "nl-BE", {
        minimumFractionDigits: digits, maximumFractionDigits: digits,
    }).format(Number(value || 0));
    const money = (value) => `Fr ${number(value, 2)}`;
    const integer = (value) => /^\d+$/.test(String(value).trim()) && Number.isSafeInteger(Number(value)) ? Number(value) : null;
    const decimal = (value) => {
        const normalized = String(value).trim().replace(",", ".");
        return /^\d+(?:\.\d{1,2})?$/.test(normalized) ? normalized : null;
    };
    const element = (tag, className, content) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (content !== undefined) node.textContent = content;
        return node;
    };
    const metric = (label, value) => {
        const box = element("div", "share-ui-metric");
        box.append(element("span", "share-ui-metric-label", label), element("strong", "", value));
        return box;
    };
    const field = (label, value, inputMode = "numeric") => {
        const wrap = element("label", "form-label share-ui-field", label);
        const input = element("input", "form-control");
        input.type = "text";
        input.inputMode = inputMode;
        input.value = String(value ?? "");
        wrap.appendChild(input);
        return [wrap, input];
    };
    const action = (label, style = "btn-outline-primary") => {
        const icon = label === tr("save") ? "fa-check"
            : label === tr("close") ? "fa-xmark"
            : label === tr("revoke") ? "fa-ban" : "fa-plus";
        const button = AetherActionButtons.button({ label, icon });
        // Behoud plaatsingshulpen, zonder de oude Bootstrap-knopkleuren.
        style.split(/\s+/).filter((name) => name && !name.startsWith("btn")).forEach((name) => button.classList.add(name));
        return button;
    };
    const feedback = (host, message, danger = false) => {
        const target = host.querySelector("[data-share-feedback]");
        if (!target) return;
        target.className = `small mt-2 ${danger ? "text-danger" : "text-success"}`;
        target.textContent = message;
    };
    const errorMessage = (error) => {
        let code = "";
        let serverMessage = "";
        try {
            const body = String(error?.responseBody || "");
            const response = JSON.parse(body);
            code = response.errorCode || "";
            serverMessage = typeof response.error === "string" ? response.error : "";
        } catch (_) { /* A network error has no JSON body. */ }
        if (([404, 409, 503].includes(Number(error?.status)) || code === "shares.event_closed") && serverMessage) return serverMessage;
        if (code === "shares.conflict" || error?.status === 409) return tr("conflict");
        if (code === "shares.forbidden" || error?.status === 403) return tr("forbidden");
        if (code === "shares.invalid_request" || error?.status === 400 || error?.status === 422) return tr("invalid");
        return Number.isInteger(Number(error?.status)) && Number(error.status) > 0
            ? `${tr("unavailable")} (HTTP ${error.status})` : tr("unavailable");
    };
    const post = (endpoint, body) => apiFetchIdempotentJson(`api/shares/${endpoint}.php`, { method: "POST", body });
    const query = (endpoint, key, id) => apiFetchJson(`api/shares/${endpoint}.php?${key}=${encodeURIComponent(id)}`);
    const canTrade = (character) => {
        const user = typeof getCurrentUser === "function" ? getCurrentUser() : window.AETHER_CURRENT_USER;
        return !!character && (userHasPrivilegedRole(user)
            || (["player", "extra"].includes(character.type) && Number(character.idUser) === Number(user?.id)));
    };
    const recipientSelect = (recipients) => {
        const select = element("select", "form-select");
        select.setAttribute("aria-label", tr("recipient"));
        select.add(new Option(tr("recipient"), ""));
        for (const person of recipients) {
            select.add(new Option(`${person.firstName || ""} ${person.lastName || ""}`.trim()
                || `#${person.id}`, person.id));
        }
        return select;
    };

    function companyGrid(host, companies, onOpen) {
        const grid = element("div", "share-company-grid");
        for (const company of companies) {
            const tile = element("button", "share-company-tile");
            tile.type = "button";
            tile.setAttribute("aria-haspopup", "dialog");
            const imageWrap = element("span", "share-company-logo");
            const fallback = element("i", "fa-solid fa-building");
            fallback.setAttribute("aria-hidden", "true");
            imageWrap.appendChild(fallback);
            if (company.logoUrl) {
                const image = element("img", "");
                image.alt = "";
                image.loading = "lazy";
                image.src = company.logoUrl;
                image.addEventListener("error", () => { image.remove(); fallback.hidden = false; });
                fallback.hidden = true;
                imageWrap.appendChild(image);
            }
            tile.append(imageWrap, element("span", "share-company-name", company.companyName || `#${company.idCompany}`));
            tile.addEventListener("click", () => onOpen(company, tile));
            grid.appendChild(tile);
        }
        host.appendChild(grid);
        return grid;
    }

    function companyDialog(company, trigger, renderBody) {
        const dialog = element("dialog", "share-ui-dialog share-company-dialog");
        const title = element("h3", "h4", company.companyName || `#${company.idCompany}`);
        title.id = "share-company-dialog-title";
        dialog.setAttribute("aria-labelledby", title.id);
        const body = element("div", "share-company-dialog-body");
        dialog.append(title, body);
        dialog.addEventListener("close", () => {
            dialog.remove();
            if (trigger?.isConnected) trigger.focus({ preventScroll: true });
        }, { once: true });
        document.body.appendChild(dialog);
        renderBody(body, () => dialog.close());
        dialog.showModal();
        return dialog;
    }

    function detailLine(host, label, value) {
        const row = element("div", "share-detail-line mb-2");
        row.append(element("span", "", label), element("span", "", value));
        host.appendChild(row);
    }

    function periodFromDate(date, outgame = true) {
        const match = String(date || "").match(/^(\d{4})-(\d{2})-/);
        return match ? { purchaseYear: Number(match[1]) - (outgame ? 100 : 0),
            purchaseQuarter: Math.floor((Number(match[2]) - 1) / 3) + 1 } : null;
    }
    function currentGamePeriod() {
        const parts = new Intl.DateTimeFormat("en", { timeZone: "Europe/Brussels", year: "numeric", month: "2-digit" }).formatToParts(new Date());
        return { purchaseYear: Number(parts.find(part => part.type === "year").value) - 100,
            purchaseQuarter: Math.floor((Number(parts.find(part => part.type === "month").value) - 1) / 3) + 1 };
    }
    function eventForPeriod(events, period) {
        if (!period) return null;
        const start = `${period.purchaseYear + 100}-${String((period.purchaseQuarter - 1) * 3 + 1).padStart(2, "0")}-01`;
        const eligible = events.filter(event => String(event.dateEnd || event.dateStart).slice(0, 10) >= start);
        const ongoing = eligible.filter(event => String(event.dateStart).slice(0, 10) <= start)
            .sort((a, b) => String(b.dateStart).localeCompare(String(a.dateStart)) || Number(b.id) - Number(a.id));
        return ongoing[0] || eligible.sort((a, b) => String(a.dateStart).localeCompare(String(b.dateStart)) || Number(a.id) - Number(b.id))[0] || null;
    }
    function purchasePeriodLabel(purchase) {
        return purchase.purchaseYear && purchase.purchaseQuarter
            ? `${purchase.purchaseYear} · K${purchase.purchaseQuarter}`
            : purchase.eventTitle ? `${purchase.eventTitle} (${purchase.eventDate})` : tr("eventUnknown");
    }
    function purchaseQuarterControl(events, options = {}) {
        const wrapper = element("div", "share-purchase-quarter");
        wrapper.appendChild(element("span", "form-label d-block", tr("purchaseQuarter")));
        const group = element("div", "button-group");
        const year = element("select", "form-select");
        const quarter = element("select", "form-select");
        year.setAttribute("aria-label", "Speljaar van aankoop");
        quarter.setAttribute("aria-label", "Kwartaal van aankoop");
        group.append(year, quarter); wrapper.appendChild(group);
        const foundation = periodFromDate(options.foundationDate, false);
        const fallback = options.value || currentGamePeriod();
        const known = events.map(event => periodFromDate(event.dateStart)).filter(Boolean);
        const minYear = Math.max(1000, Math.min(options.includePeriod?.purchaseYear ?? Infinity,
            foundation?.purchaseYear || Math.min(fallback.purchaseYear, ...known.map(period => period.purchaseYear)) - 1));
        const maxYear = Math.min(9899, Math.max(fallback.purchaseYear, ...known.map(period => period.purchaseYear)));
        const current = currentGamePeriod();
        const periods = [];
        for (let y = minYear; y <= maxYear; y++) for (let q = 1; q <= 4; q++) {
            const period = { purchaseYear: y, purchaseQuarter: q };
            const included = options.includePeriod?.purchaseYear === y && options.includePeriod?.purchaseQuarter === q;
            if (!included && foundation && y * 4 + q < foundation.purchaseYear * 4 + foundation.purchaseQuarter) continue;
            if (options.allowPast === false && y * 4 + q < current.purchaseYear * 4 + current.purchaseQuarter) continue;
            const event = eventForPeriod(events, period);
            if (!included && (!event || (options.idEvent && Number(event.id) !== Number(options.idEvent)))) continue;
            periods.push(period);
        }
        const years = [...new Set(periods.map(period => period.purchaseYear))];
        years.forEach(value => year.add(new Option(String(value), String(value))));
        const getValue = () => year.value && quarter.value
            ? { purchaseYear: Number(year.value), purchaseQuarter: Number(quarter.value) } : null;
        const updateQuarter = (preferred) => {
            quarter.replaceChildren();
            periods.filter(period => period.purchaseYear === Number(year.value)).forEach(period => quarter.add(new Option(`K${period.purchaseQuarter}`, period.purchaseQuarter)));
            if (periods.some(period => period.purchaseYear === Number(year.value) && period.purchaseQuarter === preferred)) quarter.value = String(preferred);
            quarter.disabled = !quarter.children.length;
        };
        year.value = years.includes(fallback.purchaseYear) ? String(fallback.purchaseYear) : String(years.at(-1) || "");
        updateQuarter(fallback.purchaseQuarter);
        year.disabled = !years.length;
        year.addEventListener("change", () => { updateQuarter(); options.onChange?.(getValue()); });
        quarter.addEventListener("change", () => options.onChange?.(getValue()));
        return { wrapper, getValue };
    }

    function purchaseQuote(data, companyId, eventId) {
        return (data.purchaseQuotes || []).find(quote => Number(quote.idCompany) === Number(companyId) && Number(quote.idEvent) === Number(eventId));
    }

    function companyPurchaseEvents(data, companyId) {
        return (data.purchaseEvents || []).filter(event => purchaseQuote(data, companyId, event.id));
    }

    function renderPurchaseHistory(host, data, holding, character, refresh) {
        const purchases = (data.purchases || []).filter(purchase => Number(purchase.idCompany) === Number(holding.idCompany));
        host.replaceChildren();
        if (!purchases.length) host.appendChild(element("p", "text-muted", "Geen afzonderlijke aankopen geregistreerd."));
        purchases.forEach(purchase => {
            const row = element("section", "data-card share-purchase-row mb-3");
            const display = () => {
                row.replaceChildren();
                const header = element("div", "d-flex justify-content-between align-items-center gap-2 mb-2");
                header.appendChild(element("h4", "h5 mb-0", purchasePeriodLabel(purchase)));
                const edit = AetherActionButtons.button({ icon: "fa-pen", title: "Aankoop aanpassen", ariaLabel: "Aankoop aanpassen" });
                edit.setAttribute("aria-label", `Aankoop ${purchasePeriodLabel(purchase)} aanpassen`);
                edit.addEventListener("click", editing);
                header.appendChild(AetherActionButtons.group([edit])); row.appendChild(header);
                detailLine(row, tr("purchaseEvent"), purchase.eventTitle || tr("eventUnknown"));
                detailLine(row, tr("quantity"), number(purchase.shareCount));
                detailLine(row, tr("price"), purchase.unitPrice == null ? "Onbekend" : money(purchase.unitPrice));
                detailLine(row, "Totaalbedrag", purchase.unitPrice == null ? "Onbekend" : money(purchase.totalAmount));
                if (purchase.purchaseDateSource === "character_created") row.appendChild(element("p", "small text-muted mb-0",
                    "Aankoopkwartaal aangevuld op basis van de aanmaakdatum van het personage."));
            };
            const editing = () => {
                row.replaceChildren();
                const form = element("form", "share-ui-form-grid");
                const events = companyPurchaseEvents(data, holding.idCompany);
                const retainedPeriod = purchase.idEvent == null && purchase.purchaseYear ? {
                    purchaseYear: Number(purchase.purchaseYear), purchaseQuarter: Number(purchase.purchaseQuarter)
                } : null;
                let periodControl;
                const priceLine = element("p", "small mb-0");
                const [countWrap, count] = field(tr("quantity"), purchase.shareCount);
                const toggle = element("label", "d-flex align-items-center gap-2 share-purchase-bank-switch");
                const switchControl = element("span", "company-listing-switch");
                const bank = element("input", "company-listing-switch-input"); bank.type = "checkbox"; bank.checked = false;
                bank.disabled = purchase.unitPrice == null;
                const slider = element("span", "company-listing-switch-slider"); slider.setAttribute("aria-hidden", "true");
                switchControl.append(bank, slider);
                toggle.append(switchControl, element("span", "", tr("adjustBank")));
                const save = action(tr("save")); save.type = "submit";
                const effective = () => {
                    const period = periodControl?.getValue() || (!events.length ? retainedPeriod : null);
                    const sameFoundation = purchase.idEvent == null && period?.purchaseYear === Number(purchase.purchaseYear)
                        && period?.purchaseQuarter === Number(purchase.purchaseQuarter);
                    const event = sameFoundation ? { id: null, title: purchase.legacyOwnership ? "Historisch bezit" : "Oprichting" } : eventForPeriod(events, period);
                    const quote = purchaseQuote(data, holding.idCompany, event?.id);
                    const price = Number(event?.id) === Number(purchase.idEvent) ? purchase.unitPrice : quote?.shareMarketPrice;
                    return { period, event, price, total: Number(price) * Number(count.value) };
                };
                const update = () => {
                    const selected = effective();
                    const unknownUnchanged = purchase.unitPrice == null && selected.price == null && selected.event;
                    save.disabled = !selected.period || !selected.event || (!unknownUnchanged && !(Number(selected.price) > 0)) || !integer(count.value);
                    priceLine.textContent = save.disabled ? "Voor dit kwartaal is geen geldige snapshotprijs beschikbaar."
                        : unknownUnchanged ? "De oorspronkelijke aankoopprijs is niet geregistreerd. Het aantal en kwartaal kunnen worden aangepast zonder bankboeking."
                        : purchase.unitPrice == null ? `${selected.event.title} · ${money(selected.price)} per aandeel · totaal ${money(selected.total)}. Het oorspronkelijke aankoopbedrag is onbekend; er volgt geen bankboeking.`
                        : `${selected.event.title} · ${money(selected.price)} per aandeel · totaal ${money(selected.total)} · verschil ${money(selected.total - Number(purchase.totalAmount))}. ${bank.checked ? "Het bankverschil wordt mee geboekt." : "De bankrekening wordt niet aangepast."}`;
                };
                periodControl = purchaseQuarterControl(events, { value: purchase.purchaseYear ? purchase : periodFromDate(purchase.eventDate),
                    foundationDate: holding.foundationDate, onChange: update,
                    includePeriod: eventForPeriod(events, retainedPeriod) ? retainedPeriod : null });
                if (!events.length && retainedPeriod) form.appendChild(element("p", "small mb-0",
                    `Bestaand aankoopkwartaal: ${purchasePeriodLabel(purchase)}. Er is geen bedrijfssnapshot beschikbaar om een ander kwartaal te kiezen.`));
                count.addEventListener("input", update); bank.addEventListener("change", update);
                form.append(periodControl.wrapper, countWrap, toggle, priceLine, AetherActionButtons.group([save]));
                if (purchase.draftCapitalPurchase) form.appendChild(element("p", "small text-muted", "Deze aankoop is betaald uit aandelenstartkapitaal. Het bestede startkapitaal wordt mee gecorrigeerd."));
                if (purchase.idCounterpartyCharacter) form.appendChild(element("p", "small text-muted", "Bij een bankcorrectie wordt ook de rekening van de oorspronkelijke verkoper aangepast."));
                form.appendChild(element("div", "")).dataset.shareFeedback = "1";
                form.addEventListener("submit", async event => {
                    event.preventDefault(); update(); if (save.disabled) return;
                    save.disabled = true;
                    try {
                        await post("updateSharePurchase", { idCompany: Number(holding.idCompany), idCharacter: Number(character.id),
                            idTransaction: Number(purchase.id), revision: Number(purchase.revision || 0), shareCount: integer(count.value),
                            ...effective().period, adjustBank: bank.checked });
                        await refresh();
                    } catch (error) { feedback(form, errorMessage(error), true); update(); }
                });
                row.appendChild(form); update();
            };
            display(); host.appendChild(row);
        });
    }

    function showHolding(holding, character, reload, trigger, purchases, data) {
        let historySelected = false;
        let historyInitialized = false, historyInitializing = false;
        const renderBody = (dialogBody, close) => {
            dialogBody.replaceChildren();
            const body = element("div", "share-holding-summary");
            dialogBody.appendChild(body);
            if (data.canEditPurchases) {
                const tabs = element("div", "nav nav-tabs share-purchase-tabs mb-3");
                tabs.setAttribute("role", "tablist");
                const history = element("div", "share-holding-purchases");
                const buttons = [];
                const refreshData = async () => {
                    data = await query("getCharacterPortfolio", "idCharacter", character.id);
                    holding = (data.holdings || []).find(row => Number(row.idCompany) === Number(holding.idCompany)) || holding;
                    purchases = (data.purchases || []).filter(row => Number(row.idCompany) === Number(holding.idCompany));
                    renderBody(dialogBody, close);
                    await reload();
                };
                const initializeHistory = async () => {
                    if (historyInitialized || historyInitializing) return;
                    historyInitializing = true;
                    history.replaceChildren(element("p", "text-muted", tr("loading")));
                    try {
                        await post("initializeSharePurchaseHistory", { idCompany: Number(holding.idCompany), idCharacter: Number(character.id) });
                        const fresh = await query("getCharacterPortfolio", "idCharacter", character.id);
                        historyInitialized = true;
                        data = fresh;
                        holding = (data.holdings || []).find(row => Number(row.idCompany) === Number(holding.idCompany)) || holding;
                        purchases = (data.purchases || []).filter(row => Number(row.idCompany) === Number(holding.idCompany));
                        if (dialogBody.isConnected) renderBody(dialogBody, close);
                        await reload();
                    } catch (error) {
                        history.replaceChildren(element("p", "text-danger", errorMessage(error)));
                        const retry = AetherActionButtons.button({ icon: "fa-rotate-right", label: "Opnieuw proberen" });
                        retry.addEventListener("click", () => { void initializeHistory(); });
                        history.appendChild(AetherActionButtons.group([retry]));
                    } finally { historyInitializing = false; }
                };
                const switchTab = index => {
                    historySelected = index === 1;
                    body.hidden = historySelected; history.hidden = !historySelected;
                    buttons.forEach((button, i) => {
                        button.className = `nav-link${i === index ? " active" : ""}`;
                        button.setAttribute("aria-selected", String(i === index));
                        button.tabIndex = i === index ? 0 : -1;
                    });
                    if (historySelected) void initializeHistory();
                };
                [tr("purchaseSummary"), tr("purchases")].forEach((text, index) => {
                    const tab = element("button", "nav-link", text); tab.type = "button";
                    tab.setAttribute("role", "tab");
                    const tabId = `share-${character.id}-${holding.idCompany}-tab-${index}`;
                    const paneId = `${tabId}-pane`;
                    tab.id = tabId; tab.setAttribute("aria-controls", paneId);
                    const pane = index === 0 ? body : history;
                    pane.id = paneId; pane.setAttribute("role", "tabpanel"); pane.setAttribute("aria-labelledby", tabId);
                    tab.addEventListener("click", () => switchTab(index));
                    tab.addEventListener("keydown", event => {
                        if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
                        event.preventDefault(); const next = event.key === "Home" ? 0 : event.key === "End" ? 1 : 1 - index;
                        switchTab(next); buttons[next].focus();
                    });
                    buttons.push(tab); tabs.appendChild(tab);
                });
                dialogBody.replaceChildren(tabs, body, history);
                switchTab(historySelected ? 1 : 0);
                renderPurchaseHistory(history, data, holding, character, async () => {
                    historySelected = true; await refreshData();
                    if (typeof getCharacter === "function") await getCharacter(Number(character.id), null, "economy");
                });
            }
            detailLine(body, tr("quantity"), number(holding.shareCount));
            if (Number(holding.issuedShareCount) > 0) detailLine(body, "Aandeel in het bedrijf", `${number(Number(holding.shareCount) / Number(holding.issuedShareCount) * 100, 2)}%`);
            const price = holding.marketUnitPrice;
            detailLine(body, "Huidige marktwaarde per aandeel", price == null ? "—" : money(price));
            detailLine(body, "Totale marktwaarde", price == null ? "—" : money(Number(price) * Number(holding.shareCount)));
            detailLine(body, tr("intrinsic"), money(holding.intrinsicValue));
            if (data.canManagePurchases) {
                const removeHolding = AetherActionButtons.button({ icon: "fa-trash", label: tr("removeHolding") });
                removeHolding.addEventListener("click", async () => {
                    if (typeof window.confirm === "function"
                        && !window.confirm("Alle aandelen van dit personage worden naar naamloze handen verplaatst. Doorgaan?")) return;
                    removeHolding.disabled = true;
                    try {
                        await post("removeCharacterHolding", { idCompany: Number(holding.idCompany), idCharacter: Number(character.id) });
                        close();
                        await reload();
                        if (typeof getCharacter === "function") await getCharacter(Number(character.id), null, "economy");
                    } catch (error) {
                        feedback(body, errorMessage(error), true);
                        removeHolding.disabled = false;
                    }
                });
                body.appendChild(AetherActionButtons.group([removeHolding]));
            }
            if (purchases.length && !data.canEditPurchases) {
                const history = element("details", "my-3");
                history.appendChild(element("summary", "", tr("purchases")));
                for (const purchase of purchases) {
                    history.appendChild(element("p", "small mt-2", `${purchasePeriodLabel(purchase)} · ${number(purchase.shareCount)} ${tr("shares")} · ${money(purchase.totalAmount)}`));
                }
                body.appendChild(history);
            }
            if (character.state !== "active" || !canTrade(character)) return;
            body.appendChild(element("h4", "h5 mt-3", tr("sell")));
            body.appendChild(element("p", "small text-muted", "Bied aandelen aan een ander personage aan. De verkoop wordt voltooid wanneer de ontvanger het aanbod koopt."));
            const formHost = element("div", "mb-3", tr("loading"));
            body.appendChild(formHost);
            query("getShareRecipientOptions", "idCharacter", character.id).then(response => {
                if (!formHost.isConnected) return;
                renderOfferForm(formHost, response?.recipients || [], [{
                    id: holding.idCompany, name: holding.companyName, max: holding.shareCount
                }], async (companyId, recipientId, count, unitPrice) => {
                    await post("createHolderOffer", { idCompany: companyId,
                        idSellingCharacter: Number(character.id), idRecipientCharacter: recipientId,
                        shareCount: count, unitPrice });
                    close();
                    await reload();
                }, { defaultPrice: price });
            }).catch(error => { formHost.textContent = errorMessage(error); });
        };
        companyDialog(holding, trigger, renderBody);
    }

    function attachPortfolio(host, character) {
        const id = Number(character?.id || 0);
        if (!host || !id) return;
        const card = element("section", "card aether-panel shadow-sm mb-4 share-ui-portfolio");
        const content = element("div", "share-ui-portfolio-content", tr("loading"));
        card.appendChild(content);
        host.appendChild(card);
        let renderedSignature = null;
        let requestId = 0;
        async function load(force = false) {
            if (!card.isConnected) return;
            const currentRequest = ++requestId;
            try {
                const [data, created] = await Promise.all([
                    query("getCharacterPortfolio", "idCharacter", id),
                    canTrade(character) ? query("getCreatedShareOffers", "idCharacter", id) : Promise.resolve(null),
                ]);
                if (!card.isConnected || currentRequest !== requestId) return;
                // Background refreshes must never replace an open offer form or its draft.
                if (!force && content.querySelector("details[open]")) return;
                const signature = JSON.stringify([data, created]);
                if (force || signature !== renderedSignature) {
                    renderPortfolio(content, data, created, character, () => load(true));
                    renderedSignature = signature;
                    content.querySelectorAll("details").forEach((details) => {
                        details.addEventListener("toggle", () => {
                            if (!details.open && details.isConnected) void load();
                        });
                    });
                }
            } catch (error) {
                if (card.isConnected && currentRequest === requestId
                    && !content.querySelector("details[open]")) {
                    feedback(content, errorMessage(error), true);
                    if (renderedSignature === null) content.textContent = errorMessage(error);
                }
            }
        }
        void load();
        card.dataset.characterId = String(id);
        card.aetherRefresh = load;
        return card;
    }

    function renderPortfolio(card, data, created, character, reload) {
        card.replaceChildren(element("h3", "h4", tr("portfolio")));
        const status = data?.status || {};
        detailLine(card, tr("intrinsic"), money(status.intrinsicPortfolioValue));
        const holdings = Array.isArray(data?.holdings) ? data.holdings : [];
        if (!holdings.length) card.appendChild(element("p", "text-muted mb-0", tr("emptyPortfolio")));
        companyGrid(card, holdings, (holding, trigger) => showHolding(holding, character, reload, trigger,
            (data?.purchases || []).filter(purchase => Number(purchase.idCompany) === Number(holding.idCompany)), data));
        if (character?.state === "draft") renderDraftPurchaseForm(card, data, character, reload);
        else if (data?.canManagePurchases) renderManagerPurchaseButton(card, data, character, reload);
        if (canTrade(character) && created?.offers?.length) {
            const offers = created.offers;
            const details = element("details", "mt-3");
            details.appendChild(element("summary", "", `${tr("myOffers")} (${offers.length})`));
            for (const offer of offers) {
                const row = element("div", "share-ui-holding");
                row.appendChild(element("span", "", `${offer.companyName} · ${number(
                    Number(offer.offeredShareCount) - Number(offer.purchasedShareCount)
                )} ${tr("shares")} · ${money(offer.unitPrice)}`));
                const revoke = action(tr("revoke"), "btn-outline-danger btn-sm");
                revoke.addEventListener("click", async () => {
                    revoke.disabled = true;
                    try { await post("cancelOffer", { idOffer: Number(offer.id) }); await reload(); }
                    catch (error) { feedback(card, errorMessage(error), true); revoke.disabled = false; }
                });
                row.appendChild(AetherActionButtons.group([revoke]));
                details.appendChild(row);
            }
            if (!offers.length) details.appendChild(element("p", "text-muted", tr("noOffers")));
            card.appendChild(details);
        }
        card.appendChild(element("div", "", "")).dataset.shareFeedback = "1";
    }

    function renderDraftPurchaseForm(card, data, character, reload) {
        const budget = data.draftBudget;
        const openBudget = budget && budget.closedAt === null;
        const metrics = element("div", "share-ui-metrics mt-3");
        metrics.append(metric("Startkapitaal Aandelen", money(openBudget ? budget.budgetAmount : 0)),
            metric("Besteed startkapitaal", money(budget?.spentAmount || 0)),
            metric("Beschikbaar aankoopbedrag", money(openBudget ? budget.remainingAmount : 0)));
        card.append(metrics);
        card.append(element("p", "small text-muted mt-2", openBudget
            ? "Alle beschikbare bedrijfsaandelen zijn zichtbaar tijdens Draft. Je betaalt de snapshotmarktprijs, zonder vaardigheidsaanpassing. Ongebruikt startkapitaal vervalt na Draft."
            : "Neem eerst de statuseigenschap Aandelen om tijdens Draft startkapitaal te gebruiken. Een eerder afgesloten budget wordt niet opnieuw beschikbaar."));
        const quotes = (data.draftQuotes || []).filter(quote => Number(quote.freeShareCount) > 0);
        if (!quotes.length) { card.append(element("p", "text-muted", "Geen bedrijfssnapshots met een marktprijs beschikbaar.")); return; }
        card.appendChild(element("h4", "h5 mt-3", "Beschikbare aandelen"));
        const companies = [...new Map(quotes.map(quote => [Number(quote.idCompany), quote])).values()];
        companyGrid(card, companies, (company, trigger) => companyDialog(company, trigger, (body, close) => {
            renderDraftQuoteForm(body, data, character, async () => { close(); await reload(); },
                quotes.filter(quote => Number(quote.idCompany) === Number(company.idCompany)));
        }));
    }

    function renderDraftQuoteForm(card, data, character, reload, quotes) {
        const budget = data.draftBudget;
        const openBudget = budget && budget.closedAt === null;
        card.appendChild(element("p", "small text-muted", "Je koopt aan de marktprijs van de gekozen snapshot. Het aankoopbedrag wordt van je beschikbare startkapitaal afgetrokken."));
        const form = element("form", "share-ui-form-grid mt-2 mb-3");
        const wrap = element("label", "form-label", data.canChooseDraftSnapshot ? "Bedrijf en snapshot" : "Beschikbare aandelen");
        const select = element("select", "form-select"); select.setAttribute("aria-label", "Aandelen kopen tijdens Draft");
        quotes.forEach((quote, index) => select.add(new Option(
            `${quote.companyName} · ${quote.eventTitle || "Oprichting"}${quote.quoteDate ? ` (${quote.quoteDate})` : ""} · ${money(quote.unitPrice)} · ${number(quote.freeShareCount)} vrij`, index)));
        select.value = "0"; wrap.append(select);
        const [countWrap, count] = field(tr("quantity"), "1");
        const total = element("p", "mb-0");
        const periodHost = element("div", "");
        let quarterControl = null;
        const save = action("Aandelen kopen"); save.type = "submit";
        const update = () => {
            const quote = quotes[Number(select.value)]; const quantity = integer(count.value);
            total.textContent = quote && quantity ? `Aankoopbedrag: ${money(Number(quote.unitPrice) * quantity)}` : "";
            save.disabled = !data.canBuyDraftShares || !openBudget || !quantity || !quote
                || quantity > Number(quote.freeShareCount) || Number(quote.unitPrice) * quantity > Number(budget?.remainingAmount || 0);
            if (quarterControl && !quarterControl.getValue()) save.disabled = true;
        };
        const updatePeriod = () => {
            const quote = quotes[Number(select.value)];
            quarterControl = null; periodHost.replaceChildren();
            if (data.canChooseDraftSnapshot && quote?.idEvent && data.purchaseEvents?.length) {
                quarterControl = purchaseQuarterControl(data.purchaseEvents, {
                    value: periodFromDate(quote.quoteDate), idEvent: Number(quote.idEvent), allowPast: true,
                    foundationDate: quote.foundationDate, onChange: update
                });
                periodHost.appendChild(quarterControl.wrapper);
            } else if (quote?.quoteDate) {
                const period = data.canChooseDraftSnapshot ? periodFromDate(quote.quoteDate, false) : currentGamePeriod();
                if (period) periodHost.appendChild(element("p", "small mb-0", `${tr("purchaseQuarter")}: ${period.purchaseYear} · K${period.purchaseQuarter}`));
            }
            update();
        };
        select.addEventListener("change", updatePeriod); count.addEventListener("input", update);
        form.append(wrap, periodHost, countWrap, total, AetherActionButtons.group([save]));
        form.appendChild(element("div", "")).dataset.shareFeedback = "1";
        form.addEventListener("submit", async event => {
            event.preventDefault(); update(); if (save.disabled) return;
            const quote = quotes[Number(select.value)]; save.disabled = true;
            try {
                await post("buyDraftShares", { idCharacter: Number(character.id), idCompany: Number(quote.idCompany),
                    idSnapshot: Number(quote.idSnapshot), shareCount: integer(count.value), ...(quarterControl?.getValue() || {}) });
                await reload();
                if (typeof getCharacter === "function") await getCharacter(Number(character.id), null, "economy");
            } catch (error) { feedback(form, errorMessage(error), true); update(); }
        });
        updatePeriod(); card.append(form);
    }

    function renderManagerPurchaseButton(card, data, character, reload) {
        const add = AetherActionButtons.button({ icon: "fa-plus", label: tr("addPurchase") });
        const heading = card.children[0];
        const header = element("div", "d-flex justify-content-between align-items-start gap-2");
        card.replaceChildren(header, ...Array.from(card.children).slice(1));
        header.append(heading, AetherActionButtons.group([add]));
        add.addEventListener("click", () => companyDialog({companyName: tr("addPurchase")}, add, (body) => {
            const quotes = data.managerQuotes || [];
            const select = element("select", "form-select mb-3");
            select.setAttribute("aria-label", "Snapshot voor aandelenaankoop");
            const events = [...new Map(quotes.map(quote => [Number(quote.idEvent), quote])).values()];
            events.forEach(quote => select.add(new Option(`${quote.eventTitle} (${quote.quoteDate})`, quote.idEvent)));
            const list = element("div", "");
            const render = () => {
                list.replaceChildren();
                companyGrid(list, quotes.filter(quote => Number(quote.idEvent) === Number(select.value)), (quote, trigger) => {
                    companyDialog(quote, trigger, (purchaseBody, close) => {
                        const company = (data.purchaseCompanies || []).find(item => Number(item.id) === Number(quote.idCompany));
                        renderPurchaseForm(purchaseBody, {...data, purchaseCompanies: company ? [company] : [],
                            purchaseEvents: (data.purchaseEvents || []).filter(event => Number(event.id) === Number(quote.idEvent)),
                            defaultPurchaseEventId: Number(quote.idEvent)}, character, async () => { close(); await reload(); });
                    });
                });
            };
            select.addEventListener("change", render);
            body.append(select, list);
            if (!events.length) body.appendChild(element("p", "text-muted", "Geen snapshots met vrije aandelen beschikbaar."));
            render();
        }));
    }

    function renderPurchaseForm(card, data, character, reload) {
        const details = element("details", "mt-3 share-ui-purchase-manager");
        details.open = true;
        details.appendChild(element("summary", "", tr("addPurchase")));
        card.appendChild(details);
        const events = data.purchaseEvents || [];
        const companies = data.purchaseCompanies || [];
        if (!events.length || !companies.length) {
            details.appendChild(element("p", "text-muted", tr("setupPurchase")));
            return;
        }
        const form = element("form", "share-ui-form-grid mt-2");
        const periodHost = element("div", "");
        const companyWrap = element("label", "form-label", tr("company"));
        const companySelect = element("select", "form-select");
        for (const company of companies) companySelect.add(new Option(company.companyName, company.id));
        companyWrap.appendChild(companySelect);
        const [countWrap, count] = field(tr("quantity"), "1");
        const priceLine = element("p", "small mb-0");
        let period;
        const save = action(tr("save")); save.type = "submit";
        const update = () => {
            const event = eventForPeriod(companyPurchaseEvents(data, companySelect.value), period?.getValue());
            const price = purchaseQuote(data, companySelect.value, event?.id)?.shareMarketPrice;
            save.disabled = !event || !(Number(price) > 0) || !integer(count.value);
            priceLine.textContent = save.disabled ? "Geen geldige snapshotprijs voor dit kwartaal."
                : `${event.title} · ${money(price)} per aandeel · totaal ${money(Number(price) * Number(count.value))}`;
        };
        const rebuildPeriod = () => {
            const company = companies.find(company => Number(company.id) === Number(companySelect.value));
            const defaultEvent = events.find(event => Number(event.id) === Number(data.defaultPurchaseEventId));
            period = purchaseQuarterControl(companyPurchaseEvents(data, companySelect.value), { value: period?.getValue() || periodFromDate(defaultEvent?.dateStart) || currentGamePeriod(),
                foundationDate: company?.foundationDate, onChange: update });
            periodHost.replaceChildren(period.wrapper); update();
        };
        companySelect.addEventListener("change", rebuildPeriod); count.addEventListener("input", update);
        form.append(periodHost, companyWrap, countWrap, priceLine, AetherActionButtons.group([save]));
        form.appendChild(element("div", "")).dataset.shareFeedback = "1";
        details.append(element("p", "small text-muted mt-2", tr("purchaseDebits")), form);
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const quantity = integer(count.value), selected = period.getValue();
            update();
            if (!quantity || save.disabled || !selected || !companySelect.value) {
                feedback(form, tr("invalid"), true); return;
            }
            save.disabled = true;
            try {
                await post("addSharePurchase", { idCharacter: Number(character.id), idCompany: Number(companySelect.value),
                    ...selected, shareCount: quantity });
                await reload();
                if (typeof getCharacter === "function") await getCharacter(Number(character.id), null, "economy");
            } catch (error) { feedback(form, errorMessage(error), true); }
            finally { update(); }
        });
        rebuildPeriod();
    }

    function renderOfferForm(target, recipients, companies, submit, options = {}) {
        target.replaceChildren();
        if (!recipients.length) {
            target.appendChild(element("p", "text-muted", tr("noRecipients")));
            return;
        }
        const company = element("select", "form-select");
        company.setAttribute("aria-label", tr("company"));
        for (const item of companies) company.add(new Option(item.name || `#${item.id}`, item.id));
        const recipient = recipientSelect(recipients);
        const [countWrap, count] = field(tr("quantity"), "1");
        const [priceWrap, price] = field(tr("price"), options.defaultPrice ?? "", "decimal");
        const submitButton = action(tr("create"), "btn-primary");
        const form = element("div", "share-ui-form-grid");
        if (!options.fixedCompany) form.appendChild(company);
        form.append(recipient, countWrap, priceWrap, AetherActionButtons.group([submitButton]));
        target.appendChild(form);
        target.appendChild(element("div", "")).dataset.shareFeedback = "1";
        submitButton.addEventListener("click", async () => {
            const selected = companies.find((item) => Number(item.id) === Number(company.value));
            const quantity = integer(count.value);
            const unitPrice = decimal(price.value);
            const recipientId = integer(recipient.value);
            if (!selected || !recipientId || !quantity || quantity > Number(selected.max)
                || !unitPrice || Number(unitPrice) <= 0) {
                feedback(target, tr("invalid"), true);
                return;
            }
            submitButton.disabled = true;
            try {
                await submit(Number(selected.id), recipientId, quantity, unitPrice);
                feedback(target, tr("saved"));
            } catch (error) {
                feedback(target, errorMessage(error), true);
            } finally { submitButton.disabled = false; }
        });
    }

    async function attachCompany(host, idCompany, companyData = null, afterMutation = null, listingHost = null) {
        const id = Number(idCompany || 0);
        if (!host) return;
        if (!id) { host.classList.add("d-none"); host.replaceChildren(); listingHost?.replaceChildren(); return; }
        host.classList.remove("d-none");
        host.textContent = tr("loading");
        const currentId = String(id);
        host.dataset.companyId = currentId;
        async function load() {
            try {
                const data = await query("getCompanyShares", "idCompany", id);
                if (host.dataset.companyId === currentId) {
                    const reload = afterMutation || load;
                    renderCompany(host, data, companyData, reload);
                    if (listingHost) renderCompanyListing(listingHost, data, reload);
                }
            } catch (error) {
                if (!error?.status) console.error("Aandelenkaart kon niet worden getekend:", error);
                if (host.dataset.companyId === currentId) host.textContent = errorMessage(error);
            }
        }
        await load();
    }

    function renderCompany(host, data, companyData, reload) {
        host.replaceChildren(element("h3", "h5", "Aandeelhouders"));
        const company = data.company || {};
        const stock = data.stock || {};
        const sold = Number(stock.held || 0) + Number(stock.anonymous || 0);
        const issued = Number(stock.issued || 0);
        const metrics = element("div", "share-ui-metrics");
        metrics.append(metric(tr("issued"), number(issued)), metric(tr("sold"), number(sold)),
            metric(tr("free"), number(stock.free)));
        host.appendChild(metrics);
        if (Array.isArray(data.holdings) && data.holdings.length) {
            const list = element("div", "table-responsive share-ui-holdings mt-2");
            const table = element("table", "table aether-table align-middle mb-0");
            const head = element("thead", "");
            const groupHeadings = element("tr", "");
            const nameHeading = element("th", "", tr("name")); nameHeading.setAttribute("scope", "col");
            const sharesHeading = element("th", "text-end", "Aandelen"); sharesHeading.setAttribute("scope", "colgroup"); sharesHeading.colSpan = 2;
            const characterHeading = element("th", "share-holder-character-column"); characterHeading.setAttribute("scope", "col");
            groupHeadings.append(nameHeading, sharesHeading);
            if (data.canManageShares) groupHeadings.appendChild(characterHeading);
            head.appendChild(groupHeadings);
            const rows = element("tbody", "");
            for (const holding of data.holdings) {
                const person = (companyData?.personnelEntries || []).find((p) => Number(p.idCharacter) === Number(holding.idCharacter));
                const name = holding.displayName || person?.nameLabel || person?.displayName || `#${holding.idCharacter}`;
                const row = element("tr", "");
                row.append(element("td", "", name), element("td", "text-end text-nowrap", number(holding.shareCount)),
                    element("td", "text-end text-nowrap", issued > 0 ? `${number(Number(holding.shareCount) / issued * 100, 2)}%` : "—"));
                if (data.canManageShares) {
                    const cell = element("td", "text-end share-holder-character-column");
                    const link = element("a", "data-info-button share-holder-character-link");
                    const companyHolder = Number(holding.idHoldingCompany) > 0;
                    link.href = companyHolder ? `companies.html?company=${Number(holding.idHoldingCompany)}`
                        : `index.html?character=${Number(holding.idCharacter)}&tab=economy`;
                    link.setAttribute("aria-label", `${name}: ${companyHolder ? "bedrijf" : "economie"} bekijken`);
                    link.title = `${name}: ${companyHolder ? "bedrijf" : "economie"} bekijken`;
                    const icon = element("i", companyHolder ? "fa-solid fa-industry" : "fa-solid fa-user"); icon.setAttribute("aria-hidden", "true");
                    link.appendChild(icon); cell.appendChild(link); row.appendChild(cell);
                }
                rows.appendChild(row);
            }
            table.append(head, rows); list.appendChild(table);
            host.appendChild(list);
        }
        if (!data.holdings?.length) host.appendChild(element("p", "text-muted mb-0", "Nog geen aandeelhouders."));
        if (data.canManageShares || (Array.isArray(data.myBoardCharacterIds) && data.myBoardCharacterIds.length)) {
            renderPrivateOffer(host, data, reload, companyData);
        }
    }

    function renderCompanyListing(host, data, reload) {
        host.replaceChildren();
        const listed = Number(data.company?.isShareListed) === 1;
        const label = element("label", "company-listing-switch");
        const input = element("input", "company-listing-switch-input");
        input.type = "checkbox";
        input.checked = listed;
        input.disabled = !data.canManageShares;
        input.setAttribute("aria-label", "Beursgenoteerd");
        const slider = element("span", "company-listing-switch-slider");
        slider.setAttribute("aria-hidden", "true");
        label.append(input, slider);
        const status = element("span", "company-listing-status", listed ? "Ja" : "Nee");
        status.setAttribute("aria-live", "polite");
        host.append(label, status);
        if (!data.canManageShares) return;
        input.addEventListener("change", async () => {
            input.disabled = true;
            status.textContent = input.checked ? "Ja" : "Nee";
            try { await post("setShareListing", { idCompany: Number(data.company.id), isShareListed: input.checked }); await reload(); }
            catch (error) {
                input.checked = listed;
                input.disabled = false;
                status.textContent = listed ? "Ja" : "Nee";
                host.appendChild(element("p", "text-danger mb-0", errorMessage(error)));
            }
        });
    }

    function managerForm(parent, label, fields, endpoint, build, reload) {
        const form = element("form", "share-ui-form-grid mt-2");
        const inputs = fields.map(([name, value, mode]) => {
            const [wrap, input] = field(name, value, mode || "numeric");
            form.appendChild(wrap);
            return input;
        });
        const save = action(tr("save"));
        save.type = "submit";
        form.appendChild(AetherActionButtons.group([save]));
        form.appendChild(element("div", "")).dataset.shareFeedback = "1";
        const details = element("details", "share-ui-manager-section mt-2");
        details.append(element("summary", "", label), form);
        parent.appendChild(details);
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const body = build(inputs.map((input) => input.value));
            if (!body) { feedback(form, tr("invalid"), true); return; }
            save.disabled = true;
            try { await post(endpoint, body); await reload(); }
            catch (error) { feedback(form, errorMessage(error), true); }
            finally { save.disabled = false; }
        });
    }

    function managerToggle(parent, label, checked, endpoint, body, reload) {
        const form = element("form", "share-ui-toggle mt-2");
        const control = element("label", "form-check-label");
        const input = element("input", "form-check-input me-2");
        input.type = "checkbox";
        input.checked = checked;
        control.append(input, document.createTextNode(label));
        const save = action(tr("save"));
        save.type = "submit";
        form.append(control, AetherActionButtons.group([save]));
        form.appendChild(element("div", "")).dataset.shareFeedback = "1";
        parent.appendChild(form);
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            save.disabled = true;
            try { await post(endpoint, { ...body, [endpoint === "setShareListing" ? "isShareListed" : "isBoardMember"]: input.checked }); await reload(); }
            catch (error) { feedback(form, errorMessage(error), true); }
            finally { save.disabled = false; }
        });
    }

    function renderCompanyManager(host, data, companyData, reload) {
        const idCompany = Number(data.company.id);
        const stock = data.stock || {};
        const pane = element("div", "share-ui-manager mt-3");
        pane.appendChild(element("h4", "h6", tr("manager")));
        host.appendChild(pane);
        managerForm(pane, tr("initialCount"), [
            [tr("initialValue"), data.company.initialCompanyValue, "decimal"],
            [tr("initialCount"), data.company.initialShareCount]],
        "setInitialShares", ([value, count]) => decimal(value) !== null && integer(count) > 0
            ? { idCompany, initialCompanyValue: decimal(value), initialShareCount: integer(count) } : null, reload);
        managerForm(pane, tr("companyValue"), [[tr("companyValue"), data.company.companyValue, "decimal"]],
            "setCompanyValue", ([value]) => decimal(value) !== null
                ? { idCompany, companyValue: decimal(value) } : null, reload);
        managerToggle(pane, tr("listed"), Number(data.company.isShareListed) === 1,
            "setShareListing", { idCompany }, reload);
        managerForm(pane, tr("anonymous"), [[tr("anonymous"), stock.anonymous]],
            "setAnonymousShares", ([value]) => integer(value) !== null
                ? { idCompany, shareCount: integer(value) } : null, reload);
        managerForm(pane, tr("buyback"), [[tr("quantity"), "1"]],
            "buybackFreeShares", ([value]) => integer(value) > 0 && integer(value) <= Number(stock.free)
                ? { idCompany, shareCount: integer(value) } : null, reload);
        for (const snapshot of data.snapshots || []) {
            managerForm(pane, `${snapshot.eventTitle || `${tr("snapshot")} #${snapshot.id}`} · ${tr("market")}`,
                [[tr("price"), snapshot.shareMarketPrice ?? "", "decimal"]],
                "setShareMarketPrice", ([value]) => decimal(value) !== null && Number(decimal(value)) > 0
                    ? { idCompany, idSnapshot: Number(snapshot.id), unitPrice: decimal(value) } : null, reload);
        }
        for (const person of companyData?.personnelEntries || []) {
            const idCharacter = Number(person.idCharacter || 0);
            if (!idCharacter) continue;
            const checked = (data.boardCharacterIds || []).some((id) => Number(id) === idCharacter);
            managerToggle(pane, `${tr("board")}: ${person.nameLabel || person.displayName || `#${idCharacter}`}`,
                checked, "setCompanyBoardMember", { idCompany, idCharacter }, reload);
        }
    }

    function renderPrivateOffer(host, data, reload, companyData) {
        const open = action(tr("privateOffer"));
        const buttons = [open];
        if (data.canManageShares) {
            const parent = AetherActionButtons.button({ icon: "fa-industry", label: "Aandelen moederbedrijf" });
            parent.disabled = data.canAddCompanyHolding === false || Number(data.stock?.free || 0) < 1;
            if (data.canAddCompanyHolding === false) parent.title = "Voer eerst migratie 0023_company_share_ownership uit.";
            parent.addEventListener("click", () => showCompanyHoldingDialog(data, parent, reload));
            buttons.push(parent);
        }
        const group = AetherActionButtons.group(buttons);
        group.classList.add("mt-3");
        host.appendChild(group);
        const dialog = element("dialog", "share-ui-dialog");
        dialog.appendChild(element("h4", "h5", tr("privateOffer")));
        const seller = element("select", "form-select mb-3");
        seller.setAttribute("aria-label", tr("seller"));
        if (data.canManageShares) seller.add(new Option(data.company.companyName || tr("company"), 0));
        for (const id of data.myBoardCharacterIds) {
            const person = (companyData?.personnelEntries || []).find((item) => Number(item.idCharacter) === Number(id));
            seller.add(new Option(person?.nameLabel || person?.displayName || `#${id}`, id));
        }
        if (!data.canManageShares && data.myBoardCharacterIds.length > 1) dialog.appendChild(seller);
        const snapshotSelect = element("select", "form-select mb-3");
        snapshotSelect.setAttribute("aria-label", tr("snapshot"));
        const snapshots = Array.isArray(data.snapshots) ? data.snapshots : [];
        for (const snapshot of snapshots) {
            snapshotSelect.add(new Option(snapshot.eventTitle
                ? `${snapshot.eventTitle} (${snapshot.eventDate || `#${snapshot.idEvent}`})`
                : `${tr("snapshot")} #${snapshot.id}`, snapshot.id));
        }
        dialog.appendChild(snapshotSelect);
        const formHost = element("div", "");
        dialog.appendChild(formHost);
        const ownOffers = element("div", "mt-3");
        dialog.appendChild(ownOffers);
        host.appendChild(dialog);
        async function loadRecipients() {
            if (!snapshots.length) {
                formHost.textContent = tr("noSnapshotsForOffer");
                return;
            }
            formHost.textContent = tr("loading");
            const offeringId = Number(seller.value);
            try {
                const response = await query("getShareRecipientOptions", "idCharacter", offeringId);
                renderOfferForm(formHost, response?.recipients || [], [{
                    id: Number(data.company.id), name: data.company.companyName,
                    max: Number(data.stock.free),
                }], async (idCompany, recipientId, count, price) => {
                    await post("createPrivateOffer", { idCompany, idOfferingCharacter: offeringId,
                        idSnapshot: Number(snapshotSelect.value), idRecipientCharacter: recipientId,
                        shareCount: count, unitPrice: price });
                    dialog.close();
                    await reload();
                }, { fixedCompany: true, defaultPrice: snapshots.find((item) => Number(item.id) === Number(snapshotSelect.value))?.shareMarketPrice });
            } catch (error) { formHost.textContent = errorMessage(error); }
        }
        seller.addEventListener("change", () => { void loadRecipients(); });
        snapshotSelect.addEventListener("change", () => { void loadRecipients(); });
        async function loadOwnOffers() {
            ownOffers.replaceChildren(element("h5", "h6", tr("myOffers")));
            try {
                const results = await Promise.all(data.myBoardCharacterIds.map((id) =>
                    query("getCreatedShareOffers", "idCharacter", id)));
                const offers = [...(data.managerPrivateOffers || []),
                    ...results.flatMap((result) => result?.offers || [])]
                    .filter((offer) => Number(offer.idCompany) === Number(data.company.id))
                    .filter((offer, index, all) => all.findIndex((candidate) =>
                        Number(candidate.id) === Number(offer.id)) === index);
                if (!offers.length) ownOffers.appendChild(element("p", "text-muted", tr("noOffers")));
                for (const offer of offers) {
                    const row = element("div", "share-ui-holding");
                    row.appendChild(element("span", "", offer.recipientNames || tr("recipient")));
                    row.appendChild(element("span", "", `${number(
                        Number(offer.offeredShareCount) - Number(offer.purchasedShareCount)
                    )} ${tr("shares")} · ${money(offer.unitPrice)}`));
                    const revoke = action(tr("revoke"), "btn-outline-danger btn-sm");
                    revoke.addEventListener("click", async () => {
                        revoke.disabled = true;
                        try { await post("cancelOffer", { idOffer: Number(offer.id) }); dialog.close(); await reload(); }
                        catch (error) { feedback(ownOffers, errorMessage(error), true); revoke.disabled = false; }
                    });
                    row.appendChild(AetherActionButtons.group([revoke]));
                    ownOffers.appendChild(row);
                }
                ownOffers.appendChild(element("div", "")).dataset.shareFeedback = "1";
            } catch (error) { ownOffers.appendChild(element("p", "text-danger", errorMessage(error))); }
        }
        open.addEventListener("click", () => {
            dialog.showModal();
            void loadRecipients();
            void loadOwnOffers();
        });
    }

    function showCompanyHoldingDialog(data, trigger, reload) {
        companyDialog({ companyName: "Aandelen moederbedrijf" }, trigger, (body, close) => {
            const available = Number(data.stock?.free || 0);
            body.appendChild(element("p", "", `${data.company.companyName} · ${number(available)} vrije aandelen`));
            const selected = element("p", "fw-bold", "Kies het moederbedrijf dat mede-eigenaar wordt.");
            const list = element("div", "mb-3");
            list.textContent = tr("loading");
            const [countWrap, count] = field(tr("quantity"), "1");
            const save = action(tr("save"));
            save.disabled = true;
            let selectedId = 0;
            body.append(selected, list, countWrap, AetherActionButtons.group([save]));
            body.appendChild(element("div", "")).dataset.shareFeedback = "1";
            void apiFetchJson("api/companies/getCompanyList.php").then(companies => {
                list.replaceChildren();
                const choices = companies.filter(company => Number(company.id) !== Number(data.company.id))
                    .map(company => ({ ...company, idCompany: Number(company.id) }));
                if (!choices.length) { list.textContent = "Geen andere bedrijven beschikbaar."; return; }
                const grid = companyGrid(list, choices, (company, tile) => {
                    selectedId = Number(company.idCompany);
                    for (const item of grid.children) item.setAttribute("aria-pressed", item === tile ? "true" : "false");
                    selected.textContent = company.companyName;
                    save.disabled = false;
                });
                for (const tile of grid.children) {
                    tile.setAttribute("aria-pressed", "false");
                    tile.setAttribute("aria-haspopup", "false");
                }
            }).catch(error => { list.textContent = errorMessage(error); });
            save.addEventListener("click", async () => {
                const quantity = integer(count.value);
                if (!selectedId || !quantity || quantity > available) {
                    feedback(body, `Kies een bedrijf en 1 tot ${number(available)} aandelen.`, true);
                    return;
                }
                save.disabled = true;
                try {
                    await post("addCompanyShareHolding", { idCompany: Number(data.company.id), idHoldingCompany: selectedId, shareCount: quantity });
                    close();
                    await reload();
                } catch (error) { feedback(body, errorMessage(error), true); save.disabled = false; }
            });
        });
    }

    // The current character screen already polls market stock; refresh valuation when it does.
    function refreshVisiblePortfolio() {
        for (const card of document.querySelectorAll(".share-ui-portfolio")) {
            if (card.isConnected && !card.closest("#economyTab")?.classList.contains("d-none")) {
                void card.aetherRefresh?.();
            }
        }
    }
    window.setInterval(refreshVisiblePortfolio, 15000);
    window.addEventListener("focus", refreshVisiblePortfolio);
    document.addEventListener("visibilitychange", () => { if (!document.hidden) refreshVisiblePortfolio(); });
    return { attachPortfolio, attachCompany, refreshVisiblePortfolio, tr, money, errorMessage,
        mutateCompany: post, companyGrid, companyDialog, detailLine, purchaseQuarterControl, periodFromDate, currentGamePeriod };
})();
