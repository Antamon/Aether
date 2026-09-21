<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function migrationConcurrencyAssert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function migrationSql(string $root, string $phase, string $code): string
{
    $path = $root . '/sql/migrations/' . $phase . '/' . $code . '.sql';
    migrationConcurrencyAssert(is_file($path), "Ontbrekend migratiebestand: {$phase}/{$code}.sql");
    return is_file($path) ? (string) file_get_contents($path) : '';
}

function sqlWithoutComments(string $sql): string
{
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;
    return preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
}

function migrationPosition(string $sql, string $needle, string $message): int
{
    $position = strpos($sql, $needle);
    migrationConcurrencyAssert($position !== false, $message);
    return $position === false ? PHP_INT_MAX : $position;
}

/** @return list<array<int, string>> */
function exportRows(string $sql, string $table, string $pattern): array
{
    $rows = [];
    $inside = false;
    $needle = 'INSERT INTO `' . $table . '`';
    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        if (str_starts_with($line, $needle)) {
            $inside = true;
            continue;
        }
        if (!$inside) {
            continue;
        }
        if (preg_match($pattern, trim($line), $matches) === 1) {
            $rows[] = $matches;
        }
        if (str_ends_with(rtrim($line), ';')) {
            $inside = false;
        }
    }
    return $rows;
}

$codes = [
    '0001_create_schema_migration_registry',
    '0002_unique_character_skill',
    '0003_unique_character_specialisation',
];
$phases = ['preflight', 'apply', 'verify', 'rollback'];

foreach ($phases as $phase) {
    $files = glob($root . '/sql/migrations/' . $phase . '/*.sql') ?: [];
    sort($files);
    migrationConcurrencyAssert(
        array_map(static fn(string $path): string => basename($path, '.sql'), $files) === $codes,
        "Migratiebestanden in {$phase} zijn niet compleet of niet opeenvolgend genummerd."
    );
}

foreach ($codes as $code) {
    $preflight = sqlWithoutComments(migrationSql($root, 'preflight', $code));
    migrationConcurrencyAssert(
        preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|CALL)\b/i', $preflight) !== 1,
        "Preflight {$code} bevat een schrijfbewerking."
    );
    $verify = sqlWithoutComments(migrationSql($root, 'verify', $code));
    migrationConcurrencyAssert(
        preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|CALL)\b/i', $verify) !== 1,
        "Verify {$code} bevat een schrijfbewerking."
    );
}

$activeBundlePath = $root . '/sql/migrations/run_0001_to_0003_phpmyadmin.sql';
migrationConcurrencyAssert(is_file($activeBundlePath), 'Het actieve phpMyAdmin-bestand ontbreekt.');
$activeBundle = is_file($activeBundlePath) ? (string) file_get_contents($activeBundlePath) : '';
$activeBundleWithoutComments = sqlWithoutComments($activeBundle);
migrationConcurrencyAssert(str_contains($activeBundle, 'GEBLOKKEERD VOOR ONE.COM'), 'Het incompatibele one.com-bundelbestand is niet duidelijk geblokkeerd.');
migrationConcurrencyAssert(preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DELETE|REPLACE|DROP|TRUNCATE)\b/i', $activeBundleWithoutComments) !== 1,
    'Het geblokkeerde one.com-bestand kan nog schema of data wijzigen.');

$inspectionPath = $root . '/sql/migrations/inspect_0001_to_0003_onecom_readonly.sql';
migrationConcurrencyAssert(is_file($inspectionPath), 'Het alleen-lezen one.com-inspectiebestand ontbreekt.');
$inspection = is_file($inspectionPath) ? (string) file_get_contents($inspectionPath) : '';
$inspectionWithoutComments = sqlWithoutComments($inspection);
migrationConcurrencyAssert(!str_contains(strtolower($inspectionWithoutComments), 'information_schema'), 'Het one.com-inspectiebestand gebruikt toch information_schema.');
migrationConcurrencyAssert(preg_match('/\b(CREATE|ALTER|INSERT|UPDATE|DELETE|REPLACE|DROP|TRUNCATE|CALL)\b/i', $inspectionWithoutComments) !== 1,
    'Het one.com-inspectiebestand bevat een schrijfbewerking.');
