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
        min: 0,
        max: 30000,
        description: "Dit type onderneming is klein, persoonlijk en volledig verweven met het leven van de eigenaar. De zaak is vaak gevestigd in of naast de woning, en draait op vakmanschap, reputatie en vaste klanten. Groei is beperkt, maar stabiliteit kan hoog zijn zolang de eigenaar gezond blijft. Innovatie gebeurt traag en meestal uit noodzaak, niet uit strategie. Dit zijn de stille radertjes van de stad: onmisbaar, maar zelden zichtbaar in het grote economische spel. Voorbeelden: bakkerij, kleermakerij, schoenmaker, kleine herberg, horlogemaker."
    },
    {
        key: "family",
        label: "Familiebedrijf",
        min: 30000,
        max: 450000,
        description: "Een familiebedrijf vormt een eerste echte economische entiteit los van één individu. Meerdere familieleden of werknemers dragen bij aan de werking, en er is vaak sprake van specialisatie en eenvoudige organisatie. Investeringen in materiaal of infrastructuur zijn merkbaar, en het bedrijf kan lokale concurrentie aangaan of zelfs domineren. De continuïteit ligt in de familie: generaties bouwen verder op wat eerder is opgebouwd. Voorbeelden: drukkerij, brouwerij, transportbedrijf met paarden en vroege stoomwagens, kleine bouwonderneming, industriële wasserij."
    },
    {
        key: "national",
        label: "Nationale onderneming",
        min: 450000,
        max: 6750000,
        description: "Dit type bedrijf overstijgt de lokale markt en opereert op nationaal niveau, vaak met meerdere vestigingen of distributiepunten. Er ontstaat een duidelijke hiërarchie met management, administratie en gespecialiseerde arbeiders. De eigenaar is niet langer dagelijks betrokken bij de uitvoering, maar stuurt op afstand via leidinggevenden. Deze bedrijven zijn zichtbaar in het straatbeeld en beginnen een merkidentiteit op te bouwen. Ze beïnvloeden prijzen, werkgelegenheid en soms zelfs lokale politiek. Voorbeelden: warenhuisketen, nationale brouwerijgroep, spoorwegmaatschappij binnen één land, grote textielfabriek, gas- of elektriciteitsmaatschappij in opkomst."
    },
    {
        key: "small_international",
        label: "Kleine internationale groep",
        min: 6750000,
        max: 101250000,
        description: "Deze bedrijven opereren over landsgrenzen heen en combineren productie, handel en financiën. Ze hebben dochterondernemingen in meerdere regio’s en werken vaak met complexe eigendomsstructuren. Beslissingen worden strategisch genomen en hebben impact op hele sectoren. Innovatie speelt een grotere rol, zeker in deze moderne tijd waar technologie een concurrentievoordeel biedt. Ze onderhouden banden met banken, adel en overheden. Voorbeelden: internationale staalproducent, chemisch bedrijf, spoorwegnetwerk tussen meerdere landen, fabrikant van industriële machines, telegraaf- of communicatienetwerk."
    },
    {
        key: "large_international",
        label: "Grote internationale groep",
        min: 101250000,
        max: Infinity,
        description: "Dit zijn economische grootmachten die functioneren als staten binnen staten. Ze controleren volledige productieketens, van grondstof tot eindproduct, en hebben invloed op internationale handel en politiek. De leiding bestaat uit elites: industriëlen, bankiers en adel. Hun beslissingen kunnen oorlogen beïnvloeden, steden doen groeien of instorten, en technologische revoluties versnellen. In een steampunkwereld zijn dit de bedrijven die experimenteren met grensverleggende en soms gevaarlijke technologieën. Voorbeelden: continentaal spoorwegimperium, megaconglomeraat in staal en wapens, energiebedrijf met stoom- en elektrische netwerken, internationale bankholding, fabrikant van geavanceerde lucht- of oorlogsmachines."
    }
];

let currentCompanyId = 0;
let currentCompanyData = null;
let companies = [];
let companyPageListenersInitialized = false;
let isCreatingCompany = false;
let createCompanyRequestInFlight = false;
let companyAutoSavePending = false;
let isPopulatingCompanyForm = false;
let saveCompanyRequestInFlight = false;
let saveCompanyQueued = false;
let saveCompanyInFlightPromise = null;
let currentCompanyPersonnelState = [];
let companyPersonnelSavePending = false;
let saveCompanyPersonnelRequestInFlight = false;
let saveCompanyPersonnelQueued = false;
let saveCompanyPersonnelInFlightPromise = null;
let companyPersonnelSpecialisationModalInstance = null;

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

    const companyPersonnelCard = document.getElementById("companyPersonnelCard");
    if (companyPersonnelCard) {
        companyPersonnelCard.addEventListener("click", handleCompanyPersonnelCardClick);
        companyPersonnelCard.addEventListener("change", handleCompanyPersonnelCardChange);
    }

    const companySnapshotsCard = document.getElementById("companySnapshotsCard");
    if (companySnapshotsCard) {
        companySnapshotsCard.addEventListener("click", handleCompanySnapshotsCardClick);
        companySnapshotsCard.addEventListener("change", handleCompanySnapshotsCardChange);
    }

    const createCompanySnapshotButton = document.getElementById("createCompanySnapshotButton");
    if (createCompanySnapshotButton) {
        createCompanySnapshotButton.addEventListener("click", () => {
            void createCompanySnapshot();
        });
    }

    const companyPersonnelSpecSaveBtn = document.getElementById("companyPersonnelSpecSaveBtn");
    if (companyPersonnelSpecSaveBtn) {
        companyPersonnelSpecSaveBtn.addEventListener("click", saveCompanyPersonnelSpecialisationFromModal);
    }
}

