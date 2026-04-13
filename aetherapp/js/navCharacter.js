// Navigation/header rendering for the character sheet

function canEditCharacterNav(character) {
    if (!currentUser || !character) return false;

    const role = currentUser.role;
    if (role === "administrator" || role === "director") {
        return true;
    }

    return role === "participant"
        && character.type === "player"
        && Number(character.idUser) === Number(currentUser.id);
}

async function updateExperienceToTrait(character, delta) {
    if (!character || character.type !== "player") return;

    const currentValue = Number(character.experienceToTrait) || 0;
    const nextValue = Math.max(0, Math.min(6, currentValue + delta));

    if (nextValue === currentValue) return;

    const openCollapse = document.querySelector("#accordionSkills .accordion-collapse.show");
    const openCollapseId = openCollapse ? openCollapse.id : null;

    try {
        await apiFetchJson("api/characters/updateCharacter.php", {
            method: "POST",
            body: {
                id: character.id,
                experienceToTrait: nextValue
            }
        });

        getCharacter(character.id, openCollapseId);
    } catch (err) {
        console.error("Fout bij omzetten van ervaringspunten naar statuspunten:", err);
    }
}

function statusLabelFromPoints(statusPoints) {
    if (statusPoints <= 14) return "Average person";
    if (statusPoints < 25) return "Influential";
    if (statusPoints < 35) return "Elite";
    return "Secret master";
}

function renderCharacterNav(isAdminView) {
    const container = document.getElementById("pageNav");
    if (!container) return;

    const templateId = isAdminView ? "tplNavSheetAdmin" : "tplNavSheetParticipant";
    const tpl = document.getElementById(templateId);

    if (!tpl) {
        console.error("Template niet gevonden:", templateId);
        return;
    }

    container.innerHTML = "";
    container.appendChild(tpl.content.cloneNode(true));
    container.classList.remove("d-none");
}

function getActiveNavTabName() {
    const activeTab = document.querySelector("#navSheet .navTab.active");
    if (!activeTab) {
        return "sheet";
    }

    return activeTab.dataset.tab || "sheet";
}

function showCharacterTab(targetTab, character) {
    if (targetTab === "background") {
        showBackgroundTab(currentCharacter || character);
        return;
    }

    if (targetTab === "diary") {
        showDiaryTab(currentCharacter || character);
        return;
    }

    if (targetTab === "personality") {
        showPersonalityTab(currentCharacter || character);
        return;
    }

    if (targetTab === "economy") {
        showEconomyTab(currentCharacter || character);
        return;
    }

    if (targetTab === "passport") {
        showPassportTab(currentCharacter || character);
        return;
    }

    showSheetTab();
}

