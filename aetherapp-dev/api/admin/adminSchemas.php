<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/validation.php';

/** Field contracts used by the active skill editor. All text is plain text. */
function aetherAdminSchema(string $route): array
{
    return match ($route) {
        'getSkillList' => [],
        'getSkill' => ['idSkill' => ['type' => 'int', 'required' => true, 'min' => 1]],
        'newSkill' => ['name' => ['type' => 'string', 'required' => true, 'trim' => true, 'minLength' => 1, 'maxLength' => 30]],
        'saveSkill' => [
            'idSkill' => ['type' => 'int', 'required' => true, 'min' => 1],
            'name' => ['type' => 'string', 'required' => true, 'trim' => true, 'minLength' => 1, 'maxLength' => 30],
            'description' => ['type' => 'string', 'required' => true, 'trim' => true, 'maxLength' => 1200],
            'beginner' => ['type' => 'string', 'required' => true, 'trim' => true, 'maxLength' => 1200],
            'professional' => ['type' => 'string', 'required' => true, 'trim' => true, 'maxLength' => 1200],
            'master' => ['type' => 'string', 'required' => true, 'trim' => true, 'maxLength' => 1200],
            'isSecret' => ['type' => 'bool', 'required' => true],
            'categoryIds' => ['type' => 'admin_category_ids', 'required' => true],
            'specialisations' => ['type' => 'admin_specialisations', 'required' => true],
        ],
        'saveSkillType' => [
            'action' => ['type' => 'enum', 'required' => true, 'values' => ['create', 'update', 'delete']],
            'idSkillType' => ['type' => 'int', 'required' => true, 'min' => 0],
            'name' => ['type' => 'string', 'required' => true, 'trim' => true, 'maxLength' => 50],
        ],
        default => throw new LogicException('Unknown admin schema'),
    };
}

function aetherValidateAdminRequest(string $route, array $input): array
{
    $schema = aetherAdminSchema($route);
    $arrays = [];
    foreach (['categoryIds', 'specialisations'] as $field) {
        if (isset($schema[$field])) {
            $arrays[$field] = $input[$field] ?? null;
            unset($schema[$field], $input[$field]);
        }
    }
    $validated = aetherValidateInput($input, $schema);
    if ($route === 'saveSkill') {
        $errors = [];
        $categories = $arrays['categoryIds'];
        $specialisations = $arrays['specialisations'];
        if (!is_array($categories) || !array_is_list($categories)) {
            $errors[] = 'Veld categoryIds moet een lijst zijn.';
        } else {
            foreach ($categories as $id) {
                if (!is_int($id) || $id < 1) $errors[] = 'categoryIds bevat een ongeldig ID.';
            }
        }
        if (!is_array($specialisations) || !array_is_list($specialisations)) {
            $errors[] = 'Veld specialisations moet een lijst zijn.';
        } else {
            foreach ($specialisations as $item) {
                if (!is_array($item) || count($item) !== 2 || !array_key_exists('idSkillSpecialisation', $item) || !array_key_exists('name', $item)) {
                    $errors[] = 'Een specialisatie bevat ontbrekende of onverwachte velden.';
                    continue;
                }
                try {
                    aetherValidateInput($item, [
                        'idSkillSpecialisation' => ['type' => 'int', 'required' => true, 'min' => 0],
                        'name' => ['type' => 'string', 'required' => true, 'trim' => true, 'minLength' => 1, 'maxLength' => 100],
                    ]);
                } catch (AetherValidationException $e) {
                    array_push($errors, ...$e->getValidationErrors());
                }
            }
        }
        if ($errors) throw new AetherValidationException($errors);
        $validated['categoryIds'] = array_values(array_unique($categories));
        $validated['specialisations'] = array_map(static fn(array $item): array => [
            'idSkillSpecialisation' => $item['idSkillSpecialisation'], 'name' => trim($item['name']),
        ], $specialisations);
    }
    if ($route === 'saveSkillType') {
        if ($validated['action'] === 'create' && $validated['idSkillType'] !== 0) {
            throw new AetherValidationException(['Nieuwe categorie mag geen bestaand ID opgeven.']);
        }
        if ($validated['action'] !== 'create' && $validated['idSkillType'] < 1) {
            throw new AetherValidationException(['Een bestaand categorie-ID is verplicht.']);
        }
        if ($validated['action'] !== 'delete' && $validated['name'] === '') {
            throw new AetherValidationException(['De categorienaam is verplicht.']);
        }
    }
    return $validated;
}
