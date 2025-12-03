// Eventpagina initialiseren na load
window.addEventListener("load", () => {
    initEventPage();
});

async function initEventPage() {
    try {
        // Huidige gebruiker
        const currentUser = await apiFetchJson("getCurrentUser.php");
        if (!currentUser) {
            console.error("Kon huidige gebruiker niet ophalen.");
            return;
        }

        window.AETHER_CURRENT_USER = currentUser;

        // Eventlijst voor "ikzelf"
        await generateEventList(0);

        const isAdmin =
            currentUser.role === "administrator" ||
            currentUser.role === "director";

        if (isAdmin) {
            // admin-extra's (nieuw event, inline edit, …)
            setupAdminEventUI();

            // container van de deelnemers-dropdown zichtbaar maken
            const filterWrapper = document.getElementById("participantFilterWrapper");
            if (filterWrapper) {
                filterWrapper.classList.remove("d-none");
            }

            // dropdown zelf opbouwen
            await initParticipantFilter();
        }

        // gewone spelers krijgen geen deelnemers-filter

    } catch (err) {
        console.error("Fout bij initialiseren eventpagina:", err);
    }
}


/**
 * Haal events op en vul de tabel #eventList
 * @param {number} idParticipant - 0 = huidige gebruiker, anders specifieke userId
 */
async function generateEventList(idParticipant = 0) {
    try {
        const dataObject = { idUser: idParticipant };

        const data = await apiFetchJson("api/events/getEventList.php", {
            method: "POST",
            body: dataObject
        });

        const eventList = document.getElementById("eventList");
        if (!eventList) {
            console.warn("Element #eventList niet gevonden.");
            return;
        }

        eventList.innerHTML = "";

        if (!data) {
            return;
        }

        const currentUser = window.AETHER_CURRENT_USER || {};
        const isAdmin = currentUser.role === "administrator" || currentUser.role === "director";

        let aetherNumber = 0; // voor weekend-events

        for (const value of Object.values(data)) {
            const newTableRow = document.createElement("tr");
            newTableRow.id = "row_" + value.id;

            // Type (weekend / mini) → weergegeven als "Aether X"
            const divType = document.createElement("td");
            divType.classList.add("text-nowrap");

            if (value.type === "weekend") {
                aetherNumber++;
                divType.innerHTML = "Aether " + aetherNumber;
            } else {
                // bv. "mini"
                divType.innerHTML = "Aether " + value.type;
            }
            newTableRow.appendChild(divType);

            // Titel
            const divTitle = document.createElement("td");
            divTitle.innerHTML = value.title;
            newTableRow.appendChild(divTitle);

            // Beschrijving
            const divDescription = document.createElement("td");
            divDescription.classList.add("col-4");
            divDescription.innerHTML = value.description;
            newTableRow.appendChild(divDescription);

            // Datum (start / eind)
            const divDate = document.createElement("td");
            divDate.classList.add("text-nowrap");
            if (value.dateStart && value.dateEnd) {
                divDate.innerHTML = `${value.dateStart} – ${value.dateEnd}`;
            } else if (value.dateStart) {
                divDate.innerHTML = value.dateStart;
            } else {
                divDate.innerHTML = "";
            }
            newTableRow.appendChild(divDate);

            // Locatie
            const divVenue = document.createElement("td");
            divVenue.classList.add("text-nowrap");
            divVenue.innerHTML = value.venue ?? "";
            newTableRow.appendChild(divVenue);

            // EP
            const divExperience = document.createElement("td");
            divExperience.classList.add("text-center");
            divExperience.innerHTML = value.ep ?? "";
            newTableRow.appendChild(divExperience);

            // Deelname
            const divParticipation = document.createElement("td");
            divParticipation.classList.add("text-center");

            if (isAdmin) {
                // Admin/Director → checkbox
                const checkParticipation = document.createElement("input");
                checkParticipation.type = "checkbox";
                checkParticipation.id = "checkParticipation_" + value.id;
                checkParticipation.classList.add("form-check-input");

                if (value.participation === true) {
                    checkParticipation.checked = true;
                }

                checkParticipation.addEventListener("change", async (e) => {
                    try {
                        const userId =
                            idParticipant && idParticipant !== 0
                                ? idParticipant
                                : (window.AETHER_CURRENT_USER && window.AETHER_CURRENT_USER.id) || 0;

                        const dataObject = {
                            idUser: userId,
                            idEvent: value.id,
                            participation: e.target.checked
                        };

                        await apiFetchJson("api/events/updateParticipation.php", {
                            method: "POST",
                            body: dataObject
                        });
                    } catch (err) {
                        console.error("Fout bij updaten deelname:", err);
                    }
                });

                divParticipation.appendChild(checkParticipation);
            } else {
                // Gewone speler → tekst
                if (value.participation === true) {
                    divParticipation.innerHTML = "Yes";
                } else {
                    divParticipation.innerHTML = "";
                }
            }

            newTableRow.appendChild(divParticipation);

            // Admin/Director: extra kolom met edit-icoon
            if (isAdmin) {
                const editEventCell = document.createElement("td");
                const editIcon = document.createElement("i");
                editIcon.classList.add("fa-solid", "fa-pen", "cursor-pointer");
                editIcon.addEventListener("click", () => {
                    makeEventRowEditable(value, idParticipant);
                });
                editEventCell.appendChild(editIcon);
                newTableRow.appendChild(editEventCell);
            }

            eventList.appendChild(newTableRow);
        }
    } catch (err) {
        console.error("Fout bij ophalen eventlijst:", err);
    }
}

