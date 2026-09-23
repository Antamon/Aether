<?php
declare(strict_types=1);

$dsn = getenv('AETHER_TEST_MYSQL_DSN') ?: '';
$user = getenv('AETHER_TEST_MYSQL_USER') ?: '';
$pass = getenv('AETHER_TEST_MYSQL_PASSWORD') ?: '';
if ($dsn === '' || getenv('AETHER_ALLOW_COMPANY_MARIADB_TESTS') !== 'YES' || getenv('AETHER_TEST_DB_DISPOSABLE') !== 'YES') {
    echo "SKIP: company-locktest vereist een expliciet toegestane, wegwerpbare MariaDB-testdatabase.\n";
    exit(0);
}
if (($argv[1] ?? '') === 'worker') {
    [, , $order, $dsn64, $user64, $pass64, $companies, $personnel, $ready, $release, $locked] = $argv;
    $pdo = new PDO(base64_decode($dsn64), base64_decode($user64), base64_decode($pass64), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->beginTransaction();
    $pdo->query("SELECT id FROM {$companies} WHERE id = 1 FOR UPDATE")->fetchColumn();
    file_put_contents($locked, $order);
    if ($order === 'first') {
        file_put_contents($ready, 'ready');
        while (!is_file($release)) usleep(10000);
    }
    $pdo->exec("DELETE FROM {$personnel} WHERE idCompany = 1");
    $pdo->exec("INSERT INTO {$personnel} (idCompany, idCharacter) VALUES (1, 7)");
    $pdo->commit();
    echo 'committed';
    exit;
}

$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/(?:test|dev|ci)/i', $database)) {
    fwrite(STDERR, "REFUSED: database heeft geen herkenbare testnaam.\n");
    exit(1);
}
$suffix = bin2hex(random_bytes(5));
$companies = 'tmp_aether_company_' . $suffix;
$personnel = 'tmp_aether_personnel_' . $suffix;
$ready = sys_get_temp_dir().'/company-ready-'.$suffix;
$release = sys_get_temp_dir().'/company-release-'.$suffix;
$lockedFirst = sys_get_temp_dir().'/company-locked-first-'.$suffix;
$lockedSecond = sys_get_temp_dir().'/company-locked-second-'.$suffix;
$processes = [];
try {
    $pdo->exec("CREATE TABLE {$companies} (id INT PRIMARY KEY) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE {$personnel} (idCompany INT NOT NULL, idCharacter INT NOT NULL, UNIQUE KEY uq_company_character (idCompany,idCharacter)) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO {$companies} (id) VALUES (1)");
    $worker = static fn(string $order, string $locked): array => [
        PHP_BINARY, __FILE__, 'worker', $order,
        base64_encode($dsn), base64_encode($user), base64_encode($pass),
        $companies, $personnel, $ready, $release, $locked,
    ];
    $first = proc_open($worker('first', $lockedFirst), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $firstPipes);
    if (!is_resource($first)) throw new RuntimeException('Eerste worker kon niet starten.');
    $processes[] = [$first, $firstPipes];
    $deadline = microtime(true) + 5;
    while (!is_file($ready) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($ready)) throw new RuntimeException('Eerste worker kreeg de companylock niet.');
    $second = proc_open($worker('second', $lockedSecond), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $secondPipes);
    if (!is_resource($second)) throw new RuntimeException('Tweede worker kon niet starten.');
    $processes[] = [$second, $secondPipes];
    usleep(250000);
    if (is_file($lockedSecond)) throw new RuntimeException('Tweede worker passeerde de companylock te vroeg.');
    file_put_contents($release, 'go');
    $outputs = [];
    foreach ($processes as [$process, $pipes]) {
        fclose($pipes[0]);
        $outputs[] = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Workerfout: '.$error);
    }
    $processes = [];
    if ($outputs !== ['committed','committed'] || (int) $pdo->query("SELECT COUNT(*) FROM {$personnel}")->fetchColumn() !== 1) {
        throw new RuntimeException('Gelijktijdige personeelsvervanging gaf geen enkele eindrij.');
    }
    echo "MariaDB company-locktest passed.\n";
} finally {
    if (!is_file($release)) file_put_contents($release, 'go');
    foreach ($processes as [$process, $pipes]) {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($process)) proc_close($process);
    }
    $pdo->exec("DROP TABLE IF EXISTS {$personnel}");
    $pdo->exec("DROP TABLE IF EXISTS {$companies}");
    foreach ([$ready,$release,$lockedFirst,$lockedSecond] as $path) if (is_file($path)) unlink($path);
}
