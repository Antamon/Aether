function canEditBankAccountForCharacter(character) {
    if (!currentUser || !character) return false;
    if (typeof character.canEditBankAccount === "boolean") {
        return character.canEditBankAccount;
    }

    return (currentUser.role === "administrator" || currentUser.role === "director")
        && character.state !== "draft";
}

function canTransferMoneyForCharacter(character) {
    if (typeof character?.canTransferMoney === "boolean") {
        return character.canTransferMoney;
    }

    return canEditBankAccountForCharacter(character);
}

function characterHasTraitFlagClient(character, flagKey, legacyTraitName = "") {
    const searchName = String(legacyTraitName || "").trim().toLowerCase();
    return getCharacterLinkedTraits(character).some((trait) => {
        const flags = trait?.traitFlags;
        if (flags && typeof flags === "object" && flags[flagKey]) {
            return true;
        }

        const keys = Array.isArray(trait?.traitFlagKeys) ? trait.traitFlagKeys : [];
        if (keys.includes(flagKey)) {
            return true;
        }

        return searchName !== "" && String(trait?.name || "").trim().toLowerCase() === searchName;
    });
}

function getDraftBankAccountAmount(character) {
    const backendAmount = Number(character?.draftBankAccountAmount);
    if (Number.isFinite(backendAmount)) {
        return backendAmount;
    }

    const totalIncome = getCharacterTraitIncomeTotal(character);
    const multiplier = characterHasTraitFlagClient(character, "savings_bank_multiplier", "Spaarder") ? 15 : 10;
    return totalIncome * multiplier;
}

function getDisplayedCharacterBankAccountAmount(character) {
    if (!character) return 0;
    if (character.state === "draft") {
        return getDraftBankAccountAmount(character);
    }

    const storedAmount = Number(character.bankaccount ?? 0);
    return Number.isFinite(storedAmount) ? storedAmount : 0;
}

function getDefaultBankTransferDateClient() {
    const date = new Date();
    date.setFullYear(date.getFullYear() - 100);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

function getCharacterEconomySnapshots(character) {
    return Array.isArray(character?.economySnapshots) ? character.economySnapshots : [];
}

function getCharacterEconomySnapshotEventOptions(character) {
    return Array.isArray(character?.economySnapshotEventOptions)
        ? character.economySnapshotEventOptions
        : [];
}

function getDisplayedCharacterSecuritiesAmount(character) {
    const storedAmount = Number(character?.securitiesaccount ?? 0);
    return Number.isFinite(storedAmount) ? Math.max(0, storedAmount) : 0;
}

function getCharacterSecuritiesSnapshots(character) {
    return (Array.isArray(character?.economySnapshots) ? character.economySnapshots : []).filter((snapshot) => (
        Number(snapshot?.securitiesBalanceSnapshot || 0) > 0
        || String(snapshot?.securitiesStatus || "none") !== "none"
    ));
}

function formatEconomyPercentage(value) {
    const numeric = Number(value ?? 0);
    const resolved = Number.isFinite(numeric) ? numeric : 0;
    return `${resolved.toLocaleString("nl-BE", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    })}%`;
}

function getSecuritiesManagerTypeOptions() {
    return [
        { value: "self", label: "Eigen beheer" },
        { value: "bank", label: "De bank" },
        { value: "third", label: "Een derde" }
    ];
}

function getSecuritiesManagerTypeLabel(value) {
    const option = getSecuritiesManagerTypeOptions().find((entry) => entry.value === value);
    return option?.label || "Eigen beheer";
}

function getSecuritiesRiskProfileLabel(character, riskProfile) {
    const options = character?.securitiesRiskProfileOptions || {};
    return options?.[riskProfile]?.label || `Profiel ${riskProfile}`;
}

function renderSecuritiesSettingsSection(host, character) {
    const canManage = Boolean(character?.canManageSecurities);
    const isDraft = String(character?.state || "") === "draft";
    const settingsWrap = document.createElement("div");
    settingsWrap.className = "economy-securities-settings";

    if (isDraft) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Effectenbeheer wordt pas beschikbaar zodra het personage niet meer in draft staat.";
        settingsWrap.appendChild(help);
        host.appendChild(settingsWrap);
        return;
    }

    const managerRow = document.createElement("div");
    managerRow.className = "mb-3";

    const managerLabel = document.createElement("label");
    managerLabel.className = "form-label";
    managerLabel.textContent = "Beheerder";
    managerRow.appendChild(managerLabel);

    const managerSelect = document.createElement("select");
    managerSelect.className = "form-select";
    managerSelect.dataset.role = "securities-manager-type";
    managerSelect.disabled = !canManage;
    getSecuritiesManagerTypeOptions().forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = option.value;
        optionEl.textContent = option.label;
        optionEl.selected = option.value === String(character?.securitiesManagerType || "self");
        managerSelect.appendChild(optionEl);
    });
    managerRow.appendChild(managerSelect);

    const thirdManagerWrap = document.createElement("div");
    thirdManagerWrap.className = "mt-3";
    thirdManagerWrap.dataset.role = "securities-third-manager-wrap";
    thirdManagerWrap.classList.toggle("d-none", String(character?.securitiesManagerType || "self") !== "third");

    const thirdManagerLabel = document.createElement("label");
    thirdManagerLabel.className = "form-label";
    thirdManagerLabel.textContent = "Externe beheerder";
    thirdManagerWrap.appendChild(thirdManagerLabel);

    const thirdManagerSelect = document.createElement("select");
    thirdManagerSelect.className = "form-select";
    thirdManagerSelect.dataset.role = "securities-manager-character";
    thirdManagerSelect.disabled = !canManage;

    const emptyManagerOption = document.createElement("option");
    emptyManagerOption.value = "";
    emptyManagerOption.textContent = "Kies een actief personage";
    thirdManagerSelect.appendChild(emptyManagerOption);

    (Array.isArray(character?.securitiesManagerOptions) ? character.securitiesManagerOptions : []).forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = String(option.id || 0);
        optionEl.textContent = option.displayName || `Personage #${option.id || 0}`;
        optionEl.selected = Number(option.id || 0) === Number(character?.securitiesManagerCharacterId || 0);
        thirdManagerSelect.appendChild(optionEl);
    });

    thirdManagerWrap.appendChild(thirdManagerSelect);
    managerRow.appendChild(thirdManagerWrap);
    settingsWrap.appendChild(managerRow);

    const riskRow = document.createElement("div");
    riskRow.className = "mb-3";

    const riskLabel = document.createElement("label");
    riskLabel.className = "form-label";
    riskLabel.textContent = "Risicoprofiel";
    riskRow.appendChild(riskLabel);

    const riskSelect = document.createElement("select");
    riskSelect.className = "form-select";
    riskSelect.dataset.role = "securities-risk-profile";
    riskSelect.disabled = !canManage;
    [1, 2, 3, 4, 5].forEach((riskProfile) => {
        const optionEl = document.createElement("option");
        optionEl.value = String(riskProfile);
        optionEl.textContent = getSecuritiesRiskProfileLabel(character, riskProfile);
        optionEl.selected = Number(character?.securitiesRiskProfile || 3) === riskProfile;
        riskSelect.appendChild(optionEl);
    });
    riskRow.appendChild(riskSelect);
    settingsWrap.appendChild(riskRow);

    const info = document.createElement("div");
    info.className = "text-muted small";
    info.textContent = `Huidig beleggingsniveau: ${Number(character?.securitiesManagerSkillLevel || 0)}.`;
    settingsWrap.appendChild(info);

    host.appendChild(settingsWrap);
}

