// Utility-functies voor character berekeningen

const CHARACTER_BASE_HEALTH = 4;
const CHARACTER_HEALTH_STEP_COST = 3;

function getCharacterIntValue(character, field) {
    const value = Number(character?.[field] ?? 0);
    return Number.isFinite(value) ? value : 0;
}

function getPhysicalHealthValue(character) {
    return CHARACTER_BASE_HEALTH
        + getCharacterIntValue(character, "physicalHealth")
        + getCharacterIntValue(character, "physicalHealthFree");
}

function getMentalHealthValue(character) {
    return CHARACTER_BASE_HEALTH
        + getCharacterIntValue(character, "mentalHealth")
        + getCharacterIntValue(character, "mentalHealthFree");
}

function getHealthExperienceCost(character) {
    return (
        getCharacterIntValue(character, "physicalHealth")
        + getCharacterIntValue(character, "mentalHealth")
    ) * CHARACTER_HEALTH_STEP_COST;
}

function calculateExperience(character) {
    let experience = 0;

    (character.skills || []).forEach(skill => {
        // levels
        if (skill.level == 1) experience += 1;
        else if (skill.level == 2) experience += 3;
        else if (skill.level == 3) experience += 6;

        // specialisaties: 2 XP per stuk, MAAR disciplines zijn gratis
        if (Array.isArray(skill.specialisations)) {
            const nonDisciplineSpecs = skill.specialisations.filter(
                spec => spec.kind !== "discipline"
            );
            experience += nonDisciplineSpecs.length * 2;
        }
    });

    return experience;
}

function getMaxExperience(character) {
    if (!character || character.type !== "player") return 0;

    if (typeof character.maxExperience === "number") {
        return character.maxExperience;
    }

    if (typeof character.experience === "number") {
        return character.experience;
    }

    return (!character.idUser || Number(character.idUser) === 0) ? 15 : 15;
}

function getExperienceBudget(character) {
    if (!character || character.type !== "player") return 0;

    if (typeof character.experience === "number") {
        return character.experience;
    }

    const convertedExperience = Math.max(0, Math.min(6, getCharacterIntValue(character, "experienceToTrait")));
    return Math.max(0, getMaxExperience(character) - convertedExperience - getHealthExperienceCost(character));
}

function getRemainingExperience(character) {
    if (!character || character.type !== "player") return 0;
    return Math.max(0, getExperienceBudget(character) - calculateExperience(character));
}

function getMaxStatusPoints(character) {
    if (!character || character.type !== "player") return 0;

    if (typeof character.maxStatusPoints === "number") {
        return character.maxStatusPoints;
    }

    const convertedExperience = Number(character.experienceToTrait) || 0;
    return 8 + (convertedExperience * 2);
}

function getUsedStatusPoints(character) {
    if (!character || character.type !== "player") return 0;
    return Number(character.usedStatusPoints) || 0;
}

function getAvailableStatusPoints(character) {
    if (!character || character.type !== "player") return 0;

    if (typeof character.availableStatusPoints === "number") {
        return character.availableStatusPoints;
    }

    return Math.max(0, getMaxStatusPoints(character) - getUsedStatusPoints(character));
}

function expertiseExtra(experience) {
    if (experience <= 20) return "Average person";
    else if (experience <= 30) return "Professional";
    else if (experience <= 40) return "Master";
    else return "Super human";
}
