<?php
declare(strict_types=1);
$iterator=new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),static fn($item)=>!$item->isDir()||!preg_match('/^(aetherapp|aetherapp-dev|vendor|node_modules|\.git|img|images|fonts|css|js|lib|webfonts|\.php.*)$/',$item->getFilename())));
$iterator->setMaxDepth(4);$count=0;
foreach($iterator as $file)if($file->isFile()&&strtolower($file->getExtension())==='php'&&$file->getSize()<100000){
    $source=file_get_contents($file->getPathname());
    if(preg_match('/new\s+PDO\s*\(|mysqli_connect\s*\(|new\s+mysqli\s*\(/i',$source)){
        echo json_encode(['candidate'=>$file->getPathname(),'size'=>$file->getSize(),'modified'=>date('c',$file->getMTime()),'sha256'=>hash('sha256',$source)]).PHP_EOL;
        if(++$count>=30)break;
    }
}
echo json_encode(['connectionSources'=>$count]).PHP_EOL;
