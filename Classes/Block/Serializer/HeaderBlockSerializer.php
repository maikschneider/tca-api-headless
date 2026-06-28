<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Block\Serializer;

use MaikSchneider\HeadlessPages\Block\BlockContext;
use MaikSchneider\HeadlessPages\Block\BlockSerializerInterface;
use MaikSchneider\HeadlessPages\Link\TypoLinkResolver;

/**
 * Serializes the "header" content element into a `header` block:
 * `{ headline, subheadline?, link? }`.
 */
final class HeaderBlockSerializer implements BlockSerializerInterface
{
    public function __construct(
        private readonly TypoLinkResolver $typoLinkResolver,
    ) {
    }

    public function supports(array $row): bool
    {
        return ($row['CType'] ?? '') === 'header';
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $data = [
            'headline' => (string)($row['header'] ?? ''),
        ];

        if (($row['subheader'] ?? '') !== '') {
            $data['subheadline'] = (string)$row['subheader'];
        }

        $link = $this->typoLinkResolver->resolve((string)($row['header_link'] ?? ''));
        if ($link !== null) {
            $data['link'] = $link;
        }

        return [
            'type' => 'header',
            'id' => (int)($row['uid'] ?? 0),
            'data' => $data,
        ];
    }

    public function getPriority(): int
    {
        return 10;
    }
}
