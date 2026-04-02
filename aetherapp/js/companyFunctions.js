const COMPANY_STABILITY_RANGES = [
    {
        min: -7,
        max: -5,
        message: "Extreem volatiel. Grote winsten mogelijk, maar even goed zware verliezen. Sterk afhankelijk van hype, innovatie of gebeurtenissen op de markten."
    },
    {
        min: -4,
        max: -2,
        message: "Volatiel. Inkomsten kunnen sterk fluctueren. Gevoelig voor trends, marktschommelingen of externe invloeden. Risico begint een rol te spelen."
    },
    {
        min: -1,
        max: 1,
        message: "Normale variatie. Het bedrijf kent duidelijke ups en downs, maar dit is ingecalculeerd en beheersbaar."
    },
    {
        min: 2,
        max: 4,
        message: "Stabiel. Kleine schommelingen. Af en toe een kleine dip of piek, maar verrassingen zijn zeldzaam."
    },
    {
        min: 5,
        max: 7,
        message: "Extreem stabiel. Inkomsten zijn voorspelbaar en nauwelijks gevoelig voor trends of externe factoren. Denk aan basisvoorzieningen of langdurige contracten."
    }
];

const COMPANY_PROFITABILITY_RANGES = [
    {
        min: -7,
        max: -5,
        message: "Verlieslatend. Kosten liggen duidelijk hoger dan de inkomsten. Mogelijk werkt het businessmodel niet meer, ontsporen de kosten of drukt concurrentie het bedrijf weg. Het kan ook zijn dat het bedrijf in een intensieve investeringsfase zit waarbij winst nog niet de focus is."
    },
    {
        min: -4,
        max: -2,
        message: "Riskante toestand. Inkomsten zijn niet gegarandeerd. Potentiele marges zijn klein of worden opgeslorpt door kosten. Mogelijk kampt het bedrijf met sterke concurrentie, inefficientie of dalende vraag, of het investeert bewust in groei waardoor winst beperkt blijft."
    },
    {
        min: -1,
        max: 1,
        message: "Normale winst. Inkomsten en kosten zijn meestal in balans. Het bedrijf is stabiel, maar bouwt weinig reserves op."
    },
    {
        min: 2,
        max: 4,
        message: "Goed boerend bedrijf. Duidelijk winstgevend met goede marges. Efficiente werking, stabiele vraag en ruimte voor investeringen of uitbreiding."
    },
    {
        min: 5,
        max: 7,
        message: "Uitzonderlijk winstgevend. Zeer hoge rendabiliteit. Sterke marktpositie, unieke diensten of quasi-monopolie zorgen voor uitzonderlijke winsten."
    }
];

