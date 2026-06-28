<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block;

/**
 * Turns a single tt_content row into one block of the headless contract.
 *
 * Implementations are auto-tagged via Services.yaml and consulted in
 * descending priority order; the first whose {@see supports()} returns true
 * wins. A serializer returns the full block envelope (type, id, data, …) so it
 * owns its own semantic `type` name.
 */
interface BlockSerializerInterface
{
    /**
     * @param array<string, mixed> $row
     */
    public function supports(array $row): bool;

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed> The complete block envelope.
     */
    public function serialize(array $row, BlockContext $context): array;

    /**
     * Higher priority serializers are consulted first. The fallback uses the
     * lowest priority so specific serializers always take precedence.
     */
    public function getPriority(): int;
}