async function loadCompanyList(selectedCompanyId = 0) {
    try {
        const list = await apiFetchJson("api/companies/getCompanyList.php");
        companies = Array.isArray(list) ? list : [];

        renderCompanyList();

        if (companies.length === 0) {
            currentCompanyId = 0;
            currentCompanyData = null;
            currentCompanyPersonnelState = [];
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
        currentCompanyData = company || null;
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
    companyPersonnelSavePending = false;
    saveCompanyPersonnelQueued = false;

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
        currentCompanyData = company;
        currentCompanyPersonnelState = [];
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
        renderCompanyShareholders(null);
        renderCompanySnapshots(null);
        renderCompanyPersonnel(null);
        isPopulatingCompanyForm = false;
        return;
    }

    if (!company) {
        currentCompanyData = null;
        currentCompanyPersonnelState = [];
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
        renderCompanyShareholders(null);
        renderCompanySnapshots(null);
        renderCompanyPersonnel(null);
        isPopulatingCompanyForm = false;
        return;
    }

    currentCompanyData = company;
    currentCompanyPersonnelState = cloneCompanyPersonnelEntries(company?.personnelEntries);
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
    renderCompanyShareholders(company);
    renderCompanySnapshots(company);
    renderCompanyPersonnel(company);
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
    companyPersonnelSavePending = false;
    hideCompanyFeedback();
    isCreatingCompany = true;
    createCompanyRequestInFlight = false;
    currentCompanyId = 0;
    currentCompanyData = createEmptyCompanyDraft();
    currentCompanyPersonnelState = [];
    populateCompanyForm(currentCompanyData);
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
        if (!force && !companyAutoSavePending && !saveCompanyQueued && !companyPersonnelSavePending && !saveCompanyPersonnelQueued) {
            return currentSaveResult;
        }
    }

    if (saveCompanyPersonnelInFlightPromise) {
        const currentPersonnelSaveResult = await saveCompanyPersonnelInFlightPromise;
        if (!force && !companyAutoSavePending && !saveCompanyQueued && !companyPersonnelSavePending && !saveCompanyPersonnelQueued) {
            return currentPersonnelSaveResult;
        }
    }

    const hasPendingCompanyAutoSave = companyAutoSavePending || saveCompanyQueued;
    const hasPendingPersonnelSave = companyPersonnelSavePending || saveCompanyPersonnelQueued;
    if (!force && !hasPendingCompanyAutoSave && !hasPendingPersonnelSave) {
        return true;
    }

    let personnelSaveResult = true;
    if (hasPendingPersonnelSave) {
        companyPersonnelSavePending = false;
        personnelSaveResult = await saveCompanyPersonnel({
            successMessage: "Personeel automatisch opgeslagen.",
            silentWhenUnavailable: true
        });
    }

    if (!personnelSaveResult) {
        return false;
    }

    if (!hasPendingCompanyAutoSave) {
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

function formatCompanyRangeCurrency(value) {
    return `${Number(value || 0).toLocaleString("nl-BE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function formatCompanyTypeRange(type) {
    if (!type) {
        return "";
    }

    const min = Number(type.min);
    const max = Number(type.max);
    const hasFiniteMin = Number.isFinite(min) && min > 0;
    const hasFiniteMax = Number.isFinite(max);

    if (!hasFiniteMin && hasFiniteMax) {
        return `t.e.m. ${formatCompanyRangeCurrency(max)}`;
    }

    if (hasFiniteMin && hasFiniteMax) {
        return `Vanaf ${formatCompanyRangeCurrency(min)} t.e.m. ${formatCompanyRangeCurrency(max)}`;
    }

    if (hasFiniteMin) {
        return `Vanaf ${formatCompanyRangeCurrency(min)}`;
    }

    return "";
}

function updateCompanyTypePresentation() {
    const idInput = document.getElementById("companyId");
    const nameInput = document.getElementById("companyName");
    const valueInput = document.getElementById("companyValue");
    const typeCard = document.getElementById("companyTypeCard");
    const typeLabel = document.getElementById("companyTypeLabel");
    const typeRange = document.getElementById("companyTypeRange");
    const typeDescription = document.getElementById("companyTypeDescription");

    if (!idInput || !nameInput || !valueInput || !typeCard || !typeLabel || !typeRange || !typeDescription) {
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
        typeRange.textContent = "";
        typeDescription.textContent = "";
        return;
    }

    typeCard.classList.remove("d-none");
    typeLabel.textContent = companyType.label;
    typeRange.textContent = formatCompanyTypeRange(companyType);
    typeDescription.textContent = companyType.description;
}

function renderCompanyShareholders(company) {
    const card = document.getElementById("companyShareholdersCard");
    const groupsHost = document.getElementById("companyShareholdersGroups");
    const availableHost = document.getElementById("companyShareholdersAvailable");

    if (!card || !groupsHost || !availableHost) {
        return;
    }

    const hasCompany = Number(company?.id || 0) > 0 && !company?.isDraft;
    if (!hasCompany) {
        card.classList.add("d-none");
        groupsHost.innerHTML = "";
        availableHost.textContent = "0% beschikbaar";
        return;
    }

    const groups = Array.isArray(company?.shareholderGroups) ? company.shareholderGroups : [];
    const nonEmptyGroups = groups.filter((group) => Array.isArray(group?.shareholders) && group.shareholders.length > 0);

    groupsHost.innerHTML = "";
    card.classList.remove("d-none");
    availableHost.textContent = `${Number(company?.availableSharePercentage || 0)}% beschikbaar`;

    if (nonEmptyGroups.length === 0) {
        const empty = document.createElement("p");
        empty.className = "company-shareholders-empty mb-0";
        empty.textContent = "Nog geen aandeelhouders gekoppeld.";
        groupsHost.appendChild(empty);
        return;
    }

    nonEmptyGroups.forEach((group) => {
        const section = document.createElement("section");
        section.className = "company-shareholders-group";

        const title = document.createElement("h3");
        title.className = "company-shareholders-group-title";
        title.textContent = group.label || `${group.shareClass || ""}-aandelen`;
        section.appendChild(title);

        const list = document.createElement("div");
        list.className = "company-shareholders-list";

        group.shareholders.forEach((shareholder) => {
            const item = document.createElement("div");
            item.className = "company-shareholders-item";

            const name = document.createElement("span");
            name.className = "company-shareholders-name";
            name.textContent = shareholder.displayName || `Personage #${shareholder.idCharacter || 0}`;
            item.appendChild(name);

            const percentage = document.createElement("span");
            percentage.className = "company-shareholders-percentage";
            percentage.textContent = `${Number(shareholder.percentage || 0)}%`;
            item.appendChild(percentage);

            list.appendChild(item);
        });

        section.appendChild(list);
        groupsHost.appendChild(section);
    });
}

function formatCompanySnapshotEventOptionLabel(option) {
    const title = String(option?.title || "").trim();
    const dateStart = String(option?.dateStart || "").trim();
    return dateStart ? `${title} (${dateStart})` : title;
}