const COMPANY_TYPE_DEFINITIONS = [
    {
        key: "micro",
        label: "Micro-onderneming",
        max: 30000,
        description: "Dit type onderneming is klein, persoonlijk en volledig verweven met het leven van de eigenaar. De zaak is vaak gevestigd in of naast de woning, en draait op vakmanschap, reputatie en vaste klanten. Groei is beperkt, maar stabiliteit kan hoog zijn zolang de eigenaar gezond blijft. Innovatie gebeurt traag en meestal uit noodzaak, niet uit strategie. Dit zijn de stille radertjes van de stad: onmisbaar, maar zelden zichtbaar in het grote economische spel. Voorbeelden: bakkerij, kleermakerij, schoenmaker, kleine herberg, horlogemaker."
    },
    {
        key: "family",
        label: "Familiebedrijf",
        max: 450000,
        description: "Een familiebedrijf vormt een eerste echte economische entiteit los van één individu. Meerdere familieleden of werknemers dragen bij aan de werking, en er is vaak sprake van specialisatie en eenvoudige organisatie. Investeringen in materiaal of infrastructuur zijn merkbaar, en het bedrijf kan lokale concurrentie aangaan of zelfs domineren. De continuïteit ligt in de familie: generaties bouwen verder op wat eerder is opgebouwd. Voorbeelden: drukkerij, brouwerij, transportbedrijf met paarden en vroege stoomwagens, kleine bouwonderneming, industriële wasserij."
    },
    {
        key: "national",
        label: "Nationale onderneming",
        max: 6750000,
        description: "Dit type bedrijf overstijgt de lokale markt en opereert op nationaal niveau, vaak met meerdere vestigingen of distributiepunten. Er ontstaat een duidelijke hiërarchie met management, administratie en gespecialiseerde arbeiders. De eigenaar is niet langer dagelijks betrokken bij de uitvoering, maar stuurt op afstand via leidinggevenden. Deze bedrijven zijn zichtbaar in het straatbeeld en beginnen een merkidentiteit op te bouwen. Ze beïnvloeden prijzen, werkgelegenheid en soms zelfs lokale politiek. Voorbeelden: warenhuisketen, nationale brouwerijgroep, spoorwegmaatschappij binnen één land, grote textielfabriek, gas- of elektriciteitsmaatschappij in opkomst."
    },
    {
        key: "small_international",
        label: "Kleine internationale groep",
        max: 101250000,
        description: "Deze bedrijven opereren over landsgrenzen heen en combineren productie, handel en financiën. Ze hebben dochterondernemingen in meerdere regio’s en werken vaak met complexe eigendomsstructuren. Beslissingen worden strategisch genomen en hebben impact op hele sectoren. Innovatie speelt een grotere rol, zeker in deze moderne tijd waar technologie een concurrentievoordeel biedt. Ze onderhouden banden met banken, adel en overheden. Voorbeelden: internationale staalproducent, chemisch bedrijf, spoorwegnetwerk tussen meerdere landen, fabrikant van industriële machines, telegraaf- of communicatienetwerk."
    },
    {
        key: "large_international",
        label: "Grote internationale groep",
        max: Infinity,
        description: "Dit zijn economische grootmachten die functioneren als staten binnen staten. Ze controleren volledige productieketens, van grondstof tot eindproduct, en hebben invloed op internationale handel en politiek. De leiding bestaat uit elites: industriëlen, bankiers en adel. Hun beslissingen kunnen oorlogen beïnvloeden, steden doen groeien of instorten, en technologische revoluties versnellen. In een steampunkwereld zijn dit de bedrijven die experimenteren met grensverleggende en soms gevaarlijke technologieën. Voorbeelden: continentaal spoorwegimperium, megaconglomeraat in staal en wapens, energiebedrijf met stoom- en elektrische netwerken, internationale bankholding, fabrikant van geavanceerde lucht- of oorlogsmachines."
    }
];

let currentCompanyId = 0;
let companies = [];
let companyPageListenersInitialized = false;
let isCreatingCompany = false;
let createCompanyRequestInFlight = false;
let companyAutoSavePending = false;
let isPopulatingCompanyForm = false;
let saveCompanyRequestInFlight = false;
let saveCompanyQueued = false;
let saveCompanyInFlightPromise = null;

window.addEventListener("load", () => {
    initCompanyPage();
});

async function initCompanyPage() {
    try {
        const currentUser = await apiFetchJson("getCurrentUser.php");
        if (!currentUser || !userHasPrivilegedRole(currentUser)) {
            window.location.href = "index.html";
            return;
        }

        window.AETHER_CURRENT_USER = currentUser;
        syncPrivilegedNavbar(currentUser);
        setupCompanyPageListeners();
        await loadCompanyList();
    } catch (err) {
        console.error("Fout bij initialiseren bedrijvenpagina:", err);
        window.location.href = "index.html";
    }
}

