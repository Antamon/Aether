<?php
declare(strict_types=1);
if (($argv[1] ?? '') === '--worker') {
    [$scenario, $fixture, $endpoint] = array_slice($argv, 2);
    $_SERVER['SCRIPT_NAME'] = '/aether/aetherapp/'.$endpoint;
    require $fixture.'/api/shared/session.php';
    aetherStartSession();
    $_SESSION = [];
    if (str_starts_with($scenario, 'stale') || str_starts_with($scenario, 'switch')) {
        $_SESSION['user'] = ['id'=>7, 'source'=>'wordpress'];
        $_SESSION['aetherCsrfToken'] = 'old-token';
    }
    $GLOBALS['identityScenario'] = $scenario;
    register_shutdown_function(static function (): void {
        fwrite(STDERR, 'HTTP_STATUS:'.(http_response_code() ?: 200));
    });
    require $fixture.'/'.$endpoint;
    exit;
}
$root = getenv('AETHER_TEST_ROOT') ?: dirname(__DIR__);
$routes = getenv('AETHER_TEST_ROUTES') ?: $root;
$fixture = sys_get_temp_dir().'/aether-login-order-'.bin2hex(random_bytes(6));
foreach (['api/shared', 'api/auth'] as $directory) mkdir($fixture.'/'.$directory, 0777, true);
foreach (glob($root.'/api/shared/*.php') as $source) copy($source, $fixture.'/api/shared/'.basename($source));
copy($root.'/api/auth/accessControl.php', $fixture.'/api/auth/accessControl.php');
foreach (['checkLogin.php', 'getCurrentUser.php'] as $endpoint) copy($routes.'/'.$endpoint, $fixture.'/'.$endpoint);
file_put_contents($fixture.'/sessionUserBootstrap.php', <<<'STUB'
<?php
require_once __DIR__.'/api/shared/session.php';
function aetherLoadWordPressIfAvailable(): bool { return true; }
function is_user_logged_in(): bool { return !str_starts_with($GLOBALS['identityScenario'], 'guest') && !str_starts_with($GLOBALS['identityScenario'], 'stale'); }
function wp_get_current_user(): object { return (object)['ID'=>str_starts_with($GLOBALS['identityScenario'],'switch') ? 9 : 7]; }
function aetherHydrateSessionUserFromWordPress(): bool {
    if (!is_user_logged_in()) return false;
    unset($_SESSION['aetherCsrfToken']);
    $_SESSION['user'] = ['id'=>wp_get_current_user()->ID, 'source'=>'wordpress', 'firstName'=>'Test', 'lastName'=>'User', 'username'=>'test'];
    return true;
}
function aetherProfileInterfaceLanguage(int $id): string { return 'nl'; }
STUB);
file_put_contents($fixture.'/db.php', <<<'STUB'
<?php
file_put_contents(__DIR__.'/database-touched', 'yes');
if (str_ends_with($GLOBALS['identityScenario'], 'down')) {
    http_response_code(500);echo '{"error":"Server error."}';exit;
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE tblUser (id INTEGER PRIMARY KEY, username TEXT, firstName TEXT, lastName TEXT, role TEXT)');
$role = $GLOBALS['identityScenario'] === 'unknown-role' ? 'untrusted' : 'director';
$stmt = $pdo->prepare('INSERT INTO tblUser VALUES (?, ?, ?, ?, ?)');
foreach ([7,9] as $id) $stmt->execute([$id,'test','Test','User',$role]);
STUB);
try {
    foreach (['checkLogin.php', 'getCurrentUser.php'] as $endpoint) {
        foreach (['guest-down','stale-down','authenticated-down','authenticated-ok','switch-ok','unknown-role'] as $scenario) {
            @unlink($fixture.'/database-touched');
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $scenario, $fixture, $endpoint], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
            $body = stream_get_contents($pipes[1]);$err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);fclose($pipes[2]);$exit = proc_close($process);
            $data = json_decode($body, true);
            if ($exit !== 0 || !is_array($data)) throw new RuntimeException('Invalid endpoint response: '.$endpoint.' '.$scenario.' '.$body.' '.$err);
            $guest = str_starts_with($scenario,'guest') || str_starts_with($scenario,'stale');
            $denied = $guest || $scenario === 'unknown-role';
            $status = $denied ? ($endpoint === 'checkLogin.php' ? 200 : 401) : ($scenario === 'authenticated-down' ? 500 : 200);
            if (!str_contains($err, 'HTTP_STATUS:'.$status)) throw new RuntimeException('Incorrect HTTP status: '.$endpoint.' '.$scenario);
            if ($guest && is_file($fixture.'/database-touched')) throw new RuntimeException('Guest accessed database bootstrap.');
            if ($denied && ($data['status'] ?? null) !== 'redirect') throw new RuntimeException('Denied identity must redirect.');
            if (!$denied && $scenario !== 'authenticated-down') {
                $user = $endpoint === 'checkLogin.php' ? $data['user'] : $data;
                if ($user['id'] !== ($scenario === 'switch-ok' ? 9 : 7) || $user['role'] !== 'director' || empty($data['csrfToken'])) throw new RuntimeException('Authenticated identity/role/CSRF changed.');
            }
        }
    }
    echo "PASS: guests and logged-out sessions redirect before database access; authenticated outages stay HTTP 500; valid identities, account switches, roles and CSRF remain enforced.\n";
} finally {
    foreach (glob($fixture.'/api/shared/*.php') as $file) unlink($file);
    foreach (['api/auth/accessControl.php','checkLogin.php','getCurrentUser.php','sessionUserBootstrap.php','db.php','database-touched'] as $file) if (is_file($fixture.'/'.$file)) unlink($fixture.'/'.$file);
    foreach (['api/shared','api/auth','api'] as $directory) rmdir($fixture.'/'.$directory);
    rmdir($fixture);
}