/**
 * Maak een rij in de eventtabel bewerkbaar (inline editing voor admin/director).
 */
function makeEventRowEditable(eventValue, idParticipant = 0) {
    const row = document.getElementById("row_" + eventValue.id);
    if (!row) return;

    row.innerHTML = "";

    // Type
    const divType = document.createElement("td");
    const selectType = document.createElement("select");
    selectType.classList.add("form-select");
    selectType.id = "inputType_" + eventValue.id;

    const optionWeekend = document.createElement("option");
    optionWeekend.value = "weekend";
    optionWeekend.innerHTML = "weekend";
    if (eventValue.type === "weekend") optionWeekend.selected = true;
    selectType.appendChild(optionWeekend);

    const optionMini = document.createElement("option");
    optionMini.value = "mini";
    optionMini.innerHTML = "mini";
    if (eventValue.type === "mini") optionMini.selected = true;
    selectType.appendChild(optionMini);

    divType.appendChild(selectType);
    row.appendChild(divType);

    // Titel
    const divTitle = document.createElement("td");
    const inputTitle = document.createElement("input");
    inputTitle.type = "text";
    inputTitle.id = "inputTitle_" + eventValue.id;
    inputTitle.value = eventValue.title;
    divTitle.appendChild(inputTitle);
    row.appendChild(divTitle);

    // Beschrijving
    const divDescription = document.createElement("td");
    divDescription.classList.add("col-4");
    const inputDescription = document.createElement("input");
    inputDescription.type = "text";
    inputDescription.id = "inputDescription_" + eventValue.id;
    inputDescription.value = eventValue.description;
    divDescription.appendChild(inputDescription);
    row.appendChild(divDescription);

    // Datum start / eind
    const divDate = document.createElement("td");
    const inputDateStart = document.createElement("input");
    inputDateStart.type = "date";
    inputDateStart.id = "inputDateStart_" + eventValue.id;
    inputDateStart.value = eventValue.dateStart || "";
    divDate.appendChild(inputDateStart);

    const inputDateEnd = document.createElement("input");
    inputDateEnd.type = "date";
    inputDateEnd.id = "inputDateEnd_" + eventValue.id;
    inputDateEnd.value = eventValue.dateEnd || "";
    divDate.appendChild(inputDateEnd);
    row.appendChild(divDate);

    // Venue
    const divVenue = document.createElement("td");
    const inputVenue = document.createElement("input");
    inputVenue.type = "text";
    inputVenue.id = "inputVenue_" + eventValue.id;
    inputVenue.value = eventValue.venue || "";
    divVenue.appendChild(inputVenue);
    row.appendChild(divVenue);

    // EP
    const divExperience = document.createElement("td");
    const inputExperience = document.createElement("input");
    inputExperience.type = "text";
    inputExperience.id = "inputExperience_" + eventValue.id;
    inputExperience.value = eventValue.ep || "";
    divExperience.appendChild(inputExperience);
    row.appendChild(divExperience);

    // Submit icon
    const divSubmit = document.createElement("td");
    divSubmit.classList.add("text-center");
    const submitIcon = document.createElement("i");
    submitIcon.classList.add("fa-solid", "fa-check", "cursor-pointer");
    submitIcon.addEventListener("click", async () => {
        try {
            const dataObject = {
                type: document.getElementById("inputType_" + eventValue.id).value,
                title: document.getElementById("inputTitle_" + eventValue.id).value,
                description: document.getElementById("inputDescription_" + eventValue.id).value,
                dateStart: document.getElementById("inputDateStart_" + eventValue.id).value,
                dateEnd: document.getElementById("inputDateEnd_" + eventValue.id).value,
                venue: document.getElementById("inputVenue_" + eventValue.id).value,
                ep: document.getElementById("inputExperience_" + eventValue.id).value,
                id: eventValue.id
            };

            await apiFetchJson("api/events/updateEvent.php", {
                method: "POST",
                body: dataObject
            });

            // Na opslaan: lijst opnieuw opbouwen
            await generateEventList(idParticipant);
        } catch (err) {
            console.error("Fout bij updaten event:", err);
        }
    });
    divSubmit.appendChild(submitIcon);
    row.appendChild(divSubmit);
}

