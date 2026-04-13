const PASSPORT_PDF_PT_PER_MM = 72 / 25.4;
const PASSPORT_PDF_WIDTH_PT = 87 * PASSPORT_PDF_PT_PER_MM;
const PASSPORT_PDF_HEIGHT_PT = 125 * PASSPORT_PDF_PT_PER_MM;
const PASSPORT_PDF_A4_WIDTH_PT = 210 * PASSPORT_PDF_PT_PER_MM;
const PASSPORT_PDF_A4_HEIGHT_PT = 297 * PASSPORT_PDF_PT_PER_MM;
const PASSPORT_PDF_GUIDE_OFFSET_PT = 5 * PASSPORT_PDF_PT_PER_MM;
const PASSPORT_PDF_CONTENT_MARGIN_PT = 24;
const PASSPORT_PDF_FOOTER_PT = 14;
const PASSPORT_PDF_BODY_FONT_SIZE = 7.1;
const PASSPORT_PDF_BODY_LINE_HEIGHT = 9;
const PASSPORT_PDF_SMALL_FONT_SIZE = 6.2;
const PASSPORT_PDF_SMALL_LINE_HEIGHT = 7.5;
const PASSPORT_PDF_HEADING_FONT_SIZE = 7.6;
const PASSPORT_PDF_HEADING_LINE_HEIGHT = 10;
const PASSPORT_PDF_TITLE_FONT_SIZE = 10.5;
const PASSPORT_PDF_TITLE_LINE_HEIGHT = 13;
const PASSPORT_PDF_CHAR_WIDTH_FACTOR = 0.6;
const PASSPORT_PDF_FOLD_LINE_DASH_PT = [4, 3];
const PASSPORT_PDF_GUIDE_LINE_WIDTH_PT = 1;
const PASSPORT_PDF_GUIDE_MARK_LENGTH_PT = 18;
const PASSPORT_PDF_PAGE_BORDER_LINE_WIDTH_PT = 0.2;
const PASSPORT_PDF_PAGE_BORDER_DASH_PT = [1.2, 1.8];
const PASSPORT_PDF_BACKGROUND_IMAGE_PATH_TWO_UP = "img/paspoorten/paspoort_achterkant_1_katern.webp";
const PASSPORT_PDF_BACKGROUND_IMAGE_PATH_FOUR_UP = "img/paspoorten/paspoort_achterkant_2_katernen.webp";
const PASSPORT_PDF_BLEED_PT = 5 * PASSPORT_PDF_PT_PER_MM;
const PASSPORT_PDF_RENDER_SCALE = 3;
const PASSPORT_PDF_FONT_REGULAR_PATH = "fonts/CrimsonText-Regular.ttf";
const PASSPORT_PDF_FONT_BOLD_PATH = "fonts/CrimsonText-Bold.ttf";
const PASSPORT_PDF_FONT_REGULAR_NAME = "CrimsonText-Regular";
const PASSPORT_PDF_FONT_BOLD_NAME = "CrimsonText-Bold";
const PASSPORT_PDF_FONT_SCRIPT_PATH = "fonts/MySoul-Regular.ttf";
const PASSPORT_PDF_FONT_SCRIPT_NAME = "MySoul-Regular";
const PASSPORT_PDF_STAMP_IMAGE_PATH = "img/paspoorten/stempel-burgerlijke-stand-rood.png";
const PASSPORT_PDF_HANDWRITING_COLOR = [0.12, 0.24, 0.55];
const PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR = 0.42;
const PASSPORT_PDF_BOLD_LABEL_WIDTH_FACTOR = 0.54;

window.aetherPassportSelections = {
    personal: true,
    economy: true,
    traits: false,
    skills: false,
    printMode: "paper-save",
    ...(window.aetherPassportSelections || {})
};

function showPassportTab(character) {
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
    if (economyTab) economyTab.classList.add("d-none");

    const passportTab = document.getElementById("passportTab");
    if (!passportTab) return;

    passportTab.classList.remove("d-none");
    passportTab.style.display = "block";
    passportTab.hidden = false;
    passportTab.classList.add("p-3");
    passportTab.style.backgroundColor = "#fff";
    passportTab.style.minHeight = "300px";

    renderPassportTab(currentCharacter || character);
}

function getPassportPageCharacterName(character) {
    const title = String(character?.title || "").trim();
    const firstName = String(character?.firstName || "").trim();
    const lastName = String(character?.lastName || "").trim();
    return [title, firstName, lastName].filter(Boolean).join(" ") || "Onbekend personage";
}

function getPassportTextContent(value) {
    const text = String(value ?? "");
    if (!text) return "";

    const temp = document.createElement("div");
    temp.innerHTML = text;
    return temp.textContent || temp.innerText || "";
}

function normalizePassportPdfText(value) {
    const replacements = {
        "\u2018": "'",
        "\u2019": "'",
        "\u201C": "\"",
        "\u201D": "\"",
        "\u2013": "-",
        "\u2014": "-",
        "\u2026": "...",
        "\u00A0": " ",
        "\u2022": "-",
        "\u20AC": "EUR"
    };

    let normalized = getPassportTextContent(value);
    Object.entries(replacements).forEach(([searchValue, replacement]) => {
        normalized = normalized.split(searchValue).join(replacement);
    });

    normalized = normalized
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\r/g, "")
        .replace(/\t/g, "    ")
        .replace(/[^\x20-\x7E\u00A0-\u00FF\n]/g, " ")
        .replace(/[ ]{2,}/g, " ");

    return normalized.trim();
}

function escapePassportPdfString(value) {
    return normalizePassportPdfText(value)
        .replace(/\\/g, "\\\\")
        .replace(/\(/g, "\\(")
        .replace(/\)/g, "\\)")
        .replace(/\u00B0/g, "\\260");
}

function getPassportStyleMetrics(style) {
    if (style === "title") {
        return {
            font: "bold",
            size: PASSPORT_PDF_TITLE_FONT_SIZE,
            lineHeight: PASSPORT_PDF_TITLE_LINE_HEIGHT
        };
    }

    if (style === "heading") {
        return {
            font: "bold",
            size: PASSPORT_PDF_HEADING_FONT_SIZE,
            lineHeight: PASSPORT_PDF_HEADING_LINE_HEIGHT
        };
    }

    if (style === "small") {
        return {
            font: "regular",
            size: PASSPORT_PDF_SMALL_FONT_SIZE,
            lineHeight: PASSPORT_PDF_SMALL_LINE_HEIGHT
        };
    }

    return {
        font: "regular",
        size: PASSPORT_PDF_BODY_FONT_SIZE,
        lineHeight: PASSPORT_PDF_BODY_LINE_HEIGHT
    };
}

function getPassportMaxChars(style, indent = 0) {
    const metrics = getPassportStyleMetrics(style);
    const contentWidth = PASSPORT_PDF_WIDTH_PT - (PASSPORT_PDF_CONTENT_MARGIN_PT * 2) - indent;
    return Math.max(10, Math.floor(contentWidth / (metrics.size * PASSPORT_PDF_CHAR_WIDTH_FACTOR)));
}

function wrapPassportText(text, style = "body", indent = 0) {
    const normalized = normalizePassportPdfText(text);
    if (!normalized) {
        return [];
    }

    const maxChars = getPassportMaxChars(style, indent);
    const paragraphs = normalized.split("\n");
    const lines = [];

    paragraphs.forEach((paragraph, paragraphIndex) => {
        const words = paragraph.split(" ").filter(Boolean);
        if (words.length === 0) {
            if (paragraphIndex < paragraphs.length - 1) {
                lines.push("");
            }
            return;
        }

        let currentLine = "";
        words.forEach((word) => {
            const nextLine = currentLine ? `${currentLine} ${word}` : word;
            if (nextLine.length <= maxChars) {
                currentLine = nextLine;
                return;
            }

            if (currentLine) {
                lines.push(currentLine);
            }

            if (word.length <= maxChars) {
                currentLine = word;
                return;
            }

            let remaining = word;
            while (remaining.length > maxChars) {
                lines.push(remaining.slice(0, maxChars - 1) + "-");
                remaining = remaining.slice(maxChars - 1);
            }
            currentLine = remaining;
        });

        if (currentLine) {
            lines.push(currentLine);
        }

        if (paragraphIndex < paragraphs.length - 1) {
            lines.push("");
        }
    });

    return lines;
}

function wrapPassportTextToWidth(text, fontSize, availableWidth, widthFactor = PASSPORT_PDF_CHAR_WIDTH_FACTOR) {
    const normalized = normalizePassportPdfText(text);
    if (!normalized) {
        return [];
    }

    const maxChars = Math.max(8, Math.floor(availableWidth / (fontSize * widthFactor)));
    const paragraphs = normalized.split("\n");
    const lines = [];

    paragraphs.forEach((paragraph, paragraphIndex) => {
        const words = paragraph.split(" ").filter(Boolean);
        if (words.length === 0) {
            if (paragraphIndex < paragraphs.length - 1) {
                lines.push("");
            }
            return;
        }

        let currentLine = "";
        words.forEach((word) => {
            const nextLine = currentLine ? `${currentLine} ${word}` : word;
            if (nextLine.length <= maxChars) {
                currentLine = nextLine;
                return;
            }

            if (currentLine) {
                lines.push(currentLine);
            }

            if (word.length <= maxChars) {
                currentLine = word;
                return;
            }

            let remaining = word;
            while (remaining.length > maxChars) {
                lines.push(remaining.slice(0, maxChars - 1) + "-");
                remaining = remaining.slice(maxChars - 1);
            }
            currentLine = remaining;
        });

        if (currentLine) {
            lines.push(currentLine);
        }

        if (paragraphIndex < paragraphs.length - 1) {
            lines.push("");
        }
    });

    return lines;
}

function wrapPassportTextWithFirstLineWidth(
    text,
    fontSize,
    firstLineWidth,
    laterLineWidth,
    widthFactor = PASSPORT_PDF_CHAR_WIDTH_FACTOR
) {
    const normalized = normalizePassportPdfText(text);
    if (!normalized) {
        return [];
    }

    const firstMaxChars = Math.max(8, Math.floor(firstLineWidth / (fontSize * widthFactor)));
    const laterMaxChars = Math.max(8, Math.floor(laterLineWidth / (fontSize * widthFactor)));
    const paragraphs = normalized.split("\n");
    const lines = [];
    let isFirstRenderedLine = true;

    paragraphs.forEach((paragraph, paragraphIndex) => {
        const words = paragraph.split(" ").filter(Boolean);
        if (words.length === 0) {
            if (paragraphIndex < paragraphs.length - 1) {
                lines.push("");
                isFirstRenderedLine = false;
            }
            return;
        }

        let currentLine = "";
        words.forEach((word) => {
            let currentMaxChars = isFirstRenderedLine ? firstMaxChars : laterMaxChars;
            let nextLine = currentLine ? `${currentLine} ${word}` : word;

            if (nextLine.length <= currentMaxChars) {
                currentLine = nextLine;
                return;
            }

            if (currentLine) {
                lines.push(currentLine);
                currentLine = "";
                isFirstRenderedLine = false;
                currentMaxChars = laterMaxChars;
            }

            if (word.length <= currentMaxChars) {
                currentLine = word;
                return;
            }

            let remaining = word;
            while (remaining.length > currentMaxChars) {
                lines.push(remaining.slice(0, currentMaxChars - 1) + "-");
                remaining = remaining.slice(currentMaxChars - 1);
                isFirstRenderedLine = false;
                currentMaxChars = laterMaxChars;
            }
            currentLine = remaining;
        });

        if (currentLine) {
            lines.push(currentLine);
            isFirstRenderedLine = false;
        }

        if (paragraphIndex < paragraphs.length - 1) {
            lines.push("");
        }
    });

    return lines;
}

function capitalizePassportFirstLetter(value) {
    const normalized = normalizePassportPdfText(value);
    if (!normalized) return "";
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function normalizePassportInlineText(value) {
    return normalizePassportPdfText(value)
        .replace(/\n+/g, " ")
        .replace(/[ ]{2,}/g, " ")
        .trim();
}

function createPassportLines(text, style = "body", indent = 0) {
    const metrics = getPassportStyleMetrics(style);
    return wrapPassportText(text, style, indent).map((lineText) => ({
        type: "text",
        text: lineText,
        style,
        indent,
        font: metrics.font,
        size: metrics.size,
        lineHeight: metrics.lineHeight
    }));
}

function createPassportSpacer(height = 4) {
    return {
        type: "spacer",
        height
    };
}

function createPassportSectionPages(sectionTitle, subtitle, lineItems) {
    const pages = [];
    const usableHeight = PASSPORT_PDF_HEIGHT_PT
        - (PASSPORT_PDF_CONTENT_MARGIN_PT * 2)
        - PASSPORT_PDF_FOOTER_PT
        - PASSPORT_PDF_TITLE_LINE_HEIGHT
        - (subtitle ? PASSPORT_PDF_SMALL_LINE_HEIGHT : 0)
        - 6;

    let currentPage = {
        sectionTitle,
        subtitle,
        lines: [],
        usedHeight: 0
    };

    const pushPage = () => {
        pages.push({
            sectionTitle,
            subtitle,
            lines: currentPage.lines.slice()
        });
        currentPage = {
            sectionTitle,
            subtitle,
            lines: [],
            usedHeight: 0
        };
    };

    lineItems.forEach((item) => {
        const itemHeight = item.type === "spacer"
            ? item.height
            : item.lineHeight;

        if (currentPage.lines.length > 0 && currentPage.usedHeight + itemHeight > usableHeight) {
            pushPage();
        }

        currentPage.lines.push(item);
        currentPage.usedHeight += itemHeight;
    });

    if (currentPage.lines.length > 0 || pages.length === 0) {
        pushPage();
    }

    return pages.map((page, index) => ({
        sectionTitle: page.sectionTitle,
        subtitle: index === 0 ? page.subtitle : `${page.subtitle} - vervolg`,
        lines: page.lines
    }));
}

function createSinglePassportPage(sectionTitle, subtitle, lineItems) {
    return [{
        sectionTitle,
        subtitle,
        lines: fitPassportLinesToSinglePage(lineItems, subtitle)
    }];
}

function formatPassportLongDate(value) {
    const input = String(value || "").trim();
    if (!input || input === "0001-01-01") return "";

    const match = input.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return input;

    const [, year, month, day] = match;
    const monthNames = [
        "januari",
        "februari",
        "maart",
        "april",
        "mei",
        "juni",
        "juli",
        "augustus",
        "september",
        "oktober",
        "november",
        "december"
    ];

    const monthIndex = Math.max(0, Math.min(11, Number(month) - 1));
    return `${Number(day)} ${monthNames[monthIndex]} ${year}`;
}

function getPassportPrimaryProfessionTrait(character) {
    const professionGroups = Array.isArray(character?.professionGroups) ? character.professionGroups : [];
    const traits = professionGroups.flatMap((group) => Array.isArray(group?.linkedTraits) ? group.linkedTraits : []);
    if (traits.length === 0) return null;

    const nonMilitary = traits.find((trait) => {
        const traitGroup = String(trait?.traitGroup || "").trim().toLowerCase();
        return traitGroup !== "leger" && traitGroup !== "militair";
    });

    return nonMilitary || traits[0];
}

function getPassportMilitaryRankTrait(character) {
    const militaryRankTraitIds = new Set([
        23, 24, 25, 26, 27,
        121, 122, 123, 124, 125, 126, 127,
        208, 209, 210, 211, 212
    ]);

    const groups = [
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : []),
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : [])
    ];

    for (const group of groups) {
        const linkedTraits = Array.isArray(group?.linkedTraits) ? group.linkedTraits : [];
        const militaryRankTrait = linkedTraits.find((trait) => (
            militaryRankTraitIds.has(Number(trait?.idTrait || trait?.id || 0))
        ));

        if (militaryRankTrait) {
            return militaryRankTrait;
        }
    }

    return null;
}