function renderSecuritiesDepositActions(host, character) {
    const canManage = Boolean(character?.canManageSecurities);
    const isDraft = String(character?.state || "") === "draft";
    if (isDraft) {
        return;
    }

    const depositWrap = document.createElement("div");
    depositWrap.className = "economy-securities-action-block";

    const depositLabel = document.createElement("label");
    depositLabel.className = "form-label";
    depositLabel.textContent = "Bedrag storten";
    depositWrap.appendChild(depositLabel);

    const depositRow = document.createElement("div");
    depositRow.className = "input-group mb-3";
    const depositInput = document.createElement("input");
    depositInput.type = "number";
    depositInput.className = "form-control";
    depositInput.step = "0.01";
    depositInput.min = "0.01";
    depositInput.dataset.role = "securities-deposit-amount";
    depositInput.disabled = !canManage;
    depositRow.appendChild(depositInput);

    const depositButton = document.createElement("button");
    depositButton.type = "button";
    depositButton.className = "btn btn-primary";
    depositButton.dataset.action = "deposit-securities-balance";
    depositButton.disabled = !canManage;
    depositButton.textContent = "Bedrag storten";
    depositRow.appendChild(depositButton);
    depositWrap.appendChild(depositRow);

    const withdrawLabel = document.createElement("label");
    withdrawLabel.className = "form-label";
    withdrawLabel.textContent = "Bedrag opnemen buiten snapshot";
    depositWrap.appendChild(withdrawLabel);

    const withdrawRow = document.createElement("div");
    withdrawRow.className = "input-group";
    const withdrawInput = document.createElement("input");
    withdrawInput.type = "number";
    withdrawInput.className = "form-control";
    withdrawInput.step = "0.01";
    withdrawInput.min = "0.01";
    withdrawInput.dataset.role = "securities-manual-withdraw-amount";
    withdrawInput.disabled = !canManage;
    withdrawRow.appendChild(withdrawInput);

    const withdrawButton = document.createElement("button");
    withdrawButton.type = "button";
    withdrawButton.className = "btn btn-outline-warning";
    withdrawButton.dataset.action = "withdraw-securities-balance";
    withdrawButton.disabled = !canManage;
    withdrawButton.textContent = "Naar bankrekening";
    withdrawRow.appendChild(withdrawButton);
    depositWrap.appendChild(withdrawRow);

    const help = document.createElement("div");
    help.className = "form-text";
    help.textContent = "Een opname buiten snapshot om boekt slechts 75% van het gekozen bedrag naar de bankrekening.";
    depositWrap.appendChild(help);

    host.appendChild(depositWrap);
}

function renderSecuritiesSnapshotsSection(host, character) {
    const snapshots = getCharacterSecuritiesSnapshots(character);

    appendEconomySubsectionTitle(host, "Rendementen");

    if (snapshots.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Nog geen rendementen beschikbaar.";
        host.appendChild(empty);
        return;
    }

    const list = document.createElement("div");
    list.className = "list-group list-group-flush";

    snapshots.forEach((snapshot) => {
        const item = document.createElement("div");
        item.className = "list-group-item px-0";

        const header = document.createElement("div");
        header.className = "d-flex justify-content-between align-items-start gap-3 flex-wrap";

        const titleWrap = document.createElement("div");
        const title = document.createElement("div");
        title.className = "fw-semibold";
        title.textContent = String(snapshot?.eventTitle || "").trim() || `Event #${snapshot?.idEvent || 0}`;
        titleWrap.appendChild(title);

        const meta = document.createElement("div");
        meta.className = "text-muted small";
        meta.textContent = [
            formatEconomyDate(snapshot?.transactionDate),
            `${getSecuritiesManagerTypeLabel(String(snapshot?.securitiesManagerType || "self"))}`,
            `${getSecuritiesRiskProfileLabel(character, Number(snapshot?.securitiesRiskProfile || 3))}`,
            `Skill ${Number(snapshot?.securitiesManagerSkillLevel || 0)}`
        ].filter(Boolean).join(" - ");
        titleWrap.appendChild(meta);
        header.appendChild(titleWrap);

        const amount = document.createElement("div");
        const returnAmount = Number(snapshot?.securitiesReturnAmount || 0);
        amount.className = `fw-semibold ${returnAmount < 0 ? "text-danger" : "text-success"}`;
        amount.textContent = formatCharacterCurrency(returnAmount);
        header.appendChild(amount);
        item.appendChild(header);

        const details = document.createElement("div");
        details.className = "economy-securities-snapshot-meta text-muted small mt-2";
        const status = String(snapshot?.securitiesStatus || "none");
        const parts = [
            `Basistarief ${formatEconomyPercentage(snapshot?.securitiesBasePercentage || 0)}`,
            `Variatie ${formatEconomyPercentage(snapshot?.securitiesVariationPercentage || 0)}`,
            `Totaal ${formatEconomyPercentage(snapshot?.securitiesReturnPercentage || 0)}`
        ];
        if (status === "pending") {
            parts.push("Wacht op goedkeuring");
        } else if (status === "approved") {
            parts.push("Goedgekeurd");
        }
        details.textContent = parts.join(" - ");
        item.appendChild(details);

        const snapshotWithdrawalAmount = Number(snapshot?.securitiesSnapshotWithdrawalAmount || 0);
        if (snapshotWithdrawalAmount > 0) {
            const withdrawalInfo = document.createElement("div");
            withdrawalInfo.className = "small mt-2";
            withdrawalInfo.textContent = `Opname uit portefeuille: ${formatCharacterCurrency(snapshotWithdrawalAmount)}`;
            item.appendChild(withdrawalInfo);
        }

        const actions = document.createElement("div");
        actions.className = "d-flex align-items-center gap-2 flex-wrap mt-3";

        if (status === "pending" && character?.canApproveSecuritiesSnapshots) {
            const rerollButton = document.createElement("button");
            rerollButton.type = "button";
            rerollButton.className = "btn btn-sm btn-outline-secondary";
            rerollButton.dataset.action = "reroll-securities-snapshot";
            rerollButton.dataset.idSnapshot = String(snapshot?.id || 0);
            rerollButton.textContent = "Opnieuw genereren";
            actions.appendChild(rerollButton);

            const approveButton = document.createElement("button");
            approveButton.type = "button";
            approveButton.className = "btn btn-sm btn-primary";
            approveButton.dataset.action = "approve-securities-snapshot";
            approveButton.dataset.idSnapshot = String(snapshot?.id || 0);
            approveButton.textContent = "Goedkeuren";
            actions.appendChild(approveButton);
        }

        const canWithdrawFromSnapshot = status === "approved"
            && Boolean(character?.canManageSecurities)
            && !Boolean(snapshot?.securitiesEventEnded)
            && snapshotWithdrawalAmount <= 0;
        if (canWithdrawFromSnapshot) {
            const withdrawInput = document.createElement("input");
            withdrawInput.type = "number";
            withdrawInput.className = "form-control form-control-sm economy-securities-snapshot-withdraw-input";
            withdrawInput.step = "0.01";
            withdrawInput.min = "0.01";
            withdrawInput.max = "30000";
            withdrawInput.dataset.role = "securities-snapshot-withdraw-amount";
            withdrawInput.dataset.idSnapshot = String(snapshot?.id || 0);
            actions.appendChild(withdrawInput);

            const withdrawButton = document.createElement("button");
            withdrawButton.type = "button";
            withdrawButton.className = "btn btn-sm btn-outline-primary";
            withdrawButton.dataset.action = "withdraw-securities-snapshot";
            withdrawButton.dataset.idSnapshot = String(snapshot?.id || 0);
            withdrawButton.textContent = "Opnemen";
            actions.appendChild(withdrawButton);
        }

        if (actions.childNodes.length > 0) {
            item.appendChild(actions);
        }

        list.appendChild(item);
    });

    host.appendChild(list);
}

function renderCharacterSecuritiesSection(host, character) {
    if (!host || !character) return;

    const card = buildEconomyCard("Effectenportefeuille");
    const balance = getDisplayedCharacterSecuritiesAmount(character);
    addEconomyMetric(card.body, "Effectenportefeuille", formatCharacterCurrency(balance));
    renderSecuritiesDepositActions(card.body, character);
    renderSecuritiesSettingsSection(card.body, character);
    renderSecuritiesSnapshotsSection(card.body, character);
    host.appendChild(card.card);
}

function formatEconomyDate(dateValue) {
    const value = String(dateValue || "").trim();
    if (!value) return "";

    const parsedDate = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsedDate.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("nl-BE").format(parsedDate);
}

function parseBankAmount(value) {
    const normalized = String(value ?? "")
        .trim()
        .replace(/\s+/g, "")
        .replace(",", ".");

    if (normalized === "") {
        return null;
    }

    const amount = Number(normalized);
    if (!Number.isFinite(amount)) {
        return null;
    }

    return Math.round(amount * 100) / 100;
}

function getApiErrorMessage(error, fallbackMessage) {
    const message = String(error?.message || "").trim();
    const jsonStart = message.indexOf("{");
    if (jsonStart >= 0) {
        try {
            const parsed = JSON.parse(message.slice(jsonStart));
            if (parsed?.error) {
                const details = String(parsed?.details || "").trim();
                if (details && details !== parsed.error) {
                    return `${parsed.error} (${details})`;
                }

                return parsed.error;
            }
        } catch (parseError) {
            // Fall back to generic message below.
        }
    }

    return fallbackMessage;
}

function buildEconomyCard(title) {
    const card = document.createElement("div");
    card.className = "card shadow-sm mb-4";

    const body = document.createElement("div");
    body.className = "card-body";
    card.appendChild(body);

    const heading = document.createElement("h4");
    heading.className = "card-title h5 mb-3";
    heading.textContent = title;
    body.appendChild(heading);

    return { card, body };
}