function pageNav(userRole, character, activeTab = "sheet") {
    const usedExperience = calculateExperience(character);
    const remainingExperience = getRemainingExperience(character);
    const maxExperience = getMaxExperience(character);
    const spentExperience = Math.max(0, maxExperience - remainingExperience);
    const availableStatusPoints = getAvailableStatusPoints(character);
    const maxStatusPoints = getMaxStatusPoints(character);
    const usedStatusPoints = getUsedStatusPoints(character);
    const convertedExperience = Number(character.experienceToTrait) || 0;
    const statusLabel = statusLabelFromPoints(usedStatusPoints);
    const expertise = expertiseExtra(usedExperience);
    const canEditNav = canEditCharacterNav(character);
    const canConvertToStatus = canEditNav
        && character.type === "player"
        && character.state === "draft";

    const isAdmin = userRole === "administrator" || userRole === "director";

    if (isAdmin) {
        renderCharacterNav(true);

        function getUserList() {
            apiFetchJson("api/users/getUserList.php")
                .then((data) => {
                    if (!data) return;

                    const userList = [];
                    for (const value of Object.values(data)) {
                        userList.push({
                            id: value.id,
                            text: value.firstName + " " + value.lastName
                        });
                    }

                    new DropdownInput(
                        "listParticipant",
                        userList,
                        async (selectedId) => {
                            let newIdUser;

                            if (selectedId === null || selectedId === undefined || selectedId === "") {
                                newIdUser = 0;
                            } else {
                                newIdUser = parseInt(selectedId, 10);
                            }

                            if (character.idUser == newIdUser) {
                                return;
                            }

                            try {
                                await apiFetchJson("api/characters/updateCharacter.php", {
                                    method: "POST",
                                    body: {
                                        id: character.id,
                                        idUser: newIdUser
                                    }
                                });
                                getCharacter(character.id, null, getActiveNavTabName());
                            } catch (err) {
                                console.error("Fout bij koppelen/loskoppelen deelnemer:", err);
                            }
                        },
                        character.idUser
                    );
                })
                .catch((err) => {
                    console.error("Fout bij ophalen userlist:", err);
                });
        }

        activateSelectOption("type", character.type);
        activateSelectOption("state", character.state);

        const selType = document.getElementById("type");
        const selState = document.getElementById("state");

        if (selType) {
            selType.addEventListener("change", async (e) => {
                const idCharacter = document.getElementById("idCharacter").value;
                if (!idCharacter) return;

                const newType = e.target.value;
                try {
                    await apiFetchJson("api/characters/updateCharacter.php", {
                        method: "POST",
                        body: {
                            id: idCharacter,
                            type: newType
                        }
                    });
                    await getCharacter(idCharacter, null, getActiveNavTabName());
                } catch (err) {
                    console.error("Fout bij opslaan type:", err);
                }
            });
        }

        if (selState) {
            selState.addEventListener("change", async (e) => {
                const idCharacter = document.getElementById("idCharacter").value;
                if (!idCharacter) return;

                const newState = e.target.value;
                try {
                    await apiFetchJson("api/characters/updateCharacter.php", {
                        method: "POST",
                        body: {
                            id: idCharacter,
                            state: newState
                        }
                    });
                    await getCharacter(idCharacter, null, getActiveNavTabName());
                } catch (err) {
                    console.error("Fout bij opslaan status:", err);
                }
            });
        }

        getUserList();
    } else {
        renderCharacterNav(false);
        document.getElementById("nameParticipant").innerHTML = character.nameParticipant;
        document.getElementById("type").innerHTML = character.type;
        document.getElementById("state").innerHTML = character.state;
    }

    const lblExp = document.getElementById("lblExperiance");
    if (lblExp) {
        if (character.type === "player") {
            lblExp.innerHTML = `${spentExperience} / ${maxExperience}`;
        } else {
            lblExp.innerHTML = `${usedExperience} (${expertise})`;
        }
    }

    const lblStatus = document.getElementById("lblStatusPoints");
    if (lblStatus) {
        if (character.type === "player") {
            if (character.state === "draft") {
                lblStatus.textContent = `${usedStatusPoints} / ${maxStatusPoints}`;
            } else {
                lblStatus.textContent = `${usedStatusPoints} (${statusLabel})`;
            }
        } else {
            lblStatus.textContent = `${usedStatusPoints} (${statusLabel})`;
        }
    }

    const statusPointInfo = document.getElementById("statusPointInfo");
    if (statusPointInfo) {
        if (canConvertToStatus) {
            const tooltipText = `Omgezet uit EP: ${convertedExperience} / 6`;
            statusPointInfo.setAttribute("title", tooltipText);
            statusPointInfo.setAttribute("data-bs-original-title", tooltipText);
            statusPointInfo.classList.remove("d-none");
        } else {
            statusPointInfo.removeAttribute("title");
            statusPointInfo.removeAttribute("data-bs-original-title");
            statusPointInfo.classList.add("d-none");
        }
    }

    const statusPointControls = document.getElementById("statusPointControls");
    const btnStatusPointMinus = document.getElementById("btnStatusPointMinus");
    const btnStatusPointPlus = document.getElementById("btnStatusPointPlus");

    if (statusPointControls && btnStatusPointMinus && btnStatusPointPlus) {
        statusPointControls.classList.toggle("d-none", !canConvertToStatus);

        if (canConvertToStatus) {
            btnStatusPointMinus.disabled = convertedExperience <= 0;
            btnStatusPointPlus.disabled = convertedExperience >= 6 || remainingExperience <= 0;
            btnStatusPointMinus.onclick = () => updateExperienceToTrait(character, -1);
            btnStatusPointPlus.onclick = () => updateExperienceToTrait(character, 1);
        } else {
            btnStatusPointMinus.onclick = null;
            btnStatusPointPlus.onclick = null;
        }
    }

    if (typeof initTooltips === "function") {
        initTooltips();
    }

    setupNavTabHandlers(character);
    setActiveNavTab(activeTab);
}

function setActiveNavTab(tabName) {
    const tabs = document.querySelectorAll("#navSheet .navTab");
    tabs.forEach(tab => tab.classList.remove("active"));

    let matched = false;
    tabs.forEach(tab => {
        const t = tab.dataset.tab || "sheet";
        if (t === tabName || (!tab.dataset.tab && tabName === "sheet")) {
            tab.classList.add("active");
            matched = true;
        }
    });

    if (!matched && tabs.length > 0) {
        tabs[0].classList.add("active");
    }
}

function setupNavTabHandlers(character) {
    const tabs = document.querySelectorAll("#navSheet .navTab");
    tabs.forEach(tab => {
        tab.addEventListener("click", (e) => {
            e.preventDefault();
            const targetTab = tab.dataset.tab || "sheet";
            setActiveNavTab(targetTab);
            closeOffcanvasIfOpen();
            showCharacterTab(targetTab, character);
        });
    });
}

function closeOffcanvasIfOpen() {
    const offcanvasEl = document.getElementById("offcanvasScrolling");
    if (!offcanvasEl) return;
    const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasEl);
    if (offcanvasInstance) {
        offcanvasInstance.hide();
    }
}