function setupCompanyPageListeners() {
    if (companyPageListenersInitialized) return;
    companyPageListenersInitialized = true;

    const form = document.getElementById("companyForm");
    if (form) {
        form.addEventListener("submit", (event) => {
            event.preventDefault();
        });
    }

    const stabilityInput = document.getElementById("companyStability");
    if (stabilityInput) {
        stabilityInput.addEventListener("input", () => {
            updateSliderPresentation("stability");
            markCompanyAutoSavePending();
        });

        stabilityInput.addEventListener("change", () => {
            markCompanyAutoSavePending();
            void flushCompanyAutoSave();
        });
    }

    const profitabilityInput = document.getElementById("companyProfitability");
    if (profitabilityInput) {
        profitabilityInput.addEventListener("input", () => {
            updateSliderPresentation("profitability");
            markCompanyAutoSavePending();
        });

        profitabilityInput.addEventListener("change", () => {
            markCompanyAutoSavePending();
            void flushCompanyAutoSave();
        });
    }

    const newCompanyButton = document.getElementById("newCompanyButton");
    if (newCompanyButton) {
        newCompanyButton.addEventListener("click", async () => {
            if (!isCreatingCompany) {
                const canContinue = await flushCompanyAutoSave({ force: false });
                if (!canContinue) {
                    return;
                }
            }
            startCreatingCompany();
        });
    }

    const companyNameInput = document.getElementById("companyName");
    if (companyNameInput) {
        companyNameInput.addEventListener("input", () => {
            markCompanyAutoSavePending();
        });

        companyNameInput.addEventListener("blur", () => {
            void ensureCompanyCreated(true);
            void flushCompanyAutoSave();
        });

        companyNameInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter" && isCreatingCompany && Number(document.getElementById("companyId")?.value || 0) <= 0) {
                event.preventDefault();
                void ensureCompanyCreated(true);
            }
        });
    }

    ["companyDescription", "companyFoundationDate", "companyValue"].forEach((fieldId) => {
        const input = document.getElementById(fieldId);
        if (!input) return;

        input.addEventListener("input", () => {
            if (fieldId === "companyValue") {
                updateCompanyTypePresentation();
            }
            markCompanyAutoSavePending();
        });

        input.addEventListener("change", () => {
            if (fieldId === "companyValue") {
                updateCompanyTypePresentation();
            }
            markCompanyAutoSavePending();
            void flushCompanyAutoSave();
        });

        input.addEventListener("blur", () => {
            void flushCompanyAutoSave();
        });
    });

    const companyLogoUploadButton = document.getElementById("companyLogoUploadButton");
    const companyLogoDeleteButton = document.getElementById("companyLogoDeleteButton");
    const companyLogoFileInput = document.getElementById("companyLogoFileInput");

    if (companyLogoUploadButton && companyLogoFileInput) {
        companyLogoUploadButton.addEventListener("click", () => {
            const id = Number(document.getElementById("companyId")?.value || 0);
            if (id <= 0) {
                showCompanyFeedback("Maak eerst het bedrijf aan voor je een logo oplaadt.", "danger");
                return;
            }

            companyLogoFileInput.click();
        });
    }

    if (companyLogoFileInput) {
        companyLogoFileInput.addEventListener("change", async () => {
            const file = companyLogoFileInput.files?.[0];
            if (!file) {
                return;
            }

            await uploadCompanyLogo(file);
            companyLogoFileInput.value = "";
        });
    }

    if (companyLogoDeleteButton) {
        companyLogoDeleteButton.addEventListener("click", async () => {
            await deleteCompanyLogo();
        });
    }
}

async function loadCompanyList(selectedCompanyId = 0) {
    try {
        const list = await apiFetchJson("api/companies/getCompanyList.php");
        companies = Array.isArray(list) ? list : [];

        renderCompanyList();

        if (companies.length === 0) {
            currentCompanyId = 0;
            setCompanyFormState(false);
            populateCompanyForm(null);
            showCompanyFeedback("Nog geen bedrijven beschikbaar.", "info");
            return;
        }

        const nextCompanyId = Number(selectedCompanyId) > 0
            ? Number(selectedCompanyId)
            : Number(companies[0].id || 0);

        hideCompanyFeedback();
        await loadCompany(nextCompanyId);
    } catch (err) {
        console.error("Fout bij laden bedrijvenlijst:", err);
        setCompanyFormState(false);
        populateCompanyForm(null);
        showCompanyFeedback("Kon de bedrijvenlijst niet laden.", "danger");
    }
}

function renderCompanyList() {
    const listEl = document.getElementById("companyList");
    if (!listEl) return;

    listEl.innerHTML = "";

    if (companies.length === 0) {
        const empty = document.createElement("div");
        empty.className = "text-muted small";
        empty.textContent = "Nog geen bedrijven beschikbaar.";
        listEl.appendChild(empty);
        return;
    }

    companies.forEach((company) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "list-group-item list-group-item-action company-list-item";
        button.textContent = company.companyName || `Bedrijf #${company.id}`;
        button.dataset.idCompany = String(company.id || 0);

        if (Number(company.id) === Number(currentCompanyId)) {
            button.classList.add("active");
            button.setAttribute("aria-current", "true");
        }

        button.addEventListener("click", async () => {
            if (!isCreatingCompany) {
                const canContinue = await flushCompanyAutoSave({ force: false });
                if (!canContinue) {
                    return;
                }
            }
            isCreatingCompany = false;
            await loadCompany(Number(company.id || 0));
        });

        listEl.appendChild(button);
    });
}

