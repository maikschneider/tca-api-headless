<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Contract;

/**
 * Constants describing the headless page contract emitted by this extension.
 *
 * The version is surfaced in every page payload as `contract` and is bumped
 * only on breaking changes; additive changes (new block types, new optional
 * keys) keep the same version by design.
 */
final class Contract
{
    /**
     * Current contract version, emitted as the `contract` key of a page payload.
     */
    public const VERSION = '1.0';
}
