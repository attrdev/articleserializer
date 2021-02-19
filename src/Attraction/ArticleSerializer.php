<?php

namespace Attraction;

class ArticleSerializer
{

    const HTML_ENCODING = '<?xml encoding="UTF-8">';
    const DOM_DOCUMENT_ENCODING = 'UTF-8';
    const DOM_DOCUMENT_VERSION = '1.0';
    const VERSION = '0.2';
    const COLUMNS_COUNT = 12;

    private static function innerHTML($node, $document, $cleanWhitespaces = true)
    {

        $tagName = $node->tagName ?? null;
        $xml = $document->saveXML($node) ?? null;

        if ($cleanWhitespaces) {
            $xml = preg_replace('/[\t\r\n]\s*/S', '', $xml);
            $xml = preg_replace('/\s+/S', ' ', $xml);
        }

        $xml = preg_replace('/(<\/{0,1}' . $tagName . '.*?>)/i', '', $xml);

        if ($cleanWhitespaces) {
            $xml = trim($xml);
        }

        return $xml;
    }

    public static function unserialize($jsonPayload)
    {
        $payload = json_decode($jsonPayload);
        $blocks = $payload->blocks ?? [];

        $traverse = function ($blocks) use (&$traverse) {

            $buffer = [];

            foreach ($blocks as $block) {

                $type = $block->type ?? false;

                if ($type == 'paragraph') {
                    $text = $block->data->text ?? null;
                    $buffer[] = sprintf('<p>%s</p>', $text);
                    continue;
                }

                if ($type == 'delimiter') {
                    $buffer[] = '<hr>';
                    continue;
                }

                if ($type == 'code') {
                    $text = $block->data->text ?? null;
                    $buffer[] = sprintf('<pre>%s</pre>', $text);
                    continue;
                }

                if ($type == 'header') {
                    $text = $block->data->text ?? null;
                    $level = $block->data->level ?? 1;
                    $buffer[] = sprintf('<h%s>%s</h%s>', $level, $text, $level);
                    continue;
                }

                if ($type == 'quote') {

                    $buffer[] = '<blockquote>';

                    $content = $block->data->content ?? [];

                    foreach ($content as $quoteItem) {
                        $quoteItem = trim($quoteItem);
                        if($quoteItem == '') { continue; }
                        $buffer[] = sprintf('<p>%s</p>', $quoteItem);
                    }

                    $cite = $block->data->cite ?? null;
                    if ($cite != '') {
                        $buffer[] = sprintf('<p><cite>%s</cite></p>', $cite);
                    }

                    $buffer[] = '</blockquote>';
                    continue;
                }

                if ($type == 'layer') {

                    $content = $block->data->content ?? [];
                    $buffer[] = sprintf('<div>%s</div>', implode(PHP_EOL, $traverse($content)));
                    continue;
                }

                if ($type == 'columns') {

                    $content = $block->data->content ?? [];
                    $buffer[] = sprintf('<div class="grid">%s</div>', implode(PHP_EOL, $traverse($content)));
                    continue;
                }

                if ($type == 'column') {

                    $content = $block->data->content ?? [];
                    $size = $block->data->size ?? static::COLUMNS_COUNT;
                    $buffer[] = sprintf('<div class="column column-%s">%s</div>', $size, implode(PHP_EOL, $traverse($content)));
                    continue;
                }

                if ($type == 'embed') {
                    $content = $block->data->content ?? null;
                    $buffer[] = sprintf('<figure>%s</figure>', $content);
                    continue;
                }

                if ($type == 'image') {
                    $buffer[] = '<figure>';

                    $link = $block->data->link ?? null;

                    if (!is_null($link)) {

                        $href = $link->href ?? null;
                        $target = $link->target ?? null;

                        $attributes = ['a'];

                        if (!is_null($link->href)) {
                            $attributes[] = sprintf('href="%s"', $link->href);
                        }
                        if (!is_null($link->target)) {
                            $attributes[] = sprintf('target="%s"', $link->target);
                        }

                        $buffer[] = sprintf('<%s>', implode(' ', $attributes));
                    }

                    $attributes = ['img'];
                    $src = $block->data->src ?? null;
                    $alt = $block->data->alt ?? null;
                    $id = $block->data->id ?? null;

                    if ($src != '') {
                        $attributes[] = sprintf('src="%s"', $src);
                    }
                    if ($alt != '') {
                        $attributes[] = sprintf('alt="%s"', $alt);
                    }
                    if (!is_null($id)) {
                        $attributes[] = sprintf('data-image="%s"', $id);
                    }

                    $buffer[] = sprintf('<%s>', implode(' ', $attributes));

                    if (!is_null($link)) {
                        $buffer[] = '</a>';
                    }

                    $caption = $block->data->caption ?? null;
                    if ($caption != '') {
                        $buffer[] = sprintf('<figcaption>%s</figcaption>', $caption);
                    }

                    $buffer[] = '</figure>';
                    continue;
                }
            }

            return $buffer;
        };

        $buffer = $traverse($blocks) ?? [];

        return implode(PHP_EOL, $buffer);
    }

