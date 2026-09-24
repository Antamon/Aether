<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$pages = ['index.html', 'eventParticipation.html', 'companies.html', 'admin.html', 'static.html'];
foreach ($pages as $page) {
    $html = file_get_contents($root . '/' . $page);
    if ($html === false) throw new RuntimeException("Pagina ontbreekt: $page");
    foreach (['asset.php?path=css/style.css', 'aether-page', 'navbar navbar-expand-lg'] as $shared) {
        if (!str_contains($html, $shared)) throw new RuntimeException("$page mist $shared");
    }
    $dom = new DOMDocument();
    // DOMDocument uses an HTML4 parser; HTML5 attributes may emit benign warnings.
    if (!@$dom->loadHTML($html)) throw new RuntimeException("Ongeldige HTML in $page");
    if ($dom->getElementsByTagName('body')->length !== 1) throw new RuntimeException("Body ontbreekt in $page");
    if (isset(['eventParticipation.html'=>'eventList', 'companies.html'=>'companyForm', 'admin.html'=>'adminSkillsSection'][$page])) {
        $targetId = ['eventParticipation.html'=>'eventList', 'companies.html'=>'companyForm', 'admin.html'=>'adminSkillsSection'][$page];
        $xpath = new DOMXPath($dom);
        if ($xpath->query('//*[@id="' . $targetId . '"]/ancestor::main')->length !== 1) {
            throw new RuntimeException("$page heeft de actieve inhoud niet in de hoofdcontainer.");
        }
    }
}

$event = file_get_contents($root . '/eventParticipation.html');
$eventDom = new DOMDocument();
@$eventDom->loadHTML($event);
$eventXpath = new DOMXPath($eventDom);
if ($eventXpath->query('//table/thead/tr/th')->length !== $eventXpath->query('//*[@id="eventNew"]/tr/td')->length) {
    throw new RuntimeException('Eventheader en nieuwe-eventrij hebben niet hetzelfde aantal kolommen.');
}
foreach (['newTitle', 'newDescription', 'newDateStart', 'newDateEnd', 'newVenue', 'newExperience'] as $id) {
    if (!preg_match('/<input\b(?=[^>]*\bid="' . $id . '")(?=[^>]*\bclass="[^"]*form-control)[^>]*>/s', $event)) {
        throw new RuntimeException("Eventveld $id mist gedeelde Bootstrapopmaak.");
    }
}
foreach (['companies.html', 'admin.html', 'index.html'] as $page) {
    if (!str_contains(file_get_contents($root . '/' . $page), 'aether-panel')) {
        throw new RuntimeException("$page mist gedeelde paneelopmaak.");
    }
}
foreach (['formCharacter.js', 'actionsCharacter.js', 'economyCharacter.js', 'passportCharacter.js'] as $script) {
    if (!str_contains(file_get_contents($root . '/js/' . $script), 'aether-panel')) {
        throw new RuntimeException("Dynamische kaarten in $script missen de gedeelde paneelklasse.");
    }
}
$companies = file_get_contents($root . '/companies.html');
if (substr_count($companies, 'aether-inset') < 5) throw new RuntimeException('Bedrijfssubpanelen delen de insetklasse niet.');
$css = file_get_contents($root . '/css/style.css');
foreach (['.aether-page', '.aether-panel', '.aether-inset', '.aether-field-label'] as $selector) {
    if (!str_contains($css, $selector)) throw new RuntimeException("CSS-selector ontbreekt: $selector");
}
if (substr_count($css, '{') !== substr_count($css, '}')) throw new RuntimeException('CSS-accolades zijn niet in balans.');
echo "PASS: vijf pagina's delen basislayout en stylesheet; panelen, subpanelen en eventvelden gebruiken gedeelde stijlen.\n";
