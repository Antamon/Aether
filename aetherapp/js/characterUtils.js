// Utility-functies voor character berekeningen

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

function expertiseExtra(experience) {
    if (experience <= 20) return "Average person";
    else if (experience <= 30) return "Professional";
    else if (experience <= 40) return "Master";
    else return "Super human";
}
