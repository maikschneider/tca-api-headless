<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Unit\Meta;

use MaikSchneider\HeadlessPages\Link\TypoLinkResolver;
use MaikSchneider\HeadlessPages\Meta\SeoMetaBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

final class SeoMetaBuilderTest extends TestCase
{
    private function subject(): SeoMetaBuilder
    {
        $linkService = self::createStub(LinkService::class);
        $linkService->method('resolve')->willReturn([]);
        $fileRepository = self::createStub(FileRepository::class);
        $fileRepository->method('findByRelation')->willReturn([]);

        return new SeoMetaBuilder(
            new TypoLinkResolver($linkService, self::createStub(SiteFinder::class)),
            $fileRepository,
        );
    }

    private function language(): SiteLanguage
    {
        return self::createStub(SiteLanguage::class);
    }

    #[Test]
    public function buildsDefaultsFromTitleAndSlug(): void
    {
        $meta = $this->subject()->build(['uid' => 2, 'title' => 'Team', 'slug' => '/team'], $this->language());

        self::assertSame('Team', $meta['seo']['title']);
        self::assertSame('index,follow', $meta['seo']['robots']);
        self::assertSame('/team', $meta['seo']['canonical']);
        self::assertSame('Team', $meta['seo']['og']['title']);
        self::assertArrayNotHasKey('description', $meta['seo']);

        self::assertSame('https://schema.org', $meta['schema']['@context']);
        self::assertSame('WebPage', $meta['schema']['@type']);
        self::assertSame('Team', $meta['schema']['name']);
    }

    #[Test]
    public function honoursNoIndexAndNoFollow(): void
    {
        $meta = $this->subject()->build(
            ['uid' => 2, 'title' => 'Team', 'no_index' => 1, 'no_follow' => 1],
            $this->language(),
        );

        self::assertSame('noindex,nofollow', $meta['seo']['robots']);
    }

    #[Test]
    public function prefersSeoTitleAndExposesDescription(): void
    {
        $meta = $this->subject()->build(
            ['uid' => 2, 'title' => 'Team', 'seo_title' => 'Our Team — Acme', 'description' => 'Meet us'],
            $this->language(),
        );

        self::assertSame('Our Team — Acme', $meta['seo']['title']);
        self::assertSame('Meet us', $meta['seo']['description']);
        self::assertSame('Meet us', $meta['seo']['og']['description']);
        self::assertSame('Meet us', $meta['schema']['description']);
        // schema.name stays the page title, not the SEO title.
        self::assertSame('Team', $meta['schema']['name']);
    }
}
