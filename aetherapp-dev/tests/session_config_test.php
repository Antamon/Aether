<?php
declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/aether/aetherapp-dev/api/characters/getCharacter.php';
$_SERVER['HTTPS'] = 'on';
require_once __DIR__ . '/../api/auth/accessControl.php';

$failures = [];
function sessionCheck(bool $condition, string $message): void { global $failures; if (!$condition) $failures[] = $message; }

$cookie = aetherSessionCookieOptions($_SERVER);
sessionCheck($cookie['path'] === '/aether/aetherapp-dev/', 'Cookiepad van API-route');
sessionCheck($cookie['secure'] && $cookie['httponly'] && $cookie['samesite'] === 'Lax', 'Veilige cookie-opties');
sessionCheck(aetherSessionCookieOptions(['SCRIPT_NAME'=>'/aether/aetherapp-dev/index.php','HTTPS'=>'off'])['path'] === '/aether/aetherapp-dev/', 'Cookiepad van ingangspagina');
sessionCheck(!aetherSessionCookieOptions(['SCRIPT_NAME'=>'/index.php','HTTPS'=>'off'])['secure'], 'Geen spoofbare proxyheader als HTTPS-bewijs');
sessionCheck(aetherSessionCookieOptions(['SCRIPT_NAME'=>'/index.php','SERVER_PORT'=>'443'])['secure'], 'HTTPS via serverpoort');

$GLOBALS['testWpLoggedIn'] = true;
$GLOBALS['testWpId'] = 42;
function is_user_logged_in(): bool { return $GLOBALS['testWpLoggedIn']; }
function wp_get_current_user(): object { return (object) ['ID'=>$GLOBALS['testWpId'],'user_login'=>'test','user_email'=>'']; }

aetherStartSession();
sessionCheck(session_get_cookie_params()['path'] === '/aether/aetherapp-dev/', 'Werkelijke sessiecookiepad');
sessionCheck(ini_get('session.use_strict_mode') === '1' && ini_get('session.use_only_cookies') === '1', 'Strikte cookiesessie');
$_SESSION = [];
sessionCheck(aetherEnsureSessionIdentity() === 42, 'WordPress-login vult Aether-sessie');
sessionCheck(($_SESSION['user']['source'] ?? null) === 'wordpress', 'Vertrouwde sessiebron');
$_SESSION['aetherCsrfToken'] = 'old-token';
$GLOBALS['testWpLoggedIn'] = false;
sessionCheck(aetherEnsureSessionIdentity() === 0 && !isset($_SESSION['aetherCsrfToken']), 'WordPress-logout wist identiteit en CSRF');
$GLOBALS['testWpLoggedIn'] = true;
$GLOBALS['testWpId'] = 43;
sessionCheck(aetherEnsureSessionIdentity() === 43, 'Nieuwe WordPress-login wordt herkend');
$GLOBALS['testWpLoggedIn'] = false;
$_SESSION = ['user'=>['id'=>43], 'aetherCsrfToken'=>'legacy-token'];
sessionCheck(aetherEnsureSessionIdentity() === 0 && !isset($_SESSION['aetherCsrfToken']), 'Ook oude sessie zonder bronmarkering vervalt na WordPress-logout');
$GLOBALS['testWpLoggedIn'] = true;
sessionCheck(aetherEnsureSessionIdentity() === 43, 'WordPress-login blijft beschikbaar na oude sessie');
$GLOBALS['testWpId'] = 44;
sessionCheck(aetherEnsureSessionIdentity() === 44, 'WordPress-accountwissel vervangt oude identiteit');

$_SESSION = [];
session_write_close();
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Session configuration tests passed.\n";