function formatCompanySnapshotCurrency(amount) {
    const numericAmount = Number(amount || 0);
    return `${numericAmount.toLocaleString("nl-BE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function getCompanySnapshotAdjustedVariationAmounts(snapshot) {
    const companyValue = Number(snapshot?.companyValue || 0);
    const lowerBoundPercentage = Number(snapshot?.stabilityLowerBoundPercentage || 0);
    const upperBoundPercentage = Number(snapshot?.stabilityUpperBoundPercentage || 0);
    const personnelImpactPercentage = Number(snapshot?.personnelImpactPercentage || 0);

    const baseMinAmount = companyValue * (lowerBoundPercentage / 100);
    const baseMaxAmount = companyValue * (upperBoundPercentage / 100);
    let adjustedMinAmount = baseMinAmount;
    let adjustedMaxAmount = baseMaxAmount;

    if (personnelImpactPercentage > 0 && adjustedMinAmount < 0) {
        adjustedMinAmount += Math.abs(adjustedMinAmount) * (personnelImpactPercentage / 100);
    } else if (personnelImpactPercentage < 0 && adjustedMaxAmount > 0) {
        adjustedMaxAmount += adjustedMaxAmount * (personnelImpactPercentage / 100);
    }

    return {
        minAmount: adjustedMinAmount,
        maxAmount: adjustedMaxAmount
    };
}

function getCompanySnapshotAppliedActionLabel(action) {
    if (action === "loss_adjustment") return "Bedrijfswaarde aangepast";
    if (action === "reinvest") return "Herinvesteerd";
    if (action === "dividend") return "Dividend uitbetaald";
    return "";
}

function applyCompanySnapshotResponse(result, idCompany) {
    if (result?.company && Number(result.company.id || 0) === idCompany) {
        currentCompanyId = idCompany;
        populateCompanyForm(result.company);
        renderCompanyList();
        return;
    }

    if (currentCompanyData && Number(currentCompanyData.id || 0) === idCompany) {
        currentCompanyData.availableSharePercentage = Number(result?.availableSharePercentage || 0);
        currentCompanyData.snapshotEventOptions = Array.isArray(result?.snapshotEventOptions) ? result.snapshotEventOptions : [];
        currentCompanyData.snapshots = Array.isArray(result?.snapshots) ? result.snapshots : [];
    }

    renderCompanySnapshots(currentCompanyData);
}

function sortCompanySnapshotsByDate(snapshots) {
    return [...(Array.isArray(snapshots) ? snapshots : [])].sort((left, right) => {
        const leftDate = String(left?.dateStart || "");
        const rightDate = String(right?.dateStart || "");
        if (leftDate !== rightDate) {
            return leftDate.localeCompare(rightDate);
        }

        return Number(left?.idCompanySnapshot || 0) - Number(right?.idCompanySnapshot || 0);
    });
}

function renderCompanySnapshots(company) {
    const card = document.getElementById("companySnapshotsCard");
    const eventSelect = document.getElementById("companySnapshotEventSelect");
    const list = document.getElementById("companySnapshotsList");
    const empty = document.getElementById("companySnapshotsEmpty");
    const createButton = document.getElementById("createCompanySnapshotButton");

    if (!card || !eventSelect || !list || !empty || !createButton) {
        return;
    }

    const hasCompany = Number(company?.id || 0) > 0 && !company?.isDraft;
    if (!hasCompany) {
        card.classList.add("d-none");
        list.innerHTML = "";
        empty.classList.add("d-none");
        eventSelect.innerHTML = '<option value="0">Kies een event</option>';
        createButton.disabled = true;
        return;
    }

    card.classList.remove("d-none");
    createButton.disabled = false;

    const snapshots = sortCompanySnapshotsByDate(company?.snapshots);
    const usedEventIds = snapshots.map((snapshot) => Number(snapshot?.idEvent || 0)).filter((idEvent) => idEvent > 0);
    const eventOptions = (Array.isArray(company?.snapshotEventOptions) ? company.snapshotEventOptions : [])
        .filter((option) => !usedEventIds.includes(Number(option?.idEvent || 0)));

    eventSelect.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "0";
    placeholder.textContent = "Kies een event";
    eventSelect.appendChild(placeholder);
    eventOptions.forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = String(option.idEvent || 0);
        optionEl.textContent = formatCompanySnapshotEventOptionLabel(option);
        eventSelect.appendChild(optionEl);
    });
    createButton.disabled = eventOptions.length === 0;

    list.innerHTML = "";
    if (snapshots.length === 0) {
        empty.classList.remove("d-none");
        return;
    }

    empty.classList.add("d-none");
    snapshots.forEach((snapshot) => {
        const appliedAction = String(snapshot?.appliedAction || "none");
        const isApplied = appliedAction !== "" && appliedAction !== "none";
        const item = document.createElement("section");
        item.className = "company-snapshot-item";
        item.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);

        const header = document.createElement("div");
        header.className = "company-snapshot-header";

        const titleWrap = document.createElement("div");
        const title = document.createElement("h3");
        title.className = "h5 mb-1";
        title.textContent = snapshot.title || `Event #${snapshot.idEvent || 0}`;
        titleWrap.appendChild(title);

        const subtitle = document.createElement("p");
        subtitle.className = "company-snapshot-subtitle";
        subtitle.textContent = snapshot.dateStart || "";
        titleWrap.appendChild(subtitle);
        header.appendChild(titleWrap);

        const buttonGroup = document.createElement("div");
        buttonGroup.className = "company-snapshot-actions";

        const recalculateButton = document.createElement("button");
        recalculateButton.type = "button";
        recalculateButton.className = "btn btn-outline-secondary btn-sm";
        recalculateButton.dataset.action = "recalculate-company-snapshot";
        recalculateButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        recalculateButton.textContent = "Herbereken";
        recalculateButton.disabled = isApplied;
        buttonGroup.appendChild(recalculateButton);

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "btn btn-outline-danger btn-sm";
        deleteButton.dataset.action = "delete-company-snapshot";
        deleteButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        deleteButton.textContent = "Verwijderen";
        buttonGroup.appendChild(deleteButton);
        header.appendChild(buttonGroup);
        item.appendChild(header);

        const fields = document.createElement("div");
        fields.className = "row g-3";

        const stabilityCol = document.createElement("div");
        stabilityCol.className = "col-12 col-md-6";
        const stabilityRow = document.createElement("div");
        stabilityRow.className = "company-snapshot-inline-field";
        const stabilityLabel = document.createElement("label");
        stabilityLabel.className = "form-label mb-0";
        stabilityLabel.textContent = "Stabiliteit";
        stabilityRow.appendChild(stabilityLabel);
        const stabilityInput = document.createElement("input");
        stabilityInput.type = "number";
        stabilityInput.className = "form-control";
        stabilityInput.min = "-7";
        stabilityInput.max = "7";
        stabilityInput.step = "1";
        stabilityInput.value = String(Number(snapshot.stability || 0));
        stabilityInput.dataset.companySnapshotField = "stability";
        stabilityInput.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        stabilityInput.disabled = isApplied;
        stabilityRow.appendChild(stabilityInput);
        stabilityCol.appendChild(stabilityRow);
        fields.appendChild(stabilityCol);

        const profitabilityCol = document.createElement("div");
        profitabilityCol.className = "col-12 col-md-6";
        const profitabilityRow = document.createElement("div");
        profitabilityRow.className = "company-snapshot-inline-field";
        const profitabilityLabel = document.createElement("label");
        profitabilityLabel.className = "form-label mb-0";
        profitabilityLabel.textContent = "Rendabiliteit";
        profitabilityRow.appendChild(profitabilityLabel);
        const profitabilityInput = document.createElement("input");
        profitabilityInput.type = "number";
        profitabilityInput.className = "form-control";
        profitabilityInput.min = "-7";
        profitabilityInput.max = "7";
        profitabilityInput.step = "1";
        profitabilityInput.value = String(Number(snapshot.profitability || 0));
        profitabilityInput.dataset.companySnapshotField = "profitability";
        profitabilityInput.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        profitabilityInput.disabled = isApplied;
        profitabilityRow.appendChild(profitabilityInput);
        profitabilityCol.appendChild(profitabilityRow);
        fields.appendChild(profitabilityCol);

        item.appendChild(fields);

        const result = document.createElement("div");
        result.className = "company-snapshot-profit";
        const profitAmount = Number(snapshot.profitAmount || 0);
        const baseProfitAmount = Number(snapshot.baseProfitAmount || 0);
        const stabilityAdjustmentAmount = Number(snapshot.stabilityAdjustmentAmount || 0);
        const personnelImpactPercentage = Number(snapshot.personnelImpactPercentage || 0);
        const lowerBoundPercentage = Number(snapshot.stabilityLowerBoundPercentage || 0);
        const upperBoundPercentage = Number(snapshot.stabilityUpperBoundPercentage || 0);
        const variationAmounts = getCompanySnapshotAdjustedVariationAmounts(snapshot);
        result.innerHTML = `
            <div class="company-snapshot-profit-label">Winst/verlies periode</div>
            <div class="company-snapshot-profit-value">${formatCompanySnapshotCurrency(profitAmount)}</div>
            <div class="company-snapshot-profit-meta">
                <div>Rendabiliteit: ${formatCompanySnapshotCurrency(baseProfitAmount)}</div>
                <div>Stabiliteit: ${formatCompanySnapshotCurrency(variationAmounts.minAmount)} tot ${formatCompanySnapshotCurrency(variationAmounts.maxAmount)}</div>
            </div>
        `;
        item.appendChild(result);

        const actionSection = document.createElement("div");
        actionSection.className = "company-snapshot-profit-meta mt-3";
        const dividendPerPercentage = profitAmount > 0 ? Number((profitAmount / 110).toFixed(2)) : 0;

        if (isApplied) {
            const appliedInfo = document.createElement("div");
            appliedInfo.textContent = `Toegepast: ${getCompanySnapshotAppliedActionLabel(appliedAction)}`;
            actionSection.appendChild(appliedInfo);
        } else if (profitAmount < 0) {
            const lossButton = document.createElement("button");
            lossButton.type = "button";
            lossButton.className = "btn btn-outline-danger btn-sm";
            lossButton.dataset.action = "apply-company-snapshot";
            lossButton.dataset.applyAction = "loss_adjustment";
            lossButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
            lossButton.textContent = "Bedrijfswaarde aanpassen";
            actionSection.appendChild(lossButton);
        } else if (profitAmount > 0) {
            const dividendInfo = document.createElement("div");
            dividendInfo.textContent = `Dividend voor 1%: ${formatCompanySnapshotCurrency(dividendPerPercentage)}`;
            actionSection.appendChild(dividendInfo);

            const actionButtons = document.createElement("div");
            actionButtons.className = "company-snapshot-actions mt-2";

            const reinvestButton = document.createElement("button");
            reinvestButton.type = "button";
            reinvestButton.className = "btn btn-outline-primary btn-sm";
            reinvestButton.dataset.action = "apply-company-snapshot";
            reinvestButton.dataset.applyAction = "reinvest";
            reinvestButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
            reinvestButton.textContent = "Herinvesteren";
            actionButtons.appendChild(reinvestButton);

            const dividendButton = document.createElement("button");
            dividendButton.type = "button";
            dividendButton.className = "btn btn-outline-success btn-sm";
            dividendButton.dataset.action = "apply-company-snapshot";
            dividendButton.dataset.applyAction = "dividend";
            dividendButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
            dividendButton.textContent = "Dividend uitbetalen";
            actionButtons.appendChild(dividendButton);

            actionSection.appendChild(actionButtons);
        }

        if (actionSection.childElementCount > 0) {
            item.appendChild(actionSection);
        }
        list.appendChild(item);
    });
}

