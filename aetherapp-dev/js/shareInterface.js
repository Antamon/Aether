// Share screens use the dedicated, permission-checked API. Numbers sent to it stay locale neutral.
const aetherShareUi = (() => {
    const copy = {
        nl: {
            purchases: "Aankopen per event", eventUnknown: "Event onbekend", addPurchase: "Aandelenaankoop toevoegen",
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
            save: "Bewaren", cancel: "Annuleren", create: "Aanbod maken", close: "Sluiten",
            recipient: "Ontvangend personage", seller: "Verkopend personage", board: "Raad van bestuur",
            offer: "Rechtstreeks aanbod", sell: "Aandelen rechtstreeks aanbieden",
            privateOffer: "Privéaanbod uit vrije aandelen", buyback: "Vrije aandelen intrekken",
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
            purchases: "Purchases by event", eventUnknown: "Unknown event", addPurchase: "Add share purchase",
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
            save: "Save", cancel: "Cancel", create: "Create offer", close: "Close",
            recipient: "Recipient character", seller: "Selling character", board: "Board of directors",
            offer: "Direct offer", sell: "Offer owned shares directly",
            privateOffer: "Private offer from free shares", buyback: "Retire free shares",
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
            || (character.type === "player" && Number(character.idUser) === Number(user?.id)));
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
        const metrics = element("div", "share-ui-metrics");
        metrics.append(metric(tr("intrinsic"), money(status.intrinsicPortfolioValue)),
            metric(tr("level"), number(status.shareLevel)),
            metric(tr("status"), number(status.usedStatusPoints)),
            metric(tr("personnel"), number(status.personnelRequirement)));
        card.appendChild(metrics);
        const holdings = Array.isArray(data?.holdings) ? data.holdings : [];
        if (!holdings.length) card.appendChild(element("p", "text-muted mb-0", tr("emptyPortfolio")));
        for (const holding of holdings) {
            const row = element("div", "share-ui-holding");
            row.append(element("strong", "", holding.companyName || `#${holding.idCompany}`),
                element("span", "", `${number(holding.shareCount)} ${tr("shares")} · ${money(holding.intrinsicValue)}`));
            card.appendChild(row);
        }
        if (Array.isArray(data?.purchases) && data.purchases.length) {
            const history = element("details", "mt-3 share-ui-purchase-history");
            history.appendChild(element("summary", "", tr("purchases")));
            for (const purchase of data.purchases) {
                const event = purchase.eventTitle
                    ? `${purchase.eventTitle} (${purchase.eventDate})` : tr("eventUnknown");
                history.appendChild(element("div", "share-ui-holding",
                    `${event} · ${purchase.companyName} · ${number(purchase.shareCount)} ${tr("shares")} · ${money(purchase.totalAmount)}`));
            }
            card.appendChild(history);
        }
        if (character?.state === "draft") renderDraftPurchaseForm(card, data, character, reload);
        else if (data?.canManagePurchases) renderPurchaseForm(card, data, character, reload);
        if (canTrade(character)) {
            const offers = Array.isArray(created?.offers) ? created.offers : [];
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
        if (character?.state === "active" && canTrade(character) && holdings.length) {
            const details = element("details", "mt-3");
            details.appendChild(element("summary", "", tr("sell")));
            const target = element("div", "share-ui-offer-form mt-2");
            details.appendChild(target);
            card.appendChild(details);
            details.addEventListener("toggle", async () => {
                if (!details.open || target.dataset.loaded) return;
                target.dataset.loaded = "1";
                try {
                    const response = await query("getShareRecipientOptions", "idCharacter", character.id);
                    renderOfferForm(target, response?.recipients || [], holdings.map((row) => ({
                        id: row.idCompany, name: row.companyName, max: row.shareCount,
                    })), async (companyId, recipientId, count, price) => {
                        await post("createHolderOffer", { idCompany: companyId,
                            idSellingCharacter: Number(character.id), idRecipientCharacter: recipientId,
                            shareCount: count, unitPrice: price });
                        await reload();
                    });
                } catch (error) { target.textContent = errorMessage(error); }
            });
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
        const quotes = data.draftQuotes || [];
        if (!quotes.length) { card.append(element("p", "text-muted", "Geen bedrijfssnapshots met een marktprijs beschikbaar.")); return; }
        const form = element("form", "share-ui-form-grid mt-2");
        const wrap = element("label", "form-label", data.canChooseDraftSnapshot ? "Bedrijf en snapshot" : "Beschikbare aandelen");
        const select = element("select", "form-select"); select.setAttribute("aria-label", "Aandelen kopen tijdens Draft");
        quotes.forEach((quote, index) => select.add(new Option(
            `${quote.companyName} · ${quote.eventTitle || "Oprichting"}${quote.quoteDate ? ` (${quote.quoteDate})` : ""} · ${money(quote.unitPrice)} · ${number(quote.freeShareCount)} vrij`, index)));
        select.value = "0"; wrap.append(select);
        const [countWrap, count] = field(tr("quantity"), "1");
        const total = element("p", "mb-0");
        const save = action("Aandelen kopen"); save.type = "submit";
        const update = () => {
            const quote = quotes[Number(select.value)]; const quantity = integer(count.value);
            total.textContent = quote && quantity ? `Aankoopbedrag: ${money(Number(quote.unitPrice) * quantity)}` : "";
            save.disabled = !data.canBuyDraftShares || !openBudget || !quantity || !quote
                || quantity > Number(quote.freeShareCount) || Number(quote.unitPrice) * quantity > Number(budget?.remainingAmount || 0);
        };
        select.addEventListener("change", update); count.addEventListener("input", update);
        form.append(wrap, countWrap, total, AetherActionButtons.group([save]));
        form.appendChild(element("div", "")).dataset.shareFeedback = "1";
        form.addEventListener("submit", async event => {
            event.preventDefault(); update(); if (save.disabled) return;
            const quote = quotes[Number(select.value)]; save.disabled = true;
            try {
                await post("buyDraftShares", { idCharacter: Number(character.id), idCompany: Number(quote.idCompany),
                    idSnapshot: Number(quote.idSnapshot), shareCount: integer(count.value) });
                await reload();
                if (typeof getCharacter === "function") await getCharacter(Number(character.id), null, "economy");
            } catch (error) { feedback(form, errorMessage(error), true); update(); }
        });
        update(); card.append(form);
    }

    function renderPurchaseForm(card, data, character, reload) {
        const details = element("details", "mt-3 share-ui-purchase-manager");
        details.appendChild(element("summary", "", tr("addPurchase")));
        card.appendChild(details);
        const events = data.purchaseEvents || [];
        const companies = data.purchaseCompanies || [];
        if (!events.length || !companies.length) {
            details.appendChild(element("p", "text-muted", tr("setupPurchase")));
            return;
        }
        const form = element("form", "share-ui-form-grid mt-2");
        const eventWrap = element("label", "form-label", tr("purchaseEvent"));
        const eventSelect = element("select", "form-select");
        for (const event of events) eventSelect.add(new Option(`${event.title} (${event.dateStart})`, event.id));
        if (data.defaultPurchaseEventId) eventSelect.value = String(data.defaultPurchaseEventId);
        eventWrap.appendChild(eventSelect);
        const companyWrap = element("label", "form-label", tr("company"));
        const companySelect = element("select", "form-select");
        for (const company of companies) companySelect.add(new Option(company.companyName, company.id));
        companyWrap.appendChild(companySelect);
        const [countWrap, count] = field(tr("quantity"), "1");
        const [priceWrap, price] = field(tr("price"), "", "decimal");
        const save = action(tr("save")); save.type = "submit";
        form.append(eventWrap, companyWrap, countWrap, priceWrap, AetherActionButtons.group([save]));
        form.appendChild(element("div", "")).dataset.shareFeedback = "1";
        details.append(element("p", "small text-muted mt-2", tr("purchaseDebits")), form);
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const quantity = integer(count.value), unitPrice = decimal(price.value);
            if (!quantity || !unitPrice || Number(unitPrice) <= 0 || !eventSelect.value || !companySelect.value) {
                feedback(form, tr("invalid"), true); return;
            }
            save.disabled = true;
            try {
                await post("addSharePurchase", { idCharacter: Number(character.id), idCompany: Number(companySelect.value),
                    idEvent: Number(eventSelect.value), shareCount: quantity, unitPrice });
                await reload();
                if (typeof getCharacter === "function") await getCharacter(Number(character.id), null, "economy");
            } catch (error) { feedback(form, errorMessage(error), true); }
            finally { save.disabled = false; }
        });
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
        form.append(company, recipient, countWrap, priceWrap, AetherActionButtons.group([submitButton]));
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
            const list = element("div", "share-ui-holdings mt-2");
            for (const holding of data.holdings) {
                const person = (companyData?.personnelEntries || []).find((p) => Number(p.idCharacter) === Number(holding.idCharacter));
                list.appendChild(element("div", "share-ui-holding",
                    `${holding.displayName || person?.nameLabel || person?.displayName || `#${holding.idCharacter}`}: ${number(holding.shareCount)} ${tr("shares")}`));
            }
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
        const open = action(tr("privateOffer"), "btn-outline-primary mt-3");
        host.appendChild(AetherActionButtons.group([open]));
        const dialog = element("dialog", "share-ui-dialog");
        dialog.appendChild(element("h4", "h5", tr("privateOffer")));
        const seller = element("select", "form-select mb-3");
        seller.setAttribute("aria-label", tr("seller"));
        if (data.canManageShares) seller.add(new Option(data.company.companyName || tr("company"), 0));
        for (const id of data.myBoardCharacterIds) {
            const person = (companyData?.personnelEntries || []).find((item) => Number(item.idCharacter) === Number(id));
            seller.add(new Option(person?.nameLabel || person?.displayName || `#${id}`, id));
        }
        dialog.appendChild(seller);
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
        const close = action(tr("close"), "btn-outline-secondary mt-3");
        close.addEventListener("click", () => dialog.close());
        dialog.appendChild(AetherActionButtons.group([close]));
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
                }, { defaultPrice: snapshots.find((item) => Number(item.id) === Number(snapshotSelect.value))?.shareMarketPrice });
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
        mutateCompany: post };
})();
