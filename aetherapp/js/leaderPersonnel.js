"use strict";

let leaderPersonnelState = null;

function leaderPersonnelFeedback(message, kind = "success") {
    const el = document.getElementById("leaderPersonnelFeedback");
    el.className = `alert alert-${kind}`;
    el.textContent = message;
}

function leaderPersonnelElement(tag, className, text) {
    const el = document.createElement(tag);
    el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
}

async function leaderPersonnelLoad() {
    const id = Number(document.getElementById("leaderCompanySelect").value || 0);
    if (!id) return;
    void aetherShareUi.attachCompany(document.getElementById("leaderCompanyShares"), id);
    leaderPersonnelState = await apiFetchJson("api/companies/getCompanyPersonnelForLeader.php", {
        method: "POST", body: { id }
    });
    const list = document.getElementById("leaderPersonnelList");
    list.replaceChildren();
    for (const person of leaderPersonnelState.personnel || []) {
        const card = leaderPersonnelElement("div", "col-md-6");
        const body = leaderPersonnelElement("div", "aether-inset p-3 h-100");
        const title = leaderPersonnelElement("h2", "h5", person.name || `Personage #${person.idCharacter}`);
        body.appendChild(title);
        if (person.isCompanyLeader) {
            body.appendChild(leaderPersonnelElement("p", "text-muted",
                `Basisloon Fr ${Number(person.leaderMonthlyIncome || 0).toFixed(2)}/maand · ${person.leaderStatusPoints} statuspunten · personeelsvoorwaarde ${person.leaderPersonnelRequirement}`));
        }
        const form = leaderPersonnelElement("form", "row g-2");
        const salaryWrap = leaderPersonnelElement("label", "col-6 form-label", "Loonsverhoging (%)");
        const salary = leaderPersonnelElement("input", "form-control");
        salary.type = "number"; salary.min = "0"; salary.max = "999999.99"; salary.step = "0.01";
        salary.value = String(person.salaryIncreasePercentage || 0);
        salaryWrap.appendChild(salary);
        const boardWrap = leaderPersonnelElement("label", "col-6 form-check align-self-end mb-2");
        const board = leaderPersonnelElement("input", "form-check-input");
        board.type = "checkbox"; board.checked = Boolean(person.isBoardMember);
        board.disabled = Boolean(person.isCompanyLeader);
        boardWrap.append(board, document.createTextNode(" Raad van bestuur"));
        const pressure = leaderPersonnelElement("p", "text-muted col-12 mb-0",
            `Winstdruk: Fr ${Number(person.annualProfitPressureAmount || 0).toFixed(2)} per jaar`);
        const save = leaderPersonnelElement("button", "btn btn-outline-primary btn-sm", "Bewaren");
        save.type = "submit";
        form.append(salaryWrap, boardWrap, pressure, save);
        if (!person.isCompanyLeader) {
            const remove = leaderPersonnelElement("button", "btn btn-outline-danger btn-sm ms-2", "Verwijderen");
            remove.type = "button";
            remove.addEventListener("click", () => leaderPersonnelMutate({
                idCompany: id, idCharacter: person.idCharacter, action: "remove"
            }));
            form.appendChild(remove);
        }
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            void leaderPersonnelMutate({ idCompany: id, idCharacter: person.idCharacter,
                action: "update", salaryIncreasePercentage: Number(salary.value),
                isBoardMember: board.checked });
        });
        body.appendChild(form); card.appendChild(body); list.appendChild(card);
    }
    const selected = new Set((leaderPersonnelState.personnel || []).map((p) => Number(p.idCharacter)));
    const characterSelect = document.getElementById("leaderCharacterSelect");
    characterSelect.replaceChildren(new Option("Kies een personage", ""));
    for (const person of leaderPersonnelState.characterOptions || []) {
        if (!selected.has(Number(person.idCharacter))) {
            characterSelect.add(new Option(person.name || `Personage #${person.idCharacter}`, person.idCharacter));
        }
    }
}

async function leaderPersonnelMutate(body) {
    try {
        await apiFetchIdempotentJson("api/companies/saveCompanyPersonnelAction.php", {
            method: "POST", body
        });
        await leaderPersonnelLoad();
        leaderPersonnelFeedback("Personeel bijgewerkt.");
    } catch (error) {
        leaderPersonnelFeedback(error?.message || "Bijwerken mislukt.", "danger");
    }
}

window.addEventListener("load", async () => {
    try {
        const result = await apiFetchJson("api/companies/getMyLeaderCompanies.php");
        const select = document.getElementById("leaderCompanySelect");
        select.replaceChildren();
        for (const company of result.companies || []) {
            select.add(new Option(company.companyName, company.id));
        }
        if (!select.options.length) {
            leaderPersonnelFeedback("Er is geen bedrijf waarvoor je bedrijfsleider bent.", "warning");
            document.getElementById("leaderPersonnelAddForm").classList.add("d-none");
            return;
        }
        const requested = new URLSearchParams(location.search).get("company");
        if (requested && [...select.options].some((option) => option.value === requested)) select.value = requested;
        select.addEventListener("change", () => void leaderPersonnelLoad());
        document.getElementById("leaderPersonnelAddForm").addEventListener("submit", (event) => {
            event.preventDefault();
            const idCharacter = Number(document.getElementById("leaderCharacterSelect").value || 0);
            if (!idCharacter) return;
            void leaderPersonnelMutate({
                idCompany: Number(select.value), idCharacter, action: "add",
                salaryIncreasePercentage: Number(document.getElementById("leaderNewSalary").value || 0),
                isBoardMember: document.getElementById("leaderNewBoard").checked
            });
        });
        await leaderPersonnelLoad();
    } catch (error) {
        leaderPersonnelFeedback(error?.message || "Kon personeelsbeheer niet openen.", "danger");
    }
});