async function loadCompany(idCompany) {
    if (Number(idCompany) <= 0) {
        return;
    }

    try {
        const company = await apiFetchJson("api/companies/getCompany.php", {
            method: "POST",
            body: { id: Number(idCompany) }
        });

        currentCompanyId = Number(company?.id || 0);
        setCompanyFormState(currentCompanyId > 0);
        populateCompanyForm(company);
        renderCompanyList();
    } catch (err) {
        console.error("Fout bij laden bedrijf:", err);
        showCompanyFeedback("Kon het geselecteerde bedrijf niet laden.", "danger");
    }
}

function populateCompanyForm(company) {
    isPopulatingCompanyForm = true;

    const formTitle = document.getElementById("companyFormTitle");
    const formSubtitle = document.getElementById("companyFormSubtitle");

    const idInput = document.getElementById("companyId");
    const nameInput = document.getElementById("companyName");
    const descriptionInput = document.getElementById("companyDescription");
    const foundationDateInput = document.getElementById("companyFoundationDate");
    const valueInput = document.getElementById("companyValue");
    const stabilityInput = document.getElementById("companyStability");
    const profitabilityInput = document.getElementById("companyProfitability");

    if (company?.isDraft) {
        if (formTitle) formTitle.textContent = "Nieuw bedrijf";
        if (formSubtitle) formSubtitle.textContent = "Geef eerst een bedrijfsnaam op. Daarna wordt het bedrijf automatisch aangemaakt met standaardwaarden.";
        if (idInput) idInput.value = "";
        if (nameInput) nameInput.value = company.companyName || "";
        if (descriptionInput) descriptionInput.value = company.description || "";
        if (foundationDateInput) foundationDateInput.value = company.foundationDate || getDefaultFoundationDate();
        if (valueInput) valueInput.value = formatCompanyValueForInput(company.companyValue);
        if (stabilityInput) stabilityInput.value = String(normalizeSliderValue(company.stability));
        if (profitabilityInput) profitabilityInput.value = String(normalizeSliderValue(company.profitability));
        updateCompanyLogo(null, false);
        updateCompanyLogoActions(false, false);
        updateSliderPresentation("stability");
        updateSliderPresentation("profitability");
        updateCompanyTypePresentation();
        isPopulatingCompanyForm = false;
        return;
    }

    if (!company) {
        if (formTitle) formTitle.textContent = "Bedrijfsgegevens";
        if (formSubtitle) formSubtitle.textContent = "Kies links een bedrijf of maak een nieuw bedrijf aan.";
        if (idInput) idInput.value = "";
        if (nameInput) nameInput.value = "";
        if (descriptionInput) descriptionInput.value = "";
        if (foundationDateInput) foundationDateInput.value = "";
        if (valueInput) valueInput.value = "0.00";
        if (stabilityInput) stabilityInput.value = "0";
        if (profitabilityInput) profitabilityInput.value = "0";
        updateCompanyLogo(null, false);
        updateCompanyLogoActions(false, false);
        updateSliderPresentation("stability");
        updateSliderPresentation("profitability");
        updateCompanyTypePresentation();
        isPopulatingCompanyForm = false;
        return;
    }

    if (formTitle) formTitle.textContent = company.companyName || "Bedrijfsgegevens";
    if (formSubtitle) formSubtitle.textContent = "Werk de gegevens van dit bedrijf bij. Wijzigingen worden automatisch bewaard.";
    if (idInput) idInput.value = String(company.id || "");
    if (nameInput) nameInput.value = company.companyName || "";
    if (descriptionInput) descriptionInput.value = company.description || "";
    if (foundationDateInput) foundationDateInput.value = company.foundationDate || "";
    if (valueInput) valueInput.value = formatCompanyValueForInput(company.companyValue);
    if (stabilityInput) stabilityInput.value = String(normalizeSliderValue(company.stability));
    if (profitabilityInput) profitabilityInput.value = String(normalizeSliderValue(company.profitability));
    updateCompanyLogo(company.logoUrl || null, Boolean(company.logoUrl));
    updateCompanyLogoActions(true, Boolean(company.logoUrl));

    updateSliderPresentation("stability");
    updateSliderPresentation("profitability");
    updateCompanyTypePresentation();
    isPopulatingCompanyForm = false;
}

