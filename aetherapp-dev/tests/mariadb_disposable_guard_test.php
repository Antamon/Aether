<?php
declare(strict_types=1);
require_once __DIR__ . '/mariadb_disposable_guard.php';

$failures=[];
function guardCheck(bool $ok,string $message):void { global $failures; if(!$ok)$failures[]=$message; }
$dsn='mysql:host=localhost;dbname=aether_disposable_local_ci;charset=utf8mb4';
guardCheck(aetherDisposableMariaDbName($dsn,'aether_disposable_local_ci'), 'Exacte wegwerpnaam toegestaan');
foreach (['oneiros_beaetherdev','aetherapp_dev','aether_disposable_other','aether_disposable_local_ci_extra'] as $name) {
    guardCheck(!aetherDisposableMariaDbName($dsn,$name), 'Afwijkende database geweigerd');
}
guardCheck(!aetherDisposableMariaDbName('mysql:host=localhost;dbname=oneiros_beaetherdev;charset=utf8mb4','oneiros_beaetherdev'), 'Online devnaam geweigerd');
$old=[];
foreach (['AETHER_TEST_MYSQL_DSN','AETHER_TEST_MYSQL_DATABASE','AETHER_TEST_MYSQL_USER','AETHER_TEST_MYSQL_PASSWORD','AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS','AETHER_TEST_DB_DISPOSABLE','AETHER_ALLOW_COMPANY_MARIADB_TESTS'] as $key) {
    $old[$key]=getenv($key);
}
try {
    putenv('AETHER_TEST_MYSQL_DSN='.$dsn);
    putenv('AETHER_TEST_MYSQL_DATABASE=aether_disposable_local_ci');
    putenv('AETHER_TEST_MYSQL_USER=placeholder');
    putenv('AETHER_TEST_MYSQL_PASSWORD=placeholder');
    putenv('AETHER_ALLOW_MARIADB_CONCURRENCY_TESTS=YES');
    putenv('AETHER_TEST_DB_DISPOSABLE=YES');
    putenv('AETHER_ALLOW_COMPANY_MARIADB_TESTS=YES');
    guardCheck(aetherMariaDbConcurrencyAllowed('AETHER_ALLOW_COMPANY_MARIADB_TESTS'), 'Volledig expliciete testconfiguratie');
    putenv('AETHER_TEST_DB_DISPOSABLE=NO');
    guardCheck(!aetherMariaDbConcurrencyAllowed('AETHER_ALLOW_COMPANY_MARIADB_TESTS'), 'Ontbrekende disposable-markering weigert');
} finally {
    foreach ($old as $key=>$value) putenv($value===false ? $key : $key.'='.$value);
}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}
echo "Disposable MariaDB guard tests passed.\n";
