<?php
declare(strict_types=1);

final class CharacterRequestValidationException extends InvalidArgumentException
{
    /** @var list<string> */
    private array $validationErrors;

    /** @param list<string> $validationErrors */
    public function __construct(array $validationErrors)
    {
        parent::__construct('Ongeldige invoer.');
        $this->validationErrors = $validationErrors;
    }

    /** @return list<string> */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}

/** @return array<string, array<string, mixed>> */
function aetherCharacterRequestSchema(string $route, array $input = []): array
{
    $id = static fn(bool $required = true): array => ['type' => 'int', 'required' => $required, 'min' => 1];
    $optionalId = static fn(): array => ['type' => 'int', 'required' => false, 'min' => 0];
    $text = static fn(bool $required, int $max, int $min = 0): array => [
        'type' => 'string', 'required' => $required, 'trim' => true,
        'minLength' => $min, 'maxLength' => $max, 'html' => false,
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
            'goals' => $text(false, 16000), 'achievements' => $text(false, 16000),
            'gossip1' => $text(false, 16000), 'gossip2' => $text(false, 16000), 'gossip3' => $text(false, 16000),
        ],
        'saveCharacterSection' => [
            'idCharacter' => $id(),
            'section' => $enum(true, ['personal_background', 'knowledge', 'nature', 'demeanour']),
            'content' => $text(false, 16000),
        ],
        'saveCharacterTie' => [
            'idCharacter' => $id(), 'idTie' => $optionalId(), 'idOtherCharacter' => $id(),
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

/** @return array<string, mixed> */
function aetherValidateCharacterRequest(string $route, array $input): array
{
    $schema = aetherCharacterRequestSchema($route, $input);
    $errors = [];
    $unknown = array_diff(array_keys($input), array_keys($schema));
    foreach ($unknown as $field) {
        $errors[] = "Onverwacht veld: {$field}.";
    }

    $validated = [];
    foreach ($schema as $field => $rules) {
        $present = array_key_exists($field, $input);
        if (!$present) {
            if (($rules['required'] ?? false) === true) {
                $errors[] = "Veld {$field} is verplicht.";
            } elseif (array_key_exists('default', $rules)) {
                $validated[$field] = $rules['default'];
            }
            continue;
        }

        $value = $input[$field];
        $type = (string) ($rules['type'] ?? 'string');
        if ($value === null && str_starts_with($type, 'nullable_')) {
            $validated[$field] = null;
            continue;
        }

        if ($type === 'string' || $type === 'enum' || $type === 'nullable_enum' || $type === 'date') {
            if (!is_string($value)) {
                $errors[] = "Veld {$field} moet tekst zijn.";
                continue;
            }
            if (($rules['trim'] ?? $type !== 'date') === true) {
                $value = trim($value);
            }
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length < (int) ($rules['minLength'] ?? 0)) {
                $errors[] = "Veld {$field} is te kort.";
            }
            if (isset($rules['maxLength']) && $length > (int) $rules['maxLength']) {
                $errors[] = "Veld {$field} is te lang (maximaal {$rules['maxLength']} tekens).";
            }
            if (($type === 'enum' || $type === 'nullable_enum') && !in_array($value, $rules['values'] ?? [], true)) {
                $errors[] = "Veld {$field} bevat geen toegestane waarde.";
            }
            if ($type === 'date') {
                if (($rules['allowEmpty'] ?? false) === true && $value === '') {
                    $validated[$field] = '';
                    continue;
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                $dateErrors = DateTimeImmutable::getLastErrors();
                if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
                    $errors[] = "Veld {$field} moet een geldige datum in formaat JJJJ-MM-DD zijn.";
                }
            }
            $validated[$field] = $value;
            continue;
        }

        if ($type === 'int' || $type === 'nullable_int') {
            $validInteger = is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1);
            if (!$validInteger || is_bool($value)) {
                $errors[] = "Veld {$field} moet een geheel getal zijn.";
                continue;
            }
            $value = (int) $value;
        } elseif ($type === 'number') {
            if ((!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) || is_bool($value)) {
                $errors[] = "Veld {$field} moet een getal zijn.";
                continue;
            }
            $value = (float) $value;
            if (!is_finite($value)) {
                $errors[] = "Veld {$field} moet een eindig getal zijn.";
                continue;
            }
            if (isset($rules['scale'])) {
                $value = round($value, (int) $rules['scale']);
            }
        } elseif ($type === 'bool') {
            if (!is_bool($value)) {
                $errors[] = "Veld {$field} moet true of false zijn.";
                continue;
            }
        } else {
            throw new LogicException("Onbekend validatietype {$type} voor {$field}.");
        }

        if (isset($rules['min']) && $value < $rules['min']) {
            $errors[] = "Veld {$field} is kleiner dan toegestaan.";
        }
        if (isset($rules['minExclusive']) && $value <= $rules['minExclusive']) {
            $errors[] = "Veld {$field} moet groter zijn dan {$rules['minExclusive']}.";
        }
        if (isset($rules['max']) && $value > $rules['max']) {
            $errors[] = "Veld {$field} is groter dan toegestaan.";
        }
        $validated[$field] = $value;
    }

    if ($errors !== []) {
        throw new CharacterRequestValidationException($errors);
    }
    return $validated;
}

/** @return array<string, mixed> */
function aetherReadCharacterJsonRequest(string $route): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        $input = $_POST !== [] ? $_POST : [];
    } else {
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            aetherCharacterValidationFailure(['Requestbody bevat geen geldige JSON.']);
        }
    }
    if (!is_array($input) || (array_is_list($input) && $input !== [])) {
        aetherCharacterValidationFailure(['Requestbody moet een JSON-object zijn.']);
    }
    try {
        return aetherValidateCharacterRequest($route, $input);
    } catch (CharacterRequestValidationException $e) {
        aetherCharacterValidationFailure($e->getValidationErrors());
    }
}

/** @return array<string, mixed> */
function aetherValidateCharacterRequestOrFail(string $route, array $input): array
{
    try {
        return aetherValidateCharacterRequest($route, $input);
    } catch (CharacterRequestValidationException $e) {
        aetherCharacterValidationFailure($e->getValidationErrors());
    }
}

/** @param list<string> $errors */
function aetherCharacterValidationFailure(array $errors): never
{
    http_response_code(422);
    echo json_encode(['error' => 'Ongeldige invoer.', 'validationErrors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}
