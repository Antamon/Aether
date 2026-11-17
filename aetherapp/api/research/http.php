<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../shared/response.php';
require_once __DIR__.'/../shared/request.php';
require_once __DIR__.'/../shared/idempotency.php';
require_once __DIR__.'/researchReadService.php';
try {
    require __DIR__.'/../../db.php';
    $user=aetherRequireAuthenticatedUser($pdo);
    $method=$_SERVER['REQUEST_METHOD']??'';
    if ($method==='GET') {
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        $pdo->beginTransaction();researchLock($pdo);
        $result=researchRead($pdo,$user,(string)($_GET['op']??''),$_GET);
        $pdo->commit();aetherJsonResponse($result);
    }
    if ($method!=='POST') aetherJsonError(405,'Gebruik GET of POST.');
    aetherRequireCsrfToken();if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $data=aetherReadJsonObject();$op=researchText($data['op']??'',40);unset($data['op']);
    $sf=in_array($op,['scan','shown','confirm'],true);
    $authorize=function() use($pdo,$user,$data,$sf): void {
        if (!$sf) researchManager($user);
        else researchAccess($pdo,$user,researchInt($data['idCharacter']??0),researchInt($data['idEvent']??0));
    };
    $authorize();
    $result=aetherRunIdempotentMutation($pdo,$user,'research.'.$op,aetherRequireIdempotencyKey(),$data,function() use($pdo,$user,$data,$op,$authorize) {
        researchLock($pdo);$authorize();
        if ($op==='scan') {researchFields($data,['idCharacter','idEvent','code']);researchText($data['code']??'',100);return researchScan($pdo,$user,$data);}
        if (in_array($op,['shown','confirm','progress_complete'],true)) {
            researchFields($data,['idCharacter','idEvent','idDiscovery','idStep','reason']);
            foreach (['idCharacter','idEvent','idDiscovery','idStep'] as $key) researchInt($data[$key]??0);
            if ($op==='progress_complete') researchText($data['reason']??'',1000);
            return researchAdvance($pdo,$user,$data,$op==='confirm'?'confirm':'shown',$op==='progress_complete');
        }
        return researchAdminSave($pdo,$user,$op,$data);
    },$authorize);
    aetherJsonResponse($result);
} catch (AetherResearchException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();aetherJsonError($e->status,$e->getMessage());
} catch (AetherValidationException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();aetherJsonValidationError($e->getValidationErrors());
} catch (AetherIdempotencyException $e) { aetherJsonError($e->getHttpStatus(),$e->getMessage());
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();error_log('Research: '.$e->getMessage());aetherJsonError(500,'Onderzoek kon niet worden geladen of bewaard. Controleer of migratie 0024 is uitgevoerd.');
}
