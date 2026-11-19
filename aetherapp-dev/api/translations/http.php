<?php
declare(strict_types=1);
define('AETHER_SKIP_CONTENT_TRANSLATION',true);
require_once __DIR__.'/../../db.php';
require_once __DIR__.'/../auth/accessControl.php';
require_once __DIR__.'/../shared/request.php';
require_once __DIR__.'/../shared/idempotency.php';
require_once __DIR__.'/../shared/translationService.php';
try {
    $user=aetherRequireAuthenticatedUser($pdo);
    $manager=aetherIsPrivilegedRole($user['role']);
    $method=$_SERVER['REQUEST_METHOD']??'';
    if ($method==='GET' && ($_GET['op']??'')==='status') {
        if (!$manager) aetherJsonError(403,'Je hebt geen rechten voor deze handeling.');
        aetherJsonResponse(aetherTranslationStatus($pdo));
    }
    if ($method!=='POST') aetherJsonError(405,'Gebruik POST.');
    aetherRequireCsrfToken();$input=aetherReadJsonObject();$op=$input['op']??'';
    if (!in_array($op,['automatic','read','save'],true)) aetherJsonError(400,'Ongeldige vertaalhandeling.');
    if ($op!=='automatic'&&!$manager) aetherJsonError(403,'Je hebt geen rechten voor deze handeling.');
    foreach (['source','target','format','grant'] as $key) if (!is_string($input[$key]??null)) aetherJsonError(422,'Ongeldige vertaalinvoer.');
    $source=$input['source'];$target=$input['target'];$format=$input['format'];
    if (trim($source)===''||mb_strlen($source,'UTF-8')>500000||!in_array($target,['nl','en'],true)||!in_array($format,['plain','html'],true)) aetherJsonError(422,'Ongeldige vertaalinvoer.');
    if (!aetherTranslationGrantValid($user,$source,$format,$input['grant'])) aetherJsonError(403,'Open de oorspronkelijke pagina opnieuw om deze tekst te vertalen.');
    if ($op==='automatic') {
        // Release the session lock before external I/O, so normal app requests remain usable.
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        $result=aetherTranslationAutomatic($pdo,$source,$target,$format);
        if ($manager) $result['usage']=aetherTranslationStatus($pdo);
        aetherJsonResponse($result);
    }
    if ($op==='read') {
        $row=aetherTranslationCached($pdo,$source,$target);
        aetherJsonResponse(['text'=>$row&&$row['status']==='ready'?$row['translatedText']:'','sourceLanguage'=>$row['sourceLanguage']??'nl','version'=>aetherTranslationVersion($row)]);
    }
    foreach (['text','sourceLanguage','version'] as $key) if (!is_string($input[$key]??null)) aetherJsonError(422,'Ongeldige vertaalinvoer.');
    if (mb_strlen($input['text'],'UTF-8')>500000||!in_array($input['sourceLanguage'],['nl','en'],true)) aetherJsonError(422,'Ongeldige vertaalinvoer.');
    $result=aetherRunIdempotentMutation($pdo,$user,'translations.saveManual',aetherRequireIdempotencyKey(),$input,
        static fn()=>aetherTranslationSaveManual($pdo,$source,$target,$format,$input['text'],$input['sourceLanguage'],$input['version']),
        static function()use($pdo,$source,$format,$input):void {
            $user=aetherRequirePrivilegedUser($pdo);
            if (!aetherTranslationGrantValid($user,$source,$format,$input['grant'])) aetherJsonError(403,'Open de oorspronkelijke pagina opnieuw om deze tekst te vertalen.');
        });
    aetherJsonResponse($result);
} catch (DomainException $e) {aetherJsonError(409,$e->getMessage());}
catch (InvalidArgumentException $e) {aetherJsonError(422,$e->getMessage());}
catch (AetherIdempotencyException $e) {aetherJsonError($e->getHttpStatus(),$e->getMessage());}
catch (Throwable $e) {error_log('Aether translation request unavailable.');aetherJsonError(503,'Vertalen is tijdelijk niet beschikbaar. De oorspronkelijke tekst blijft behouden.');}