migrationConcurrencyAssert(str_contains($inspection, 'SHOW INDEX FROM `tblLinkCharacterSkill`')
    && str_contains($inspection, 'SHOW INDEX FROM `tblCharacterSpecialisation`'),
    'Het one.com-inspectiebestand toont niet beide indexsets.');

$bundlePath = $root . '/sql/migrations/run_0001_to_0003_requires_information_schema.sql.reference';
migrationConcurrencyAssert(is_file($bundlePath), 'De geblokkeerde bundelimplementatie is niet als referentie bewaard.');
$bundle = is_file($bundlePath) ? (string) file_get_contents($bundlePath) : '';
$bundleWithoutComments = sqlWithoutComments($bundle);

migrationConcurrencyAssert(!preg_match('/\bSOURCE\b/i', $bundleWithoutComments), 'Het bundelbestand gebruikt een niet-portabel SOURCE-commando.');
migrationConcurrencyAssert(str_contains($bundleWithoutComments, 'SET FOREIGN_KEY_CHECKS = 1;'), 'Het bundelbestand herstelt foreign-keycontroles niet expliciet met een phpMyAdmin-veilige waarde.');
migrationConcurrencyAssert(str_contains($bundle, 'SET @aether_target_schema = DATABASE();'), 'Het bundelbestand bewaart de geselecteerde doeldatabase niet.');
migrationConcurrencyAssert(!str_contains($bundle, 'TABLE_SCHEMA = DATABASE()'), 'Een metadatacontrole gebruikt nog een veranderlijke phpMyAdmin-databasecontext.');
migrationConcurrencyAssert(str_contains($bundle, 'BEGIN NOT ATOMIC'), 'Het bundelbestand gebruikt geen MariaDB anonymous compound block.');
migrationConcurrencyAssert(str_contains($bundle, "SIGNAL SQLSTATE '45000'"), 'Het bundelbestand stopt blokkerende preflightfouten niet met SIGNAL.');
migrationConcurrencyAssert(str_contains($bundle, 'CREATE TABLE IF NOT EXISTS `tblSchemaMigration`'), 'Het migratieregister wordt niet herhaalbaar aangemaakt.');
migrationConcurrencyAssert(!preg_match('/\b(DELETE|UPDATE|REPLACE|TRUNCATE)\b/i', $bundleWithoutComments), 'Het bundelbestand bevat automatische datawijziging of opschoning.');
migrationConcurrencyAssert(substr_count($bundle, 'ALTER TABLE `tblLinkCharacterSkill`') === 1, 'De character-skillindex wordt niet via exact één conditionele ALTER toegevoegd.');
migrationConcurrencyAssert(substr_count($bundle, 'ALTER TABLE `tblCharacterSpecialisation`') === 1, 'De character-specialisatie-index wordt niet via exact één conditionele ALTER toegevoegd.');

$firstIndexAlter = migrationPosition($bundle, 'ALTER TABLE `tblLinkCharacterSkill`', 'De eerste relevante ALTER TABLE ontbreekt.');
foreach ([
    'duplicateCharacterSkills' => 'De skillduplicatecontrole ontbreekt.',
    'idCharacter IS NULL OR idSkill IS NULL' => 'De skill-NULL-controle ontbreekt.',
    'duplicateCharacterSpecialisations' => 'De specialisatieduplicatecontrole ontbreekt.',
    'idCharacter IS NULL OR idSkill IS NULL OR idSkillSpecialisation IS NULL' => 'De specialisatie-NULL-controle ontbreekt.',
    'verweesde character-specialisatierecords gevonden' => 'De specialisatiewezencontrole ontbreekt.',
    'een specialisatie hoort bij een andere skill' => 'De verkeerde-skillcontrole ontbreekt.',
    'de bedoelde indexnaam heeft een verkeerde definitie' => 'De controle op een fout gedefinieerde bedoelde indexnaam ontbreekt.',
] as $needle => $message) {
    migrationConcurrencyAssert(migrationPosition($bundle, $needle, $message) < $firstIndexAlter, $message . ' De controle staat niet vóór de eerste ALTER TABLE.');
}
migrationConcurrencyAssert(substr_count($bundle, 'de bedoelde indexnaam heeft een verkeerde definitie') === 2, 'Niet beide bedoelde indexnamen worden op een verkeerde definitie gecontroleerd.');