function getPassportResidenceLines(character) {
    const streetLine = [character?.street, character?.houseNumber].filter(Boolean).join(" ").trim();
    const cityLine = [character?.postalCode, character?.municipality].filter(Boolean).join(" ").trim();
    return [streetLine, cityLine].filter(Boolean);
}

function getPassportRingCounts(total) {
    const safeTotal = Math.max(0, Number(total) || 0);
    return {
        primary: Math.ceil(safeTotal / 2),
        secondary: Math.floor(safeTotal / 2)
    };
}

function buildPassportHealthReferencePage(character) {
    const physicalCounts = getPassportRingCounts(
        typeof getPhysicalHealthValue === "function" ? getPhysicalHealthValue(character) : 0
    );
    const mentalCounts = getPassportRingCounts(
        typeof getMentalHealthValue === "function" ? getMentalHealthValue(character) : 0
    );

    return {
        sectionTitle: "",
        subtitle: "",
        lines: [],
        template: "health_reference",
        templateData: {
            physicalGreenCount: physicalCounts.secondary,
            physicalRedCount: physicalCounts.primary,
            mentalWhiteCount: mentalCounts.secondary,
            mentalYellowCount: mentalCounts.primary
        }
    };
}

function buildPassportPersonalPageFields(character) {
    const isUpperClass = String(character?.class || "").trim().toLowerCase() === "upper class";
    const professionTrait = getPassportPrimaryProfessionTrait(character);
    const militaryRankTrait = getPassportMilitaryRankTrait(character);
    const birthValue = [String(character?.birthPlace || "").trim(), formatPassportLongDate(character?.birthDate)].filter(Boolean).join(" - ");
    const residenceLines = getPassportResidenceLines(character);

    const fields = [
        { label: "naam - nom - name", valueLines: [String(character?.lastName || "").trim()] },
        { label: "voornaam - prénom - first name", valueLines: [String(character?.firstName || "").trim()] },
        { label: "burgerlijke staat - etat civil - marital status", valueLines: [String(character?.maritalStatus || "").trim()] },
        { label: "geboren te / op - ne en / en - born at / on", valueLines: [birthValue] },
        { label: "nationaliteit - nationalite - nationality", valueLines: [String(character?.nationality || "").trim()] },
        isUpperClass
            ? { label: "predicaat - predicat - predicate", valueLines: [String(character?.title || "").trim()] }
            : { label: "aanspreking - adresse - address", valueLines: [String(character?.title || "").trim()] },
        { label: "woonplaats - residence - place of residence", valueLines: residenceLines.length > 0 ? residenceLines : [""] }
    ];

    if (!isUpperClass) {
        fields.splice(6, 0, {
            label: "beroep - profession - profession",
            valueLines: [String(professionTrait?.name || "").trim()]
        });
    }

    if (militaryRankTrait && String(militaryRankTrait?.name || "").trim()) {
        fields.push({
            label: "militaire rang - grade militaire - military rank",
            valueLines: [String(militaryRankTrait?.name || "").trim()]
        });
    }

    return fields;
}

function buildPassportPersonalPages(character) {
    return [{
        sectionTitle: "",
        subtitle: "",
        lines: [],
        template: "personal_identity_front",
        templateData: {
            fields: buildPassportPersonalPageFields(character),
            portraitUrl: String(character?.portraitUrl || "").trim(),
            stateRegisterNumber: String(character?.stateRegisterNumber || "").trim()
        }
    }, buildPassportHealthReferencePage(character)];
}

function buildPassportEconomyPages(character) {
    const recurringIncomeTotal = Number(character?.recurringIncomeTotal);
    const totalIncome = Number.isFinite(recurringIncomeTotal)
        ? recurringIncomeTotal
        : (typeof getCharacterTraitIncomeTotal === "function"
            ? getCharacterTraitIncomeTotal(character)
            : 0);
    const securitiesAmount = Number(character?.securitiesaccount || 0);
    const companyShares = Array.isArray(character?.companyShares) ? character.companyShares : [];
    const livingStandardText = typeof getCharacterLivingStandardText === "function"
        ? normalizePassportInlineText(getCharacterLivingStandardText(character))
        : "";

    return [{
        sectionTitle: "",
        subtitle: "",
        lines: [],
        template: "wealth_overview",
        templateData: {
            title: "Welvaart",
            monthlyIncome: formatCharacterCurrency(totalIncome),
            livingStandardText,
            shareEntries: companyShares.map((share) => ({
                companyName: normalizePassportPdfText(
                    String(share?.companyName || "").trim() || String(share?.name || "").trim() || "Onbekend bedrijf"
                ),
                percentageLabel: `${Number(share?.percentage || 0)}% aandelen`
            })),
            securitiesAmount: securitiesAmount > 0 ? formatCharacterCurrency(securitiesAmount) : ""
        }
    }];
}

function getPassportTraitDisplayLabel(trait) {
    const name = String(trait?.name || "").trim() || `Trait #${trait?.idTrait || trait?.id || "?"}`;
    const rank = Number(trait?.rank ?? 1);
    const rankType = String(trait?.rankType || "singular");
    if (rankType !== "singular" && rank > 1) {
        return `${name} (rang ${rank})`;
    }
    return name;
}

function buildPassportTraitPages(character) {
    const groups = [
        ...(Array.isArray(character?.traitGroups) ? character.traitGroups : []),
        ...(Array.isArray(character?.professionGroups) ? character.professionGroups : [])
    ];
    const traitEntries = [];

    groups.forEach((group) => {
        const linkedTraits = Array.isArray(group?.linkedTraits) ? group.linkedTraits : [];
        linkedTraits.forEach((trait) => {
            const label = getPassportTraitDisplayLabel(trait);
            const description = normalizePassportInlineText(trait?.description || "");
            const fullWidth = PASSPORT_PDF_WIDTH_PT - (PASSPORT_PDF_GUIDE_OFFSET_PT * 2);
            const descriptionLines = description
                ? wrapPassportTextToWidth(
                    description,
                    7,
                    fullWidth,
                    PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
                )
                : [];
            traitEntries.push({
                label,
                description,
                estimatedHeight: 8.4 + (descriptionLines.length * 8.4) + 3
            });
        });
    });

    if (traitEntries.length === 0) {
        return [{
            sectionTitle: "",
            subtitle: "",
            lines: [],
            template: "trait_overview",
            templateData: {
                title: "Statuseigenschappen",
                entries: []
            }
        }];
    }

    const pages = [];
    const usableHeight = PASSPORT_PDF_HEIGHT_PT - (PASSPORT_PDF_GUIDE_OFFSET_PT * 2) - 28;
    let currentEntries = [];
    let usedHeight = 0;

    traitEntries.forEach((entry) => {
        if (currentEntries.length > 0 && usedHeight + entry.estimatedHeight > usableHeight) {
            pages.push({
                sectionTitle: "",
                subtitle: "",
                lines: [],
                template: "trait_overview",
                templateData: {
                    title: "Statuseigenschappen",
                    entries: currentEntries
                }
            });
            currentEntries = [];
            usedHeight = 0;
        }

        currentEntries.push(entry);
        usedHeight += entry.estimatedHeight;
    });

    if (currentEntries.length > 0) {
        pages.push({
            sectionTitle: "",
            subtitle: "",
            lines: [],
            template: "trait_overview",
            templateData: {
                title: "Statuseigenschappen",
                entries: currentEntries
            }
        });
    }

    return pages;
}

function getPassportSkillLevelLabel(skill) {
    const level = Number(skill?.level || 0);
    if (level >= 3) return "Meester";
    if (level === 2) return "Deskundige";
    if (level === 1) return "Beginneling";
    return "Ongetraind";
}

function fitPassportLinesToSinglePage(lines, subtitle) {
    const usableHeight = PASSPORT_PDF_HEIGHT_PT
        - (PASSPORT_PDF_CONTENT_MARGIN_PT * 2)
        - PASSPORT_PDF_FOOTER_PT
        - PASSPORT_PDF_TITLE_LINE_HEIGHT
        - (subtitle ? PASSPORT_PDF_SMALL_LINE_HEIGHT : 0)
        - 6;

    const fitted = [];
    let usedHeight = 0;
    for (const line of lines) {
        const itemHeight = line.type === "spacer" ? line.height : line.lineHeight;
        if (usedHeight + itemHeight > usableHeight) {
            const ellipsisLine = createPassportLines("...", "small");
            ellipsisLine.forEach((ellipsisItem) => {
                const ellipsisHeight = ellipsisItem.lineHeight;
                if (usedHeight + ellipsisHeight <= usableHeight) {
                    fitted.push(ellipsisItem);
                    usedHeight += ellipsisHeight;
                }
            });
            break;
        }

        fitted.push(line);
        usedHeight += itemHeight;
    }

    return fitted;
}

function buildPassportSkillPages(character) {
    const skills = Array.isArray(character?.skills) ? character.skills : [];

    return skills.map((skill) => {
        const level = Number(skill?.level || 0);
        const levelSections = [];
        if (level >= 1 && String(skill?.beginner || "").trim()) {
            levelSections.push({ level: 1, text: normalizePassportInlineText(skill.beginner || "") });
        }
        if (level >= 2 && String(skill?.professional || "").trim()) {
            levelSections.push({ level: 2, text: normalizePassportInlineText(skill.professional || "") });
        }
        if (level >= 3 && String(skill?.master || "").trim()) {
            levelSections.push({ level: 3, text: normalizePassportInlineText(skill.master || "") });
        }

        return {
            sectionTitle: "",
            subtitle: "",
            lines: [],
            template: "skill_detail",
            templateData: {
                name: capitalizePassportFirstLetter(skill?.name || "Onbekende vaardigheid"),
                levelLabel: getPassportSkillLevelLabel(skill),
                description: normalizePassportInlineText(skill?.description || "") || "Geen beschrijving.",
                levelSections
            }
        };
    });
}

function getPassportSectionDefinitions(character) {
    const traitPages = buildPassportTraitPages(character);
    const skillPages = buildPassportSkillPages(character);
    const linkedTraits = typeof getCharacterLinkedTraits === "function"
        ? getCharacterLinkedTraits(character)
        : [];

    return [
        {
            id: "personal",
            title: "Persoonsgegevens",
            required: true,
            available: true,
            pageCount: 2,
            getPages: () => buildPassportPersonalPages(character)
        },
        {
            id: "economy",
            title: "Economie",
            required: false,
            available: true,
            pageCount: 1,
            getPages: () => buildPassportEconomyPages(character)
        },
        {
            id: "traits",
            title: "Statuseigenschappen",
            required: false,
            pageCount: traitPages.length,
            available: linkedTraits.length > 0,
            getPages: () => traitPages
        },
        {
            id: "skills",
            title: "Vaardigheden",
            required: false,
            pageCount: skillPages.length,
            available: skillPages.length > 0,
            getPages: () => skillPages
        }
    ];
}

function renderPassportSectionOption(section, selected) {
    const wrapper = document.createElement("label");
    wrapper.className = "d-flex align-items-center justify-content-between gap-3 mb-2";

    const left = document.createElement("div");
    left.className = "d-flex align-items-center gap-2";

    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.className = "form-check-input mt-0";
    checkbox.dataset.role = "passport-section-toggle";
    checkbox.dataset.section = section.id;
    checkbox.checked = selected;
    checkbox.disabled = section.required || !section.available;
    left.appendChild(checkbox);

    const title = document.createElement("span");
    title.className = "passport-section-option-title";
    title.textContent = section.title;
    left.appendChild(title);

    const meta = document.createElement("span");
    meta.className = "passport-section-option-meta text-muted";
    meta.textContent = section.available
        ? `${section.pageCount} ${section.pageCount === 1 ? "pagina" : "pagina's"}`
        : "0 pagina's";

    wrapper.appendChild(left);
    wrapper.appendChild(meta);

    return wrapper;
}

function getPassportPrintMode() {
    return window.aetherPassportSelections?.printMode === "one-signature"
        ? "one-signature"
        : "paper-save";
}