function appendEconomySubsectionTitle(host, title) {
    const heading = document.createElement("h5");
    heading.className = "economy-subsection-title";
    heading.textContent = title;
    host.appendChild(heading);
}

function getCharacterIncomeTraitEntries(character) {
    const groups = [
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : []),
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : [])
    ];
    const seenTraitIds = new Set();
    const nobilityIncomeMultiplier = typeof getUpperClassNobilityIncomeMultiplier === "function"
        ? getUpperClassNobilityIncomeMultiplier(character)
        : 1;
    const entries = [];

    groups.forEach((group) => {
        const linkedTraits = Array.isArray(group?.linkedTraits) ? group.linkedTraits : [];
        linkedTraits.forEach((trait) => {
            const traitId = Number(trait?.idTrait || trait?.id || 0);
            if (traitId > 0 && seenTraitIds.has(traitId)) {
                return;
            }

            if (traitId > 0) {
                seenTraitIds.add(traitId);
            }

            const income = typeof calculateTraitIncomeAtRank === "function"
                ? calculateTraitIncomeAtRank(trait)
                : Number(trait?.income || 0);
            const normalizedIncome = Number(income);
            if (!Number.isFinite(normalizedIncome) || normalizedIncome === 0) {
                return;
            }

            const isScaledNobilityIncome = (
                String(group?.trackKey || "") === "upper_nobility_lineage"
                || linkedTraits.some((linkedTrait) => traitHasCharacterFlag(linkedTrait, "nobility_income_scaled"))
                || String(group?.traitGroup || "") === "Adeldom"
            );
            const adjustedIncome = isScaledNobilityIncome
                ? normalizedIncome * nobilityIncomeMultiplier
                : normalizedIncome;

            entries.push({
                label: String(trait?.name || trait?.traitName || `Trait #${traitId || "?"}`),
                amount: adjustedIncome
            });
        });
    });

    return entries;
}

function renderIncomeSection(host, character) {
    const incomeCard = buildEconomyCard("Inkomsten");
    const entries = getCharacterIncomeTraitEntries(character);
    const salaryIncreaseAmount = Number(character?.salaryIncreaseAmount || 0);
    const householdStaffExpenseAmount = Number(character?.householdStaffExpenseAmount || 0);
    const totalIncome = getCharacterTraitIncomeTotal(character);

    const list = document.createElement("div");
    list.className = "economy-income-list";

    if (entries.length === 0 && salaryIncreaseAmount <= 0 && householdStaffExpenseAmount <= 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Geen trait-inkomsten of -uitgaven.";
        incomeCard.body.appendChild(empty);
    } else {
        entries.forEach((entry) => {
            const row = document.createElement("div");
            row.className = "economy-income-row";

            const label = document.createElement("span");
            label.className = "economy-income-label";
            label.textContent = entry.label;
            row.appendChild(label);

            const value = document.createElement("span");
            value.className = [
                "economy-income-value",
                entry.amount < 0 ? "text-danger" : "text-success"
            ].join(" ");
            value.textContent = formatCharacterCurrency(entry.amount);
            row.appendChild(value);

            list.appendChild(row);
        });

        if (salaryIncreaseAmount > 0) {
            const salaryRow = document.createElement("div");
            salaryRow.className = "economy-income-row";

            const label = document.createElement("span");
            label.className = "economy-income-label";
            label.textContent = "Loonsverhoging gekoppeld bedrijf";
            salaryRow.appendChild(label);

            const value = document.createElement("span");
            value.className = "economy-income-value text-success";
            value.textContent = formatCharacterCurrency(salaryIncreaseAmount);
            salaryRow.appendChild(value);

            list.appendChild(salaryRow);
        }

        if (householdStaffExpenseAmount > 0) {
            const expenseRow = document.createElement("div");
            expenseRow.className = "economy-income-row";

            const label = document.createElement("span");
            label.className = "economy-income-label";
            label.textContent = "Inwonend personeel";
            expenseRow.appendChild(label);

            const value = document.createElement("span");
            value.className = "economy-income-value text-danger";
            value.textContent = formatCharacterCurrency(-householdStaffExpenseAmount);
            expenseRow.appendChild(value);

            list.appendChild(expenseRow);
        }

        const totalRow = document.createElement("div");
        totalRow.className = "economy-income-row economy-income-row--total";

        const totalLabel = document.createElement("span");
        totalLabel.className = "economy-income-label";
        totalLabel.textContent = "Eindbedrag";
        totalRow.appendChild(totalLabel);

        const totalValue = document.createElement("span");
        totalValue.className = [
            "economy-income-value",
            totalIncome < 0 ? "text-danger" : "text-success"
        ].join(" ");
        totalValue.textContent = formatCharacterCurrency(totalIncome);
        totalRow.appendChild(totalValue);

        list.appendChild(totalRow);
        incomeCard.body.appendChild(list);
    }

    host.appendChild(incomeCard.card);
}

function getCharacterCompanyShares(character) {
    return Array.isArray(character?.companyShares) ? character.companyShares : [];
}

function getCharacterCompanySharePurchaseOptions(character) {
    return Array.isArray(character?.companySharePurchaseOptions) ? character.companySharePurchaseOptions : [];
}

function getCompanyShareUnitPrice(value) {
    const companyValue = Number(value ?? 0);
    if (!Number.isFinite(companyValue) || companyValue <= 0) {
        return 0;
    }

    return companyValue / 100;
}

function canDisplayCompanyShareCard(character, share) {
    if (!character || !share) return false;

    const hasAssignedCompany = Number(share.companyId || 0) > 0;
    if (currentUser?.role === "administrator" || currentUser?.role === "director") {
        return true;
    }

    if (hasAssignedCompany) {
        return true;
    }

    return character.state === "draft" && character.type === "player";
}

function createCompanyShareCompanyOptions(share) {
    const currentCompanyId = Number(share?.companyId || 0);
    const currentCompanyName = String(share?.companyName || "").trim();
    const options = Array.isArray(share?.availableCompanies)
        ? [...share.availableCompanies]
        : [];

    if (currentCompanyId > 0 && !options.some((company) => Number(company?.id || 0) === currentCompanyId)) {
        options.unshift({
            id: currentCompanyId,
            companyName: currentCompanyName || `Bedrijf #${currentCompanyId}`,
            companyValue: Number(share?.companyValue ?? 0),
            remainingSharePercentage: Number(share?.remainingSharePercentage ?? 0),
            isUnavailableCurrent: true
        });
    }

    return options;
}

function formatCompanyShareOptionLabel(company) {
    const name = String(company?.companyName || "").trim() || `Bedrijf #${company?.id || 0}`;
    const value = Number(company?.companyValue ?? 0);
    const unitPrice = getCompanyShareUnitPrice(value);
    const remainingSharePercentage = Number(company?.remainingSharePercentage ?? 0);
    const label = `${name} - ${formatCharacterCurrency(unitPrice)} per 1%`;

    if (company?.isUnavailableCurrent) {
        return `${label} - huidige koppeling`;
    }

    return `${label} - nog ${remainingSharePercentage}% beschikbaar`;
}

function formatCompanySharePurchaseOptionLabel(option) {
    const name = String(option?.companyName || "").trim() || `Bedrijf #${option?.idCompany || 0}`;
    const unitPrice = Number(option?.unitPrice ?? 0);
    const remainingSharePercentage = Number(option?.remainingSharePercentage ?? 0);
    const shareClass = String(option?.shareClass || "").trim();

    return `${name} - ${shareClass}-aandeel - ${formatCharacterCurrency(unitPrice)} per 1% - nog ${remainingSharePercentage}% beschikbaar`;
}

function formatCharacterEconomySnapshotEventLabel(eventOption) {
    const title = String(eventOption?.title || "").trim() || `Event #${eventOption?.id || 0}`;
    const dateLabel = formatEconomyDate(eventOption?.dateStart);
    return dateLabel ? `${title} (${dateLabel})` : title;
}

