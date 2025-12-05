// Navigation/header rendering for the character sheet

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

function pageNav(userRole, character) {
    const pageNavContainer = document.getElementById("pageNav");
    const usedExperience = calculateExperience(character);
    const expertise = expertiseExtra(usedExperience);

    const isAdmin = userRole === "administrator" || userRole === "director";

    if (isAdmin) {
        // Admin-template
        renderCharacterNav(true);

        // Deelnemer-koppeling (DropdownInput)
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

                            // null vanuit reset-knop = loskoppelen
                            if (selectedId === null || selectedId === undefined || selectedId === "") {
                                newIdUser = 0;
                            } else {
                                newIdUser = parseInt(selectedId, 10);
                            }

                            if (character.idUser == newIdUser) {
                                return;
                            }

                            const payload = {
                                id: character.id,
                                idUser: newIdUser
                            };

                            try {
                                await apiFetchJson("api/characters/updateCharacter.php", {
                                    method: "POST",
                                    body: payload
                                });
                                character.idUser = newIdUser;
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

        activateSelectOption("type", character["type"]);
        activateSelectOption("state", character["state"]);

        // Autosave voor type & state
        const selType = document.getElementById("type");
        const selState = document.getElementById("state");

        if (selType) {
            selType.addEventListener("change", async (e) => {
                const idCharacter = document.getElementById("idCharacter").value;
                if (!idCharacter) return;

                const newType = e.target.value;
                const payload = {
                    id: idCharacter,
                    type: newType
                };
                try {
                    await apiFetchJson("api/characters/updateCharacter.php", {
                        method: "POST",
                        body: payload
                    });

                    // Frontend state mee aanpassen
                    if (currentCharacter) {
                        currentCharacter.type = newType;
                        pageNav(currentUser.role, currentCharacter);
                    }

                } catch (err) {
                    console.error("Fout bij opslaan type:", err);
                }
            });
        }

        if (selState) {
            selState.addEventListener("change", async (e) => {
                const idCharacter = document.getElementById("idCharacter").value;
                if (!idCharacter) return;

                const payload = {
                    id: idCharacter,
                    state: e.target.value
                };
                try {
                    await apiFetchJson("api/characters/updateCharacter.php", {
                        method: "POST",
                        body: payload
                    });
                } catch (err) {
                    console.error("Fout bij opslaan status:", err);
                }
            });
        }

        getUserList();
    } else {
        // Participant-template
        renderCharacterNav(false);
        document.getElementById("nameParticipant").innerHTML = character.nameParticipant;
        document.getElementById("type").innerHTML = character.type;
        document.getElementById("state").innerHTML = character.state;
    }

    // Experience-label
    const lblExp = document.getElementById("lblExperiance");
    if (lblExp) {
        if (character.type === "player") {
            // max EP bepalen
            let maxExperience;

            if (typeof character.experience === "number") {
                // normale situatie: backend stuurde experience/maxExperience mee
                maxExperience = character.experience;
            } else if (typeof character.maxExperience === "number") {
                maxExperience = character.maxExperience;
            } else if (!character.idUser || character.idUser === 0) {
                // geen deelnemer gekoppeld ⇒ standaard 15 EP
                maxExperience = 15;
            } else {
                // safety fallback
                maxExperience = 15;
            }

            lblExp.innerHTML = `${usedExperience} / ${maxExperience}`;
        } else {
            // figurant / NPC
            lblExp.innerHTML = `${usedExperience} (${expertise})`;
        }
    }

    setupNavTabHandlers(character);
    setActiveNavTab("sheet");
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

    // Fallback: als niets matcht, activeer de eerste tab
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
            if (targetTab === "background") {
                showBackgroundTab(currentCharacter || character);
            } else {
                // default terug naar sheet
                showSheetTab();
            }
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