function cloneCompanyPersonnelEntries(entries) {
    try {
        return JSON.parse(JSON.stringify(Array.isArray(entries) ? entries : []));
    } catch (error) {
        return [];
    }
}

function getCompanyPersonnelCharacterOptions() {
    return Array.isArray(currentCompanyData?.personnelCharacterOptions)
        ? currentCompanyData.personnelCharacterOptions
        : [];
}

function getCompanyPersonnelSkillOptions() {
    return Array.isArray(currentCompanyData?.personnelSkillOptions)
        ? currentCompanyData.personnelSkillOptions
        : [];
}

function getCompanyPersonnelImportanceOptions() {
    return Array.isArray(currentCompanyData?.personnelImportanceOptions) && currentCompanyData.personnelImportanceOptions.length > 0
        ? currentCompanyData.personnelImportanceOptions
        : ["Negligible", "Low", "Moderate", "High", "Critical"];
}

function createEmptyCompanyPersonnelEntry() {
    return {
        idCharacter: 0,
        importance: "Moderate",
        salaryIncreasePercentage: 0,
        skills: []
    };
}

function createEmptyCompanyPersonnelSkill() {
    return {
        idSkill: 0,
        level: 1,
        specialisations: []
    };
}

function findCompanyPersonnelSkillOption(idSkill) {
    return getCompanyPersonnelSkillOptions().find((skill) => Number(skill?.idSkill || 0) === Number(idSkill)) || null;
}

function getCompanyPersonnelCharacterLabel(idCharacter) {
    const option = getCompanyPersonnelCharacterOptions().find((entry) => Number(entry?.idCharacter || 0) === Number(idCharacter));
    return option?.nameLabel || option?.displayName || `Personage #${Number(idCharacter || 0)}`;
}

function getCompanyPersonnelCharacterProfessionLabel(idCharacter) {
    const option = getCompanyPersonnelCharacterOptions().find((entry) => Number(entry?.idCharacter || 0) === Number(idCharacter));
    return option?.professionLabel || "";
}

function getAvailableCharacterOptionsForPersonnelEntry(entryIndex) {
    const selectedCharacterIds = currentCompanyPersonnelState
        .map((entry, index) => index === entryIndex ? 0 : Number(entry?.idCharacter || 0))
        .filter((idCharacter) => idCharacter > 0);

    return getCompanyPersonnelCharacterOptions().filter((option) => {
        const idCharacter = Number(option?.idCharacter || 0);
        const currentIdCharacter = Number(currentCompanyPersonnelState?.[entryIndex]?.idCharacter || 0);
        return idCharacter === currentIdCharacter || !selectedCharacterIds.includes(idCharacter);
    });
}

function getAvailableSkillOptionsForPersonnelEntry(entry, skillIndex) {
    const selectedSkillIds = (Array.isArray(entry?.skills) ? entry.skills : [])
        .map((skill, index) => index === skillIndex ? 0 : Number(skill?.idSkill || 0))
        .filter((idSkill) => idSkill > 0);

    return getCompanyPersonnelSkillOptions().filter((option) => {
        const idSkill = Number(option?.idSkill || 0);
        const currentIdSkill = Number(entry?.skills?.[skillIndex]?.idSkill || 0);
        return idSkill === currentIdSkill || !selectedSkillIds.includes(idSkill);
    });
}

