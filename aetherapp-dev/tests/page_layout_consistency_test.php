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
    if (!str_contains($html, 'https://fonts.googleapis.com/css2?family=Amarante&display=swap')) {
        throw new RuntimeException("$page mist het Amarante-lettertype.");
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
foreach (['backgroundCharacter.js', 'diaryCharacter.js'] as $script) {
    if (!str_contains(file_get_contents($root . '/js/' . $script), 'aether-panel')) {
        throw new RuntimeException("Grote inhoudsblokken in $script missen de decoratieve paneelklasse.");
    }
}
$companies = file_get_contents($root . '/companies.html');
if (substr_count($companies, 'aether-inset') < 5) throw new RuntimeException('Bedrijfssubpanelen delen de insetklasse niet.');
$css = file_get_contents($root . '/css/style.css');
if (!str_contains($css, 'font-family: "Amarante", Georgia, serif;')) {
    throw new RuntimeException('Kopteksten gebruiken Amarante niet.');
}
foreach (['.aether-page', '.aether-panel', '.aether-inset', '.aether-field-label'] as $selector) {
    if (!str_contains($css, $selector)) throw new RuntimeException("CSS-selector ontbreekt: $selector");
}
foreach (['../img/paper.png', 'mainContentBorderTopLeft.svg', 'mainContentBorderTopRight.svg', 'mainContentBorderBottomLeft.svg', 'mainContentBorderBottomRight.svg'] as $assetOrRule) {
    if (!str_contains($css, $assetOrRule)) {
        throw new RuntimeException("Papierachtergrond of decoratieve rand ontbreekt: $assetOrRule");
    }
}
foreach (['padding: 45px 35.5px', '71px 90px no-repeat', 'inset: 0 71px', 'mainContentBorderTopMiddle.svg', 'mainContentBorderBottomMiddle.svg', '71px 90px repeat-x', 'calc(100% - 180px)'] as $fixedBorderRule) {
    if (!str_contains($css, $fixedBorderRule)) {
        throw new RuntimeException("Decoratieve hoeken hebben geen vaste originele afmetingen: $fixedBorderRule");
    }
}
foreach (['img/paper.png', 'img/mainContentBorderTopLeft.svg', 'img/mainContentBorderTopMiddle.svg', 'img/mainContentBorderTopRight.svg', 'img/mainContentBorderMiddleLeft.svg', 'img/mainContentBorderMiddleRight.svg', 'img/mainContentBorderBottomLeft.svg', 'img/mainContentBorderBottomMiddle.svg', 'img/mainContentBorderBottomRight.svg'] as $asset) {
    if (!is_file($root . '/' . $asset)) throw new RuntimeException("Vormgevingsasset ontbreekt: $asset");
}
foreach (['TopLeft', 'TopMiddle', 'TopRight', 'MiddleLeft', 'MiddleRight', 'BottomLeft', 'BottomMiddle', 'BottomRight'] as $tile) {
    $tileSvg = file_get_contents($root . '/img/mainContentBorder' . $tile . '.svg');
    $tileDom = new DOMDocument();
    if ($tileSvg === false || !@$tileDom->loadXML($tileSvg)
        || !str_contains($tileSvg, 'viewBox="0 0 71 90"')) {
        throw new RuntimeException("Randasset $tile heeft niet de verwachte vaste 71 x 90-tegelgrootte.");
    }
}
$characterPage = file_get_contents($root . '/index.html');
$characterDom = new DOMDocument();
@$characterDom->loadHTML($characterPage);
$characterXpath = new DOMXPath($characterDom);
foreach (['characterSidebarContent', 'characterSidebarNavTrigger', 'sidebarCharacterUserFilter', 'sidebarCharacterTypeFilter', 'sidebarCharacterStatusFilter', 'sidebarCharacterClassFilter', 'characterNames', 'startNewCharacter'] as $id) {
    if (!str_contains($characterPage, 'id="' . $id . '"')) throw new RuntimeException("Character-sidebar mist $id.");
}
if ($characterXpath->query('//*[@id="characterNames"]/ancestor::template[@id="characterSidebarContent"]')->length !== 1) {
    throw new RuntimeException('Characterlijst staat niet in de nieuwe sidebar-template.');
}
$companyPage = file_get_contents($root . '/companies.html');
$companyDom = new DOMDocument();
@$companyDom->loadHTML($companyPage);
$companyXpath = new DOMXPath($companyDom);
foreach (['companyList', 'newCompanyButton'] as $id) {
    if ($companyXpath->query('//*[@id="' . $id . '"]/ancestor::template[@id="companySidebarContent"]')->length !== 1) {
        throw new RuntimeException("Bedrijfscontrol $id staat niet in de nieuwe sidebar-template.");
    }
}
if ($companyXpath->query('//*[@id="companyList"]/ancestor::main')->length !== 0) {
    throw new RuntimeException('De oude vaste bedrijvenkolom is nog aanwezig.');
}
if (substr_count((string)file_get_contents($root . '/js/companyFunctions.js'), 'window.closeCompanySidebar?.()') !== 2) {
    throw new RuntimeException('Bedrijf selecteren en aanmaken sluiten de nieuwe sidebar niet.');
}
foreach ($pages as $page) {
    $html = (string)file_get_contents($root . '/' . $page);
    if (!str_contains($html, 'asset.php?path=js/aetherSidebars.js') || !str_contains($html, 'aether-top-nav') || !str_contains($html, 'data-sidebar-page=')) {
        throw new RuntimeException("$page mist de gedeelde sidebarinitialisatie.");
    }
}
foreach (['img/titleDecoration.svg', 'img/goldTab.png', 'img/leather.jpg', 'img/tabPersonages.svg', 'img/tabEvenementen.svg', 'img/tabBedrijven.svg', 'img/tabAdmin.svg'] as $asset) {
    if (!is_file($root . '/' . $asset)) throw new RuntimeException("Sidebarasset ontbreekt: $asset");
}
foreach (['TopLeft', 'TopMiddle', 'TopRight', 'MiddleLeft', 'MiddleRight', 'BottomLeft', 'BottomMiddle', 'BottomRight'] as $tile) {
    $asset = $root . '/img/sidebarContentBorder' . $tile . '.svg';
    $svg = file_get_contents($asset);
    $svgDom = new DOMDocument();
    if ($svg === false || !@$svgDom->loadXML($svg) || !str_contains($svg, 'viewBox="0 0 55 55"')) {
        throw new RuntimeException("Sidebar-randtegel $tile mist of heeft een onverwachte maat.");
    }
}
foreach (['--aether-sidebar-width: min(545.5px', 'translateX(calc(-100% + 19px))', 'width: calc(100% - 4px)', 'width: calc(100% - 15px)', 'width: 124px', 'clip-path: polygon(0 0, 90.4px 33px', '105px 113px', '90.4px 138px, 0 175px)', 'drop-shadow(0 0 10px rgba(0, 0, 0, 0.5))', '55px 55px no-repeat', '55px 55px repeat-x', '--aether-sidebar-tab-offset: 140px', '--aether-sidebar-tab-offset: 280px', '--aether-sidebar-tab-offset: 420px', 'grid-template-columns: repeat(3, minmax(0, 1fr))', 'rgb(251, 233, 168) 65%'] as $sidebarRule) {
    if (!str_contains($css, $sidebarRule)) throw new RuntimeException("Sidebar-regel ontbreekt: $sidebarRule");
}
if (!str_contains($css, 'linear-gradient(#362a1e 0 0) left 6.989px top 55px') || !str_contains($css, 'linear-gradient(#362a1e 0 0) right 6.989px top 55px')) {
    throw new RuntimeException('Rechte sidebarlijnen sluiten niet aan op de vaste hoeken.');
}
if (str_contains($characterPage, 'data-bs-toggle="offcanvas"')) {
    throw new RuntimeException('Character-sidebar gebruikt nog de Bootstrap-slider.');
}
$sidebarJs = file_get_contents($root . '/js/aetherSidebars.js');
foreach (['characters', 'events', 'companies', 'admin'] as $index => $key) {
    $position = strpos($sidebarJs, '{ key: "' . $key . '"');
    if ($position === false || ($index > 0 && $position <= $previousPosition)) {
        throw new RuntimeException('Sidebar-tabvolgorde is niet Characters, Evenementen, Bedrijven, Admin.');
    }
    $previousPosition = $position;
}
if (substr_count($sidebarJs, 'templateId:') !== 2) {
    throw new RuntimeException('Event- en adminsidebar moeten voorlopig leeg blijven.');
}
foreach (['tabPersonages.svg', 'tabEvenementen.svg', 'tabBedrijven.svg', 'tabAdmin.svg', 'document.body.append(deck)', 'deck.classList.toggle("is-open"', 'panel.inert = !open', 'aria-expanded', 'getBoundingClientRect().bottom', 'window.closeCharacterSidebar', 'window.closeCompanySidebar'] as $sidebarBehavior) {
    if (!str_contains($sidebarJs, $sidebarBehavior)) throw new RuntimeException("Sidebarbediening mist $sidebarBehavior");
}
if (str_contains($sidebarJs, 'window.location.href = `${config.page}?sidebar=${config.key}`')) {
    throw new RuntimeException('Een tabklik mag geen paginawissel veroorzaken.');
}
foreach (['loadNavigationList(nextKey)', 'index.html?character=', 'companies.html?company=', 'renderNavigationCharacters', 'renderNavigationCompanies', 'tabHost.classList.toggle("is-active", open)'] as $navigationBehavior) {
    if (!str_contains($sidebarJs, $navigationBehavior)) throw new RuntimeException("Itemnavigatie mist $navigationBehavior");
}
if (!str_contains($css, '.aether-sidebar-deck.is-open .offcanvas-tab:not(.is-active)')) {
    throw new RuntimeException('Gesloten tabs blijven niet links wanneer één sidebar open is.');
}
if (!str_contains($css, 'pointer-events: none;') || !str_contains($css, 'z-index: 0;')) {
    throw new RuntimeException('Niet-actieve tabs verdwijnen niet achter de geopende sidebar.');
}
if (!str_contains((string) file_get_contents($root . '/js/characterFunctions.js'), 'params.get("character")')
    || !str_contains((string) file_get_contents($root . '/js/companyFunctions.js'), 'params.get("company")')) {
    throw new RuntimeException('De gekozen sidebaritems worden niet in de doelpagina geladen.');
}
if (substr_count($css, '{') !== substr_count($css, '}')) throw new RuntimeException('CSS-accolades zijn niet in balans.');
echo "PASS: gedeelde paginaopmaak, Amarante en vier gedeelde sidebartabs.\n";
