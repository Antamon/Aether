// API helpers voor character-gerelateerde calls

async function fetchCurrentUser() {
    return apiFetchJson("getCurrentUser.php", { method: "GET" });
}

async function fetchCharacter(id) {
    return apiFetchJson("api/characters/getCharacter.php", {
        method: "POST",
        body: { id }
    });
}

async function fetchCharacterList() {
    return apiFetchJson("api/characters/getCharacterList.php", {
        method: "POST",
        body: {}
    });
}

async function deleteCharacterById(id) {
    return apiFetchJson("api/characters/deleteCharacter.php", {
        method: "POST",
        body: { id }
    });
}

async function updateCharacter(payload) {
    const fetcher = Object.prototype.hasOwnProperty.call(payload, "bankaccount")
        || Object.prototype.hasOwnProperty.call(payload, "securitiesaccount")
        ? apiFetchFinancialJson
        : apiFetchJson;
    return fetcher("api/characters/updateCharacter.php", {
        method: "POST",
        body: payload
    });
}