function getAvailableSpecialisationOptionsForPersonnelSkill(skillEntry) {
    const idSkill = Number(skillEntry?.idSkill || 0);
    if (idSkill <= 0) {
        return [];
    }

    const selectedIds = (Array.isArray(skillEntry?.specialisations) ? skillEntry.specialisations : [])
        .map((specialisation) => Number(specialisation?.idSkillSpecialisation || 0))
        .filter((idSkillSpecialisation) => idSkillSpecialisation > 0);

    const skillOption = findCompanyPersonnelSkillOption(idSkill);
    const specialisations = Array.isArray(skillOption?.specialisations) ? skillOption.specialisations : [];

    return specialisations.filter((specialisation) => {
        const idSkillSpecialisation = Number(specialisation?.idSkillSpecialisation || 0);
        return idSkillSpecialisation > 0 && !selectedIds.includes(idSkillSpecialisation);
    });
}

function formatCompanyPersonnelSkillLevelLabel(level) {
    if (Number(level) === 3) return "Meester";
    if (Number(level) === 2) return "Deskundige";
    return "Beginneling";
}

function renderCompanyPersonnel(company) {
    const card = document.getElementById("companyPersonnelCard");
    const list = document.getElementById("companyPersonnelList");
    const empty = document.getElementById("companyPersonnelEmpty");
    const addButton = document.getElementById("addCompanyPersonnelButton");

    if (!card || !list || !empty || !addButton) {
        return;
    }

    const hasCompany = Number(company?.id || 0) > 0 && !company?.isDraft;
    if (!hasCompany) {
        card.classList.add("d-none");
        list.innerHTML = "";
        empty.classList.add("d-none");
        addButton.disabled = true;
        return;
    }

    card.classList.remove("d-none");
    addButton.disabled = false;
    list.innerHTML = "";

    const entries = Array.isArray(currentCompanyPersonnelState) ? currentCompanyPersonnelState : [];
    if (entries.length === 0) {
        empty.classList.remove("d-none");
        return;
    }

    empty.classList.add("d-none");
    entries.forEach((entry, entryIndex) => {
        const cardEl = document.createElement("section");
        cardEl.className = "company-personnel-entry";
        cardEl.dataset.personnelIndex = String(entryIndex);

        const header = document.createElement("div");
        header.className = "company-personnel-entry-header";

        const titleWrap = document.createElement("div");
        const title = document.createElement("h3");
        title.textContent = Number(entry?.idCharacter || 0) > 0
            ? getCompanyPersonnelCharacterLabel(entry.idCharacter)
            : `Personeelslid ${entryIndex + 1}`;
        titleWrap.appendChild(title);

        const professionLabel = Number(entry?.idCharacter || 0) > 0
            ? (entry?.professionLabel || getCompanyPersonnelCharacterProfessionLabel(entry.idCharacter))
            : "";
        if (professionLabel) {
            const subtitle = document.createElement("p");
            subtitle.className = "company-personnel-entry-subtitle";
            subtitle.textContent = professionLabel;
            titleWrap.appendChild(subtitle);
        }
        header.appendChild(titleWrap);

        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "btn btn-outline-danger btn-sm";
        removeButton.dataset.action = "remove-company-personnel";
        removeButton.dataset.personnelIndex = String(entryIndex);
        removeButton.textContent = "Verwijderen";
        header.appendChild(removeButton);

        cardEl.appendChild(header);

        const fieldsRow = document.createElement("div");
        fieldsRow.className = "row g-3";

        const characterCol = document.createElement("div");
        characterCol.className = "col-12";

        const characterSelect = document.createElement("select");
        characterSelect.className = "form-select";
        characterSelect.dataset.companyPersonnelField = "idCharacter";
        characterSelect.dataset.personnelIndex = String(entryIndex);
        const characterPlaceholder = document.createElement("option");
        characterPlaceholder.value = "0";
        characterPlaceholder.textContent = "Kies een personage";
        characterSelect.appendChild(characterPlaceholder);

        getAvailableCharacterOptionsForPersonnelEntry(entryIndex).forEach((option) => {
            const optionEl = document.createElement("option");
            optionEl.value = String(option.idCharacter || 0);
            optionEl.textContent = option.displayName || `Personage #${option.idCharacter || 0}`;
            optionEl.selected = Number(entry?.idCharacter || 0) === Number(option.idCharacter || 0);
            characterSelect.appendChild(optionEl);
        });
        characterCol.appendChild(characterSelect);
        fieldsRow.appendChild(characterCol);

        const importanceCol = document.createElement("div");
        importanceCol.className = "col-12 col-md-6";
        const importanceLabel = document.createElement("label");
        importanceLabel.className = "form-label";
        importanceLabel.textContent = "Importance";
        importanceCol.appendChild(importanceLabel);

        const importanceSelect = document.createElement("select");
        importanceSelect.className = "form-select";
        importanceSelect.dataset.companyPersonnelField = "importance";
        importanceSelect.dataset.personnelIndex = String(entryIndex);
        getCompanyPersonnelImportanceOptions().forEach((importance) => {
            const optionEl = document.createElement("option");
            optionEl.value = importance;
            optionEl.textContent = importance;
            optionEl.selected = String(entry?.importance || "Moderate") === importance;
            importanceSelect.appendChild(optionEl);
        });
        importanceCol.appendChild(importanceSelect);
        fieldsRow.appendChild(importanceCol);

        const salaryCol = document.createElement("div");
        salaryCol.className = "col-12 col-md-6";
        const salaryLabel = document.createElement("label");
        salaryLabel.className = "form-label";
        salaryLabel.textContent = "Loonsverhoging (%)";
        salaryCol.appendChild(salaryLabel);

        const salaryInput = document.createElement("input");
        salaryInput.type = "number";
        salaryInput.className = "form-control";
        salaryInput.min = "0";
        salaryInput.step = "0.01";
        salaryInput.value = Number(entry?.salaryIncreasePercentage || 0).toFixed(2);
        salaryInput.dataset.companyPersonnelField = "salaryIncreasePercentage";
        salaryInput.dataset.personnelIndex = String(entryIndex);
        salaryCol.appendChild(salaryInput);
        fieldsRow.appendChild(salaryCol);

        cardEl.appendChild(fieldsRow);

        const skills = Array.isArray(entry?.skills) ? entry.skills : [];
        if (skills.length === 0) {
            entry.skills = [createEmptyCompanyPersonnelSkill()];
        }

        const skillEntry = entry.skills[0];
        const skillSection = document.createElement("div");
        skillSection.className = "company-personnel-skills-section";

        const skillRow = document.createElement("div");
        skillRow.className = "row g-3";

        const skillSelectCol = document.createElement("div");
        skillSelectCol.className = "col-12 col-md-8";
        const skillSelectLabel = document.createElement("label");
        skillSelectLabel.className = "form-label";
        skillSelectLabel.textContent = "Vaardigheid";
        skillSelectCol.appendChild(skillSelectLabel);

        const skillSelect = document.createElement("select");
        skillSelect.className = "form-select";
        skillSelect.dataset.companyPersonnelField = "skillId";
        skillSelect.dataset.personnelIndex = String(entryIndex);
        skillSelect.dataset.skillIndex = "0";
        const skillPlaceholder = document.createElement("option");
        skillPlaceholder.value = "0";
        skillPlaceholder.textContent = "Kies een vaardigheid";
        skillSelect.appendChild(skillPlaceholder);
        getAvailableSkillOptionsForPersonnelEntry(entry, 0).forEach((option) => {
            const optionEl = document.createElement("option");
            optionEl.value = String(option.idSkill || 0);
            optionEl.textContent = option.name || `Vaardigheid #${option.idSkill || 0}`;
            optionEl.selected = Number(skillEntry?.idSkill || 0) === Number(option.idSkill || 0);
            skillSelect.appendChild(optionEl);
        });
        skillSelectCol.appendChild(skillSelect);
        skillRow.appendChild(skillSelectCol);

        const levelCol = document.createElement("div");
        levelCol.className = "col-12 col-md-4";
        const levelLabel = document.createElement("label");
        levelLabel.className = "form-label";
        levelLabel.textContent = "Bekwaamheid";
        levelCol.appendChild(levelLabel);

        const levelSelect = document.createElement("select");
        levelSelect.className = "form-select";
        levelSelect.dataset.companyPersonnelField = "skillLevel";
        levelSelect.dataset.personnelIndex = String(entryIndex);
        levelSelect.dataset.skillIndex = "0";
        [1, 2, 3].forEach((level) => {
            const optionEl = document.createElement("option");
            optionEl.value = String(level);
            optionEl.textContent = formatCompanyPersonnelSkillLevelLabel(level);
            optionEl.selected = Number(skillEntry?.level || 1) === level;
            levelSelect.appendChild(optionEl);
        });
        levelCol.appendChild(levelSelect);
        skillRow.appendChild(levelCol);
        skillSection.appendChild(skillRow);

        const specialisationsSection = document.createElement("div");
        specialisationsSection.className = "company-personnel-specialisations";
        const specialisationsHeader = document.createElement("div");
        specialisationsHeader.className = "company-personnel-skills-header";
        const specialisationsLabel = document.createElement("div");
        specialisationsLabel.className = "company-personnel-specialisations-label";
        specialisationsLabel.textContent = "Specialisaties";
        specialisationsHeader.appendChild(specialisationsLabel);

        const addSpecialisationButton = document.createElement("button");
        addSpecialisationButton.type = "button";
        addSpecialisationButton.className = "btn btn-outline-secondary btn-sm";
        addSpecialisationButton.dataset.action = "open-company-personnel-specialisation-modal";
        addSpecialisationButton.dataset.personnelIndex = String(entryIndex);
        addSpecialisationButton.dataset.skillIndex = "0";
        addSpecialisationButton.textContent = "Specialisatie toevoegen";
        addSpecialisationButton.disabled = Number(skillEntry?.idSkill || 0) <= 0;
        specialisationsHeader.appendChild(addSpecialisationButton);
        specialisationsSection.appendChild(specialisationsHeader);

        const specialisationsList = document.createElement("div");
        specialisationsList.className = "company-personnel-specialisations-list";
        const selectedSpecialisations = Array.isArray(skillEntry?.specialisations) ? skillEntry.specialisations : [];
        if (selectedSpecialisations.length === 0) {
            const noSpecialisations = document.createElement("span");
            noSpecialisations.className = "company-personnel-specialisation-empty";
            noSpecialisations.textContent = "Nog geen specialisaties.";
            specialisationsList.appendChild(noSpecialisations);
        } else {
            selectedSpecialisations.forEach((specialisation, specialisationIndex) => {
                const badge = document.createElement("div");
                badge.className = "company-personnel-specialisation-chip";

                const text = document.createElement("span");
                text.textContent = specialisation?.name || `Specialisatie #${specialisation?.idSkillSpecialisation || 0}`;
                badge.appendChild(text);

                const deleteButton = document.createElement("button");
                deleteButton.type = "button";
                deleteButton.className = "btn btn-sm btn-link";
                deleteButton.dataset.action = "remove-company-personnel-specialisation";
                deleteButton.dataset.personnelIndex = String(entryIndex);
                deleteButton.dataset.skillIndex = "0";
                deleteButton.dataset.specialisationIndex = String(specialisationIndex);
                deleteButton.textContent = "x";
                badge.appendChild(deleteButton);

                specialisationsList.appendChild(badge);
            });
        }
        specialisationsSection.appendChild(specialisationsList);
        skillSection.appendChild(specialisationsSection);
        cardEl.appendChild(skillSection);
        list.appendChild(cardEl);
    });
}