function renderCompanySharesSection(host, character) {
    const companyShares = getCharacterCompanyShares(character)
        .filter((share) => canDisplayCompanyShareCard(character, share));
    const purchaseOptions = getCharacterCompanySharePurchaseOptions(character);
    const canShowPurchaseCard = character?.state !== "draft"
        && (currentUser?.role === "administrator" || currentUser?.role === "director" || character?.type === "player");
    const canRenderPurchaseCard = canShowPurchaseCard && purchaseOptions.length > 0;

    if (!host || (companyShares.length === 0 && !canRenderPurchaseCard)) {
        return;
    }

    companyShares.forEach((share) => {
        const shareClassLabel = `${share.shareClass || "?"}-aandelen`;
        const shareTitle = Number(share.companyId || 0) > 0
            ? `${shareClassLabel} ${share.companyName || `Bedrijf #${share.companyId}`}`
            : shareClassLabel;

        const shareCard = buildEconomyCard(shareTitle);
        shareCard.card.classList.add("economy-share-card");

        const item = document.createElement("div");
        item.className = "economy-share-item";

        const header = document.createElement("div");
        header.className = "economy-share-header d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2";

        const titleWrap = document.createElement("div");
        titleWrap.className = "economy-share-title-wrap";

        const meta = document.createElement("p");
        meta.className = "economy-share-subtitle text-muted mb-0";
        meta.textContent = share.name || `${share.shareClass || ""}-aandeel`;
        titleWrap.appendChild(meta);
        header.appendChild(titleWrap);

        const percentageBadge = document.createElement("span");
        percentageBadge.className = "economy-share-badge badge text-bg-secondary fs-6";
        percentageBadge.textContent = `${Number(share.percentage || 0)}%`;
        header.appendChild(percentageBadge);
        item.appendChild(header);

        if (share.canManageAssignments) {
            const fieldWrap = document.createElement("div");
            fieldWrap.className = "mb-3";

            const label = document.createElement("label");
            label.className = "form-label";
            label.textContent = "Gekoppeld bedrijf";
            fieldWrap.appendChild(label);

            const select = document.createElement("select");
            select.className = "form-select";
            select.dataset.role = "company-share-company-select";
            select.dataset.idLinkCharacterTrait = String(share.idLinkCharacterTrait || 0);

            const emptyOption = document.createElement("option");
            emptyOption.value = "";
            emptyOption.textContent = "Geen bedrijf gekoppeld";
            select.appendChild(emptyOption);

            createCompanyShareCompanyOptions(share).forEach((company) => {
                const option = document.createElement("option");
                option.value = String(company.id || 0);
                option.textContent = formatCompanyShareOptionLabel(company);
                option.selected = Number(company.id || 0) === Number(share.companyId || 0);
                select.appendChild(option);
            });

            fieldWrap.appendChild(select);

            if (!share.companyId && createCompanyShareCompanyOptions(share).length === 0) {
                const help = document.createElement("div");
                help.className = "form-text";
                help.textContent = "Er zijn momenteel geen bedrijven beschikbaar die bij dit aandeeltype passen of nog voldoende vrije aandelen hebben.";
                fieldWrap.appendChild(help);
            }

            item.appendChild(fieldWrap);
        }

        if (Number(share.companyId || 0) > 0) {
            const metrics = document.createElement("div");
            metrics.className = "economy-share-metrics";
            addEconomyMetric(metrics, "Type bedrijf", String(share.companyTypeLabelResolved || share.companyTypeLabel || "-"));
            addEconomyMetric(metrics, "Bedrijfswaarde", formatCharacterCurrency(share.companyValue || 0));
            addEconomyMetric(metrics, "Aandelen op de markt", `${Number(share.remainingSharePercentage || 0)}%`);
            item.appendChild(metrics);

            const descriptionWrap = document.createElement("div");
            descriptionWrap.className = "economy-share-description mb-3";

            const descriptionLabel = document.createElement("div");
            descriptionLabel.className = "economy-share-section-label text-muted mb-2";
            descriptionLabel.textContent = "Beschrijving bedrijf";
            descriptionWrap.appendChild(descriptionLabel);

            const descriptionBody = document.createElement("div");
            descriptionBody.className = "economy-share-description-body d-flex gap-3 align-items-start flex-wrap";

            const logoWrap = document.createElement("div");
            logoWrap.className = "economy-share-logo border rounded bg-light d-flex align-items-center justify-content-center overflow-hidden";

            if (share.companyLogoUrl) {
                const logo = document.createElement("img");
                logo.src = share.companyLogoUrl;
                logo.alt = `${share.companyName || "Bedrijf"} logo`;
                logo.className = "w-100 h-100";
                logo.style.objectFit = "contain";
                logoWrap.appendChild(logo);
            } else {
                const placeholder = document.createElement("span");
                placeholder.className = "small text-muted text-center px-2";
                placeholder.textContent = "Geen logo";
                logoWrap.appendChild(placeholder);
            }

            const descriptionText = document.createElement("div");
            descriptionText.className = "economy-share-description-text flex-grow-1";
            descriptionText.textContent = String(share.companyDescription || "").trim() || "Geen beschrijving beschikbaar.";

            descriptionBody.appendChild(logoWrap);
            descriptionBody.appendChild(descriptionText);
            descriptionWrap.appendChild(descriptionBody);
            item.appendChild(descriptionWrap);
        }

        const showTradingControls = character?.state !== "draft" && Number(share.companyId || 0) > 0;
        if (showTradingControls) {
            const pricing = document.createElement("div");
            pricing.className = "economy-share-metrics economy-share-pricing";
            addEconomyMetric(pricing, "Prijs per 1% aandeel", formatCharacterCurrency(share.nextPercentageCost || share.sellPercentageValue || 0));
            item.appendChild(pricing);
        }

        if (showTradingControls && (share.canIncreaseRank || share.canDecreaseRank)) {
            const actionWrap = document.createElement("div");
            actionWrap.className = "economy-share-actions d-flex justify-content-between align-items-center gap-3 flex-wrap";

            const help = document.createElement("div");
            help.className = "economy-share-help text-muted";

            const hasAssignedCompany = Number(share.companyId || 0) > 0;
            const canIncreaseByCapacity = Number(share.remainingSharePercentage || 0) > Number(share.percentage || 0);
            const canDecreaseByPercentage = Number(share.percentage || 0) > 0;
            const canIncrease = hasAssignedCompany
                && canIncreaseByCapacity
                && Boolean(share.canAffordNextRank);
            const canDecrease = hasAssignedCompany
                && canDecreaseByPercentage;

            if (!hasAssignedCompany) {
                help.textContent = "Koppel eerst een bedrijf om dit aandeel te beheren.";
            } else if (!canIncreaseByCapacity) {
                help.textContent = "Dit bedrijf heeft geen vrije aandelen meer beschikbaar om bij te kopen.";
            } else if (!share.canAffordNextRank) {
                help.textContent = `Aandeel kopen is niet mogelijk: ${formatCharacterCurrency(share.nextPercentageCost || 0)} per 1% en onvoldoende saldo op de bankrekening.`;
            } else {
                help.textContent = `Aandeel kopen of verkopen gebeurt telkens per 1% voor ${formatCharacterCurrency(share.nextPercentageCost || share.sellPercentageValue || 0)}.`;
            }
            actionWrap.appendChild(help);

            const buttonGroup = document.createElement("div");
            buttonGroup.className = "economy-share-button-group d-flex align-items-center gap-2";

            if (share.canDecreaseRank) {
                const decreaseButton = document.createElement("button");
                decreaseButton.type = "button";
                decreaseButton.className = "btn btn-outline-danger";
                decreaseButton.dataset.action = "decrease-company-share-rank";
                decreaseButton.dataset.idLinkCharacterTrait = String(share.idLinkCharacterTrait || 0);
                decreaseButton.disabled = !canDecrease;
                decreaseButton.textContent = "Aandeel verkopen";
                buttonGroup.appendChild(decreaseButton);
            }

            if (share.canIncreaseRank) {
                const increaseButton = document.createElement("button");
                increaseButton.type = "button";
                increaseButton.className = "btn btn-outline-primary";
                increaseButton.dataset.action = "increase-company-share-rank";
                increaseButton.dataset.idLinkCharacterTrait = String(share.idLinkCharacterTrait || 0);
                increaseButton.disabled = !canIncrease;
                increaseButton.textContent = "Aandeel kopen";
                buttonGroup.appendChild(increaseButton);
            }

            actionWrap.appendChild(buttonGroup);

            item.appendChild(actionWrap);
        }

        shareCard.body.appendChild(item);
        host.appendChild(shareCard.card);
    });

    if (!canRenderPurchaseCard) {
        return;
    }

    const purchaseCard = buildEconomyCard("Nieuw aandeel kopen");
    purchaseCard.card.classList.add("economy-share-card");

    const form = document.createElement("div");
    form.className = "economy-share-purchase";

    const label = document.createElement("label");
    label.className = "form-label";
    label.textContent = "Beschikbare bedrijven";
    label.setAttribute("for", "companySharePurchaseSelect");
    form.appendChild(label);

    const select = document.createElement("select");
    select.className = "form-select";
    select.id = "companySharePurchaseSelect";
    select.dataset.role = "company-share-purchase-select";

    const emptyOption = document.createElement("option");
    emptyOption.value = "";
    emptyOption.textContent = "Kies een bedrijf en aandelentype";
    select.appendChild(emptyOption);

    purchaseOptions.forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = `${Number(option.idCompany || 0)}:${String(option.shareClass || "")}`;
        optionEl.textContent = formatCompanySharePurchaseOptionLabel(option);
        select.appendChild(optionEl);
    });

    select.disabled = false;
    form.appendChild(select);

    const help = document.createElement("div");
    help.className = "form-text";
    help.textContent = "Koop 1% in een bedrijf. De passende statuseigenschap wordt automatisch aan het personage toegevoegd.";
    form.appendChild(help);

    const buttonRow = document.createElement("div");
    buttonRow.className = "mt-3";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-primary";
    button.dataset.action = "buy-new-company-share";
    button.disabled = false;
    button.textContent = "Aandeel kopen";
    buttonRow.appendChild(button);
    form.appendChild(buttonRow);

    purchaseCard.body.appendChild(form);
    host.appendChild(purchaseCard.card);
}