$skillEquivalentCheck = migrationPosition($bundle, 'equivalentCharacterSkillIndexes', 'De equivalente character-skillindexcontrole ontbreekt.');
$skillConditional = migrationPosition($bundle, "IF v_equivalent_indexes = 0 THEN\n    ALTER TABLE `tblLinkCharacterSkill`", 'De character-skillindex wordt niet conditioneel toegevoegd.');
migrationConcurrencyAssert($skillEquivalentCheck < $skillConditional, 'De equivalente character-skillindex wordt pas na ALTER gecontroleerd.');
$specialisationEquivalentCheck = migrationPosition($bundle, 'equivalentCharacterSpecialisationIndexes', 'De equivalente specialisatie-indexcontrole ontbreekt.');
$specialisationConditional = migrationPosition($bundle, "IF v_equivalent_indexes = 0 THEN\n    ALTER TABLE `tblCharacterSpecialisation`", 'De character-specialisatie-index wordt niet conditioneel toegevoegd.');
migrationConcurrencyAssert($specialisationEquivalentCheck < $specialisationConditional, 'De equivalente specialisatie-index wordt pas na ALTER gecontroleerd.');

$skillVerification = migrationPosition($bundle, 'verifiedCharacterSkillIndexes', 'De verificatie van de character-skillindex ontbreekt.');
$skillRegistration = migrationPosition($bundle, "SELECT '0002_unique_character_skill'", 'Registratie van migratie 0002 ontbreekt.');
migrationConcurrencyAssert($skillVerification < $skillRegistration, 'Migratie 0002 wordt vóór verificatie geregistreerd.');
$specialisationVerification = migrationPosition($bundle, 'verifiedCharacterSpecialisationIndexes', 'De verificatie van de specialisatie-index ontbreekt.');
$specialisationRegistration = migrationPosition($bundle, "SELECT '0003_unique_character_specialisation'", 'Registratie van migratie 0003 ontbreekt.');
migrationConcurrencyAssert($specialisationVerification < $specialisationRegistration, 'Migratie 0003 wordt vóór verificatie geregistreerd.');

migrationConcurrencyAssert(substr_count($bundle, 'WHERE NOT EXISTS (') >= 3, 'Niet alle migratieregistraties zijn herhaalbaar gemaakt.');
migrationConcurrencyAssert(str_contains($bundle, 'Migraties 0001-0003 zijn geslaagd.'), 'De duidelijke succesmelding ontbreekt.');
migrationConcurrencyAssert(str_contains($bundle, 'characterSkillOrphanWarning'), 'De afzonderlijke waarschuwing voor verweesde skilllinks ontbreekt.');
migrationConcurrencyAssert(str_contains($bundle, 'integrityCheckResult'), 'De samenvattende bevestiging van lege integriteitscontroles ontbreekt.');
migrationConcurrencyAssert(str_contains($bundle, 'DELIMITER $$') && str_contains($bundle, 'DELIMITER ;'), 'De phpMyAdmin/MariaDB delimiters ontbreken.');
$compoundEnd = migrationPosition($bundle, 'END$$', 'Het MariaDB-compoundblok heeft geen duidelijk einde.');
$successOutput = migrationPosition($bundle, "SELECT 'Migraties 0001-0003 zijn geslaagd.'", 'De succesresultaatquery ontbreekt.');
migrationConcurrencyAssert($successOutput > $compoundEnd, 'Resultaatqueries staan nog in het compoundblok en kunnen phpMyAdmin uit synchronisatie brengen.');
$lastDomainOutput = migrationPosition($bundle, 'ORDER BY cs.id;', 'De laatste domeinintegriteitsquery ontbreekt.');
$informationSchemaOutput = strrpos($bundle, 'FROM information_schema.STATISTICS');
migrationConcurrencyAssert($informationSchemaOutput !== false && $informationSchemaOutput > $lastDomainOutput,
    'De information_schema-resultaatquery staat niet als laatste en kan de one.com-databasecontext verstoren.');
