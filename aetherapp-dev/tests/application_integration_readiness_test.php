<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function integrationCheck(bool $ok, string $message): void { global $failures; if (!$ok) $failures[] = $message; }

/** Check literal __DIR__ includes with Linux-style case sensitivity, even on Windows. */
function integrationExactPath(string $path): bool
{
    $root = str_replace('\\', '/', dirname(__DIR__));
    $absolute = str_starts_with(str_replace('\\', '/', $path), '/');
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') array_pop($parts);
        else $parts[] = $part;
    }
    $normalized = ($absolute ? '/' : '') . implode('/', $parts);
    if (!str_starts_with($normalized, $root . '/')) return false;
    $prefix = $root;
    foreach (explode('/', substr($normalized, strlen($root) + 1)) as $part) {
        $entries = scandir($prefix);
        if ($entries === false || !in_array($part, $entries, true)) return false;
        $prefix .= '/' . $part;
    }
    return is_file($prefix);
}

$graph = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$checked = 0;
foreach ($iterator as $entry) {
    $path = $entry->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (!str_ends_with($relative, '.php') || preg_match('#^(?:tests|vendor|legacy|docs|sql)/#', $relative)
        || in_array($relative, ['config.local.php', 'db.legacy.local.php'], true)) continue;
    $source = file_get_contents($path);
    if ($source === false) { $failures[] = 'Kon PHP-bestand niet lezen: ' . $relative; continue; }
    preg_match('/\bfunction\s+\w+\s*\(/', $source, $firstFunction, PREG_OFFSET_CAPTURE);
    $firstFunctionOffset = $firstFunction[0][1] ?? null;
    preg_match_all('/\b(?:require|include)(?:_once)?\s*(?:\(\s*)?__DIR__\s*\.\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[1] as [$include, $offset]) {
        $target = dirname($path) . $include;
        $checked++;
        integrationCheck(integrationExactPath($target), 'Ontbrekend of afwijkend hoofdletterpad: ' . $relative . ' -> ' . $include);
        $resolved = realpath($target);
        if ($resolved !== false && ($firstFunctionOffset === null || $offset < $firstFunctionOffset)) $graph[$path][] = $resolved;
    }
}

$visiting = []; $visited = []; $cycles = [];
$visit = function (string $path) use (&$visit, &$visiting, &$visited, &$graph, &$cycles): void {
    if (isset($visiting[$path])) { $cycles[basename($path)] = true; return; }
    if (isset($visited[$path])) return;
    $visiting[$path] = true;
    foreach ($graph[$path] ?? [] as $next) $visit($next);
    unset($visiting[$path]); $visited[$path] = true;
};
foreach (array_keys($graph) as $path) $visit($path);
integrationCheck($checked > 100, 'Actieve include-inventaris te klein');
integrationCheck($cycles === [], 'Top-level include-cyclus gevonden: ' . implode(', ', array_keys($cycles)));

$frontendRoutes = 0;
foreach (glob($root . '/js/*.js') ?: [] as $script) {
    preg_match_all('#api/[A-Za-z0-9_/-]+\.php#', (string) file_get_contents($script), $routes);
    foreach (array_unique($routes[0]) as $route) {
        $frontendRoutes++;
        integrationCheck(integrationExactPath($root . '/' . $route), 'Frontend verwijst naar ontbrekende API-route: ' . basename($script) . ' -> ' . $route);
    }
}
integrationCheck($frontendRoutes > 50, 'Frontend-API-routecontrole te klein');

$htaccess = (string) file_get_contents($root . '/.htaccess');
$ignore = (string) file_get_contents($root . '/.gitignore');
integrationCheck(str_contains($htaccess, 'db\\.legacy\\.local') && str_contains($htaccess, 'config(?:\\.local|\\.example)'), 'Configuratie niet door Apache-regels beschermd');
integrationCheck(str_contains($htaccess, 'tests|docs|sql|legacy') && str_contains($htaccess, 'F,L'), 'Privébrondirectories niet door Apache-regels beschermd');
integrationCheck(str_contains($ignore, 'config.local.php') && str_contains($ignore, 'db.legacy.local.php'), 'Lokale configuratie/back-up niet genegeerd');
integrationCheck(str_contains((string) file_get_contents($root . '/api/.htaccess'), 'no-store'), 'API-cacheheaders ontbreken');
integrationCheck(str_contains((string) file_get_contents($root . '/version.php'), 'aetherSendNoStoreHeaders(false)'), 'Versie-endpoint zonder no-store');
integrationCheck(str_contains((string) file_get_contents($root . '/tokenhandler.php'), '410'), 'Uitgeschakelde OIDC-callback');
integrationCheck(str_contains((string) file_get_contents($root . '/img/bedrijfslogo/.htaccess'), 'php'), 'Logomap beschermt niet tegen PHP');
integrationCheck(str_contains((string) file_get_contents($root . '/img/portret/.htaccess'), 'php'), 'Portretmap beschermt niet tegen PHP');

foreach (['characters','companies','events','admin','users','shared','auth'] as $module) {
    integrationCheck(is_dir($root . '/api/' . $module), 'Ontbrekende API-module: ' . $module);
}
foreach (glob($root . '/api/characters/*.php') ?: [] as $route) {
    if (basename($route) === 'characterRequestValidation.php') continue;
    $source = (string) file_get_contents($route);
    integrationCheck(!str_contains($source, "require_once __DIR__ . '/characterRequestValidation.php'"), 'Actieve characterroute gebruikt oude validatiefacade: ' . basename($route));
}

if ($failures) { fwrite(STDERR, implode(PHP_EOL, array_unique($failures)) . PHP_EOL); exit(1); }
echo "Integration readiness static checks passed ({$checked} literal includes, {$frontendRoutes} frontend API paths; no top-level cycles).\n";