function addEconomyMetric(host, label, value, valueClass = "") {
    const row = document.createElement("div");
    row.className = "d-flex justify-content-between align-items-center gap-3 mb-2";

    const labelEl = document.createElement("span");
    labelEl.className = "text-muted";
    labelEl.textContent = label;
    row.appendChild(labelEl);

    const valueEl = document.createElement("span");
    valueEl.className = `fw-semibold ${valueClass}`.trim();
    valueEl.textContent = value;
    row.appendChild(valueEl);

    host.appendChild(row);
}

function renderCharacterEconomySnapshotsSection(host, character) {
    if (!host || !character) return;
    const canCreateSnapshots = Boolean(character?.canCreateEconomySnapshots);
    if (!canCreateSnapshots) {
        return;
    }

    const snapshotCard = buildEconomyCard("Snapshots");
    const snapshots = getCharacterEconomySnapshots(character);
    const eventOptions = getCharacterEconomySnapshotEventOptions(character);

    const help = document.createElement("p");
    help.className = "text-muted mb-3";
    help.textContent = "Maak per event een momentopname van de inkomsten van dit personage. Alle trait-inkomsten worden dan in een keer op de bankrekening gezet en een eventuele effectenportefeuille krijgt tegelijk een wachtend rendement.";
    snapshotCard.body.appendChild(help);

    const form = document.createElement("div");
    form.className = "row g-3 align-items-end mb-3";
    form.dataset.role = "character-economy-snapshot-form";

    const selectCol = document.createElement("div");
    selectCol.className = "col-12 col-md-8";

    const selectLabel = document.createElement("label");
    selectLabel.className = "form-label";
    selectLabel.textContent = "Event";
    selectLabel.setAttribute("for", "characterEconomySnapshotEventSelect");
    selectCol.appendChild(selectLabel);

    const select = document.createElement("select");
    select.className = "form-select";
    select.id = "characterEconomySnapshotEventSelect";
    select.dataset.role = "character-economy-snapshot-event";

    const emptyOption = document.createElement("option");
    emptyOption.value = "";
    emptyOption.textContent = eventOptions.length > 0
        ? "Kies een event"
        : "Geen events meer beschikbaar";
    select.appendChild(emptyOption);

    eventOptions.forEach((eventOption) => {
        const option = document.createElement("option");
        option.value = String(eventOption.id || 0);
        option.textContent = formatCharacterEconomySnapshotEventLabel(eventOption);
        select.appendChild(option);
    });

    select.disabled = eventOptions.length === 0;
    selectCol.appendChild(select);
    form.appendChild(selectCol);

    const actionCol = document.createElement("div");
    actionCol.className = "col-12 col-md-4";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-primary w-100";
    button.dataset.action = "create-character-economy-snapshot";
    button.disabled = eventOptions.length === 0;
    button.textContent = "Snapshot maken";
    actionCol.appendChild(button);
    form.appendChild(actionCol);

    snapshotCard.body.appendChild(form);

    if (snapshots.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Nog geen snapshots beschikbaar.";
        snapshotCard.body.appendChild(empty);
        host.appendChild(snapshotCard.card);
        return;
    }

    const list = document.createElement("div");
    list.className = "list-group list-group-flush";

    snapshots.forEach((snapshot) => {
        const item = document.createElement("div");
        item.className = "list-group-item px-0";

        const header = document.createElement("div");
        header.className = "d-flex justify-content-between align-items-start gap-3 flex-wrap";

        const titleWrap = document.createElement("div");

        const title = document.createElement("div");
        title.className = "fw-semibold";
        title.textContent = String(snapshot.eventTitle || "").trim() || `Event #${snapshot.idEvent || 0}`;
        titleWrap.appendChild(title);

        const meta = document.createElement("div");
        meta.className = "text-muted small";
        meta.textContent = formatEconomyDate(snapshot.transactionDate);
        titleWrap.appendChild(meta);

        header.appendChild(titleWrap);

        const amount = document.createElement("div");
        amount.className = `fw-semibold ${Number(snapshot.amount || 0) < 0 ? "text-danger" : "text-success"}`;
        amount.textContent = formatCharacterCurrency(snapshot.amount || 0);
        header.appendChild(amount);

        item.appendChild(header);

        const actionRow = document.createElement("div");
        actionRow.className = "mt-2";

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "btn btn-sm btn-outline-danger";
        deleteButton.dataset.action = "delete-character-economy-snapshot";
        deleteButton.dataset.idSnapshot = String(snapshot.id || 0);
        deleteButton.textContent = "Verwijderen";
        actionRow.appendChild(deleteButton);

        item.appendChild(actionRow);
        list.appendChild(item);
    });

    snapshotCard.body.appendChild(list);
    host.appendChild(snapshotCard.card);
}

function renderBankAccountEditor(host, character, balance) {
    const canEdit = canEditBankAccountForCharacter(character);
    const fieldWrap = document.createElement("div");
    fieldWrap.className = "mb-3";

    const label = document.createElement("label");
    label.className = "form-label";
    label.textContent = "Saldo bankrekening";
    fieldWrap.appendChild(label);

    if (!canEdit) {
        const value = document.createElement("div");
        value.className = "form-control-plaintext fw-semibold";
        value.textContent = formatCharacterCurrency(balance);
        fieldWrap.appendChild(value);
        host.appendChild(fieldWrap);
        return;
    }

    const inputGroup = document.createElement("div");
    inputGroup.className = "input-group";

    const input = document.createElement("input");
    input.type = "number";
    input.className = "form-control";
    input.step = "0.01";
    input.value = balance.toFixed(2);
    input.dataset.role = "bank-balance-input";
    inputGroup.appendChild(input);

    fieldWrap.appendChild(inputGroup);
    host.appendChild(fieldWrap);
}