function renderPassportPreview(container, character) {
    if (!container) return;

    container.innerHTML = "";

    const previewOuter = document.createElement("div");
    previewOuter.className = "mx-auto position-relative overflow-hidden shadow-sm";
    previewOuter.style.width = "100%";
    previewOuter.style.maxWidth = "340px";
    previewOuter.style.aspectRatio = "87 / 125";
    previewOuter.style.backgroundImage = "url('img/paspoorten/paspoort_achterkant_1_katern.webp')";
    previewOuter.style.backgroundSize = "200% 100%";
    previewOuter.style.backgroundPosition = "right center";
    previewOuter.style.backgroundRepeat = "no-repeat";
    previewOuter.style.border = "1px solid rgba(0,0,0,0.08)";
    previewOuter.style.borderRadius = "6px";
    previewOuter.style.color = "#111";
    previewOuter.style.fontFamily = "\"Crimson Text\", Georgia, serif";

    const content = document.createElement("div");
    content.className = "position-absolute";
    content.style.left = "5.7%";
    content.style.right = "5.7%";
    content.style.top = "4%";
    content.style.bottom = "4%";
    previewOuter.appendChild(content);

    const fields = buildPassportPersonalPageFields(character);
    let fieldCount = 0;
    fields.forEach((field) => {
        if (fieldCount >= 7) return;
        fieldCount += 1;

        const label = document.createElement("div");
        label.style.fontSize = "0.48rem";
        label.style.lineHeight = "1.1";
        label.style.marginBottom = "0.18rem";
        label.textContent = String(field?.label || "");
        content.appendChild(label);

        const value = document.createElement("div");
        value.style.fontSize = "0.92rem";
        value.style.lineHeight = "1.05";
        value.style.minHeight = "0.95rem";
        value.style.borderBottom = "1px solid rgba(0,0,0,0.6)";
        value.style.marginBottom = "0.38rem";
        value.style.color = "#1f3d8a";
        value.style.fontStyle = "italic";
        value.textContent = Array.isArray(field?.valueLines) ? String(field.valueLines[0] || "") : "";
        content.appendChild(value);
    });

    const portraitWrap = document.createElement("div");
    portraitWrap.className = "position-absolute";
    portraitWrap.style.right = "5.7%";
    portraitWrap.style.bottom = "12%";
    portraitWrap.style.width = "34%";
    portraitWrap.style.height = "25%";
    portraitWrap.style.border = "1px solid rgba(0,0,0,0.3)";
    portraitWrap.style.background = "#d7d7d7";
    portraitWrap.style.overflow = "hidden";
    previewOuter.appendChild(portraitWrap);

    if (String(character?.portraitUrl || "").trim()) {
        const portrait = document.createElement("img");
        portrait.src = String(character.portraitUrl).trim();
        portrait.alt = "Pasfoto";
        portrait.style.width = "100%";
        portrait.style.height = "100%";
        portrait.style.objectFit = "cover";
        portraitWrap.appendChild(portrait);
    } else {
        const cross = document.createElement("div");
        cross.className = "w-100 h-100 position-relative";
        cross.innerHTML = `
            <div style="position:absolute;inset:0;border-top:1px solid rgba(0,0,0,0.35);transform:rotate(45deg);transform-origin:center;"></div>
            <div style="position:absolute;inset:0;border-top:1px solid rgba(0,0,0,0.35);transform:rotate(-45deg);transform-origin:center;"></div>
        `;
        portraitWrap.appendChild(cross);
    }

    const footer = document.createElement("div");
    footer.className = "position-absolute";
    footer.style.left = "5.7%";
    footer.style.right = "5.7%";
    footer.style.bottom = "4%";
    footer.style.fontSize = "0.48rem";
    footer.style.lineHeight = "1.15";
    footer.innerHTML = `
        <div style="font-weight:700;font-size:0.78rem;margin-bottom:0.15rem;">N° ${String(character?.stateRegisterNumber || "").trim() || "XXXX-XXXX"}</div>
        <div>Op iedere vervalsing van dit paspoort staat correctionele straf.</div>
        <div>Toute falsification de ce passeport est passible de sanctions correctionnelles.</div>
        <div>Any falsification of this passport is subject to criminal penalties.</div>
    `;
    previewOuter.appendChild(footer);

    container.appendChild(previewOuter);
}

function renderPassportTab(character) {
    const container = document.getElementById("passportTabContent");
    if (!container || !character) return;

    const sections = getPassportSectionDefinitions(character);
    const totalPages = sections.reduce((sum, section) => {
        if (section.required) return sum + section.pageCount;
        if (window.aetherPassportSelections[section.id] && section.available) {
            return sum + section.pageCount;
        }
        return sum;
    }, 0);
    const imposedPageCount = getPassportImposedPageCount(totalPages);
    const imposedSheetCount = getPassportImposedSheetCount(imposedPageCount, getPassportPrintMode());

    container.innerHTML = "";
    const row = document.createElement("div");
    row.className = "row g-4";

    const leftCol = document.createElement("div");
    leftCol.className = "col-xl-6";
    const rightCol = document.createElement("div");
    rightCol.className = "col-xl-6";
    row.appendChild(leftCol);
    row.appendChild(rightCol);

    const leftCard = document.createElement("div");
    leftCard.className = "card shadow-sm h-100";
    const leftBody = document.createElement("div");
    leftBody.className = "card-body";
    leftCard.appendChild(leftBody);

    const heading = document.createElement("h4");
    heading.className = "card-title h5 mb-3";
    heading.textContent = "Paspoort aanmaken";
    leftBody.appendChild(heading);

    const introText = document.createElement("p");
    introText.className = "text-muted";
    introText.textContent = "Via deze functie kan je een kant en klare PDF aanmaken die je recto verso kan afdrukken. Snij uit volgens de snijlijnen (volle lijnen aan de rand van de pagina) en vouw dubbel langs de vouwlijnen (stippellijnen). Zo bekom je een handige kleine booklet die precies past in een paspoortkaftje dat je bij aanvang van het spel van ons kan krijgen. Je paspoort kan ook fungeren als personagekaart met referentie naar al je vaardigheden en andere eigenschappen. Kies hier welke informatie je mee wil opnemen.";
    leftBody.appendChild(introText);

    sections.forEach((section) => {
        leftBody.appendChild(
            renderPassportSectionOption(
                section,
                section.required ? true : Boolean(window.aetherPassportSelections[section.id])
            )
        );
    });

    const printText = document.createElement("p");
    printText.className = "text-muted mt-4 mb-3";
    printText.textContent = "Je kan kiezen om zoveel mogelijk paspoortpagina's op een A4 te printen, maar dan moet je printer netjes recto verso kunnen printen, of je kan gaan voor minder pagina's per A4. Dat gaat wat meer papier verbruiken, maar dan zal een verschuiving tussen voor- en achterkant minder hard opvallen.";
    leftBody.appendChild(printText);

    const renderPrintOption = (value, labelText, description) => {
        const label = document.createElement("label");
        label.className = "d-flex align-items-start gap-2 mb-2";

        const radio = document.createElement("input");
        radio.type = "radio";
        radio.name = "passportPrintMode";
        radio.className = "form-check-input mt-1";
        radio.dataset.role = "passport-print-mode";
        radio.value = value;
        radio.checked = getPassportPrintMode() === value;

        const textWrap = document.createElement("div");
        const title = document.createElement("div");
        title.className = "passport-section-option-title";
        title.textContent = labelText;
        textWrap.appendChild(title);

        const meta = document.createElement("div");
        meta.className = "text-muted small";
        meta.textContent = description;
        textWrap.appendChild(meta);

        label.appendChild(radio);
        label.appendChild(textWrap);
        return label;
    };

    leftBody.appendChild(renderPrintOption("paper-save", "Papier sparen", "Gebruik de bestaande impositie met zo veel mogelijk paspoortpagina's per A4."));
    leftBody.appendChild(renderPrintOption("one-signature", "Meer speling tussen recto verso druk", "Druk telkens maar 1 katern per A4-vel af."));

    const actionRow = document.createElement("div");
    actionRow.className = "d-flex justify-content-end mt-4";
    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-primary";
    button.dataset.action = "generate-character-passport-pdf";
    button.textContent = "PDF maken";
    actionRow.appendChild(button);
    leftBody.appendChild(actionRow);

    const totalInfo = document.createElement("div");
    totalInfo.className = "passport-total-pages mt-3";
    totalInfo.textContent = `Totaal geselecteerd: ${totalPages} ${totalPages === 1 ? "pagina" : "pagina's"}. PDF: ${imposedPageCount} paspoortpagina's op ${imposedSheetCount} ${imposedSheetCount === 1 ? "A4-vel" : "A4-vellen"}.`;
    leftBody.appendChild(totalInfo);

    leftCol.appendChild(leftCard);

    const rightCard = document.createElement("div");
    rightCard.className = "card shadow-sm h-100";
    const rightBody = document.createElement("div");
    rightBody.className = "card-body";
    rightCard.appendChild(rightBody);
    renderPassportPreview(rightBody, character);
    rightCol.appendChild(rightCard);

    container.appendChild(row);
}

function formatPassportPdfColor(color) {
    const channels = Array.isArray(color) ? color : [0, 0, 0];
    return channels.map((channel) => {
        const numeric = Number(channel);
        if (!Number.isFinite(numeric)) return "0";
        return Math.max(0, Math.min(1, numeric)).toFixed(3);
    }).join(" ");
}

function renderPassportPdfText(fontKey, fontSize, x, y, text, options = {}) {
    const rotate180 = Boolean(options?.rotate180);
    const matrix = rotate180
        ? `-1 0 0 -1 ${x.toFixed(2)} ${y.toFixed(2)} Tm`
        : `1 0 0 1 ${x.toFixed(2)} ${y.toFixed(2)} Tm`;
    const fillColor = options?.fillColor ? `${formatPassportPdfColor(options.fillColor)} rg ` : "";

    return `q ${fillColor}BT /${fontKey} ${fontSize.toFixed(2)} Tf ${matrix} (${escapePassportPdfString(text)}) Tj ET Q`;
}

function renderPassportPdfLine(x1, y1, x2, y2, dashPattern = [], lineWidth = PASSPORT_PDF_GUIDE_LINE_WIDTH_PT, strokeColor = null) {
    const dash = Array.isArray(dashPattern) && dashPattern.length > 0
        ? `[${dashPattern.map((value) => Number(value).toFixed(2)).join(" ")}] 0 d`
        : "[] 0 d";
    const color = strokeColor ? `${formatPassportPdfColor(strokeColor)} RG ` : "";

    return `q ${color}${Number(lineWidth).toFixed(2)} w ${dash} ${x1.toFixed(2)} ${y1.toFixed(2)} m ${x2.toFixed(2)} ${y2.toFixed(2)} l S Q`;
}

function estimatePassportPdfTextWidth(text, fontSize, widthFactor = PASSPORT_PDF_CHAR_WIDTH_FACTOR) {
    return normalizePassportPdfText(text).length * fontSize * widthFactor;
}

function renderPassportLogicalPdfLine(
    pageLeft,
    pageBottom,
    localX1,
    localY1,
    localX2,
    localY2,
    rotate180 = false,
    dashPattern = [],
    lineWidth = PASSPORT_PDF_GUIDE_LINE_WIDTH_PT,
    strokeColor = null
) {
    if (rotate180) {
        return renderPassportPdfLine(
            pageLeft + PASSPORT_PDF_WIDTH_PT - localX1,
            pageBottom + PASSPORT_PDF_HEIGHT_PT - localY1,
            pageLeft + PASSPORT_PDF_WIDTH_PT - localX2,
            pageBottom + PASSPORT_PDF_HEIGHT_PT - localY2,
            dashPattern,
            lineWidth,
            strokeColor
        );
    }

    return renderPassportPdfLine(
        pageLeft + localX1,
        pageBottom + localY1,
        pageLeft + localX2,
        pageBottom + localY2,
        dashPattern,
        lineWidth,
        strokeColor
    );
}

function renderPassportPdfCircle(x, y, radius, options = {}) {
    const kappa = 0.5522847498;
    const control = radius * kappa;
    const lineWidth = Number(options?.lineWidth || 1);
    const strokeColor = options?.strokeColor ? `${formatPassportPdfColor(options.strokeColor)} RG ` : "";
    const fillColor = options?.fillColor ? `${formatPassportPdfColor(options.fillColor)} rg ` : "";
    const paintOperator = options?.fillColor ? "B" : "S";

    return `q ${strokeColor}${fillColor}${lineWidth.toFixed(2)} w `
        + `${(x).toFixed(2)} ${(y + radius).toFixed(2)} m `
        + `${(x).toFixed(2)} ${(y + radius - control).toFixed(2)} ${(x + radius - control).toFixed(2)} ${(y).toFixed(2)} ${(x + radius).toFixed(2)} ${(y).toFixed(2)} c `
        + `${(x + radius + control).toFixed(2)} ${(y).toFixed(2)} ${(x + (radius * 2)).toFixed(2)} ${(y + radius - control).toFixed(2)} ${(x + (radius * 2)).toFixed(2)} ${(y + radius).toFixed(2)} c `
        + `${(x + (radius * 2)).toFixed(2)} ${(y + radius + control).toFixed(2)} ${(x + radius + control).toFixed(2)} ${(y + (radius * 2)).toFixed(2)} ${(x + radius).toFixed(2)} ${(y + (radius * 2)).toFixed(2)} c `
        + `${(x + radius - control).toFixed(2)} ${(y + (radius * 2)).toFixed(2)} ${(x).toFixed(2)} ${(y + radius + control).toFixed(2)} ${(x).toFixed(2)} ${(y + radius).toFixed(2)} c `
        + `${paintOperator} Q`;
}

function convertPassportBytesToHex(bytes) {
    let hex = "";
    const chunkSize = 4096;

    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        const chunk = bytes.subarray(offset, offset + chunkSize);
        for (let index = 0; index < chunk.length; index += 1) {
            hex += chunk[index].toString(16).padStart(2, "0");
        }
    }

    return hex + ">";
}

function encodePassportPdfLatin1(value) {
    const input = String(value || "");
    const bytes = new Uint8Array(input.length);

    for (let index = 0; index < input.length; index += 1) {
        bytes[index] = input.charCodeAt(index) & 0xFF;
    }

    return bytes;
}

function decodePassportDataUrlToBytes(dataUrl) {
    const base64Data = String(dataUrl || "").split(",")[1] || "";
    const binaryString = atob(base64Data);
    const bytes = new Uint8Array(binaryString.length);

    for (let index = 0; index < binaryString.length; index += 1) {
        bytes[index] = binaryString.charCodeAt(index);
    }

    return bytes;
}

