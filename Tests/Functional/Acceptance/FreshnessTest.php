<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Acceptance;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;

/**
 * Without the response cache, every request reflects the records as they are:
 * edits and scheduled transitions show up on the next request without a cache
 * flush, and nothing is written to the cache. The clock is moved through the
 * date aspect and the simulated access time of core's restrictions.
 */
final class FreshnessTest extends AbstractApiTestCase
{
    /** 2100-01-01, the starttime of the scheduled fixtures. */
    private const FUTURE = 4102444800;

    /** @var array<string, mixed> */
    private array $clock = [];

    private AspectInterface $dateAspect;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = array_intersect_key($GLOBALS, array_flip(['EXEC_TIME', 'SIM_EXEC_TIME', 'SIM_ACCESS_TIME']));
        $this->dateAspect = $this->get(Context::class)->getAspect('date');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_composition.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content_composition.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_navigation.csv');
    }

    protected function tearDown(): void
    {
        foreach ($this->clock as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        $this->get(Context::class)->setAspect('date', $this->dateAspect);
        parent::tearDown();
    }

    #[Test]
    public function anEditShowsUpOnTheNextRequest(): void
    {
        self::assertContains('First in main', $this->headersOf(4));
        self::assertSame([2], $this->lookUp('/about'));

        $this->update('tt_content', 201, ['header' => 'Edited header']);
        $this->update('pages', 4, ['title' => 'Edited title']);
        $this->update('pages', 2, ['slug' => '/edited-about']);

        self::assertContains('Edited header', $this->headersOf(4));
        self::assertSame('Edited title', $this->fetch('/_api/headless_pages/4')['title']);
        self::assertSame([], $this->lookUp('/about'));
        self::assertSame([2], $this->lookUp('/edited-about'));
        $this->assertNothingCached();
    }

    #[Test]
    public function scheduledContentAndPagesAppearOnceTheirStarttimeHasPassed(): void
    {
        self::assertNotContains('Scheduled', $this->headersOf(4));
        self::assertNotContains('Scheduled', $this->navigationTitles(70));
        self::assertSame([], $this->lookUp('/navigation/scheduled'));

        $this->moveClockTo(self::FUTURE + 60);

        self::assertContains('Scheduled', $this->headersOf(4));
        self::assertContains('Scheduled', $this->navigationTitles(70));
        self::assertSame([82], $this->lookUp('/navigation/scheduled'));
        $this->assertNothingCached();
    }

    #[Test]
    public function contentAndPagesDisappearOnceTheirEndtimeHasPassed(): void
    {
        $this->update('tt_content', 201, ['endtime' => self::FUTURE]);
        $this->update('pages', 71, ['endtime' => self::FUTURE]);
        self::assertContains('First in main', $this->headersOf(4));
        self::assertContains('Products', $this->navigationTitles(70));
        self::assertSame([71], $this->lookUp('/navigation/products'));

        $this->moveClockTo(self::FUTURE + 60);

        self::assertNotContains('First in main', $this->headersOf(4));
        self::assertNotContains('Products', $this->navigationTitles(70));
        self::assertSame([], $this->lookUp('/navigation/products'));
        self::assertSame(404, $this->request('http://localhost/_api/headless_pages/71')->getStatusCode());
        $this->assertNothingCached();
    }

    /**
     * @return list<string>
     */
    private function headersOf(int $pageUid): array
    {
        return array_column($this->fetch('/_api/headless_pages/' . $pageUid)['regions']['main'] ?? [], 'header');
    }

    /**
     * @return list<string>
     */
    private function navigationTitles(int $rootUid): array
    {
        return array_column($this->fetch('/_api/headless_navigation/' . $rootUid)['children'], 'title');
    }

    /**
     * @return list<int>
     */
    private function lookUp(string $path): array
    {
        return array_column($this->fetch('/_api/headless_pages?path=' . rawurlencode($path))['hydra:member'], 'uid');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $path): array
    {
        $response = $this->request('http://localhost' . $path);
        self::assertSame(200, $response->getStatusCode(), $path . ': ' . $response->getBody());
        self::assertFalse($response->hasHeader('X-TCA-API-Cache'));

        return $this->json($response);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function update(string $table, int $uid, array $values): void
    {
        $this->getConnectionPool()->getConnectionForTable($table)->update($table, $values, ['uid' => $uid]);
    }

    private function moveClockTo(int $timestamp): void
    {
        // Core's start and end time restrictions read the simulated access time.
        $GLOBALS['EXEC_TIME'] = $GLOBALS['SIM_EXEC_TIME'] = $GLOBALS['SIM_ACCESS_TIME'] = $timestamp;
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . $timestamp)));
    }

    private function assertNothingCached(): void
    {
        self::assertSame(0, $this->getConnectionPool()->getConnectionForTable('cache_tca_api')->count('*', 'cache_tca_api', []));
    }
}
