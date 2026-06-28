<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerInterface;
use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;

/**
 * Serializes the "bullets" content element into a `list` block:
 * `{ headline?, items: PortableText[] }`.
 *
 * The bullets bodytext holds one item per line; each item is converted to its
 * own Portable Text array.
 */
final class ListBlockSerializer implements BlockSerializerInterface
{
    public function __construct(
        private readonly HtmlToPortableText $htmlToPortableText,
    ) {
    }

    public function supports(array $row): bool
    {
        return ($row['CType'] ?? '') === 'bullets';
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $data = [];
        if (($row['header'] ?? '') !== '') {
            $data['headline'] = (string)$row['header'];
        }

        $data['items'] = $this->items((string)($row['bodytext'] ?? ''));

        return [
            'type' => 'list',
            'id' => (int)($row['uid'] ?? 0),
            'data' => $data,
        ];
    }

    public function getPriority(): int
    {
        return 10;
    }

    /**
     * @return list<list<array<string, mixed>>>
     */
    private function items(string $bodytext): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $bodytext) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $items[] = $this->htmlToPortableText->convert($line);
        }

        return $items;
    }
}