/**
 * Admin / Director: nieuwe event-UI + knoppen initialiseren
 */
function setupAdminEventUI() {
    const btnGroup = document.getElementById("eventNewButtonGroup");
    const eventNew = document.getElementById("eventNew");
    const btnNew = document.getElementById("eventNewSubmit");
    const btnCancel = document.getElementById("eventNewCancel");

    const toggleBtnGroup = document.getElementById("eventNewButtonGroup");

    if (toggleBtnGroup) {
        toggleBtnGroup.classList.remove("d-none");
    }

    if (btnGroup && eventNew) {
        btnGroup.addEventListener("click", () => {
            btnGroup.classList.add("d-none");
            eventNew.classList.remove("d-none");
        });
    }

    if (btnCancel && btnGroup && eventNew) {
        btnCancel.addEventListener("click", () => {
            btnGroup.classList.remove("d-none");
            eventNew.classList.add("d-none");
            clearNewEventFields();
        });
    }

    if (btnNew && btnGroup && eventNew) {
        btnNew.addEventListener("click", async () => {
            const dataObject = {
                type: document.getElementById("newType").value,
                title: document.getElementById("newTitle").value,
                description: document.getElementById("newDescription").value,
                dateStart: document.getElementById("newDateStart").value,
                dateEnd: document.getElementById("newDateEnd").value,
                venue: document.getElementById("newVenue").value,
                ep: document.getElementById("newExperience").value
            };

            try {
                await apiFetchJson("api/events/newEvent.php", {
                    method: "POST",
                    body: dataObject
                });

                await generateEventList(0);
                btnGroup.classList.remove("d-none");
                eventNew.classList.add("d-none");
                clearNewEventFields();
            } catch (err) {
                console.error("Fout bij aanmaken nieuw event:", err);
            }
        });
    }
}

function clearNewEventFields() {
    const fields = [
        "newType",
        "newTitle",
        "newDescription",
        "newDateStart",
        "newDateEnd",
        "newVenue",
        "newExperience"
    ];

    fields.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
}

/**
 * Haal userlist op en maak de DropdownInput voor deelnemers-filter
 */
async function initParticipantFilter() {
    try {
        const data = await apiFetchJson("api/users/getUserList.php");
        if (!data) return;

        const userList = [];
        for (const value of Object.values(data)) {
            userList.push({
                id: value.id,
                text: `${value.firstName} ${value.lastName}`
            });
        }

        // DropdownInput komt uit mainFunctions.js
        new DropdownInput("listParticipant", userList, chosenParticipant);
    } catch (err) {
        console.error("Fout bij ophalen userlist:", err);
    }
}

/**
 * Callback voor DropdownInput: toon events van geselecteerde participant
 */
function chosenParticipant(id) {
    const numericId = parseInt(id, 10) || 0;
    generateEventList(numericId);
}
