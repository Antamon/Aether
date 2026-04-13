// Form-related helpers: listeners, autosave, read-only render, editability toggles

function setupCharacterFormListeners() {
    // Alleen autosave; de create-flow listeners zitten in createCharacter.js
    const velden = [
        "firstName",
        "lastName",
        "class",
        "birthDate",
        "birthPlace",
        "nationality",
        "stateRegisterNumber",
        "street",
        "houseNumber",
        "municipality",
        "postalCode",
        "title",
        "maritalStatus"
    ];

    velden.forEach((veld) => {
        const el = document.getElementById(veld);
        if (el) {
            el.addEventListener("change", handleAutoSaveField);
        }
    });
}

function canEditHealthForCharacter(character) {
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

function canEditFreeHealthForCharacter(character) {
    if (!currentUser) return false;
    return currentUser.role === "administrator" || currentUser.role === "director";
}

function canEditClassForCharacter(character) {
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

function syncClassFieldPresentation(character) {
    const classRow = document.getElementById("classRow");
    if (!classRow || !character) return;

    const valueCol = classRow.querySelector(".col-sm-8");
    if (!valueCol) return;

    if (canEditClassForCharacter(character)) {
        const select = document.getElementById("class");
        if (select) {
            select.value = character.class || "";
        }
        return;
    }

    valueCol.textContent = character.class || "";
}

function formatCharacterCurrency(amount) {
    return `${Number(amount || 0).toLocaleString("nl-BE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} Fr`;
}

function traitHasCharacterFlag(trait, flagKey) {
    const flags = trait?.traitFlags;
    if (flags && typeof flags === "object" && flags[flagKey]) {
        return true;
    }

    const keys = Array.isArray(trait?.traitFlagKeys) ? trait.traitFlagKeys : [];
    return keys.includes(flagKey);
}

function getUpperClassNobilityIncomeMultiplier(character) {
    if (character?.class !== "upper class") {
        return 1;
    }

    const traitGroups = Array.isArray(character?.traitGroups) ? character.traitGroups : [];
    const titleGroup = traitGroups.find((group) => (
        String(group?.trackKey || "") === "upper_nobility_title"
        || String(group?.traitGroup || "") === "Adellijke titel"
    ));
    const linkedTitle = Array.isArray(titleGroup?.linkedTraits) ? titleGroup.linkedTraits[0] : null;

    if (traitHasCharacterFlag(linkedTitle, "family_head_title") || String(linkedTitle?.name || "").trim().toLowerCase() === "familiehoofd") {
        return 2;
    }

    if (linkedTitle) {
        return 1.5;
    }

    return 1;
}

function getCharacterTraitIncomeTotal(character) {
    const backendTotal = Number(character?.recurringIncomeTotal);
    if (Number.isFinite(backendTotal)) {
        return backendTotal;
    }

    const groups = [
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : []),
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : [])
    ];
    const seenTraitIds = new Set();
    const nobilityIncomeMultiplier = getUpperClassNobilityIncomeMultiplier(character);

    const baseIncome = groups.reduce((total, group) => {
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

            if (income !== null && Number.isFinite(Number(income))) {
                const adjustedIncome = (
                    String(group?.trackKey || "") === "upper_nobility_lineage"
                    || linkedTraits.some((linkedTrait) => traitHasCharacterFlag(linkedTrait, "nobility_income_scaled"))
                    || String(group?.traitGroup || "") === "Adeldom"
                )
                    ? Number(income) * nobilityIncomeMultiplier
                    : Number(income);
                total += adjustedIncome;
            }
        });
        return total;
    }, 0);

    const salaryIncreaseAmount = Number(character?.salaryIncreaseAmount || 0);
    return baseIncome + (Number.isFinite(salaryIncreaseAmount) ? salaryIncreaseAmount : 0);
}

