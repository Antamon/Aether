<?php
declare(strict_types=1);

/** Only values already present in an authorized response may obtain a translation. */
function aetherContentResponse(PDO $pdo, mixed $data, string $language): mixed
{
    if (!in_array($language, ['nl', 'en'], true) || !is_array($data)) return $data;
    $fields = ['name','description','beginner','professional','master','content','goals','achievements',
        'gossip1','gossip2','gossip3','personal_background','knowledge','nature','demeanour',
        'traitGroup','groupLabel','trackLabel','leftModuleLabel','functionDescription','resultTitle','resultText','label','title',
        'skillName','skillTypeName','specialisationName','traitName','discoveryTitle','challengeTitle',
        'companyTypeLabel','companyTypeDescription','professionLabel','nationality','value','text'];
    $sources = [];
    $walk = static function (mixed $value, string $key = '', array $path = []) use (&$walk, &$sources, $fields): void {
        $contexts = array_values(array_filter($path, static fn(string $item): bool => $item !== '' && !ctype_digit($item)));
        $parent = $contexts ? end($contexts) : '';
        if (is_array($value)) {
            foreach ($value as $childKey => $child) $walk($child, (string)$childKey, [...$path, $key]);
        } elseif (is_string($value) && trim($value) !== '' && (in_array($key, $fields, true)
            || (ctype_digit($key) && in_array($parent, ['specialisations','disciplines','categories','types'], true)))) {
            // Names of people and companies are identifiers, not translatable game terminology.
            if ($key === 'name' && in_array($parent, ['characters','recipients','users','holders','shareholders','companies','personnel'], true)) return;
            $sources[hash('sha256', $value)] = $value;
        }
    };
    $walk($data);
    if (!$sources) return $data;
    $translations = []; $covered = []; $formats = [];
    try {
        foreach (array_chunk(array_keys($sources), 200) as $hashes) {
            $placeholders = implode(',', array_fill(0, count($hashes), '?'));
            $statement = $pdo->prepare("SELECT * FROM tblContentTranslation
                WHERE targetLanguage = ? AND sourceHash IN ($placeholders) AND status = 'ready'");
            $statement->execute([$language, ...$hashes]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $covered[$row['sourceHash']] = true;
                $formats[$row['sourceHash']] = $row['format'];
                $source = $sources[$row['sourceHash']] ?? null;
                if ($source !== null && $row['translatedText'] !== $source) {
                    $text = (string)$row['translatedText'];
                    if ($row['format'] === 'html') {
                        require_once __DIR__ . '/richText.php';
                        $text = aetherSanitizeRichText($text, ['p','br','strong','em','u','ul','ol','li','h4','blockquote']);
                    }
                    $translations[] = ['source' => $source, 'text' => $text, 'format' => $row['format'], 'provider'=>$row['provider']??''];
                }
            }
        }
    } catch (PDOException $error) {
        // Rollout remains usable before the migration is applied; never damage the source response.
        $code = (string)$error->getCode();
        if ($code !== '42S02' && !($code === 'HY000' && str_contains($error->getMessage(), 'no such table: tblContentTranslation'))) throw $error;
        return $data;
    }
    $envelope = ['aetherPayload'=>$data,'aetherContentTranslations'=>$translations];
    $user = $GLOBALS['AETHER_AUTHENTICATED_USER'] ?? null;
    if (is_array($user)) {
        require_once __DIR__.'/translationService.php';
        try {
            $settings=aetherTranslationSettings();
            $manager=in_array($user['role'],['administrator','director'],true);
            $editable=[];$missing=[];
            foreach ($sources as $hash=>$source) {
                $format=$formats[$hash]??aetherTranslationFormat($source);
                $long=mb_strlen($source,'UTF-8')>$settings['maxAutomaticCharacters'];
                if (!$manager && (isset($covered[$hash])||$long||!$settings['enabled']||$settings['googleApiKey']==='')) continue;
                $entry=['hash'=>$hash,'source'=>$source,'format'=>$format,'grant'=>aetherTranslationGrant($user,$source,$format),'manualRequired'=>$long];
                if ($manager) $editable[]=$entry;
                if (!isset($covered[$hash])&&!$long&&$settings['enabled']&&$settings['googleApiKey']!=='') $missing[]=$entry;
            }
            if ($manager) {$envelope['aetherEditableContent']=$editable;$envelope['aetherTranslationUsage']=aetherTranslationStatus($pdo,$settings);}
            if ($missing) $envelope['aetherMissingContent']=$missing;
        } catch (Throwable $e) { /* Translation setup can never interrupt an authorized game read. */ }
    }
    return count($envelope)>2 || $translations ? $envelope : $data;
}