migrationConcurrencyAssert(str_contains($bundle, 'SET @aether_migrations_complete = 0;')
    && str_contains($bundle, 'SET @aether_migrations_complete = 1;'),
    'De resultaatqueries worden niet door een volledige succesvolle uitvoering afgeschermd.');

$registryApply = sqlWithoutComments(migrationSql($root, 'apply', $codes[0]));
migrationConcurrencyAssert(str_contains($registryApply, 'CREATE TABLE `tblSchemaMigration`'), 'Migratieregister wordt niet aangemaakt.');
migrationConcurrencyAssert(str_contains($registryApply, 'UNIQUE KEY `uq_tblSchemaMigration_code` (`migrationCode`)'), 'Migratiecode is niet uniek.');
migrationConcurrencyAssert(strpos($registryApply, 'CREATE TABLE') < strpos($registryApply, 'INSERT INTO'), 'Registermigratie wordt vóór tabelcreatie geregistreerd.');

$skillApply = sqlWithoutComments(migrationSql($root, 'apply', $codes[1]));
migrationConcurrencyAssert(str_contains($skillApply, 'ALTER TABLE `tblLinkCharacterSkill`'), 'Skillmigratie gebruikt de verkeerde tabel.');
migrationConcurrencyAssert(str_contains($skillApply, 'UNIQUE KEY `uq_tblLinkCharacterSkill_character_skill` (`idCharacter`, `idSkill`)'), 'Unieke characterskillindex ontbreekt of heeft verkeerde kolommen.');
migrationConcurrencyAssert(strpos($skillApply, 'ALTER TABLE') < strpos($skillApply, 'INSERT INTO `tblSchemaMigration`'), 'Skillmigratie wordt te vroeg geregistreerd.');

$specialisationApply = sqlWithoutComments(migrationSql($root, 'apply', $codes[2]));
migrationConcurrencyAssert(str_contains($specialisationApply, 'ALTER TABLE `tblCharacterSpecialisation`'), 'Specialisatiemigratie gebruikt de verkeerde tabel.');
migrationConcurrencyAssert(str_contains($specialisationApply, 'UNIQUE KEY `uq_tblCharacterSpecialisation_character_skill_specialisation`'), 'Unieke characterspecialisatie-index ontbreekt.');
migrationConcurrencyAssert(str_contains($specialisationApply, '(`idCharacter`, `idSkill`, `idSkillSpecialisation`)'), 'Specialisatie-index heeft verkeerde kolommen.');
migrationConcurrencyAssert(strpos($specialisationApply, 'ALTER TABLE') < strpos($specialisationApply, 'INSERT INTO `tblSchemaMigration`'), 'Specialisatiemigratie wordt te vroeg geregistreerd.');

foreach ([$skillApply, $specialisationApply] as $apply) {
    migrationConcurrencyAssert(
        preg_match('/\b(DELETE|UPDATE|REPLACE|TRUNCATE)\b/i', $apply) !== 1,
        'Een index-applybestand wijzigt of verwijdert bestaande gebruikersdata.'
    );
}

$skillPreflight = migrationSql($root, 'preflight', $codes[1]);
migrationConcurrencyAssert(str_contains($skillPreflight, 'HAVING COUNT(*) > 1'), 'Skillpreflight zoekt geen duplicaten.');
migrationConcurrencyAssert(str_contains($skillPreflight, 'missing_character') && str_contains($skillPreflight, 'missing_skill'), 'Skillpreflight zoekt geen weesrecords.');
$specialisationPreflight = migrationSql($root, 'preflight', $codes[2]);
migrationConcurrencyAssert(str_contains($specialisationPreflight, 'HAVING COUNT(*) > 1'), 'Specialisatiepreflight zoekt geen duplicaten.');
migrationConcurrencyAssert(str_contains($specialisationPreflight, 'specialisation_belongs_to_other_skill'), 'Specialisatiepreflight controleert de skillkoppeling niet.');