function readPassportUInt16(bytes, offset) {
    return (bytes[offset] << 8) | bytes[offset + 1];
}

function readPassportInt16(bytes, offset) {
    const value = readPassportUInt16(bytes, offset);
    return value > 0x7FFF ? value - 0x10000 : value;
}

function readPassportUInt32(bytes, offset) {
    return ((bytes[offset] * 0x1000000) >>> 0)
        + ((bytes[offset + 1] << 16) >>> 0)
        + ((bytes[offset + 2] << 8) >>> 0)
        + (bytes[offset + 3] >>> 0);
}

function readPassportFixed(bytes, offset) {
    return readPassportInt16(bytes, offset) + (readPassportUInt16(bytes, offset + 2) / 65536);
}

function parsePassportTtfTables(bytes) {
    const numTables = readPassportUInt16(bytes, 4);
    const tables = {};

    for (let index = 0; index < numTables; index += 1) {
        const recordOffset = 12 + (index * 16);
        const tag = String.fromCharCode(
            bytes[recordOffset],
            bytes[recordOffset + 1],
            bytes[recordOffset + 2],
            bytes[recordOffset + 3]
        );

        tables[tag] = {
            offset: readPassportUInt32(bytes, recordOffset + 8),
            length: readPassportUInt32(bytes, recordOffset + 12)
        };
    }

    return tables;
}

function parsePassportTtfCmapFormat4(bytes, cmapOffset) {
    const segCount = readPassportUInt16(bytes, cmapOffset + 6) / 2;
    const endCodeOffset = cmapOffset + 14;
    const startCodeOffset = endCodeOffset + (segCount * 2) + 2;
    const idDeltaOffset = startCodeOffset + (segCount * 2);
    const idRangeOffsetOffset = idDeltaOffset + (segCount * 2);
    const glyphIdArrayOffset = idRangeOffsetOffset + (segCount * 2);

    return (charCode) => {
        for (let segmentIndex = 0; segmentIndex < segCount; segmentIndex += 1) {
            const endCode = readPassportUInt16(bytes, endCodeOffset + (segmentIndex * 2));
            const startCode = readPassportUInt16(bytes, startCodeOffset + (segmentIndex * 2));
            if (charCode < startCode || charCode > endCode) {
                continue;
            }

            const idDelta = readPassportInt16(bytes, idDeltaOffset + (segmentIndex * 2));
            const idRangeOffset = readPassportUInt16(bytes, idRangeOffsetOffset + (segmentIndex * 2));
            if (idRangeOffset === 0) {
                return (charCode + idDelta + 0x10000) % 0x10000;
            }

            const glyphIndexAddress = idRangeOffsetOffset
                + (segmentIndex * 2)
                + idRangeOffset
                + ((charCode - startCode) * 2);

            if (glyphIndexAddress + 1 >= bytes.length) {
                return 0;
            }

            const glyphId = readPassportUInt16(bytes, glyphIndexAddress);
            if (glyphId === 0) {
                return 0;
            }

            return (glyphId + idDelta + 0x10000) % 0x10000;
        }

        return 0;
    };
}

function parsePassportTtfFont(bytes, fallbackBaseName) {
    const tables = parsePassportTtfTables(bytes);
    const head = tables.head;
    const hhea = tables.hhea;
    const hmtx = tables.hmtx;
    const maxp = tables.maxp;
    const cmap = tables.cmap;
    const post = tables.post;

    if (!head || !hhea || !hmtx || !maxp || !cmap || !post) {
        throw new Error("TTF mist verplichte tabellen.");
    }

    const unitsPerEm = readPassportUInt16(bytes, head.offset + 18);
    const xMin = readPassportInt16(bytes, head.offset + 36);
    const yMin = readPassportInt16(bytes, head.offset + 38);
    const xMax = readPassportInt16(bytes, head.offset + 40);
    const yMax = readPassportInt16(bytes, head.offset + 42);
    const macStyle = readPassportUInt16(bytes, head.offset + 44);
    const ascent = readPassportInt16(bytes, hhea.offset + 4);
    const descent = readPassportInt16(bytes, hhea.offset + 6);
    const numberOfHMetrics = readPassportUInt16(bytes, hhea.offset + 34);
    const numGlyphs = readPassportUInt16(bytes, maxp.offset + 4);
    const italicAngle = readPassportFixed(bytes, post.offset + 4);

    const advanceWidths = new Array(numGlyphs).fill(0);
    let lastAdvanceWidth = 0;
    for (let glyphIndex = 0; glyphIndex < numGlyphs; glyphIndex += 1) {
        if (glyphIndex < numberOfHMetrics) {
            lastAdvanceWidth = readPassportUInt16(bytes, hmtx.offset + (glyphIndex * 4));
            advanceWidths[glyphIndex] = lastAdvanceWidth;
            continue;
        }

        advanceWidths[glyphIndex] = lastAdvanceWidth;
    }

    const cmapVersion = readPassportUInt16(bytes, cmap.offset);
    const numSubtables = readPassportUInt16(bytes, cmap.offset + 2);
    let cmapMapper = null;

    if (cmapVersion === 0) {
        for (let index = 0; index < numSubtables; index += 1) {
            const recordOffset = cmap.offset + 4 + (index * 8);
            const platformId = readPassportUInt16(bytes, recordOffset);
            const encodingId = readPassportUInt16(bytes, recordOffset + 2);
            const subtableOffset = cmap.offset + readPassportUInt32(bytes, recordOffset + 4);
            const format = readPassportUInt16(bytes, subtableOffset);

            if (platformId === 3 && (encodingId === 1 || encodingId === 0) && format === 4) {
                cmapMapper = parsePassportTtfCmapFormat4(bytes, subtableOffset);
                break;
            }
        }
    }

    if (!cmapMapper) {
        throw new Error("Geen bruikbare cmap gevonden in TTF.");
    }

    const os2 = tables["OS/2"];
    const capHeight = os2 && tables["OS/2"].length >= 90
        ? readPassportInt16(bytes, os2.offset + 88)
        : ascent;
    const typoAscent = os2 ? readPassportInt16(bytes, os2.offset + 68) : ascent;
    const typoDescent = os2 ? readPassportInt16(bytes, os2.offset + 70) : descent;

    const widths = [];
    const firstChar = 32;
    const lastChar = 126;
    for (let charCode = firstChar; charCode <= lastChar; charCode += 1) {
        const glyphIndex = cmapMapper(charCode);
        const advanceWidth = advanceWidths[glyphIndex] || advanceWidths[0] || unitsPerEm;
        widths.push(Math.round((advanceWidth / unitsPerEm) * 1000));
    }

    const flags = 32
        + 2
        + ((macStyle & 0x02) || italicAngle !== 0 ? 64 : 0);

    return {
        baseFontName: fallbackBaseName,
        firstChar,
        lastChar,
        widths,
        ascent: Math.round((typoAscent / unitsPerEm) * 1000),
        descent: Math.round((typoDescent / unitsPerEm) * 1000),
        capHeight: Math.round((capHeight / unitsPerEm) * 1000),
        bbox: [
            Math.round((xMin / unitsPerEm) * 1000),
            Math.round((yMin / unitsPerEm) * 1000),
            Math.round((xMax / unitsPerEm) * 1000),
            Math.round((yMax / unitsPerEm) * 1000)
        ],
        italicAngle,
        stemV: italicAngle !== 0 ? 110 : 80,
        flags,
        missingWidth: widths[0] || 500,
        bytes
    };
}

