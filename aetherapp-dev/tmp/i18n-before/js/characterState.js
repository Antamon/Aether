// Globale state voor personage-pagina
let currentUser = null;
let currentCharacter = null;
let originalCharacterFormHtml = null;
let originalSkillsHtml = null;

function setCurrentUser(user) {
    currentUser = user;
    window.AETHER_CURRENT_USER = user;
}

function getCurrentUser() {
    return currentUser;
}

function setCurrentCharacter(character) {
    currentCharacter = character;
}

function getCurrentCharacter() {
    return currentCharacter;
}

function setOriginalCharacterFormHtml(html) {
    originalCharacterFormHtml = html;
}

function getOriginalCharacterFormHtml() {
    return originalCharacterFormHtml;
}

function setOriginalSkillsHtml(html) {
    originalSkillsHtml = html;
}

function getOriginalSkillsHtml() {
    return originalSkillsHtml;
}
