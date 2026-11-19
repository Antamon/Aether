<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/shared/contentLanguage.php';
function seedCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$sql = file_get_contents(__DIR__ . '/../sql/migrations/0028_content_translation_seed.sql');
$report = json_decode(file_get_contents(__DIR__ . '/../docs/content-translation-coverage.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE tblContentTranslation (sourceHash TEXT,targetLanguage TEXT,sourceLanguage TEXT,sourceText TEXT,translatedText TEXT,format TEXT,provider TEXT,status TEXT DEFAULT \'ready\',PRIMARY KEY(sourceHash,targetLanguage))');
$insert = $pdo->prepare('INSERT OR IGNORE INTO tblContentTranslation (sourceHash,targetLanguage,sourceLanguage,sourceText,translatedText,format,provider) VALUES (?,?,?,?,?,?,?)');
$rows = [];
foreach (explode("\n", $sql) as $line) {
    if (!str_starts_with($line, 'INSERT IGNORE INTO tblContentTranslation')) continue;
    preg_match_all('/CONVERT\(0x([0-9a-f]+) USING utf8mb4\)/', $line, $matches);
    $row = array_map('hex2bin', $matches[1]);
    seedCheck(count($row) === 7, 'Invalid seed row.');
    seedCheck(hash('sha256', $row[3]) === $row[0], 'Source hash mismatch.');
    seedCheck(in_array($row[1], ['nl','en'], true) && in_array($row[2], ['nl','en'], true), 'Unsupported seed locale.');
    seedCheck(in_array($row[5], ['plain','html'], true) && $row[4] !== '', 'Empty or untyped translation.');
    seedCheck(!preg_match('/AETHERTRANSLATIONPART\d+END/', $row[4]), 'HTML placeholder left in translation.');
    seedCheck(mb_check_encoding($row[3], 'UTF-8') && mb_check_encoding($row[4], 'UTF-8'), 'Invalid UTF-8.');
    $insert->execute($row); $rows[] = $row;
}
seedCheck(count($rows) === $report['translationRows'], 'Seed coverage report mismatch.');
seedCheck($report['translatedOrOriginalSources'] === $report['uniqueSources'], 'Initial import is incomplete.');
$before = (int)$pdo->query('SELECT COUNT(*) FROM tblContentTranslation')->fetchColumn();
// Reapplying the SQL must preserve both the row count and an editorial correction.
$pdo->exec("UPDATE tblContentTranslation SET translatedText='Editorial correction' WHERE sourceHash='".$rows[0][0]."' AND targetLanguage='".$rows[0][1]."'");
foreach ($rows as $row) $insert->execute($row);
seedCheck((int)$pdo->query('SELECT COUNT(*) FROM tblContentTranslation')->fetchColumn() === $before, 'Seed is not repeatable.');
$find = $pdo->prepare('SELECT translatedText FROM tblContentTranslation WHERE sourceHash=? AND targetLanguage=?');
$find->execute([$rows[0][0],$rows[0][1]]);
seedCheck($find->fetchColumn() === 'Editorial correction', 'Seed overwrote an editorial correction.');
foreach ([['academicus','en','scholar'],['Academic','nl','Academisch'],['Belgian','nl','Belgisch']] as [$source,$language,$expected]) {
    $find->execute([hash('sha256',$source),$language]);
    seedCheck($find->fetchColumn() === $expected, 'Missing game glossary/nationality translation.');
}
echo "PASS: {$before} seed rows, complete source coverage, hashes, UTF-8, supported languages, idempotence and preserved editorial corrections\n";
