<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block;

/**
 * Holds all block serializers, sorted by descending priority, and dispatches a
 * tt_content row to the first one that supports it.
 */
final class BlockSerializerRegistry
{
    /**
     * @var list<BlockSerializerInterface>
     */
    private array $serializers;

    /**
     * @param iterable<BlockSerializerInterface> $serializers
     */
    public function __construct(iterable $serializers)
    {
        $list = $serializers instanceof \Traversable ? iterator_to_array($serializers) : (array)$serializers;
        usort($list, static fn(BlockSerializerInterface $a, BlockSerializerInterface $b): int => $b->getPriority() <=> $a->getPriority());
        $this->serializers = array_values($list);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed> The block envelope.
     */
    public function serialize(array $row, BlockContext $context): array
    {
        foreach ($this->serializers as $serializer) {
            if ($serializer->supports($row)) {
                return $serializer->serialize($row, $context);
            }
        }

        // Unreachable while the FallbackBlockSerializer is registered, but guard explicitly.
        throw new \RuntimeException(
            sprintf('No block serializer supported content element uid %s.', $row['uid'] ?? '?'),
            1751000000,
        );
    }
}
