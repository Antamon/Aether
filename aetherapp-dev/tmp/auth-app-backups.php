<?php
declare(strict_types=1);
$iterator=new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),static fn($item)=>!$item->isDir()||!preg_match('/^(vendor|node_modules|\.git|img|images|fonts|css|js|wp-content|feedback-runtime|feedback-browser|data|lib)$/',$item->getFilename())),RecursiveIteratorIterator::SELF_FIRST);
$iterator->setMaxDepth(6);$count=0;
foreach($iterator as $file) if($file->isFile()&&preg_match('/^(?:db(?:\.legacy\.local)?\.php(?:\.(?:bak|old))?|config\.local\.php(?:\.(?:bak|old))?)$/i',$file->getFilename())){
    echo json_encode(['candidate'=>$file->getPathname(),'size'=>$file->getSize(),'sha256'=>$file->getSize()<100000?hash_file('sha256',$file->getPathname()):null]).PHP_EOL;
    if(++$count>=100)break;
}
// Only compare the hosting database hostname; do not read WordPress credentials.
$wp=file_get_contents(dirname($base).'/wp-config.php');
if(preg_match('/define\(\s*[\'"]DB_HOST[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/',$wp,$m)){
    $prod=require $base.'/aetherapp/config.local.php';
    echo json_encode(['wordpressHostMatchesAppHost'=>($prod['database']['host']??null)===$m[1],'wordpressHostIsLocal'=>in_array($m[1],['localhost','127.0.0.1'],true)]).PHP_EOL;
}
