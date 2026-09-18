<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$failures = [];

function assertRouteCoverage(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function routeContents(string $projectRoot, string $relativePath): string
{
    $contents = file_get_contents($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($contents === false) {
        throw new RuntimeException("Kon {$relativePath} niet lezen.");
    }

    return $contents;
}

$writeRoutes = [
    'api/admin/deleteActionUse.php',
    'api/admin/deleteKnowledgeUnlock.php',
    'api/admin/newSkill.php',
    'api/admin/saveKnowledgeVisibility.php',
    'api/admin/saveSkill.php',
    'api/admin/saveSkillType.php',
    'api/admin/updateActionUse.php',
    'api/characters/AddNewSkill.php',
    'api/characters/addCharacterLanguage.php',
    'api/characters/addSkillSpecialisation.php',
    'api/characters/buyCompanyShare.php',
    'api/characters/deleteBankTransaction.php',
    'api/characters/deleteCharacter.php',
    'api/characters/deleteCharacterEconomySnapshot.php',
    'api/characters/deleteCharacterLanguage.php',
    'api/characters/deleteCharacterPortrait.php',
    'api/characters/deleteCharacterTie.php',
    'api/characters/deleteSkillSpecialisation.php',
    'api/characters/newCharacter.php',
    'api/characters/revealCharacterActionKnowledge.php',
    'api/characters/saveBankTransfer.php',
    'api/characters/saveCharacterDiary.php',
    'api/characters/saveCharacterEconomySnapshot.php',
    'api/characters/saveCharacterSection.php',
    'api/characters/saveCharacterSecuritiesPortfolio.php',
    'api/characters/saveCharacterTie.php',
    'api/characters/saveCompanyShare.php',
    'api/characters/updateCharacter.php',
    'api/characters/updateSkill.php',
    'api/characters/updateTrait.php',
    'api/characters/uploadCharacterPortrait.php',
    'api/characters/useCharacterSkillAction.php',
    'api/companies/deleteCompanyLogo.php',
    'api/companies/newCompany.php',
    'api/companies/saveCompanyPersonnel.php',
    'api/companies/saveCompanySnapshot.php',
    'api/companies/updateCompany.php',
    'api/companies/uploadCompanyLogo.php',
    'api/events/newEvent.php',
    'api/events/updateEvent.php',
    'api/events/updateParticipation.php',
];

$authPattern = '/(?:aetherRequireAuthenticatedUser|aetherRequirePrivilegedUser|requirePrivilegedAdminAccess|requireAdministratorAccess|requirePrivilegedCompanyAccess)\s*\(/';
$csrfPattern = '/(?:aetherRequireCsrfToken\s*\(|requirePrivilegedAdminAccess\s*\(\s*\$pdo\s*,\s*true|requireAdministratorAccess\s*\(\s*\$pdo\s*,\s*true|requirePrivilegedCompanyAccess\s*\(\s*\$pdo\s*,\s*true)/s';

foreach ($writeRoutes as $route) {
    $contents = routeContents($projectRoot, $route);
    assertRouteCoverage((bool) preg_match($authPattern, $contents), "Schrijfroute zonder zichtbare authenticatiecontrole: {$route}");
    assertRouteCoverage((bool) preg_match($csrfPattern, $contents), "Schrijfroute zonder zichtbare CSRF-controle: {$route}");
}

$characterWriteObjectChecks = [
    'api/characters/AddNewSkill.php' => 'aetherRequireCharacterAccess',
    'api/characters/addCharacterLanguage.php' => 'canCurrentUserManageCharacterLanguages',
    'api/characters/addSkillSpecialisation.php' => 'aetherRequireCharacterAccess',
    'api/characters/buyCompanyShare.php' => 'canIncreaseCompanyShareRank',
    'api/characters/deleteCharacter.php' => 'aetherCanEditCharacter',
    'api/characters/deleteCharacterEconomySnapshot.php' => 'canManageCharacterEconomySnapshots',
    'api/characters/deleteCharacterLanguage.php' => 'canCurrentUserManageCharacterLanguages',
    'api/characters/deleteCharacterPortrait.php' => 'canManageCharacterPortrait',
    'api/characters/deleteCharacterTie.php' => 'aetherCanEditCharacter',
    'api/characters/deleteSkillSpecialisation.php' => 'aetherRequireCharacterAccess',
    'api/characters/revealCharacterActionKnowledge.php' => 'canViewCharacterActions',
    'api/characters/saveBankTransfer.php' => 'canTransferFromCharacter',
    'api/characters/saveCharacterDiary.php' => 'aetherCanEditCharacter',
    'api/characters/saveCharacterEconomySnapshot.php' => 'canManageCharacterEconomySnapshots',
    'api/characters/saveCharacterSection.php' => 'aetherRequireCharacterAccess',
    'api/characters/saveCharacterSecuritiesPortfolio.php' => 'canManageCharacterSecurities',
    'api/characters/saveCharacterTie.php' => 'aetherCanEditCharacter',
    'api/characters/saveCompanyShare.php' => 'canManageCompanyShareAssignments',
    'api/characters/updateCharacter.php' => 'aetherCanEditCharacter',
    'api/characters/updateSkill.php' => 'aetherRequireCharacterAccess',
    'api/characters/updateTrait.php' => 'aetherCanEditDraftCharacter',
    'api/characters/uploadCharacterPortrait.php' => 'canManageCharacterPortrait',
    'api/characters/useCharacterSkillAction.php' => 'canViewCharacterActions',
];
foreach ($characterWriteObjectChecks as $route => $objectAccessMarker) {
    $contents = routeContents($projectRoot, $route);
    assertRouteCoverage(
        str_contains($contents, $objectAccessMarker),
        "Schrijfroute zonder objectcontrole ({$objectAccessMarker}): {$route}"
    );
}

$characterReadRoutes = [
    'api/characters/getCharacter.php' => ['aetherCanViewCharacter'],
    'api/characters/getCharacterActionEvents.php' => ['canViewCharacterActions'],
    'api/characters/getCharacterActionKnowledgeTargets.php' => ['canViewCharacterActions'],
    'api/characters/getCharacterDiary.php' => ['aetherRequireCharacterAccess'],
    'api/characters/getCharacterLanguageOptions.php' => ['canCurrentUserManageCharacterLanguages'],
    'api/characters/getCharacterSections.php' => ['aetherRequireCharacterAccess'],
    'api/characters/getCharacterTies.php' => ['aetherRequireCharacterAccess'],
    'api/characters/getDisciplineList.php' => ['aetherRequireCharacterAccess'],
    'api/characters/getNewSkills.php' => ['aetherRequireCharacterAccess'],
    'api/characters/getSkillSpecialisations.php' => ['aetherRequireCharacterAccess'],
];
foreach ($characterReadRoutes as $route => $objectAccessMarkers) {
    $contents = routeContents($projectRoot, $route);
    assertRouteCoverage(str_contains($contents, 'aetherRequireAuthenticatedUser'), "Leesroute zonder authenticatiecontrole: {$route}");
    $hasObjectAccess = false;
    foreach ($objectAccessMarkers as $objectAccessMarker) {
        if (str_contains($contents, $objectAccessMarker)) {
            $hasObjectAccess = true;
            break;
        }
    }
    assertRouteCoverage($hasObjectAccess, "Leesroute zonder objectcontrole: {$route}");
}

$tieOptions = routeContents($projectRoot, 'api/characters/getCharacterTieOptions.php');
assertRouteCoverage(str_contains($tieOptions, 'aetherRequireAuthenticatedUser'), 'Relatie-opties zijn niet beperkt tot aangemelde gebruikers.');

$characterList = routeContents($projectRoot, 'api/characters/getCharacterList.php');
$newSkills = routeContents($projectRoot, 'api/characters/getNewSkills.php');
assertRouteCoverage(!preg_match('/\$postData\s*\[\s*[\'\"]role[\'\"]\s*\]/', $characterList), 'Personagelijst vertrouwt nog een browserrol.');
assertRouteCoverage(!preg_match('/\$postData\s*\[\s*[\'\"]role[\'\"]\s*\]/', $newSkills), 'Vaardighedenroute vertrouwt nog een browserrol.');

$newCharacter = routeContents($projectRoot, 'api/characters/newCharacter.php');
assertRouteCoverage(str_contains($newCharacter, "\$postData['createdBy'] = \$creatorId"), 'Nieuw personage forceert createdBy niet vanuit de serveridentiteit.');
assertRouteCoverage(str_contains($newCharacter, "\$postData['role']") === false, 'Nieuw personage leest een browserrol uit de payload.');

$updateCharacter = routeContents($projectRoot, 'api/characters/updateCharacter.php');
$characterService = routeContents($projectRoot, 'api/characters/characterService.php');
assertRouteCoverage(str_contains($characterService, 'aetherCanChangeCharacterAuthorityField'), 'Personage-update controleert bevoegdheidsvelden niet centraal.');
foreach (['idUser', 'type', 'state', 'createdBy', 'createdAt'] as $authorityField) {
    assertRouteCoverage(str_contains($characterService, "'{$authorityField}'"), "Personage-update noemt bevoegdheidsveld {$authorityField} niet in de servercontrole.");
}

$eventList = routeContents($projectRoot, 'api/events/getEventList.php');
assertRouteCoverage(str_contains($eventList, 'aetherIsPrivilegedRole'), 'Eventlijst beperkt een opgegeven gebruikers-ID niet op basis van de serverrol.');

$genericAccessControl = routeContents($projectRoot, 'api/auth/accessControl.php');
$characterAccess = routeContents($projectRoot, 'api/characters/characterAccess.php');
$movedCharacterAccessFunctions = [
    'aetherCanViewCharacter',
    'aetherCanEditCharacter',
    'aetherCanEditDraftCharacter',
    'aetherCanEditCharacterDiaryAchievements',
    'aetherFetchCharacterAccessRecord',
    'aetherRequireCharacterAccess',
    'aetherCanChangeCharacterAuthorityField',
    'aetherCanManageSkill',
    'aetherRequireSkillAccess',
];
foreach ($movedCharacterAccessFunctions as $functionName) {
    $definition = "function {$functionName}(";
    assertRouteCoverage(
        !str_contains($genericAccessControl, $definition),
        "Characterspecifieke functie staat nog in de generieke authlaag: {$functionName}"
    );
    assertRouteCoverage(
        str_contains($characterAccess, $definition),
        "Characterspecifieke functie ontbreekt in characterAccess.php: {$functionName}"
    );
}
assertRouteCoverage(
    str_contains($characterAccess, "require_once __DIR__ . '/../auth/accessControl.php'"),
    'Characterbeleid laadt de generieke authlaag niet.'
);
assertRouteCoverage(
    !str_contains($genericAccessControl, 'characterAccess.php'),
    'De generieke authlaag is afhankelijk geworden van characterbeleid.'
);
assertRouteCoverage(
    !str_contains($characterAccess, '$_POST') && !str_contains($characterAccess, '$_SESSION'),
    'Characterbeleid leest identiteit of rechten rechtstreeks uit browser- of sessiewaarden.'
);

$routesUsingMovedCharacterAccess = [
    'api/characters/AddNewSkill.php',
    'api/characters/addSkillSpecialisation.php',
    'api/characters/deleteCharacter.php',
    'api/characters/deleteCharacterTie.php',
    'api/characters/deleteSkillSpecialisation.php',
    'api/characters/getCharacter.php',
    'api/characters/getCharacterDiary.php',
    'api/characters/getCharacterSections.php',
    'api/characters/getCharacterTies.php',
    'api/characters/getDisciplineList.php',
    'api/characters/getNewSkills.php',
    'api/characters/getSkillSpecialisations.php',
    'api/characters/saveCharacterDiary.php',
    'api/characters/saveCharacterSection.php',
    'api/characters/saveCharacterTie.php',
    'api/characters/updateCharacter.php',
    'api/characters/updateSkill.php',
    'api/characters/updateTrait.php',
];
foreach ($routesUsingMovedCharacterAccess as $route) {
    assertRouteCoverage(
        str_contains(routeContents($projectRoot, $route), "require_once __DIR__ . '/characterAccess.php'"),
        "Characterroute laadt characterAccess.php niet rechtstreeks: {$route}"
    );
}

$mainFunctions = routeContents($projectRoot, 'js/mainFunctions.js');
$characterFunctions = routeContents($projectRoot, 'js/characterFunctions.js');
$companyFunctions = routeContents($projectRoot, 'js/companyFunctions.js');
assertRouteCoverage(str_contains($mainFunctions, 'X-CSRF-Token'), 'Algemene JSON-schrijfverzoeken sturen geen CSRF-header.');
assertRouteCoverage(str_contains($characterFunctions, 'X-CSRF-Token'), 'Upload van een personageportret stuurt geen CSRF-header.');
assertRouteCoverage(str_contains($companyFunctions, 'X-CSRF-Token'), 'Upload van een bedrijfslogo stuurt geen CSRF-header.');

$sessionBootstrap = routeContents($projectRoot, 'sessionUserBootstrap.php');
$tokenHandler = routeContents($projectRoot, 'tokenhandler.php');
assertRouteCoverage(str_contains($sessionBootstrap, 'session_regenerate_id(true)'), 'WordPress-login vernieuwt het PHP-sessie-ID niet.');
assertRouteCoverage(str_contains($tokenHandler, 'http_response_code(410)'), 'Ongebruikte OIDC-callback is niet fail-closed uitgeschakeld.');
assertRouteCoverage(!str_contains($tokenHandler, "\$_SESSION['user']"), 'Uitgeschakelde OIDC-callback kan nog een gebruiker in de sessie zetten.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Access-control route coverage tests passed.\n";
