<?php
declare(strict_types=1);
foreach(['/economie/php/dbconnect.php','/functions.php']as$file){
    $tokens=token_get_all(file_get_contents($base.$file));$variables=[];$strings=[];
    foreach($tokens as $t)if(is_array($t)&&$t[0]===T_VARIABLE)$variables[$t[1]]=true;
    echo json_encode(['file'=>$file,'variables'=>array_keys($variables)]).PHP_EOL;
}
