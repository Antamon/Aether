<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Build safe HTML templates for the offline content importer. Nothing is published.
require_once __DIR__ . '/../api/shared/richText.php';
$entries = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach ($entries as &$entry) {
    $source = $entry['source'];
    if (!$entry['rich']) { $entry['template'] = null; $entry['parts'] = [$source]; continue; }
    $allowed = ['p','br','strong','em','ul','ol','li','h4'];
    if ($entry['research']) $allowed = [...$allowed, 'u', 'blockquote'];
    $entry['sanitized'] = aetherSanitizeRichText($source, $allowed);
    $document = new DOMDocument('1.0', 'UTF-8');
    @$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $entry['sanitized'] . '</body></html>');
    $entry['parts'] = [];
    foreach ((new DOMXPath($document))->query('//body//text()') as $node) {
        if (trim($node->nodeValue) === '') continue;
        $entry['parts'][] = $node->nodeValue;
        $node->nodeValue = 'AETHERTRANSLATIONPART' . (count($entry['parts']) - 1) . 'END';
    }
    $body = $document->getElementsByTagName('body')->item(0);
    $entry['template'] = '';
    foreach ($body->childNodes as $node) $entry['template'] .= $document->saveHTML($node);
}
unset($entry);
echo json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
