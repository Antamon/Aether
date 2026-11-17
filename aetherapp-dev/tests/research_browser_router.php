<?php
// LOCAL TEST SERVER ONLY. Never used by Apache/production; tests/ is blocked by .htaccess.
if (PHP_SAPI!=='cli-server' || !getenv('RESEARCH_BROWSER_FIXTURE')) {http_response_code(404);exit;}
$root=dirname(__DIR__);$fixture=$root.'/.research-test-runtime/http';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/test-login' || $path==='/checkLogin.php') {
    require_once $fixture.'/api/auth/accessControl.php';require $fixture.'/db.php';
    aetherStartSession();if($path==='/test-login') {$_SESSION['user']=['id'=>(int)($_GET['id']??99)];unset($_SESSION['aetherCsrfToken']);}
    $user=aetherRequireAuthenticatedUser($pdo);$user['displayName']=$user['firstName'].' '.$user['lastName'];
    header('Content-Type: application/json');echo json_encode(['status'=>'ok','user'=>$user,'csrfToken'=>aetherGetCsrfToken()]);exit;
}
if (preg_match('#^/api/research/(http|upload|media)\.php$#D',$path)) {require $fixture.$path;exit;}
if ($path==='/test-character') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="nl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/modal.css"><link rel="stylesheet" href="css/research.css"></head><body><main class="container aether-page"><div id="actionsTabContent"></div></main>';
    foreach(['mainFunctions','actionButtons','modal','actionsCharacter','richTextCharacter','vendor/jsQR','researchUi','researchCharacter']as$js)echo '<script src="js/'.$js.'.js"></script>';
    echo '<script>const currentCharacter={id:1,skills:[]}; window.aetherShareMarketClient={resetActionView(){}}; const getCurrentUser=()=>window.AETHER_CURRENT_USER; aetherCharacterActionsState.characterId=1;aetherCharacterActionsState.events=[{idEvent:1,title:"Eén",dateStart:"2026-06-09",dateEnd:"2026-06-11"}];aetherCharacterActionsState.worldKnowledgeLevel=1;aetherCharacterActionsState.activeAction="research";aetherCharacterActionsState.selectedEventId=1; aetherGetCurrentUser().then(()=>renderCharacterActionsTab(currentCharacter));</script></body></html>';exit;
}
if (str_starts_with($path,'/api/') || $path==='/api/version.php') {header('Content-Type: application/json');echo '[]';exit;}
$file=realpath($root.$path);$allowed=preg_match('#^/(research\.html|VERSION|(?:js|css|img|images|fonts|Modal/assets)/[a-zA-Z0-9_./() -]+)$#D',$path);
if (!$file || !$allowed || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)) {http_response_code(404);exit;}
$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));$types=['html'=>'text/html; charset=utf-8','js'=>'text/javascript; charset=utf-8','css'=>'text/css; charset=utf-8','png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml','woff2'=>'font/woff2','ttf'=>'font/ttf'];
header('Content-Type: '.($types[$ext]??'text/plain'));readfile($file);
