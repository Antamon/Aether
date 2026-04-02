// Traits-related logic (render grouped traits, tooltips and link/rank updates)

const upperClassLeftTraitGroups = new Set(["Adeldom", "Adellijke titel"]);
const professionSectorSelectionByCharacter = {};

async function submitTraitAction({ action, idCharacter, idTrait, idCurrentTrait = 0, openCollapseId = null }) {
    const result = await apiFetchJson("api/characters/updateTrait.php", {
        method: "POST",
        body: {
            action,
            idCharacter,
            idTrait,
            idCurrentTrait
        }
    });

    if (result?.error) {
        alert(result.error);
        getCharacter(idCharacter, openCollapseId);
        return false;
    }

    if (action === "remove") {
        delete professionSectorSelectionByCharacter[idCharacter];
    }

    getCharacter(idCharacter, openCollapseId);
    return true;
}

function getOpenSkillCollapseId() {
    const openCollapse = document.querySelector("#accordionSkills .accordion-collapse.show");
    return openCollapse ? openCollapse.id : null;
}

function isGroupedTraitGroup(group) {
    return Boolean(group?.grouped);
}

function canEditTraitsForCharacter(character) {
    if (!currentUser || !character) return false;

    const role = currentUser.role;
    if (role === "administrator" || role === "director") {
        return true;
    }

    return role === "participant"
        && character.type === "player"
        && character.state === "draft"
        && Number(character.idUser) === Number(currentUser.id);
}

