<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @param list<string> $allowedElements
 */
function aetherSanitizeRichText(string $html, array $allowedElements): string
{
    if (trim($html) === '') {
        return '';
    }

    $normalized = aetherNormalizeLegacyRichText($html);
    $cacheKey = implode(',', $allowedElements);

    /** @var array<string, HTMLPurifier> $purifiers */
    static $purifiers = [];
    if (!isset($purifiers[$cacheKey])) {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', $cacheKey);
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('Cache.DefinitionImpl', null);
        $purifiers[$cacheKey] = new HTMLPurifier($config);
    }

    return trim($purifiers[$cacheKey]->purify($normalized));
}

function aetherNormalizeLegacyRichText(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previousErrorMode = libxml_use_internal_errors(true);
    try {
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="aether-rich-text-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        if (!$loaded) {
            return '';
        }

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="aether-rich-text-root"]')?->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }

        $dangerousNodes = $xpath->query(
            '//script|//style|//iframe|//object|//embed|//form|//input|//button|//img|//svg|//math|//template|//link|//meta|//base'
        );
        if ($dangerousNodes !== false) {
            foreach (iterator_to_array($dangerousNodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $legacyDivs = $xpath->query('//div[not(@id="aether-rich-text-root")]');
        if ($legacyDivs !== false) {
            foreach (iterator_to_array($legacyDivs) as $div) {
                aetherReplaceRichTextElement($document, $div, 'p');
            }
        }

        $unwrappedElements = $xpath->query('//span|//a');
        if ($unwrappedElements !== false) {
            foreach (iterator_to_array($unwrappedElements) as $element) {
                $parent = $element->parentNode;
                if ($parent === null) {
                    continue;
                }
                while ($element->firstChild !== null) {
                    $parent->insertBefore($element->firstChild, $element);
                }
                $parent->removeChild($element);
            }
        }

        foreach (['b' => 'strong', 'i' => 'em'] as $legacyTag => $semanticTag) {
            $nodes = $xpath->query('//' . $legacyTag);
            if ($nodes === false) {
                continue;
            }
            foreach (iterator_to_array($nodes) as $node) {
                aetherReplaceRichTextElement($document, $node, $semanticTag);
            }
        }

        aetherWrapTopLevelRichText($document, $root);

        $normalized = '';
        foreach (iterator_to_array($root->childNodes) as $childNode) {
            $normalized .= $document->saveHTML($childNode);
        }
        return $normalized;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);
    }
}

function aetherWrapTopLevelRichText(DOMDocument $document, DOMElement $root): void
{
    $blockElements = ['p', 'h4', 'ul', 'ol'];
    $paragraph = null;

    foreach (iterator_to_array($root->childNodes) as $node) {
        if ($node instanceof DOMText && trim($node->textContent) === '') {
            $root->removeChild($node);
            continue;
        }

        if ($node instanceof DOMElement && in_array(strtolower($node->tagName), $blockElements, true)) {
            $paragraph = null;
            continue;
        }

        if (!$paragraph instanceof DOMElement) {
            $paragraph = $document->createElement('p');
            $root->insertBefore($paragraph, $node);
        }
        $paragraph->appendChild($node);
    }
}

function aetherReplaceRichTextElement(DOMDocument $document, DOMNode $node, string $replacementTag): void
{
    $parent = $node->parentNode;
    if ($parent === null) {
        return;
    }

    $replacement = $document->createElement($replacementTag);
    while ($node->firstChild !== null) {
        $replacement->appendChild($node->firstChild);
    }
    $parent->replaceChild($replacement, $node);
}