    public static function serialize($html, $prettyPrint = true)
    {

        $document = new \DOMDocument(static::DOM_DOCUMENT_VERSION, static::DOM_DOCUMENT_ENCODING);
        @$document->loadHTML(static::HTML_ENCODING . $html);

        $traverse = function ($node) use (&$traverse, $document) {

            $blocks = [];

            if (!$node->hasChildNodes()) {
                return $blocks;
            }

            foreach ($node->childNodes as $childNode) {

                $tagName = $childNode->tagName ?? null;

                if (in_array($tagName, ['html', 'body'])) {
                    return $traverse($childNode);
                }

                if ($tagName == 'p') {
                    $blocks[] = [
                        'type' => 'paragraph',
                        'data' => [
                            'text' => static::innerHTML($childNode, $document)
                        ]
                    ];
                    continue;
                }

                if ($tagName == 'hr') {
                    $blocks[] = [
                        'type' => 'delimiter'
                    ];
                    continue;
                }

                if ($tagName == 'pre') {
                    $blocks[] = [
                        'type' => 'code',
                        'data' => [
                            'text' => trim(static::innerHTML($childNode, $document, false))
                        ]
                    ];
                    continue;
                }

                if (strlen($tagName) == 2 && preg_match('/h([1-6])/i', $tagName, $matches)) {

                    $headingSize = (int) $matches[1];
                    if ($headingSize < 1) {
                        $headingSize = 1;
                    }
                    if ($headingSize > 6) {
                        $headingSize = 6;
                    }

                    $blocks[] = [
                        'type' => 'header',
                        'data' => [
                            'text' => static::innerHTML($childNode, $document),
                            'level' => $headingSize
                        ]
                    ];
                    continue;
                }

                if ($tagName == 'blockquote') {

                    if (!$childNode->hasChildNodes()) {
                        continue;
                    }

                    $content = [];
                    $cite = null;

                    foreach ($childNode->childNodes as $quoteNode) {

                        if (($quoteNode->tagName ?? null) == 'p') {

                            foreach ($quoteNode->childNodes as $quoteParagraphNode) {

                                if (get_class($quoteParagraphNode) == 'DOMText') {
                                    $content[] = static::innerHTML($quoteParagraphNode, $document);
                                    continue;
                                }

                                if (($quoteParagraphNode->tagName ?? null) == 'cite') {
                                    $cite = static::innerHTML($quoteParagraphNode, $document);
                                    continue;
                                }
                            }
                        }
                    }

                    if (count($content) > 0) {
                        $block = [
                            'type' => 'quote',
                            'data' => [
                                'content' => array_filter($content)
                            ]
                        ];
                        if (!is_null($cite)) {
                            $block['data']['cite'] = $cite;
                        }
                        $blocks[] = $block;
                    }

                    continue;
                }

                if ($tagName == 'figure') {

                    if (!$childNode->hasChildNodes()) {
                        continue;
                    }

                    $type = 'embed';
                    $data = [];

                    $parseImage = function ($figureNode) {

                        $data = [];

                        $data['src'] = $figureNode->getAttribute('src') ?? null;

                        $alt = $figureNode->getAttribute('alt') ?? null;

                        $id = $figureNode->getAttribute('data-image') ?? null;
                        if (!is_null($id)) {
                            $data['id'] = $id;
                        }

                        if (!is_null($alt)) {
                            $data['alt'] = $alt;
                        }

                        return $data;
                    };

                    foreach ($childNode->childNodes as $figureNode) {

                        $figureNodeTagName = $figureNode->tagName ?? null;

                        if ($figureNodeTagName == 'img') {

                            $type = 'image';
                            $data = array_merge($data, $parseImage($figureNode));
                            continue;
                        }

                        if ($figureNodeTagName == 'figcaption') {
                            $data['caption'] = static::innerHTML($figureNode, $document);
                            continue;
                        }

                        if ($figureNodeTagName == 'a') {

                            $href = $figureNode->getAttribute('href') ?? null;

                            if ($href != '') {
                                $data['link'] = [
                                    'href' => $href
                                ];

                                $target = $figureNode->getAttribute('target') ?? null;
                                if ($target != '') {
                                    $data['link']['target'] = $target;
                                }
                            }

                            if ($figureNode->hasChildNodes()) {
                                foreach ($figureNode->childNodes as $figureNodeChildNode) {
                                    $figureNodeChildNodeTagName = $figureNodeChildNode->tagName ?? null;
                                    if ($figureNodeChildNodeTagName == 'img') {
                                        $type = 'image';
                                        $data = array_merge($data, $parseImage($figureNodeChildNode));
                                    }
                                }
                            }

                            continue;
                        }
                    }

                    if ($type == 'embed') {
                        $block = [
                            'type' => 'embed',
                            'data' => [
                                'content' => static::innerHTML($childNode, $document)
                            ]
                        ];
                    } else {
                        $block = [
                            'type' => 'image',
                            'data' => $data
                        ];
                    }

                    $blocks[] = $block;
                    continue;
                }

                if ($tagName == 'div') {

                    $type = 'layer';

                    $classList = array_map('trim', explode(' ', $childNode->getAttribute('class') ?? null));

                    if (in_array('grid', $classList)) {
                        $type = 'columns';
                    }

                    if (in_array('column', $classList)) {
                        $type = 'column';
                        $columnSize = 0;
                        foreach ($classList as $class) {
                            if (preg_match('/column-([0-9]{1,2})/i', $class, $matches)) {
                                $columnSize = (int) $matches[1];
                            }
                        }
                    }

                    $block = [
                        'type' => $type,
                        'data' => ['content' => $traverse($childNode)]
                    ];

                    if (isset($columnSize)) {
                        $block['data']['size'] = $columnSize;
                        $block['data']['total'] = static::COLUMNS_COUNT;
                    }

                    $blocks[] = $block;
                    continue;
                }
            }

            return $blocks;
        };

        $blocks = $traverse($document);
        $checksum = sha1(json_encode($blocks));

        $output = (object) [
            'version' => static::VERSION,
            'time' => time(),
            'checksum' => $checksum,
            'blocks' => $blocks
        ];

        return json_encode($output, ($prettyPrint ? JSON_PRETTY_PRINT : null));
    }
}
