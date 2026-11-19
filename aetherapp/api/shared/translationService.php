<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_once __DIR__.'/richText.php';

function aetherTranslationSettings(?array $values = null): array
{
    $v = $values ?? (aetherLoadConfiguration()['translation'] ?? []);
    $integer = static fn(string $key,int $default,int $max): int => isset($v[$key]) && filter_var($v[$key],FILTER_VALIDATE_INT)!==false ? max(0,min($max,(int)$v[$key])) : $default;
    $zone=(string)($v['usageTimeZone']??'Europe/Brussels');
    if (!in_array($zone,DateTimeZone::listIdentifiers(),true)) $zone='Europe/Brussels';
    $key=(string)($v['googleApiKey']??'');
    return ['enabled'=>filter_var($v['enabled']??false,FILTER_VALIDATE_BOOLEAN),'googleApiKey'=>$key,
        'monthlyCharacterLimit'=>$integer('monthlyCharacterLimit',500000,100000000),
        'maxAutomaticCharacters'=>$integer('maxAutomaticCharacters',2000,30000),
        'requestTimeoutSeconds'=>max(1,$integer('requestTimeoutSeconds',8,20)),
        'budgetKey'=>preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D',(string)($v['budgetKey']??''))?(string)$v['budgetKey']:'aether-google',
        'usageTimeZone'=>$zone,'usageDatabase'=>$v['usageDatabase']??null];
}
function aetherTranslationUsagePdo(PDO $pdo,array $settings): PDO
{
    if (!$settings['usageDatabase']) return $pdo;
    require_once __DIR__.'/database.php';
    $db=$settings['usageDatabase'];
    if (!is_array($db)||!preg_match('/^mysql:(?:host=[^;]+|unix_socket=[^;]+);dbname=[^;]+;charset=utf8mb4$/D',(string)($db['dsn']??''))) throw new RuntimeException('Ongeldige gebruiksdatabank.');
    static $connections=[];$key=hash('sha256',json_encode($db,JSON_THROW_ON_ERROR));
    return $connections[$key]??=aetherCreateDatabaseConnection(['database'=>$db]);
}
function aetherTranslationMonth(array $settings,?DateTimeImmutable $now=null): string
{
    return ($now??new DateTimeImmutable())->setTimezone(new DateTimeZone($settings['usageTimeZone']))->format('Y-m');
}
function aetherTranslationQuery(PDO $pdo,string $sql,array $params=[]): PDOStatement
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $sql=str_replace(['INSERT IGNORE',' FOR UPDATE'],['INSERT OR IGNORE',''],$sql);
    $s=$pdo->prepare($sql);$s->execute($params);return $s;
}
function aetherTranslationStatus(PDO $pdo,?array $settings=null,?DateTimeImmutable $now=null): array
{
    $settings??=aetherTranslationSettings();$month=aetherTranslationMonth($settings,$now);
    $status=['month'=>$month,'limit'=>$settings['monthlyCharacterLimit'],'used'=>0,'successful'=>0,'exhausted'=>false,
        'maxAutomaticCharacters'=>$settings['maxAutomaticCharacters'],'configured'=>$settings['enabled']&&$settings['googleApiKey']!=='','available'=>false];
    try {
        $usage=aetherTranslationQuery(aetherTranslationUsagePdo($pdo,$settings),'SELECT sentCharacters,successfulCharacters FROM tblTranslationUsage WHERE budgetKey=? AND monthKey=?',[$settings['budgetKey'],$month])->fetch(PDO::FETCH_ASSOC);
        $status['available']=true;$status['used']=(int)($usage['sentCharacters']??0);$status['successful']=(int)($usage['successfulCharacters']??0);
        $status['exhausted']=$status['used'] >= $status['limit'];
    } catch (PDOException $e) { if (!aetherTranslationMissingTable($e)) throw $e; }
    return $status;
}
function aetherTranslationMissingTable(PDOException $e): bool
{
    return (string)$e->getCode()==='42S02'||((string)$e->getCode()==='HY000'&&str_contains($e->getMessage(),'no such table:'));
}
function aetherTranslationFormat(string $source): string
{
    return preg_match('/<\/?(?:p|br|h[1-6]|div|span|strong|em|ul|ol|li|blockquote|u)\b/i',$source)?'html':'plain';
}
function aetherTranslationSafeHtml(string $text): string
{
    return aetherSanitizeRichText($text,['p','br','strong','em','u','ul','ol','li','h4','blockquote']);
}
function aetherTranslationGrant(array $user,string $source,string $format,?int $expires=null): string
{
    if (empty($_SESSION['aetherTranslationSecret'])) $_SESSION['aetherTranslationSecret']=bin2hex(random_bytes(32));
    $expires??=time()+3600;
    $message=$user['id'].'|'.hash('sha256',$source).'|'.$format.'|'.$expires;
    return $expires.'.'.hash_hmac('sha256',$message,$_SESSION['aetherTranslationSecret']);
}
function aetherTranslationGrantValid(array $user,string $source,string $format,string $grant): bool
{
    if (empty($_SESSION['aetherTranslationSecret'])||!preg_match('/^(\d{10})\.([a-f0-9]{64})$/D',$grant,$m)||(int)$m[1]<time()) return false;
    return hash_equals(aetherTranslationGrant($user,$source,$format,(int)$m[1]),$grant);
}
/** HTTPS only, fixed Google endpoint, key in a header (never a URL or browser response). */
function aetherGoogleTranslate(string $source,string $target,string $format,array $settings): array
{
    $body=json_encode(['q'=>$source,'target'=>$target,'format'=>$format==='html'?'html':'text','model'=>'nmt'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $headers=['Content-Type: application/json; charset=utf-8','X-Goog-Api-Key: '.$settings['googleApiKey']];
    if (!function_exists('curl_init')) throw new RuntimeException('Google-verbinding niet beschikbaar.');
    $curl=curl_init('https://translation.googleapis.com/language/translate/v2');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>$settings['requestTimeoutSeconds'],CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    if (!is_string($response)||$status!==200) throw new RuntimeException('Google-vertaling tijdelijk niet beschikbaar.');
    return aetherGoogleTranslationResponse($response,$target,$format);
}
function aetherGoogleTranslationResponse(string $response,string $target,string $format): array
{
    $row=json_decode($response,true,512,JSON_THROW_ON_ERROR)['data']['translations'][0]??null;
    if (!is_array($row)||!is_string($row['translatedText']??null)||$row['translatedText']==='') throw new RuntimeException('Ongeldig vertaalantwoord.');
    $text=$format==='html'?aetherTranslationSafeHtml($row['translatedText']):html_entity_decode($row['translatedText'],ENT_QUOTES|ENT_HTML5,'UTF-8');
    return ['text'=>$text,'sourceLanguage'=>in_array($row['detectedSourceLanguage']??'', ['nl','en'],true)?$row['detectedSourceLanguage']:($target==='en'?'nl':'en')];
}
function aetherTranslationCached(PDO $pdo,string $source,string $target): ?array
{
    $row=aetherTranslationQuery($pdo,'SELECT * FROM tblContentTranslation WHERE sourceHash=? AND targetLanguage=?',[hash('sha256',$source),$target])->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}
/** Reserve the whole charge before I/O. Failed/uncertain attempts remain counted conservatively. */
function aetherTranslationAutomatic(PDO $pdo,string $source,string $target,string $format,?array $settings=null,?callable $transport=null,?DateTimeImmutable $now=null): array
{
    $settings??=aetherTranslationSettings();$hash=hash('sha256',$source);
    if ($row=aetherTranslationCached($pdo,$source,$target)) if ($row['status']==='ready') return ['state'=>'ready','text'=>$row['translatedText'],'format'=>$row['format'],'provider'=>$row['provider']];
    if (mb_strlen($source,'UTF-8')>$settings['maxAutomaticCharacters']) return ['state'=>'manual_required'];
    if (!$settings['enabled']||$settings['googleApiKey']==='') return ['state'=>'disabled'];
    if ($transport===null&&!function_exists('curl_init')) return ['state'=>'unavailable'];
    $input=$format==='html'?aetherTranslationSafeHtml($source):$source;$count=mb_strlen($input,'UTF-8');
    if ($count>$settings['maxAutomaticCharacters']||strlen($input)>90000) return ['state'=>'manual_required'];
    if (!$count) return ['state'=>'empty'];
    $usage=aetherTranslationUsagePdo($pdo,$settings);$now??=new DateTimeImmutable();$utc=$now->setTimezone(new DateTimeZone('UTC'));
    $time=$utc->format('Y-m-d H:i:s');$retry=$utc->modify('+5 minutes')->format('Y-m-d H:i:s');$month=aetherTranslationMonth($settings,$now);$budget=$settings['budgetKey'];$token=bin2hex(random_bytes(16));
    if ($pdo->inTransaction()||$usage->inTransaction()) throw new LogicException('Vertalen mag niet binnen een speltransactie.');
    $usage->beginTransaction();
    try {
        aetherTranslationQuery($usage,'INSERT IGNORE INTO tblTranslationRequest (budgetKey,sourceHash,targetLanguage) VALUES (?,?,?)',[$budget,$hash,$target]);
        $request=aetherTranslationQuery($usage,'SELECT * FROM tblTranslationRequest WHERE budgetKey=? AND sourceHash=? AND targetLanguage=? FOR UPDATE',[$budget,$hash,$target])->fetch(PDO::FETCH_ASSOC);
        if ($request['state']==='ready') { $usage->commit();$result=['text'=>$request['translatedText'],'sourceLanguage'=>$request['sourceLanguage']]; }
        elseif ($request['retryAfter']!==null&&$request['retryAfter']>$time) { $usage->commit();return ['state'=>'pending']; }
        else {
            aetherTranslationQuery($usage,'INSERT IGNORE INTO tblTranslationUsage (budgetKey,monthKey) VALUES (?,?)',[$budget,$month]);
            $reserved=aetherTranslationQuery($usage,'UPDATE tblTranslationUsage SET sentCharacters=sentCharacters+? WHERE budgetKey=? AND monthKey=? AND sentCharacters<=?',[$count,$budget,$month,$settings['monthlyCharacterLimit']-$count]);
            if ($reserved->rowCount()!==1) { $usage->commit();return ['state'=>'quota_exhausted']; }
            aetherTranslationQuery($usage,"UPDATE tblTranslationRequest SET state='processing',attemptToken=?,retryAfter=? WHERE budgetKey=? AND sourceHash=? AND targetLanguage=?",[$token,$retry,$budget,$hash,$target]);
            $usage->commit();
            try {
                $result=($transport??'aetherGoogleTranslate')($input,$target,$format,$settings);
                if (!is_string($result['text']??null)||!is_string($result['sourceLanguage']??null)||$result['text']==='') throw new RuntimeException('Ongeldig vertaalantwoord.');
                if ($format==='html') $result['text']=aetherTranslationSafeHtml($result['text']);
                $usage->beginTransaction();
                $done=aetherTranslationQuery($usage,"UPDATE tblTranslationRequest SET state='ready',translatedText=?,sourceLanguage=?,format=?,retryAfter=NULL WHERE budgetKey=? AND sourceHash=? AND targetLanguage=? AND attemptToken=? AND state='processing'",[$result['text'],$result['sourceLanguage'],$format,$budget,$hash,$target,$token]);
                if ($done->rowCount()===1) aetherTranslationQuery($usage,'UPDATE tblTranslationUsage SET successfulCharacters=successfulCharacters+? WHERE budgetKey=? AND monthKey=?',[$count,$budget,$month]);
                $usage->commit();
            } catch (Throwable $e) {
                if ($usage->inTransaction()) $usage->rollBack();
                aetherTranslationQuery($usage,"UPDATE tblTranslationRequest SET state='failed' WHERE budgetKey=? AND sourceHash=? AND targetLanguage=? AND attemptToken=?",[$budget,$hash,$target,$token]);
                return ['state'=>'unavailable'];
            }
        }
    } catch (Throwable $e) {if($usage->inTransaction())$usage->rollBack();throw $e;}
    aetherTranslationQuery($pdo,"INSERT IGNORE INTO tblContentTranslation (sourceHash,targetLanguage,sourceLanguage,sourceText,translatedText,format,status,provider) VALUES (?,?,?,?,?,?,'ready','google-nmt')",[$hash,$target,$result['sourceLanguage'],$source,$result['text'],$format]);
    aetherTranslationQuery($pdo,"UPDATE tblContentTranslation SET sourceLanguage=?,translatedText=?,format=?,status='ready',provider='google-nmt' WHERE sourceHash=? AND targetLanguage=? AND status<>'ready'",[$result['sourceLanguage'],$result['text'],$format,$hash,$target]);
    $saved=aetherTranslationCached($pdo,$source,$target);
    return ['state'=>'ready','text'=>$saved['translatedText'],'format'=>$saved['format'],'provider'=>$saved['provider']];
}
function aetherTranslationVersion(?array $row): string
{
    return hash('sha256',json_encode($row?[$row['translatedText'],$row['sourceLanguage'],$row['format'],$row['status'],$row['provider']]:null,JSON_THROW_ON_ERROR));
}
function aetherTranslationSaveManual(PDO $pdo,string $source,string $target,string $format,string $text,string $sourceLanguage,string $version): array
{
    $row=aetherTranslationQuery($pdo,'SELECT * FROM tblContentTranslation WHERE sourceHash=? AND targetLanguage=? FOR UPDATE',[hash('sha256',$source),$target])->fetch(PDO::FETCH_ASSOC)?:null;
    if (!hash_equals(aetherTranslationVersion($row),$version)) throw new DomainException('De vertaling is ondertussen gewijzigd. Open de tekst opnieuw.');
    if ($format==='html') $text=aetherTranslationSafeHtml($text);
    if (trim(strip_tags($text))==='') throw new InvalidArgumentException('Vul een vertaling in.');
    $hash=hash('sha256',$source);
    if (!$row) aetherTranslationQuery($pdo,"INSERT INTO tblContentTranslation (sourceHash,targetLanguage,sourceLanguage,sourceText,translatedText,format,status,provider) VALUES (?,?,?,?,?,?,'ready','manual')",[$hash,$target,$sourceLanguage,$source,$text,$format]);
    else aetherTranslationQuery($pdo,"UPDATE tblContentTranslation SET sourceLanguage=?,translatedText=?,format=?,status='ready',provider='manual' WHERE sourceHash=? AND targetLanguage=?",[$sourceLanguage,$text,$format,$hash,$target]);
    return ['text'=>$text,'format'=>$format,'version'=>aetherTranslationVersion(aetherTranslationCached($pdo,$source,$target))];
}
