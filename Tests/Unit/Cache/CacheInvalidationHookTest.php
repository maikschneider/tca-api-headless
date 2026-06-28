<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Cache;

use MaikSchneider\TcaApiHeadless\Cache\CacheInvalidationHook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class CacheInvalidationHookTest extends TestCase
{
    #[Test]
    public function flushesByTagKeys(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())
            ->method('flushByTags')
            ->with(['pages_2', 'tt_content_5']);

        $hook = new CacheInvalidationHook($cache);
        $hook->clearCachePostProc(['tags' => ['pages_2' => true, 'tt_content_5' => true]]);
    }

    #[Test]
    public function doesNothingWithoutTags(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::never())->method('flushByTags');

        (new CacheInvalidationHook($cache))->clearCachePostProc([]);
    }
}
