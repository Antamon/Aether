<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/adminAccess.php';
require_once __DIR__.'/adminTraitService.php';
require_once __DIR__.'/../shared/request.php';
require_once __DIR__.'/../shared/response.php';
require_once __DIR__.'/../shared/idempotency.php';
require_once __DIR__.'/../characters/characterMediaUtils.php';
try{
    require __DIR__.'/../../db.php';$user=aetherRequireAdminEditor($pdo);
    $method=$_SERVER['REQUEST_METHOD']??'';
    if($method==='GET'){
        $query=aetherValidateInput($_GET,['id'=>['type'=>'int','min'=>0,'default'=>0]]);
        aetherJsonResponse($query['id']?aetherAdminTraitDetail($pdo,$query['id']):['traits'=>aetherAdminTraitCatalog($pdo)]);
    }
    if($method!=='POST')aetherJsonError(405,'Gebruik GET of POST.');
    aetherRequireCsrfToken();$data=aetherAdminValidateTrait(aetherReadJsonObject());
    $result=aetherRunIdempotentMutation($pdo,$user,'admin.saveTrait',aetherRequireIdempotencyKey(),$data,static fn()=>aetherAdminSaveTrait($pdo,$data),static fn()=>aetherRequireAdminEditor($pdo));
    aetherJsonResponse($result);
}catch(AdminTraitProblem $e){aetherJsonError($e->status,$e->getMessage());}
catch(AetherValidationException $e){aetherJsonValidationError($e->getValidationErrors());}
catch(AetherIdempotencyException $e){aetherJsonError($e->getHttpStatus(),$e->getMessage());}
catch(Throwable $e){error_log('admin traits: '.$e->getMessage());aetherJsonError(500,'Kon de eigenschap niet verwerken.');}