$skillRollback = sqlWithoutComments(migrationSql($root, 'rollback', $codes[1]));
$specialisationRollback = sqlWithoutComments(migrationSql($root, 'rollback', $codes[2]));
migrationConcurrencyAssert(str_contains($skillRollback, 'DROP INDEX `uq_tblLinkCharacterSkill_character_skill`'), 'Skillrollback verwijdert de nieuwe index niet expliciet.');
migrationConcurrencyAssert(str_contains($specialisationRollback, 'DROP INDEX `uq_tblCharacterSpecialisation_character_skill_specialisation`'), 'Specialisatierollback verwijdert de nieuwe index niet expliciet.');
foreach ([$skillRollback, $specialisationRollback] as $rollback) {
    $withoutRegistryDelete = preg_replace('/DELETE\s+FROM\s+`?tblSchemaMigration`?.*?;/is', '', $rollback) ?? $rollback;
    migrationConcurrencyAssert(preg_match('/\b(DELETE|UPDATE|TRUNCATE|REPLACE)\b/i', $withoutRegistryDelete) !== 1, 'Rollback wijzigt gebruikersdata.');
}

$export = (string) file_get_contents($root . '/sql/oneiros_be_aether.sql');
migrationConcurrencyAssert(str_contains($export, 'Server version: 10.11.18-MariaDB'), 'Verwachte MariaDB-versie ontbreekt in de export.');
migrationConcurrencyAssert(!str_contains($export, 'uq_tblLinkCharacterSkill_character_skill'), 'Export bevat onverwacht al de nieuwe skillindex.');
migrationConcurrencyAssert(!str_contains($export, 'uq_tblCharacterSpecialisation_character_skill_specialisation'), 'Export bevat onverwacht al de nieuwe specialisatie-index.');

$skillRows = exportRows($export, 'tblLinkCharacterSkill', '/^\((\d+),\s*(\d+),\s*(\d+),\s*(-?\d+)\)[,;]?$/');
$skillKeys = [];
foreach ($skillRows as $row) {
    $key = $row[2] . ':' . $row[3];
    $skillKeys[$key][] = (int) $row[1];
}
migrationConcurrencyAssert(array_filter($skillKeys, static fn(array $ids): bool => count($ids) > 1) === [], 'SQL-export bevat dubbele characterskillkoppelingen.');

$characterIds = [];
foreach (exportRows($export, 'tblCharacter', '/^\((\d+),/') as $row) {
    $characterIds[(int) $row[1]] = true;
}
$orphanSkillLinkIds = [];
foreach ($skillRows as $row) {
    if (!isset($characterIds[(int) $row[2]])) {
        $orphanSkillLinkIds[] = (int) $row[1];
    }
}
sort($orphanSkillLinkIds);
migrationConcurrencyAssert($orphanSkillLinkIds === [70, 71, 72, 508], 'Bekende verweesde skilllinks in de export wijzigden; herbeoordeel de preflight.');

$definitionSkills = [];
foreach (exportRows($export, 'tblSkillSpecialisation', '/^\((\d+),\s*(\d+),/') as $row) {
    $definitionSkills[(int) $row[1]] = (int) $row[2];
}
$specialisationKeys = [];
$wrongSkillLinks = [];
foreach (exportRows($export, 'tblCharacterSpecialisation', '/^\((\d+),\s*(\d+),\s*(\d+),\s*(\d+)\)[,;]?$/') as $row) {
    $specialisationKeys[$row[2] . ':' . $row[3] . ':' . $row[4]][] = (int) $row[1];
    if (($definitionSkills[(int) $row[4]] ?? null) !== (int) $row[3]) {
        $wrongSkillLinks[] = (int) $row[1];
    }
}
migrationConcurrencyAssert(array_filter($specialisationKeys, static fn(array $ids): bool => count($ids) > 1) === [], 'SQL-export bevat dubbele characterspecialisatielinks.');
migrationConcurrencyAssert($wrongSkillLinks === [], 'SQL-export bevat een specialisatie die bij een andere skill hoort.');

$skillRepository = (string) file_get_contents($root . '/api/characters/characterSkillRepository.php');
migrationConcurrencyAssert(substr_count($skillRepository, 'ON DUPLICATE KEY UPDATE id = id') >= 2, 'Skillinserts behandelen unieke races niet idempotent.');
$gossip = (string) file_get_contents($root . '/api/characters/gossipKnowledgeUtils.php');
migrationConcurrencyAssert(str_contains($gossip, 'attemptCount = attemptCount + 1'), 'Gossipattempt gebruikt geen atomaire increment.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Database migration and concurrency tests passed.\n";
