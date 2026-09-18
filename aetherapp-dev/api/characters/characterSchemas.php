<?php
declare(strict_types=1);

/** @return array<string, array<string, mixed>> */
function aetherCharacterRequestSchema(string $route, array $input = []): array
{
    $id = static fn(bool $required = true): array => ['type' => 'int', 'required' => $required, 'min' => 1];
    $optionalId = static fn(): array => ['type' => 'int', 'required' => false, 'min' => 0];
    $text = static fn(bool $required, int $max, int $min = 0): array => [
        'type' => 'string', 'required' => $required, 'trim' => true,
        'minLength' => $min, 'maxLength' => $max, 'html' => false,
    ];
    $richText = static fn(bool $required, int $max): array => [
        'type' => 'string', 'required' => $required, 'trim' => true,
        'minLength' => 0, 'maxLength' => $max, 'html' => true,
    ];
    $enum = static fn(bool $required, array $values): array => [
        'type' => 'enum', 'required' => $required, 'trim' => true, 'values' => $values,
    ];

    $simpleIdRoutes = [
        'deleteCharacter' => 'id',
        'deleteCharacterPortrait' => 'id',
        'getCharacter' => 'id',
        'getNewSkills' => 'id',
        'deleteBankTransaction' => 'idTransaction',
        'deleteCharacterEconomySnapshot' => 'idSnapshot',
    ];
    if (isset($simpleIdRoutes[$route])) {
        return [$simpleIdRoutes[$route] => $id()];
    }

    $characterIdRoutes = [
        'getCharacterActionEvents', 'getCharacterDiary', 'getCharacterLanguageOptions',
        'getCharacterSections', 'getCharacterTies', 'saveCharacterEconomySnapshot',
    ];
    if (in_array($route, $characterIdRoutes, true)) {
        $schema = ['idCharacter' => $id()];
        if ($route === 'saveCharacterEconomySnapshot') {
            $schema['idEvent'] = $id();
        }
        return $schema;
    }

    if (in_array($route, ['getCharacterList', 'getCharacterTieOptions'], true)) {
        return [];
    }

    return match ($route) {
        'addCharacterLanguage' => [
            'idCharacter' => $id(),
            'idLanguage' => $optionalId(),
            'name' => $text(false, 120),
        ],
        'AddNewSkill' => [
            'idCharacter' => $id(), 'idSkill' => $id(),
            'level' => ['type' => 'int', 'required' => false, 'min' => 0, 'max' => 3, 'default' => 0],
        ],
        'addSkillSpecialisation' => [
            'idSkill' => $id(), 'idCharacter' => $id(),
            'idSkillSpecialisation' => $optionalId(),
            'name' => $text(false, 100),
            'kind' => ['type' => 'nullable_enum', 'required' => false, 'values' => ['discipline', 'specialisation']],
        ],
        'buyCompanyShare' => [
            'idCharacter' => $id(), 'idCompany' => $id(),
            'shareClass' => $enum(true, ['A', 'B']),
        ],
        'deleteCharacterLanguage' => ['idCharacter' => $id(), 'idCharacterLanguage' => $id()],
        'deleteCharacterTie' => ['idCharacter' => $id(), 'idTie' => $id()],
        'deleteSkillSpecialisation' => [
            'idSkill' => $id(), 'idCharacter' => $id(), 'idSkillSpecialisation' => $id(),
        ],
        'getCharacterActionKnowledgeTargets' => ['idCharacter' => $id(), 'idEvent' => $id()],
        'getDisciplineList', 'getSkillSpecialisations' => ['idSkill' => $id(), 'idCharacter' => $id()],
        'revealCharacterActionKnowledge' => [
            'idCharacter' => $id(), 'idEvent' => $id(), 'idSourceCharacter' => $id(),
        ],
        'saveBankTransfer' => [
            'idSourceCharacter' => $id(), 'idTargetCharacter' => $id(),
            'amount' => ['type' => 'number', 'required' => true, 'minExclusive' => 0, 'max' => 9999999999.99, 'scale' => 2],
            'description' => $text(false, 255),
            'transactionDate' => ['type' => 'date', 'required' => true],
        ],
        'saveCharacterDiary' => [
            'idCharacter' => $id(), 'idDiary' => $optionalId(), 'idEvent' => $id(),
            'goals' => $richText(false, 16000), 'achievements' => $richText(false, 16000),
            'gossip1' => $text(false, 16000), 'gossip2' => $text(false, 16000), 'gossip3' => $text(false, 16000),
        ],
        'saveCharacterSection' => [
            'idCharacter' => $id(),
            'section' => $enum(true, ['personal_background', 'knowledge', 'nature', 'demeanour']),
            'content' => $richText(false, 16000),
        ],
        'saveCharacterTie' => [
            'idCharacter' => $id(),
            'idTie' => ['type' => 'nullable_int', 'required' => false, 'min' => 0, 'default' => null],
            'idOtherCharacter' => $id(),
            'relationType' => $enum(true, ['superior', 'dependent', 'landlord', 'household_staff', 'spouse', 'ally', 'adversary', 'person_of_interest']),
            'description' => $text(false, 255),
        ],
        'saveCompanyShare' => aetherCompanyShareSchema($input, $id, $enum),
        'updateSkill' => [
            'action' => $enum(true, ['up', 'down', 'delete']), 'idSkill' => $id(), 'idCharacter' => $id(),
        ],
        'updateTrait' => [
            'action' => $enum(true, ['add', 'change', 'remove', 'rank_up', 'rank_down']),
            'idCharacter' => $id(), 'idTrait' => $id(), 'idCurrentTrait' => $optionalId(),
        ],
        'useCharacterSkillAction' => [
            'idCharacter' => $id(), 'idEvent' => $id(), 'idSkill' => $id(),
            'actionCode' => $enum(true, ['psi']),
            'actionSubtype' => $text(true, 64, 1),
            'clearBurn' => ['type' => 'bool', 'required' => false, 'default' => false],
        ],
        'uploadCharacterPortrait' => ['id' => $id()],
        'saveCharacterSecuritiesPortfolio' => aetherCharacterSecuritiesSchema($input, $id, $enum),
        'newCharacter' => aetherNewCharacterSchema($id, $text, $enum),
        'updateCharacter' => aetherUpdateCharacterSchema($id, $text, $enum),
        default => throw new LogicException("Geen invoerschema voor character-route {$route}."),
    };
}

