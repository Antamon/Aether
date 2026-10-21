<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/companies/companyRichText.php';

$html = aetherSanitizeCompanyDescription('<h4>Bedrijf</h4><p><strong>Vast</strong> en <em>oud</em></p>'
    . '<script>alert(1)</script><img src=x onerror=alert(1)>');
if (!str_contains($html, '<strong>Vast</strong>') || !str_contains($html, '<em>oud</em>')
    || !str_contains($html, '<h4>Bedrijf</h4>')
    || str_contains($html, '<script') || str_contains($html, '<img') || str_contains($html, 'onerror')) {
    throw new RuntimeException('Bedrijfsbeschrijving bewaart opmaak of verwijdert gevaarlijke HTML niet correct.');
}
echo "Company description rich text passed.\n";
