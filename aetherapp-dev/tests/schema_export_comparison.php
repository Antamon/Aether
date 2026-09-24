<?php
declare(strict_types=1);

// Read-only schema inventory for phpMyAdmin exports. Never prints INSERT data.

function schemaFromDump(string $path): array
{
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('Export kon niet worden gelezen.');
    $tables = [];
    preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) (ENGINE=[^;]+);/s', $sql, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $columns = [];
        foreach (preg_split('/\R/', $match[2]) as $line) {
            if (preg_match('/^\s*`([^`]+)`\s+(.+?)(?:,)?\s*$/', $line, $column)) {
                $columns[$column[1]] = rtrim(trim($column[2]), ',');
            }
        }
        $tables[$match[1]] = ['columns'=>$columns, 'options'=>preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=N', $match[3]), 'alter'=>[]];
    }
    preg_match_all('/ALTER TABLE `([^`]+)`\s+(.*?);/s', $sql, $alters, PREG_SET_ORDER);
    foreach ($alters as $alter) {
        if (isset($tables[$alter[1]])) $tables[$alter[1]]['alter'][] = preg_replace('/\s+/', ' ', trim($alter[2]));
    }
    return $tables;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
if ($argc !== 3) { fwrite(STDERR, "Usage: php schema_export_comparison.php production.sql development.sql\n"); exit(2); }
$production = schemaFromDump($argv[1]);
$development = schemaFromDump($argv[2]);
echo 'TABLES production=' . count($production) . ' dev=' . count($development) . PHP_EOL;
foreach ($development as $table=>$dev) {
    if (!isset($production[$table])) { echo "ADD_TABLE $table\n"; continue; }
    $prod = $production[$table];
    foreach ($dev['columns'] as $name=>$definition) {
        if (!isset($prod['columns'][$name])) echo "ADD_COLUMN $table.$name\n";
        elseif ($prod['columns'][$name] !== $definition) echo "CHANGE_COLUMN $table.$name\n";
    }
    foreach ($prod['columns'] as $name=>$definition) {
        if (!isset($dev['columns'][$name])) echo "PROD_ONLY_COLUMN $table.$name\n";
    }
    if ($prod['options'] !== $dev['options']) echo "TABLE_OPTIONS_DIFF $table\n";
    $normaliseAlter = static fn(array $items): array => array_map(
        static fn(string $item): string => preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=N', $item),
        $items
    );
    if ($normaliseAlter($prod['alter']) !== $normaliseAlter($dev['alter'])) {
        echo "ALTER_STRUCTURAL_DIFF $table\n";
        foreach (['DEV'=>$dev,'PROD'=>$prod] as $side=>$definition) {
            $ddl = implode(' ', $definition['alter']);
            preg_match_all('/ADD (UNIQUE )?KEY `([^`]+)` \(([^)]+)\)/', $ddl, $keys, PREG_SET_ORDER);
            foreach ($keys as $key) echo " $side KEY " . ($key[1] !== '' ? 'UNIQUE ' : '') . $key[2] . ' (' . $key[3] . ")\n";
            preg_match_all('/ADD CONSTRAINT `([^`]+)` FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)/', $ddl, $fks, PREG_SET_ORDER);
            foreach ($fks as $fk) echo " $side FK $fk[1] ($fk[2]) -> $fk[3] ($fk[4])\n";
        }
    }
}
foreach ($production as $table=>$prod) if (!isset($development[$table])) echo "PROD_ONLY_TABLE $table\n";
