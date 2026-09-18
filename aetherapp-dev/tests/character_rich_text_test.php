<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/api/characters/characterRichText.php';
require_once $projectRoot . '/api/characters/characterRequestValidation.php';

$failures = [];

function assertCharacterRichText(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$formatted = aetherSanitizeCharacterRichText(
    '<h4>Kop</h4><p><strong>Vet</strong> en <em>cursief</em><br>regel</p>'
    . '<ul><li>Eén</li><li>Twee</li></ul><ol><li>Drie</li></ol>'
);
foreach (['<h4>Kop</h4>', '<strong>Vet</strong>', '<em>cursief</em>', '<br', '<ul>', '<ol>', '<li>'] as $fragment) {
    assertCharacterRichText(
        str_contains($formatted, $fragment),
        "Toegestane rich-textopmaak ontbreekt na sanitization: {$fragment}"
    );
}

$legacy = aetherSanitizeCharacterRichText(
    '<div style="margin-left:20px">België&nbsp;<span class="legacy" onclick="evil()"><b>vet</b> en <i>cursief</i></span></div>'
    . '<div>Tweede alinea</div>'
);
assertCharacterRichText(str_contains($legacy, '<p>'), 'Legacy divs worden niet naar alinea’s genormaliseerd.');
assertCharacterRichText(str_contains($legacy, 'België'), 'Accenten blijven niet behouden.');
assertCharacterRichText(str_contains($legacy, 'Tweede alinea'), 'Tekst uit een tweede legacy div gaat verloren.');
assertCharacterRichText(str_contains($legacy, '<strong>vet</strong>'), 'Legacy b wordt niet naar strong genormaliseerd.');
assertCharacterRichText(str_contains($legacy, '<em>cursief</em>'), 'Legacy i wordt niet naar em genormaliseerd.');
foreach (['<div', '<span', 'style=', 'class=', 'onclick='] as $forbiddenFragment) {
    assertCharacterRichText(
        !str_contains(strtolower($legacy), $forbiddenFragment),
        "Legacy markup bevat nog verboden fragment: {$forbiddenFragment}"
    );
}
$legacyText = html_entity_decode(strip_tags($legacy), ENT_QUOTES | ENT_HTML5, 'UTF-8');
assertCharacterRichText(str_contains($legacyText, "\u{00A0}"), '&nbsp; blijft niet als één spatie-eenheid behouden.');

$dangerous = aetherSanitizeCharacterRichText(
    '<script>alert("script")</script>'
    . '<style>body{display:none}</style>'
    . '<p id="x" class="y" style="color:red" onclick="evil()">Veilige tekst'
    . '<img src=x onerror="alert(1)"></p>'
    . '<iframe src="https://example.invalid">iframe-inhoud</iframe>'
    . '<object data="x">object-inhoud</object><embed src="x">'
    . '<svg onload="evil()"><script>alert("svg")</script><circle></circle></svg>'
    . '<form action="https://example.invalid"><input value="x"><button>verstuur</button></form>'
    . '<a href="javascript:alert(1)">linktekst</a>'
);
assertCharacterRichText(str_contains($dangerous, 'Veilige tekst'), 'Veilige tekst rond gevaarlijke markup gaat verloren.');
foreach ([
    '<script', 'alert(', '<style', 'display:none', 'onclick=', 'onerror=', 'style=', 'class=', 'id=',
    '<img', '<svg', '<iframe', 'iframe-inhoud', '<object', 'object-inhoud', '<embed', '<form', '<input', '<button',
    'javascript:', 'href=', 'src=',
] as $forbiddenFragment) {
    assertCharacterRichText(
        !str_contains(strtolower($dangerous), $forbiddenFragment),
        "Gevaarlijke rich text bevat nog: {$forbiddenFragment}"
    );
}

$document = new DOMDocument('1.0', 'UTF-8');
$previousErrorMode = libxml_use_internal_errors(true);
$document->loadHTML(
    '<?xml encoding="UTF-8"><div>' . $formatted . $legacy . $dangerous . '</div>',
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
);
libxml_clear_errors();
libxml_use_internal_errors($previousErrorMode);
$allowedTags = AETHER_CHARACTER_RICH_TEXT_ALLOWED_ELEMENTS;
foreach ($document->getElementsByTagName('*') as $element) {
    if (strtolower($element->tagName) === 'div') {
        continue;
    }
    assertCharacterRichText(
        in_array(strtolower($element->tagName), $allowedTags, true),
        "Sanitizer liet een niet-toegestaan element door: {$element->tagName}"
    );
    assertCharacterRichText(
        $element->attributes->length === 0,
        "Sanitizer liet attributen door op {$element->tagName}."
    );
}

$roundTrip = aetherSanitizeCharacterRichText($formatted);
assertCharacterRichText($roundTrip === $formatted, 'Opnieuw openen en bewaren verandert toegestane opmaak.');
$jsonRoundTrip = json_decode(json_encode(['content' => $formatted], JSON_UNESCAPED_UNICODE), true);
assertCharacterRichText(
    is_array($jsonRoundTrip)
        && aetherSanitizeCharacterRichText((string) $jsonRoundTrip['content']) === $formatted,
    'JSON opslaan en opnieuw laden behoudt de toegestane rich text niet.'
);

$sectionSchema = aetherCharacterRequestSchema('saveCharacterSection');
$diarySchema = aetherCharacterRequestSchema('saveCharacterDiary');
assertCharacterRichText(($sectionSchema['content']['html'] ?? false) === true, 'Charactersecties zijn niet als rich text gedocumenteerd.');
foreach (AETHER_CHARACTER_RICH_TEXT_DIARY_FIELDS as $field) {
    assertCharacterRichText(($diarySchema[$field]['html'] ?? false) === true, "Diaryveld {$field} is niet als rich text gedocumenteerd.");
}
foreach (['gossip1', 'gossip2', 'gossip3'] as $plainTextField) {
    assertCharacterRichText(
        ($diarySchema[$plainTextField]['html'] ?? true) === false,
        "Gewoon tekstveld {$plainTextField} is onterecht als HTML gemarkeerd."
    );
}

/** @return string */
function richTextSource(string $projectRoot, string $relativePath): string
{
    $source = file_get_contents($projectRoot . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException("Kon {$relativePath} niet lezen.");
    }
    return $source;
}

$saveSection = richTextSource($projectRoot, 'api/characters/saveCharacterSection.php');
$readSections = richTextSource($projectRoot, 'api/characters/getCharacterSections.php');
$saveDiary = richTextSource($projectRoot, 'api/characters/saveCharacterDiary.php');
$readDiary = richTextSource($projectRoot, 'api/characters/getCharacterDiary.php');
$readService = richTextSource($projectRoot, 'api/characters/characterReadService.php');
foreach ([$saveSection, $saveDiary] as $routeSource) {
    assertCharacterRichText(
        str_contains($routeSource, 'characterRichText.php'),
        'Een rich-text schrijfroutelaadt de character-sanitizer niet.'
    );
}
foreach ([$readSections, $readDiary] as $routeSource) {
    assertCharacterRichText(
        str_contains($routeSource, 'characterReadService.php'),
        'Een rich-text leesroute laadt de gedeelde character-leesservice niet.'
    );
}
assertCharacterRichText(
    str_contains($readService, 'characterRichText.php'),
    'De character-leesservice laadt de character-sanitizer niet.'
);
assertCharacterRichText(
    str_contains($saveSection, 'aetherSanitizeCharacterRichText')
        && str_contains($saveSection, "'content' => \$content"),
    'Charactersecties worden niet vóór opslag gesanitized en gesanitized teruggegeven.'
);
assertCharacterRichText(
    substr_count($saveDiary, 'aetherSanitizeCharacterRichText') >= 2,
    'Goals en achievements worden niet beide vóór opslag gesanitized.'
);
assertCharacterRichText(
    str_contains($readService, 'aetherSanitizeCharacterRichText')
        && str_contains($readService, 'aetherSanitizeCharacterDiaryRow'),
    'Bestaande rich text wordt niet tijdens iedere leesroute gesanitized.'
);

$richTextJs = richTextSource($projectRoot, 'js/richTextCharacter.js');
$backgroundJs = richTextSource($projectRoot, 'js/backgroundCharacter.js');
$diaryJs = richTextSource($projectRoot, 'js/diaryCharacter.js');
$indexHtml = richTextSource($projectRoot, 'index.html');
foreach (['bold', 'italic', 'insertUnorderedList', 'insertOrderedList', 'formatBlock', 'data-value="p"', 'data-value="h4"', 'removeFormat'] as $toolbarFeature) {
    assertCharacterRichText(
        str_contains($richTextJs, $toolbarFeature),
        "De herstelde toolbar mist functie: {$toolbarFeature}"
    );
}
assertCharacterRichText(
    str_contains($richTextJs, 'editor.contentEditable = "true"')
        && str_contains($richTextJs, 'document.execCommand'),
    'De oorspronkelijke contenteditable-editor is niet hersteld.'
);
assertCharacterRichText(
    str_contains($backgroundJs, 'renderCharacterRichText(viewDiv, content')
        && str_contains($backgroundJs, 'createCharacterRichTextEditor(content'),
    'Background en personality gebruiken de gedeelde rich-texthelpers niet.'
);
assertCharacterRichText(
    str_contains($diaryJs, 'renderCharacterRichText(goalsSection.view')
        && str_contains($diaryJs, 'renderCharacterRichText(achievementsSection.view')
        && str_contains($diaryJs, 'createCharacterRichTextEditor(html'),
    'Goals en achievements worden niet als gesaniteerde rich text weergegeven en bewerkt.'
);
assertCharacterRichText(
    str_contains($diaryJs, 'gossipInputs.gossip1.view.textContent')
        && str_contains($diaryJs, 'view.textContent = entry[g.key]'),
    'Gossip wordt niet langer als gewone tekst weergegeven.'
);
$helperPosition = strpos($indexHtml, 'js/richTextCharacter.js');
$diaryPosition = strpos($indexHtml, 'js/diaryCharacter.js');
$backgroundPosition = strpos($indexHtml, 'js/backgroundCharacter.js');
assertCharacterRichText(
    $helperPosition !== false
        && $diaryPosition !== false
        && $backgroundPosition !== false
        && $helperPosition < $diaryPosition
        && $helperPosition < $backgroundPosition,
    'De rich-texthelper wordt niet vóór de characterconsumenten geladen.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Character rich-text tests passed.\n";