function renderCharacterWealthSection(character) {
    const container = document.getElementById("wealthSectionContent");
    if (!container || !character) return;

    const totalIncome = getCharacterTraitIncomeTotal(character);
    const bankBalance = typeof getDisplayedCharacterBankAccountAmount === "function"
        ? getDisplayedCharacterBankAccountAmount(character)
        : Number(character?.bankaccount ?? 0);
    container.innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-4">
            <span>Inkomsten: ${formatCharacterCurrency(totalIncome)}</span>
            <span>Bankrekening: ${formatCharacterCurrency(bankBalance)}</span>
        </div>
    `;
}

function getCharacterLinkedTraits(character) {
    const groups = [
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : []),
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : [])
    ];
    const seenTraitIds = new Set();
    const linkedTraits = [];

    groups.forEach((group) => {
        const groupTraits = Array.isArray(group?.linkedTraits) ? group.linkedTraits : [];
        groupTraits.forEach((trait) => {
            const traitId = Number(trait?.idTrait || trait?.id || 0);
            if (traitId > 0 && seenTraitIds.has(traitId)) {
                return;
            }

            if (traitId > 0) {
                seenTraitIds.add(traitId);
            }

            linkedTraits.push(trait);
        });
    });

    return linkedTraits;
}

function getCharacterStaffRequirementTraits(character) {
    return getCharacterLinkedTraits(character).filter((trait) => Number(trait?.staffRequirements ?? 0) > 0);
}

function getUpperClassLivingStandardText(character) {
    const traitGroups = Array.isArray(character?.traitGroups) ? character.traitGroups : [];
    const adeldomGroup = traitGroups.find((group) => (
        String(group?.trackKey || "") === "upper_nobility_lineage"
        || String(group?.traitGroup || "") === "Adeldom"
    ));
    const adeldomTrait = Array.isArray(adeldomGroup?.linkedTraits) ? adeldomGroup.linkedTraits[0] : null;
    return String(adeldomTrait?.description || "").trim();
}

function getMiddleClassLivingStandardText(character) {
    const income = Number(getCharacterTraitIncomeTotal(character) || 0);

    if (income <= 50) {
        return "Je vervalt in armoede en wordt lagere klasse.";
    }

    if (income <= 100) {
        return `Je behoort tot de verarmde middenklasse. Je huurt
een klein appartement of kleine gezinswoning van
een hospita in een minder goede wijk van de stad. Je
maaltijden zijn sober en soms karig, je kleding een
beetje afstands. Je hebt moeite met de eindjes aan elkaar te knopen op het einde van de maand. Je woont
maar zelden sociale evenementen bij door geldge-
brek.`;
    }

    if (income <= 200) {
        return `Je maakt deel uit van de lagere middenklasse. Je
woont in een eigen appartement of kleine gezinswoning in een goede buurt van de stad. Je kan je de
eenvoudige pleziertjes van het leven veroorloven.
Je sociaal leven bestaat uit bezoekjes aan volkstheaters, circussen, musea en activiteiten met je collega’s.`;
    }

    if (income <= 350) {
        return `Je behoort tot de comfortabele middenmoot van
je klasse. Je woont in een comfortabele stadswoning
met twee tot vier slaapkamers en een huishoudster. Je
hebt een goed uitgebouwd professioneel netwerk en
komt regelmatig in contact met invloedrijke mensen.
Je moet voorzichtig zijn met je geld, maar je kan
jezelf en je gezin af en toe verwennen met een luxe
uitgave.`;
    }

    if (income <= 1000) {
        return `Je maakt deel uit van de gegoede middenklasse.
Je woont in een mooi, groot herenhuis met 6 tot 8
slaapkamers, een huishoudster, een butler en een
tuinman of klusjesman. Je bent belangrijk genoeg
om regelmatig uitgenodigd te worden op aangelegenheden waar de prominenten uit de samenleving bijeen komen.`;
    }

    if (income <= 4000) {
        return `Je behoort tot de hogere middenklasse. Je woont in
een riante stadswoning of landhuis met vele kamers,
meerdere badkamers, salons en een grote tuin. Bij
het huis hoort een ploeg van ongeveer 10 bedienden.
Je exclusieve diners bij kaarslicht zijn gelegenheden
waar reikhalzend naar wordt uitgekeken. Je persoon-
lijk vermogen wordt geschat tussen de 1 700 000Fr
en de 15 000 000Fr.`;
    }

    return `De levensstijl van de nouveau riche die de luxe van
de hoge adel benadert. Je woont in een uniek kasteel
of paleis buiten de stad. Bij de woonst horen de om-
liggende velden en bossen. Je krijgt er een uitgebreide
huishoudploeg bij, alsook boswachters om je domein
veilig te houden. Uitgebreide diners en exuberante
feestjes met belangrijke mensen, zijn de normale gang
van zaken.`;
}

function getLowerClassLivingStandardText() {
    return `Je leeft in armoede. Een eigendom is iets wat je nooit zal bereiken. Als je geluk hebt, kan je een schamel onderkomen huren waar je met het hele gezin woont. Hartig dagelijks voedsel is geen zekerheid, gezondheidszorg een onbereikbare droom. Je enige hoop op een comfortabel leven is als inwonend personeel bij een rijke werkgeven.`;
}

function getUpperClassHouseholdStaffLivingStandardText(tier) {
    if (tier === 1) {
        return "Je woont inwonend bij een adellijke familie die wel status heeft, maar weinig echte rijkdom. Je beschikt meestal over een kleine kamer onder het dak of in een achtervleugel van het huis, met enkel het strikt noodzakelijke meubilair. Je eet degelijk maar eenvoudig, vaak na de familie en het hogere personeel. Privacy is beperkt: je kamer is van jou, maar klein, sober en duidelijk ondergeschikt aan de rest van het huis.";
    }

    if (tier === 2) {
        return "Je woont op een landgoed, in een herenhuis of klein kasteel waar personeel een vaste plaats heeft binnen het huishouden. Je hebt een eenvoudige maar nette eigen kamer in de dienstvertrekken, meestal met bed, waskom, kast en een stoel. Je maaltijden zijn degelijk en regelmatig, en je leeft comfortabeler dan de meeste arbeiders. Je privévertrek is bescheiden, maar veilig, verwarmd en duidelijk beter dan wat de lagere klasse zich gewoonlijk kan veroorloven.";
    }

    if (tier === 3) {
        return "Je dient in een groot adellijk huishouden waar orde, hiërarchie en comfort belangrijk zijn. Je beschikt over een nette personeelskamer in de bediendenvleugel, met voldoende ruimte voor je persoonlijke spullen en een degelijk bed. Bad- en wasvoorzieningen zijn meestal gedeeld met ander personeel, maar goed onderhouden. Je leeft in zekerheid en comfort, al blijft duidelijk dat zelfs je privéruimte deel uitmaakt van het grotere huishouden van je meester.";
    }

    if (tier === 4) {
        return "Je woont in een voornaam huis of stadspaleis waar het personeel talrijk is en de organisatie strak verloopt. Je hebt een verzorgde kamer in de personeelsvertrekken, mogelijk met een klein zitgedeelte of gedeelde salon voor het hogere dienstpersoneel. Je eet goed, bent degelijk gekleed en leeft in een omgeving van luxe, al geniet je er slechts indirect van. Je privévertrekken zijn comfortabel, stil en respectabel, maar altijd ondergeschikt aan de pracht van de familie zelf.";
    }

    if (tier === 5) {
        return "Je maakt deel uit van een enorm huishouden dat bijna als een kleine instelling functioneert. Je woont in ruime en goed onderhouden dienstvertrekken, vaak in een aparte personeelsvleugel of bijgebouw, met veel meer comfort dan de meeste gewone burgers ooit kennen. Hogere personeelsleden kunnen zelfs over meerdere kleine kamers beschikken, zoals een slaapvertrek en een privézitkamer of kantoorhoek. Je leeft materieel comfortabel en in grote veiligheid, maar je bestaan blijft volledig ingebed in de discipline en verwachtingen van een machtige dynastie.";
    }

    return "";
}

function getMiddleClassHouseholdStaffLivingStandardText(income) {
    if (income <= 350) {
        return "";
    }

    if (income <= 1000) {
        return "Je woont in een groot herenhuis waar personeel deel uitmaakt van het normale huishouden. Je kamer ligt in de personeelsvertrekken en is degelijk, verzorgd en ruim genoeg om echt als privéruimte te voelen. Voor hoger dienstpersoneel is een iets grotere kamer of een klein eigen salonnetje niet ondenkbaar. Je leeft onder goede materiële omstandigheden, met voldoende comfort, regelmaat en een zekere trots die hoort bij dienst in een voornaam burgerlijk huis.";
    }

    if (income <= 4000) {
        return "Je woont inwonend in een groot landhuis of statige stadswoning waar personeel overvloediger aanwezig is. Je beschikt over comfortabele dienstvertrekken, en afhankelijk van je functie kan dat meer zijn dan één eenvoudige kamer. Het hogere personeel heeft soms toegang tot een kleine privézitruimte, beter meubilair en meer rust dan lager personeel. Je leeft zeer behoorlijk, met veel meer comfort, veiligheid en status dan de meeste mensen uit de middenklasse.";
    }

    return "Je woont in een uitzonderlijk luxueus huishouden waar zelfs de personeelsvertrekken ruim en verzorgd zijn. Afhankelijk van je rang binnen het personeel kan je kamer bijna de allure hebben van een kleine burgerwoning: een ruim slaapvertrek, goed meubilair en soms een aparte privéruimte om te zitten of administratie te doen. Je leeft in overvloed en orde, maar ook in een omgeving waar uiterlijk vertoon, discipline en representatie zeer belangrijk zijn. Je geniet van comfort dat voor de meeste mensen onbereikbaar is, al blijft het comfort volledig verbonden aan je dienstbaarheid.";
}

async function getCharacterHouseholdStaffLivingStandardText(character) {
    if (!character?.id || typeof fetchCharacterTies !== "function") {
        return "";
    }

    let ties = [];
    try {
        ties = await fetchCharacterTies(character.id) || [];
    } catch (err) {
        console.error("Fout bij ophalen ties voor levensstandaard:", err);
        return "";
    }

    const confirmedLandlordTie = ties.find((tie) => (
        String(tie?.relationType || "").toLowerCase() === "landlord"
        && Boolean(tie?.hasReverseHouseholdStaff)
    ));

    if (confirmedLandlordTie) {
        if (String(confirmedLandlordTie?.otherClass || "").trim() === "upper class") {
            return getUpperClassHouseholdStaffLivingStandardText(
                Number(confirmedLandlordTie?.otherUpperClassLivingStandardTier || 0)
            );
        }

        if (String(confirmedLandlordTie?.otherClass || "").trim() === "middle class") {
            return getMiddleClassHouseholdStaffLivingStandardText(
                Number(
                    confirmedLandlordTie?.otherMiddleClassLivingStandardIncome
                    ?? confirmedLandlordTie?.otherRecurringIncomeTotal
                    ?? 0
                )
            );
        }

        return "";
    }

    const pendingLandlordTie = ties.find((tie) => (
        String(tie?.relationType || "").toLowerCase() === "landlord"
    ));

    if (pendingLandlordTie) {
        return "Op dit moment ben je dakloos.";
    }

    return "";
}

function getResolvedMiddleClassLivingStandardText(character) {
    const backendIncome = Number(character?.middleClassLivingStandardIncome);
    const income = Number.isFinite(backendIncome)
        ? backendIncome
        : Number(getCharacterTraitIncomeTotal(character) || 0);

    if (income <= 50) {
        return "Je vervalt in armoede en wordt lagere klasse.";
    }

    if (income <= 100) {
        return `Je behoort tot de verarmde middenklasse. Je huurt
een klein appartement of kleine gezinswoning van
een hospita in een minder goede wijk van de stad. Je
maaltijden zijn sober en soms karig, je kleding een
beetje afstands. Je hebt moeite met de eindjes aan elkaar te knopen op het einde van de maand. Je woont
maar zelden sociale evenementen bij door geldge-
brek.`;
    }

    if (income <= 200) {
        return `Je maakt deel uit van de lagere middenklasse. Je
woont in een eigen appartement of kleine gezinswoning in een goede buurt van de stad. Je kan je de
eenvoudige pleziertjes van het leven veroorloven.
Je sociaal leven bestaat uit bezoekjes aan volkstheaters, circussen, musea en activiteiten met je collega's.`;
    }

    if (income <= 350) {
        return `Je behoort tot de comfortabele middenmoot van
je klasse. Je woont in een comfortabele stadswoning
met twee tot vier slaapkamers en een huishoudster. Je
hebt een goed uitgebouwd professioneel netwerk en
komt regelmatig in contact met invloedrijke mensen.
Je moet voorzichtig zijn met je geld, maar je kan
jezelf en je gezin af en toe verwennen met een luxe
uitgave.`;
    }

    if (income <= 1000) {
        return `Je maakt deel uit van de gegoede middenklasse.
Je woont in een mooi, groot herenhuis met 6 tot 8
slaapkamers, een huishoudster, een butler en een
tuinman of klusjesman. Je bent belangrijk genoeg
om regelmatig uitgenodigd te worden op aangelegenheden waar de prominenten uit de samenleving bijeen komen.`;
    }

    if (income <= 4000) {
        return `Je behoort tot de hogere middenklasse. Je woont in
een riante stadswoning of landhuis met vele kamers,
meerdere badkamers, salons en een grote tuin. Bij
het huis hoort een ploeg van ongeveer 10 bedienden.
Je exclusieve diners bij kaarslicht zijn gelegenheden
waar reikhalzend naar wordt uitgekeken. Je persoon-
lijk vermogen wordt geschat tussen de 1 700 000Fr
en de 15 000 000Fr.`;
    }

    return `De levensstijl van de nouveau riche die de luxe van
de hoge adel benadert. Je woont in een uniek kasteel
of paleis buiten de stad. Bij de woonst horen de om-
liggende velden en bossen. Je krijgt er een uitgebreide
huishoudploeg bij, alsook boswachters om je domein
veilig te houden. Uitgebreide diners en exuberante
feestjes met belangrijke mensen, zijn de normale gang
van zaken.`;
}

