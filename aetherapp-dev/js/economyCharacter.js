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

function canEditSecuritiesBalanceForCharacter(character) {
    if (!currentUser || !character) return false;
    if (typeof character.canEditSecuritiesAccount === "boolean") {
        return character.canEditSecuritiesAccount;
    }

    return (currentUser.role === "administrator" || currentUser.role === "director")
        && character.state !== "draft";
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

function getCharacterSecuritiesManagerDisplayName(character) {
    const managerType = String(character?.securitiesManagerType || "self");
    if (managerType === "bank") {
        return "De bank";
    }

    if (managerType === "third") {
        const resolvedDisplayName = String(character?.securitiesManagerDisplayName || "").trim();
        if (resolvedDisplayName !== "") {
            return resolvedDisplayName;
        }

        const selectedManager = (Array.isArray(character?.securitiesManagerOptions) ? character.securitiesManagerOptions : [])
            .find((option) => Number(option?.id || 0) === Number(character?.securitiesManagerCharacterId || 0));
        return String(selectedManager?.displayName || "").trim() || "Onbekend";
    }

    const ownName = `${String(character?.firstName || "").trim()} ${String(character?.lastName || "").trim()}`.trim();
    return ownName || "Eigen beheer";
}

function getCharacterSecuritiesExpectedReturnSummary(character) {
    const balance = getDisplayedCharacterSecuritiesAmount(character);
    const skillLevel = Math.max(0, Math.min(4, Number(character?.securitiesManagerSkillLevel || 0)));
    const basePercentage = [2, 3, 5, 8, 13][skillLevel] ?? 0;
    const variationPercentage = Number(character?.securitiesRiskProfileOptions?.[Number(character?.securitiesRiskProfile || 3)]?.variationPercentage ?? 5);
    const minPercentage = basePercentage - variationPercentage;
    const maxPercentage = basePercentage + variationPercentage;
    const minAmount = balance * (minPercentage / 100);
    const maxAmount = balance * (maxPercentage / 100);

    return {
        basePercentage,
        variationPercentage,
        minPercentage,
        maxPercentage,
        minAmount,
        maxAmount
    };
}

function getApprovedSecuritiesWithdrawalSnapshots(character) {
    return getCharacterSecuritiesSnapshots(character).filter((snapshot) => (
        String(snapshot?.securitiesStatus || "none") === "approved"
    ));
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

function renderSecuritiesIntroduction(host) {
    const intro = document.createElement("p");
    intro.className = "text-muted mb-3";
    intro.textContent = "Je effectenportefeuille stellen beleggingen in B-aandelen voor die verspreidt zijn over verschillende bedrijven en obligaties. Het is een stabiele manier om je inkomen te vergroten, maar je geld staat ook vast voor langere tijd.";
    host.appendChild(intro);
}

function renderSecuritiesProfileSummary(host, character) {
    const balance = getDisplayedCharacterSecuritiesAmount(character);
    if (balance <= 0) {
        return;
    }

    renderSecuritiesBalanceEditor(host, character, balance);

    const managerInfo = document.createElement("div");
    managerInfo.className = "mb-1";
    managerInfo.textContent = `Beheerder: ${getCharacterSecuritiesManagerDisplayName(character)}`;
    host.appendChild(managerInfo);

    const riskInfo = document.createElement("div");
    riskInfo.className = "mb-3";
    riskInfo.textContent = `Risicoprofiel: ${getSecuritiesRiskProfileLabel(character, Number(character?.securitiesRiskProfile || 3))}`;
    host.appendChild(riskInfo);

    const expected = getCharacterSecuritiesExpectedReturnSummary(character);
    const expectedWrap = document.createElement("div");
    expectedWrap.className = "mb-3";

    const expectedTitle = document.createElement("div");
    expectedTitle.className = "fw-semibold mb-1";
    expectedTitle.textContent = "Verwacht rendement";
    expectedWrap.appendChild(expectedTitle);

    const expectedPercentages = document.createElement("div");
    expectedPercentages.className = "text-muted small";
    expectedPercentages.textContent = `${formatEconomyPercentage(expected.minPercentage)} tot ${formatEconomyPercentage(expected.maxPercentage)}`;
    expectedWrap.appendChild(expectedPercentages);

    const expectedAmounts = document.createElement("div");
    expectedAmounts.className = "text-muted small";
    expectedAmounts.textContent = `${formatCharacterCurrency(expected.minAmount)} tot ${formatCharacterCurrency(expected.maxAmount)}`;
    expectedWrap.appendChild(expectedAmounts);

    host.appendChild(expectedWrap);
}

function renderSecuritiesActionButtons(host, character) {
    const isDraft = String(character?.state || "") === "draft";
    if (isDraft) {
        return;
    }

    const canManage = Boolean(character?.canManageSecurities);
    const balance = getDisplayedCharacterSecuritiesAmount(character);
    const approvedSnapshots = getApprovedSecuritiesWithdrawalSnapshots(character);
    const actionWrap = document.createElement("div");
    actionWrap.className = "d-flex align-items-start gap-2 flex-wrap";

    const depositButton = document.createElement("button");
    depositButton.type = "button";
    depositButton.className = "btn btn-success";
    depositButton.dataset.action = "open-securities-deposit-modal";
    depositButton.disabled = !canManage;
    depositButton.textContent = "Geld storten";
    actionWrap.appendChild(depositButton);

    if (balance > 0) {
        const withdrawButton = document.createElement("button");
        withdrawButton.type = "button";
        withdrawButton.className = "btn btn-success";
        withdrawButton.dataset.action = "open-securities-withdrawal-modal";
        withdrawButton.disabled = !canManage || approvedSnapshots.length === 0;
        withdrawButton.textContent = "Geld opnemen";
        actionWrap.appendChild(withdrawButton);
    }

    host.appendChild(actionWrap);

    if (balance > 0 && approvedSnapshots.length === 0) {
        const help = document.createElement("div");
        help.className = "form-text";
        help.textContent = "Opnemen kan zodra er een goedgekeurde snapshot beschikbaar is.";
        host.appendChild(help);
    }
}

let securitiesDepositModalInstance = null;
let securitiesWithdrawalModalInstance = null;

function ensureSecuritiesDepositModal() {
    let modalEl = document.getElementById("securitiesDepositModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "securitiesDepositModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Geld storten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Opgepast, geld dat je stort op je effectenportefeuille staat vast voor langere tijd. Jaarlijks (per event) kan je er tot 30 000Fr kosteloos afhalen. Voor hogere bedragen verlies je 25%.</p>
                        <div class="mb-0">
                            <label class="form-label">Bedrag</label>
                            <input type="text" inputmode="decimal" class="form-control" data-currency-input="true" data-role="securities-deposit-amount">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="button" class="btn btn-success" data-action="confirm-securities-deposit">Geld storten</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    if (!securitiesDepositModalInstance) {
        securitiesDepositModalInstance = new bootstrap.Modal(modalEl);
    }

    return modalEl;
}

function populateSecuritiesDepositModal(character) {
    const modalEl = ensureSecuritiesDepositModal();
    const input = modalEl.querySelector("[data-role='securities-deposit-amount']");
    const submitButton = modalEl.querySelector("button[data-action='confirm-securities-deposit']");
    const canManage = Boolean(character?.canManageSecurities);

    if (input) {
        input.value = "";
        input.max = String(Math.max(0, bankAmountFromCharacter(character)).toFixed(2));
        input.disabled = !canManage;
    }

    if (submitButton) {
        submitButton.disabled = !canManage;
    }
}

function openSecuritiesDepositModal() {
    if (!currentCharacter) return;

    populateSecuritiesDepositModal(currentCharacter);
    securitiesDepositModalInstance?.show();
}

function ensureSecuritiesWithdrawalModal() {
    let modalEl = document.getElementById("securitiesWithdrawalModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "securitiesWithdrawalModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Geld opnemen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Snapshot</label>
                            <select class="form-select" data-role="securities-withdraw-snapshot"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bedrag</label>
                            <input type="text" inputmode="decimal" class="form-control" data-currency-input="true" data-role="securities-withdraw-amount">
                        </div>
                        <div class="small text-muted mb-2" data-role="securities-withdraw-summary"></div>
                        <div class="small text-muted" data-role="securities-withdraw-warning"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="button" class="btn btn-success" data-action="confirm-securities-withdrawal">Geld opnemen</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    if (!securitiesWithdrawalModalInstance) {
        securitiesWithdrawalModalInstance = new bootstrap.Modal(modalEl);
    }

    return modalEl;
}

function getSelectedSecuritiesWithdrawalSnapshot() {
    const modalEl = document.getElementById("securitiesWithdrawalModal");
    const select = modalEl?.querySelector("[data-role='securities-withdraw-snapshot']");
    const idSnapshot = Number(select?.value || 0);
    return getApprovedSecuritiesWithdrawalSnapshots(currentCharacter).find((snapshot) => Number(snapshot?.id || 0) === idSnapshot) || null;
}

function updateSecuritiesWithdrawalModalState() {
    const modalEl = document.getElementById("securitiesWithdrawalModal");
    if (!modalEl || !currentCharacter) return;

    const snapshot = getSelectedSecuritiesWithdrawalSnapshot();
    const amountInput = modalEl.querySelector("[data-role='securities-withdraw-amount']");
    const summary = modalEl.querySelector("[data-role='securities-withdraw-summary']");
    const warning = modalEl.querySelector("[data-role='securities-withdraw-warning']");
    const submitButton = modalEl.querySelector("button[data-action='confirm-securities-withdrawal']");

    if (!amountInput || !summary || !warning || !submitButton) {
        return;
    }

    if (!snapshot) {
        summary.textContent = "Geen snapshot beschikbaar.";
        warning.textContent = "";
        submitButton.disabled = true;
        return;
    }

    const currentWithdrawalAmount = Number(snapshot?.securitiesSnapshotWithdrawalAmount || 0);
    const availableAmount = getDisplayedCharacterSecuritiesAmount(currentCharacter) + currentWithdrawalAmount;
    amountInput.max = String(Math.max(0, availableAmount).toFixed(2));

    const amount = parseBankAmount(amountInput.value);
    const resolvedAmount = amount === null || amount < 0 ? 0 : amount;
    const hasLoss = Boolean(snapshot?.securitiesEventEnded) || resolvedAmount > 30000;
    const bankAmount = hasLoss ? resolvedAmount * 0.75 : resolvedAmount;

    summary.textContent = `Huidige opname voor deze snapshot: ${formatCharacterCurrency(currentWithdrawalAmount)}. Maximaal beschikbaar om aan te passen: ${formatCharacterCurrency(availableAmount)}.`;

    if (resolvedAmount <= 0) {
        warning.textContent = "Een bedrag van 0,00 Fr zet de snapshotopname terug.";
    } else if (hasLoss) {
        warning.textContent = Boolean(snapshot?.securitiesEventEnded)
            ? `Opgepast: het event van deze snapshot is al afgelopen. Daarom geldt 25% verlies. Er wordt ${formatCharacterCurrency(bankAmount)} op de bankrekening gezet.`
            : `Opgepast: boven 30 000,00 Fr geldt 25% verlies. Er wordt ${formatCharacterCurrency(bankAmount)} op de bankrekening gezet.`;
    } else {
        warning.textContent = "Deze opname blijft binnen de kosteloze grens van 30 000,00 Fr voor een lopend event.";
    }

    submitButton.disabled = resolvedAmount > availableAmount;
}

function populateSecuritiesWithdrawalModal(character) {
    const modalEl = ensureSecuritiesWithdrawalModal();
    const select = modalEl.querySelector("[data-role='securities-withdraw-snapshot']");
    const amountInput = modalEl.querySelector("[data-role='securities-withdraw-amount']");
    const submitButton = modalEl.querySelector("button[data-action='confirm-securities-withdrawal']");
    const snapshots = getApprovedSecuritiesWithdrawalSnapshots(character);
    const canManage = Boolean(character?.canManageSecurities);

    if (!select || !amountInput || !submitButton) {
        return;
    }

    select.innerHTML = "";
    snapshots.forEach((snapshot) => {
        const option = document.createElement("option");
        option.value = String(snapshot.id || 0);
        option.textContent = `${String(snapshot.eventTitle || "").trim() || `Event #${snapshot.idEvent || 0}`} (${formatEconomyDate(snapshot.transactionDate)})`;
        select.appendChild(option);
    });

    const firstSnapshot = snapshots[0] || null;
    if (firstSnapshot) {
        setCurrencyInputDisplayValue(amountInput, Number(firstSnapshot.securitiesSnapshotWithdrawalAmount || 0));
    } else {
        amountInput.value = "";
    }
    select.disabled = !canManage || snapshots.length === 0;
    amountInput.disabled = !canManage || snapshots.length === 0;
    submitButton.disabled = !canManage || snapshots.length === 0;
    updateSecuritiesWithdrawalModalState();
}

function openSecuritiesWithdrawalModal() {
    if (!currentCharacter) return;

    populateSecuritiesWithdrawalModal(currentCharacter);
    securitiesWithdrawalModalInstance?.show();
}

let bankTransferModalInstance = null;

function ensureBankTransferModal() {
    let modalEl = document.getElementById("bankTransferModal");
    if (!modalEl) {
        modalEl = document.createElement("div");
        modalEl.className = "modal fade";
        modalEl.id = "bankTransferModal";
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Overschrijving</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div data-role="bank-transfer-form">
                            <div class="mb-3">
                                <label class="form-label">Ontvanger</label>
                                <select class="form-select" data-role="bank-transfer-target"></select>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-md-6 mb-3">
                                    <label class="form-label">Datum</label>
                                    <input type="date" class="form-control" data-role="bank-transfer-date">
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <label class="form-label">Bedrag</label>
                                    <input type="text" inputmode="decimal" class="form-control" data-currency-input="true" data-role="bank-transfer-amount">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Mededeling</label>
                                <input type="text" class="form-control" maxlength="255" data-role="bank-transfer-description">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="button" class="btn btn-success" data-action="create-bank-transfer">Overschrijving</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalEl);
    }

    if (!bankTransferModalInstance) {
        bankTransferModalInstance = new bootstrap.Modal(modalEl);
    }

    return modalEl;
}

function populateBankTransferModal(character) {
    const modalEl = ensureBankTransferModal();
    const transferTargets = Array.isArray(character?.bankTransferTargets) ? character.bankTransferTargets : [];
    const canTransfer = canTransferMoneyForCharacter(character)
        && String(character?.state || "") !== "draft"
        && transferTargets.length > 0;
    const form = modalEl.querySelector("[data-role='bank-transfer-form']");
    const targetSelect = form?.querySelector("[data-role='bank-transfer-target']");
    const dateInput = form?.querySelector("[data-role='bank-transfer-date']");
    const amountInput = form?.querySelector("[data-role='bank-transfer-amount']");
    const descriptionInput = form?.querySelector("[data-role='bank-transfer-description']");
    const submitButton = modalEl.querySelector("button[data-action='create-bank-transfer']");

    if (!form || !targetSelect || !dateInput || !amountInput || !descriptionInput || !submitButton) {
        return modalEl;
    }

    targetSelect.innerHTML = "";
    transferTargets.forEach((target) => {
        const option = document.createElement("option");
        option.value = String(target.id || 0);
        option.textContent = target.displayName || `Personage #${target.id || 0}`;
        targetSelect.appendChild(option);
    });

    dateInput.value = character.defaultBankTransferDate || getDefaultBankTransferDateClient();
    amountInput.value = "";
    amountInput.max = String(Math.max(0, bankAmountFromCharacter(character)).toFixed(2));
    descriptionInput.value = "";

    [
        targetSelect,
        dateInput,
        amountInput,
        descriptionInput,
        submitButton
    ].forEach((element) => {
        element.disabled = !canTransfer;
    });

    return modalEl;
}

function openBankTransferModal() {
    if (!currentCharacter) return;

    populateBankTransferModal(currentCharacter);
    bankTransferModalInstance?.show();
}

function renderSecuritiesSnapshotsSection(host, character) {
    const snapshots = getCharacterSecuritiesSnapshots(character);

    if (snapshots.length === 0) {
        return;
    }

    appendEconomySubsectionTitle(host, "Rendementen");

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
            const creditedAmount = Number(snapshot?.securitiesSnapshotWithdrawalBankAmount || snapshotWithdrawalAmount);
            withdrawalInfo.textContent = `Opname uit portefeuille: ${formatCharacterCurrency(snapshotWithdrawalAmount)}${creditedAmount !== snapshotWithdrawalAmount ? `, uitbetaald op bankrekening: ${formatCharacterCurrency(creditedAmount)}` : ""}`;
            item.appendChild(withdrawalInfo);
        }

        const actions = document.createElement("div");
        actions.className = "d-flex align-items-center gap-2 flex-wrap mt-3";

        if (status === "pending" && character?.canApproveSecuritiesSnapshots) {
            const rerollButton = document.createElement("button");
            rerollButton.type = "button";
            rerollButton.className = "btn btn-sm btn-success";
            rerollButton.dataset.action = "reroll-securities-snapshot";
            rerollButton.dataset.idSnapshot = String(snapshot?.id || 0);
            rerollButton.textContent = "Opnieuw genereren";
            actions.appendChild(rerollButton);

            const approveButton = document.createElement("button");
            approveButton.type = "button";
            approveButton.className = "btn btn-sm btn-success";
            approveButton.dataset.action = "approve-securities-snapshot";
            approveButton.dataset.idSnapshot = String(snapshot?.id || 0);
            approveButton.textContent = "Goedkeuren";
            actions.appendChild(approveButton);
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
    renderSecuritiesIntroduction(card.body);
    renderSecuritiesProfileSummary(card.body, character);
    renderSecuritiesActionButtons(card.body, character);
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

function formatCharacterEconomySnapshotEventLabel(eventOption) {
    const title = String(eventOption?.title || "").trim() || `Event #${eventOption?.id || 0}`;
    const dateLabel = formatEconomyDate(eventOption?.dateStart);
    return dateLabel ? `${title} (${dateLabel})` : title;
}

function formatCurrencyInputDisplayValue(value) {
    const amount = Number(value ?? 0);
    const resolvedAmount = Number.isFinite(amount) ? amount : 0;
    return `${formatCharacterCurrencyValue(resolvedAmount, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function setCurrencyInputDisplayValue(input, value) {
    if (!input) return;
    input.value = formatCurrencyInputDisplayValue(value);
}

function setCurrencyInputEditingValue(input) {
    if (!input) return;

    const amount = parseBankAmount(input.value);
    if (amount === null) {
        return;
    }

    input.value = amount.toFixed(2);
}

function parseBankAmount(value) {
    let normalized = String(value ?? "")
        .trim()
        .replace(/fr/gi, "")
        .replace(/\s+/g, "");

    if (normalized === "") {
        return null;
    }

    const hasComma = normalized.includes(",");
    const hasDot = normalized.includes(".");
    if (hasComma && hasDot) {
        if (normalized.lastIndexOf(",") > normalized.lastIndexOf(".")) {
            normalized = normalized.replace(/\./g, "").replace(",", ".");
        } else {
            normalized = normalized.replace(/,/g, "");
        }
    } else if (hasComma) {
        normalized = normalized.replace(/\./g, "").replace(",", ".");
    } else {
        const dotParts = normalized.split(".");
        if (dotParts.length > 2) {
            normalized = dotParts.join("");
        }
    }

    normalized = normalized.replace(/[^\d.-]/g, "");

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
    card.className = "card aether-panel shadow-sm mb-4";

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
    button.className = "btn btn-success w-100";
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
        deleteButton.className = "btn btn-sm btn-danger";
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
    input.type = "text";
    input.inputMode = "decimal";
    input.className = "form-control";
    input.dataset.currencyInput = "true";
    input.dataset.role = "bank-balance-input";
    setCurrencyInputDisplayValue(input, balance);
    inputGroup.appendChild(input);

    fieldWrap.appendChild(inputGroup);
    host.appendChild(fieldWrap);
}

function renderSecuritiesBalanceEditor(host, character, balance) {
    const canEdit = canEditSecuritiesBalanceForCharacter(character);
    const fieldWrap = document.createElement("div");
    fieldWrap.className = "mb-3";

    const label = document.createElement("label");
    label.className = "form-label";
    label.textContent = "Saldo effectenportefeuille";
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
    input.type = "text";
    input.inputMode = "decimal";
    input.className = "form-control";
    input.dataset.currencyInput = "true";
    input.dataset.role = "securities-balance-input";
    setCurrencyInputDisplayValue(input, balance);
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
    const isDraft = String(character?.state || "") === "draft";

    if (embedded) {
        appendEconomySubsectionTitle(targetHost, "Overschrijving");
    }

    const actionWrap = document.createElement("div");
    actionWrap.className = "d-flex flex-column gap-2";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-success align-self-start";
    button.dataset.action = "open-bank-transfer-modal";
    button.disabled = isDraft || !canTransfer || transferTargets.length === 0;
    button.textContent = "Overschrijving";
    actionWrap.appendChild(button);

    if (isDraft) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Overschrijvingen zijn pas beschikbaar zodra het personage niet meer in draft staat.";
        actionWrap.appendChild(help);
    } else if (!canTransfer) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Alleen administrator- en director-gebruikers kunnen een overschrijving uitvoeren.";
        actionWrap.appendChild(help);
    } else if (transferTargets.length === 0) {
        const help = document.createElement("p");
        help.className = "text-muted mb-0";
        help.textContent = "Er zijn geen actieve doelpersonages beschikbaar.";
        actionWrap.appendChild(help);
    }

    targetHost.appendChild(actionWrap);
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
            deleteBtn.className = "btn btn-sm btn-danger";
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
    renderTransactionsSection(accountCard.body, character, { embedded: true });
    renderTransferSection(accountCard.body, character, { embedded: true });
    leftCol.appendChild(accountCard.card);
    renderCharacterSecuritiesSection(leftCol, character);
    renderCharacterEconomySnapshotsSection(rightCol, character);

    container.appendChild(leftCol);
    container.appendChild(rightCol);
    aetherShareUi.attachPortfolio(rightCol, character);
    aetherShareMarketClient.attachEconomy(
        rightCol, character,
        typeof aetherCharacterActionsState === "object"
            ? aetherCharacterActionsState.selectedEventId : 0
    );
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

async function saveSecuritiesBalance() {
    const input = document.querySelector("[data-role='securities-balance-input']");
    if (!input || !currentCharacter?.id) return;

    const amount = parseBankAmount(input.value);
    if (amount === null || amount < 0) {
        alert("Geef een geldig saldo op.");
        return;
    }

    try {
        await updateCharacter({
            id: currentCharacter.id,
            securitiesaccount: amount
        });

        await getCharacter(currentCharacter.id, null, "economy");
    } catch (err) {
        console.error("Fout bij bewaren van saldo effectenportefeuille:", err);
        alert("Kon het saldo van de effectenportefeuille niet bewaren.");
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
        await apiFetchFinancialJson("api/characters/saveBankTransfer.php", {
            method: "POST",
            body: {
                idSourceCharacter: currentCharacter.id,
                idTargetCharacter,
                transactionDate,
                amount,
                description
            }
        });

        bankTransferModalInstance?.hide();
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
        await apiFetchFinancialJson("api/characters/deleteBankTransaction.php", {
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
        await apiFetchFinancialJson("api/characters/saveCharacterEconomySnapshot.php", {
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
        await apiFetchFinancialJson("api/characters/deleteCharacterEconomySnapshot.php", {
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
        await apiFetchFinancialJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
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

    const input = document.querySelector("#securitiesDepositModal [data-role='securities-deposit-amount']");
    if (!input) return;

    const amount = parseBankAmount(input.value);
    if (amount === null || amount <= 0) {
        alert("Geef een geldig bedrag op om te storten.");
        return;
    }

    try {
        await apiFetchFinancialJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "deposit",
                idCharacter: currentCharacter.id,
                amount
            }
        });

        securitiesDepositModalInstance?.hide();
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
        await apiFetchFinancialJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
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
        await apiFetchFinancialJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
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
        await apiFetchFinancialJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
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

async function saveSecuritiesSnapshotWithdrawal() {
    if (!currentCharacter?.id) return;

    const modalEl = document.getElementById("securitiesWithdrawalModal");
    const select = modalEl?.querySelector("[data-role='securities-withdraw-snapshot']");
    const amountInput = modalEl?.querySelector("[data-role='securities-withdraw-amount']");
    if (!select || !amountInput) return;

    const idSnapshot = Number(select.value || 0);
    const amount = parseBankAmount(amountInput.value);

    if (idSnapshot <= 0) {
        alert("Kies een snapshot.");
        return;
    }

    if (amount === null || amount < 0) {
        alert("Geef een geldig bedrag op om uit de effectenportefeuille op te nemen.");
        return;
    }

    try {
        await apiFetchFinancialJson("api/characters/saveCharacterSecuritiesPortfolio.php", {
            method: "POST",
            body: {
                action: "withdraw_snapshot",
                idCharacter: currentCharacter.id,
                idSnapshot,
                amount
            }
        });

        securitiesWithdrawalModalInstance?.hide();
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
        const openTransferButton = e.target.closest("button[data-action='open-bank-transfer-modal']");
        if (openTransferButton && !openTransferButton.disabled) {
            openBankTransferModal();
            return;
        }

        const openSecuritiesDepositButton = e.target.closest("button[data-action='open-securities-deposit-modal']");
        if (openSecuritiesDepositButton && !openSecuritiesDepositButton.disabled) {
            openSecuritiesDepositModal();
            return;
        }

        const openSecuritiesWithdrawalButton = e.target.closest("button[data-action='open-securities-withdrawal-modal']");
        if (openSecuritiesWithdrawalButton && !openSecuritiesWithdrawalButton.disabled) {
            openSecuritiesWithdrawalModal();
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

        const confirmDepositSecuritiesButton = e.target.closest("button[data-action='confirm-securities-deposit']");
        if (confirmDepositSecuritiesButton && !confirmDepositSecuritiesButton.disabled) {
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

        const confirmSnapshotWithdrawalButton = e.target.closest("button[data-action='confirm-securities-withdrawal']");
        if (confirmSnapshotWithdrawalButton && !confirmSnapshotWithdrawalButton.disabled) {
            await saveSecuritiesSnapshotWithdrawal();
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

        const securitiesWithdrawalSnapshotSelect = e.target.closest("select[data-role='securities-withdraw-snapshot']");
        if (securitiesWithdrawalSnapshotSelect && !securitiesWithdrawalSnapshotSelect.disabled) {
            const snapshot = getSelectedSecuritiesWithdrawalSnapshot();
            const amountInput = document.querySelector("#securitiesWithdrawalModal [data-role='securities-withdraw-amount']");
            if (snapshot && amountInput) {
                setCurrencyInputDisplayValue(amountInput, Number(snapshot.securitiesSnapshotWithdrawalAmount || 0));
            }
            updateSecuritiesWithdrawalModalState();
            return;
        }

        const balanceInput = e.target.closest("[data-role='bank-balance-input']");
        if (balanceInput && !balanceInput.disabled) {
            await saveBankBalance();
            return;
        }

        const securitiesBalanceInput = e.target.closest("[data-role='securities-balance-input']");
        if (!securitiesBalanceInput || securitiesBalanceInput.disabled) return;

        await saveSecuritiesBalance();
    });

    document.addEventListener("input", (e) => {
        const securitiesWithdrawalAmountInput = e.target.closest("[data-role='securities-withdraw-amount']");
        if (securitiesWithdrawalAmountInput) {
            updateSecuritiesWithdrawalModalState();
        }
    });

    document.addEventListener("focusin", (e) => {
        const currencyInput = e.target.closest("input[data-currency-input='true']");
        if (!currencyInput || currencyInput.disabled) return;

        setCurrencyInputEditingValue(currencyInput);
    });

    document.addEventListener("focusout", (e) => {
        const currencyInput = e.target.closest("input[data-currency-input='true']");
        if (!currencyInput) return;

        const amount = parseBankAmount(currencyInput.value);
        if (String(currencyInput.value || "").trim() === "" || amount === null) {
            return;
        }

        setCurrencyInputDisplayValue(currencyInput, amount);
    });
}

function showEconomyTab(character) {
    const sheetRow = document.querySelector("#sheetBody .row");
    if (sheetRow) sheetRow.classList.remove("d-none");
    const sheetBody = document.getElementById("sheetBody");
    if (sheetBody) sheetBody.classList.remove("d-none");

    for (const id of ["characterForm", "skills", "backgroundTab", "diaryTab",
        "personalityTab", "actionsTab", "passportTab"]) {
        document.getElementById(id)?.classList.add("d-none");
    }

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