function renderTransferSection(host, character, options = {}) {
    const canTransfer = canTransferMoneyForCharacter(character);
    const embedded = options.embedded === true;
    const transferCard = embedded ? null : buildEconomyCard("Overschrijving");
    const targetHost = embedded ? host : transferCard.body;
    const transferTargets = Array.isArray(character?.bankTransferTargets) ? character.bankTransferTargets : [];

    if (embedded) {
        appendEconomySubsectionTitle(targetHost, "Overschrijving");
    }

    if (character.state === "draft") {
        const help = document.createElement("p");
        help.className = "text-muted mb-3";
        help.textContent = "Overschrijvingen zijn pas beschikbaar zodra het personage niet meer in draft staat.";
        targetHost.appendChild(help);

        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-outline-secondary";
        button.disabled = true;
        button.textContent = "Overschrijving";
        targetHost.appendChild(button);

        if (!embedded) {
            host.appendChild(transferCard.card);
        }
        return;
    }

    if (!canTransfer) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Alleen administrator- en director-gebruikers kunnen een overschrijving uitvoeren.";
        targetHost.appendChild(help);
        if (!embedded) {
            host.appendChild(transferCard.card);
        }
        return;
    }

    if (transferTargets.length === 0) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Er zijn geen actieve doelpersonages beschikbaar.";
        targetHost.appendChild(help);
        if (!embedded) {
            host.appendChild(transferCard.card);
        }
        return;
    }

    const form = document.createElement("div");
    form.dataset.role = "bank-transfer-form";

    const targetWrap = document.createElement("div");
    targetWrap.className = "mb-3";
    const targetLabel = document.createElement("label");
    targetLabel.className = "form-label";
    targetLabel.textContent = "Ontvanger";
    targetWrap.appendChild(targetLabel);
    const targetSelect = document.createElement("select");
    targetSelect.className = "form-select";
    targetSelect.dataset.role = "bank-transfer-target";
    transferTargets.forEach((target) => {
        const option = document.createElement("option");
        option.value = String(target.id);
        option.textContent = target.displayName || `Personage #${target.id}`;
        targetSelect.appendChild(option);
    });
    targetWrap.appendChild(targetSelect);
    form.appendChild(targetWrap);

    const dateAmountRow = document.createElement("div");
    dateAmountRow.className = "row g-3";

    const dateWrap = document.createElement("div");
    dateWrap.className = "col-12 col-md-6 mb-3";
    const dateLabel = document.createElement("label");
    dateLabel.className = "form-label";
    dateLabel.textContent = "Datum";
    dateWrap.appendChild(dateLabel);
    const dateInput = document.createElement("input");
    dateInput.type = "date";
    dateInput.className = "form-control";
    dateInput.dataset.role = "bank-transfer-date";
    dateInput.value = character.defaultBankTransferDate || getDefaultBankTransferDateClient();
    dateWrap.appendChild(dateInput);
    dateAmountRow.appendChild(dateWrap);

    const amountWrap = document.createElement("div");
    amountWrap.className = "col-12 col-md-6 mb-3";
    const amountLabel = document.createElement("label");
    amountLabel.className = "form-label";
    amountLabel.textContent = "Bedrag";
    amountWrap.appendChild(amountLabel);
    const amountInput = document.createElement("input");
    amountInput.type = "number";
    amountInput.className = "form-control";
    amountInput.step = "0.01";
    amountInput.min = "0.01";
    amountInput.max = String(Math.max(0, bankAmountFromCharacter(character)).toFixed(2));
    amountInput.dataset.role = "bank-transfer-amount";
    amountWrap.appendChild(amountInput);
    dateAmountRow.appendChild(amountWrap);
    form.appendChild(dateAmountRow);

    const descriptionWrap = document.createElement("div");
    descriptionWrap.className = "mb-3";
    const descriptionLabel = document.createElement("label");
    descriptionLabel.className = "form-label";
    descriptionLabel.textContent = "Mededeling";
    descriptionWrap.appendChild(descriptionLabel);
    const descriptionInput = document.createElement("input");
    descriptionInput.type = "text";
    descriptionInput.className = "form-control";
    descriptionInput.maxLength = 255;
    descriptionInput.dataset.role = "bank-transfer-description";
    descriptionWrap.appendChild(descriptionInput);
    form.appendChild(descriptionWrap);

    const submitButton = document.createElement("button");
    submitButton.type = "button";
    submitButton.className = "btn btn-primary";
    submitButton.dataset.action = "create-bank-transfer";
    submitButton.textContent = "Overschrijving";
    form.appendChild(submitButton);

    targetHost.appendChild(form);
    if (!embedded) {
        host.appendChild(transferCard.card);
    }
}

function renderTransactionsSection(host, character, options = {}) {
    const embedded = options.embedded === true;
    const transactionCard = embedded ? null : buildEconomyCard("Verrichtingen");
    const targetHost = embedded ? host : transactionCard.body;
    const transactions = Array.isArray(character?.bankTransactions) ? character.bankTransactions : [];
    const canDeleteTransactions = Boolean(character?.canDeleteBankTransactions);
    const hasAnyDeletableTransaction = canDeleteTransactions && transactions.some((transaction) => Boolean(transaction?.canDelete));

    if (embedded) {
        appendEconomySubsectionTitle(targetHost, "Verrichtingen");
    }

    if (transactions.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Nog geen verrichtingen beschikbaar.";
        targetHost.appendChild(empty);
        if (!embedded) {
            host.appendChild(transactionCard.card);
        }
        return;
    }

    const tableWrap = document.createElement("div");
    tableWrap.className = "economy-transactions-table";

    const tableInner = document.createElement("div");
    tableInner.className = "economy-transactions-table-inner";

    const header = document.createElement("div");
    header.className = "economy-transactions-header";
    header.dataset.canDelete = hasAnyDeletableTransaction ? "true" : "false";

    const headerLabels = ["Naam", "Datum", "Mededeling", "Bedrag"];
    headerLabels.forEach((labelText) => {
        const label = document.createElement("div");
        label.className = "economy-transactions-header-label";
        label.textContent = labelText;
        header.appendChild(label);
    });

    if (hasAnyDeletableTransaction) {
        const actionSpacer = document.createElement("div");
        actionSpacer.className = "economy-transactions-header-spacer";
        actionSpacer.setAttribute("aria-hidden", "true");
        header.appendChild(actionSpacer);
    }

    const list = document.createElement("div");
    list.className = "list-group list-group-flush";

    transactions.forEach((transaction) => {
        const item = document.createElement("div");
        item.className = "list-group-item px-0";

        const row = document.createElement("div");
        row.className = "economy-transaction-row";
        const rowCanDelete = canDeleteTransactions && Boolean(transaction?.canDelete);
        row.dataset.canDelete = rowCanDelete ? "true" : "false";

        const nameEl = document.createElement("div");
        nameEl.className = "economy-transaction-cell economy-transaction-cell--name";
        nameEl.textContent = transaction.counterpartyName || "Onbekend personage";
        row.appendChild(nameEl);

        const dateEl = document.createElement("div");
        dateEl.className = "economy-transaction-cell economy-transaction-cell--date";
        dateEl.textContent = formatEconomyDate(transaction.transactionDate);
        row.appendChild(dateEl);

        const descriptionEl = document.createElement("div");
        descriptionEl.className = "economy-transaction-cell economy-transaction-cell--description";
        const isOutgoing = transaction.direction === "outgoing";
        const signedAmountLabel = `${isOutgoing ? "-" : "+"}${formatCharacterCurrency(transaction.amount)}`;
        descriptionEl.textContent = transaction.description || "";
        row.appendChild(descriptionEl);

        const amountEl = document.createElement("div");
        amountEl.className = [
            "economy-transaction-cell",
            "economy-transaction-cell--amount",
            isOutgoing ? "text-danger" : "text-success"
        ].join(" ");
        amountEl.textContent = signedAmountLabel;
        row.appendChild(amountEl);

        if (rowCanDelete) {
            const actionCol = document.createElement("div");
            actionCol.className = "economy-transaction-cell economy-transaction-cell--action";

            const deleteBtn = document.createElement("button");
            deleteBtn.type = "button";
            deleteBtn.className = "btn btn-sm btn-outline-danger";
            deleteBtn.dataset.action = "delete-bank-transaction";
            deleteBtn.dataset.idTransaction = String(transaction.id || 0);
            deleteBtn.textContent = "Verwijderen";
            actionCol.appendChild(deleteBtn);

            row.appendChild(actionCol);
        }

        item.appendChild(row);
        list.appendChild(item);
    });

    tableInner.appendChild(header);
    tableInner.appendChild(list);
    tableWrap.appendChild(tableInner);

    targetHost.appendChild(tableWrap);
    if (!embedded) {
        host.appendChild(transactionCard.card);
    }
}

function renderCharacterEconomyTab(character) {
    const container = document.getElementById("economyTabContent");
    if (!container || !character) return;

    const bankAmount = getDisplayedCharacterBankAccountAmount(character);
    container.innerHTML = "";
    container.classList.add("row", "g-4");

    const leftCol = document.createElement("div");
    leftCol.className = "col-12 col-lg-6";
    leftCol.dataset.role = "economy-left-column";

    const rightCol = document.createElement("div");
    rightCol.className = "col-12 col-lg-6";
    rightCol.dataset.role = "economy-right-column";

    renderIncomeSection(leftCol, character);

    const accountCard = buildEconomyCard("Bankrekening");
    if (character.state === "draft") {
        const multiplierText = characterHasTraitFlagClient(character, "savings_bank_multiplier", "Spaarder") ? "15x inkomsten" : "10x inkomsten";

        const help = document.createElement("p");
        help.className = "text-muted small mb-3";
        help.textContent = `Draft-personages tonen een statisch startsaldo op basis van ${multiplierText}.`;
        accountCard.body.appendChild(help);
    }

    renderBankAccountEditor(accountCard.body, character, bankAmount);
    renderTransferSection(accountCard.body, character, { embedded: true });
    renderTransactionsSection(accountCard.body, character, { embedded: true });
    leftCol.appendChild(accountCard.card);
    renderCharacterSecuritiesSection(leftCol, character);
    renderCharacterEconomySnapshotsSection(rightCol, character);
    renderCompanySharesSection(rightCol, character);

    container.appendChild(leftCol);
    container.appendChild(rightCol);
}