function getCharacterLivingStandardText(character) {
    const characterClass = String(character?.class || "").trim();
    if (characterClass === "upper class") {
        return getUpperClassLivingStandardText(character);
    }

    if (characterClass === "middle class") {
        return getResolvedMiddleClassLivingStandardText(character);
    }

    if (characterClass === "lower class") {
        return getLowerClassLivingStandardText();
    }

    return "";
}

async function renderCharacterLivingStandardSection(character) {
    const container = document.getElementById("livingStandardSectionContent");
    const title = document.getElementById("livingStandardSectionTitle");
    if (!container || !title) return;

    const householdStaffText = await getCharacterHouseholdStaffLivingStandardText(character);
    const text = householdStaffText || getCharacterLivingStandardText(character);
    const normalizedText = String(text || "").replace(/\s*\r?\n\s*/g, " ").trim();
    const shouldShow = normalizedText !== "";

    title.classList.toggle("d-none", !shouldShow);
    container.classList.toggle("d-none", !shouldShow);
    container.classList.toggle("mb-4", shouldShow);

    if (!shouldShow) {
        container.innerHTML = "";
        syncCharacterLeftInfoSectionsVisibility();
        return;
    }

    container.innerHTML = "";
    const paragraph = document.createElement("p");
    paragraph.className = "character-sheet-info-text mb-0";
    paragraph.textContent = normalizedText;
    container.appendChild(paragraph);
    syncCharacterLeftInfoSectionsVisibility();
}

