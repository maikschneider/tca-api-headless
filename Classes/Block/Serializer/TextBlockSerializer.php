<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Block\Serializer;

use MaikSchneider\HeadlessPages\Block\BlockContext;
use MaikSchneider\HeadlessPages\Block\BlockSerializerInterface;
use MaikSchneider\HeadlessPages\RichText\HtmlToPortableText;

/**
 * Serializes the "text" content element into a `text` block:
 * `{ headline?, body: PortableText }`.
 */
final class TextBlockSerializer implements BlockSerializerInterface
{
    public function __construct(
        private readonly HtmlToPortableText $htmlToPortableText,
    ) {
    }

    public function supports(array $row): bool
    {
        return ($row['CType'] ?? '') === 'text';
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $data = [];
        if (($row['header'] ?? '') !== '') {
            $data['headline'] = (string)$row['header'];
        }
        $data['body'] = $this->htmlToPortableText->convert((string)($row['bodytext'] ?? ''));

        return [
            'type' => 'text',
            'id' => (int)($row['uid'] ?? 0),
            'data' => $data,
        ];
    }

    public function getPriority(): int
    {
        return 10;
    }
}
