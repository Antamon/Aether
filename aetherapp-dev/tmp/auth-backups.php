<?php
declare(strict_types=1);
$webroot=dirname($base);
foreach (['aether-oud','aether-x','backup'] as $folder) {
    $dir=$webroot.'/'.$folder;
    if (!is_dir($dir)) continue;
    echo json_encode(['directory'=>$folder,'entries'=>array_values(array_diff(scandir($dir),['.','..']))]).PHP_EOL;
    $iterator=new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),static fn($item)=>!$item->isDir()||!preg_match('/^(vendor|node_modules|\.git|wp-content|wp-includes|wp-admin|images|img|css|js)$/',$item->getFilename())),RecursiveIteratorIterator::SELF_FIRST);
    $iterator->setMaxDepth(4);$count=0;
    foreach($iterator as $file) if($file->isFile()&&preg_match('/^(db\.php|config\.local\.php|db\.legacy\.local\.php)$|(?:aether.*\.(?:zip|tar|gz))$/i',$file->getFilename())) {
        echo json_encode(['candidate'=>$file->getPathname(),'size'=>$file->getSize(),'modified'=>date('c',$file->getMTime()),'sha256'=>$file->getSize()<100000?hash_file('sha256',$file->getPathname()):null]).PHP_EOL;
        if(++$count>=80) break;
    }
}