function setCompanyFormState(enabled, options = {}) {
    const form = document.getElementById("companyForm");
    if (!form) return;

    const { nameOnly = false } = options;

    Array.from(form.elements).forEach((element) => {
        if ("disabled" in element) {
            if (element.id === "companyId") {
                element.disabled = false;
                return;
            }

            element.disabled = !enabled || (nameOnly && element.id !== "companyName");
        }
    });

    if (!enabled || nameOnly) {
        updateCompanyLogoActions(false, hasCompanyLogo());
    }
}

function startCreatingCompany() {
    companyAutoSavePending = false;
    hideCompanyFeedback();
    isCreatingCompany = true;
    createCompanyRequestInFlight = false;
    currentCompanyId = 0;
    populateCompanyForm(createEmptyCompanyDraft());
    setCompanyFormState(true, { nameOnly: true });
    renderCompanyList();
    document.getElementById("companyName")?.focus();
}

function createEmptyCompanyDraft() {
    return {
        companyName: "",
        description: "",
        foundationDate: getDefaultFoundationDate(),
        companyValue: 0,
        stability: 0,
        profitability: 0,
        isDraft: true
    };
}

function getDefaultFoundationDate() {
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    date.setFullYear(date.getFullYear() - 100);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

function updateCompanyLogo(logoUrl, hasLogo) {
    const logoImage = document.getElementById("companyLogoImage");
    const logoPlaceholder = document.getElementById("companyLogoPlaceholder");

    if (!logoImage || !logoPlaceholder) return;

    if (logoUrl) {
        logoImage.src = logoUrl;
        logoImage.classList.remove("d-none");
        logoPlaceholder.classList.add("d-none");
        return;
    }

    logoImage.src = "";
    logoImage.classList.add("d-none");
    logoPlaceholder.classList.remove("d-none");
    logoPlaceholder.textContent = hasLogo ? "Logo laden..." : "Geen logo";
}

function hasCompanyLogo() {
    const logoImage = document.getElementById("companyLogoImage");
    return Boolean(logoImage && !logoImage.classList.contains("d-none") && logoImage.src);
}

function updateCompanyLogoActions(canManage, hasLogo) {
    const uploadButton = document.getElementById("companyLogoUploadButton");
    const deleteButton = document.getElementById("companyLogoDeleteButton");
    const fileInput = document.getElementById("companyLogoFileInput");

    if (uploadButton) {
        uploadButton.disabled = !canManage;
    }

    if (deleteButton) {
        deleteButton.disabled = !canManage || !hasLogo;
    }

    if (fileInput) {
        fileInput.disabled = !canManage;
    }
}

function markCompanyAutoSavePending() {
    if (isPopulatingCompanyForm) {
        return;
    }

    companyAutoSavePending = true;
}

async function flushCompanyAutoSave(options = {}) {
    const { force = true } = options;

    if (isPopulatingCompanyForm) {
        return true;
    }

    if (saveCompanyInFlightPromise) {
        const currentSaveResult = await saveCompanyInFlightPromise;
        if (!force && !companyAutoSavePending && !saveCompanyQueued) {
            return currentSaveResult;
        }
    }

    const hasPendingAutoSave = companyAutoSavePending || saveCompanyQueued;
    if (!force && !hasPendingAutoSave) {
        return true;
    }

    companyAutoSavePending = false;
    return await saveCompany({
        successMessage: "Automatisch opgeslagen.",
        silentWhenUnavailable: true
    });
}

async function ensureCompanyCreated(force = false) {
    if (!isCreatingCompany || createCompanyRequestInFlight || Number(document.getElementById("companyId")?.value || 0) > 0) {
        return;
    }

    const companyName = String(document.getElementById("companyName")?.value || "").trim();
    if (!companyName) {
        return;
    }

    createCompanyRequestInFlight = true;
    setCompanyFormState(false);
    showCompanyFeedback("Nieuw bedrijf wordt aangemaakt...", "info");

    try {
        const createdCompany = await apiFetchJson("api/companies/newCompany.php", {
            method: "POST",
            body: {
                companyName
            }
        });

        const createdCompanyId = Number(createdCompany?.id || 0);
        if (createdCompanyId <= 0) {
            throw new Error("Geen geldig bedrijf ID ontvangen.");
        }

        isCreatingCompany = false;
        setCompanyFormState(true);
        await loadCompanyList(createdCompanyId);
        showCompanyFeedback("Nieuw bedrijf aangemaakt.", "success");
    } catch (err) {
        console.error("Fout bij aanmaken bedrijf:", err);
        setCompanyFormState(true, { nameOnly: true });
        showCompanyFeedback(force ? "Kon het nieuwe bedrijf niet aanmaken." : "Kon het nieuwe bedrijf niet automatisch aanmaken.", "danger");
    } finally {
        createCompanyRequestInFlight = false;
    }
}

async function uploadCompanyLogo(file) {
    const id = Number(document.getElementById("companyId")?.value || 0);
    if (id <= 0) {
        showCompanyFeedback("Maak eerst het bedrijf aan voor je een logo oplaadt.", "danger");
        return;
    }

    const formData = new FormData();
    formData.append("id", String(id));
    formData.append("logo", file);

    const logoImage = document.getElementById("companyLogoImage");
    const previousLogoUrl = logoImage && !logoImage.classList.contains("d-none") ? logoImage.src : null;
    const previousHasLogo = Boolean(previousLogoUrl);

    if (!previousHasLogo) {
        updateCompanyLogo(null, true);
    }

    updateCompanyLogoActions(false, previousHasLogo);
    showCompanyFeedback("Bedrijfslogo wordt opgeladen...", "info");

    try {
        const response = await fetch("api/companies/uploadCompanyLogo.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) {
            let text = "";
            try {
                text = await response.text();
            } catch (error) {
                text = "(geen body)";
            }
            throw new Error(`API-fout ${response.status}: ${text}`);
        }

        const result = await response.json();
        updateCompanyLogo(result?.logoUrl || null, Boolean(result?.logoUrl));
        updateCompanyLogoActions(true, Boolean(result?.logoUrl));
        showCompanyFeedback("Bedrijfslogo opgeslagen.", "success");
    } catch (err) {
        console.error("Fout bij opladen bedrijfslogo:", err);
        updateCompanyLogo(previousLogoUrl, previousHasLogo);
        updateCompanyLogoActions(true, previousHasLogo);
        showCompanyFeedback("Kon het bedrijfslogo niet opladen.", "danger");
    }
}

async function deleteCompanyLogo() {
    const id = Number(document.getElementById("companyId")?.value || 0);
    if (id <= 0) {
        showCompanyFeedback("Kies eerst een bedrijf.", "danger");
        return;
    }

    updateCompanyLogoActions(false, hasCompanyLogo());
    showCompanyFeedback("Bedrijfslogo wordt verwijderd...", "info");

    try {
        await apiFetchJson("api/companies/deleteCompanyLogo.php", {
            method: "POST",
            body: { id }
        });

        updateCompanyLogo(null, false);
        updateCompanyLogoActions(true, false);
        showCompanyFeedback("Bedrijfslogo verwijderd.", "success");
    } catch (err) {
        console.error("Fout bij verwijderen bedrijfslogo:", err);
        updateCompanyLogoActions(true, hasCompanyLogo());
        showCompanyFeedback("Kon het bedrijfslogo niet verwijderen.", "danger");
    }
}

function normalizeSliderValue(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return 0;
    return Math.max(-7, Math.min(7, Math.round(parsed)));
}

function formatCompanyValueForInput(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return "0.00";
    return parsed.toFixed(2);
}

function parseCompanyValueInputValue(value) {
    const normalizedValue = String(value ?? "").trim().replace(",", ".");
    if (!normalizedValue) {
        return null;
    }

    const parsed = Number(normalizedValue);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return null;
    }

    return parsed;
}