async function loadPassportPdfFontAsset(path, cacheKey, fallbackBaseName) {
    if (window[cacheKey] !== undefined) {
        return window[cacheKey];
    }

    const promiseKey = `${cacheKey}Promise`;
    if (!window[promiseKey]) {
        window[promiseKey] = fetch(path, { cache: "force-cache" })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Font niet gevonden (${response.status}).`);
                }

                const bytes = new Uint8Array(await response.arrayBuffer());
                return parsePassportTtfFont(bytes, fallbackBaseName);
            })
            .catch(() => null)
            .then((fontAsset) => {
                window[cacheKey] = fontAsset;
                return fontAsset;
            });
    }

    return window[promiseKey];
}

function getPassportBackgroundImagePathForLayout(layout) {
    return layout === "two-up"
        ? PASSPORT_PDF_BACKGROUND_IMAGE_PATH_TWO_UP
        : PASSPORT_PDF_BACKGROUND_IMAGE_PATH_FOUR_UP;
}

async function loadPassportPdfBackgroundImage(layout) {
    const cacheKey = `aetherPassportPdfBackgroundImage_${layout}`;
    if (window[cacheKey] !== undefined) {
        return window[cacheKey];
    }

    const promiseKey = `${cacheKey}Promise`;
    if (!window[promiseKey]) {
        window[promiseKey] = new Promise((resolve) => {
            const image = new Image();
            image.onload = () => {
                window[cacheKey] = image;
                resolve(image);
            };
            image.onerror = () => {
                window[cacheKey] = null;
                resolve(null);
            };
            image.src = getPassportBackgroundImagePathForLayout(layout);
        });
    }

    return window[promiseKey];
}

async function loadPassportPdfGenericImage(path, cacheKey) {
    if (window[cacheKey] !== undefined) {
        return window[cacheKey];
    }

    const promiseKey = `${cacheKey}Promise`;
    if (!window[promiseKey]) {
        window[promiseKey] = new Promise((resolve) => {
            const image = new Image();
            image.onload = () => {
                window[cacheKey] = image;
                resolve(image);
            };
            image.onerror = () => {
                window[cacheKey] = null;
                resolve(null);
            };
            image.src = path;
        });
    }

    return window[promiseKey];
}

function buildPassportImageCacheKey(prefix, value) {
    const input = String(value || "");
    let hash = 0;

    for (let index = 0; index < input.length; index += 1) {
        hash = ((hash << 5) - hash) + input.charCodeAt(index);
        hash |= 0;
    }

    return `${prefix}_${Math.abs(hash)}`;
}

function buildPassportPdfJpegAssetFromCanvas(canvas, assetName) {
    const dataUrl = canvas.toDataURL("image/jpeg", 0.92);
    const bytes = decodePassportDataUrlToBytes(dataUrl);

    return {
        name: assetName,
        width: canvas.width,
        height: canvas.height,
        bitsPerComponent: 8,
        colorSpace: "DeviceRGB",
        hexData: convertPassportBytesToHex(bytes)
    };
}

function drawPassportCanvasLine(ctx, x1, y1, x2, y2, lineWidth, dashPattern = []) {
    ctx.save();
    ctx.lineWidth = lineWidth;
    ctx.setLineDash(dashPattern);
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
    ctx.restore();
}

function drawPassportCanvasCrossPlaceholder(ctx, x, y, width, height) {
    ctx.save();
    ctx.fillStyle = "#d7d7d7";
    ctx.fillRect(x, y, width, height);
    ctx.strokeStyle = "#8a8a8a";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.rect(x, y, width, height);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x + width, y + height);
    ctx.moveTo(x + width, y);
    ctx.lineTo(x, y + height);
    ctx.stroke();
    ctx.restore();
}

function drawPassportCanvasCoverImage(ctx, image, x, y, width, height) {
    if (!image) {
        drawPassportCanvasCrossPlaceholder(ctx, x, y, width, height);
        return;
    }

    const imageRatio = image.naturalWidth / Math.max(1, image.naturalHeight);
    const boxRatio = width / Math.max(1, height);
    let sourceX = 0;
    let sourceY = 0;
    let sourceWidth = image.naturalWidth;
    let sourceHeight = image.naturalHeight;

    if (imageRatio > boxRatio) {
        sourceWidth = image.naturalHeight * boxRatio;
        sourceX = (image.naturalWidth - sourceWidth) / 2;
    } else {
        sourceHeight = image.naturalWidth / boxRatio;
        sourceY = (image.naturalHeight - sourceHeight) / 2;
    }

    ctx.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, x, y, width, height);
}

function getPassportSlotSidePosition(slotIndex) {
    return slotIndex % 2 === 0 ? "left" : "right";
}

function getPassportPageImagePlacement(slot, slotIndex, layout) {
    const sidePosition = getPassportSlotSidePosition(slotIndex);
    const x = sidePosition === "left"
        ? slot.x - PASSPORT_PDF_BLEED_PT
        : slot.x;
    const topBleed = layout === "two-up" ? PASSPORT_PDF_BLEED_PT : 0;
    const y = slot?.rotate180
        ? slot.y
        : slot.y - PASSPORT_PDF_BLEED_PT;

    return {
        x,
        y,
        width: PASSPORT_PDF_WIDTH_PT + PASSPORT_PDF_BLEED_PT,
        height: PASSPORT_PDF_HEIGHT_PT + PASSPORT_PDF_BLEED_PT + topBleed,
        sidePosition
    };
}

function drawPassportPageBackgroundOnCanvas(ctx, backgroundImage, sidePosition, layout, canvasWidth, canvasHeight, options = {}) {
    if (!backgroundImage) return;

    const spreadWidthPt = (PASSPORT_PDF_WIDTH_PT * 2) + (PASSPORT_PDF_BLEED_PT * 2);
    const spreadHeightPt = PASSPORT_PDF_HEIGHT_PT + PASSPORT_PDF_BLEED_PT + (layout === "two-up" ? PASSPORT_PDF_BLEED_PT : 0);
    const cropXPt = sidePosition === "left"
        ? 0
        : PASSPORT_PDF_WIDTH_PT + PASSPORT_PDF_BLEED_PT;
    const cropWidthPt = PASSPORT_PDF_WIDTH_PT + PASSPORT_PDF_BLEED_PT;
    const cropYPt = 0;
    const cropHeightPt = spreadHeightPt;

    const sourceX = (cropXPt / spreadWidthPt) * backgroundImage.naturalWidth;
    const sourceY = (cropYPt / spreadHeightPt) * backgroundImage.naturalHeight;
    const sourceWidth = (cropWidthPt / spreadWidthPt) * backgroundImage.naturalWidth;
    const sourceHeight = (cropHeightPt / spreadHeightPt) * backgroundImage.naturalHeight;
    const topShiftPt = options?.shiftUpPt ? Number(options.shiftUpPt) : 0;
    const destinationY = -(topShiftPt / Math.max(1, cropHeightPt)) * canvasHeight;
    const destinationHeight = canvasHeight - destinationY;

    ctx.drawImage(
        backgroundImage,
        sourceX,
        sourceY,
        sourceWidth,
        sourceHeight,
        0,
        destinationY,
        canvasWidth,
        destinationHeight
    );
}

async function createPassportPdfPageBackgroundAsset(page, sidePosition, layout, assetName, options = {}) {
    const scale = PASSPORT_PDF_RENDER_SCALE;
    const bleedPx = Math.round(PASSPORT_PDF_BLEED_PT * scale);
    const topBleedPx = layout === "two-up" ? bleedPx : 0;
    const trimWidthPx = Math.round(PASSPORT_PDF_WIDTH_PT * scale);
    const trimHeightPx = Math.round(PASSPORT_PDF_HEIGHT_PT * scale);
    const canvas = document.createElement("canvas");
    canvas.width = trimWidthPx + bleedPx;
    canvas.height = trimHeightPx + bleedPx + topBleedPx;

    const ctx = canvas.getContext("2d");
    if (!ctx) {
        throw new Error("Canvascontext niet beschikbaar.");
    }

    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    const backgroundImage = await loadPassportPdfBackgroundImage(layout);
    drawPassportPageBackgroundOnCanvas(ctx, backgroundImage, sidePosition, layout, canvas.width, canvas.height, {
        shiftUpPt: options?.shiftUpPt || 0
    });

    if (String(page?.template || "") === "personal_identity_front") {
        const stampImage = await loadPassportPdfGenericImage(
            PASSPORT_PDF_STAMP_IMAGE_PATH,
            "aetherPassportStampImage"
        );
        const trimOffsetX = sidePosition === "left" ? bleedPx : 0;
        const trimTopOffsetY = topBleedPx;
        if (stampImage) {
            const stampSize = 95 * scale;
            const stampX = trimOffsetX + trimWidthPx - stampSize - (18 * scale);
            const stampY = trimTopOffsetY + (16 * scale);
            const stampRotationDegrees = (Math.random() * 90) - 45;
            ctx.save();
            ctx.globalAlpha = 0.72;
            ctx.translate(stampX + (stampSize / 2), stampY + (stampSize / 2));
            ctx.rotate((stampRotationDegrees * Math.PI) / 180);
            ctx.drawImage(stampImage, -(stampSize / 2), -(stampSize / 2), stampSize, stampSize);
            ctx.restore();
        }

        const portraitWidth = 58 * scale;
        const portraitHeight = 80 * scale;
        const portraitX = trimOffsetX + trimWidthPx - portraitWidth - (14 * scale);
        const portraitY = trimTopOffsetY + trimHeightPx - portraitHeight - (14 * scale);
        const portraitUrl = String(page?.templateData?.portraitUrl || "").trim();
        const portraitImage = portraitUrl
            ? await loadPassportPdfGenericImage(
                portraitUrl,
                buildPassportImageCacheKey("aetherPassportPortrait", portraitUrl)
            )
            : null;
        drawPassportCanvasCoverImage(ctx, portraitImage, portraitX, portraitY, portraitWidth, portraitHeight);
    }

    return buildPassportPdfJpegAssetFromCanvas(canvas, assetName);
}

function getPassportPhysicalPageNumber(page) {
    return Number(page?.physicalPageNumber || 0);
}

function isPassportPhysicalPageRightHand(page) {
    const physicalPageNumber = getPassportPhysicalPageNumber(page);
    return physicalPageNumber > 0 && physicalPageNumber % 2 === 1;
}

function getPassportImposedPageCount(pageCount) {
    const count = Math.max(0, Number(pageCount || 0));
    if (count === 0) return 0;
    const remainder = count % 4;
    return remainder === 0 ? count : count + (4 - remainder);
}

function getPassportImposedSheetCount(pageCount, printMode = getPassportPrintMode()) {
    let remaining = Math.max(0, Number(pageCount || 0));
    let sheetCount = 0;

    if (printMode === "one-signature") {
        return Math.ceil(remaining / 4);
    }

    while (remaining > 0) {
        if (remaining <= 4) {
            sheetCount += 1;
            remaining -= 4;
            continue;
        }

        sheetCount += 1;
        remaining -= 8;
    }

    return sheetCount;
}

function createPassportBlankPage() {
    return {
        sectionTitle: "",
        subtitle: "",
        lines: [],
        isBlank: true,
        template: "blank_lined"
    };
}

function preparePassportLogicalPagesForImposition(pages) {
    const preparedPages = (Array.isArray(pages) ? pages : []).map((page) => ({
        sectionTitle: String(page?.sectionTitle || ""),
        subtitle: String(page?.subtitle || ""),
        lines: Array.isArray(page?.lines) ? page.lines.slice() : [],
        isBlank: Boolean(page?.isBlank),
        template: String(page?.template || ""),
        templateData: page?.templateData ? { ...page.templateData } : null
    }));

    const imposedPageCount = getPassportImposedPageCount(preparedPages.length);
    while (preparedPages.length < imposedPageCount) {
        preparedPages.push(createPassportBlankPage());
    }

    return preparedPages.map((page, index) => ({
        ...page,
        physicalPageNumber: index + 1
    }));
}

function getPassportSheetSlots(layout) {
    if (layout === "two-up") {
        const marginX = (PASSPORT_PDF_A4_WIDTH_PT - (PASSPORT_PDF_WIDTH_PT * 2)) / 2;
        const baseY = (PASSPORT_PDF_A4_HEIGHT_PT - PASSPORT_PDF_HEIGHT_PT) / 2;

        return [
            { x: marginX, y: baseY, rotate180: false },
            { x: marginX + PASSPORT_PDF_WIDTH_PT, y: baseY, rotate180: false }
        ];
    }

    const marginX = (PASSPORT_PDF_A4_WIDTH_PT - (PASSPORT_PDF_WIDTH_PT * 2)) / 2;
    const marginY = (PASSPORT_PDF_A4_HEIGHT_PT - (PASSPORT_PDF_HEIGHT_PT * 2)) / 2;

    return [
        { x: marginX, y: marginY + PASSPORT_PDF_HEIGHT_PT, rotate180: true },
        { x: marginX + PASSPORT_PDF_WIDTH_PT, y: marginY + PASSPORT_PDF_HEIGHT_PT, rotate180: true },
        { x: marginX, y: marginY, rotate180: false },
        { x: marginX + PASSPORT_PDF_WIDTH_PT, y: marginY, rotate180: false }
    ];
}

function createPassportImpositionSignatures(logicalPages) {
    const signatures = [];
    const totalPages = logicalPages.length;
    const signatureCount = Math.ceil(totalPages / 4);

    for (let signatureIndex = 0; signatureIndex < signatureCount; signatureIndex += 1) {
        const lowerIndex = signatureIndex * 2;
        const upperIndex = totalPages - (signatureIndex * 2) - 2;

        signatures.push({
            first: logicalPages[lowerIndex] || createPassportBlankPage(),
            second: logicalPages[lowerIndex + 1] || createPassportBlankPage(),
            third: logicalPages[upperIndex] || createPassportBlankPage(),
            fourth: logicalPages[upperIndex + 1] || createPassportBlankPage()
        });
    }

    return signatures;
}

function createPassportImpositionSheets(logicalPages, printMode = getPassportPrintMode()) {
    const signatures = createPassportImpositionSignatures(logicalPages);
    const sheets = [];

    if (printMode === "one-signature") {
        signatures.forEach((signature) => {
            sheets.push({
                layout: "two-up",
                front: [signature.fourth, signature.first],
                back: [signature.second, signature.third]
            });
        });
        return sheets;
    }

    for (let index = 0; index < signatures.length; index += 2) {
        const bottomSignature = signatures[index];
        const topSignature = signatures[index + 1] || null;

        if (topSignature) {
            sheets.push({
                layout: "four-up",
                front: [
                    topSignature.third,
                    topSignature.second,
                    bottomSignature.fourth,
                    bottomSignature.first
                ],
                back: [
                    topSignature.first,
                    topSignature.fourth,
                    bottomSignature.second,
                    bottomSignature.third
                ]
            });
            continue;
        }

        sheets.push({
            layout: "two-up",
            front: [bottomSignature.fourth, bottomSignature.first],
            back: [bottomSignature.second, bottomSignature.third]
        });
    }

    return sheets;
}

function getPassportSheetGuideGeometry(layout) {
    const slots = getPassportSheetSlots(layout);
    const leftX = slots[0]?.x || 0;
    const centerX = leftX + PASSPORT_PDF_WIDTH_PT;
    const rightX = leftX + (PASSPORT_PDF_WIDTH_PT * 2);

    if (layout === "two-up") {
        const bottomY = slots[0]?.y || 0;
        const topY = bottomY + PASSPORT_PDF_HEIGHT_PT;
        return {
            verticalBoundaries: [leftX, centerX, rightX],
            horizontalBoundaries: [bottomY, topY]
        };
    }

    const bottomY = slots[2]?.y || 0;
    const middleY = bottomY + PASSPORT_PDF_HEIGHT_PT;
    const topY = bottomY + (PASSPORT_PDF_HEIGHT_PT * 2);

    return {
        verticalBoundaries: [leftX, centerX, rightX],
        horizontalBoundaries: [bottomY, middleY, topY]
    };
}

function buildPassportPdfSheetGuides(layout) {
    const geometry = getPassportSheetGuideGeometry(layout);
    const topEdgeY = PASSPORT_PDF_A4_HEIGHT_PT - PASSPORT_PDF_GUIDE_OFFSET_PT;
    const bottomEdgeY = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const leftEdgeX = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const rightEdgeX = PASSPORT_PDF_A4_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT;

    const content = [];

    geometry.verticalBoundaries.forEach((x, index) => {
        if (index === 1) {
            content.push(renderPassportPdfLine(
                x,
                topEdgeY - PASSPORT_PDF_GUIDE_MARK_LENGTH_PT,
                x,
                topEdgeY,
                PASSPORT_PDF_FOLD_LINE_DASH_PT
            ));
            content.push(renderPassportPdfLine(
                x,
                bottomEdgeY,
                x,
                bottomEdgeY + PASSPORT_PDF_GUIDE_MARK_LENGTH_PT,
                PASSPORT_PDF_FOLD_LINE_DASH_PT
            ));
            return;
        }

        content.push(renderPassportPdfLine(
            x,
            topEdgeY - PASSPORT_PDF_GUIDE_MARK_LENGTH_PT,
            x,
            topEdgeY
        ));
        content.push(renderPassportPdfLine(
            x,
            bottomEdgeY,
            x,
            bottomEdgeY + PASSPORT_PDF_GUIDE_MARK_LENGTH_PT
        ));
    });

    geometry.horizontalBoundaries.forEach((y) => {
        content.push(renderPassportPdfLine(
            leftEdgeX,
            y,
            leftEdgeX + PASSPORT_PDF_GUIDE_MARK_LENGTH_PT,
            y
        ));
        content.push(renderPassportPdfLine(
            rightEdgeX - PASSPORT_PDF_GUIDE_MARK_LENGTH_PT,
            y,
            rightEdgeX,
            y
        ));
    });

    return content.join("\n");
}

function renderPassportPdfImage(imageName, x, y, width, height, rotate180 = false) {
    if (rotate180) {
        return `q ${(-width).toFixed(2)} 0 0 ${(-height).toFixed(2)} ${(x + width).toFixed(2)} ${(y + height).toFixed(2)} cm /${imageName} Do Q`;
    }

    return `q ${width.toFixed(2)} 0 0 ${height.toFixed(2)} ${x.toFixed(2)} ${y.toFixed(2)} cm /${imageName} Do Q`;
}

function renderPassportLogicalPdfText(fontKey, fontSize, pageLeft, pageBottom, localX, localY, text, rotate180 = false, options = {}) {
    if (rotate180) {
        return renderPassportPdfText(
            fontKey,
            fontSize,
            pageLeft + PASSPORT_PDF_WIDTH_PT - localX,
            pageBottom + PASSPORT_PDF_HEIGHT_PT - localY,
            text,
            { ...options, rotate180: true }
        );
    }

    return renderPassportPdfText(
        fontKey,
        fontSize,
        pageLeft + localX,
        pageBottom + localY,
        text,
        options
    );
}

function buildPassportPdfPersonalIdentityPageContent(page, slot) {
    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const rotate180 = Boolean(slot?.rotate180);
    const margin = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const lineLeft = margin;
    const lineRight = PASSPORT_PDF_WIDTH_PT - margin;
    const fields = Array.isArray(page?.templateData?.fields) ? page.templateData.fields : [];

    let cursorY = PASSPORT_PDF_HEIGHT_PT - margin - 8;
    const labelSize = 6.6;
    const scriptSize = 12.6;
    const scriptLineHeight = 15.5;
    const fieldGap = 0;
    const labelToValueGap = 10.5;
    const scriptYOffset = -1.5;

    fields.forEach((field) => {
        content.push(renderPassportLogicalPdfText(
            "F1",
            labelSize,
            pageLeft,
            pageBottom,
            lineLeft,
            cursorY,
            String(field?.label || ""),
            rotate180
        ));
        cursorY -= labelToValueGap;

        const valueLines = Array.isArray(field?.valueLines) && field.valueLines.length > 0
            ? field.valueLines
            : [""];

        valueLines.forEach((valueLine) => {
            const lineY = cursorY - 1.5;
            content.push(renderPassportLogicalPdfLine(
                pageLeft,
                pageBottom,
                lineLeft,
                lineY,
                lineRight,
                lineY,
                rotate180,
                [],
                0.45
            ));

            const valueText = String(valueLine || "").trim();
            if (valueText) {
                content.push(renderPassportLogicalPdfText(
                    "F3",
                    scriptSize,
                    pageLeft,
                    pageBottom,
                    lineLeft + 4,
                    cursorY + scriptYOffset,
                    valueText,
                    rotate180,
                    { fillColor: PASSPORT_PDF_HANDWRITING_COLOR }
                ));
            }

            cursorY -= scriptLineHeight;
        });

        cursorY -= fieldGap;
    });

    content.push(renderPassportLogicalPdfText(
        "F1",
        7,
        pageLeft,
        pageBottom,
        lineLeft,
        cursorY + 2,
        "handtekening houder",
        rotate180
    ));

    const registryNumber = String(page?.templateData?.stateRegisterNumber || "").trim() || "XXXX-XXXX";
    content.push(renderPassportLogicalPdfText(
        "F1",
        7.5,
        pageLeft,
        pageBottom,
        lineLeft + 22,
        28,
        `N° ${registryNumber}`,
        rotate180
    ));

    const footerLines = [
        "Op iedere vervalsing van dit paspoort staat correctionele straf.",
        "Toute falsification de ce passeport est passible de sanctions correctionnelles.",
        "Any falsification of this passport is subject to criminal penalties."
    ];
    let footerY = 19;
    footerLines.forEach((footerLine) => {
        content.push(renderPassportLogicalPdfText(
            "F1",
            4.25,
            pageLeft,
            pageBottom,
            lineLeft,
            footerY,
            footerLine,
            rotate180
        ));
        footerY -= 5.4;
    });

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = isPassportPhysicalPageRightHand(page)
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(
        renderPassportLogicalPdfText(
            "F2",
            PASSPORT_PDF_SMALL_FONT_SIZE,
            pageLeft,
            pageBottom,
            pageNumberX,
            PASSPORT_PDF_GUIDE_OFFSET_PT + 3,
            pageNumberText,
            rotate180
        )
    );

    return content.join("\n");
}

function buildPassportPdfSkillDetailPageContentWithMetrics(page, slot, options = {}) {
    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const rotate180 = Boolean(slot?.rotate180);
    const margin = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const contentLeft = margin;
    const contentRight = PASSPORT_PDF_WIDTH_PT - margin;
    const contentWidth = contentRight - contentLeft;
    const titleSize = 12;
    const levelSize = 12;
    const bodySize = Number(options?.bodySize || 7);
    const bodyLineHeight = Number(options?.bodyLineHeight || 8.6);
    const levelNumberColumnWidth = 10;
    const textColumnX = contentLeft + levelNumberColumnWidth + 4;
    const separatorWidth = 0.45;
    const skillName = normalizePassportPdfText(page?.templateData?.name || "Onbekende vaardigheid");
    const levelLabel = normalizePassportPdfText(page?.templateData?.levelLabel || "");
    const description = normalizePassportPdfText(page?.templateData?.description || "Geen beschrijving.");
    const levelSections = Array.isArray(page?.templateData?.levelSections)
        ? page.templateData.levelSections
        : [];

    const wrapAtWidth = (text, fontSize, availableWidth) => wrapPassportTextToWidth(
        text,
        fontSize,
        availableWidth,
        PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
    );

    let cursorY = PASSPORT_PDF_HEIGHT_PT - margin - 7;
    content.push(renderPassportLogicalPdfText(
        "F2",
        titleSize,
        pageLeft,
        pageBottom,
        contentLeft,
        cursorY,
        skillName,
        rotate180
    ));

    if (levelLabel) {
        const levelWidth = estimatePassportPdfTextWidth(levelLabel, levelSize);
        content.push(renderPassportLogicalPdfText(
            "F2",
            levelSize,
            pageLeft,
            pageBottom,
            contentRight - levelWidth,
            cursorY,
            levelLabel,
            rotate180
        ));
    }

    cursorY -= 15;

    wrapAtWidth(description, bodySize, contentWidth).forEach((line) => {
        content.push(renderPassportLogicalPdfText(
            "F1",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            line,
            rotate180
        ));
        cursorY -= bodyLineHeight;
    });

    if (levelSections.length > 0) {
        cursorY -= 4;
    }

    levelSections.forEach((section, index) => {
        if (index > 0) {
            content.push(renderPassportLogicalPdfLine(
                pageLeft,
                pageBottom,
                contentLeft,
                cursorY,
                contentRight,
                cursorY,
                rotate180,
                [],
                separatorWidth
            ));
            cursorY -= 8;
        }

        content.push(renderPassportLogicalPdfText(
            "F1",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            String(section?.level || ""),
            rotate180
        ));

        const sectionLines = wrapAtWidth(section?.text || "", bodySize, contentWidth - levelNumberColumnWidth - 4);
        sectionLines.forEach((line, lineIndex) => {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                textColumnX,
                cursorY - (lineIndex * bodyLineHeight),
                line,
                rotate180
            ));
        });

        cursorY -= Math.max(1, sectionLines.length) * bodyLineHeight;
        cursorY -= 3;
    });

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = isPassportPhysicalPageRightHand(page)
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(renderPassportLogicalPdfText(
        "F2",
        PASSPORT_PDF_SMALL_FONT_SIZE,
        pageLeft,
        pageBottom,
        pageNumberX,
        PASSPORT_PDF_GUIDE_OFFSET_PT + 3,
        pageNumberText,
        rotate180
    ));

    return {
        content: content.join("\n"),
        finalCursorY: cursorY
    };
}

function buildPassportPdfSkillDetailPageContent(page, slot) {
    const defaultRender = buildPassportPdfSkillDetailPageContentWithMetrics(page, slot, {
        bodySize: 7,
        bodyLineHeight: 8.6
    });
    const minimumCursorY = PASSPORT_PDF_GUIDE_OFFSET_PT + 16;

    if (defaultRender.finalCursorY >= minimumCursorY) {
        return defaultRender.content;
    }

    return buildPassportPdfSkillDetailPageContentWithMetrics(page, slot, {
        bodySize: 6.5,
        bodyLineHeight: 8.0
    }).content;
}

function buildPassportPdfWealthOverviewPageContent(page, slot) {
    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const rotate180 = Boolean(slot?.rotate180);
    const margin = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const contentLeft = margin;
    const contentRight = PASSPORT_PDF_WIDTH_PT - margin;
    const contentWidth = contentRight - contentLeft;
    const title = normalizePassportPdfText(page?.templateData?.title || "Welvaart");
    const monthlyIncome = normalizePassportPdfText(page?.templateData?.monthlyIncome || "");
    const livingStandardText = normalizePassportInlineText(page?.templateData?.livingStandardText || "");
    const shareEntries = Array.isArray(page?.templateData?.shareEntries) ? page.templateData.shareEntries : [];
    const securitiesAmount = normalizePassportPdfText(page?.templateData?.securitiesAmount || "");
    const titleSize = 12;
    const bodySize = 7;
    const bodyLineHeight = 8.4;
    let cursorY = PASSPORT_PDF_HEIGHT_PT - margin - 7;

    const renderLeftRightRow = (label, value) => {
        const labelText = normalizePassportPdfText(label);
        const valueText = normalizePassportPdfText(value);
        const valueWidth = estimatePassportPdfTextWidth(valueText, bodySize, PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR);

        content.push(renderPassportLogicalPdfText(
            "F2",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            labelText,
            rotate180
        ));

        if (valueText) {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                contentRight - valueWidth,
                cursorY,
                valueText,
                rotate180
            ));
        }

        cursorY -= bodyLineHeight;
        cursorY -= 4;
    };

    const renderTitleDescriptionBlock = (label, value) => {
        const labelText = normalizePassportPdfText(label);
        const descriptionLines = wrapPassportTextToWidth(
            normalizePassportInlineText(value),
            bodySize,
            contentWidth,
            PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
        );

        content.push(renderPassportLogicalPdfText(
            "F2",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            labelText,
            rotate180
        ));

        descriptionLines.forEach((line, index) => {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                contentLeft,
                cursorY - ((index + 1) * bodyLineHeight),
                line,
                rotate180
            ));
        });

        cursorY -= bodyLineHeight;
        cursorY -= descriptionLines.length * bodyLineHeight;
        cursorY -= 4;
    };

    content.push(renderPassportLogicalPdfText(
        "F2",
        titleSize,
        pageLeft,
        pageBottom,
        contentLeft,
        cursorY,
        title,
        rotate180
    ));

    cursorY -= 16;
    renderLeftRightRow("Maandinkomen", monthlyIncome);

    if (securitiesAmount) {
        renderLeftRightRow("Effectenportefeuille", securitiesAmount);
    }

    renderTitleDescriptionBlock("Levensstijl", livingStandardText);

    if (shareEntries.length > 0) {
        content.push(renderPassportLogicalPdfText(
            "F2",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            "Aandelen",
            rotate180
        ));
        cursorY -= bodyLineHeight;
        cursorY -= 2;
    }

    shareEntries.forEach((share) => {
        const companyName = normalizePassportPdfText(share?.companyName || "Onbekend bedrijf");
        const percentageLabel = normalizePassportPdfText(share?.percentageLabel || "");
        const percentageLines = wrapPassportTextToWidth(
            percentageLabel,
            bodySize,
            contentWidth,
            PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
        );

        content.push(renderPassportLogicalPdfText(
            "F2",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            companyName,
            rotate180
        ));

        percentageLines.forEach((line, index) => {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                contentLeft,
                cursorY - ((index + 1) * bodyLineHeight),
                line,
                rotate180
            ));
        });

        cursorY -= bodyLineHeight;
        cursorY -= percentageLines.length * bodyLineHeight;
        cursorY -= 3;
    });

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = isPassportPhysicalPageRightHand(page)
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(renderPassportLogicalPdfText(
        "F2",
        PASSPORT_PDF_SMALL_FONT_SIZE,
        pageLeft,
        pageBottom,
        pageNumberX,
        PASSPORT_PDF_GUIDE_OFFSET_PT + 3,
        pageNumberText,
        rotate180
    ));

    return content.join("\n");
}

function buildPassportPdfHealthReferencePageContent(page, slot) {
    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const rotate180 = Boolean(slot?.rotate180);
    const margin = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const contentLeft = margin;
    const contentRight = PASSPORT_PDF_WIDTH_PT - margin;
    const contentWidth = contentRight - contentLeft;
    const titleSize = 9.6;
    const bodySize = 5.7;
    const bodyLineHeight = 6.4;
    const symbolRadius = 5.8;
    const textColumnX = contentLeft + (20 * PASSPORT_PDF_PT_PER_MM);
    const textColumnWidth = contentRight - textColumnX;
    let cursorY = PASSPORT_PDF_HEIGHT_PT - margin - 7;

    const renderParagraph = (text, gapAfter = 4) => {
        const lines = wrapPassportTextToWidth(
            normalizePassportInlineText(text),
            bodySize,
            contentWidth,
            PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
        );

        lines.forEach((line, index) => {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                contentLeft,
                cursorY - (index * bodyLineHeight),
                line,
                rotate180
            ));
        });

        cursorY -= Math.max(1, lines.length) * bodyLineHeight;
        cursorY -= gapAfter;
    };

    const renderSymbolTextRow = (symbolConfig, text, options = {}) => {
        const topAlignY = cursorY;
        const lines = wrapPassportTextToWidth(
            normalizePassportInlineText(text),
            bodySize,
            textColumnWidth,
            PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
        );
        const textHeight = Math.max(1, lines.length) * bodyLineHeight;
        const rowHeight = Math.max(symbolRadius * 2, textHeight);
        const symbolCenterY = topAlignY - (rowHeight / 2);
        const symbolLeftX = contentLeft;
        const symbolTopY = symbolCenterY - symbolRadius;

        if (symbolConfig?.type === "circle") {
            content.push(renderPassportPdfCircle(
                pageLeft + symbolLeftX,
                pageBottom + symbolTopY,
                symbolRadius,
                {
                    lineWidth: 1.1,
                    strokeColor: symbolConfig.strokeColor,
                    fillColor: symbolConfig.fillColor
                }
            ));

            const valueText = String(symbolConfig?.text ?? "");
            const numberFontSize = Number(options?.numberFontSize || 6.2);
            const numberWidth = estimatePassportPdfTextWidth(valueText, numberFontSize, 0.5);
            content.push(renderPassportLogicalPdfText(
                "F2",
                numberFontSize,
                pageLeft,
                pageBottom,
                symbolLeftX + symbolRadius - (numberWidth / 2),
                symbolCenterY - (numberFontSize * 0.28),
                valueText,
                rotate180,
                { fillColor: symbolConfig.textColor || [0, 0, 0] }
            ));
        } else if (symbolConfig?.type === "x") {
            content.push(renderPassportLogicalPdfText(
                "F2",
                9.4,
                pageLeft,
                pageBottom,
                symbolLeftX + 1,
                symbolCenterY - 2.5,
                "X",
                rotate180
            ));
        }

        lines.forEach((line, index) => {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                textColumnX,
                topAlignY - (index * bodyLineHeight),
                line,
                rotate180
            ));
        });

        cursorY -= rowHeight;
        cursorY -= Number(options?.gapAfter || 5);
    };

    const renderSection = (title, topCount, topColors, topText, secondCount, secondColors, secondText, xText) => {
        content.push(renderPassportLogicalPdfText(
            "F2",
            titleSize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            title,
            rotate180
        ));
        cursorY -= 10;

        renderSymbolTextRow(
            {
                type: "circle",
                text: topCount,
                strokeColor: topColors.stroke,
                fillColor: topColors.fill || topColors.stroke,
                textColor: topColors.textColor || [1, 1, 1]
            },
            topText
        );
        renderSymbolTextRow(
            {
                type: "circle",
                text: secondCount,
                strokeColor: secondColors.stroke,
                fillColor: secondColors.fill || secondColors.stroke,
                textColor: secondColors.textColor || [1, 1, 1]
            },
            secondText
        );
        renderSymbolTextRow(
            { type: "x" },
            xText,
            { gapAfter: 7 }
        );
    };

    renderSection(
        "Lichamelijke gerondheid",
        Number(page?.templateData?.physicalGreenCount || 0),
        { stroke: [0.16, 0.55, 0.25] },
        "Zolang je nog groene ringen over hebt, ben je lichamelijk in goede conditie en is er niets aan de hand.",
        Number(page?.templateData?.physicalRedCount || 0),
        { stroke: [0.72, 0.15, 0.12] },
        "Ben je al je groene ringen kwijt, maar heb je nog rode ringen over, dan ben je gewond. Speel uit dat je pijn hebt. Laat je wonden verzorgen, ander raken ze geinfecteerd.",
        "Wanneer je al je fysieke ringen kwijt bent, krijg je een kritieke fysieke verwonde. Dat kan bijvoorbeeld een gebroken been zijn, of een doorboorde long. Tot je medische zorgen krijgt, kan je alleen over de grond kruipen en kermen van de pijn."
    );

    renderSection(
        "Mentale gerondheid",
        Number(page?.templateData?.mentalWhiteCount || 0),
        { stroke: [0.78, 0.78, 0.78], fill: [1, 1, 1], textColor: [0, 0, 0] },
        "Zolang je nog witte ringen over hebt, ben je mentaal in goede stabiel en is er niets aan de hand.",
        Number(page?.templateData?.mentalYellowCount || 0),
        { stroke: [0.8, 0.68, 0.05] },
        "Ben je al je witte ringen kwijt, maar heb je nog gele ringen over, dan ben je gestresseerd. Je hebt last van psychotische waanbeelden en bent emotioneel onstabiel. Kalmeringsmiddelen voorgeschreven door een bekwaam psycholoog, kunnen je kalmeren.",
        "Wanneer je al je mentale ringen kwijt bent, krijg je een kritiekementale aandoening. Dat kan bijvoorbeeld oncontroleerbare kleptomanische neigingen krijgen, of krijg je een schitzofrene aandoening. Tot je medische zorgen krijgt, zit je in een vast in een ernstige episode van je aandoening en ben je in onhandelbare paniek of helemaal in jezelf gekeerd."
    );

    content.push(renderPassportLogicalPdfText(
        "F2",
        titleSize,
        pageLeft,
        pageBottom,
        contentLeft,
        cursorY,
        "Ringen verliezen",
        rotate180
    ));
    cursorY -= 10;

    renderParagraph("Fysieke ringen verlies je doorgaans door terecht te komen in geweldadige omstandigheden. Slag en steekwapens doen doorgaans 1 punt schade per rake klap, vuurwapens veroorzaken 2 punten schade.", 5);
    renderParagraph("Mentale schade wordt veroorzaakt door stresvolle situaties. Dat kan een hevige ruzie zijn waarin je verwikkeld bent geraakt, of betrokken zijn in een gevecht. Dit soort situaties kost je doorgaans 1 mentale ring. Extreme toestanden, zoals marteling, moord of aanraking met het bovennatuurlijke, geven je 2 tot 3 stress.", 4);

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = isPassportPhysicalPageRightHand(page)
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(renderPassportLogicalPdfText(
        "F2",
        PASSPORT_PDF_SMALL_FONT_SIZE,
        pageLeft,
        pageBottom,
        pageNumberX,
        PASSPORT_PDF_GUIDE_OFFSET_PT + 3,
        pageNumberText,
        rotate180
    ));

    return content.join("\n");
}

function buildPassportPdfTraitOverviewPageContent(page, slot) {
    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const rotate180 = Boolean(slot?.rotate180);
    const margin = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const contentLeft = margin;
    const contentRight = PASSPORT_PDF_WIDTH_PT - margin;
    const contentWidth = contentRight - contentLeft;
    const title = normalizePassportPdfText(page?.templateData?.title || "Statuseigenschappen");
    const entries = Array.isArray(page?.templateData?.entries) ? page.templateData.entries : [];
    const titleSize = 12;
    const bodySize = 7;
    const bodyLineHeight = 8.4;
    let cursorY = PASSPORT_PDF_HEIGHT_PT - margin - 7;

    content.push(renderPassportLogicalPdfText(
        "F2",
        titleSize,
        pageLeft,
        pageBottom,
        contentLeft,
        cursorY,
        title,
        rotate180
    ));

    cursorY -= 16;

    entries.forEach((entry) => {
        const label = normalizePassportPdfText(entry?.label || "");
        const description = normalizePassportPdfText(entry?.description || "");
        const descriptionLines = description
            ? wrapPassportTextToWidth(
                description,
                bodySize,
                contentWidth,
                PASSPORT_PDF_DETAIL_TEXT_WIDTH_FACTOR
            )
            : [];

        content.push(renderPassportLogicalPdfText(
            "F2",
            bodySize,
            pageLeft,
            pageBottom,
            contentLeft,
            cursorY,
            label,
            rotate180
        ));

        descriptionLines.forEach((line, index) => {
            content.push(renderPassportLogicalPdfText(
                "F1",
                bodySize,
                pageLeft,
                pageBottom,
                contentLeft,
                cursorY - ((index + 1) * bodyLineHeight),
                line,
                rotate180
            ));
        });

        cursorY -= bodyLineHeight;
        cursorY -= descriptionLines.length * bodyLineHeight;

        cursorY -= 3;
    });

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = isPassportPhysicalPageRightHand(page)
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(renderPassportLogicalPdfText(
        "F2",
        PASSPORT_PDF_SMALL_FONT_SIZE,
        pageLeft,
        pageBottom,
        pageNumberX,
        PASSPORT_PDF_GUIDE_OFFSET_PT + 3,
        pageNumberText,
        rotate180
    ));

    return content.join("\n");
}

function buildPassportPdfLogicalPageTextContent(page, slot) {
    if (!page) {
        return "";
    }

    if (String(page?.template || "") === "personal_identity_front") {
        return buildPassportPdfPersonalIdentityPageContent(page, slot);
    }

    if (String(page?.template || "") === "health_reference") {
        return buildPassportPdfHealthReferencePageContent(page, slot);
    }

    if (String(page?.template || "") === "skill_detail") {
        return buildPassportPdfSkillDetailPageContent(page, slot);
    }

    if (String(page?.template || "") === "wealth_overview") {
        return buildPassportPdfWealthOverviewPageContent(page, slot);
    }

    if (String(page?.template || "") === "trait_overview") {
        return buildPassportPdfTraitOverviewPageContent(page, slot);
    }

    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const pageIsRightHand = isPassportPhysicalPageRightHand(page);
    const rotate180 = Boolean(slot?.rotate180);
    const textX = PASSPORT_PDF_CONTENT_MARGIN_PT;
    let localY = PASSPORT_PDF_HEIGHT_PT - PASSPORT_PDF_CONTENT_MARGIN_PT;

    if (page.isBlank) {
        const lineLeft = PASSPORT_PDF_GUIDE_OFFSET_PT;
        const lineRight = PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT;
        const topY = PASSPORT_PDF_HEIGHT_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 12;
        const bottomY = PASSPORT_PDF_GUIDE_OFFSET_PT + 18;
        const lineCount = 14;
        const spacing = (topY - bottomY) / Math.max(1, lineCount - 1);

        for (let index = 0; index < lineCount; index += 1) {
            const lineY = topY - (index * spacing);
            content.push(renderPassportLogicalPdfLine(
                pageLeft,
                pageBottom,
                lineLeft,
                lineY,
                lineRight,
                lineY,
                rotate180,
                [],
                0.45
            ));
        }
    }

    if (!page.isBlank && page.sectionTitle) {
        content.push(renderPassportLogicalPdfText(
            "F2",
            PASSPORT_PDF_TITLE_FONT_SIZE,
            pageLeft,
            pageBottom,
            textX,
            localY,
            page.sectionTitle,
            rotate180
        ));
        localY -= PASSPORT_PDF_TITLE_LINE_HEIGHT;
    }

    if (!page.isBlank && page.subtitle) {
        content.push(renderPassportLogicalPdfText(
            "F1",
            PASSPORT_PDF_SMALL_FONT_SIZE,
            pageLeft,
            pageBottom,
            textX,
            localY,
            page.subtitle,
            rotate180
        ));
        localY -= PASSPORT_PDF_SMALL_LINE_HEIGHT;
    }

    if (!page.isBlank) {
        localY -= 4;
        (Array.isArray(page?.lines) ? page.lines : []).forEach((line) => {
            if (line.type === "spacer") {
                localY -= line.height;
                return;
            }

            const fontKey = line.font === "bold" ? "F2" : "F1";
            content.push(
                renderPassportLogicalPdfText(
                    fontKey,
                    line.size,
                    pageLeft,
                    pageBottom,
                    textX + (line.indent || 0),
                    localY,
                    line.text,
                    rotate180
                )
            );
            localY -= line.lineHeight;
        });
    }

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberY = PASSPORT_PDF_GUIDE_OFFSET_PT + 3;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = pageIsRightHand
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(
        renderPassportLogicalPdfText(
            "F2",
            PASSPORT_PDF_SMALL_FONT_SIZE,
            pageLeft,
            pageBottom,
            pageNumberX,
            pageNumberY,
            pageNumberText,
            rotate180
        )
    );

    return content.join("\n");
}

function buildPassportPersonalPageFields(character) {
    const isUpperClass = String(character?.class || "").trim().toLowerCase() === "upper class";
    const professionTrait = getPassportPrimaryProfessionTrait(character);
    const militaryRankTrait = getPassportMilitaryRankTrait(character);
    const birthValue = [String(character?.birthPlace || "").trim(), formatPassportLongDate(character?.birthDate)].filter(Boolean).join(" - ");
    const residenceLines = getPassportResidenceLines(character);

    const fields = [];
    fields.push({ label: "naam - nom - name", valueLines: [String(character?.lastName || "").trim()] });
    fields.push({ label: "voornaam - prenom - first name", valueLines: [String(character?.firstName || "").trim()] });
    fields.push({ label: "burgerlijke staat - etat civil - marital status", valueLines: [String(character?.maritalStatus || "").trim()] });
    fields.push({ label: "geboren te / op - ne en / en - born at / on", valueLines: [birthValue] });
    fields.push({ label: "nationaliteit - nationalite - nationality", valueLines: [String(character?.nationality || "").trim()] });
    fields.push(
        isUpperClass
            ? { label: "predicaat - predicat - predicate", valueLines: [String(character?.title || "").trim()] }
            : { label: "aanspreking - adresse - address", valueLines: [String(character?.title || "").trim()] }
    );

    if (!isUpperClass) {
        fields.push({
            label: "beroep - profession - profession",
            valueLines: [String(professionTrait?.name || "").trim()]
        });
    }

    fields.push({
        label: "woonplaats - residence - place of residence",
        valueLines: residenceLines.length > 0 ? residenceLines : [""]
    });

    if (militaryRankTrait && String(militaryRankTrait?.name || "").trim()) {
        fields.push({
            label: "militaire rang - grade militaire - military rank",
            valueLines: [String(militaryRankTrait?.name || "").trim()]
        });
    }

    return fields;
}

function buildPassportPdfPersonalIdentityPageContent(page, slot) {
    const content = [];
    const pageLeft = slot.x;
    const pageBottom = slot.y;
    const rotate180 = Boolean(slot?.rotate180);
    const margin = PASSPORT_PDF_GUIDE_OFFSET_PT;
    const lineLeft = margin;
    const lineRight = PASSPORT_PDF_WIDTH_PT - margin;
    const fields = Array.isArray(page?.templateData?.fields) ? page.templateData.fields : [];

    let cursorY = PASSPORT_PDF_HEIGHT_PT - margin - 8;
    const labelSize = 6.6;
    const scriptSize = 12.6;
    const scriptLineHeight = 15.5;
    const fieldGap = 0;
    const labelToValueGap = 10.5;
    const scriptYOffset = -1.5;

    fields.forEach((field) => {
        content.push(renderPassportLogicalPdfText(
            "F1",
            labelSize,
            pageLeft,
            pageBottom,
            lineLeft,
            cursorY,
            String(field?.label || ""),
            rotate180
        ));
        cursorY -= labelToValueGap;

        const valueLines = Array.isArray(field?.valueLines) && field.valueLines.length > 0
            ? field.valueLines
            : [""];

        valueLines.forEach((valueLine) => {
            const lineY = cursorY - 1.5;
            content.push(renderPassportLogicalPdfLine(
                pageLeft,
                pageBottom,
                lineLeft,
                lineY,
                lineRight,
                lineY,
                rotate180,
                [],
                0.45
            ));

            const valueText = String(valueLine || "").trim();
            if (valueText) {
                content.push(renderPassportLogicalPdfText(
                    "F3",
                    scriptSize,
                    pageLeft,
                    pageBottom,
                    lineLeft + 4,
                    cursorY + scriptYOffset,
                    valueText,
                    rotate180,
                    { fillColor: PASSPORT_PDF_HANDWRITING_COLOR }
                ));
            }

            cursorY -= scriptLineHeight;
        });

        cursorY -= fieldGap;
    });

    content.push(renderPassportLogicalPdfText(
        "F1",
        7,
        pageLeft,
        pageBottom,
        lineLeft,
        cursorY + 2,
        "handtekening houder",
        rotate180
    ));

    const registryNumber = String(page?.templateData?.stateRegisterNumber || "").trim() || "XXXX-XXXX";
    content.push(renderPassportLogicalPdfText(
        "F1",
        7.5,
        pageLeft,
        pageBottom,
        lineLeft + 22,
        28,
        `N\u00B0 ${registryNumber}`,
        rotate180
    ));

    const footerLines = [
        "Op iedere vervalsing van dit paspoort staat correctionele straf.",
        "Toute falsification de ce passeport est passible de sanctions correctionnelles.",
        "Any falsification of this passport is subject to criminal penalties."
    ];
    let footerY = 19;
    footerLines.forEach((footerLine) => {
        content.push(renderPassportLogicalPdfText(
            "F1",
            4.25,
            pageLeft,
            pageBottom,
            lineLeft,
            footerY,
            footerLine,
            rotate180
        ));
        footerY -= 5.4;
    });

    const pageNumberText = `P${getPassportPhysicalPageNumber(page)}`;
    const pageNumberWidth = estimatePassportPdfTextWidth(pageNumberText, PASSPORT_PDF_SMALL_FONT_SIZE);
    const pageNumberX = isPassportPhysicalPageRightHand(page)
        ? PASSPORT_PDF_WIDTH_PT - PASSPORT_PDF_GUIDE_OFFSET_PT - 3 - pageNumberWidth
        : PASSPORT_PDF_GUIDE_OFFSET_PT + 3;

    content.push(
        renderPassportLogicalPdfText(
            "F2",
            PASSPORT_PDF_SMALL_FONT_SIZE,
            pageLeft,
            pageBottom,
            pageNumberX,
            PASSPORT_PDF_GUIDE_OFFSET_PT + 3,
            pageNumberText,
            rotate180
        )
    );

    return content.join("\n");
}

function buildPassportPdfSheetContent(sheet, sideKey, pageAssetsByNumber = {}) {
    const slots = getPassportSheetSlots(sheet?.layout);
    const pages = Array.isArray(sheet?.[sideKey]) ? sheet[sideKey] : [];
    const guideContent = buildPassportPdfSheetGuides(sheet?.layout);
    const pageBackgroundContent = pages.map((page, index) => {
        const slot = slots[index];
        if (!slot || !page) return "";
        const pageAsset = pageAssetsByNumber[getPassportPhysicalPageNumber(page)];
        if (!pageAsset) return "";
        const placement = getPassportPageImagePlacement(slot, index, sheet?.layout);
        return renderPassportPdfImage(
            pageAsset.name,
            placement.x,
            placement.y,
            placement.width,
            placement.height,
            Boolean(slot?.rotate180)
        );
    }).filter(Boolean).join("\n");
    const textContent = pages.map((page, index) => {
        const slot = slots[index];
        if (!slot || !page) return "";
        return buildPassportPdfLogicalPageTextContent(page, slot);
    }).filter(Boolean).join("\n");

    return [guideContent, pageBackgroundContent, textContent].filter(Boolean).join("\n");
}

async function buildPassportPdfBlob(pages) {
    const logicalPages = preparePassportLogicalPagesForImposition(pages);
    const regularFont = await loadPassportPdfFontAsset(
        PASSPORT_PDF_FONT_REGULAR_PATH,
        "aetherPassportPdfRegularFont",
        PASSPORT_PDF_FONT_REGULAR_NAME
    );
    const boldFont = await loadPassportPdfFontAsset(
        PASSPORT_PDF_FONT_BOLD_PATH,
        "aetherPassportPdfBoldFont",
        PASSPORT_PDF_FONT_BOLD_NAME
    );
    const scriptFont = await loadPassportPdfFontAsset(
        PASSPORT_PDF_FONT_SCRIPT_PATH,
        "aetherPassportPdfScriptFont",
        PASSPORT_PDF_FONT_SCRIPT_NAME
    );
    const sheets = createPassportImpositionSheets(logicalPages, getPassportPrintMode());
    const pageRenderPlansByNumber = {};
    sheets.forEach((sheet) => {
        ["front", "back"].forEach((sideKey) => {
            const slotPages = Array.isArray(sheet?.[sideKey]) ? sheet[sideKey] : [];
            slotPages.forEach((page, slotIndex) => {
                const pageNumber = getPassportPhysicalPageNumber(page);
                if (!pageNumber) return;
                pageRenderPlansByNumber[pageNumber] = {
                    layout: sheet.layout,
                    sidePosition: getPassportSlotSidePosition(slotIndex),
                    rotate180: Boolean(getPassportSheetSlots(sheet.layout)?.[slotIndex]?.rotate180)
                };
            });
        });
    });
    const pageAssets = await Promise.all(logicalPages.map((page, index) => {
        const plan = pageRenderPlansByNumber[page.physicalPageNumber] || {
            layout: "two-up",
            sidePosition: isPassportPhysicalPageRightHand(page) ? "right" : "left",
            rotate180: false
        };

        return createPassportPdfPageBackgroundAsset(
            page,
            plan.sidePosition,
            plan.layout,
            `ImPassportPage${index + 1}`,
            {
                shiftUpPt: plan.layout === "four-up" && plan.rotate180 ? PASSPORT_PDF_BLEED_PT : 0
            }
        );
    }));
    const pageAssetsByNumber = Object.fromEntries(pageAssets.map((asset, index) => ([
        logicalPages[index].physicalPageNumber,
        asset
    ])));
    const imposedPdfPages = sheets.flatMap((sheet) => ([
        buildPassportPdfSheetContent(sheet, "front", pageAssetsByNumber),
        buildPassportPdfSheetContent(sheet, "back", pageAssetsByNumber)
    ]));
    const objects = [];
    let nextId = 1;
    const reserveId = () => nextId++;
    const setObject = (id, value) => {
        objects[id] = value;
    };

    const fontRegularFileId = regularFont ? reserveId() : null;
    const fontRegularDescriptorId = regularFont ? reserveId() : null;
    const fontRegularId = reserveId();
    const fontBoldFileId = boldFont ? reserveId() : null;
    const fontBoldDescriptorId = boldFont ? reserveId() : null;
    const fontBoldId = reserveId();
    const fontScriptFileId = scriptFont ? reserveId() : null;
    const fontScriptDescriptorId = scriptFont ? reserveId() : null;
    const fontScriptId = reserveId();
    const imageObjectIds = pageAssets.map(() => reserveId());
    const contentIds = imposedPdfPages.map(() => reserveId());
    const pageIds = imposedPdfPages.map(() => reserveId());
    const pagesId = reserveId();
    const catalogId = reserveId();
    const imageResources = [];
    const fontResourceEntries = [];

    if (regularFont && fontRegularFileId && fontRegularDescriptorId && fontRegularId) {
        const regularFontHex = convertPassportBytesToHex(regularFont.bytes);
        setObject(
            fontRegularFileId,
            `<< /Length ${regularFontHex.length} /Length1 ${regularFont.bytes.length} /Filter /ASCIIHexDecode >>\nstream\n${regularFontHex}\nendstream`
        );
        setObject(
            fontRegularDescriptorId,
            `<< /Type /FontDescriptor /FontName /${regularFont.baseFontName} /Flags ${regularFont.flags} /FontBBox [${regularFont.bbox.join(" ")}] /ItalicAngle ${regularFont.italicAngle.toFixed(2)} /Ascent ${regularFont.ascent} /Descent ${regularFont.descent} /CapHeight ${regularFont.capHeight} /StemV ${regularFont.stemV} /MissingWidth ${regularFont.missingWidth} /FontFile2 ${fontRegularFileId} 0 R >>`
        );
        setObject(
            fontRegularId,
            `<< /Type /Font /Subtype /TrueType /BaseFont /${regularFont.baseFontName} /FirstChar ${regularFont.firstChar} /LastChar ${regularFont.lastChar} /Widths [${regularFont.widths.join(" ")}] /Encoding /WinAnsiEncoding /FontDescriptor ${fontRegularDescriptorId} 0 R >>`
        );
        fontResourceEntries.push(`/F1 ${fontRegularId} 0 R`);
    } else if (fontRegularId) {
        setObject(
            fontRegularId,
            "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>"
        );
        fontResourceEntries.push(`/F1 ${fontRegularId} 0 R`);
    }

    if (boldFont && fontBoldFileId && fontBoldDescriptorId && fontBoldId) {
        const boldFontHex = convertPassportBytesToHex(boldFont.bytes);
        setObject(
            fontBoldFileId,
            `<< /Length ${boldFontHex.length} /Length1 ${boldFont.bytes.length} /Filter /ASCIIHexDecode >>\nstream\n${boldFontHex}\nendstream`
        );
        setObject(
            fontBoldDescriptorId,
            `<< /Type /FontDescriptor /FontName /${boldFont.baseFontName} /Flags ${boldFont.flags} /FontBBox [${boldFont.bbox.join(" ")}] /ItalicAngle ${boldFont.italicAngle.toFixed(2)} /Ascent ${boldFont.ascent} /Descent ${boldFont.descent} /CapHeight ${boldFont.capHeight} /StemV ${boldFont.stemV} /MissingWidth ${boldFont.missingWidth} /FontFile2 ${fontBoldFileId} 0 R >>`
        );
        setObject(
            fontBoldId,
            `<< /Type /Font /Subtype /TrueType /BaseFont /${boldFont.baseFontName} /FirstChar ${boldFont.firstChar} /LastChar ${boldFont.lastChar} /Widths [${boldFont.widths.join(" ")}] /Encoding /WinAnsiEncoding /FontDescriptor ${fontBoldDescriptorId} 0 R >>`
        );
        fontResourceEntries.push(`/F2 ${fontBoldId} 0 R`);
    } else if (fontBoldId) {
        setObject(
            fontBoldId,
            "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>"
        );
        fontResourceEntries.push(`/F2 ${fontBoldId} 0 R`);
    }

    if (scriptFont && fontScriptFileId && fontScriptDescriptorId && fontScriptId) {
        const scriptFontHex = convertPassportBytesToHex(scriptFont.bytes);
        setObject(
            fontScriptFileId,
            `<< /Length ${scriptFontHex.length} /Length1 ${scriptFont.bytes.length} /Filter /ASCIIHexDecode >>\nstream\n${scriptFontHex}\nendstream`
        );
        setObject(
            fontScriptDescriptorId,
            `<< /Type /FontDescriptor /FontName /${scriptFont.baseFontName} /Flags ${scriptFont.flags} /FontBBox [${scriptFont.bbox.join(" ")}] /ItalicAngle ${scriptFont.italicAngle.toFixed(2)} /Ascent ${scriptFont.ascent} /Descent ${scriptFont.descent} /CapHeight ${scriptFont.capHeight} /StemV ${scriptFont.stemV} /MissingWidth ${scriptFont.missingWidth} /FontFile2 ${fontScriptFileId} 0 R >>`
        );
        setObject(
            fontScriptId,
            `<< /Type /Font /Subtype /TrueType /BaseFont /${scriptFont.baseFontName} /FirstChar ${scriptFont.firstChar} /LastChar ${scriptFont.lastChar} /Widths [${scriptFont.widths.join(" ")}] /Encoding /WinAnsiEncoding /FontDescriptor ${fontScriptDescriptorId} 0 R >>`
        );
        fontResourceEntries.push(`/F3 ${fontScriptId} 0 R`);
    } else {
        setObject(
            fontScriptId,
            "<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>"
        );
        fontResourceEntries.push(`/F3 ${fontScriptId} 0 R`);
    }

    pageAssets.forEach((asset, index) => {
        const imageId = imageObjectIds[index];
        imageResources.push(`/${asset.name} ${imageId} 0 R`);
        setObject(
            imageId,
            `<< /Type /XObject /Subtype /Image /Width ${asset.width} /Height ${asset.height} /ColorSpace /${asset.colorSpace} /BitsPerComponent ${asset.bitsPerComponent} /Filter [/ASCIIHexDecode /DCTDecode] /Length ${asset.hexData.length} >>\nstream\n${asset.hexData}\nendstream`
        );
    });

    imposedPdfPages.forEach((pageContent, index) => {
        const streamContent = pageContent;
        setObject(
            contentIds[index],
            `<< /Length ${streamContent.length} >>\nstream\n${streamContent}\nendstream`
        );
        setObject(
            pageIds[index],
            `<< /Type /Page /Parent ${pagesId} 0 R /MediaBox [0 0 ${PASSPORT_PDF_A4_WIDTH_PT.toFixed(2)} ${PASSPORT_PDF_A4_HEIGHT_PT.toFixed(2)}] /Resources << /Font << ${fontResourceEntries.join(" ")} >> /XObject << ${imageResources.join(" ")} >> >> /Contents ${contentIds[index]} 0 R >>`
        );
    });

    setObject(
        pagesId,
        `<< /Type /Pages /Kids [${pageIds.map((id) => `${id} 0 R`).join(" ")}] /Count ${pageIds.length} >>`
    );
    setObject(catalogId, `<< /Type /Catalog /Pages ${pagesId} 0 R >>`);

    let pdf = "%PDF-1.4\n";
    const offsets = [0];

    for (let id = 1; id < nextId; id += 1) {
        offsets[id] = pdf.length;
        pdf += `${id} 0 obj\n${objects[id]}\nendobj\n`;
    }

    const xrefOffset = pdf.length;
    pdf += `xref\n0 ${nextId}\n`;
    pdf += "0000000000 65535 f \n";
    for (let id = 1; id < nextId; id += 1) {
        pdf += `${String(offsets[id]).padStart(10, "0")} 00000 n \n`;
    }

    pdf += `trailer\n<< /Size ${nextId} /Root ${catalogId} 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
    return new Blob([encodePassportPdfLatin1(pdf)], { type: "application/pdf" });
}

function collectSelectedPassportPages(character) {
    const sections = getPassportSectionDefinitions(character);
    const pages = [];

    sections.forEach((section) => {
        if (section.required) {
            pages.push(...section.getPages());
            return;
        }

        if (section.available && window.aetherPassportSelections[section.id]) {
            pages.push(...section.getPages());
        }
    });

    return pages;
}

async function downloadCharacterPassportPdf(character) {
    const pages = collectSelectedPassportPages(character);
    if (pages.length === 0) {
        alert("Er zijn geen pagina's geselecteerd voor het paspoort.");
        return;
    }

    const blob = await buildPassportPdfBlob(pages);
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    const fileName = normalizePassportPdfText(`${character?.firstName || "personage"}-${character?.lastName || "paspoort"}`)
        .replace(/\s+/g, "-")
        .toLowerCase();

    anchor.href = url;
    anchor.download = `${fileName || "paspoort"}.pdf`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function setupPassportSectionListeners() {
    if (window.aetherPassportListenersInitialized) return;
    window.aetherPassportListenersInitialized = true;

    document.addEventListener("change", (event) => {
        const toggle = event.target.closest("[data-role='passport-section-toggle']");
        if (toggle) {
            const section = String(toggle.dataset.section || "");
            if (!section) return;

            window.aetherPassportSelections[section] = Boolean(toggle.checked);
            if (currentCharacter) {
                renderPassportTab(currentCharacter);
            }
            return;
        }

        const printMode = event.target.closest("[data-role='passport-print-mode']");
        if (printMode) {
            window.aetherPassportSelections.printMode = String(printMode.value || "paper-save");
            if (currentCharacter) {
                renderPassportTab(currentCharacter);
            }
        }
    });

    document.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-action='generate-character-passport-pdf']");
        if (!button) return;

        if (!currentCharacter) {
            alert("Geen personage geladen.");
            return;
        }

        button.disabled = true;
        downloadCharacterPassportPdf(currentCharacter).catch(() => {
            alert("Kon het paspoort-PDF niet maken.");
        }).finally(() => {
            button.disabled = false;
        });
    });
}

setupPassportSectionListeners();
