<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Page;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use MaikSchneider\TcaApiHeadless\Tests\Functional\QueryCounting\CountingDriverMiddleware;
use MaikSchneider\TcaApiHeadless\Tests\Functional\QueryCounting\QueryCounter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Counts the queries from right before the page handler runs to the response
 * (query_counter fixture extension), for pages with 1, 10 and 50 content
 * elements of the site "main", whose fallback types add a second pass.
 */
final class ComposePageQueryCountTest extends AbstractApiTestCase
{
    private const CONTENT_COUNTS = [60 => 1, 61 => 10, 62 => 50];

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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_file.csv');
    }

    #[Test]
    public function contentWithoutMediaCostsAConstantNumberOfQueries(): void
    {
        $this->createPages(withMedia: false);

        $counts = $this->queryCounts();

        self::assertCount(1, array_unique($counts), json_encode($counts, JSON_THROW_ON_ERROR));
        // Two of them are the page's own: its og_image and its URL for the canonical.
        self::assertLessThanOrEqual(12, max($counts), json_encode($counts, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function contentWithMediaCostsAConstantNumberOfQueries(): void
    {
        $this->createPages(withMedia: true);

        $first = $this->queryCount(array_key_first(self::CONTENT_COUNTS));
        $counts = $this->queryCounts();

        self::assertLessThanOrEqual(19, $first);
        self::assertCount(1, array_unique($counts), json_encode($counts, JSON_THROW_ON_ERROR));
        self::assertLessThanOrEqual(19, max($counts), json_encode($counts, JSON_THROW_ON_ERROR));
    }

    /**
     * @return non-empty-array<int, int> query count by number of content elements
     */
    private function queryCounts(): array
    {
        $counts = [];
        foreach (self::CONTENT_COUNTS as $pageUid => $contentCount) {
            $counts[$contentCount] = $this->queryCount($pageUid);
        }

        return $counts;
    }

    private function queryCount(int $pageUid): int
    {
        $response = $this->request('http://localhost/_api/headless_pages/' . $pageUid);
        $count = QueryCounter::stop();

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertCount(self::CONTENT_COUNTS[$pageUid], $this->json($response)['regions']['main']);

        return $count;
    }

    /**
     * Text elements, or text and media elements with two file references each.
     */
    private function createPages(bool $withMedia): void
    {
        $connectionPool = $this->getConnectionPool();
        $contentUid = 1000;
        foreach (self::CONTENT_COUNTS as $pageUid => $contentCount) {
            $connectionPool->getConnectionForTable('pages')->insert('pages', [
                'uid' => $pageUid,
                'pid' => 1,
                'title' => 'Content ' . $contentCount,
                'slug' => '/content-' . $contentCount,
                'doktype' => 1,
            ]);
            for ($i = 0; $i < $contentCount; $i++) {
                $contentUid++;
                $connectionPool->getConnectionForTable('tt_content')->insert('tt_content', [
                    'uid' => $contentUid,
                    'pid' => $pageUid,
                    'sorting' => $i,
                    'CType' => $withMedia ? 'textmedia' : 'text',
                    'header' => 'Content ' . $contentUid,
                    'bodytext' => '<p>Text of <strong>' . $contentUid . '</strong></p>',
                    'assets' => $withMedia ? 2 : 0,
                ]);
                if (!$withMedia) {
                    continue;
                }
                foreach ([1, 2] as $position) {
                    $connectionPool->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', [
                        'pid' => $pageUid,
                        'uid_local' => 1,
                        'uid_foreign' => $contentUid,
                        'tablenames' => 'tt_content',
                        'fieldname' => 'assets',
                        'sorting_foreign' => $position,
                    ]);
                }
            }
        }
    }
}
