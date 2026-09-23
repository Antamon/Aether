<?php
declare(strict_types=1);

/** @return array<string, array<string, mixed>> */
function aetherEventFields(bool $required): array
{
    return [
        'type' => ['type' => 'enum', 'values' => ['weekend', 'mini'], 'required' => $required],
        'title' => ['type' => 'string', 'trim' => true, 'minLength' => 1, 'maxLength' => 50, 'required' => $required, 'html' => false],
        'description' => ['type' => 'string', 'trim' => true, 'maxLength' => 255, 'required' => $required, 'html' => false],
        'dateStart' => ['type' => 'date', 'required' => $required],
        'dateEnd' => ['type' => 'date', 'required' => $required],
        'venue' => ['type' => 'string', 'trim' => true, 'maxLength' => 50, 'required' => $required, 'html' => false],
        'ep' => ['type' => 'int', 'min' => 0, 'max' => 2147483647, 'required' => $required],
    ];
}

/** @return array<string, array<string, mixed>> */
function aetherEventRequestSchema(string $action): array
{
    return match ($action) {
        'list' => [
            'idUser' => ['type' => 'int', 'min' => 0, 'default' => 0],
        ],
        'create' => aetherEventFields(true),
        'update' => ['id' => ['type' => 'int', 'min' => 1, 'required' => true]] + aetherEventFields(false),
        'participation' => [
            'idEvent' => ['type' => 'int', 'min' => 1, 'required' => true],
            'idUser' => ['type' => 'int', 'min' => 0, 'default' => 0],
            'participation' => ['type' => 'bool', 'required' => true],
        ],
        'knowledgeVisibility' => [
            'idEvent' => ['type' => 'int', 'min' => 1, 'required' => true],
            'idCharacter' => ['type' => 'int', 'min' => 1, 'required' => true],
            'isVisible' => ['type' => 'bool', 'required' => true],
        ],
        'deleteKnowledgeUnlock' => [
            'idEvent' => ['type' => 'int', 'min' => 1, 'required' => true],
            'idSourceCharacter' => ['type' => 'int', 'min' => 1, 'required' => true],
            'idViewerCharacter' => ['type' => 'int', 'min' => 1, 'required' => true],
        ],
        'eventId' => [
            'idEvent' => ['type' => 'int', 'min' => 1, 'required' => true],
        ],
        'adminActionUseUpdate' => [
            'idActionUse' => ['type' => 'int', 'min' => 1, 'required' => true],
            'idSkill' => ['type' => 'int', 'min' => 0, 'required' => true],
            'createdAt' => ['type' => 'string', 'trim' => true, 'minLength' => 1, 'maxLength' => 32, 'required' => true],
            'actionCode' => ['type' => 'string', 'trim' => true, 'minLength' => 1, 'maxLength' => 100, 'required' => true],
            'actionSubtype' => ['type' => 'string', 'trim' => true, 'maxLength' => 100, 'required' => true],
            'rollBase' => ['type' => 'string', 'trim' => true, 'maxLength' => 12, 'required' => true],
            'rollModifier' => ['type' => 'int', 'min' => -2147483648, 'max' => 2147483647, 'required' => true],
            'rollFinal' => ['type' => 'string', 'trim' => true, 'maxLength' => 12, 'required' => true],
            'resultTitle' => ['type' => 'string', 'trim' => true, 'maxLength' => 255, 'required' => true, 'html' => false],
            'resultText' => ['type' => 'string', 'trim' => true, 'maxLength' => 65535, 'required' => true, 'html' => false],
        ],
        'adminActionUseDelete' => [
            'idActionUse' => ['type' => 'int', 'min' => 1, 'required' => true],
        ],
        default => throw new LogicException("Onbekend eventschema: {$action}."),
    };
}
