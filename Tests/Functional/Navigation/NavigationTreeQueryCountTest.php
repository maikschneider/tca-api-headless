<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Navigation;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use MaikSchneider\TcaApiHeadless\Tests\Functional\QueryCounting\CountingDriverMiddleware;
use MaikSchneider\TcaApiHeadless\Tests\Functional\QueryCounting\QueryCounter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Counts the queries from right before the page handler runs to the response
 * (query_counter fixture extension) for navigation roots below page 1 with
 * subpage trees of different width and height. The site "main" reads two
 * levels.
 */
final class NavigationTreeQueryCountTest extends AbstractApiTestCase
{
    /** Root uid => subpages per level. */
    private const TREES = [
        100 => [],
        110 => [2],
        120 => [2, 2],
        150 => [10, 5],
        300 => [2, 2, 2],
    ];

    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        'maikschneider/tca-api-headless',
        __DIR__ . '/../Fixtures/Extensions/query_counter',
    ];

    protected array $configurationToUseInTestInstance = [
        'DB' => [
            'Connections' => [
                'Default' => [
                    'driverMiddlewares' => [
                        'tca-api-headless/query-counter' => ['target' => CountingDriverMiddleware::class],
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTrees();
    }

    #[Test]
    public function everyLevelCostsOneQuery(): void
    {
        $counts = $this->queryCounts('http://localhost/_api/headless_navigation/');

        self::assertSame($counts[100] + 1, $counts[110], json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertSame($counts[110], $counts[120], json_encode($counts, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function theNumberOfQueriesDoesNotDependOnTheNumberOfPages(): void
    {
        $counts = $this->queryCounts('http://localhost/_api/headless_navigation/');

        self::assertSame($counts[120], $counts[150], json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertSame($counts[120], $counts[300], json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertLessThanOrEqual(8, $counts[120], json_encode($counts, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function aTranslationCostsOneMoreQueryPerLevel(): void
    {
        $counts = $this->queryCounts('http://localhost/de/_api/headless_navigation/');

        self::assertSame($counts[100] + 2, $counts[110], json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertSame($counts[120], $counts[150], json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertSame($counts[120], $counts[300], json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertLessThanOrEqual(13, $counts[120], json_encode($counts, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<int, int> query count by root uid
     */
    private function queryCounts(string $baseUrl): array
    {
        $counts = [];
        foreach (array_keys(self::TREES) as $rootUid) {
            $response = $this->request($baseUrl . $rootUid);
            $counts[$rootUid] = QueryCounter::stop();

            self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
            self::assertSame(self::TREES[$rootUid] === [] ? 0 : self::TREES[$rootUid][0], \count($this->json($response)['children']));
        }

        return $counts;
    }

    /**
     * Every page with a German translation, whose uid is the default uid + 1000.
     */
    private function createTrees(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $insert = static function (int $uid, int $pid, int $sorting) use ($connection): void {
            foreach ([0, 1] as $language) {
                $connection->insert('pages', [
                    'uid' => $uid + 1000 * $language,
                    'pid' => $pid,
                    'title' => 'Page ' . $uid,
                    'slug' => '/page-' . $uid,
                    'doktype' => 1,
                    'sorting' => $sorting,
                    'sys_language_uid' => $language,
                    'l10n_parent' => $language === 0 ? 0 : $uid,
                ]);
            }
        };

        foreach (self::TREES as $rootUid => $widths) {
            $insert($rootUid, 1, $rootUid);
            $nextUid = $rootUid + 1;
            $parents = [$rootUid];
            foreach ($widths as $width) {
                $children = [];
                foreach ($parents as $parent) {
                    for ($i = 0; $i < $width; $i++) {
                        $insert($nextUid, $parent, $i);
                        $children[] = $nextUid++;
                    }
                }
                $parents = $children;
            }
        }
    }
}
