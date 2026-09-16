<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/richText.php';

const AETHER_CHARACTER_RICH_TEXT_SECTIONS = [
    'personal_background',
    'knowledge',
    'nature',
    'demeanour',
];

const AETHER_CHARACTER_RICH_TEXT_DIARY_FIELDS = [
    'goals',
    'achievements',
];

const AETHER_CHARACTER_RICH_TEXT_ALLOWED_ELEMENTS = [
    'p',
    'br',
    'strong',
    'em',
    'ul',
    'ol',
    'li',
    'h4',
];

function aetherSanitizeCharacterRichText(string $html): string
{
    return aetherSanitizeRichText($html, AETHER_CHARACTER_RICH_TEXT_ALLOWED_ELEMENTS);
}

/** @param array<string, mixed> $row */
function aetherSanitizeCharacterDiaryRow(array $row): array
{
    foreach (AETHER_CHARACTER_RICH_TEXT_DIARY_FIELDS as $field) {
        $row[$field] = aetherSanitizeCharacterRichText((string) ($row[$field] ?? ''));
    }

    return $row;
}
