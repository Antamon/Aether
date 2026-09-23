<?php
declare(strict_types=1);

/** @return array<string, array<string, mixed>> */
function aetherCompanyUpdateSchema(): array
{
    return [
        'id' => ['type' => 'int', 'required' => true, 'min' => 1],
        'companyName' => ['type' => 'string', 'required' => false, 'trim' => true, 'minLength' => 1, 'maxLength' => 255],
        'description' => ['type' => 'string', 'required' => false, 'trim' => true],
        'foundationDate' => ['type' => 'date', 'required' => false, 'allowEmpty' => true],
        'companyValue' => ['type' => 'decimal', 'required' => false, 'min' => '0.00', 'max' => '9999999999.99', 'scale' => 2],
        'stability' => ['type' => 'int', 'required' => false, 'min' => -7, 'max' => 7],
        'profitability' => ['type' => 'int', 'required' => false, 'min' => -7, 'max' => 7],
    ];
}

/** @return array<string, array<string, mixed>> */
function aetherCompanySnapshotSchema(array $input): array
{
    $action = is_string($input['action'] ?? null) ? trim($input['action']) : '';
    $schema = [
        'action' => [
            'type' => 'enum',
            'required' => true,
            'values' => ['create', 'update', 'recalculate', 'apply', 'delete'],
        ],
        'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
    ];

    return match ($action) {
        'create' => $schema + [
            'idEvent' => ['type' => 'int', 'required' => true, 'min' => 1],
            'stability' => ['type' => 'int', 'required' => false, 'min' => -7, 'max' => 7, 'default' => 0],
            'profitability' => ['type' => 'int', 'required' => false, 'min' => -7, 'max' => 7, 'default' => 0],
        ],
        'update' => $schema + [
            'idCompanySnapshot' => ['type' => 'int', 'required' => true, 'min' => 1],
            'stability' => ['type' => 'int', 'required' => false, 'min' => -7, 'max' => 7, 'default' => 0],
            'profitability' => ['type' => 'int', 'required' => false, 'min' => -7, 'max' => 7, 'default' => 0],
        ],
        'recalculate', 'delete' => $schema + [
            'idCompanySnapshot' => ['type' => 'int', 'required' => true, 'min' => 1],
        ],
        'apply' => $schema + [
            'idCompanySnapshot' => ['type' => 'int', 'required' => true, 'min' => 1],
            'applyAction' => [
                'type' => 'enum',
                'required' => true,
                'values' => ['loss_adjustment', 'reinvest', 'dividend'],
            ],
        ],
        default => $schema,
    };
}
