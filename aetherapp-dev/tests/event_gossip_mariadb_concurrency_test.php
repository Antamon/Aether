<?php
declare(strict_types=1);
require_once __DIR__ . '/mariadb_disposable_guard.php';

$dsn = getenv('AETHER_TEST_MYSQL_DSN') ?: '';
$user = getenv('AETHER_TEST_MYSQL_USER') ?: '';
$password = getenv('AETHER_TEST_MYSQL_PASSWORD') ?: '';
if (!aetherMariaDbConcurrencyAllowed('AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS')) {
    echo "SKIP: echte MariaDB-eventconcurrencytest vereist expliciete testdatabaseconfiguratie.\n";
    exit(0);
}

if (($argv[1] ?? '') === 'worker') {
    [, , $mode, $flag, $table, $ready, $release] = $argv;
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    aetherAssertDisposableMariaDbConnection($pdo);
    $pdo->beginTransaction();
    $select = $pdo->query("SELECT attemptCount, unlockGossip1, unlockGossip2 FROM {$table} WHERE id=1 FOR UPDATE");
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if ($mode === 'first') {
        file_put_contents($ready, 'locked');
        while (!is_file($release)) usleep(10000);
    }
    $column = $flag === 'one' ? 'unlockGossip1' : 'unlockGossip2';
    $pdo->exec("UPDATE {$table} SET attemptCount = attemptCount + 1, {$column} = GREATEST({$column}, 1) WHERE id=1");
    $pdo->commit();
    echo json_encode($row, JSON_THROW_ON_ERROR);
    exit;
}

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
aetherAssertDisposableMariaDbConnection($pdo);

$suffix = bin2hex(random_bytes(5));
$table = 'tmp_aether_event_gossip_' . $suffix;
$ready = sys_get_temp_dir() . '/aether-event-ready-' . $suffix;
$release = sys_get_temp_dir() . '/aether-event-release-' . $suffix;
$failures = [];
try {
    $pdo->exec("CREATE TABLE {$table} (
        id INT PRIMARY KEY,
        attemptCount INT NOT NULL,
        unlockGossip1 TINYINT(1) NOT NULL,
        unlockGossip2 TINYINT(1) NOT NULL
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO {$table} (id, attemptCount, unlockGossip1, unlockGossip2) VALUES (1, 0, 0, 0)");
    $command = static function (string $mode, string $flag) use ($table, $ready, $release): array {
        return [PHP_BINARY, __FILE__, 'worker', $mode, $flag, $table, $ready, $release];
    };
    $process1 = proc_open($command('first', 'one'), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
    $deadline = microtime(true) + 5;
    while (!is_file($ready) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($ready)) throw new RuntimeException('De eerste worker verkreeg de lock niet tijdig.');
    $process2 = proc_open($command('second', 'two'), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);
    usleep(250000);
    file_put_contents($release, 'continue');
    $output1 = (string) stream_get_contents($pipes1[1]);
    $error1 = (string) stream_get_contents($pipes1[2]);
    $output2 = (string) stream_get_contents($pipes2[1]);
    $error2 = (string) stream_get_contents($pipes2[2]);
    foreach ($pipes1 as $pipe) fclose($pipe);
    foreach ($pipes2 as $pipe) fclose($pipe);
    $exit1 = proc_close($process1);
    $exit2 = proc_close($process2);
    $final = $pdo->query("SELECT attemptCount, unlockGossip1, unlockGossip2 FROM {$table} WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($exit1 !== 0 || $exit2 !== 0) $failures[] = "Workerfout: {$error1} {$error2}";
    if (($final['attemptCount'] ?? null) != 2 || ($final['unlockGossip1'] ?? null) != 1 || ($final['unlockGossip2'] ?? null) != 1) {
        $failures[] = 'Gelijktijdige reveals verloren een attempt of unlockvlag: ' . json_encode($final);
    }
    $secondObserved = json_decode($output2, true);
    if (($secondObserved['attemptCount'] ?? null) != 1 || ($secondObserved['unlockGossip1'] ?? null) != 1) {
        $failures[] = 'De tweede transactie las geen na de eerste commit ververste lockstate.';
    }
    if (json_decode($output1, true) === null) $failures[] = 'De eerste worker gaf geen geldige observatie terug.';
} finally {
    $pdo->exec("DROP TABLE IF EXISTS {$table}");
    @unlink($ready);
    @unlink($release);
}

if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "MariaDB event gossip concurrency tests passed.\n";
