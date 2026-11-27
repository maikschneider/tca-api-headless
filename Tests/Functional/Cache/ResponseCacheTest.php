<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Cache;

use PHPUnit\Framework\Attributes\Test;

/**
 * The response cache is turned on per site, and the path of a lookup and the
 * depth of a navigation are part of the cache key.
 */
final class ResponseCacheTest extends AbstractCacheTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_composition.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content_composition.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_navigation.csv');
    }

    #[Test]
    public function aSiteWithTheSettingCachesPagesLookupsAndNavigation(): void
    {
        self::assertSame(['First in main', 'Second in main', 'Listed fallback', 'All languages'], self::headersIn($this->warm('/_api/headless_pages/4')));
        self::assertSame(['Zweiter im Hauptbereich', 'All languages'], self::headersIn($this->warm('/de/_api/headless_pages/4')));
        self::assertSame([2], self::memberUids($this->warm('/_api/headless_pages?path=/about')));
        self::assertContains('Products', self::childTitles($this->warm('/_api/headless_navigation/70')));
    }

    #[Test]
    public function aSiteWithoutTheSettingIsNotCached(): void
    {
        foreach (['/_api/headless_pages/30', '/_api/headless_pages?path=/second-child', '/_api/headless_navigation/30'] as $path) {
            foreach ([1, 2] as $request) {
                $response = $this->request('http://second.localhost' . $path);
                self::assertSame(200, $response->getStatusCode(), $path);
                self::assertFalse($response->hasHeader('X-TCA-API-Cache'), $path);
            }
        }

        self::assertSame(0, $this->getConnectionPool()->getConnectionForTable('cache_tca_api')->count('*', 'cache_tca_api', []));
    }

    #[Test]
    public function eachPathIsCachedOnItsOwn(): void
    {
        $this->warm('/_api/headless_pages?path=/about');

        self::assertSame([4], self::memberUids($this->fetch('/_api/headless_pages?path=/composed', 'MISS')));
        self::assertSame([], self::memberUids($this->fetch('/_api/headless_pages?path=/unknown', 'MISS')));
        self::assertSame([2], self::memberUids($this->fetch('/_api/headless_pages?path=/about', 'HIT')));
        self::assertSame([4], self::memberUids($this->fetch('/_api/headless_pages?path=/composed', 'HIT')));
    }

    #[Test]
    public function eachDepthIsCachedOnItsOwn(): void
    {
        $defaultDepth = $this->warm('/_api/headless_navigation/70');

        $oneLevel = $this->fetch('/_api/headless_navigation/70?depth=1', 'MISS');
        $threeLevels = $this->fetch('/_api/headless_navigation/70?depth=3', 'MISS');

        self::assertSame(['Product A'], self::childTitles($defaultDepth['children'][0]));
        self::assertSame([], self::childTitles($oneLevel['children'][0]));
        self::assertSame(['Product A details'], self::childTitles($threeLevels['children'][0]['children'][0]));
        self::assertSame($oneLevel, $this->fetch('/_api/headless_navigation/70?depth=1', 'HIT'));
        self::assertSame($threeLevels, $this->fetch('/_api/headless_navigation/70?depth=3', 'HIT'));
        self::assertSame($defaultDepth, $this->fetch('/_api/headless_navigation/70', 'HIT'));
    }
}
