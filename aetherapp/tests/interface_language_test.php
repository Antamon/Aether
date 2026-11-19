<?php
declare(strict_types=1);
require_once __DIR__.'/../api/shared/interfaceLanguage.php';
function languageCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
foreach(['nl','nl_BE','nl-NL','NL']as $locale)languageCheck(aetherNormalizeInterfaceLanguage($locale)==='nl','Dutch locale: '.$locale);
foreach(['en','en_GB','en-US','EN_au']as $locale)languageCheck(aetherNormalizeInterfaceLanguage($locale)==='en','English locale: '.$locale);
foreach(['','fr_FR','invalid',null]as $locale)languageCheck(aetherNormalizeInterfaceLanguage($locale)==='nl','Unsupported locale falls back to Dutch');
$profileLocale='en_GB';$requested=[];
function get_user_locale(int $id):string{global $profileLocale,$requested;$requested[]=$id;return $profileLocale;}
languageCheck(aetherProfileInterfaceLanguage(42)==='en','WordPress profile locale is used');
$profileLocale='nl_BE';languageCheck(aetherProfileInterfaceLanguage(7)==='nl','Different user gets their own locale');
languageCheck($requested===[42,7],'Only the supplied authenticated ids are looked up');
foreach(['checkLogin.php','getCurrentUser.php']as $file){$source=file_get_contents(__DIR__.'/../'.$file);languageCheck(str_contains($source,"'interfaceLanguage' => aetherProfileInterfaceLanguage((int) \$user['id'])"),'Language returned by '.$file);}
echo "PASS: WordPress profile language, supported locales, fallback and both session endpoints.\n";
