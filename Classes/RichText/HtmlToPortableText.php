<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\RichText;

/**
 * Converts RTE HTML into Portable Text.
 *
 * Blocks: p (normal), h1-h6, ul/ol items (bullet/number, nested lists raise
 * the level); paragraphs and lists inside a blockquote get the style blockquote.
 * Container elements such as div are unwrapped, inline
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
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
    ];

    private const CONTAINERS = ['p', 'blockquote', 'div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'figure', 'li', 'ul', 'ol', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'dl', 'dt', 'dd', 'pre', 'hr', 'address', 'figcaption', 'nav'];

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
     * content between them become paragraphs.
     *
     * @param string $paragraphStyle style of paragraphs, list items and loose inline content
     * @return list<array<string, mixed>>
     */
    private function blocks(\DOMNode $container, int $listLevel, string $paragraphStyle = 'normal'): array
    {
        $blocks = [];
        $inline = [];
        foreach ($container->childNodes as $node) {
            $tag = $node instanceof \DOMElement ? strtolower($node->tagName) : null;
            if ($tag === null || !self::isBlock($tag)) {
                $inline[] = $node;
                continue;
            }

            $blocks = [
                ...$blocks,
                ...$this->textBlock($inline, $paragraphStyle),
                ...$this->blockElement($node, $tag, $listLevel, $paragraphStyle),
            ];
            $inline = [];
        }

        return [...$blocks, ...$this->textBlock($inline, $paragraphStyle)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockElement(\DOMElement $element, string $tag, int $listLevel, string $paragraphStyle): array
    {
        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->textBlock(iterator_to_array($element->childNodes, false), self::BLOCK_STYLES[$tag]),
            'p' => $this->textBlock(iterator_to_array($element->childNodes, false), $paragraphStyle),
            'pre' => $this->textBlock(iterator_to_array($element->childNodes, false), $paragraphStyle, true),
            'blockquote' => $this->blocks($element, $listLevel, 'blockquote'),
            'ul', 'ol' => $this->listItems($element, $tag === 'ol' ? 'number' : 'bullet', $listLevel, $paragraphStyle),
            default => $this->blocks($element, $listLevel, $paragraphStyle),
        };
    }

    /**
     * Each li becomes one block; paragraphs inside an item are separated by a
     * line break. A nested list follows with a higher level, in document order:
     * content after it continues as another item of the outer level.
     *
     * @return list<array<string, mixed>>
     */
    private function listItems(\DOMElement $list, string $listItem, int $level, string $paragraphStyle): array
    {
        $blocks = [];
        foreach ($list->childNodes as $item) {
            if (!$item instanceof \DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }

            $inline = [];
            foreach ($item->childNodes as $node) {
                $tag = $node instanceof \DOMElement ? strtolower($node->tagName) : null;
                if ($tag !== 'ul' && $tag !== 'ol') {
                    $inline[] = $node;
                    continue;
                }

                $blocks = [
                    ...$blocks,
                    ...$this->listItem($inline, $listItem, $level, $paragraphStyle),
                    ...$this->listItems($node, $tag === 'ol' ? 'number' : 'bullet', $level + 1, $paragraphStyle),
                ];
                $inline = [];
            }
            $blocks = [...$blocks, ...$this->listItem($inline, $listItem, $level, $paragraphStyle)];
        }

        return $blocks;
    }

    /**
     * @param list<\DOMNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function listItem(array $nodes, string $listItem, int $level, string $paragraphStyle): array
    {
        return array_map(
            static fn (array $block): array => [...$block, 'listItem' => $listItem, 'level' => $level],
            $this->textBlock($nodes, $paragraphStyle),
        );
    }

    private static function isBlock(string $tag): bool
    {
        return isset(self::BLOCK_STYLES[$tag]) || \in_array($tag, self::CONTAINERS, true);
    }

    /**
     * One block from inline nodes; none when they hold no text.
     *
     * @param list<\DOMNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function textBlock(array $nodes, string $style, bool $preformatted = false): array
    {
        $markDefs = [];
        $spans = [];
        foreach ($nodes as $node) {
            $spans = [...$spans, ...$this->spans($node, [], $markDefs, $preformatted)];
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
     * @param bool $preformatted inside pre: whitespace is kept as is
     * @return list<array{marks: list<string>, text: string, pre: bool, break?: bool}>
     */
    private function spans(\DOMNode $node, array $marks, array &$markDefs, bool $preformatted = false): array
    {
        if ($node instanceof \DOMText) {
            $text = $preformatted
                ? str_replace(["\r\n", "\r"], "\n", $node->textContent)
                : preg_replace('/[ \t\r\n]+/', ' ', $node->textContent) ?? '';

            return $text === '' ? [] : [['marks' => $marks, 'text' => $text, 'pre' => $preformatted]];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $tag = strtolower($node->tagName);
        if (\in_array($tag, ['script', 'style', 'template'], true)) {
            return [];
        }
        if ($tag === 'br') {
            return [['marks' => $marks, 'text' => "\n", 'pre' => $preformatted]];
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

        // A block element inside inline content (a paragraph in a list item) is a line of its own.
        $break = ['marks' => $marks, 'text' => "\n", 'pre' => false, 'break' => true];
        $spans = [];
        foreach ($node->childNodes as $child) {
            $spans = [...$spans, ...$this->spans($child, $marks, $markDefs, $preformatted || $tag === 'pre')];
        }

        return self::isBlock($tag) ? [$break, ...$spans, $break] : $spans;
    }

    /**
     * Drops the whitespace HTML doesn't render at the start and end of a block
     * and merges neighbouring spans with the same marks. Preformatted text keeps
     * its whitespace apart from trailing whitespace at the end of the block.
     *
     * @param list<array{marks: list<string>, text: string, pre: bool, break?: bool}> $spans
     * @return list<array{marks: list<string>, text: string, pre: bool, break?: bool}>
     */
    private function trimmed(array $spans): array
    {
        $merged = [];
        $atLineStart = true;
        foreach ($spans as $span) {
            if (($span['break'] ?? false) && $atLineStart) {
                continue;
            }
            if ($span['pre']) {
                // HTML ignores a line break right after the opening pre tag.
                $span['text'] = $merged === [] ? (preg_replace('/^\n/', '', $span['text']) ?? '') : $span['text'];
            } elseif ($merged === []) {
                $span['text'] = ltrim($span['text'], " \n");
            } elseif ($atLineStart || str_ends_with($merged[array_key_last($merged)]['text'], ' ')) {
                $span['text'] = ltrim($span['text'], ' ');
            }
            if ($span['text'] === '') {
                continue;
            }
            $previous = array_key_last($merged);
            if ($previous !== null && !$span['pre'] && !$merged[$previous]['pre'] && str_starts_with($span['text'], "\n")) {
                $merged[$previous]['text'] = rtrim($merged[$previous]['text'], ' ');
            }
            $atLineStart = str_ends_with($span['text'], "\n");

            $last = array_key_last($merged);
            if ($last !== null && $merged[$last]['marks'] === $span['marks'] && $merged[$last]['pre'] === $span['pre']) {
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
