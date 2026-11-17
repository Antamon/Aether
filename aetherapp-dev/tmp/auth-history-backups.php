<?php
declare(strict_types=1);
$history=getcwd().'/.vscode-server/data/User/History';
if(!is_dir($history)){echo json_encode(['editorHistoryAvailable'=>false]).PHP_EOL;exit;}
$count=0;
foreach(glob($history.'/*/entries.json') as $file){
    $entry=json_decode(file_get_contents($file),true);$resource=urldecode($entry['resource']??'');
    if(!preg_match('~/aether/(aetherapp|aetherapp-dev)/(?:db\.php|config\.local\.php)$~',$resource))continue;
    $versions=[];foreach($entry['entries']??[] as $version){$saved=dirname($file).'/'.$version['id'];if(is_file($saved))$versions[]=['path'=>$saved,'timestamp'=>$version['timestamp']??null,'size'=>filesize($saved),'sha256'=>hash_file('sha256',$saved)];}
    echo json_encode(['resource'=>$resource,'versions'=>$versions]).PHP_EOL;$count++;
}
echo json_encode(['matchingConfigHistories'=>$count]).PHP_EOL;
