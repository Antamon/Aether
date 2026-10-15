// Event search and live offer controls; server prices and stock remain authoritative.
const aetherShareMarketClient = (() => {
    let characterId = 0;
    let eventId = 0;
    let version = "";
    let data = null;
    let timer = null;
    let pending = false;
    let reloadQueued = false;
    let recipients = null;
    let searchError = false;
    let searchPromise = null;
    let searchPromiseKey = "";
    let marketEditKey = "";
    const labels = {
        nl: { search: "Aandelenmarkt doorzoeken", market: "Aandelenmarkt", noEvent: "Geen koopbaar evenement.",
              noFinds: "Nog geen aandelen gevonden.", offers: "Beschikbare aanbiedingen", noOffers: "Geen aanbiedingen beschikbaar.",
              buy: "Kopen", share: "Delen", shared: "Gedeelde aandelen", choose: "Kies een personage", shares: "aandelen", stock: "Vrije voorraad", error: "De aandelenmarkt kon niet worden geladen.", searchError: "De aandelenzoekactie kon niet worden uitgevoerd.", assessed: "bedrijven onderzocht", discovered: "opgemerkt",
              snapshotCompanies: "Bedrijven met een snapshot voor dit event", notSearched: "Nog niet doorzocht", found: "Gevonden", notFound: "Niet gevonden", chance: "kans", discoveryRoll: "ontdekkingsworp", quantityRoll: "aandelenworp", priceRoll: "prijsworp", purchasePrice: "aankoopprijs", marketPrice: "marktprijs", notListed: "niet beursgenoteerd" },
        en: { search: "Search share market", market: "Share market", noEvent: "No tradeable event.",
              noFinds: "No shares discovered yet.", offers: "Available offers", noOffers: "No offers available.",
              buy: "Buy", share: "Share", shared: "Shared shares", choose: "Choose a character", shares: "shares", stock: "Free stock", error: "The share market could not be loaded.", searchError: "The share search could not be completed.", assessed: "companies checked", discovered: "discovered",
              snapshotCompanies: "Companies with a snapshot for this event", notSearched: "Not searched yet", found: "Found", notFound: "Not found", chance: "chance", discoveryRoll: "discovery roll", quantityRoll: "share quantity roll", priceRoll: "price roll", purchasePrice: "purchase price", marketPrice: "market price", notListed: "not listed" },
    };
    const tr = (key) => (labels[document.documentElement.lang?.slice(0, 2)] || labels.nl)[key];
    const money = (value) => `Fr ${new Intl.NumberFormat(document.documentElement.lang || "nl-BE", {
        minimumFractionDigits: 2, maximumFractionDigits: 2,
    }).format(Number(value || 0))}`;
    const url = (path) => `${path}?idCharacter=${encodeURIComponent(characterId)}`
        + (eventId > 0 ? `&idEvent=${encodeURIComponent(eventId)}` : "");

    function canTrade(character) {
        const user = getCurrentUser();
        return userHasPrivilegedRole(user)
            || (character?.type === "player" && Number(character?.idUser) === Number(user?.id));
    }

    function canSearch(character) {
        return character?.state === "active" && canTrade(character) && Array.isArray(character.skills)
            && character.skills.some((skill) => Number(skill.id) === 6 && Number(skill.level) >= 1);
    }

    async function refresh(force = false) {
        if (!characterId || document.hidden
            || ![...document.querySelectorAll("[data-share-market-host]")].some((host) => {
                const tab = host.closest("#actionsTab, #economyTab");
                return host.isConnected && tab && !tab.classList.contains("d-none");
            })) return;
        if (pending) {
            reloadQueued = reloadQueued || force;
            return;
        }
        pending = true;
        const requestedId = characterId;
        const requestedEventId = eventId;
        try {
            const state = await apiFetchJson(url("api/shares/getShareMarketVersion.php"));
            if (requestedId !== characterId || requestedEventId !== eventId) return;
            if (force || state?.version !== version) {
                const next = await apiFetchJson(url("api/shares/getCharacterShareSearch.php"));
                if (requestedId !== characterId || requestedEventId !== eventId) return;
                version = state?.version || "";
                data = next;
                render();
            }
        } catch (error) {
            console.error("Aandelenmarkt:", error);
            for (const host of document.querySelectorAll("[data-share-market-host]")) {
                host.textContent = tr("error");
            }
        } finally {
            pending = false;
            if (requestedId !== characterId || requestedEventId !== eventId || reloadQueued) {
                reloadQueued = false;
                void refresh(true);
            }
        }
    }

    function begin(id, selectedEventId = 0) {
        const nextId = Number(id || 0);
        const nextEventId = Number(selectedEventId || 0);
        if (nextId !== characterId || nextEventId !== eventId) {
            if (marketEditKey) aetherDataCard.close(marketEditKey);
            marketEditKey = "";
            characterId = nextId;
            eventId = nextEventId;
            version = "";
            data = null;
            recipients = null;
            searchError = false;
        }
        if (!timer) timer = window.setInterval(() => { void refresh(); }, 15000);
        void refresh(true);
    }

    function searchSelectedEvent() {
        const purchaseEventId = Number(data?.currentEvent?.id || eventId);
        if (characterId <= 0 || purchaseEventId <= 0 || data?.canTradeAtEvent === false) return Promise.resolve(null);
        const key = `${characterId}:${purchaseEventId}`;
        if (searchPromise && searchPromiseKey === key) return searchPromise;
        const requestedId = characterId;
        const requestedEventId = purchaseEventId;
        const task = apiFetchIdempotentJson("api/shares/searchShareMarket.php", {
            method: "POST", body: { idCharacter: requestedId, idEvent: requestedEventId },
        }).then(async (result) => {
            searchError = false;
            if (requestedId === characterId && requestedEventId === Number(data?.currentEvent?.id || eventId)) await refresh(true);
            return result;
        }).catch((error) => {
            if (requestedId === characterId && requestedEventId === Number(data?.currentEvent?.id || eventId)) {
                searchError = true;
                render();
            }
            throw error;
        });
        searchPromise = task;
        searchPromiseKey = key;
        void task.finally(() => {
            if (searchPromise === task) {
                searchPromise = null;
                searchPromiseKey = "";
            }
        }).catch(() => {});
        return task;
    }

    function attachActions(container, character, selectedEventId = 0) {
        if (!canSearch(character)) return;
        const host = document.createElement("section");
        host.className = "data-card share-market-card mt-3";
        host.dataset.shareMarketHost = "actions";
        container.appendChild(host);
        begin(character.id, selectedEventId);
        if (recipients === null) {
            const requestedId = characterId;
            apiFetchJson(url("api/shares/getShareRecipientOptions.php"))
                .then((result) => {
                    if (requestedId === characterId) {
                        recipients = result?.recipients || [];
                        render();
                    }
                })
                .catch((error) => console.error("Ontvangers aandelenmarkt:", error));
        }
        render();
    }

    function attachEconomy(container, character, selectedEventId = 0) {
        if (!container || character?.state !== "active" || !canTrade(character)) return;
        const host = document.createElement("section");
        host.className = "share-market-offers mt-4";
        host.dataset.shareMarketHost = "economy";
        container.appendChild(host);
        begin(character.id, userHasPrivilegedRole(getCurrentUser()) ? selectedEventId : 0);
        render();
    }

    function renderAdminAssessments(host, event) {
        if (!data?.canEditAssessments || !event) return;
        const heading = document.createElement("h4");
        heading.className = "h5 mt-3";
        heading.textContent = tr("snapshotCompanies");
        host.appendChild(heading);
        for (const item of data.assessments || []) {
            const row = document.createElement("div");
            row.className = "share-market-assessment data-card mt-2";
            if (item.idAssessment) row.dataset.shareAssessmentId = String(item.idAssessment);
            const name = document.createElement("strong");
            name.textContent = item.companyName || `#${item.idCompany}`;
            row.appendChild(name);
            if (!item.idAssessment) {
                row.appendChild(document.createTextNode(` · ${tr("notSearched")}`));
                host.appendChild(row);
                continue;
            }
            const band = { chance: Number(item.chancePercent),
                quantity: [Number(item.quantityMin), Number(item.quantityMax)],
                price: [Number(item.priceMin), Number(item.priceMax)] };
            const found = Number(item.wasDiscovered) === 1;
            const price = item.unitPrice ?? (item.shareMarketPrice && item.priceRoll
                ? Number(item.shareMarketPrice) * Number(item.priceRoll) / 100 : null);
            const detail = document.createElement("p");
            detail.className = "mb-0 mt-1";
            detail.textContent = `${tr(found ? "found" : "notFound")} · ${tr("chance")} ${band.chance}% · `
                + `${tr("discoveryRoll")} ${item.discoveryRoll} · ${tr("quantityRoll")} ${item.quantityRoll ?? "—"}%`
                + ` (${item.offeredShareCount ?? "—"} ${tr("shares")}) · `
                + `${tr("priceRoll")} ${item.priceRoll == null ? "—" : Number(item.priceRoll) - 100}% · `
                + `${tr("purchasePrice")} ${price == null ? "—" : money(price)}`
                + (Number(item.isShareListed) === 1 ? "" : ` · ${tr("notListed")}`);
            row.appendChild(detail);
            if (aetherDataCard.isEditing(marketEditKey)) {
                const fields = [
                    ["discoveryRoll", tr("discoveryRoll"), 1, 100],
                    ["quantityRoll", `${tr("quantityRoll")} (%)`, ...band.quantity],
                    ["priceRoll", `${tr("priceRoll")} (%)`, ...band.price],
                ];
                const form = document.createElement("div");
                form.className = "share-market-assessment-fields mt-2";
                for (const [key, label, min, max] of fields) {
                    const wrap = document.createElement("label");
                    wrap.className = "form-label mb-0";
                    wrap.textContent = `${label} (${min}–${max})`;
                    const input = document.createElement("input");
                    input.className = "form-control form-control-sm";
                    input.type = "number";
                    input.min = String(min);
                    input.max = String(max);
                    input.required = true;
                    input.dataset.roll = key;
                    input.value = String(key === "priceRoll" && item[key] != null
                        ? Number(item[key]) - 100 : item[key] ?? min);
                    wrap.appendChild(input);
                    form.appendChild(wrap);
                }
                row.appendChild(form);
            }
            host.appendChild(row);
        }
    }

    function renderActions(host) {
        host.replaceChildren();
        const event = data?.currentEvent;
        marketEditKey = `share-market:${characterId}:${Number(event?.id || 0)}`;
        host.classList.toggle("is-editing", aetherDataCard.isEditing(marketEditKey));
        const header = document.createElement("div");
        header.className = "d-flex align-items-start gap-2 mb-2";
        const title = document.createElement("h3");
        title.textContent = tr("market");
        header.appendChild(title);
        aetherDataCard.controls(header, {
            key: marketEditKey,
            canEdit: Boolean(data?.canEditAssessments && event),
            onEdit: () => { if (aetherDataCard.open(marketEditKey)) render(); },
            onCancel: () => { aetherDataCard.close(marketEditKey); render(); },
            onSave: async () => {
                const editedRows = [...host.querySelectorAll("[data-share-assessment-id]")];
                try {
                    for (const row of editedRows) {
                        const idAssessment = Number(row.dataset.shareAssessmentId);
                        const original = (data?.assessments || []).find((item) => Number(item.idAssessment) === idAssessment);
                        if (!original) continue;
                        const values = Object.fromEntries([...row.querySelectorAll("[data-roll]")]
                            .map((input) => [input.dataset.roll,
                                Number(input.value) + (input.dataset.roll === "priceRoll" ? 100 : 0)]));
                        if (Object.entries(values).every(([key, value]) => value === Number(original[key]))) continue;
                        await apiFetchIdempotentJson("api/shares/updateShareSearchAssessment.php", {
                            method: "POST", body: { idCompany: Number(original.idCompany), idCharacter: characterId,
                                idEvent: Number(event.id), idAssessment, ...values },
                        });
                    }
                    aetherDataCard.close(marketEditKey);
                    await refresh(true);
                } catch (error) {
                    alert(aetherShareUi.errorMessage(error));
                    await refresh(true);
                }
            },
        });
        host.appendChild(header);
        if (searchError) {
            const warning = document.createElement("p");
            warning.className = "text-danger";
            warning.textContent = tr("searchError");
            host.appendChild(warning);
        }
        const summary = document.createElement("p");
        summary.textContent = event ? `${event.title} (${event.dateStart} – ${event.dateEnd})` : tr("noEvent");
        host.appendChild(summary);
        const eventSearch = (data?.events || []).find((item) => Number(item.id) === Number(event?.id));
        if (eventSearch?.idSearch) {
            const progress = document.createElement("p");
            progress.textContent = `${Number(eventSearch.assessedCompanies || 0)} ${tr("assessed")}, `
                + `${Number(eventSearch.discoveredCompanies || 0)} ${tr("discovered")}.`;
            host.appendChild(progress);
        }
        if (event && data?.canTradeAtEvent !== false) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "btn btn-outline-primary align-self-start mb-3";
            button.textContent = tr("search");
            button.addEventListener("click", async () => {
                button.disabled = true;
                try {
                    await searchSelectedEvent();
                } catch (error) {
                    alert(aetherShareUi.errorMessage(error));
                } finally {
                    button.disabled = false;
                }
            });
            host.appendChild(button);
        }
        const finds = (data?.discoveries || []).filter((row) => Number(row.idEvent) === Number(event?.id));
        const result = document.createElement("p");
        result.textContent = finds.length
            ? finds.map((row) => {
                const live = (data?.offers || []).find((offer) => Number(offer.id) === Number(row.idOffer));
                return `${row.companyName}: ${Number(live?.availableShareCount || 0)} ${tr("shares")} · ${money(row.unitPrice)}`;
            }).join("; ")
            : tr("noFinds");
        host.appendChild(result);
        renderAdminAssessments(host, event);
        const sharedOffers = (data?.offers || []).filter((offer) =>
            offer.sourceCode === "search" && offer.recipientKind === "shared"
            && Number(offer.idEvent) === Number(event?.id));
        if (sharedOffers.length) {
            const heading = document.createElement("h4");
            heading.textContent = tr("shared");
            host.appendChild(heading);
            const list = document.createElement("ul");
            list.className = "list-group mb-3";
            for (const offer of sharedOffers) {
                const item = document.createElement("li");
                item.className = "list-group-item";
                item.textContent = `${offer.companyName}: ${Number(offer.availableShareCount || 0)} ${tr("shares")} · ${money(offer.unitPrice)}`;
                list.appendChild(item);
            }
            host.appendChild(list);
        }
        for (const find of finds) {
            if (data?.canTradeAtEvent === false || !find.idOffer || find.sharedRecipientId || find.statusCode !== "open" || !recipients?.length) continue;
            const row = document.createElement("div");
            row.className = "d-flex flex-wrap align-items-center gap-2 mb-2";
            const name = document.createElement("span");
            name.textContent = find.companyName;
            const select = document.createElement("select");
            select.className = "form-select";
            select.style.maxWidth = "18rem";
            select.setAttribute("aria-label", tr("choose"));
            const prompt = document.createElement("option");
            prompt.value = "";
            prompt.textContent = tr("choose");
            select.appendChild(prompt);
            for (const recipient of recipients) {
                const option = document.createElement("option");
                option.value = String(recipient.id);
                option.textContent = `${recipient.firstName || ""} ${recipient.lastName || ""}`.trim();
                select.appendChild(option);
            }
            const button = document.createElement("button");
            button.type = "button";
            button.className = "btn btn-outline-secondary";
            button.textContent = tr("share");
            button.addEventListener("click", async () => {
                const idRecipientCharacter = Number(select.value);
                if (!idRecipientCharacter) return;
                button.disabled = true;
                try {
                    await apiFetchIdempotentJson("api/shares/shareSearchDiscovery.php", {
                        method: "POST", body: { idOffer: Number(find.idOffer), idRecipientCharacter,
                            idEvent: Number(event.id) },
                    });
                    await refresh(true);
                } catch (error) {
                    alert(aetherShareUi.errorMessage(error));
                    button.disabled = false;
                }
            });
            row.append(name, select, button);
            host.appendChild(row);
        }
    }

    function renderEconomy(host) {
        host.replaceChildren();
        const title = document.createElement("h3");
        title.textContent = tr("offers");
        host.appendChild(title);
        const event = data?.currentEvent;
        if (data?.canEditAssessments) {
            const label = document.createElement("label");
            label.className = "form-label d-block";
            label.textContent = "Event";
            const select = document.createElement("select");
            select.className = "form-select mb-3";
            select.setAttribute("aria-label", "Event voor aandelenaankoop");
            for (const item of data?.events || []) {
                const option = document.createElement("option");
                option.value = String(item.id);
                option.textContent = `${item.title} (${item.dateStart})`;
                select.appendChild(option);
            }
            select.value = event ? String(event.id) : "";
            select.addEventListener("change", () => begin(characterId, Number(select.value)));
            label.appendChild(select);
            host.appendChild(label);
        } else {
            const caption = document.createElement("p");
            caption.textContent = event ? `${event.title} (${event.dateStart})` : tr("noEvent");
            host.appendChild(caption);
        }
        if (!event || data?.canTradeAtEvent === false) {
            const empty = document.createElement("p");
            empty.textContent = tr("noEvent");
            host.appendChild(empty);
            return;
        }
        const offers = data?.offers || [];
        if (!offers.length) {
            const empty = document.createElement("p");
            empty.textContent = tr("noOffers");
            host.appendChild(empty);
            return;
        }
        const companies = [...new Map(offers.map(offer => [Number(offer.idCompany), offer])).values()];
        const selectedCharacterId = characterId;
        aetherShareUi.companyGrid(host, companies, (company, trigger) => {
            aetherShareUi.companyDialog(company, trigger, (body, close) => {
                renderOfferPurchases(body, offers.filter(offer => Number(offer.idCompany) === Number(company.idCompany)),
                    event, selectedCharacterId, close);
            });
        });
    }

    function renderOfferPurchases(host, offers, event, selectedCharacterId, close) {
        const instructions = document.createElement("p");
        instructions.className = "small text-muted";
        instructions.textContent = `${event.title} (${event.dateStart}). Kies het aantal aandelen dat je wilt kopen. Het aankoopbedrag wordt van de bankrekening afgetrokken. De voorraad wordt bij aankoop opnieuw gecontroleerd.`;
        host.appendChild(instructions);
        for (const offer of offers) {
            const row = document.createElement("div");
            row.className = "data-card mb-3";
            const text = document.createElement("span");
            text.textContent = `${offer.companyName} · ${money(offer.unitPrice)} · ${tr("stock")}: ${offer.availableShareCount}`;
            const quantity = document.createElement("input");
            quantity.type = "number";
            quantity.min = "1";
            quantity.max = String(offer.availableShareCount);
            quantity.value = "1";
            quantity.dataset.offerId = String(offer.id);
            quantity.className = "form-control";
            quantity.style.width = "6rem";
            quantity.setAttribute("aria-label", tr("shares"));
            const total = document.createElement("p");
            total.className = "mt-2 mb-2";
            const updateTotal = () => { total.textContent = `Totaal: ${money(Number(quantity.value) * Number(offer.unitPrice))}`; };
            quantity.addEventListener("input", updateTotal);
            updateTotal();
            const errorMessage = document.createElement("p");
            errorMessage.className = "text-danger small mt-2 mb-0";
            errorMessage.setAttribute("role", "alert");
            const button = AetherActionButtons.button({ icon: "fa-cart-shopping", label: tr("buy") });
            button.disabled = Number(offer.availableShareCount) < 1;
            button.addEventListener("click", async () => {
                const count = Number(quantity.value);
                if (!Number.isInteger(count) || count < 1 || count > Number(offer.availableShareCount)) {
                    errorMessage.textContent = `Kies een geheel aantal van 1 tot ${offer.availableShareCount}.`;
                    return;
                }
                button.disabled = true;
                errorMessage.textContent = "";
                try {
                    await apiFetchIdempotentJson("api/shares/buyOffer.php", {
                        method: "POST", body: { idOffer: Number(offer.id), idCharacter: selectedCharacterId,
                            shareCount: count, idEvent: Number(event.id) },
                    });
                    close();
                    await refresh(true);
                    if (typeof getCharacter === "function" && characterId === selectedCharacterId) await getCharacter(selectedCharacterId, null, "economy");
                } catch (error) {
                    errorMessage.textContent = aetherShareUi.errorMessage(error);
                    await refresh(true);
                } finally {
                    button.disabled = false;
                }
            });
            const controls = document.createElement("div");
            controls.className = "input-group w-auto";
            controls.append(quantity, AetherActionButtons.group([button]));
            row.append(text, total, controls, errorMessage);
            host.appendChild(row);
        }
    }

    function render() {
        if (!data) return;
        for (const host of document.querySelectorAll("[data-share-market-host]")) {
            if (host.dataset.shareMarketHost === "actions") renderActions(host);
            if (host.dataset.shareMarketHost === "economy") renderEconomy(host);
        }
    }

    document.addEventListener("visibilitychange", () => { if (!document.hidden) void refresh(true); });
    window.addEventListener("focus", () => { void refresh(true); });
    return { attachActions, attachEconomy, refresh, searchSelectedEvent };
})();
