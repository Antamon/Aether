// Init na load
window.addEventListener("load", () => {
    initCharacterPage();
    setupSkillListeners();
});

async function initCharacterPage() {
    try {
        // Huidige gebruiker ophalen (gebruik globale indien al gezet)
        currentUser = window.AETHER_CURRENT_USER || await fetchCurrentUser();
        window.AETHER_CURRENT_USER = currentUser;

        // Lijst met personages
        await characterList();

        // Eventlisteners & autosave instellen
        setupCharacterFormListeners();

        // Oorspronkelijke markup van het karakterformulier bewaren
        const formContainer = document.getElementById("characterForm");
        if (formContainer && originalCharacterFormHtml === null) {
            originalCharacterFormHtml = formContainer.innerHTML;
        }

    } catch (err) {
        console.error("Fout bij initialiseren personagepagina:", err);
    }
}
// -----------------------
//  Character ophalen
// -----------------------

async function getCharacter(id, openCollapseId = null) {
    try {
        const data = await fetchCharacter(id);

        if (!data) return;

        // Bewaar globally zodat updateSkillLevel types/levels kent
        currentCharacter = data;

        // Debug
        console.log("Character from API:", data);
        console.log("Skills from API:", data.skills);

        // Gebruikte XP Ã©Ã©n keer berekenen
        const usedExperience = calculateExperience(data);

        // Navigatie bovenaan
        pageNav(currentUser.role, data);
        setActiveNavTab("sheet");
        showSheetTab();

        // Bepalen of dit personage bewerkbaar is voor de huidige user
        const role = currentUser?.role || "";
        const isAdmin = role === "administrator" || role === "director";

        const isOwnerPlayer =
            !isAdmin &&
            data.type === "player" &&
            Number(data.idUser) === Number(currentUser.id);

        const canEdit = isAdmin || isOwnerPlayer;

        console.log("currentUser:", currentUser);
        console.log("character type:", data.type, "idUser:", data.idUser);
        console.log("isAdmin:", isAdmin, "isOwnerPlayer:", isOwnerPlayer, "canEdit:", canEdit);

        // Algemene zichtbaarheid
        const grpNew = document.getElementById("groupNewCharacter");
        if (grpNew) grpNew.classList.add("d-none");
        const formEl = document.getElementById("characterForm");
        if (formEl) formEl.classList.remove("d-none");
        const skillsEl = document.getElementById("skills");
        if (skillsEl) skillsEl.classList.remove("d-none");
        const idInput = document.getElementById("idCharacter");
        if (idInput) idInput.value = data["id"];

        const formContainer = document.getElementById("characterForm");
        const accordionSkills = document.getElementById("accordionSkills");

        // --- EDITABLE MODE (admin of owner-player) ---
        if (canEdit) {
            // Originele form-markup terugzetten (indien overschreven)
            if (originalCharacterFormHtml !== null) {
                formContainer.innerHTML = originalCharacterFormHtml;
                // Events opnieuw koppelen aan inputs
                setupCharacterFormListeners();
            }

            // Velden invullen
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
                "maritalStatus",
                "profession"
            ];

            velden.forEach((veld) => {
                const el = document.getElementById(veld);
                if (el) {
                    el.value = data[veld] ?? "";
                }
            });

            // Skills: normale accordion met knoppen
            if (accordionSkills) {
                accordionSkills.innerHTML = "";
                (data["skills"] || []).forEach((skill) => {
                    accordionSkills.appendChild(
                        addSkillAccordionItem(skill, usedExperience, data)
                    );
                });
            }

            // Nieuwe skills mogen toegevoegd worden
            const selNew = document.getElementById("idNewSkill");
            const btnAdd = document.getElementById("addNewSkill");
            if (selNew) selNew.disabled = false;
            if (btnAdd) btnAdd.disabled = false;

            // Eventuele extra readOnly-logica
            if (typeof applyCharacterEditability === "function") {
                applyCharacterEditability(data, true);
            }

        // --- READ-ONLY MODE (participant die naar extra / andere speler kijkt) ---
        } else {

            // Karaktergegevens in tekst-layout weergeven
            renderCharacterDetailsReadOnly(data);

            // Skills als leesbare lijst
            if (accordionSkills) {
            renderSkillsReadOnly(accordionSkills, data.skills || []);
            }

            // Nieuwe skills niet toe te voegen
            const selNew = document.getElementById("idNewSkill");
            const btnAdd = document.getElementById("addNewSkill");
            if (selNew) selNew.disabled = true;
            if (btnAdd) {
                btnAdd.disabled = true;
                btnAdd.classList.add("disabled");
            }
        }

        // Nieuwe skillslijst ophalen (voor editable case; voor read-only geeft visibility toch al filter)
        getNewSkills(data["id"]);

        initTooltips();

        // Na hertekenen: dezelfde collapse weer openklappen (enkel zinvol in editable mode)
        if (canEdit && openCollapseId) {
            const collapseEl = document.getElementById(openCollapseId);
            if (collapseEl) {
                collapseEl.classList.add("show");
                const headerBtn = collapseEl.previousElementSibling
                    ? collapseEl.previousElementSibling.querySelector("button")
                    : null;
                if (headerBtn) {
                    headerBtn.classList.remove("collapsed");
                    headerBtn.setAttribute("aria-expanded", "true");
                }
            }
        }

    } catch (err) {
        console.error("Fout bij ophalen personage:", err);
    }
}

// -----------------------
//  Lijst van personages
// -----------------------

async function characterList() {
    try {
        const data = await fetchCharacterList(currentUser["role"]);

        const nameList = document.getElementById("characterNames");
        while (nameList.firstChild) {
            nameList.removeChild(nameList.lastChild);
        }

        if (!data) return;

        for (const value of Object.values(data)) {
            const newButton = document.createElement("button");
            newButton.classList.add("btn", "btn-primary", "btn-sm", "m-1");
            newButton.id = "showCharacter" + value["id"];
            newButton.innerHTML = value["firstName"] + " " + value["lastName"];
            newButton.setAttribute("data-idcharacter", value["id"]);
            newButton.setAttribute("data-bs-dismiss", "offcanvas");

            newButton.addEventListener("click", (e) => {
                document.getElementById("characterForm").classList.remove("d-none");
                document.getElementById("skills").classList.remove("d-none");
                getCharacter(e.target.dataset.idcharacter);
            });

            nameList.appendChild(newButton);
        }
    } catch (err) {
        console.error("Fout bij ophalen personagelijst:", err);
    }
}











