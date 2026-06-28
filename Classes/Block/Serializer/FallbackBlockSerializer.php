<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Block\Serializer;

use MaikSchneider\HeadlessPages\Block\BlockContext;
use MaikSchneider\HeadlessPages\Block\BlockSerializerInterface;

/**
 * Catch-all serializer for content elements without a dedicated serializer.
 *
 * It exposes a safe subset of common fields under the element's raw CType, so
 * an unmapped content element never breaks the page — the frontend can render
 * a placeholder or skip an unknown `type`.
 */
final class FallbackBlockSerializer implements BlockSerializerInterface
{
    public function supports(array $row): bool
    {
        return true;
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $data = [];
        if (($row['header'] ?? '') !== '') {
            $data['headline'] = (string)$row['header'];
        }
        if (($row['bodytext'] ?? '') !== '') {
            $data['bodytext'] = (string)$row['bodytext'];
        }

        return [
            'type' => (string)($row['CType'] ?? 'unknown'),
            'id' => (int)($row['uid'] ?? 0),
            'data' => $data === [] ? new \stdClass() : $data,
        ];
    }

    public function getPriority(): int
    {
        return -100;
    }
}