function bankAmountFromCharacter(character) {
    return Math.max(0, Number(getDisplayedCharacterBankAccountAmount(character) || 0));
}

async function saveBankBalance() {
    const input = document.querySelector("[data-role='bank-balance-input']");
    if (!input || !currentCharacter?.id) return;

    const amount = parseBankAmount(input.value);
    if (amount === null) {
        alert("Geef een geldig saldo op.");
        return;
    }

    try {
        await updateCharacter({
            id: currentCharacter.id,
            bankaccount: amount
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij bewaren van banksaldo:", err);
        alert("Kon het banksaldo niet bewaren.");
    }
}

async function saveCompanyShareAssignment(select) {
    if (!select || !currentCharacter?.id) return;

    const idLinkCharacterTrait = Number(select.dataset.idLinkCharacterTrait || 0);
    if (idLinkCharacterTrait <= 0) return;

    select.disabled = true;

    try {
        const selectedCompanyId = Number(select.value || 0);
        await apiFetchJson("api/characters/saveCompanyShare.php", {
            method: "POST",
            body: {
                action: selectedCompanyId > 0 ? "assign_company" : "clear_company",
                idLinkCharacterTrait,
                idCompany: selectedCompanyId > 0 ? selectedCompanyId : null
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij koppelen van aandeel aan bedrijf:", err);
        alert(getApiErrorMessage(err, "Kon het aandeel niet koppelen aan het gekozen bedrijf."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function increaseCompanyShareRank(button) {
    if (!button || !currentCharacter?.id) return;

    const idLinkCharacterTrait = Number(button.dataset.idLinkCharacterTrait || 0);
    if (idLinkCharacterTrait <= 0) return;

    button.disabled = true;

    try {
        await apiFetchJson("api/characters/saveCompanyShare.php", {
            method: "POST",
            body: {
                action: "increase_rank",
                idLinkCharacterTrait
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij verhogen van aandelenpercentage:", err);
        alert(getApiErrorMessage(err, "Kon het aandelenpercentage niet verhogen."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function decreaseCompanyShareRank(button) {
    if (!button || !currentCharacter?.id) return;

    const idLinkCharacterTrait = Number(button.dataset.idLinkCharacterTrait || 0);
    if (idLinkCharacterTrait <= 0) return;

    button.disabled = true;

    try {
        await apiFetchJson("api/characters/saveCompanyShare.php", {
            method: "POST",
            body: {
                action: "decrease_rank",
                idLinkCharacterTrait
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij verlagen van aandelenpercentage:", err);
        alert(getApiErrorMessage(err, "Kon het aandelenpercentage niet verlagen."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function buyNewCompanyShare(button) {
    if (!button || !currentCharacter?.id) return;

    const select = document.querySelector("[data-role='company-share-purchase-select']");
    if (!select || select.disabled) return;

    const [idCompanyRaw, shareClassRaw] = String(select.value || "").split(":");
    const idCompany = Number(idCompanyRaw || 0);
    const shareClass = String(shareClassRaw || "").trim();

    if (idCompany <= 0 || (shareClass !== "A" && shareClass !== "B")) {
        alert("Kies eerst een bedrijf en aandelentype.");
        return;
    }

    button.disabled = true;
    select.disabled = true;

    try {
        await apiFetchJson("api/characters/buyCompanyShare.php", {
            method: "POST",
            body: {
                idCharacter: currentCharacter.id,
                idCompany,
                shareClass
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij kopen van nieuw aandeel:", err);
        alert(getApiErrorMessage(err, "Kon het aandeel niet kopen."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function submitBankTransfer() {
    const form = document.querySelector("[data-role='bank-transfer-form']");
    if (!form || !currentCharacter?.id) return;

    const targetSelect = form.querySelector("[data-role='bank-transfer-target']");
    const dateInput = form.querySelector("[data-role='bank-transfer-date']");
    const amountInput = form.querySelector("[data-role='bank-transfer-amount']");
    const descriptionInput = form.querySelector("[data-role='bank-transfer-description']");

    const idTargetCharacter = Number(targetSelect?.value || 0);
    const transactionDate = dateInput?.value || "";
    const amount = parseBankAmount(amountInput?.value || "");
    const description = descriptionInput?.value || "";

    if (idTargetCharacter <= 0) {
        alert("Kies een ontvanger.");
        return;
    }

    if (!transactionDate) {
        alert("Kies een datum voor de overschrijving.");
        return;
    }

    if (amount === null || amount <= 0) {
        alert("Geef een geldig bedrag op.");
        return;
    }

    const availableBalance = bankAmountFromCharacter(currentCharacter);
    if (amount > availableBalance) {
        alert("Je kan niet meer geld overschrijven dan het beschikbare saldo op de rekening.");
        return;
    }

    try {
        await apiFetchJson("api/characters/saveBankTransfer.php", {
            method: "POST",
            body: {
                idSourceCharacter: currentCharacter.id,
                idTargetCharacter,
                transactionDate,
                amount,
                description
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij bewaren van overschrijving:", err);
        alert("Kon de overschrijving niet bewaren.");
    }
}

async function deleteBankTransaction(idTransaction) {
    if (!currentCharacter?.id || idTransaction <= 0) return;

    const shouldDelete = window.confirm("Deze verrichting verwijderen en het bedrag terugboeken?");
    if (!shouldDelete) {
        return;
    }

    try {
        await apiFetchJson("api/characters/deleteBankTransaction.php", {
            method: "POST",
            body: { idTransaction }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij verwijderen van verrichting:", err);
        alert("Kon de verrichting niet verwijderen.");
    }
}

async function createCharacterEconomySnapshot(button) {
    if (!button || !currentCharacter?.id) return;

    const select = document.querySelector("[data-role='character-economy-snapshot-event']");
    if (!select || select.disabled) return;

    const idEvent = Number(select.value || 0);
    if (idEvent <= 0) {
        alert("Kies eerst een event.");
        return;
    }

    button.disabled = true;
    select.disabled = true;

    try {
        await apiFetchJson("api/characters/saveCharacterEconomySnapshot.php", {
            method: "POST",
            body: {
                idCharacter: currentCharacter.id,
                idEvent
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij maken van economiesnapshot:", err);
        alert(getApiErrorMessage(err, "Kon de economiesnapshot niet bewaren."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function deleteCharacterEconomySnapshot(idSnapshot) {
    if (!currentCharacter?.id || idSnapshot <= 0) return;

    const shouldDelete = window.confirm("Deze snapshot verwijderen en het bedrag terugboeken op de bankrekening?");
    if (!shouldDelete) {
        return;
    }

    try {
        await apiFetchJson("api/characters/deleteCharacterEconomySnapshot.php", {
            method: "POST",
            body: { idSnapshot }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij verwijderen van economiesnapshot:", err);
        alert(getApiErrorMessage(err, "Kon de economiesnapshot niet verwijderen."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function saveSecuritiesSettings() {
    if (!currentCharacter?.id) return;

    const managerTypeSelect = document.querySelector("[data-role='securities-manager-type']");
    const riskProfileSelect = document.querySelector("[data-role='securities-risk-profile']");
    const managerCharacterSelect = document.querySelector("[data-role='securities-manager-character']");

    if (!managerTypeSelect || !riskProfileSelect) return;

    const managerType = String(managerTypeSelect.value || "self");
    const riskProfile = Number(riskProfileSelect.value || 3);
    const managerCharacterId = managerType === "third"
        ? Number(managerCharacterSelect?.value || 0)
        : 0;

    if (managerType === "third" && managerCharacterId <= 0) {
        return;
    }

    try {
        await apiFetchJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "save_settings",
                idCharacter: currentCharacter.id,
                managerType,
                riskProfile,
                managerCharacterId: managerType === "third" ? managerCharacterId : null
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij bewaren van effecteninstellingen:", err);
        alert(getApiErrorMessage(err, "Kon de effecteninstellingen niet bewaren."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function depositSecuritiesBalance() {
    if (!currentCharacter?.id) return;

    const input = document.querySelector("[data-role='securities-deposit-amount']");
    if (!input) return;

    const amount = parseBankAmount(input.value);
    if (amount === null || amount <= 0) {
        alert("Geef een geldig bedrag op om te storten.");
        return;
    }

    try {
        await apiFetchJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "deposit",
                idCharacter: currentCharacter.id,
                amount
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij storten naar effectenportefeuille:", err);
        alert(getApiErrorMessage(err, "Kon het bedrag niet naar de effectenportefeuille storten."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function withdrawSecuritiesBalance() {
    if (!currentCharacter?.id) return;

    const input = document.querySelector("[data-role='securities-manual-withdraw-amount']");
    if (!input) return;

    const amount = parseBankAmount(input.value);
    if (amount === null || amount <= 0) {
        alert("Geef een geldig bedrag op om op te nemen.");
        return;
    }

    try {
        await apiFetchJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "manual_withdrawal",
                idCharacter: currentCharacter.id,
                amount
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij opname uit effectenportefeuille:", err);
        alert(getApiErrorMessage(err, "Kon het bedrag niet uit de effectenportefeuille halen."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function rerollSecuritiesSnapshot(idSnapshot) {
    if (!currentCharacter?.id || idSnapshot <= 0) return;

    try {
        await apiFetchJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "reroll_snapshot",
                idCharacter: currentCharacter.id,
                idSnapshot
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij opnieuw genereren van rendement:", err);
        alert(getApiErrorMessage(err, "Kon het rendement niet opnieuw genereren."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function approveSecuritiesSnapshot(idSnapshot) {
    if (!currentCharacter?.id || idSnapshot <= 0) return;

    try {
        await apiFetchJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "approve_snapshot",
                idCharacter: currentCharacter.id,
                idSnapshot
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij goedkeuren van rendement:", err);
        alert(getApiErrorMessage(err, "Kon het rendement niet goedkeuren."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

async function withdrawSecuritiesSnapshot(idSnapshot) {
    if (!currentCharacter?.id || idSnapshot <= 0) return;

    const input = document.querySelector(`[data-role='securities-snapshot-withdraw-amount'][data-id-snapshot='${idSnapshot}']`);
    if (!input) return;

    const amount = parseBankAmount(input.value);
    if (amount === null || amount <= 0) {
        alert("Geef een geldig bedrag op om uit de effectenportefeuille op te nemen.");
        return;
    }

    try {
        await apiFetchJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "withdraw_snapshot",
                idCharacter: currentCharacter.id,
                idSnapshot,
                amount
            }
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij snapshotopname uit effectenportefeuille:", err);
        alert(getApiErrorMessage(err, "Kon het snapshotbedrag niet opnemen."));
        await getCharacter(currentCharacter.id, null, "economy");
    }
}

function setupEconomySectionListeners() {
    if (window.aetherEconomyListenersInitialized) return;
    window.aetherEconomyListenersInitialized = true;

    document.addEventListener("click", async (e) => {
        const increaseShareButton = e.target.closest("button[data-action='increase-company-share-rank']");
        if (increaseShareButton && !increaseShareButton.disabled) {
            await increaseCompanyShareRank(increaseShareButton);
            return;
        }

        const decreaseShareButton = e.target.closest("button[data-action='decrease-company-share-rank']");
        if (decreaseShareButton && !decreaseShareButton.disabled) {
            await decreaseCompanyShareRank(decreaseShareButton);
            return;
        }

        const buyNewShareButton = e.target.closest("button[data-action='buy-new-company-share']");
        if (buyNewShareButton && !buyNewShareButton.disabled) {
            await buyNewCompanyShare(buyNewShareButton);
            return;
        }

        const transferButton = e.target.closest("button[data-action='create-bank-transfer']");
        if (transferButton && !transferButton.disabled) {
            await submitBankTransfer();
            return;
        }

        const snapshotButton = e.target.closest("button[data-action='create-character-economy-snapshot']");
        if (snapshotButton && !snapshotButton.disabled) {
            await createCharacterEconomySnapshot(snapshotButton);
            return;
        }

        const depositSecuritiesButton = e.target.closest("button[data-action='deposit-securities-balance']");
        if (depositSecuritiesButton && !depositSecuritiesButton.disabled) {
            await depositSecuritiesBalance();
            return;
        }

        const withdrawSecuritiesButton = e.target.closest("button[data-action='withdraw-securities-balance']");
        if (withdrawSecuritiesButton && !withdrawSecuritiesButton.disabled) {
            await withdrawSecuritiesBalance();
            return;
        }

        const rerollSecuritiesButton = e.target.closest("button[data-action='reroll-securities-snapshot']");
        if (rerollSecuritiesButton && !rerollSecuritiesButton.disabled) {
            const idSnapshot = Number(rerollSecuritiesButton.dataset.idSnapshot || 0);
            await rerollSecuritiesSnapshot(idSnapshot);
            return;
        }

        const approveSecuritiesButton = e.target.closest("button[data-action='approve-securities-snapshot']");
        if (approveSecuritiesButton && !approveSecuritiesButton.disabled) {
            const idSnapshot = Number(approveSecuritiesButton.dataset.idSnapshot || 0);
            await approveSecuritiesSnapshot(idSnapshot);
            return;
        }

        const withdrawSnapshotSecuritiesButton = e.target.closest("button[data-action='withdraw-securities-snapshot']");
        if (withdrawSnapshotSecuritiesButton && !withdrawSnapshotSecuritiesButton.disabled) {
            const idSnapshot = Number(withdrawSnapshotSecuritiesButton.dataset.idSnapshot || 0);
            await withdrawSecuritiesSnapshot(idSnapshot);
            return;
        }

        const deleteSnapshotButton = e.target.closest("button[data-action='delete-character-economy-snapshot']");
        if (deleteSnapshotButton && !deleteSnapshotButton.disabled) {
            const idSnapshot = Number(deleteSnapshotButton.dataset.idSnapshot || 0);
            await deleteCharacterEconomySnapshot(idSnapshot);
            return;
        }

        const deleteButton = e.target.closest("button[data-action='delete-bank-transaction']");
        if (deleteButton && !deleteButton.disabled) {
            const idTransaction = Number(deleteButton.dataset.idTransaction || 0);
            await deleteBankTransaction(idTransaction);
        }
    });

    document.addEventListener("change", async (e) => {
        const shareCompanySelect = e.target.closest("select[data-role='company-share-company-select']");
        if (shareCompanySelect && !shareCompanySelect.disabled) {
            await saveCompanyShareAssignment(shareCompanySelect);
            return;
        }

        const securitiesManagerTypeSelect = e.target.closest("select[data-role='securities-manager-type']");
        if (securitiesManagerTypeSelect && !securitiesManagerTypeSelect.disabled) {
            const thirdManagerWrap = document.querySelector("[data-role='securities-third-manager-wrap']");
            if (thirdManagerWrap) {
                thirdManagerWrap.classList.toggle("d-none", String(securitiesManagerTypeSelect.value || "self") !== "third");
            }

            if (String(securitiesManagerTypeSelect.value || "self") !== "third") {
                await saveSecuritiesSettings();
            }
            return;
        }

        const securitiesManagerCharacterSelect = e.target.closest("select[data-role='securities-manager-character']");
        if (securitiesManagerCharacterSelect && !securitiesManagerCharacterSelect.disabled) {
            await saveSecuritiesSettings();
            return;
        }

        const securitiesRiskProfileSelect = e.target.closest("select[data-role='securities-risk-profile']");
        if (securitiesRiskProfileSelect && !securitiesRiskProfileSelect.disabled) {
            await saveSecuritiesSettings();
            return;
        }

        const balanceInput = e.target.closest("[data-role='bank-balance-input']");
        if (!balanceInput || balanceInput.disabled) return;

        await saveBankBalance();
    });
}

function showEconomyTab(character) {
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const sheetBody = document.getElementById("sheetBody");
    if (sheetBody) sheetBody.classList.remove("d-none");

    const characterForm = document.getElementById("characterForm");
    if (characterForm) characterForm.classList.add("d-none");
    const skills = document.getElementById("skills");
    if (skills) skills.classList.add("d-none");

    const backgroundTab = document.getElementById("backgroundTab");
    if (backgroundTab) backgroundTab.classList.add("d-none");
    const diaryTab = document.getElementById("diaryTab");
    if (diaryTab) diaryTab.classList.add("d-none");
    const personalityTab = document.getElementById("personalityTab");
    if (personalityTab) personalityTab.classList.add("d-none");
    const passportTab = document.getElementById("passportTab");
    if (passportTab) passportTab.classList.add("d-none");

    const economyTab = document.getElementById("economyTab");
    if (!economyTab) return;

    economyTab.classList.remove("d-none");
    economyTab.style.display = "block";
    economyTab.hidden = false;
    economyTab.classList.add("p-3");
    economyTab.style.backgroundColor = "#fff";
    economyTab.style.minHeight = "300px";

    renderCharacterEconomyTab(currentCharacter || character);
}

setupEconomySectionListeners();