function getCompanyTypeDefinitionForValue(value) {
    const normalizedValue = Number(value);
    if (!Number.isFinite(normalizedValue) || normalizedValue < 0) {
        return null;
    }

    return COMPANY_TYPE_DEFINITIONS.find((type) => normalizedValue <= type.max) || null;
}

function updateCompanyTypePresentation() {
    const idInput = document.getElementById("companyId");
    const nameInput = document.getElementById("companyName");
    const valueInput = document.getElementById("companyValue");
    const typeCard = document.getElementById("companyTypeCard");
    const typeLabel = document.getElementById("companyTypeLabel");
    const typeDescription = document.getElementById("companyTypeDescription");

    if (!idInput || !nameInput || !valueInput || !typeCard || !typeLabel || !typeDescription) {
        return;
    }

    const hasCompanyContext = Number(idInput.value || 0) > 0
        || isCreatingCompany
        || String(nameInput.value || "").trim() !== "";
    const companyValue = parseCompanyValueInputValue(valueInput.value);
    const companyType = companyValue === null ? null : getCompanyTypeDefinitionForValue(companyValue);

    if (!hasCompanyContext || !companyType) {
        typeCard.classList.add("d-none");
        typeLabel.textContent = "";
        typeDescription.textContent = "";
        return;
    }

    typeCard.classList.remove("d-none");
    typeLabel.textContent = companyType.label;
    typeDescription.textContent = companyType.description;
}

