<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/richText.php';

function aetherSanitizeCompanyDescription(string $description): string
{
    return aetherSanitizeRichText($description, [
        'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h4',
    ]);
}
