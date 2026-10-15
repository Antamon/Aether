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
let companySnapshotEventOptions = null;
let companySnapshotEventLoadError = false;
let companyPersonnelDraft = null;
let companyPersonnelDraftIndex = -1;

window.addEventListener("load", () => {
    initCompanyPage();
});

async function initCompanyPage() {
    try {
        const currentUser = await aetherGetCurrentUser();
        if (!currentUser || !userHasPrivilegedRole(currentUser)) {
            window.location.href = "index.html";
            return;
        }

        window.AETHER_CURRENT_USER = currentUser;
        syncPrivilegedNavbar(currentUser);
        setupCompanyPageListeners();
        const params = new URLSearchParams(window.location.search);
        const selectedId = params.get("company");
        await Promise.all([
            loadCompanySnapshotEventOptions(),
            loadCompanyList(selectedId && /^[1-9]\d*$/.test(selectedId) ? Number(selectedId) : 0)
        ]);
        if (currentCompanyData?.id) renderCompanySnapshots(currentCompanyData);
        if (params.get("new") === "1") startCreatingCompany();
    } catch (err) {
        console.error("Fout bij initialiseren bedrijvenpagina:", err);
        window.location.href = "index.html";
    }
}

async function loadCompanySnapshotEventOptions() {
    try {
        const events = await apiFetchJson("api/events/getEventList.php", {
            method: "POST",
            body: {}
        });
        if (!Array.isArray(events)) throw new Error("Ongeldige eventlijst ontvangen.");
        companySnapshotEventOptions = events.map((event) => ({
            idEvent: Number(event.id || 0),
            title: String(event.title || ""),
            dateStart: String(event.dateStart || ""),
            dateEnd: String(event.dateEnd || "")
        })).filter((event) => event.idEvent > 0);
        companySnapshotEventLoadError = false;
    } catch (error) {
        console.error("Eventkeuze voor bedrijfssnapshots laden mislukt:", error);
        companySnapshotEventLoadError = true;
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

    for (const [id, type] of [["companyStability", "stability"], ["companyProfitability", "profitability"]]) {
        const input = document.getElementById(id);
        if (!input) continue;
        input.addEventListener("input", () => updateSliderPresentation(type));
        input.addEventListener("change", () => { void saveCompanyCurrentSliders(); });
    }

    const newCompanyButton = document.getElementById("newCompanyButton");
    if (newCompanyButton) {
        newCompanyButton.addEventListener("click", async () => {
            if (aetherDataCard.hasActive()) {
                showCompanyFeedback("Bewaar of annuleer eerst de geopende kaart.", "danger");
                return;
            }
            if (!isCreatingCompany) {
                const canContinue = await flushCompanyAutoSave({ force: false });
                if (!canContinue) {
                    return;
                }
            }
            startCreatingCompany();
            window.closeCompanySidebar?.();
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

    ["companyDescription", "companyFoundationDate"].forEach((fieldId) => {
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
            if (fieldId === "companyValue") {
                setCompanyCurrencyInputDisplayValue(input);
                updateCompanyTypePresentation();
            }
            void flushCompanyAutoSave();
        });
    });

    document.addEventListener("focusin", (event) => {
        const currencyInput = event.target.closest("#companyValue[data-currency-input='true']");
        if (!currencyInput || currencyInput.disabled) return;

        setCompanyCurrencyInputEditingValue(currencyInput);
    });

    document.addEventListener("focusout", (event) => {
        const currencyInput = event.target.closest("#companyValue[data-currency-input='true']");
        if (!currencyInput) return;

        setCompanyCurrencyInputDisplayValue(currencyInput);
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
        companyPersonnelCard.addEventListener("input", (event) => {
            if (["salaryIncreasePercentage", "functionDescription"].includes(
                event.target?.dataset?.companyPersonnelField)) {
                handleCompanyPersonnelCardChange(event);
            }
        });
    }

    const companySnapshotsCard = document.getElementById("companySnapshotsCard");
    if (companySnapshotsCard) {
        companySnapshotsCard.addEventListener("click", handleCompanySnapshotsCardClick);
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

    const companyTypeInfoButton = document.getElementById("companyTypeInfoButton");
    if (companyTypeInfoButton) {
        companyTypeInfoButton.addEventListener("keydown", (event) => {
            if (event.key !== "Enter" && event.key !== " ") return;
            event.preventDefault();
            bootstrap.Modal.getOrCreateInstance(document.getElementById("companyTypeInfoModal")).show();
        });
    }
}

async function loadCompanyList(selectedCompanyId = 0) {
    try {
        const list = await apiFetchJson("api/companies/getCompanyList.php");
        companies = Array.isArray(list) ? list : [];

        renderCompanyList();

        if (companies.length === 0 || Number(selectedCompanyId) <= 0) {
            currentCompanyId = 0;
            currentCompanyData = null;
            currentCompanyPersonnelState = [];
            setCompanyFormState(false);
            populateCompanyForm(null);
            renderCompanyList();
            if (companies.length === 0) {
                showCompanyFeedback("Nog geen bedrijven beschikbaar.", "info");
            } else {
                hideCompanyFeedback();
            }
            return;
        }

        hideCompanyFeedback();
        await loadCompany(Number(selectedCompanyId));
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
    window.renderCompanySidebarCards(companies, listEl, async (company) => {
            if (aetherDataCard.hasActive()) {
                showCompanyFeedback("Bewaar of annuleer eerst de geopende kaart.", "danger");
                return;
            }
            if (!isCreatingCompany) {
                const canContinue = await flushCompanyAutoSave({ force: false });
                if (!canContinue) {
                    return;
                }
            }
            isCreatingCompany = false;
            if (await loadCompany(Number(company.id || 0))) {
                window.closeCompanySidebar?.();
            }
    }, currentCompanyId);
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

        if (currentCompanyId !== Number(company?.id || 0)) {
            aetherDataCard.reset();
            companyPersonnelDraft = null;
            companyPersonnelDraftIndex = -1;
        }
        currentCompanyId = Number(company?.id || 0);
        currentCompanyData = company || null;
        setCompanyFormState(currentCompanyId > 0);
        populateCompanyForm(company);
        renderCompanyList();
        return true;
    } catch (err) {
        console.error("Fout bij laden bedrijf:", err);
        showCompanyFeedback("Kon het geselecteerde bedrijf niet laden.", "danger");
        return false;
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
    const showSliders = (source) => {
        if (stabilityInput) stabilityInput.value = String(normalizeSliderValue(source?.stability ?? 0));
        if (profitabilityInput) profitabilityInput.value = String(normalizeSliderValue(source?.profitability ?? 0));
        updateSliderPresentation("stability");
        updateSliderPresentation("profitability");
    };

    if (company?.isDraft) {
        currentCompanyData = company;
        currentCompanyPersonnelState = [];
        if (formTitle) formTitle.textContent = "Nieuw bedrijf";
        if (formSubtitle) formSubtitle.textContent = "Geef eerst een bedrijfsnaam op. Daarna wordt het bedrijf automatisch aangemaakt met standaardwaarden.";
        if (idInput) idInput.value = "";
        if (nameInput) nameInput.value = company.companyName || "";
        if (descriptionInput) descriptionInput.value = company.description || "";
        if (foundationDateInput) foundationDateInput.value = company.foundationDate || getDefaultFoundationDate();
        if (valueInput) valueInput.textContent = formatCompanySnapshotCurrency(company.companyValue || 0);
        showSliders(company);
        updateCompanyLogo(null, false);
        updateCompanyLogoActions(false, false);
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
        if (valueInput) valueInput.textContent = "—";
        showSliders(null);
        updateCompanyLogo(null, false);
        updateCompanyLogoActions(false, false);
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
    if (valueInput) valueInput.textContent = formatCompanySnapshotCurrency(company.displayCompanyValue ?? company.companyValue);
    showSliders(sortCompanySnapshotsByDate(company.snapshots).at(-1) || company.foundationSnapshot || company);
    updateCompanyLogo(company.logoUrl || null, Boolean(company.logoUrl));
    updateCompanyLogoActions(true, Boolean(company.logoUrl));

    updateCompanyTypePresentation();
    renderCompanyShareholders(company);
    renderCompanySnapshots(company);
    renderCompanyPersonnel(company);
    isPopulatingCompanyForm = false;
}

function setCompanyFormState(enabled, options = {}) {
    const form = document.getElementById("companyForm");
    if (!form) return;
    form.classList.toggle("d-none", !enabled);

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
        const createdCompany = await apiFetchIdempotentJson("api/companies/newCompany.php", {
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
            headers: {
                "X-CSRF-Token": window.AETHER_CSRF_TOKEN || ""
            },
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
        if (Number(currentCompanyData?.id) === id) currentCompanyData.logoUrl = result?.logoUrl || null;
        const listedCompany = companies.find((company) => Number(company.id) === id);
        if (listedCompany) listedCompany.logoUrl = result?.logoUrl || null;
        renderCompanyList();
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
        if (Number(currentCompanyData?.id) === id) currentCompanyData.logoUrl = null;
        const listedCompany = companies.find((company) => Number(company.id) === id);
        if (listedCompany) listedCompany.logoUrl = null;
        renderCompanyList();
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
    if (!Number.isFinite(parsed)) {
        return formatCompanyCurrencyValue(0, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + " Fr";
    }

    return `${formatCompanyCurrencyValue(parsed, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function parseCompanyValueInputValue(value) {
    let normalizedValue = String(value ?? "")
        .trim()
        .replace(/fr/gi, "")
        .replace(/\s+/g, "");
    if (!normalizedValue) {
        return null;
    }

    const hasComma = normalizedValue.includes(",");
    const hasDot = normalizedValue.includes(".");
    if (hasComma && hasDot) {
        if (normalizedValue.lastIndexOf(",") > normalizedValue.lastIndexOf(".")) {
            normalizedValue = normalizedValue.replace(/\./g, "").replace(",", ".");
        } else {
            normalizedValue = normalizedValue.replace(/,/g, "");
        }
    } else if (hasComma) {
        normalizedValue = normalizedValue.replace(/\./g, "").replace(",", ".");
    } else {
        const dotParts = normalizedValue.split(".");
        if (dotParts.length > 2) {
            normalizedValue = dotParts.join("");
        }
    }

    normalizedValue = normalizedValue.replace(/[^\d.-]/g, "");

    const parsed = Number(normalizedValue);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return null;
    }

    return parsed;
}

function setCompanyCurrencyInputEditingValue(input) {
    if (!input) return;

    const amount = parseCompanyValueInputValue(input.value);
    if (amount === null) {
        return;
    }

    input.value = amount.toFixed(2);
}

function setCompanyCurrencyInputDisplayValue(input) {
    if (!input) return;

    const amount = parseCompanyValueInputValue(input.value);
    if (amount === null) {
        return;
    }

    input.value = formatCompanyValueForInput(amount);
}

function getCompanyTypeDefinitionForValue(value) {
    const normalizedValue = Number(value);
    if (!Number.isFinite(normalizedValue) || normalizedValue < 0) {
        return null;
    }

    return COMPANY_TYPE_DEFINITIONS.find((type) => normalizedValue <= type.max) || null;
}

function formatCompanyCurrencyValue(value, options = {}) {
    const numericValue = Number(value ?? 0);
    const resolvedValue = Number.isFinite(numericValue) ? numericValue : 0;
    return new Intl.NumberFormat("fr-BE", options)
        .formatToParts(resolvedValue)
        .map((part) => (part.type === "group" ? " " : part.value))
        .join("");
}

function formatCompanyRangeCurrency(value) {
    return `${formatCompanyCurrencyValue(value, {
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
    const companyValue = Number(currentCompanyData?.displayCompanyValue ?? currentCompanyData?.companyValue ?? 0);
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
    if (!card) return;
    const listing = document.getElementById("companyShareListing");
    void aetherShareUi.attachCompany(card, company?.isDraft ? 0 : company?.id, company,
        async () => { await loadCompany(Number(company.id)); }, listing);
}

function formatCompanySnapshotEventOptionLabel(option) {
    const title = String(option?.title || "").trim();
    const dateStart = String(option?.dateStart || "").trim();
    return dateStart ? `${title} (${dateStart})` : title;
}

function formatCompanySnapshotCurrency(amount) {
    return `${formatCompanyCurrencyValue(amount, {
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

async function applyCompanySnapshotResponse(result, idCompany) {
    if (Number(currentCompanyId) === Number(idCompany)) {
        await loadCompany(idCompany);
    }
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

function appendSnapshotDatum(parent, label, value) {
    const row = document.createElement("div");
    row.className = "share-ui-holding";
    const name = document.createElement("span");
    name.textContent = label;
    const content = document.createElement("strong");
    content.textContent = value == null || value === "" ? "Onbekend" : String(value);
    row.append(name, content);
    parent.appendChild(row);
}

function appendSnapshotInput(parent, key, label, value, options = {}) {
    const wrap = document.createElement("label");
    wrap.className = "form-label d-block mt-2";
    wrap.textContent = label;
    const input = document.createElement("input");
    input.className = "form-control";
    input.type = "number";
    input.step = options.integer ? "1" : "0.01";
    input.min = options.min ?? "0";
    if (options.max !== undefined) input.max = String(options.max);
    input.value = value == null ? "" : String(value);
    input.dataset.snapshotEdit = key;
    wrap.appendChild(input);
    parent.appendChild(wrap);
    return input;
}

function renderFoundationSnapshot(list, company) {
    const foundation = company.foundationSnapshot;
    const item = document.createElement("section");
    item.className = "company-snapshot-item data-card";
    const cardKey = `company:${company.id}:foundation`;
    const editing = aetherDataCard.isEditing(cardKey);
    item.classList.toggle("is-editing", editing);
    const header = document.createElement("div");
    header.className = "company-snapshot-header";
    const titleWrap = document.createElement("div");
    const title = document.createElement("h3");
    title.className = "h5 mb-1";
    title.textContent = "Oprichting";
    const date = document.createElement("p");
    date.className = "company-snapshot-subtitle";
    date.textContent = foundation?.foundationDate || company.foundationDate || "Datum onbekend";
    titleWrap.append(title, date);
    header.appendChild(titleWrap);
    item.appendChild(header);
    const explanation = document.createElement("p");
    explanation.className = "company-snapshot-subtitle mb-2";
    explanation.textContent = "Startstand zonder winstberekening of dividend.";
    item.appendChild(explanation);
    if (!foundation) {
        appendSnapshotDatum(item, "Gegevens", "Oprichtingssnapshot beschikbaar na migratie 0012");
        list.appendChild(item);
        return;
    }
    const values = [
        ["initialCompanyValue", "Beginwaarde bedrijf", foundation.initialCompanyValue],
        ["initialShareCount", "Aandelen bij oprichting", foundation.initialShareCount],
        ["shareMarketPrice", "Initiële marktprijs per aandeel", foundation.shareMarketPrice],
        ["anonymousShareCount", "Aandelen in anonieme handen", foundation.anonymousShareCount],
        ["stability", "Begin stabiliteit", foundation.stability],
        ["profitability", "Begin rendabiliteit", foundation.profitability],
    ];
    const inputs = new Map();
    const grid = document.createElement("div");
    grid.className = editing ? "row g-2" : "data-card-facts";
    for (const [key, label, value] of values) {
        if (!editing) {
            aetherDataCard.fact(grid, label,
                value != null && ["initialCompanyValue", "shareMarketPrice"].includes(key)
                    ? formatCompanySnapshotCurrency(value) : value);
            continue;
        }
        const col = document.createElement("div");
        col.className = "col-12 col-xl-6";
        inputs.set(key, appendSnapshotInput(col, key, label, value, {
            integer: key.endsWith("Count") || key === "stability" || key === "profitability",
            min: key === "stability" || key === "profitability" ? -7 : key === "initialShareCount" ? 1 : 0,
            max: key === "stability" || key === "profitability" ? 7 : undefined,
        }));
        grid.appendChild(col);
    }
    item.appendChild(grid);
    aetherDataCard.controls(header, {
        key: cardKey,
        canEdit: true,
        saveLabel: "Bewaren",
        onEdit: () => { if (aetherDataCard.open(cardKey)) refreshCompanyDataCards(company); },
        onCancel: () => { aetherDataCard.close(cardKey); refreshCompanyDataCards(company); },
        onSave: async () => {
        const body = { idCompany: Number(company.id) };
        for (const [key, input] of inputs) {
            if (input.value.trim() === "" && key !== "initialCompanyValue" && key !== "initialShareCount") continue;
            const n = Number(input.value);
            if (input.value.trim() === "" || !Number.isFinite(n) || n < Number(input.min)
                || (input.max && n > Number(input.max)) || (input.step === "1" && !Number.isInteger(n))) {
                showCompanyFeedback(`Controleer ${input.parentElement?.firstChild?.textContent || key}.`, "danger");
                return;
            }
            body[key] = key.endsWith("Count") || key === "stability" || key === "profitability" ? n : n.toFixed(2);
        }
        try {
            await aetherShareUi.mutateCompany("setCompanyFoundation", body);
            aetherDataCard.close(cardKey);
            await loadCompany(Number(company.id));
            showCompanyFeedback("Oprichting bewaard.", "success");
        } catch (error) { showCompanyFeedback(aetherShareUi.errorMessage(error), "danger"); }
        }
    });
    if (foundation.sourceCode === "reconstructed") {
        appendSnapshotDatum(item, "Herkomst", "Gereconstrueerd uit de migratie; historische onbekende waarden blijven leeg");
    }
    list.appendChild(item);
}

function renderCompanySnapshots(company) {
    const card = document.getElementById("companySnapshotsCard");
    const eventSelect = document.getElementById("companySnapshotEventSelect");
    const list = document.getElementById("companySnapshotsList");
    const empty = document.getElementById("companySnapshotsEmpty");
    const createButton = document.getElementById("createCompanySnapshotButton");
    const eventStatus = document.getElementById("companySnapshotEventStatus");

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
        eventStatus?.classList.add("d-none");
        return;
    }

    card.classList.remove("d-none");
    createButton.disabled = false;

    const snapshots = sortCompanySnapshotsByDate(company?.snapshots);
    const usedEventIds = snapshots.map((snapshot) => Number(snapshot?.idEvent || 0)).filter((idEvent) => idEvent > 0);
    const embeddedOptions = Array.isArray(company?.snapshotEventOptions) ? company.snapshotEventOptions : [];
    const sourceOptions = companySnapshotEventOptions?.length ? companySnapshotEventOptions : embeddedOptions;
    const remainingEvents = sourceOptions.filter((option) =>
        !usedEventIds.includes(Number(option?.idEvent || 0)));

    eventSelect.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "0";
    placeholder.textContent = "Kies een event";
    eventSelect.appendChild(placeholder);
    remainingEvents.forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = String(option.idEvent || 0);
        optionEl.textContent = formatCompanySnapshotEventOptionLabel(option);
        eventSelect.appendChild(optionEl);
    });
    createButton.disabled = remainingEvents.length === 0 || aetherDataCard.hasActive();
    if (eventStatus) {
        eventStatus.classList.toggle("d-none", remainingEvents.length > 0);
        eventStatus.textContent = remainingEvents.length ? ""
            : sourceOptions.length ? "Elk beschikbaar event heeft al een snapshot."
            : companySnapshotEventLoadError ? "De eventlijst kon niet geladen worden. Vernieuw de pagina of controleer de event-API."
            : "Er zijn nog geen events beschikbaar.";
    }

    list.innerHTML = "";
    empty.classList.add("d-none");
    renderFoundationSnapshot(list, company);
    snapshots.forEach((snapshot) => {
        const appliedAction = String(snapshot?.appliedAction || "none");
        const isApplied = appliedAction !== "" && appliedAction !== "none";
        const item = document.createElement("section");
        item.className = "company-snapshot-item data-card";
        item.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        const cardKey = `company:${company.id}:snapshot:${snapshot.idCompanySnapshot}`;
        const editing = aetherDataCard.isEditing(cardKey);
        item.classList.toggle("is-editing", editing);

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
        item.appendChild(header);

        if (editing) {
        const buttonGroup = document.createElement("div");
        buttonGroup.className = "company-snapshot-actions button-group";

        const recalculateButton = AetherActionButtons.button({ icon: "fa-rotate", label: "Herbereken" });
        recalculateButton.dataset.action = "recalculate-company-snapshot";
        recalculateButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        recalculateButton.disabled = isApplied;
        buttonGroup.appendChild(recalculateButton);

        const deleteButton = AetherActionButtons.button({ icon: "fa-trash", label: "Verwijderen" });
        deleteButton.dataset.action = "delete-company-snapshot";
        deleteButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
        buttonGroup.appendChild(deleteButton);
        header.appendChild(buttonGroup);

        const idSnapshot = Number(snapshot.idCompanySnapshot || 0);
        const snapshotInputs = new Map();
        const valueFields = document.createElement("div");
        valueFields.className = "row g-2";
        item.appendChild(valueFields);
        const saveSnapshotField = async (body, button) => {
            button.disabled = true;
            try {
                await apiFetchFinancialJson("api/companies/saveCompanySnapshot.php", {
                    method: "POST",
                    body: { action: "update", idCompany: Number(company.id), idCompanySnapshot: idSnapshot, ...body }
                });
                aetherDataCard.close(cardKey);
                await loadCompany(Number(company.id));
                showCompanyFeedback("Snapshot bijgewerkt.", "success");
            } catch (error) { showCompanyFeedback(error?.message || "Kon de snapshot niet bewaren.", "danger"); button.disabled = false; }
        };
        const addEditableSnapshotValue = (key, label, value, options = {}) => {
            const col = document.createElement("div");
            col.className = "col-12 col-xl-6";
            const field = appendSnapshotInput(col, key, label, value, options);
            valueFields.appendChild(col);
            snapshotInputs.set(key, { field, label, integer: Boolean(options.integer) });
        };
        addEditableSnapshotValue("companyValue", "Bedrijfswaarde", snapshot.companyValue);
        addEditableSnapshotValue("issuedShareCount", "Uitgegeven aandelen", snapshot.issuedShareCount,
            { integer: true, min: 1 });
        addEditableSnapshotValue("anonymousShareCount", "Aandelen in anonieme handen", snapshot.anonymousShareCount,
            { integer: true });
        addEditableSnapshotValue("shareMarketPrice", "Marktprijs per aandeel", snapshot.shareMarketPrice);

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
        profitabilityRow.appendChild(profitabilityInput);
        profitabilityCol.appendChild(profitabilityRow);
        fields.appendChild(profitabilityCol);

        item.appendChild(fields);

        const saveSnapshot = async (button) => {
            const stability = Number(stabilityInput.value);
            const profitability = Number(profitabilityInput.value);
            if (![stability, profitability].every((value) => Number.isInteger(value) && value >= -7 && value <= 7)) {
                showCompanyFeedback("Controleer stabiliteit en rendabiliteit.", "danger");
                return;
            }
            const body = {};
            if (stability !== Number(snapshot.stability)) body.stability = stability;
            if (profitability !== Number(snapshot.profitability)) body.profitability = profitability;
            for (const [key, { field, label, integer }] of snapshotInputs) {
                if (field.value.trim() === "") continue;
                const n = Number(field.value);
                if (!Number.isFinite(n) || n < Number(field.min) || (integer && !Number.isInteger(n))) {
                    showCompanyFeedback(`Controleer ${label.toLowerCase()}.`, "danger");
                    return;
                }
                if (snapshot[key] == null || (integer ? n !== Number(snapshot[key])
                    : n.toFixed(2) !== Number(snapshot[key]).toFixed(2))) {
                    body[key] = integer ? n : n.toFixed(2);
                }
            }
            if (!Object.keys(body).length) {
                aetherDataCard.close(cardKey);
                refreshCompanyDataCards(company);
                return;
            }
            await saveSnapshotField(body, button);
        };
        aetherDataCard.controls(header, {
            key: cardKey,
            canEdit: true,
            onCancel: () => { aetherDataCard.close(cardKey); refreshCompanyDataCards(company); },
            onSave: saveSnapshot
        });
        } else {
            const facts = document.createElement("div");
            facts.className = "data-card-facts";
            for (const [label, value] of [
                ["Bedrijfswaarde", snapshot.companyValue == null ? null : formatCompanySnapshotCurrency(snapshot.companyValue)],
                ["Uitgegeven aandelen", snapshot.issuedShareCount],
                ["Aandelen in anonieme handen", snapshot.anonymousShareCount],
                ["Marktprijs per aandeel", snapshot.shareMarketPrice == null ? null : formatCompanySnapshotCurrency(snapshot.shareMarketPrice)],
                ["Stabiliteit", snapshot.stability],
                ["Rendabiliteit", snapshot.profitability]
            ]) aetherDataCard.fact(facts, label, value);
            item.appendChild(facts);
            aetherDataCard.controls(header, {
                key: cardKey,
                canEdit: true,
                onEdit: () => { if (aetherDataCard.open(cardKey)) refreshCompanyDataCards(company); }
            });
        }

        const result = document.createElement("div");
        result.className = "company-snapshot-profit";
        const profitAmount = Number(snapshot.profitAmount || 0);
        const baseProfitAmount = Number(snapshot.baseProfitAmount || 0);
        const stabilityAdjustmentAmount = Number(snapshot.stabilityAdjustmentAmount || 0);
        const personnelImpactPercentage = Number(snapshot.personnelImpactPercentage || 0);
        const lowerBoundPercentage = Number(snapshot.stabilityLowerBoundPercentage || 0);
        const upperBoundPercentage = Number(snapshot.stabilityUpperBoundPercentage || 0);
        const variationAmounts = getCompanySnapshotAdjustedVariationAmounts(snapshot);
        const resultLabel = document.createElement("div");
        resultLabel.className = "company-snapshot-profit-label";
        resultLabel.textContent = isApplied ? "Historisch verwerkte winst/verlies" : "Winst/verlies periode";
        const resultValue = document.createElement("div");
        resultValue.className = "company-snapshot-profit-value";
        resultValue.textContent = formatCompanySnapshotCurrency(profitAmount);
        const resultMeta = document.createElement("div");
        resultMeta.className = "company-snapshot-profit-meta";
        const profitabilityLine = document.createElement("div");
        profitabilityLine.textContent = `Rendabiliteit: ${formatCompanySnapshotCurrency(baseProfitAmount)}`;
        const stabilityLine = document.createElement("div");
        stabilityLine.textContent = `Stabiliteit: ${formatCompanySnapshotCurrency(variationAmounts.minAmount)} tot ${formatCompanySnapshotCurrency(variationAmounts.maxAmount)}`;
        resultMeta.append(profitabilityLine, stabilityLine);
        result.append(resultLabel, resultValue, resultMeta);
        item.appendChild(result);

        const actionSection = document.createElement("div");
        actionSection.className = "company-snapshot-profit-meta mt-3";
        const dividendPerPercentage = profitAmount > 0 ? Number((profitAmount / 110).toFixed(2)) : 0;

        if (isApplied) {
            const appliedInfo = document.createElement("div");
            appliedInfo.textContent = `Toegepast: ${getCompanySnapshotAppliedActionLabel(appliedAction)}. Eerdere boekingen blijven bewaard bij correcties.`;
            actionSection.appendChild(appliedInfo);
        } else if (profitAmount < 0) {
            const lossButton = AetherActionButtons.button({ icon: "fa-check", label: "Bedrijfswaarde aanpassen" });
            lossButton.dataset.action = "apply-company-snapshot";
            lossButton.dataset.applyAction = "loss_adjustment";
            lossButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
            actionSection.appendChild(AetherActionButtons.group([lossButton]));
        } else if (profitAmount > 0) {
            const dividendInfo = document.createElement("div");
            dividendInfo.textContent = `Dividend voor 1%: ${formatCompanySnapshotCurrency(dividendPerPercentage)}`;
            actionSection.appendChild(dividendInfo);

            const actionButtons = document.createElement("div");
            actionButtons.className = "company-snapshot-actions button-group mt-2";

            const reinvestButton = AetherActionButtons.button({ icon: "fa-arrow-trend-up", label: "Herinvesteren" });
            reinvestButton.dataset.action = "apply-company-snapshot";
            reinvestButton.dataset.applyAction = "reinvest";
            reinvestButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
            actionButtons.appendChild(reinvestButton);

            const dividendButton = AetherActionButtons.button({ icon: "fa-coins", label: "Dividend uitbetalen" });
            dividendButton.dataset.action = "apply-company-snapshot";
            dividendButton.dataset.applyAction = "dividend";
            dividendButton.dataset.idCompanySnapshot = String(snapshot.idCompanySnapshot || 0);
            actionButtons.appendChild(dividendButton);

            actionSection.appendChild(actionButtons);
        }

        if ((editing || isApplied) && actionSection.childElementCount > 0) {
            item.appendChild(actionSection);
        }
        list.appendChild(item);
    });
}

function refreshCompanyDataCards(company = currentCompanyData) {
    renderCompanySnapshots(company);
    renderCompanyPersonnel(company);
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
        functionDescription: "",
        importance: "Moderate",
        salaryIncreasePercentage: 0,
        isBoardMember: false,
        isCompanyLeader: false,
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

function isCompanyLeaderProfession(idCharacter) {
    const option = getCompanyPersonnelCharacterOptions().find((entry) =>
        Number(entry?.idCharacter || 0) === Number(idCharacter || 0));
    return Boolean(option?.isCompanyLeaderProfession);
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
    addButton.disabled = aetherDataCard.hasActive();
    list.innerHTML = "";

    const entries = Array.isArray(currentCompanyPersonnelState) ? [...currentCompanyPersonnelState] : [];
    if (companyPersonnelDraft && companyPersonnelDraftIndex >= 0) {
        entries[companyPersonnelDraftIndex] = companyPersonnelDraft;
    }
    if (entries.length === 0) {
        empty.classList.remove("d-none");
        return;
    }

    empty.classList.add("d-none");
    const displayIndexes = entries.map((_, index) => index);
    if (companyPersonnelDraft && companyPersonnelDraftIndex === currentCompanyPersonnelState.length) {
        displayIndexes.unshift(displayIndexes.pop());
    }
    displayIndexes.forEach((entryIndex) => {
        const entry = entries[entryIndex];
        const cardEl = document.createElement("section");
        cardEl.className = "company-personnel-entry data-card";
        cardEl.dataset.personnelIndex = String(entryIndex);
        const cardKey = `company:${company.id}:personnel:${entryIndex}`;
        const editing = aetherDataCard.isEditing(cardKey);
        cardEl.classList.toggle("is-editing", editing);

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
        if (entry?.isCompanyLeader) {
            const leaderInfo = document.createElement("p");
            leaderInfo.className = "company-personnel-entry-subtitle";
            leaderInfo.textContent = `Bedrijfsleider · basisloon Fr ${Number(entry.leaderMonthlyIncome || 0).toFixed(2)}/maand · ${Number(entry.leaderStatusPoints || 0)} statuspunten · personeelsvoorwaarde ${Number(entry.leaderPersonnelRequirement || 0)}`;
            titleWrap.appendChild(leaderInfo);
        }
        header.appendChild(titleWrap);

        const removeButton = AetherActionButtons.button({ icon: "fa-trash", label: "Verwijderen" });
        removeButton.dataset.action = "remove-company-personnel";
        removeButton.dataset.personnelIndex = String(entryIndex);
        if (editing) header.appendChild(AetherActionButtons.group([removeButton]));

        cardEl.appendChild(header);

        if (!editing) {
            const facts = document.createElement("div");
            facts.className = "data-card-facts mt-3";
            const skill = entry?.skills?.[0];
            aetherDataCard.fact(facts, "Functiebeschrijving", entry?.functionDescription);
            aetherDataCard.fact(facts, "Belang", entry?.importance || "Moderate");
            aetherDataCard.fact(facts, "Loonsverhoging", `${Number(entry?.salaryIncreasePercentage || 0).toFixed(2)}%`);
            aetherDataCard.fact(facts, "Winstdruk per jaar", formatCompanySnapshotCurrency(Number(entry?.monthlySalaryIncreaseAmount || 0) * 12));
            aetherDataCard.fact(facts, "Raad van bestuur", entry?.isBoardMember ? "Ja" : "Nee");
            aetherDataCard.fact(facts, "Vaardigheid", skill?.skillName || findCompanyPersonnelSkillOption(skill?.idSkill)?.name || null);
            aetherDataCard.fact(facts, "Vereist niveau", skill?.idSkill ? formatCompanyPersonnelSkillLevelLabel(skill.level) : null);
            aetherDataCard.fact(facts, "Specialisaties", skill?.specialisations?.map((value) => value.name).filter(Boolean).join(", "));
            cardEl.appendChild(facts);
            aetherDataCard.controls(header, {
                key: cardKey,
                canEdit: true,
                onEdit: () => {
                    if (!aetherDataCard.open(cardKey)) return;
                    companyPersonnelDraft = cloneCompanyPersonnelEntries([entry])[0];
                    companyPersonnelDraftIndex = entryIndex;
                    refreshCompanyDataCards(company);
                }
            });
            list.appendChild(cardEl);
            return;
        }

        aetherDataCard.controls(header, {
            key: cardKey,
            canEdit: true,
            onSave: () => saveCompanyPersonnelDraft(entryIndex, cardKey),
            onCancel: () => cancelCompanyPersonnelDraft(cardKey)
        });

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

        const functionCol = document.createElement("div");
        functionCol.className = "col-12";
        const functionLabel = document.createElement("label");
        functionLabel.className = "form-label";
        functionLabel.textContent = "Functiebeschrijving";
        functionCol.appendChild(functionLabel);
        const functionInput = document.createElement("textarea");
        functionInput.className = "form-control";
        functionInput.rows = 3;
        functionInput.maxLength = 4000;
        functionInput.value = String(entry?.functionDescription || "");
        functionInput.dataset.companyPersonnelField = "functionDescription";
        functionInput.dataset.personnelIndex = String(entryIndex);
        functionCol.appendChild(functionInput);
        fieldsRow.appendChild(functionCol);

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
        const pressure = document.createElement("small");
        pressure.className = "text-muted d-block mt-1";
        pressure.textContent = `Winstdruk: Fr ${(Number(entry?.monthlySalaryIncreaseAmount || 0) * 12).toFixed(2)} per jaar`;
        salaryCol.appendChild(pressure);
        fieldsRow.appendChild(salaryCol);

        const roleCol = document.createElement("div");
        roleCol.className = "col-12 d-flex flex-wrap gap-3";
        for (const [field, label] of [["isBoardMember", "Raad van bestuur"]]) {
            const wrap = document.createElement("label");
            wrap.className = "form-check";
            const check = document.createElement("input");
            check.type = "checkbox";
            check.className = "form-check-input";
            check.checked = Boolean(entry?.[field]);
            check.disabled = field === "isBoardMember" && Boolean(entry?.isCompanyLeader);
            check.dataset.companyPersonnelField = field;
            check.dataset.personnelIndex = String(entryIndex);
            wrap.append(check, document.createTextNode(` ${label}`));
            roleCol.appendChild(wrap);
        }
        fieldsRow.appendChild(roleCol);

        cardEl.appendChild(fieldsRow);

        const skills = Array.isArray(entry?.skills) ? entry.skills : [];
        if (skills.length === 0) {
            entry.skills = [createEmptyCompanyPersonnelSkill()];
        }
        const skillEntry = entry.skills?.[0] || createEmptyCompanyPersonnelSkill();
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
        levelLabel.textContent = "Vereist niveau";
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

        const addSpecialisationButton = AetherActionButtons.button({ icon: "fa-plus", label: "Specialisatie toevoegen" });
        addSpecialisationButton.dataset.action = "open-company-personnel-specialisation-modal";
        addSpecialisationButton.dataset.personnelIndex = String(entryIndex);
        addSpecialisationButton.dataset.skillIndex = "0";
        addSpecialisationButton.disabled = Number(skillEntry?.idSkill || 0) <= 0;
        specialisationsHeader.appendChild(AetherActionButtons.group([addSpecialisationButton]));
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

                const deleteButton = AetherActionButtons.button({ icon: "fa-xmark", title: "Specialisatie verwijderen", ariaLabel: "Specialisatie verwijderen" });
                deleteButton.dataset.action = "remove-company-personnel-specialisation";
                deleteButton.dataset.personnelIndex = String(entryIndex);
                deleteButton.dataset.skillIndex = "0";
                deleteButton.dataset.specialisationIndex = String(specialisationIndex);
                badge.appendChild(AetherActionButtons.group([deleteButton]));

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
    if (personnelIndex < 0 || personnelIndex !== companyPersonnelDraftIndex || !companyPersonnelDraft) {
        return;
    }

    const entry = companyPersonnelDraft;

    if (field === "idCharacter") {
        entry.idCharacter = Math.max(0, Number(target.value || 0));
        entry.isCompanyLeader = isCompanyLeaderProfession(entry.idCharacter);
        if (entry.isCompanyLeader) entry.isBoardMember = true;
        renderCompanyPersonnel(currentCompanyData);
    } else if (field === "importance") {
        entry.importance = String(target.value || "Moderate");
    } else if (field === "functionDescription") {
        entry.functionDescription = String(target.value || "");
    } else if (field === "salaryIncreasePercentage") {
        const parsed = Number(String(target.value || "").replace(",", "."));
        entry.salaryIncreasePercentage = Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    } else if (field === "isBoardMember") {
        entry.isBoardMember = target.checked || Boolean(entry.isCompanyLeader);
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

}

function cancelCompanyPersonnelDraft(cardKey) {
    companyPersonnelDraft = null;
    companyPersonnelDraftIndex = -1;
    aetherDataCard.close(cardKey);
    refreshCompanyDataCards(currentCompanyData);
}

async function saveCompanyPersonnelDraft(index, cardKey) {
    if (!companyPersonnelDraft || index !== companyPersonnelDraftIndex) return false;
    if (Number(companyPersonnelDraft.idCharacter || 0) <= 0) {
        showCompanyFeedback("Kies eerst een personage.", "danger");
        return false;
    }
    const entries = cloneCompanyPersonnelEntries(currentCompanyPersonnelState);
    entries[index] = cloneCompanyPersonnelEntries([companyPersonnelDraft])[0];
    return saveCompanyPersonnel({ entries, closeCardKey: cardKey });
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
        const cardKey = `company:${currentCompanyData?.id}:personnel:${currentCompanyPersonnelState.length}`;
        if (!aetherDataCard.open(cardKey)) return;
        companyPersonnelDraftIndex = currentCompanyPersonnelState.length;
        companyPersonnelDraft = createEmptyCompanyPersonnelEntry();
        refreshCompanyDataCards(currentCompanyData);
        return;
    }

    if (action === "remove-company-personnel") {
        if (personnelIndex < 0) return;
        if (personnelIndex !== companyPersonnelDraftIndex) return;
        const cardKey = `company:${currentCompanyData?.id}:personnel:${personnelIndex}`;
        if (personnelIndex >= currentCompanyPersonnelState.length) {
            cancelCompanyPersonnelDraft(cardKey);
            return;
        }
        const entries = cloneCompanyPersonnelEntries(currentCompanyPersonnelState);
        entries.splice(personnelIndex, 1);
        void saveCompanyPersonnel({ entries, closeCardKey: cardKey });
        return;
    }

    if (personnelIndex < 0 || personnelIndex !== companyPersonnelDraftIndex || !companyPersonnelDraft) {
        return;
    }

    const entry = companyPersonnelDraft;

    if (skillIndex < 0 || !entry.skills?.[skillIndex]) {
        return;
    }

    const skillEntry = entry.skills[skillIndex];

    if (action === "remove-company-personnel-specialisation") {
        if (specialisationIndex < 0 || !skillEntry.specialisations?.[specialisationIndex]) return;
        skillEntry.specialisations.splice(specialisationIndex, 1);
        renderCompanyPersonnel(currentCompanyData);
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

    const entry = personnelIndex === companyPersonnelDraftIndex ? companyPersonnelDraft : null;
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
    const skillEntry = personnelIndex === companyPersonnelDraftIndex
        ? companyPersonnelDraft?.skills?.[skillIndex] : null;
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

    const eventOptions = companySnapshotEventOptions?.length
        ? companySnapshotEventOptions : (currentCompanyData?.snapshotEventOptions || []);
    const chosenEvent = eventOptions.find((option) => Number(option.idEvent) === idEvent);
    const chosenDate = String(chosenEvent?.dateStart || "");
    const orderedSnapshots = sortCompanySnapshotsByDate(currentCompanyData?.snapshots);
    const previousSnapshot = (chosenDate ? orderedSnapshots.filter((snapshot) =>
        String(snapshot.dateStart || "") < chosenDate
        || (String(snapshot.dateStart || "") === chosenDate
            && Number(snapshot.idEvent || 0) < idEvent)) : orderedSnapshots).at(-1);
    const baseline = previousSnapshot || currentCompanyData?.foundationSnapshot || currentCompanyData;

    try {
        const result = await apiFetchFinancialJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "create",
                idCompany,
                idEvent,
                stability: normalizeSliderValue(baseline?.stability),
                profitability: normalizeSliderValue(baseline?.profitability)
            }
        });

        await applyCompanySnapshotResponse(result, idCompany);
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
        const result = await apiFetchFinancialJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "recalculate",
                idCompany,
                idCompanySnapshot
            }
        });

        aetherDataCard.close(`company:${idCompany}:snapshot:${idCompanySnapshot}`);
        await applyCompanySnapshotResponse(result, idCompany);
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
        const result = await apiFetchFinancialJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "delete",
                idCompany,
                idCompanySnapshot
            }
        });

        aetherDataCard.close(`company:${idCompany}:snapshot:${idCompanySnapshot}`);
        await applyCompanySnapshotResponse(result, idCompany);
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
        const result = await apiFetchFinancialJson("api/companies/saveCompanySnapshot.php", {
            method: "POST",
            body: {
                action: "apply",
                applyAction,
                idCompany,
                idCompanySnapshot
            }
        });

        aetherDataCard.close(`company:${idCompany}:snapshot:${idCompanySnapshot}`);
        await applyCompanySnapshotResponse(result, idCompany);
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

async function saveCompanyCurrentSliders() {
    const company = currentCompanyData;
    const idCompany = Number(company?.id || 0);
    if (!idCompany || isPopulatingCompanyForm) return;
    const stability = normalizeSliderValue(document.getElementById("companyStability")?.value);
    const profitability = normalizeSliderValue(document.getElementById("companyProfitability")?.value);
    const latest = sortCompanySnapshotsByDate(company.snapshots).at(-1);
    const currentStability = Number(latest?.stability ?? company.foundationSnapshot?.stability ?? company.stability ?? 0);
    const currentProfitability = Number(latest?.profitability ?? company.foundationSnapshot?.profitability ?? company.profitability ?? 0);
    const changes = {};
    if (stability !== currentStability) changes.stability = stability;
    if (profitability !== currentProfitability) changes.profitability = profitability;
    if (Object.keys(changes).length === 0) return;
    try {
        if (latest) {
            await apiFetchFinancialJson("api/companies/saveCompanySnapshot.php", {
                method: "POST",
                body: { action: "update", idCompany, idCompanySnapshot: Number(latest.idCompanySnapshot),
                    ...changes }
            });
        } else if (company.foundationSnapshot) {
            await aetherShareUi.mutateCompany("setCompanyFoundation", {
                idCompany,
                initialCompanyValue: Number(company.foundationSnapshot.initialCompanyValue || 0).toFixed(2),
                initialShareCount: Number(company.foundationSnapshot.initialShareCount || 100),
                stability, profitability
            });
        } else {
            showCompanyFeedback("Voer eerst de snapshotmigratie uit.", "danger");
            return;
        }
        await loadCompany(idCompany);
        showCompanyFeedback("Snapshot bijgewerkt.", "success");
    } catch (error) {
        showCompanyFeedback(error?.message || aetherShareUi.errorMessage(error), "danger");
        await loadCompany(idCompany);
    }
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

            await apiFetchFinancialJson("api/companies/updateCompany.php", {
                method: "POST",
                body: {
                    id,
                    companyName,
                    description,
                    foundationDate
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
                silentWhenUnavailable = false,
                entries = currentCompanyPersonnelState,
                closeCardKey = null
            } = options;
            const idCompany = Number(document.getElementById("companyId")?.value || 0);
            if (idCompany <= 0) {
                if (!silentWhenUnavailable) {
                    showCompanyFeedback("Kies eerst een bedrijf.", "danger");
                }
                return true;
            }

            const result = await apiFetchIdempotentJson("api/companies/saveCompanyPersonnel.php", {
                method: "POST",
                body: {
                    idCompany,
                    personnel: entries.map((entry) => ({
                        idCompanyPersonnel: entry.idCompanyPersonnel,
                        idCharacter: entry.idCharacter,
                        functionDescription: String(entry.functionDescription || ""),
                        importance: entry.importance,
                        salaryIncreasePercentage: Number(entry.salaryIncreasePercentage || 0).toFixed(2),
                        isBoardMember: Boolean(entry.isBoardMember),
                        skills: (entry.skills || []).map((skill) => ({
                            idCompanyPersonnelSkill: skill.idCompanyPersonnelSkill,
                            idSkill: skill.idSkill,
                            level: skill.level,
                            specialisations: (skill.specialisations || []).map((specialisation) => ({
                                idSkillSpecialisation: specialisation.idSkillSpecialisation,
                                name: specialisation.name,
                                kind: specialisation.kind
                            }))
                        }))
                    }))
                }
            });

            currentCompanyPersonnelState = cloneCompanyPersonnelEntries(result?.personnelEntries);
            if (closeCardKey) {
                aetherDataCard.close(closeCardKey);
                companyPersonnelDraft = null;
                companyPersonnelDraftIndex = -1;
            }
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
