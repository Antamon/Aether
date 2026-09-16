<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$callbackPath = $projectRoot . '/tokenhandler.php';
$callback = file_get_contents($callbackPath);
$failures = [];

function assertOidcCallback(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

assertOidcCallback(is_string($callback), 'De callback kon niet worden gelezen.');
if (is_string($callback)) {
    assertOidcCallback(str_contains($callback, 'http_response_code(410)'), 'De callback antwoordt niet fail-closed met HTTP 410.');
    assertOidcCallback(str_contains($callback, "header('Cache-Control: no-store')"), 'De callback verhindert caching niet.');
    assertOidcCallback(!str_contains($callback, 'curl_init'), 'De uitgeschakelde callback wisselt nog een autorisatiecode om.');
    assertOidcCallback(!str_contains($callback, 'id_token'), 'De uitgeschakelde callback verwerkt nog een ID-token.');
    assertOidcCallback(!str_contains($callback, "\$_SESSION['user']"), 'De uitgeschakelde callback kan nog een login voltooien.');
    assertOidcCallback(!str_contains($callback, 'client_secret'), 'De uitgeschakelde callback bevat nog clientgeheim-configuratie.');
}

$referencingFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $relativePath = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($projectRoot) + 1));
    if ($relativePath === 'tokenhandler.php'
        || str_starts_with($relativePath, 'docs/')
        || str_starts_with($relativePath, 'tests/')
        || str_starts_with($relativePath, 'legacy/')) {
        continue;
    }

    if (!in_array(strtolower($fileInfo->getExtension()), ['php', 'html', 'js'], true)) {
        continue;
    }

    $contents = file_get_contents($fileInfo->getPathname());
    if (is_string($contents) && stripos($contents, 'tokenhandler.php') !== false) {
        $referencingFiles[] = $relativePath;
    }
}

assertOidcCallback(
    $referencingFiles === [],
    'De applicatie verwijst nog naar de oude callback: ' . implode(', ', $referencingFiles)
);

$sessionBootstrap = file_get_contents($projectRoot . '/sessionUserBootstrap.php');
$checkLogin = file_get_contents($projectRoot . '/checkLogin.php');
assertOidcCallback(
    is_string($sessionBootstrap)
        && str_contains($sessionBootstrap, 'is_user_logged_in()')
        && str_contains($sessionBootstrap, 'wp_get_current_user()'),
    'De afzonderlijke WordPress-cookieauthenticatie is niet meer aantoonbaar aanwezig.'
);
assertOidcCallback(
    is_string($checkLogin) && str_contains($checkLogin, 'aetherHydrateSessionUserFromWordPress()'),
    'De logincontrole gebruikt de WordPress-bootstrap niet meer.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "OIDC callback disablement tests passed.\n";

