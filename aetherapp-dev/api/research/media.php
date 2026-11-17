<?php
declare(strict_types=1);
require_once __DIR__.'/../shared/response.php';
require_once __DIR__.'/researchAdminService.php';
try {
    require __DIR__.'/../../db.php';$user=aetherRequireAuthenticatedUser($pdo);
    if (($_SERVER['REQUEST_METHOD']??'')!=='GET') aetherJsonError(405,'Gebruik GET.');
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $pdo->beginTransaction();researchLock($pdo);
    $id=researchInt($_GET['id']??0);$m=researchMediaPermission($pdo,$user,$id,researchInt($_GET['idEvent']??0,0),researchInt($_GET['idCharacter']??0,0));
    $bytes=researchQuery($pdo,'SELECT bytes FROM tblResearchMedia WHERE id=?',[$id])->fetchColumn();$pdo->commit();
    if (is_resource($bytes)) $bytes=stream_get_contents($bytes);
    $size=strlen($bytes);$start=0;$end=$size-1;
    header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; sandbox");header('Cross-Origin-Resource-Policy: same-origin');
    header('Content-Type: '.$m['mime']);header('Content-Disposition: inline');header('Accept-Ranges: bytes');
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/D',$_SERVER['HTTP_RANGE'],$range) || ($range[1]==='' && $range[2]==='')) {header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
        if ($range[1]==='') $start=max(0,$size-(int)$range[2]);
        else {$start=(int)$range[1];if ($range[2]!=='') $end=min($end,(int)$range[2]);}
        if ($start>$end || $start>=$size) {header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
        http_response_code(206);header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: '.($end-$start+1));echo substr($bytes,$start,$end-$start+1);
} catch (AetherResearchException $e) {if (isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();aetherJsonError($e->status,$e->getMessage());
} catch (Throwable $e) {if (isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Research media: '.$e->getMessage());aetherJsonError(404,'Media niet beschikbaar.');}
