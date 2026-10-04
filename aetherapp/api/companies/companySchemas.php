<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/validation.php';

function aetherCompanyDetailSchema(): array
{
    return ['id' => ['type' => 'int', 'required' => true, 'min' => 1]];
}

function aetherCompanyCreateSchema(): array
{
    return ['companyName' => ['type' => 'string', 'required' => true, 'trim' => true, 'minLength' => 1, 'maxLength' => 255]];
}

/** The UI sends previously read presentation fields back with its editable personnel state.
 * They are accepted for compatibility, validated, and never used for authority or writes.
 */
function aetherCompanyPersonnelEntrySchema(): array
{
    return [
        'idCompanyPersonnel' => ['type' => 'int', 'min' => 0],
        'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 0],
        'displayName' => ['type' => 'string', 'maxLength' => 4096],
        'nameLabel' => ['type' => 'string', 'maxLength' => 4096],
        'professionLabel' => ['type' => 'string', 'maxLength' => 4096],
        'importance' => ['type' => 'enum', 'values' => ['Negligible', 'Low', 'Moderate', 'High', 'Critical'], 'default' => 'Moderate'],
        'salaryIncreasePercentage' => ['type' => 'decimal', 'scale' => 2, 'min' => '0.00', 'max' => '999999.99', 'default' => '0.00'],
    ];
}

function aetherCompanyPersonnelSkillSchema(): array
{
    return [
        'idCompanyPersonnelSkill' => ['type' => 'int', 'min' => 0],
        'idSkill' => ['type' => 'int', 'required' => true, 'min' => 0],
        'skillName' => ['type' => 'string', 'maxLength' => 255],
        'level' => ['type' => 'int', 'min' => 1, 'max' => 3, 'default' => 1],
    ];
}

function aetherCompanyPersonnelSpecialisationSchema(): array
{
    return [
        'idSkillSpecialisation' => ['type' => 'int', 'min' => 0, 'default' => 0],
        'name' => ['type' => 'string', 'trim' => true, 'maxLength' => 100, 'default' => ''],
        'kind' => ['type' => 'enum', 'values' => ['discipline', 'specialisation']],
    ];
}

/** @return array{idCompany:int,personnel:list<array<string,mixed>>} */
function aetherValidateCompanyPersonnelRequest(array $input): array
{
    $extra = array_diff(array_keys($input), ['idCompany', 'personnel']);
    if ($extra !== []) {
        throw new AetherValidationException(array_map(
            static fn(string $field): array => ['field' => $field, 'code' => 'unknown_field', 'message' => "Onverwacht veld: {$field}."],
            array_values($extra)
        ));
    }
    $header = aetherValidateInput($input === [] ? [] : array_intersect_key($input, ['idCompany' => true]), [
        'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
    ]);
    if (!isset($input['personnel']) || !is_array($input['personnel']) || !array_is_list($input['personnel'])) {
        throw new AetherValidationException([['field' => 'personnel', 'code' => 'invalid_type', 'message' => 'Veld personnel moet een lijst zijn.']]);
    }
    $entries = [];
    foreach ($input['personnel'] as $i => $entry) {
        if (!is_array($entry) || array_is_list($entry) && $entry !== []) {
            throw new AetherValidationException(["Personeelsrij {$i} moet een object zijn."]);
        }
        $skills = array_key_exists('skills', $entry) ? $entry['skills'] : [];
        if (!is_array($skills) || !array_is_list($skills)) {
            throw new AetherValidationException(["Vaardigheden van personeelsrij {$i} moeten een lijst zijn."]);
        }
        $person = aetherValidateInput(array_diff_key($entry, ['skills' => true]), aetherCompanyPersonnelEntrySchema());
        $person['skills'] = [];
        foreach ($skills as $j => $skillEntry) {
            if (!is_array($skillEntry) || array_is_list($skillEntry) && $skillEntry !== []) {
                throw new AetherValidationException(["Vaardigheid {$j} van personeelsrij {$i} moet een object zijn."]);
            }
            $specialisations = array_key_exists('specialisations', $skillEntry) ? $skillEntry['specialisations'] : [];
            if (!is_array($specialisations) || !array_is_list($specialisations)) {
                throw new AetherValidationException(["Specialisaties van vaardigheid {$j} moeten een lijst zijn."]);
            }
            $skill = aetherValidateInput(array_diff_key($skillEntry, ['specialisations' => true]), aetherCompanyPersonnelSkillSchema());
            $skill['specialisations'] = [];
            foreach ($specialisations as $k => $specialisation) {
                if (!is_array($specialisation) || array_is_list($specialisation) && $specialisation !== []) {
                    throw new AetherValidationException(["Specialisatie {$k} moet een object zijn."]);
                }
                $validated = aetherValidateInput($specialisation, aetherCompanyPersonnelSpecialisationSchema());
                if ($validated['idSkillSpecialisation'] === 0 && $validated['name'] === '') {
                    throw new AetherValidationException(['Een nieuwe specialisatie moet een naam hebben.']);
                }
                $skill['specialisations'][] = $validated;
            }
            $person['skills'][] = $skill;
        }
        $entries[] = $person;
    }
    return ['idCompany' => $header['idCompany'], 'personnel' => $entries];
}
