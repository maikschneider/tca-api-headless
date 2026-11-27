<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Cache;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * The fixture sites, with tca_api_headless.cache.enabled turned on for the
 * site "main" (http://localhost/). The other sites keep the default: off.
 */
abstract class AbstractCacheTestCase extends AbstractApiTestCase
{
    protected array $pathsToLinkInTestInstance = [];

    private const SITES = [
        'typo3conf/ext/tca_api_headless/Tests/Functional/Fixtures/Sites' => 'typo3conf/sites',
        'typo3conf/ext/tca_api_headless/Tests/Functional/Fixtures/Cache/main-settings.yaml' => 'typo3conf/sites/main/settings.yaml',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Copied for every test: FunctionalTestCase::tearDown() removes site configurations that aren't linked.
        (new Testbase())->providePathsInTestInstance($this->instancePath, self::SITES);
        $this->get(CacheManager::class)->getCache('tca_api')->flush();
    }

    /**
     * Requests a path of the site "main" that answers with 200.
     *
     * @param 'MISS'|'HIT' $cache the expected X-TCA-API-Cache header
     * @return array<string, mixed>
     */
    protected function fetch(string $path, string $cache): array
    {
        $response = $this->request('http://localhost' . $path);
        self::assertSame(200, $response->getStatusCode(), $path . ': ' . $response->getBody());
        self::assertSame($cache, $response->getHeaderLine('X-TCA-API-Cache'), $path);

        return $this->json($response);
    }

    /**
     * Caches the response of a path: a MISS, then a HIT with the same body.
     *
     * @return array<string, mixed>
     */
    protected function warm(string $path): array
    {
        $data = $this->fetch($path, 'MISS');
        self::assertSame($data, $this->fetch($path, 'HIT'), $path);

        return $data;
    }

    /**
     * @param array<string, mixed> $page a headless_pages show response
     * @return list<string>
     */
    protected static function headersIn(array $page, string $region = 'main'): array
    {
        return array_column($page['regions'][$region] ?? [], 'header');
    }

    /**
     * @param array<string, mixed> $node a headless_navigation show response or one of its children
     * @return list<string>
     */
    protected static function childTitles(array $node): array
    {
        return array_column($node['children'], 'title');
    }

    /**
     * @param array<string, mixed> $collection a path lookup response
     * @return list<int>
     */
    protected static function memberUids(array $collection): array
    {
        return array_column($collection['hydra:member'], 'uid');
    }
}