function getSliderMessage(value, ranges) {
    return ranges.find((range) => value >= range.min && value <= range.max)?.message || "";
}

function updateSliderPresentation(type) {
    const isStability = type === "stability";
    const input = document.getElementById(isStability ? "companyStability" : "companyProfitability");
    const valueEl = document.getElementById(isStability ? "companyStabilityValue" : "companyProfitabilityValue");
    const helpEl = document.getElementById(isStability ? "companyStabilityHelp" : "companyProfitabilityHelp");

    if (!input || !valueEl || !helpEl) return;

    const value = normalizeSliderValue(input.value);
    input.value = String(value);
    valueEl.textContent = value > 0 ? `+${value}` : String(value);
    helpEl.textContent = getSliderMessage(
        value,
        isStability ? COMPANY_STABILITY_RANGES : COMPANY_PROFITABILITY_RANGES
    );
}

async function saveCompany(options = {}) {
    if (saveCompanyRequestInFlight) {
        saveCompanyQueued = true;
        return false;
    }

    const task = (async () => {
        saveCompanyRequestInFlight = true;

        try {
            const {
                successMessage = "Bedrijf opgeslagen.",
                silentWhenUnavailable = false
            } = options;
            let id = Number(document.getElementById("companyId")?.value || 0);
            const companyName = String(document.getElementById("companyName")?.value || "").trim();
            const description = String(document.getElementById("companyDescription")?.value || "").trim();
            const foundationDate = String(document.getElementById("companyFoundationDate")?.value || "").trim();
            const companyValueRaw = String(document.getElementById("companyValue")?.value || "").trim().replace(",", ".");
            const stability = normalizeSliderValue(document.getElementById("companyStability")?.value);
            const profitability = normalizeSliderValue(document.getElementById("companyProfitability")?.value);

            const companyValue = Number(companyValueRaw);

            if (id <= 0) {
                if (isCreatingCompany) {
                    await ensureCompanyCreated(true);
                    id = Number(document.getElementById("companyId")?.value || 0);
                }

                if (id <= 0) {
                    if (!silentWhenUnavailable) {
                        showCompanyFeedback("Kies eerst een bedrijf of maak een nieuw bedrijf aan.", "danger");
                    }
                    return true;
                }
            }

            if (!companyName) {
                showCompanyFeedback("De bedrijfsnaam is verplicht.", "danger");
                return false;
            }

            if (companyValueRaw === "" || !Number.isFinite(companyValue) || companyValue < 0) {
                showCompanyFeedback("Geef een geldige waarde op.", "danger");
                return false;
            }

            await apiFetchJson("api/companies/updateCompany.php", {
                method: "POST",
                body: {
                    id,
                    companyName,
                    description,
                    foundationDate,
                    companyValue,
                    stability,
                    profitability
                }
            });

            await loadCompanyList(id);
            showCompanyFeedback(successMessage, "success");
            return true;
        } catch (err) {
            console.error("Fout bij bewaren bedrijf:", err);
            showCompanyFeedback("Kon het bedrijf niet opslaan.", "danger");
            return false;
        } finally {
            saveCompanyRequestInFlight = false;
        }
    })();

    saveCompanyInFlightPromise = task;

    try {
        return await task;
    } finally {
        if (saveCompanyInFlightPromise === task) {
            saveCompanyInFlightPromise = null;
        }

        if (saveCompanyQueued) {
            saveCompanyQueued = false;
            companyAutoSavePending = true;
            void flushCompanyAutoSave();
        }
    }
}

function showCompanyFeedback(message, type = "success") {
    const feedback = document.getElementById("companyFeedback");
    if (!feedback) return;

    feedback.className = `alert py-2 px-3 mb-0 alert-${type}`;
    feedback.textContent = message;
}

function hideCompanyFeedback() {
    const feedback = document.getElementById("companyFeedback");
    if (!feedback) return;

    feedback.className = "alert py-2 px-3 mb-0 d-none";
    feedback.textContent = "";
}