/** @return array<string, array<string, mixed>> */
function aetherCompanyShareSchema(array $input, callable $id, callable $enum): array
{
    $action = is_string($input['action'] ?? null) ? trim($input['action']) : null;
    $schema = [
        'action' => $enum(true, ['assign_company', 'clear_company', 'increase_rank', 'decrease_rank']),
        'idLinkCharacterTrait' => $id(),
    ];
    return match ($action) {
        'assign_company' => $schema + ['idCompany' => $id()],
        'clear_company' => $schema + [
            'idCompany' => ['type' => 'nullable_int', 'required' => false, 'min' => 1],
        ],
        default => $schema,
    };
}

/** @return array<string, array<string, mixed>> */
function aetherCharacterSecuritiesSchema(array $input, callable $id, callable $enum): array
{
    $action = is_string($input['action'] ?? null) ? trim($input['action']) : null;
    $schema = [
        'action' => $enum(true, ['save_settings', 'deposit', 'manual_withdrawal', 'reroll_snapshot', 'approve_snapshot', 'withdraw_snapshot']),
        'idCharacter' => $id(),
    ];
    return match ($action) {
        'save_settings' => $schema + [
            'managerType' => $enum(true, ['self', 'bank', 'third']),
            'riskProfile' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 5],
            'managerCharacterId' => ['type' => 'nullable_int', 'required' => false, 'min' => 1],
        ],
        'deposit', 'manual_withdrawal' => $schema + [
            'amount' => ['type' => 'number', 'required' => true, 'minExclusive' => 0, 'max' => 9999999999.99, 'scale' => 2],
        ],
        'reroll_snapshot', 'approve_snapshot' => $schema + ['idSnapshot' => $id()],
        'withdraw_snapshot' => $schema + [
            'idSnapshot' => $id(),
            'amount' => ['type' => 'number', 'required' => true, 'min' => 0, 'max' => 9999999999.99, 'scale' => 2],
        ],
        default => $schema,
    };
}

/** @return array<string, array<string, mixed>> */
function aetherNewCharacterSchema(callable $id, callable $text, callable $enum): array
{
    return [
        'idUser' => ['type' => 'int', 'required' => false, 'min' => 0],
        'type' => $enum(true, ['player', 'extra']),
        'state' => $enum(false, ['active', 'inactive', 'deceased', 'other', 'draft', 'approve']),
        'firstName' => $text(true, 40, 2), 'lastName' => $text(true, 40, 2),
        'class' => $enum(true, ['upper class', 'middle class', 'lower class']),
        'birthDate' => ['type' => 'date', 'required' => false, 'default' => '1900-01-01'],
        'birthPlace' => $text(false, 40), 'nationality' => $text(false, 40),
        'stateRegisterNumber' => $text(false, 8), 'street' => $text(false, 30),
        'houseNumber' => $text(false, 11), 'municipality' => $text(false, 30),
        'postalCode' => $text(false, 4), 'title' => $text(false, 30),
        'maritalStatus' => $enum(false, ['', 'Single', 'Married', 'Widowed']),
        'experienceToTrait' => ['type' => 'int', 'required' => false, 'min' => 0, 'max' => 6, 'default' => 0],
        'physicalHealth' => ['type' => 'int', 'required' => false, 'min' => -3, 'max' => 127, 'default' => 0],
        'mentalHealth' => ['type' => 'int', 'required' => false, 'min' => -3, 'max' => 127, 'default' => 0],
        'physicalHealthFree' => ['type' => 'int', 'required' => false, 'min' => -128, 'max' => 127, 'default' => 0],
        'mentalHealthFree' => ['type' => 'int', 'required' => false, 'min' => -128, 'max' => 127, 'default' => 0],
    ];
}

/** @return array<string, array<string, mixed>> */
function aetherUpdateCharacterSchema(callable $id, callable $text, callable $enum): array
{
    $editable = aetherNewCharacterSchema($id, $text, $enum);
    foreach ($editable as &$rules) {
        $rules['required'] = false;
        unset($rules['default']);
    }
    unset($rules);
    $editable['class']['values'][] = '';
    $editable['birthDate']['allowEmpty'] = true;

    return ['id' => $id()] + $editable + [
        'bankaccount' => ['type' => 'number', 'required' => false, 'min' => -9999999999.99, 'max' => 9999999999.99, 'scale' => 2],
        'securitiesaccount' => ['type' => 'number', 'required' => false, 'min' => 0, 'max' => 9999999999.99, 'scale' => 2],
        // Herkend om de bestaande expliciete 403-controle te behouden; nooit opgenomen in SQL.
        'createdBy' => ['type' => 'int', 'required' => false, 'min' => 0],
        'createdAt' => ['type' => 'string', 'required' => false, 'trim' => true, 'maxLength' => 32, 'html' => false],
    ];
}
