<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\RichText;

/**
 * Converts RTE HTML (a tt_content bodytext) into a Portable Text array.
 *
 * Supported HTML:
 *  - <p>                       → block, style "normal"
 *  - <h1>…<h6>                 → block, style "h1"…"h6"
 *  - <ul>/<ol> with <li>       → blocks with listItem "bullet"/"number"
 *  - <strong>/<b>, <em>/<i>    → "strong" / "em" decorators
 *  - <a href>                  → link annotation (markDef)
 *  - <br>                      → newline within a span
 *
 * Unknown inline tags are unwrapped (their text is kept); unknown block tags
 * are treated as a normal block. Keys are deterministic per conversion so the
 * output is stable and testable.
 *
 * @see https://www.portabletext.org/specification/
 */
final class HtmlToPortableText
{
    private int $keyCounter = 0;

    /**
     * @return list<array<string, mixed>> A Portable Text array (empty when the input is blank).
     */
    public function convert(string $html): array
    {
        $this->keyCounter = 0;
        if (trim($html) === '') {
            return [];
        }

        $root = $this->parse($html);
        if ($root === null) {
            return [];
        }

        $blocks = [];
        foreach ($root->childNodes as $node) {
            $blocks = array_merge($blocks, $this->blocksFromNode($node));
        }

        return array_values($blocks);
    }

    private function parse(string $html): ?\DOMNode
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $divs = $dom->getElementsByTagName('div');
        return $divs->item(0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocksFromNode(\DOMNode $node): array
    {
        if ($node instanceof \DOMText) {
            return trim($node->textContent) === '' ? [] : [$this->block('normal', $node)];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $tag = strtolower($node->tagName);

        if ($tag === 'ul' || $tag === 'ol') {
            return $this->listBlocks($node, $tag === 'ol' ? 'number' : 'bullet');
        }

        if (preg_match('/^h([1-6])$/', $tag) === 1) {
            return [$this->block($tag, $node)];
        }

        // <p> and any other block-level element become a normal block.
        return [$this->block('normal', $node)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listBlocks(\DOMElement $list, string $listItem): array
    {
        $blocks = [];
        foreach ($list->childNodes as $item) {
            if ($item instanceof \DOMElement && strtolower($item->tagName) === 'li') {
                $blocks[] = $this->block('normal', $item, $listItem);
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function block(string $style, \DOMNode $node, ?string $listItem = null): array
    {
        $markDefs = [];
        $children = $this->spans($node, [], $markDefs);
        if ($children === []) {
            $children = [$this->span('', [])];
        }

        $block = [
            '_type' => 'block',
            '_key' => $this->nextKey(),
            'style' => $style,
            'markDefs' => array_values($markDefs),
            'children' => $children,
        ];

        if ($listItem !== null) {
            $block['listItem'] = $listItem;
            $block['level'] = 1;
        }

        return $block;
    }

    /**
     * @param list<string> $marks
     * @param list<array<string, mixed>> $markDefs
     * @return list<array<string, mixed>>
     */
    private function spans(\DOMNode $node, array $marks, array &$markDefs): array
    {
        $spans = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                if ($child->textContent !== '') {
                    $spans[] = $this->span($child->textContent, $marks);
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                $spans[] = $this->span("\n", $marks);
                continue;
            }

            $childMarks = $marks;
            if ($tag === 'strong' || $tag === 'b') {
                $childMarks[] = 'strong';
            } elseif ($tag === 'em' || $tag === 'i') {
                $childMarks[] = 'em';
            } elseif ($tag === 'a') {
                $key = $this->nextKey();
                $markDefs[] = [
                    '_key' => $key,
                    '_type' => 'link',
                    'href' => $child->getAttribute('href'),
                ];
                $childMarks[] = $key;
            }

            $spans = array_merge($spans, $this->spans($child, $childMarks, $markDefs));
        }

        return $spans;
    }

    /**
     * @param list<string> $marks
     * @return array<string, mixed>
     */
    private function span(string $text, array $marks): array
    {
        return [
            '_type' => 'span',
            '_key' => $this->nextKey(),
            'marks' => array_values($marks),
            'text' => $text,
        ];
    }

    private function nextKey(): string
    {
        return 'k' . $this->keyCounter++;
    }
}
