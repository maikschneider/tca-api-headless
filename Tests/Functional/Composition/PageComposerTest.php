<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Functional\Composition;

use MaikSchneider\HeadlessPages\Composition\PageComposer;
use MaikSchneider\HeadlessPages\Tests\Functional\AbstractHeadlessTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PageComposerTest extends AbstractHeadlessTestCase
{
    private function defaultSite(): Site
    {
        return new Site('test', 1, [
            'base' => '/',
            'languages' => [
                ['languageId' => 0, 'locale' => 'en_US.UTF-8', 'base' => '/'],
                ['languageId' => 1, 'locale' => 'de_DE.UTF-8', 'base' => '/de/'],
            ],
        ]);
    }

    #[Test]
    public function composesPageEnvelopeWithMeta(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $payload = $this->get(PageComposer::class)->compose(2, $this->defaultSite()->getDefaultLanguage());

        self::assertNotNull($payload);
        self::assertSame('1.0', $payload['contract']);
        self::assertSame('page', $payload['type']);
        self::assertSame(2, $payload['id']);
        self::assertSame('Team', $payload['meta']['title']);
        self::assertSame('en', $payload['meta']['language']);
        self::assertSame('/team', $payload['meta']['slug']);
        // SEO + schema.org meta (EXT:seo defaults).
        self::assertSame('index,follow', $payload['meta']['seo']['robots']);
        self::assertSame('WebPage', $payload['meta']['schema']['@type']);
        self::assertInstanceOf(\stdClass::class, $payload['regions']);
    }

    #[Test]
    public function overlaysTranslatedTitleAndSlug(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $german = $this->defaultSite()->getLanguageById(1);
        $payload = $this->get(PageComposer::class)->compose(2, $german);

        self::assertNotNull($payload);
        self::assertSame('Team (DE)', $payload['meta']['title']);
        self::assertSame('de', $payload['meta']['language']);
        self::assertSame('/de/team', $payload['meta']['slug']);
    }

    #[Test]
    public function cachesComposedPageAndInvalidatesByTag(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $composer = $this->get(PageComposer::class);
        $language = $this->defaultSite()->getDefaultLanguage();

        self::assertSame('Team', $composer->compose(2, $language)['meta']['title']);

        // Mutate the record directly, bypassing cache invalidation.
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['title' => 'Changed'], ['uid' => 2]);

        // The cached payload is still served — proving the cache is used.
        self::assertSame('Team', $composer->compose(2, $language)['meta']['title']);

        // Flushing the page tag invalidates the entry.
        GeneralUtility::makeInstance(CacheManager::class)
            ->getCache('headless_pages')
            ->flushByTag('pages_2');

        self::assertSame('Changed', $composer->compose(2, $language)['meta']['title']);
    }

    #[Test]
    public function returnsNullForMissingPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        self::assertNull(
            $this->get(PageComposer::class)->compose(999, $this->defaultSite()->getDefaultLanguage()),
        );
    }

    #[Test]
    public function emptyPageHasObjectRegions(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $payload = $this->get(PageComposer::class)->compose(1, $this->defaultSite()->getDefaultLanguage());

        self::assertNotNull($payload);
        self::assertInstanceOf(\stdClass::class, $payload['regions']);
    }

    #[Test]
    public function composesRegionsFromContentElements(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');

        $payload = $this->get(PageComposer::class)->compose(2, $this->defaultSite()->getDefaultLanguage());

        self::assertNotNull($payload);
        $regions = $payload['regions'];
        self::assertIsArray($regions);
        self::assertArrayHasKey('main', $regions);
        self::assertArrayHasKey('left', $regions);

        // colPos 0 → "main", ordered by sorting.
        self::assertCount(2, $regions['main']);
        self::assertSame('text', $regions['main'][0]['type']);
        self::assertSame(1, $regions['main'][0]['id']);
        self::assertSame('Welcome', $regions['main'][0]['data']['headline']);
        // The real TextBlockSerializer (not the fallback) is wired via DI: bodytext is Portable Text.
        self::assertSame('Hello', $regions['main'][0]['data']['body'][0]['children'][0]['text']);
        self::assertSame(2, $regions['main'][1]['id']);

        // colPos 1 → "left".
        self::assertCount(1, $regions['left']);
        self::assertSame('Sidebar', $regions['left'][0]['data']['headline']);
    }
}