function syncCharacterLeftInfoSectionsVisibility() {
    const card = document.getElementById("leftInfoSections");
    if (!card) return;

    const livingStandardTitle = document.getElementById("livingStandardSectionTitle");
    const staffTitle = document.getElementById("staffSectionTitle");
    const languagesTitle = document.getElementById("languagesSectionTitle");
    const hasVisibleLivingStandard = Boolean(livingStandardTitle && !livingStandardTitle.classList.contains("d-none"));
    const hasVisibleStaff = Boolean(staffTitle && !staffTitle.classList.contains("d-none"));
    const hasVisibleLanguages = Boolean(languagesTitle && !languagesTitle.classList.contains("d-none"));

    card.classList.toggle("d-none", !hasVisibleLivingStandard && !hasVisibleStaff && !hasVisibleLanguages);
}

function isStaffTieConfirmed(tie) {
    const relationType = String(tie?.relationType || "").toLowerCase();
    if (relationType === "dependent") {
        return Boolean(tie?.hasReverseSuperior);
    }

    if (relationType === "household_staff") {
        return Boolean(tie?.hasReverseLandlord);
    }

    if (relationType === "spouse") {
        return Boolean(tie?.hasReverseSpouse);
    }

    return false;
}

async function renderCharacterStaffSection(character) {
    const container = document.getElementById("staffSectionContent");
    const title = document.getElementById("staffSectionTitle");
    if (!container || !character?.id) return;

    const staffRequirementTraits = getCharacterStaffRequirementTraits(character);
    const requiredStaff = staffRequirementTraits.reduce(
        (total, trait) => total + Number(trait?.staffRequirements ?? 0),
        0
    );

    let ties = [];
    try {
        if (typeof fetchCharacterTies === "function") {
            ties = await fetchCharacterTies(character.id) || [];
        } else {
            ties = await apiFetchJson("api/characters/getCharacterTies.php", {
                method: "POST",
                body: { idCharacter: character.id }
            }) || [];
        }
    } catch (err) {
        console.error("Fout bij ophalen ties voor personeel:", err);
    }

    if (currentCharacter && Number(currentCharacter.id) !== Number(character.id)) {
        return;
    }

    const staffTies = ties.filter((tie) => {
        const relationType = String(tie?.relationType || "").toLowerCase();
        return relationType === "dependent" || relationType === "household_staff" || relationType === "spouse";
    });
    const confirmedStaffTies = staffTies.filter((tie) => isStaffTieConfirmed(tie));
    const shouldShowStaffSection = requiredStaff > 0 || staffTies.length > 0;

    if (title) {
        title.classList.toggle("d-none", !shouldShowStaffSection);
    }

    container.classList.toggle("d-none", !shouldShowStaffSection);
    container.classList.toggle("mb-4", shouldShowStaffSection);

    if (!shouldShowStaffSection) {
        container.innerHTML = "";
        syncCharacterLeftInfoSectionsVisibility();
        return;
    }

    const traitList = staffRequirementTraits.length > 0
        ? `<ul class="list-unstyled mb-3">${staffRequirementTraits
            .map((trait) => `
                <li class="d-flex justify-content-between align-items-center gap-3 mb-2">
                    <span>${trait.name || ""}</span>
                    <span class="badge text-bg-secondary">${Number(trait.staffRequirements || 0)}</span>
                </li>
            `)
            .join("")}</ul>`
        : `<p class="text-muted mb-3">Geen traits met personeelsvoorwaarde.</p>`;

    const dependentList = staffTies.length > 0
        ? `<ul class="list-unstyled mb-0">${staffTies
            .map((tie) => {
                const label = [tie.lastName, tie.firstName].filter(Boolean).join(" ").trim() || tie.otherName || "";
                const isConfirmed = isStaffTieConfirmed(tie);
                const badgeClass = isConfirmed ? "text-bg-success" : "text-bg-danger";
                const badgeText = isConfirmed ? "Bevestigd" : "Onbevestigd";
                return `
                    <li class="d-flex justify-content-between align-items-center gap-3 mb-2">
                        <span>${label}</span>
                        <span class="badge ${badgeClass}">${badgeText}</span>
                    </li>
                `;
            })
            .join("")}</ul>`
        : `<p class="text-muted mb-0">Geen dependent, household staff of spouse ties.</p>`;

    container.innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
            <span>Personeelsvoorwaarde: ${requiredStaff}</span>
            <span>Voldaan: ${confirmedStaffTies.length}</span>
        </div>
        <h5>Eigenschappen met personeelsvoorwaarden</h5>
        ${traitList}
        <h5>Gevolg</h5>
        ${dependentList}
    `;
    syncCharacterLeftInfoSectionsVisibility();
}

function createHealthIconStrip(total, primaryIconPath, secondaryIconPath, label) {
    const safeTotal = Math.max(0, Number(total) || 0);
    const primaryCount = Math.ceil(safeTotal / 2);
    const secondaryCount = Math.floor(safeTotal / 2);

    const wrapper = document.createElement("span");
    wrapper.className = "ms-2 d-inline-flex flex-wrap align-items-center gap-1";
    wrapper.setAttribute("aria-label", `${label}: ${safeTotal}`);

    const appendIcon = (src, alt) => {
        const img = document.createElement("img");
        img.src = src;
        img.alt = alt;
        img.width = 20;
        img.height = 20;
        wrapper.appendChild(img);
    };

    for (let i = 0; i < primaryCount; i++) {
        appendIcon(primaryIconPath, label);
    }

    for (let i = 0; i < secondaryCount; i++) {
        appendIcon(secondaryIconPath, label);
    }

    return wrapper;
}

function renderCharacterHealthSection(character) {
    const container = document.getElementById("healthSectionContent");
    if (!container || !character) return;

    const canEditHealth = canEditHealthForCharacter(character);
    const canEditFreeHealth = canEditFreeHealthForCharacter(character);
    const remainingExperience = getRemainingExperience(character);

    const rows = [
        {
            label: "Fysieke gezondheid",
            value: getPhysicalHealthValue(character),
            paidField: "physicalHealth",
            freeField: "physicalHealthFree",
            primaryIconPath: "img/heart-red.png",
            secondaryIconPath: "img/heart-green.png"
        },
        {
            label: "Mentale gezondheid",
            value: getMentalHealthValue(character),
            paidField: "mentalHealth",
            freeField: "mentalHealthFree",
            primaryIconPath: "img/eye-yellow.png",
            secondaryIconPath: "img/eye-white.png"
        }
    ];

    container.innerHTML = "";

    rows.forEach((rowConfig) => {
        const paidValue = getCharacterIntValue(character, rowConfig.paidField);
        const freeValue = getCharacterIntValue(character, rowConfig.freeField);
        const totalValue = rowConfig.value;

        const row = document.createElement("div");
        row.className = "d-flex justify-content-between align-items-start gap-3 mb-3";

        const labelCol = document.createElement("div");
        labelCol.className = "me-2 d-flex flex-wrap align-items-center";

        const labelText = document.createElement("span");
        labelText.textContent = rowConfig.label;
        labelCol.appendChild(labelText);
        labelCol.appendChild(
            createHealthIconStrip(
                rowConfig.value,
                rowConfig.primaryIconPath,
                rowConfig.secondaryIconPath,
                rowConfig.label
            )
        );
        row.appendChild(labelCol);

        if (canEditHealth) {
            const controls = document.createElement("div");
            controls.className = "d-flex flex-column align-items-end gap-1";

            const paidControls = document.createElement("div");
            paidControls.className = "btn-group btn-group-sm";

            const minusBtn = document.createElement("button");
            minusBtn.type = "button";
            minusBtn.className = "btn btn-outline-secondary";
            minusBtn.dataset.action = "character-health-adjust";
            minusBtn.dataset.field = rowConfig.paidField;
            minusBtn.dataset.delta = "-1";
            minusBtn.innerHTML = `<i class="fa-solid fa-minus"></i>`;
            minusBtn.disabled = paidValue <= -3 || totalValue <= 1;
            paidControls.appendChild(minusBtn);

            const plusBtn = document.createElement("button");
            plusBtn.type = "button";
            plusBtn.className = "btn btn-outline-secondary";
            plusBtn.dataset.action = "character-health-adjust";
            plusBtn.dataset.field = rowConfig.paidField;
            plusBtn.dataset.delta = "1";
            plusBtn.innerHTML = `<i class="fa-solid fa-plus"></i>`;
            plusBtn.disabled = character.type === "player" && remainingExperience < CHARACTER_HEALTH_STEP_COST;
            paidControls.appendChild(plusBtn);

            controls.appendChild(paidControls);

            if (canEditFreeHealth) {
                const freeControls = document.createElement("div");
                freeControls.className = "d-flex align-items-center gap-2";

                const freeLabel = document.createElement("span");
                freeLabel.className = "small text-nowrap";
                freeLabel.textContent = "gratis speciale aanpassing";
                freeControls.appendChild(freeLabel);

                const freeButtons = document.createElement("div");
                freeButtons.className = "btn-group btn-group-sm";

                const freeMinusBtn = document.createElement("button");
                freeMinusBtn.type = "button";
                freeMinusBtn.className = "btn btn-outline-secondary";
                freeMinusBtn.dataset.action = "character-health-adjust";
                freeMinusBtn.dataset.field = rowConfig.freeField;
                freeMinusBtn.dataset.delta = "-1";
                freeMinusBtn.innerHTML = `<i class="fa-solid fa-minus"></i>`;
                freeMinusBtn.disabled = totalValue <= 1;
                freeButtons.appendChild(freeMinusBtn);

                const freePlusBtn = document.createElement("button");
                freePlusBtn.type = "button";
                freePlusBtn.className = "btn btn-outline-secondary";
                freePlusBtn.dataset.action = "character-health-adjust";
                freePlusBtn.dataset.field = rowConfig.freeField;
                freePlusBtn.dataset.delta = "1";
                freePlusBtn.innerHTML = `<i class="fa-solid fa-plus"></i>`;
                freeButtons.appendChild(freePlusBtn);

                freeControls.appendChild(freeButtons);
                controls.appendChild(freeControls);
            }

            row.appendChild(controls);
        }

        container.appendChild(row);
    });
}

async function handleAutoSaveField(e) {
    const idCharacter = document.getElementById("idCharacter").value;
    if (!idCharacter) {
        // Nog geen personage-id ⇒ nieuwe personages worden via de Aanmaken-knop gemaakt
        return;
    }

    const field = e.target.id;
    const value = e.target.value;

    const dataObject = {
        id: idCharacter
    };
    dataObject[field] = value;

    try {
        await apiFetchJson("api/characters/updateCharacter.php", {
            method: "POST",
            body: dataObject
        });

        if (currentCharacter) {
            currentCharacter[field] = value;
        }

        if (field === "class") {
            getCharacter(idCharacter);
        }
    } catch (err) {
        console.error("Fout bij autosave van veld", field, err);
    }
}

function renderCharacterDetailsReadOnly(character) {
    const container = document.getElementById("characterForm");
    if (!container) return;

    // Simpele formatter voor geboortedatum (db-formaat -> gewoon tonen)
    const birthDate = character.birthDate && character.birthDate !== "0001-01-01"
        ? character.birthDate
        : "";

    const streetLine = [character.street, character.houseNumber].filter(Boolean).join(" ");
    const cityLine = [character.postalCode, character.municipality].filter(Boolean).join(" ");
    const birthLine = [character.birthPlace, birthDate].filter(Boolean).join(" / ");

    container.innerHTML = `
        <div class="card character-sheet-card mb-4">
            <div class="card-body">
                <div class="character-sheet-header mb-3">
                    <div class="character-portrait-panel">
                        <div class="character-portrait-frame">
                            <img
                                id="characterPortraitImage"
                                class="character-portrait-image d-none"
                                src=""
                                alt="Portret personage"
                            >
                            <div id="characterPortraitPlaceholder" class="character-portrait-placeholder">Geen portret</div>
                        </div>
                        <div class="character-portrait-actions">
                            <input type="file" id="characterPortraitFileInput" class="d-none" accept="image/*">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="characterPortraitUploadButton">Portret opladen</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="characterPortraitDeleteButton">Verwijderen</button>
                        </div>
                    </div>
                    <div class="character-sheet-header-fields">
                        <div class="mb-3 row" id="classRow">
                            <div class="col-sm-4">Klasse</div>
                            <div class="col-sm-8">${character.class || ""}</div>
                        </div>
                        <div class="mb-3 row">
                            <div class="col-sm-4">Aanspreking</div>
                            <div class="col-sm-8">${character.title || ""}</div>
                        </div>
                        <div class="mb-3 row">
                            <label class="col-sm-4">Familienaam</label>
                            <div class="col-sm-8">${character.lastName || ""}</div>
                        </div>
                        <div class="mb-3 row">
                            <label class="col-sm-4">Voornaam</label>
                            <div class="col-sm-8">${character.firstName || ""}</div>
                        </div>
                    </div>
                </div>
                <div class="mb-3 row">
                    <div class="col-sm-4">Straat en nummer</div>
                    <div class="col-sm-6">${streetLine}</div>
                </div>
                <div class="mb-3 row">
                    <div class="col-sm-4">Postcode en gemeente</div>
                    <div class="col-sm-6">${cityLine}</div>
                </div>
                <div class="mb-3 row">
                    <div class="col-sm-4">Burgelijke staat</div>
                    <div class="col-sm-6">${character.maritalStatus || ""}</div>
                </div>
                <div class="mb-3 row">
                    <div class="col-sm-4">Nationaliteit</div>
                    <div class="col-sm-6">${character.nationality || ""}</div>
                </div>
                <div class="mb-3 row" id="birthRow">
                    <div class="col-sm-4">Geboren te / op</div>
                    <div class="col-sm-6">${birthLine}</div>
                </div>
                ${(character.class === "upper class" || character.class === "middle class" || character.class === "lower class") ? `
                <div id="leftTraitModuleHost"></div>
                ` : ""}
                <div class="mb-3 row">
                    <div class="col-sm-4">Rijksregisternummer</div>
                    <div class="col-sm-6">${character.stateRegisterNumber || ""}</div>
                </div>
            </div>
        </div>
        <div class="card character-sheet-card mb-4">
            <div class="card-body">
                <h4 class="character-sheet-card-title">Gezondheid</h4>
                <div id="healthSectionContent"></div>
            </div>
        </div>
        <div class="card character-sheet-card" id="leftInfoSections">
            <div class="card-body">
                <h4 class="character-sheet-card-title" id="livingStandardSectionTitle">Levensstandaard</h4>
                <div class="mb-4" id="livingStandardSectionContent"></div>
                <h4 class="character-sheet-card-title" id="staffSectionTitle">Personeel</h4>
                <div class="mb-4" id="staffSectionContent"></div>
                <h4 class="character-sheet-card-title" id="languagesSectionTitle">Talen</h4>
                <div id="languagesSectionContent"></div>
            </div>
        </div>
    `;
}

function applyCharacterEditability(character, canEdit) {
    // 1. Characterformulier: inputs wel/niet bewerkbaar
    const formFields = [
        "firstName",
        "lastName",
        "class",
        "birthDate",
        "birthPlace",
        "nationality",
        "stateRegisterNumber",
        "street",
        "houseNumber",
        "municipality",
        "postalCode",
        "title",
        "maritalStatus"
    ];

    formFields.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (canEdit) {
            el.removeAttribute("disabled");
            el.classList.remove("form-control-plaintext");
        } else {
            el.setAttribute("disabled", "disabled");
        }
    });

    // 2. Nieuwe skill toevoegen (dropdown + plusknop)
    const idNewSkill = document.getElementById("idNewSkill");
    const btnAddSkill = document.getElementById("addNewSkill");

    if (idNewSkill) {
        if (canEdit) {
            idNewSkill.removeAttribute("disabled");
        } else {
            idNewSkill.setAttribute("disabled", "disabled");
        }
    }

    if (btnAddSkill) {
        if (canEdit) {
            btnAddSkill.removeAttribute("disabled");
            btnAddSkill.classList.remove("disabled");
        } else {
            btnAddSkill.setAttribute("disabled", "disabled");
            btnAddSkill.classList.add("disabled");
        }
    }

    // 3. Alle skill-knoppen (Learn/Unlearn/Delete + specialisaties) blokkeren
    const accordion = document.getElementById("accordionSkills");
    if (accordion) {
        const buttons = accordion.querySelectorAll("button[data-action]");
        buttons.forEach((btn) => {
            if (canEdit) {
                return; // XP-logica blijft zijn werk doen
            }
            btn.setAttribute("disabled", "disabled");
            btn.classList.add("disabled");
        });
    }
}

async function updateCharacterHealthField(field, delta) {
    if (!currentCharacter?.id || !field) return;

    const currentValue = getCharacterIntValue(currentCharacter, field);
    const nextValue = currentValue + Number(delta || 0);
    const isPhysicalField = field === "physicalHealth" || field === "physicalHealthFree";
    const paidField = isPhysicalField ? "physicalHealth" : "mentalHealth";
    const freeField = isPhysicalField ? "physicalHealthFree" : "mentalHealthFree";
    const nextPaidValue = field === paidField
        ? nextValue
        : getCharacterIntValue(currentCharacter, paidField);
    const nextFreeValue = field === freeField
        ? nextValue
        : getCharacterIntValue(currentCharacter, freeField);
    const nextTotalValue = CHARACTER_BASE_HEALTH + nextPaidValue + nextFreeValue;

    if ((field === "physicalHealth" || field === "mentalHealth") && nextValue < -3) {
        return;
    }

    if (nextTotalValue < 1) {
        return;
    }

    try {
        await updateCharacter({
            id: currentCharacter.id,
            [field]: nextValue
        });

        const openCollapseId = typeof getOpenSkillCollapseId === "function"
            ? getOpenSkillCollapseId()
            : null;
        await getCharacter(currentCharacter.id, openCollapseId);
    } catch (err) {
        console.error("Fout bij aanpassen van gezondheid:", err);
    }
}

function setupHealthSectionListeners() {
    if (window.aetherHealthListenersInitialized) return;
    window.aetherHealthListenersInitialized = true;

    document.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-action='character-health-adjust']");
        if (!btn || btn.disabled || btn.classList.contains("disabled")) return;

        const field = btn.dataset.field || "";
        const delta = Number(btn.dataset.delta || 0);
        if (!field || !delta) return;

        await updateCharacterHealthField(field, delta);
    });
}

setupHealthSectionListeners();
