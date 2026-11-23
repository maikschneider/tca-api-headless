<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\QueryCounting;

use MaikSchneider\TcaApi\Event\BeforeOperationEvent;

/**
 * Starts {@see QueryCounter} right before the operation handler runs, so a count
 * read after the request covers the handler and its listeners only.
 * Registered by the query_counter fixture extension.
 */
final class StartCountingBeforeOperation
{
    public function __invoke(BeforeOperationEvent $event): void
    {
        QueryCounter::start();
    }
}
