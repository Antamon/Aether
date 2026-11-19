<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/shared/contentLanguage.php';
function checkContent(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE tblContentTranslation (sourceHash TEXT, targetLanguage TEXT, translatedText TEXT, format TEXT, status TEXT, PRIMARY KEY (sourceHash,targetLanguage))');
$insert = $pdo->prepare('INSERT INTO tblContentTranslation VALUES (?,?,?,?,?)');
foreach ([
    ['Speuren','en','Investigation','plain'], ['Investigate','nl','Onderzoeken','plain'],
    ['Een verborgen geheim.','en','A hidden secret.','plain'],
    ['<p>Je vindt <strong>een brief</strong>.</p>','en','<p>You find <strong>a letter</strong>.</p><script>alert(1)</script>','html'],
] as [$source,$language,$target,$format]) $insert->execute([hash('sha256',$source),$language,$target,$format,'ready']);
$data = ['skills'=>[['id'=>6,'name'=>'Speuren','code'=>'Speuren']], 'characters'=>[['id'=>8,'name'=>'Speuren']], 'current'=>['content'=>'<p>Je vindt <strong>een brief</strong>.</p>'], 'qrToken'=>'Een verborgen geheim.'];
$response = aetherContentResponse($pdo,$data,'en');
checkContent($response['aetherPayload'] === $data, 'Sources, IDs and machine values must be unchanged.');
checkContent(count($response['aetherContentTranslations']) === 2, 'Only authorized text fields may receive metadata.');
checkContent(!str_contains(json_encode($response),'A hidden secret.'), 'A hidden level absent from the authorized response cannot leak through translations.');
$html = array_values(array_filter($response['aetherContentTranslations'], fn($entry) => $entry['format'] === 'html'))[0]['text'];
checkContent(str_contains($html,'<strong>a letter</strong>') && !str_contains($html,'script'), 'Rich translation uses the existing sanitizer.');
checkContent(aetherContentResponse($pdo,['content'=>'Gewijzigde feedback.'],'en') === ['content'=>'Gewijzigde feedback.'], 'Changed sources cannot use an old translation.');
checkContent(aetherContentResponse($pdo,['name'=>'Speuren'],'fr') === ['name'=>'Speuren'], 'Unsupported languages leave the contract unchanged.');
checkContent(aetherContentResponse($pdo,['characters'=>[['name'=>'Speuren']]],'en') === ['characters'=>[['name'=>'Speuren']]], 'Personal names are not translated.');
checkContent(count(aetherContentResponse($pdo,['holders'=>[['name'=>'Unchanged person','specialisations'=>['Speuren']]]],'en')['aetherContentTranslations']) === 1, 'Specialisation lists inside person records must be translated.');
checkContent(aetherContentResponse($pdo,['content'=>'Investigate'],'nl')['aetherContentTranslations'][0]['text'] === 'Onderzoeken', 'Originally English content has a Dutch version.');
$pdo->exec("UPDATE tblContentTranslation SET status='pending'");
checkContent(aetherContentResponse($pdo,['name'=>'Speuren'],'en') === ['name'=>'Speuren'], 'Pending content is never displayed.');
$pdo->exec('DROP TABLE tblContentTranslation');
checkContent(aetherContentResponse($pdo,$data,'en') === $data, 'Deploying PHP before the migration remains usable.');
echo "PASS: content language, authorized response isolation, source preservation, versioning and HTML safety\n";
