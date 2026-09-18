<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/characters/characterRequestValidation.php';

function assertCharacterValidation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param callable(): void $callback */
function expectCharacterValidationError(callable $callback, string $expectedFragment): void
{
    try {
        $callback();
    } catch (CharacterRequestValidationException $e) {
        $combined = implode(' ', $e->getValidationErrors());
        assertCharacterValidation(
            str_contains($combined, $expectedFragment),
            "Validatiefout bevat niet '{$expectedFragment}': {$combined}"
        );
        return;
    }
    throw new RuntimeException("Verwachte validatiefout bleef uit: {$expectedFragment}");
}

$validCharacter = aetherValidateCharacterRequest('newCharacter', [
    'idUser' => '12',
    'type' => 'player',
    'state' => 'draft',
    'firstName' => '  Ada  ',
    'lastName' => '  Lovelace ',
    'class' => 'upper class',
    'birthDate' => '1900-01-01',
]);
assertCharacterValidation($validCharacter['idUser'] === 12, 'Integer-string wordt niet genormaliseerd.');
assertCharacterValidation($validCharacter['firstName'] === 'Ada', 'Tekst wordt niet getrimd.');
assertCharacterValidation(
    is_subclass_of(CharacterRequestValidationException::class, AetherValidationException::class),
    'De tijdelijke characterexception bouwt niet voort op de generieke validatie-exception.'
);

$_POST = [
    'type' => 'player',
    'firstName' => 'Grace',
    'lastName' => 'Hopper',
    'class' => 'middle class',
];
$legacyFormCharacter = aetherReadCharacterJsonRequest('newCharacter');
assertCharacterValidation(
    $legacyFormCharacter['firstName'] === 'Grace',
    'De tijdelijke characterfacade behoudt de bestaande formulierfallback niet.'
);
$_POST = [];

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('newCharacter', [
        'type' => 'player', 'lastName' => 'Lovelace', 'class' => 'upper class',
    ]),
    'firstName is verplicht'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('newCharacter', [
        'type' => 'player', 'firstName' => 'A', 'lastName' => 'Lovelace', 'class' => 'upper class',
    ]),
    'firstName is te kort'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('newCharacter', [
        'type' => 'player', 'firstName' => 'Ada', 'lastName' => 'Lovelace', 'class' => 'aristocracy',
    ]),
    'class bevat geen toegestane waarde'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('getCharacter', ['id' => ['1']]),
    'geheel getal'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('updateCharacter', [
        'id' => 1, 'firstName' => str_repeat('a', 41),
    ]),
    'te lang'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('updateCharacter', [
        'id' => 1, 'unknownField' => 'waarde',
    ]),
    'Onverwacht veld: unknownField'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('updateCharacter', [
        'id' => 1, 'firstName` = \'gehackt\' WHERE 1=1 --' => 'waarde',
    ]),
    'Onverwacht veld'
);

expectCharacterValidationError(
    fn() => aetherValidateCharacterRequest('saveCharacterSecuritiesPortfolio', [
        'action' => 'deposit', 'idCharacter' => 1, 'amount' => 10, 'idSnapshot' => 9,
    ]),
    'Onverwacht veld: idSnapshot'
);

$xss = '<img src=x onerror=alert(1)><script>alert(2)</script>';
$validatedXss = aetherValidateCharacterRequest('saveCharacterSection', [
    'idCharacter' => 1,
    'section' => 'knowledge',
    'content' => $xss,
]);
assertCharacterValidation(
    $validatedXss['content'] === $xss,
    'Platte tekst moet exact als tekst bewaard blijven; sanitization hoort bij HTML, output-encoding bij weergave.'
);

$routes = [
    'AddNewSkill', 'addCharacterLanguage', 'addSkillSpecialisation', 'buyCompanyShare',
    'deleteBankTransaction', 'deleteCharacter', 'deleteCharacterEconomySnapshot',
    'deleteCharacterLanguage', 'deleteCharacterPortrait', 'deleteCharacterTie',
    'deleteSkillSpecialisation', 'getCharacter', 'getCharacterActionEvents',
    'getCharacterActionKnowledgeTargets', 'getCharacterDiary', 'getCharacterLanguageOptions',
    'getCharacterList', 'getCharacterSections', 'getCharacterTies', 'getDisciplineList',
    'getNewSkills', 'getSkillSpecialisations', 'newCharacter',
    'revealCharacterActionKnowledge', 'saveBankTransfer', 'saveCharacterDiary',
    'saveCharacterEconomySnapshot', 'saveCharacterSection',
    'saveCharacterSecuritiesPortfolio', 'saveCharacterTie', 'saveCompanyShare',
    'updateCharacter', 'updateSkill', 'updateTrait', 'useCharacterSkillAction',
];

