<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../shared/response.php';
require_once __DIR__.'/researchAdminService.php';
try {
    require __DIR__.'/../../db.php';$user=aetherRequireAuthenticatedUser($pdo);researchManager($user);
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') aetherJsonError(405,'Gebruik POST.');
    aetherRequireCsrfToken();if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $step=researchInt($_POST['idStep']??0);$file=$_FILES['file']??[];
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'') || (int)$file['size']<1 || (int)$file['size']>8*1024*1024) throw new AetherResearchException(422,'Kies een afbeelding, audio- of videobestand van maximaal 8 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime,['image/jpeg','image/png','image/webp','audio/mpeg','audio/ogg','audio/wav','audio/x-wav','video/mp4','video/webm','video/ogg'],true)) throw new AetherResearchException(422,'Ondersteund: JPG, PNG, WebP, MP3, Ogg, WAV, MP4 en WebM.');
    $bytes=file_get_contents($file['tmp_name']);
    if (str_starts_with($mime,'image/')) {
        $size=@getimagesize($file['tmp_name']);
        if (!$size || $size[0]*$size[1]>20000000 || !function_exists('imagecreatefromstring')) throw new AetherResearchException(422,'Afbeelding ongeldig, te groot (20 megapixel) of beeldverwerking niet beschikbaar.');
        $im=@imagecreatefromstring($bytes);if (!$im) throw new AetherResearchException(422,'Afbeelding kan niet worden gelezen.');
        ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$mime='image/png';
        if (strlen($bytes)>8*1024*1024) throw new AetherResearchException(422,'De verwerkte afbeelding is groter dan 8 MB.');
    }
    $filename=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u','',basename((string)$file['name'])),0,200);
    $pdo->beginTransaction();researchLock($pdo);
    if (!researchOne($pdo,'SELECT id FROM tblResearchStep WHERE id=?',[$step])) throw new AetherResearchException(404,'Stap niet gevonden.');
    $hash=hash('sha256',$bytes);
    $existing=researchOne($pdo,'SELECT id FROM tblResearchMedia WHERE idStep=? AND sha256=?',[$step,$hash]);
    if (!$existing) {
        researchQuery($pdo,'INSERT INTO tblResearchMedia(idStep,filename,mime,byteSize,sha256,bytes) VALUES (?,?,?,?,?,?)',[$step,$filename,$mime,strlen($bytes),$hash,$bytes]);
        researchAudit($pdo,$user,'media_upload',['idStep'=>$step,'sha256'=>$hash]);
    }
    $pdo->commit();aetherJsonResponse(['saved'=>true]);
} catch (AetherResearchException $e) {if (isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();aetherJsonError($e->status,$e->getMessage());
} catch (AetherValidationException $e) {aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {if (isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Research upload: '.$e->getMessage());aetherJsonError(500,'Media kon niet worden opgeslagen.');}
