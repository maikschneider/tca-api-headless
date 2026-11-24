<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Navigation;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * With the response cache of headless_navigation turned on by the
 * navigation_cache fixture extension, a requested depth is never answered
 * with a tree cached for another depth.
 */
final class NavigationDepthCacheTest extends AbstractApiTestCase
{
    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        'maikschneider/tca-api-headless',
        __DIR__ . '/../Fixtures/Extensions/navigation_cache',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_navigation.csv');
    }

    #[Test]
    public function aRequestWithDepthBypassesTheCachedTree(): void
    {
        $url = 'http://localhost/_api/headless_navigation/70';
        self::assertSame('MISS', $this->request($url)->getHeaderLine('X-TCA-API-Cache'));
        self::assertSame('HIT', $this->request($url)->getHeaderLine('X-TCA-API-Cache'));

        $response = $this->request($url . '?depth=1');

        self::assertSame('', $response->getHeaderLine('X-TCA-API-Cache'));
        self::assertSame([], $this->json($response)['children'][0]['children']);
    }
}