function formatTraitCurrency(amount) {
    return `${Number(amount || 0).toLocaleString("nl-BE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function isCompanyShareTraitClient(trait) {
    return Boolean(trait?.isCompanyShare);
}

function formatTraitRankLabel(trait) {
    const rank = Number(trait?.rank ?? 0);
    if (isCompanyShareTraitClient(trait)) {
        return `${rank}%`;
    }

    return `Rang ${rank}`;
}

function canEditTraitRankInTraitList(link) {
    if (!isCompanyShareTraitClient(link)) {
        return true;
    }

    return currentCharacter?.state === "draft";
}

function calculateTraitIncomeAtRank(trait) {
    if (trait?.income === null || trait?.income === undefined || trait?.income === "") {
        return null;
    }

    const baseIncome = Number(trait.income);
    if (!Number.isFinite(baseIncome)) {
        return null;
    }

    const rankType = String(trait?.rankType || "singular");
    const rank = Number(trait?.rank ?? 1);
    const evolution = Number(trait?.evolution);
    const hasEvolution = Number.isFinite(evolution) && evolution !== 0;

    if (rankType === "singular") {
        return baseIncome;
    }

    if (!hasEvolution) {
        return baseIncome * Math.max(1, Math.abs(rank || 1));
    }

    if (rank >= 1) {
        return baseIncome * Math.pow(1 + evolution, Math.max(0, rank - 1));
    }

    return baseIncome * Math.pow(1 - evolution, Math.abs(rank));
}

function createTraitMetaLine(trait) {
    const parts = [];
    const groupName = String(trait?.traitGroup || "").trim();
    const income = calculateTraitIncomeAtRank(trait);
    const staffRequirements = Number(trait?.staffRequirements ?? 0);

    if (groupName) {
        parts.push(groupName);
    }

    if (income !== null) {
        parts.push(formatTraitCurrency(income));
    }

    if (staffRequirements > 0) {
        parts.push(`Personeel: ${staffRequirements}`);
    }

    return parts.join(" | ");
}

function isCompactReadOnlyTraitGroup(group) {
    if (!group) return false;

    if (group.name === "Adeldom" || group.name === "Adellijke titel") {
        return true;
    }

    const items = [
        ...(Array.isArray(group.options) ? group.options : []),
        ...(Array.isArray(group.linkedTraits) ? group.linkedTraits : [])
    ];

    return items.some((item) => item?.type === "profession");
}

function createReadOnlyTraitBlock(link, options = {}) {
    const { compact = false } = options;

    if (compact) {
        const label = link.rankType !== "singular"
            ? `${link.name || ""} (${formatTraitRankLabel(link)})`
            : (link.name || "");
        const wrapper = document.createElement("div");
        wrapper.textContent = label;
        return wrapper;
    }

    const wrapper = document.createElement("div");
    wrapper.className = "trait-readonly mb-3";

    const title = document.createElement("h5");
    title.className = "mb-1";
    title.textContent = link.name || "";

    if (link.rankType !== "singular") {
        const rank = document.createElement("span");
        rank.className = "badge text-bg-secondary ms-2";
        rank.textContent = formatTraitRankLabel(link);
        title.appendChild(rank);
    }

    wrapper.appendChild(title);

    const metaText = createTraitMetaLine(link);
    if (metaText) {
        const meta = document.createElement("p");
        meta.className = "mb-1 text-muted trait-readonly-meta";
        meta.textContent = metaText;
        wrapper.appendChild(meta);
    }

    const description = String(link.description || "").trim();
    if (description) {
        const body = document.createElement("p");
        body.className = "mb-0";
        body.textContent = description;
        wrapper.appendChild(body);
    }

    return wrapper;
}

function setupTraitListeners() {
    if (window.aetherTraitListenersInitialized) return;
    window.aetherTraitListenersInitialized = true;

    document.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-action]");
        if (!btn || btn.disabled || btn.classList.contains("disabled")) return;
        if (!btn.closest(".trait-row")) return;

        const action = btn.dataset.action;
        const row = btn.closest(".trait-row");
        const idCharacter = Number(document.getElementById("idCharacter")?.value || 0);
        if (!row || !idCharacter) return;

        let idTrait = Number(row.dataset.idTrait || 0);
        if (action === "trait-add") {
            const select = row.querySelector("select");
            idTrait = Number(select?.value || 0);
        }

        if (!idTrait) return;

        let apiAction = "";
        if (action === "trait-add") apiAction = "add";
        if (action === "trait-remove") apiAction = "remove";
        if (action === "trait-rank-up") apiAction = "rank_up";
        if (action === "trait-rank-down") apiAction = "rank_down";
        if (!apiAction) return;

        const openCollapseId = getOpenSkillCollapseId();

        try {
            await submitTraitAction({
                action: apiAction,
                idCharacter,
                idTrait,
                openCollapseId
            });
        } catch (err) {
            console.error("Fout bij bijwerken trait:", err);
        }
    });

    document.addEventListener("change", async (e) => {
        const groupSelect = e.target.closest("select[data-role='profession-group-select']");
        if (groupSelect) {
            const idCharacter = Number(document.getElementById("idCharacter")?.value || 0);
            if (!idCharacter || !currentCharacter) return;

            if (groupSelect.value) {
                professionSectorSelectionByCharacter[idCharacter] = groupSelect.value;
            } else {
                delete professionSectorSelectionByCharacter[idCharacter];
            }

            renderLeftTraitModule(currentCharacter, canEditTraitsForCharacter(currentCharacter));
            return;
        }

        const select = e.target.closest("select[data-role='trait-select']");
        if (!select) return;
        const row = select.closest(".trait-row");
        if (!row) return;

        if (row.dataset.grouped === "true" && row.dataset.linked === "true") {
            const idCharacter = Number(document.getElementById("idCharacter")?.value || 0);
            const idCurrentTrait = Number(row.dataset.idTrait || 0);
            const idTrait = Number(select.value || 0);

            if (!idCharacter || !idCurrentTrait || !idTrait || idCurrentTrait === idTrait) {
                return;
            }

            try {
                await submitTraitAction({
                    action: "change",
                    idCharacter,
                    idTrait,
                    idCurrentTrait,
                    openCollapseId: getOpenSkillCollapseId()
                });
            } catch (err) {
                console.error("Fout bij wisselen van grouped trait:", err);
            }
            return;
        }

        updatePendingTraitRow(row);
    });
}

function getUpperClassLeftTraitGroups(character, traitGroups) {
    const groups = Array.isArray(traitGroups) ? traitGroups : [];
    if (character?.class !== "upper class") {
        return [];
    }

    return groups.filter((group) => upperClassLeftTraitGroups.has(group.name));
}

function getMainTraitGroups(character, traitGroups) {
    const groups = Array.isArray(traitGroups) ? traitGroups : [];
    if (character?.class !== "upper class") {
        return groups;
    }

    return groups.filter((group) => !upperClassLeftTraitGroups.has(group.name));
}

function getProfessionGroups(character) {
    return Array.isArray(character?.professionGroups) ? character.professionGroups : [];
}

function getLinkedProfessionGroup(character) {
    return getProfessionGroups(character).find((group) => (group.linkedTraits || []).length > 0) || null;
}

function sortProfessionGroups(groups) {
    return [...(Array.isArray(groups) ? groups : [])].sort((a, b) =>
        String(a?.name || "").localeCompare(String(b?.name || ""))
    );
}

function getSelectedProfessionGroup(character) {
    const idCharacter = Number(character?.id || 0);
    const selectedName = professionSectorSelectionByCharacter[idCharacter] || "";
    const groups = getProfessionGroups(character);
    return groups.find((group) => group.name === selectedName) || null;
}

function createProfessionSectorSelector(character, canEdit) {
    const groups = sortProfessionGroups(getProfessionGroups(character));
    if (groups.length === 0) {
        return `<p class="text-muted">Geen beroepssectoren beschikbaar.</p>`;
    }

    const idCharacter = Number(character?.id || 0);
    const selectedName = professionSectorSelectionByCharacter[idCharacter] || "";
    const disabledAttr = canEdit ? "" : " disabled";

    const options = [
        `<option value="">Kies sector</option>`,
        ...groups.map((group) => {
            const selectedAttr = group.name === selectedName ? " selected" : "";
            return `<option value="${group.name}"${selectedAttr}>${group.name}</option>`;
        })
    ].join("");

    return `
        <select class="form-select mb-2"${disabledAttr} data-role="profession-group-select">
            ${options}
        </select>
    `;
}

function renderProfessionOptions(container, group, canEdit) {
    if (!container || !group) return;

    const linkedTraits = Array.isArray(group.linkedTraits) ? group.linkedTraits : [];
    if (!canEdit) {
        const compact = isCompactReadOnlyTraitGroup(group);
        linkedTraits.forEach((link) => {
            container.appendChild(createReadOnlyTraitBlock(link, { compact }));
        });
        return;
    }

    linkedTraits.forEach((link) => {
        container.appendChild(createLinkedTraitRow(link, group, canEdit));
    });

    const pendingRow = createPendingTraitRow(group, canEdit);
    if (pendingRow) {
        container.appendChild(pendingRow);
    }
}

function renderTraitGroupItems(container, group, canEdit) {
    if (!container || !group) return false;

    const linkedTraits = Array.isArray(group.linkedTraits) ? group.linkedTraits : [];
    if (!canEdit) {
        const compact = isCompactReadOnlyTraitGroup(group);
        linkedTraits.forEach((link) => {
            container.appendChild(createReadOnlyTraitBlock(link, { compact }));
        });
        return container.childElementCount > 0;
    }

    linkedTraits.forEach((link) => {
        container.appendChild(createLinkedTraitRow(link, group, canEdit));
    });

    const pendingRow = createPendingTraitRow(group, canEdit);
    if (pendingRow) {
        container.appendChild(pendingRow);
    }

    return container.childElementCount > 0;
}

function renderProfessionTraitModule(container, character, canEdit) {
    if (!container) return;
    container.innerHTML = "";

    const linkedGroup = getLinkedProfessionGroup(character);
    const selectedGroup = getSelectedProfessionGroup(character);

    if (linkedGroup) {
        renderProfessionOptions(container, linkedGroup, canEdit);
    } else if (selectedGroup) {
        renderProfessionOptions(container, selectedGroup, canEdit);
    } else if (!canEdit) {
        container.insertAdjacentHTML("beforeend", `<p class="text-muted mb-0">Geen beroep gekozen.</p>`);
    } else {
        container.insertAdjacentHTML("beforeend", createProfessionSectorSelector(character, canEdit));
    }

    if (typeof initTooltips === "function") {
        initTooltips();
    }
}

function createLeftTraitModuleRow(labelText) {
    const row = document.createElement("div");
    row.className = "mb-3 row";

    const label = document.createElement("label");
    label.className = "col-sm-4 col-form-label";
    label.textContent = labelText;
    row.appendChild(label);

    const contentCol = document.createElement("div");
    contentCol.className = "col-sm-8";
    row.appendChild(contentCol);

    return { row, contentCol };
}

function getUpperClassLeftTraitGroupLabel(groupName) {
    if (groupName === "Adellijke titel") {
        return "Titel";
    }

    return groupName;
}

function renderUpperClassLeftTraitModule(host, character, canEdit) {
    if (!host) return;

    const leftTraitGroups = getUpperClassLeftTraitGroups(character, character.traitGroups || []);
    host.innerHTML = "";

    leftTraitGroups.forEach((group) => {
        const { row, contentCol } = createLeftTraitModuleRow(getUpperClassLeftTraitGroupLabel(group.name));
        contentCol.classList.add("upper-class-traits");

        if (!renderTraitGroupItems(contentCol, group, canEdit)) {
            return;
        }

        host.appendChild(row);
    });

    host.classList.toggle("d-none", host.childElementCount === 0);
}

function renderProfessionLeftTraitModule(host, character, canEdit) {
    if (!host) return;

    host.innerHTML = "";
    const { row, contentCol } = createLeftTraitModuleRow("Beroep");
    renderProfessionTraitModule(contentCol, character, canEdit);
    row.classList.toggle("d-none", contentCol.childElementCount === 0);
    host.appendChild(row);
    host.classList.toggle("d-none", row.classList.contains("d-none"));
}

function positionLeftTraitModuleHost(character) {
    const host = document.getElementById("leftTraitModuleHost");
    if (!host || !character) return;

    const targetRow = character.class === "upper class"
        ? document.getElementById("classRow")
        : document.getElementById("birthRow");

    if (!targetRow?.parentElement) return;
    targetRow.insertAdjacentElement("afterend", host);
}

function renderLeftTraitModule(character, canEdit) {
    const host = document.getElementById("leftTraitModuleHost");

    if (!host || !character) return;

    positionLeftTraitModuleHost(character);

    if (character.class === "upper class") {
        renderUpperClassLeftTraitModule(host, character, canEdit);
        return;
    }

    if (character.class === "middle class" || character.class === "lower class") {
        renderProfessionLeftTraitModule(host, character, canEdit);
        return;
    }

    host.classList.add("d-none");
    host.innerHTML = "";
}

function updatePendingTraitRow(row) {
    if (!row) return;

    const select = row.querySelector("select[data-role='trait-select']");
    const selectedOption = select?.selectedOptions?.[0];
    const bookBtn = row.querySelector(".trait-book");
    const actionBtn = row.querySelector("button[data-action='trait-add']");

    if (!selectedOption || !bookBtn || !actionBtn) return;

    const description = selectedOption.dataset.description || "";
    const popoverText = description || "Geen beschrijving.";
    bookBtn.setAttribute("data-bs-content", popoverText);
    bookBtn.setAttribute("data-bs-title", "Trait");
    row.dataset.idTrait = selectedOption.value || "";

    if (window.bootstrap?.Popover) {
        const instance = bootstrap.Popover.getInstance(bookBtn);
        if (instance) {
            instance.dispose();
            new bootstrap.Popover(bookBtn);
        }
    }

    actionBtn.disabled = !selectedOption.value;
}

function formatTraitOptionLabel(option) {
    const name = String(option?.name || "").trim();
    const cost = Number(option?.cost ?? 0);

    if (!name) {
        return "";
    }

    return `${name} (${Number.isFinite(cost) ? cost : 0})`;
}

function createTraitOption(option, isSelected = false) {
    const el = document.createElement("option");
    el.value = option.id;
    el.textContent = formatTraitOptionLabel(option);
    el.selected = isSelected;
    el.dataset.description = option.description || "";
    el.dataset.rankType = option.rankType || "singular";
    return el;
}

function createTraitBookButton(description) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-outline-secondary trait-book";
    btn.setAttribute("data-bs-toggle", "popover");
    btn.setAttribute("data-bs-trigger", "focus");
    btn.setAttribute("data-bs-placement", "top");
    btn.setAttribute("data-bs-title", "Trait");
    btn.setAttribute("data-bs-content", description || "Geen beschrijving.");
    btn.setAttribute("aria-label", "Toon traitbeschrijving");
    btn.innerHTML = `<i class="fa-solid fa-book"></i>`;
    return btn;
}

function createTraitRankBadge(trait) {
    const badge = document.createElement("span");
    badge.className = "input-group-text trait-rank";
    badge.textContent = isCompanyShareTraitClient(trait)
        ? `${Number(trait?.rank ?? 0)}%`
        : String(trait?.rank ?? 0);
    return badge;
}

function createTraitSelect(options, selectedId = null, disabled = false) {
    const select = document.createElement("select");
    select.className = "form-select";
    select.dataset.role = "trait-select";
    select.disabled = disabled;

    options.forEach((option) => {
        select.appendChild(createTraitOption(option, Number(option.id) === Number(selectedId)));
    });

    return select;
}

function sortTraitOptions(options) {
    return [...(Array.isArray(options) ? options : [])].sort((a, b) => {
        const aUnique = Boolean(a?.isUnique);
        const bUnique = Boolean(b?.isUnique);

        if (aUnique && bUnique) {
            const aCost = Number(a?.cost ?? 0);
            const bCost = Number(b?.cost ?? 0);
            if (aCost !== bCost) {
                return aCost - bCost;
            }
            return String(a?.name || "").localeCompare(String(b?.name || ""));
        }

        if (!aUnique && !bUnique) {
            return String(a?.name || "").localeCompare(String(b?.name || ""));
        }

        return aUnique ? -1 : 1;
    });
}

function createLinkedTraitRow(link, group, canEdit) {
    const row = document.createElement("div");
    row.className = "input-group mb-2 trait-row";
    row.dataset.idTrait = link.idTrait;
    row.dataset.linked = "true";

    const groupedTraitGroup = isGroupedTraitGroup(group);
    row.dataset.grouped = groupedTraitGroup ? "true" : "false";

    row.appendChild(createTraitBookButton(link.description));

    if (link.rankType !== "singular") {
        row.appendChild(createTraitRankBadge(link));
    }

    const selectOptions = groupedTraitGroup
        ? sortTraitOptions(
            (group.options || []).filter((option) =>
                Number(option.id) === Number(link.idTrait) ||
                !(group.linkedTraits || []).some((linkedTrait) => Number(linkedTrait.idTrait) === Number(option.id))
            )
        )
        : [link];
    const select = createTraitSelect(selectOptions, link.idTrait, !canEdit || !groupedTraitGroup);
    row.appendChild(select);

    if (canEdit && link.rankType !== "singular" && canEditTraitRankInTraitList(link)) {
        const btnDown = document.createElement("button");
        btnDown.type = "button";
        btnDown.className = "btn btn-outline-secondary";
        btnDown.dataset.action = "trait-rank-down";
        btnDown.innerHTML = `<i class="fa-solid fa-minus"></i>`;
        if (link.rankType === "range_positive" && Number(link.rank) <= (isCompanyShareTraitClient(link) ? 10 : 1)) {
            btnDown.disabled = true;
        }
        row.appendChild(btnDown);

        const btnUp = document.createElement("button");
        btnUp.type = "button";
        btnUp.className = "btn btn-outline-secondary";
        btnUp.dataset.action = "trait-rank-up";
        btnUp.innerHTML = `<i class="fa-solid fa-plus"></i>`;
        row.appendChild(btnUp);
    }

    if (canEdit) {
        const btnRemove = document.createElement("button");
        btnRemove.type = "button";
        btnRemove.className = "btn btn-danger";
        btnRemove.dataset.action = "trait-remove";
        btnRemove.innerHTML = `<i class="fa-solid fa-trash"></i>`;
        row.appendChild(btnRemove);
    }

    return row;
}

function createPendingTraitRow(group, canEdit) {
    if (!canEdit) {
        return null;
    }

    if (isGroupedTraitGroup(group) && (group.linkedTraits || []).length > 0) {
        return null;
    }

    const linkedIds = new Set((group.linkedTraits || []).map((trait) => Number(trait.idTrait)));
    const availableOptions = sortTraitOptions(
        (group.options || []).filter((option) => !linkedIds.has(Number(option.id)))
    );

    if (availableOptions.length === 0) {
        return null;
    }

    const row = document.createElement("div");
    row.className = "input-group mb-2 trait-row";
    row.dataset.idTrait = availableOptions[0].id;
    row.dataset.linked = "false";
    row.dataset.grouped = isGroupedTraitGroup(group) ? "true" : "false";

    row.appendChild(createTraitBookButton(availableOptions[0].description));

    const select = createTraitSelect(availableOptions, availableOptions[0].id, !canEdit);
    row.appendChild(select);

    const btnAdd = document.createElement("button");
    btnAdd.type = "button";
    btnAdd.className = "btn btn-success";
    btnAdd.dataset.action = "trait-add";
    btnAdd.innerHTML = `<i class="fa-solid fa-check"></i>`;
    row.appendChild(btnAdd);

    updatePendingTraitRow(row);
    return row;
}

function renderTraitGroups(container, traitGroups, canEdit, emptyMessage = "Geen traits beschikbaar.") {
    if (!container) return;
    container.innerHTML = "";

    const groups = Array.isArray(traitGroups) ? traitGroups : [];
    if (groups.length === 0) {
        if (emptyMessage) {
            container.innerHTML = `<p class="text-muted">${emptyMessage}</p>`;
        }
        return;
    }

    if (!canEdit) {
        groups.forEach((group) => {
            const linkedTraits = Array.isArray(group.linkedTraits) ? group.linkedTraits : [];
            const compact = isCompactReadOnlyTraitGroup(group);
            linkedTraits.forEach((link) => {
                container.appendChild(createReadOnlyTraitBlock(link, { compact }));
            });
        });

        if (container.childElementCount === 0 && emptyMessage) {
            container.innerHTML = `<p class="text-muted">${emptyMessage}</p>`;
        }
        return;
    }

    groups.forEach((group) => {
        const pendingRow = createPendingTraitRow(group, canEdit);
        const linkedTraits = Array.isArray(group.linkedTraits) ? group.linkedTraits : [];

        if (linkedTraits.length === 0 && !pendingRow) {
            return;
        }

        const wrapper = document.createElement("div");
        wrapper.className = "trait-group";

        const title = document.createElement("h5");
        title.textContent = group.name;
        wrapper.appendChild(title);

        linkedTraits.forEach((link) => {
            wrapper.appendChild(createLinkedTraitRow(link, group, canEdit));
        });

        if (pendingRow) {
            wrapper.appendChild(pendingRow);
        }

        container.appendChild(wrapper);
    });

    if (typeof initTooltips === "function") {
        initTooltips();
    }
}
