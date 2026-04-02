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

function characterHasTraitByName(character, traitName) {
    const searchName = String(traitName || "").trim().toLowerCase();
    return getCharacterLinkedTraits(character).some((trait) => (
        String(trait?.name || "").trim().toLowerCase() === searchName
    ));
}

function getDraftBankAccountAmount(character) {
    const totalIncome = getCharacterTraitIncomeTotal(character);
    const multiplier = characterHasTraitByName(character, "Spaarder") ? 15 : 10;
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

function getCharacterCompanyShares(character) {
    return Array.isArray(character?.companyShares) ? character.companyShares : [];
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
    const remainingSharePercentage = Number(company?.remainingSharePercentage ?? 0);
    const label = `${name} (${formatCharacterCurrency(value)})`;

    if (company?.isUnavailableCurrent) {
        return `${label} - huidige koppeling`;
    }

    return `${label} - nog ${remainingSharePercentage}% beschikbaar`;
}

function renderCompanySharesSection(host, character) {
    const companyShares = getCharacterCompanyShares(character);
    if (!host || companyShares.length === 0) {
        return;
    }

    const shareCard = buildEconomyCard("Aandelen");

    companyShares.forEach((share, index) => {
        const item = document.createElement("div");
        item.className = "economy-share-item";
        if (index < companyShares.length - 1) {
            item.classList.add("mb-4", "pb-4", "border-bottom");
        }

        const header = document.createElement("div");
        header.className = "d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2";

        const titleWrap = document.createElement("div");
        const title = document.createElement("h5");
        title.className = "mb-1";
        title.textContent = share.name || "Aandeel";
        titleWrap.appendChild(title);

        const meta = document.createElement("p");
        meta.className = "text-muted mb-0";
        meta.textContent = `${share.shareClass || ""}-aandeel in ${share.companyTypeLabel || "bedrijf"}`;
        titleWrap.appendChild(meta);
        header.appendChild(titleWrap);

        const percentageBadge = document.createElement("span");
        percentageBadge.className = "badge text-bg-secondary fs-6";
        percentageBadge.textContent = `${Number(share.percentage || 0)}%`;
        header.appendChild(percentageBadge);
        item.appendChild(header);

        const companyInfo = document.createElement("p");
        companyInfo.className = "mb-3";
        if (share.companyId) {
            companyInfo.textContent = `${share.companyName || `Bedrijf #${share.companyId}`} gekoppeld. Bedrijfswaarde: ${formatCharacterCurrency(share.companyValue || 0)}.`;
        } else {
            companyInfo.classList.add("text-muted");
            companyInfo.textContent = "Nog niet gekoppeld aan een bedrijf.";
        }
        item.appendChild(companyInfo);

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

        if (share.canIncreaseRank) {
            const actionWrap = document.createElement("div");
            actionWrap.className = "d-flex justify-content-between align-items-center gap-3 flex-wrap";

            const help = document.createElement("div");
            help.className = "text-muted";

            const hasAssignedCompany = Number(share.companyId || 0) > 0;
            const canIncreaseByCapacity = Number(share.remainingSharePercentage || 0) > Number(share.percentage || 0);
            const canIncrease = hasAssignedCompany
                && canIncreaseByCapacity
                && Boolean(share.canAffordNextRank);

            if (!hasAssignedCompany) {
                help.textContent = "Koppel eerst een bedrijf om dit aandeel verder te verhogen.";
            } else if (!canIncreaseByCapacity) {
                help.textContent = "Dit bedrijf heeft geen vrije aandelen meer voor +1%.";
            } else if (!share.canAffordNextRank) {
                help.textContent = `+1% kost ${formatCharacterCurrency(share.nextPercentageCost || 0)}. Onvoldoende saldo op de bankrekening.`;
            } else {
                help.textContent = `+1% kost ${formatCharacterCurrency(share.nextPercentageCost || 0)}.`;
            }
            actionWrap.appendChild(help);

            const increaseButton = document.createElement("button");
            increaseButton.type = "button";
            increaseButton.className = "btn btn-outline-primary";
            increaseButton.dataset.action = "increase-company-share-rank";
            increaseButton.dataset.idLinkCharacterTrait = String(share.idLinkCharacterTrait || 0);
            increaseButton.disabled = !canIncrease;
            increaseButton.textContent = "+1%";
            actionWrap.appendChild(increaseButton);

            item.appendChild(actionWrap);
        }

        shareCard.body.appendChild(item);
    });

    host.appendChild(shareCard.card);
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

function renderTransferSection(host, character) {
    const canTransfer = canTransferMoneyForCharacter(character);
    const transferCard = buildEconomyCard("Overschrijving");
    const transferTargets = Array.isArray(character?.bankTransferTargets) ? character.bankTransferTargets : [];

    if (character.state === "draft") {
        const help = document.createElement("p");
        help.className = "text-muted mb-3";
        help.textContent = "Overschrijvingen zijn pas beschikbaar zodra het personage niet meer in draft staat.";
        transferCard.body.appendChild(help);

        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-outline-secondary";
        button.disabled = true;
        button.textContent = "Overschrijving";
        transferCard.body.appendChild(button);

        host.appendChild(transferCard.card);
        return;
    }

    if (!canTransfer) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Alleen administrator- en director-gebruikers kunnen een overschrijving uitvoeren.";
        transferCard.body.appendChild(help);
        host.appendChild(transferCard.card);
        return;
    }

    if (transferTargets.length === 0) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Er zijn geen actieve doelpersonages beschikbaar.";
        transferCard.body.appendChild(help);
        host.appendChild(transferCard.card);
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

    const dateWrap = document.createElement("div");
    dateWrap.className = "mb-3";
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
    form.appendChild(dateWrap);

    const amountWrap = document.createElement("div");
    amountWrap.className = "mb-3";
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
    form.appendChild(amountWrap);

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

    transferCard.body.appendChild(form);
    host.appendChild(transferCard.card);
}

function renderTransactionsSection(host, character) {
    const transactionCard = buildEconomyCard("Verrichtingen");
    const transactions = Array.isArray(character?.bankTransactions) ? character.bankTransactions : [];
    const canDeleteTransactions = Boolean(character?.canDeleteBankTransactions);

    if (transactions.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-muted mb-0";
        empty.textContent = "Nog geen verrichtingen beschikbaar.";
        transactionCard.body.appendChild(empty);
        host.appendChild(transactionCard.card);
        return;
    }

    const tableWrap = document.createElement("div");
    tableWrap.className = "economy-transactions-table";

    const tableInner = document.createElement("div");
    tableInner.className = "economy-transactions-table-inner";

    const header = document.createElement("div");
    header.className = "economy-transactions-header";
    header.dataset.canDelete = canDeleteTransactions ? "true" : "false";

    const headerLabels = ["Naam", "Datum", "Mededeling", "Bedrag"];
    headerLabels.forEach((labelText) => {
        const label = document.createElement("div");
        label.className = "economy-transactions-header-label";
        label.textContent = labelText;
        header.appendChild(label);
    });

    if (canDeleteTransactions) {
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
        row.dataset.canDelete = canDeleteTransactions ? "true" : "false";

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
        descriptionEl.textContent = transaction.description || "";
        row.appendChild(descriptionEl);

        const amountEl = document.createElement("div");
        const isOutgoing = transaction.direction === "outgoing";
        amountEl.className = [
            "economy-transaction-cell",
            "economy-transaction-cell--amount",
            isOutgoing ? "text-danger" : "text-success"
        ].join(" ");
        amountEl.textContent = `${isOutgoing ? "-" : "+"}${formatCharacterCurrency(transaction.amount)}`;
        row.appendChild(amountEl);

        if (canDeleteTransactions) {
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

    transactionCard.body.appendChild(tableWrap);
    host.appendChild(transactionCard.card);
}

function renderCharacterEconomyTab(character) {
    const container = document.getElementById("economyTabContent");
    if (!container || !character) return;

    const bankAmount = getDisplayedCharacterBankAccountAmount(character);
    const totalIncome = getCharacterTraitIncomeTotal(character);

    container.innerHTML = "";

    const leftCol = document.createElement("div");
    leftCol.className = "col-12";

    const accountCard = buildEconomyCard("Bankrekening");
    addEconomyMetric(accountCard.body, "Trait-inkomsten", formatCharacterCurrency(totalIncome));
    if (character.state === "draft") {
        const multiplierText = characterHasTraitByName(character, "Spaarder") ? "15x inkomsten" : "10x inkomsten";
        addEconomyMetric(accountCard.body, "Startsaldo", formatCharacterCurrency(bankAmount));

        const help = document.createElement("p");
        help.className = "text-muted small mb-3";
        help.textContent = `Draft-personages tonen een statisch startsaldo op basis van ${multiplierText}.`;
        accountCard.body.appendChild(help);
    }

    renderBankAccountEditor(accountCard.body, character, bankAmount);
    leftCol.appendChild(accountCard.card);
    renderCompanySharesSection(leftCol, character);
    renderTransferSection(leftCol, character);
    renderTransactionsSection(leftCol, character);

    container.appendChild(leftCol);
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

function setupEconomySectionListeners() {
    if (window.aetherEconomyListenersInitialized) return;
    window.aetherEconomyListenersInitialized = true;

    document.addEventListener("click", async (e) => {
        const increaseShareButton = e.target.closest("button[data-action='increase-company-share-rank']");
        if (increaseShareButton && !increaseShareButton.disabled) {
            await increaseCompanyShareRank(increaseShareButton);
            return;
        }

        const transferButton = e.target.closest("button[data-action='create-bank-transfer']");
        if (transferButton && !transferButton.disabled) {
            await submitBankTransfer();
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