function handleCompanyPersonnelCardChange(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    const field = target.dataset.companyPersonnelField;
    if (!field) {
        return;
    }

    const personnelIndex = Number(target.dataset.personnelIndex || -1);
    if (personnelIndex < 0 || !currentCompanyPersonnelState[personnelIndex]) {
        return;
    }

    const entry = currentCompanyPersonnelState[personnelIndex];

    if (field === "idCharacter") {
        entry.idCharacter = Math.max(0, Number(target.value || 0));
        renderCompanyPersonnel(currentCompanyData);
    } else if (field === "importance") {
        entry.importance = String(target.value || "Moderate");
    } else if (field === "salaryIncreasePercentage") {
        const parsed = Number(String(target.value || "").replace(",", "."));
        entry.salaryIncreasePercentage = Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    } else if (field === "skillId") {
        const skillIndex = Number(target.dataset.skillIndex || -1);
        if (skillIndex < 0 || !entry.skills?.[skillIndex]) {
            return;
        }

        entry.skills[skillIndex].idSkill = Math.max(0, Number(target.value || 0));
        entry.skills[skillIndex].specialisations = [];
        renderCompanyPersonnel(currentCompanyData);
    } else if (field === "skillLevel") {
        const skillIndex = Number(target.dataset.skillIndex || -1);
        if (skillIndex < 0 || !entry.skills?.[skillIndex]) {
            return;
        }

        entry.skills[skillIndex].level = Math.max(1, Math.min(3, Number(target.value || 1)));
    }

    markCompanyPersonnelSavePending();
    void flushCompanyAutoSave();
}

