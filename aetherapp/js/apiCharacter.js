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

async function fetchCharacterList(role) {
    return apiFetchJson("api/characters/getCharacterList.php", {
        method: "POST",
        body: { role }
    });
}

async function updateCharacter(payload) {
    return apiFetchJson("api/characters/updateCharacter.php", {
        method: "POST",
        body: payload
    });
}
