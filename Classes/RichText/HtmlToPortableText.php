<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\RichText;

/**
 * Converts RTE HTML into Portable Text.
 *
 * Blocks: p (normal), h1-h6, blockquote, ul/ol items (bullet/number, nested
 * lists raise the level). Container elements such as div are unwrapped, inline
 * content outside a block becomes a normal block. Decorators: strong/b, em/i,
 * u (underline), s/strike/del (strike-through), code. Links become link
 * annotations; br becomes a line break. Script and style elements are dropped,
 * any other tag is unwrapped to its text, so the result never contains markup.
 * Keys are deterministic per call.
 *
 * @see https://www.portabletext.org/specification/
 */
final class HtmlToPortableText
{
    private const BLOCK_STYLES = [
        'p' => 'normal',
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
        'blockquote' => 'blockquote',
    ];

    private const CONTAINERS = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'figure', 'li', 'ul', 'ol', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'dl', 'dt', 'dd', 'pre', 'hr', 'address', 'figcaption', 'nav'];

    private const DECORATORS = [
        'strong' => 'strong',
        'b' => 'strong',
        'em' => 'em',
        'i' => 'em',
        'u' => 'underline',
        's' => 'strike-through',
        'strike' => 'strike-through',
        'del' => 'strike-through',
        'code' => 'code',
    ];

    private int $keyCounter = 0;

    /** @var (\Closure(string): ?string)|null */
    private ?\Closure $resolveHref = null;

    /**
     * @param (\Closure(string): ?string)|null $resolveHref maps a link's href to the
     *        href to output; a link it maps to null keeps its text without annotation
     * @return list<array<string, mixed>>
     */
    public function convert(string $html, ?\Closure $resolveHref = null): array
    {
        $this->keyCounter = 0;
        $this->resolveHref = $resolveHref;
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);

        return $root === null ? [] : $this->blocks($root, 1);
    }

    /**
     * Blocks of a container: block elements become blocks, runs of inline
     * content between them become normal blocks.
     *
     * @return list<array<string, mixed>>
     */
    private function blocks(\DOMNode $container, int $listLevel): array
    {
        $blocks = [];
        $inline = [];
        foreach ($container->childNodes as $node) {
            $tag = $node instanceof \DOMElement ? strtolower($node->tagName) : null;
            if ($tag === null || (!isset(self::BLOCK_STYLES[$tag]) && !\in_array($tag, self::CONTAINERS, true))) {
                $inline[] = $node;
                continue;
            }

            $blocks = [...$blocks, ...$this->textBlock($inline, 'normal'), ...$this->blockElement($node, $tag, $listLevel)];
            $inline = [];
        }

        return [...$blocks, ...$this->textBlock($inline, 'normal')];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockElement(\DOMElement $element, string $tag, int $listLevel): array
    {
        if (isset(self::BLOCK_STYLES[$tag])) {
            return $this->textBlock(iterator_to_array($element->childNodes, false), self::BLOCK_STYLES[$tag]);
        }

        if ($tag === 'ul' || $tag === 'ol') {
            return $this->listItems($element, $tag === 'ol' ? 'number' : 'bullet', $listLevel);
        }

        return $this->blocks($element, $listLevel);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listItems(\DOMElement $list, string $listItem, int $level): array
    {
        $blocks = [];
        foreach ($list->childNodes as $item) {
            if (!$item instanceof \DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }

            $inline = [];
            $nested = [];
            foreach ($item->childNodes as $node) {
                $tag = $node instanceof \DOMElement ? strtolower($node->tagName) : null;
                if ($tag === 'ul' || $tag === 'ol') {
                    $nested = [...$nested, ...$this->listItems($node, $tag === 'ol' ? 'number' : 'bullet', $level + 1)];
                } else {
                    $inline[] = $node;
                }
            }

            foreach ($this->textBlock($inline, 'normal') as $block) {
                $blocks[] = [...$block, 'listItem' => $listItem, 'level' => $level];
            }
            $blocks = [...$blocks, ...$nested];
        }

        return $blocks;
    }

    /**
     * One block from inline nodes; none when they hold no text.
     *
     * @param list<\DOMNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function textBlock(array $nodes, string $style): array
    {
        $markDefs = [];
        $spans = [];
        foreach ($nodes as $node) {
            $spans = [...$spans, ...$this->spans($node, [], $markDefs)];
        }

        $spans = $this->trimmed($spans);
        if ($spans === []) {
            return [];
        }

        $key = $this->nextKey();

        return [[
            '_type' => 'block',
            '_key' => $key,
            'style' => $style,
            'markDefs' => $markDefs,
            'children' => array_map(
                fn (array $span): array => ['_type' => 'span', '_key' => $this->nextKey(), 'marks' => $span['marks'], 'text' => $span['text']],
                $spans,
            ),
        ]];
    }

    /**
     * @param list<string> $marks
     * @param list<array{_key: string, _type: string, href: string}> $markDefs
     * @return list<array{marks: list<string>, text: string}>
     */
    private function spans(\DOMNode $node, array $marks, array &$markDefs): array
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/[ \t\r\n]+/', ' ', $node->textContent) ?? '';

            return $text === '' ? [] : [['marks' => $marks, 'text' => $text]];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $tag = strtolower($node->tagName);
        if (\in_array($tag, ['script', 'style', 'template'], true)) {
            return [];
        }
        if ($tag === 'br') {
            return [['marks' => $marks, 'text' => "\n"]];
        }

        if (isset(self::DECORATORS[$tag]) && !\in_array(self::DECORATORS[$tag], $marks, true)) {
            $marks[] = self::DECORATORS[$tag];
        } elseif ($tag === 'a') {
            $href = $node->getAttribute('href');
            $href = $this->resolveHref !== null ? ($this->resolveHref)($href) : $href;
            if ($href !== null && $href !== '') {
                $key = $this->nextKey();
                $markDefs[] = ['_key' => $key, '_type' => 'link', 'href' => $href];
                $marks[] = $key;
            }
        }

        $spans = [];
        foreach ($node->childNodes as $child) {
            $spans = [...$spans, ...$this->spans($child, $marks, $markDefs)];
        }

        return $spans;
    }

    /**
     * Drops the whitespace HTML doesn't render at the start and end of a block
     * and merges neighbouring spans with the same marks.
     *
     * @param list<array{marks: list<string>, text: string}> $spans
     * @return list<array{marks: list<string>, text: string}>
     */
    private function trimmed(array $spans): array
    {
        $merged = [];
        $atLineStart = true;
        foreach ($spans as $span) {
            if ($atLineStart || str_ends_with($merged[array_key_last($merged)]['text'] ?? '', ' ')) {
                $span['text'] = ltrim($span['text'], ' ');
            }
            if ($span['text'] === '') {
                continue;
            }
            $atLineStart = str_ends_with($span['text'], "\n");

            $last = array_key_last($merged);
            if ($last !== null && $merged[$last]['marks'] === $span['marks']) {
                $merged[$last]['text'] .= $span['text'];
            } else {
                $merged[] = $span;
            }
        }

        while ($merged !== []) {
            $last = array_key_last($merged);
            $merged[$last]['text'] = rtrim($merged[$last]['text'], " \n");
            if ($merged[$last]['text'] !== '') {
                break;
            }
            array_pop($merged);
        }

        return trim(implode('', array_column($merged, 'text'))) === '' ? [] : $merged;
    }

    private function nextKey(): string
    {
        return 'k' . $this->keyCounter++;
    }
}