function handleCompanyPersonnelCardClick(event) {
    const button = event.target instanceof HTMLElement ? event.target.closest("button") : null;
    if (!button) {
        return;
    }

    const action = button.dataset.action;
    if (!action) {
        return;
    }

    const personnelIndex = Number(button.dataset.personnelIndex || -1);
    const skillIndex = Number(button.dataset.skillIndex || -1);
    const specialisationIndex = Number(button.dataset.specialisationIndex || -1);

    if (action === "add-company-personnel") {
        currentCompanyPersonnelState.push(createEmptyCompanyPersonnelEntry());
        renderCompanyPersonnel(currentCompanyData);
        markCompanyPersonnelSavePending();
        return;
    }

    if (action === "remove-company-personnel") {
        if (personnelIndex < 0) return;
        currentCompanyPersonnelState.splice(personnelIndex, 1);
        renderCompanyPersonnel(currentCompanyData);
        markCompanyPersonnelSavePending();
        void flushCompanyAutoSave();
        return;
    }

    if (personnelIndex < 0 || !currentCompanyPersonnelState[personnelIndex]) {
        return;
    }

    const entry = currentCompanyPersonnelState[personnelIndex];

    if (skillIndex < 0 || !entry.skills?.[skillIndex]) {
        return;
    }

    const skillEntry = entry.skills[skillIndex];

    if (action === "remove-company-personnel-specialisation") {
        if (specialisationIndex < 0 || !skillEntry.specialisations?.[specialisationIndex]) return;
        skillEntry.specialisations.splice(specialisationIndex, 1);
        renderCompanyPersonnel(currentCompanyData);
        markCompanyPersonnelSavePending();
        void flushCompanyAutoSave();
        return;
    }

    if (action === "open-company-personnel-specialisation-modal") {
        openCompanyPersonnelSpecialisationModal(personnelIndex, skillIndex);
        return;
    }
}

function markCompanyPersonnelSavePending() {
    if (isPopulatingCompanyForm) {
        return;
    }

    companyPersonnelSavePending = true;
}

function openCompanyPersonnelSpecialisationModal(personnelIndex, skillIndex) {
    const modalEl = document.getElementById("companyPersonnelSpecialisationModal");
    const select = document.getElementById("companyPersonnelSpecSelect");
    const customInput = document.getElementById("companyPersonnelSpecCustom");
    const existingTab = document.getElementById("company-personnel-spec-existing-tab");
    const newTab = document.getElementById("company-personnel-spec-new-tab");
    const existingPane = document.getElementById("company-personnel-spec-existing-pane");
    const newPane = document.getElementById("company-personnel-spec-new-pane");

    if (!modalEl || !select || !customInput || !existingTab || !newTab || !existingPane || !newPane) {
        return;
    }

    const entry = currentCompanyPersonnelState?.[personnelIndex];
    const skillEntry = entry?.skills?.[skillIndex];
    if (!skillEntry || Number(skillEntry.idSkill || 0) <= 0) {
        alert("Kies eerst een vaardigheid.");
        return;
    }

    modalEl.dataset.personnelIndex = String(personnelIndex);
    modalEl.dataset.skillIndex = String(skillIndex);
    select.innerHTML = "";
    customInput.value = "";

    const options = getAvailableSpecialisationOptionsForPersonnelSkill(skillEntry);
    const placeholder = document.createElement("option");
    placeholder.value = "0";
    placeholder.textContent = "Kies bestaande specialisatie";
    select.appendChild(placeholder);
    options.forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = String(option.idSkillSpecialisation || 0);
        optionEl.textContent = option.name || `Specialisatie #${option.idSkillSpecialisation || 0}`;
        select.appendChild(optionEl);
    });

    existingTab.classList.add("active");
    existingTab.setAttribute("aria-selected", "true");
    existingPane.classList.add("show", "active");
    newTab.classList.remove("active");
    newTab.setAttribute("aria-selected", "false");
    newPane.classList.remove("show", "active");

    if (options.length === 0) {
        existingTab.classList.remove("active");
        existingTab.setAttribute("aria-selected", "false");
        existingPane.classList.remove("show", "active");
        newTab.classList.add("active");
        newTab.setAttribute("aria-selected", "true");
        newPane.classList.add("show", "active");
    }

    if (!companyPersonnelSpecialisationModalInstance) {
        companyPersonnelSpecialisationModalInstance = new bootstrap.Modal(modalEl);
    }

    companyPersonnelSpecialisationModalInstance.show();
}

function saveCompanyPersonnelSpecialisationFromModal() {
    const modalEl = document.getElementById("companyPersonnelSpecialisationModal");
    const select = document.getElementById("companyPersonnelSpecSelect");
    const customInput = document.getElementById("companyPersonnelSpecCustom");
    const activeTab = document.querySelector("#companyPersonnelSpecTab .nav-link.active")?.dataset.tab;

    if (!modalEl || !select || !customInput) {
        return;
    }

    const personnelIndex = Number(modalEl.dataset.personnelIndex || -1);
    const skillIndex = Number(modalEl.dataset.skillIndex || -1);
    const skillEntry = currentCompanyPersonnelState?.[personnelIndex]?.skills?.[skillIndex];
    if (!skillEntry) {
        return;
    }

    skillEntry.specialisations = Array.isArray(skillEntry.specialisations) ? skillEntry.specialisations : [];

    if (activeTab === "existing") {
        const idSkillSpecialisation = Number(select.value || 0);
        if (idSkillSpecialisation <= 0) {
            alert("Kies een bestaande specialisatie of ga naar 'New'.");
            return;
        }

        const skillOption = findCompanyPersonnelSkillOption(skillEntry.idSkill);
        const specialisationOption = Array.isArray(skillOption?.specialisations)
            ? skillOption.specialisations.find((specialisation) => Number(specialisation?.idSkillSpecialisation || 0) === idSkillSpecialisation)
            : null;

        if (!specialisationOption) {
            alert("De gekozen specialisatie is niet beschikbaar voor deze vaardigheid.");
            return;
        }

        skillEntry.specialisations.push({
            idSkillSpecialisation,
            name: specialisationOption.name || "",
            kind: specialisationOption.kind || "specialisation"
        });
    } else {
        const name = String(customInput.value || "").trim();
        if (!name) {
            alert("Vul een naam in voor de nieuwe specialisatie.");
            return;
        }

        skillEntry.specialisations.push({
            idSkillSpecialisation: 0,
            name,
            kind: "specialisation"
        });
    }

    renderCompanyPersonnel(currentCompanyData);
    markCompanyPersonnelSavePending();
    void flushCompanyAutoSave();
    companyPersonnelSpecialisationModalInstance?.hide();
}

async function createCompanySnapshot() {
    const idCompany = Number(document.getElementById("companyId")?.value || 0);
    const idEvent = Number(document.getElementById("companySnapshotEventSelect")?.value || 0);
    if (idCompany <= 0) {
        showCompanyFeedback("Kies eerst een bedrijf.", "danger");
        return;
    }

    if (idEvent <= 0) {
        showCompanyFeedback("Kies eerst een event voor de snapshot.", "danger");
        return;
    }

    const canContinue = await flushCompanyAutoSave({ force: false });
    if (canContinue === false) {
        return;
    }

    try {
        const result = await apiFetchJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "create",
                idCompany,
                idEvent,
                stability: normalizeSliderValue(document.getElementById("companyStability")?.value),
                profitability: normalizeSliderValue(document.getElementById("companyProfitability")?.value)
            }
        });

        applyCompanySnapshotResponse(result, idCompany);
        showCompanyFeedback("Snapshot opgeslagen.", "success");
    } catch (err) {
        console.error("Fout bij maken snapshot:", err);
        showCompanyFeedback(err?.message || "Kon de snapshot niet bewaren.", "danger");
    }
}