$projectRoot = dirname(__DIR__);
$directSchemaRoutes = [
    'AddNewSkill', 'addSkillSpecialisation', 'deleteCharacterLanguage', 'deleteSkillSpecialisation',
    'deleteCharacterTie', 'getCharacter', 'getCharacterDiary', 'getCharacterLanguageOptions',
    'getCharacterActionEvents', 'getCharacterActionKnowledgeTargets', 'getCharacterList',
    'getCharacterSections', 'getCharacterTies', 'getDisciplineList',
    'getNewSkills', 'getSkillSpecialisations', 'newCharacter', 'saveCharacterSection',
    'revealCharacterActionKnowledge', 'saveCharacterTie', 'updateCharacter', 'updateSkill',
    'useCharacterSkillAction',
];
foreach ($routes as $route) {
    assertCharacterValidation(
        is_array(aetherCharacterRequestSchema($route)),
        "Characterschema ontbreekt voor route: {$route}"
    );
    $path = $projectRoot . '/api/characters/' . $route . '.php';
    $contents = file_get_contents($path);
    assertCharacterValidation($contents !== false, "Route kon niet gelezen worden: {$route}");
    if (in_array($route, $directSchemaRoutes, true)) {
        assertCharacterValidation(
            str_contains($contents, 'aetherReadJsonObject()')
                && str_contains($contents, "aetherCharacterRequestSchema('{$route}'")
                && str_contains($contents, 'aetherValidateInput('),
            "Route {$route} gebruikt de expliciete JSON-lezer en het characterschema niet."
        );
    } else {
        assertCharacterValidation(
            str_contains($contents, "aetherReadCharacterJsonRequest('{$route}')"),
            "Route {$route} gebruikt het expliciete invoerschema niet."
        );
    }
}

$upload = file_get_contents($projectRoot . '/api/characters/uploadCharacterPortrait.php');
assertCharacterValidation(
    $upload !== false && str_contains($upload, "aetherValidateCharacterRequestOrFail('uploadCharacterPortrait'"),
    'Portretupload valideert de multipartvelden niet.'
);
assertCharacterValidation(
    is_array(aetherCharacterRequestSchema('uploadCharacterPortrait')),
    'Characterschema ontbreekt voor de portretupload.'
);

$tieOptions = file_get_contents($projectRoot . '/api/characters/getCharacterTieOptions.php');
assertCharacterValidation(
    $tieOptions !== false
        && str_contains($tieOptions, 'aetherReadFormFields()')
        && str_contains($tieOptions, "aetherCharacterRequestSchema('getCharacterTieOptions'")
        && str_contains($tieOptions, 'aetherValidateInput('),
    'De parameterloze tie-optieroute wijst onverwachte parameters niet af.'
);
assertCharacterValidation(
    aetherCharacterRequestSchema('getCharacterTieOptions') === [],
    'Het lege schema voor characterrelatie-opties is niet behouden.'
);

$newCharacterRoute = file_get_contents($projectRoot . '/api/characters/newCharacter.php');
$updateCharacterRoute = file_get_contents($projectRoot . '/api/characters/updateCharacter.php');
$characterRepository = file_get_contents($projectRoot . '/api/characters/characterRepository.php');
assertCharacterValidation(
    $newCharacterRoute !== false && !str_contains($newCharacterRoute, 'array_keys($postData)'),
    'Nieuw personage bouwt nog SQL-kolommen uit browserinput.'
);
assertCharacterValidation(
    $updateCharacterRoute !== false
    && $characterRepository !== false
    && str_contains($characterRepository, 'function aetherCharacterUpdateColumnMap(')
    && str_contains($characterRepository, "'firstName' => '`firstName`'")
    && !str_contains($characterRepository, '$setParts[] = "$col = :$col"'),
    'Personage-update gebruikt geen vaste kolommapping.'
);

$safeDisplayAssertions = [
    // Diary gossip stays plain text; the six documented rich-text fields have
    // separate sanitizer and rendering coverage in character_rich_text_test.php.
    'js/diaryCharacter.js' => ['gossipInputs.gossip1.view.textContent', 'view.textContent = entry[g.key]'],
    'js/navCharacter.js' => ['nameParticipant").textContent'],
    'js/skillsCharacter.js' => ['newOption.textContent', 'headerBtn.textContent'],
    'js/languageCharacter.js' => ['name.textContent = language.name'],
    'js/formCharacter.js' => ['if (element) element.textContent = value'],
    'js/passportCharacter.js' => ['passportNumber.textContent', 'return String(value ?? "")'],
];
foreach ($safeDisplayAssertions as $relativePath => $needles) {
    $contents = file_get_contents($projectRoot . '/' . $relativePath);
    assertCharacterValidation($contents !== false, "JavaScriptbestand kon niet gelezen worden: {$relativePath}");
    foreach ($needles as $needle) {
        assertCharacterValidation(
            str_contains($contents, $needle),
            "Veilige tekstweergave ontbreekt in {$relativePath}: {$needle}"
        );
    }
}

echo "character input validation tests: OK\n";
