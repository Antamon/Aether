<?php
declare(strict_types=1);

require_once __DIR__ . '/schema_export_comparison.php';

function assertSchema(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php production_schema_alignment_test.php production.sql development.sql\n");
    exit(2);
}

$production = schemaFromDump($argv[1]);
$development = schemaFromDump($argv[2]);
$common = array_intersect(array_keys($production), array_keys($development));
assertSchema(count($common) === 37, 'Onverwacht aantal gedeelde tabellen.');
assertSchema(count($production) === 67 && count($development) === 39, 'Exportbasis is veranderd.');
assertSchema(count(array_diff(array_keys($production), array_keys($development))) === 30,
    'Onverwacht aantal te behouden oude productietabellen.');
assertSchema(
    array_values(array_diff(array_keys($development), array_keys($production))) === ['tblApiIdempotency', 'tblSchemaMigration'],
    'Onverwachte dev-only tabel.'
);
$alterDifferences = [];
foreach ($common as $table) {
    assertSchema($production[$table]['columns'] === $development[$table]['columns'], "Kolomverschil in $table.");
    assertSchema($production[$table]['options'] === $development[$table]['options'], "Tabeloptieverschil in $table.");
    $normalise = static fn(array $alters): array => array_map(
        static fn(string $alter): string => preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=N', $alter),
        $alters
    );
    if ($normalise($production[$table]['alter']) !== $normalise($development[$table]['alter'])) {
        $alterDifferences[] = $table;
    }
}
sort($alterDifferences);
assertSchema($alterDifferences === ['tblCharacterSpecialisation', 'tblLinkCharacterSkill', 'tblLinkEventUser'],
    'Onverwacht verschil in indexen, foreign keys of auto-incrementdefinities.');

$sql = file_get_contents(__DIR__ . '/../sql/migrations/production_align_to_dev_2026_09_24.sql');
assertSchema($sql !== false, 'SQL-bundel ontbreekt.');
assertSchema(str_contains($sql, "DATABASE() = 'oneiros_be_aether'"), 'Productiedatabaseguard ontbreekt.');
foreach ([
    'tblSchemaMigration', 'tblApiIdempotency',
    'uq_tblLinkCharacterSkill_character_skill',
    'uq_tblCharacterSpecialisation_character_skill_specialisation',
    'uq_tblLinkEventUser_event_user', 'idx_tblLinkEventUser_user_event',
    '0001_create_schema_migration_registry', '0002_unique_character_skill',
    '0003_unique_character_specialisation', '0004_create_api_idempotency',
    '0005_unique_event_user',
] as $required) {
    assertSchema(str_contains($sql, $required), "Structuur of migratie ontbreekt: $required.");
}
foreach (['@aether_duplicate_skill', '@aether_null_skill', '@aether_duplicate_spec',
    '@aether_null_spec', '@aether_invalid_spec', '@aether_duplicate_event_user',
    '@aether_null_event_user'] as $blocker) {
    assertSchema(str_contains($sql, $blocker), "Preflight ontbreekt: $blocker.");
    assertSchema(strpos($sql, $blocker) < strpos($sql, "'CREATE TABLE IF NOT EXISTS tblSchemaMigration"),
        "Preflight staat na eerste DDL: $blocker.");
}
assertSchema(substr_count($sql, 'START TRANSACTION;') === 4, 'Vier verificatietransacties verwacht.');
assertSchema(substr_count($sql, "\nROLLBACK;") === 4, 'Elke verificatietransactie moet terugrollen.');
assertSchema(substr_count($sql, 'PREPARE aether_step FROM @aether_sql;') >= 10, 'DDL- en verificatieguards ontbreken.');
$statements = preg_replace('/^--.*$/m', '', $sql);
assertSchema(!preg_match('/\b(?:DELETE|TRUNCATE|DROP TABLE|UPDATE\s+tbl(?:Character|Link|Event|Company|Skill))\b/i', $statements),
    'Destructieve domeinoperatie gevonden.');
assertSchema(!preg_match('/\b(?:SOURCE|information_schema|CREATE PROCEDURE)\b/i', $statements),
    'Niet ondersteunde phpMyAdmin/one.com-techniek gevonden.');
foreach (array_diff(array_keys($production), array_keys($development)) as $legacyTable) {
    assertSchema(!str_contains($sql, $legacyTable), "Oude productietabel wordt genoemd: $legacyTable.");
}
foreach (['0002', '0003', '0004', '0005'] as $number) {
    assertSchema(strpos($sql, "SET @aether_probe_$number :=") < strpos($sql, "SELECT '" . $number . '_'),
        "Migratie $number registreert voor verificatie.");
}

echo "PASS: 37 gedeelde tabellen met gelijke kolommen; alleen 2 nieuwe tabellen en 3 indexgroepen verwacht.\n";
echo "PASS: preflight, conditionele DDL, rollbackprobes, registratievolgorde en behoud oude tabellen.\n";
