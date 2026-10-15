// Eventpagina initialiseren na load
window.addEventListener("load", () => {
    initEventPage();
});

async function initEventPage() {
    try {
        // Huidige gebruiker
        const currentUser = await aetherGetCurrentUser();
        if (!currentUser) {
            console.error("Kon huidige gebruiker niet ophalen.");
            return;
        }

        window.AETHER_CURRENT_USER = currentUser;

        const isAdmin =
            currentUser.role === "administrator" ||
            currentUser.role === "director";

        if (isAdmin) {
            // admin-extra's (nieuw event, inline edit, …)
            setupEventDetailUI();
        }

        const params = new URLSearchParams(window.location.search);
        const requestedEvent = Number(params.get("event"));
        const eventId = Number.isSafeInteger(requestedEvent) && requestedEvent > 0 ? requestedEvent : 0;
        const requestedParticipant = Number(params.get("participant"));
        const participantId = isAdmin && Number.isSafeInteger(requestedParticipant) && requestedParticipant > 0 ? requestedParticipant : 0;
        const request = eventDetailsState.request;
        showEventPageView(Boolean(eventId));
        await Promise.all([
            refreshEventSidebar(),
            isAdmin ? initParticipantFilter(eventId ? 0 : participantId) : Promise.resolve(),
            eventId ? openEventDetails(eventId) : Promise.resolve()
        ]);
        if (!eventId && request === eventDetailsState.request) await generateEventList(participantId);

    } catch (err) {
        console.error("Fout bij initialiseren eventpagina:", err);
    }
}


/**
 * Haal events op en vul de tabel #eventList
 * @param {number} idParticipant - 0 = huidige gebruiker, anders specifieke userId
 */
async function generateEventList(idParticipant = 0) {
    const request = ++eventDetailsState.participationRequest;
    try {
        const dataObject = { idUser: idParticipant };

        const data = await apiFetchJson("api/events/getEventList.php", {
            method: "POST",
            body: dataObject
        });
        if (request !== eventDetailsState.participationRequest) return;
        const select = document.getElementById("eventParticipantSelect");
        document.getElementById("titleParticipant").textContent = idParticipant
            ? `Deelnames — ${select?.selectedOptions[0]?.textContent || "Deelnemer"}` : "Mijn deelnames";

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
                divType.textContent = "Aether " + aetherNumber;
            } else {
                // bv. "mini"
                divType.textContent = "Aether " + value.type;
            }
            newTableRow.appendChild(divType);

            // Titel
            const divTitle = document.createElement("td");
            divTitle.textContent = value.title;
            newTableRow.appendChild(divTitle);

            // Beschrijving
            const divDescription = document.createElement("td");
            divDescription.classList.add("col-4");
            divDescription.textContent = value.description;
            newTableRow.appendChild(divDescription);

            // Datum (start / eind)
            const divDate = document.createElement("td");
            divDate.classList.add("text-nowrap");
            if (value.dateStart && value.dateEnd) {
                divDate.textContent = `${value.dateStart} – ${value.dateEnd}`;
            } else if (value.dateStart) {
                divDate.textContent = value.dateStart;
            } else {
                divDate.textContent = "";
            }
            newTableRow.appendChild(divDate);

            // Locatie
            const divVenue = document.createElement("td");
            divVenue.classList.add("text-nowrap");
            divVenue.textContent = value.venue ?? "";
            newTableRow.appendChild(divVenue);

            // EP
            const divExperience = document.createElement("td");
            divExperience.classList.add("text-center");
            divExperience.textContent = value.ep ?? "";
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
                    const previous = !e.target.checked;
                    e.target.disabled = true;
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
                        e.target.checked = previous;
                        eventDetailError(err);
                    } finally {
                        e.target.disabled = false;
                    }
                });

                divParticipation.appendChild(checkParticipation);
            } else {
                // Gewone speler → tekst
                if (value.participation === true) {
                    divParticipation.textContent = "Yes";
                } else {
                    divParticipation.textContent = "";
                }
            }

            newTableRow.appendChild(divParticipation);


            eventList.appendChild(newTableRow);
        }
    } catch (err) {
        console.error("Fout bij ophalen eventlijst:", err);
    }
}


/**
 * Haal userlist op en maak de DropdownInput voor deelnemers-filter
 */
async function initParticipantFilter(selectedId = 0) {
    await window.setupEventParticipantFilter(chosenParticipant, selectedId, window.AETHER_CURRENT_USER);
}

/**
 * Callback voor DropdownInput: toon events van geselecteerde participant
 */
function chosenParticipant(id) {
    if (!mayLeaveEventDetails()) return;
    eventDetailsState.editing = false;
    eventDetailsState.selectedId = 0;
    ++eventDetailsState.request;
    showEventPageView(false);
    window.renderEventSidebarCards(eventDetailsState.events, document.getElementById("eventSidebarList"), event => { void openEventDetails(event.id); });
    window.closeEventSidebar?.();
    const numericId = parseInt(id, 10) || 0;
    generateEventList(numericId);
}