function handleCompanySnapshotsCardClick(event) {
    const button = event.target instanceof HTMLElement ? event.target.closest("button") : null;
    if (!button) {
        return;
    }

    const action = button.dataset.action;
    if (!action) {
        return;
    }

    const idCompanySnapshot = Number(button.dataset.idCompanySnapshot || 0);
    if (idCompanySnapshot <= 0) {
        return;
    }

    if (action === "delete-company-snapshot") {
        void deleteCompanySnapshot(idCompanySnapshot);
        return;
    }

    if (action === "recalculate-company-snapshot") {
        void recalculateCompanySnapshot(idCompanySnapshot);
        return;
    }

    if (action === "apply-company-snapshot") {
        const applyAction = String(button.dataset.applyAction || "");
        if (applyAction) {
            void applyCompanySnapshot(idCompanySnapshot, applyAction);
        }
    }
}

function handleCompanySnapshotsCardChange(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    if (!target.dataset.companySnapshotField) {
        return;
    }

    const idCompanySnapshot = Number(target.dataset.idCompanySnapshot || 0);
    if (idCompanySnapshot <= 0) {
        return;
    }

    void updateCompanySnapshot(idCompanySnapshot);
}

async function updateCompanySnapshot(idCompanySnapshot) {
    const idCompany = Number(document.getElementById("companyId")?.value || 0);
    const stabilityInput = document.querySelector(`[data-company-snapshot-field="stability"][data-id-company-snapshot="${idCompanySnapshot}"]`);
    const profitabilityInput = document.querySelector(`[data-company-snapshot-field="profitability"][data-id-company-snapshot="${idCompanySnapshot}"]`);
    if (idCompany <= 0 || !(stabilityInput instanceof HTMLInputElement) || !(profitabilityInput instanceof HTMLInputElement)) {
        return;
    }

    const canContinue = await flushCompanyAutoSave({ force: false });
    if (canContinue === false) {
        return;
    }

    try {
        const result = await apiFetchJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "update",
                idCompany,
                idCompanySnapshot,
                stability: normalizeSliderValue(stabilityInput.value),
                profitability: normalizeSliderValue(profitabilityInput.value)
            }
        });

        applyCompanySnapshotResponse(result, idCompany);
        showCompanyFeedback("Snapshot bijgewerkt.", "success");
    } catch (err) {
        console.error("Fout bij bijwerken snapshot:", err);
        showCompanyFeedback(err?.message || "Kon de snapshot niet bewaren.", "danger");
    }
}

async function recalculateCompanySnapshot(idCompanySnapshot) {
    const idCompany = Number(document.getElementById("companyId")?.value || 0);
    if (idCompany <= 0) {
        return;
    }

    const canContinue = await flushCompanyAutoSave({ force: false });
    if (canContinue === false) {
        return;
    }

    try {
        const result = await apiFetchJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "recalculate",
                idCompany,
                idCompanySnapshot
            }
        });

        applyCompanySnapshotResponse(result, idCompany);
        showCompanyFeedback("Snapshot herberekend.", "success");
    } catch (err) {
        console.error("Fout bij herberekenen snapshot:", err);
        showCompanyFeedback(err?.message || "Kon de snapshot niet herberekenen.", "danger");
    }
}

async function deleteCompanySnapshot(idCompanySnapshot) {
    const idCompany = Number(document.getElementById("companyId")?.value || 0);
    if (idCompany <= 0) {
        return;
    }

    const canContinue = await flushCompanyAutoSave({ force: false });
    if (canContinue === false) {
        return;
    }

    try {
        const result = await apiFetchJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "delete",
                idCompany,
                idCompanySnapshot
            }
        });

        applyCompanySnapshotResponse(result, idCompany);
        showCompanyFeedback("Snapshot verwijderd.", "success");
    } catch (err) {
        console.error("Fout bij verwijderen snapshot:", err);
        showCompanyFeedback(err?.message || "Kon de snapshot niet verwijderen.", "danger");
    }
}

async function applyCompanySnapshot(idCompanySnapshot, applyAction) {
    const idCompany = Number(document.getElementById("companyId")?.value || 0);
    if (idCompany <= 0) {
        return;
    }

    const canContinue = await flushCompanyAutoSave({ force: false });
    if (canContinue === false) {
        return;
    }

    try {
        const result = await apiFetchJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "apply",
                applyAction,
                idCompany,
                idCompanySnapshot
            }
        });

        applyCompanySnapshotResponse(result, idCompany);
        showCompanyFeedback("Snapshot toegepast.", "success");
    } catch (err) {
        console.error("Fout bij toepassen snapshot:", err);
        showCompanyFeedback(err?.message || "Kon de snapshot niet toepassen.", "danger");
    }
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

async function saveCompanyPersonnel(options = {}) {
    if (saveCompanyPersonnelRequestInFlight) {
        saveCompanyPersonnelQueued = true;
        return false;
    }

    const task = (async () => {
        saveCompanyPersonnelRequestInFlight = true;

        try {
            const {
                successMessage = "Personeel opgeslagen.",
                silentWhenUnavailable = false
            } = options;
            const idCompany = Number(document.getElementById("companyId")?.value || 0);
            if (idCompany <= 0) {
                if (!silentWhenUnavailable) {
                    showCompanyFeedback("Kies eerst een bedrijf.", "danger");
                }
                return true;
            }

            const result = await apiFetchJson("api/companies/saveCompanyPersonnel.php", {
                method: "POST",
                body: {
                    idCompany,
                    personnel: currentCompanyPersonnelState
                }
            });

            currentCompanyPersonnelState = cloneCompanyPersonnelEntries(result?.personnelEntries);
            if (currentCompanyData && Number(currentCompanyData.id || 0) === idCompany) {
                currentCompanyData.personnelEntries = cloneCompanyPersonnelEntries(result?.personnelEntries);
                currentCompanyData.snapshots = Array.isArray(result?.snapshots) ? result.snapshots : currentCompanyData.snapshots;
            }
            renderCompanyPersonnel(currentCompanyData);
            renderCompanySnapshots(currentCompanyData);
            showCompanyFeedback(successMessage, "success");
            return true;
        } catch (err) {
            console.error("Fout bij bewaren bedrijfspersoneel:", err);
            showCompanyFeedback(err?.message || "Kon het bedrijfspersoneel niet bewaren.", "danger");
            return false;
        } finally {
            saveCompanyPersonnelRequestInFlight = false;
        }
    })();

    saveCompanyPersonnelInFlightPromise = task;

    try {
        return await task;
    } finally {
        if (saveCompanyPersonnelInFlightPromise === task) {
            saveCompanyPersonnelInFlightPromise = null;
        }

        if (saveCompanyPersonnelQueued) {
            saveCompanyPersonnelQueued = false;
            companyPersonnelSavePending = true;
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
